<?php
declare(strict_types=1);

namespace TgWatch;

/**
 * Kendi panelindeki (Azerice) servisleri sağlayıcı servisleriyle (İngilizce) otomatik eşleştirir.
 * Panel API'si "bu servis hangi sağlayıcı ID'sine bağlı" bilgisini vermediği için sinyaller birleştirilir:
 *   - aynı min/max (şart)
 *   - aynı platform (marka adları iki dilde de aynı yazılır)
 *   - addaki sayılar (50K, 30, 100/200…)
 *   - refill/cancel bayrakları
 *   - fiyat: satış ≈ a × sağlayıcı + b. a ve b kesin eşleşmelerden hesaplanır (kâr yüzdesi, sabit kâr ve kur farkı dahil).
 * Yüksek skorlu eşleşmeler bankaya "import" olarak girer, orta skorlular "kontrol bekliyor" olur.
 */
final class Matcher
{
    public const HIGH = 0.75;
    public const REVIEW = 0.5;
    public const PLATFORMS = ['instagram', 'tiktok', 'youtube', 'threads', 'telegram', 'facebook', 'twitter', 'spotify', 'soundcloud',
        'twitch', 'kick', 'linkedin', 'snapchat', 'pinterest', 'discord', 'reddit', 'quora', 'google', 'whatsapp', 'shopee',
        'deezer', 'vk', 'tumblr', 'likee', 'kwai', 'clubhouse', 'trovo', 'rumble', 'dailymotion', 'vimeo', 'audiomack', 'boomplay'];

    public function __construct(private Db $db)
    {
    }

    private static function nums(string $name): array
    {
        preg_match_all('/\d+(?:[.,]\d+)?\s?[KkMm]?/u', Normalizer::text($name), $m);
        return array_values(array_unique(array_map(static fn($n) => strtoupper(str_replace([' ', ','], ['', '.'], $n)), $m[0])));
    }

    public static function platform(string $name): string
    {
        foreach (preg_split('/[^\p{L}\p{N}]+/u', mb_strtolower(Normalizer::text($name))) ?: [] as $w) {
            if ($w !== '' && in_array($w, self::PLATFORMS, true)) {
                return $w;
            }
        }
        return '';
    }

    private static function jaccard(array $a, array $b): float
    {
        if (!$a && !$b) {
            return 0.6; // ikisinde de sayı yok: nötr
        }
        $i = count(array_intersect($a, $b));
        $u = count(array_unique(array_merge($a, $b)));
        return $u ? $i / $u : 0.0;
    }

    /**
     * @return array{own:int, providers:int, high:int, review:int, none:int, model:array{a:float,b:float,points:int,mode:string}, ms:int}
     */
    public function run(): array
    {
        $t0 = microtime(true);
        $own = $this->db->one("SELECT id FROM sources WHERE kind = 'own' ORDER BY id LIMIT 1");
        if ($own === null) {
            throw new \RuntimeException('Önce "Kendi panelim" kaynağını ekleyip senkronize edin.');
        }
        $ownRows = $this->db->all('SELECT * FROM remote_services WHERE source_id = ?', [(int)$own['id']]);
        $provRows = $this->db->all("SELECT r.* FROM remote_services r JOIN sources s ON s.id = r.source_id WHERE s.kind = 'provider'");
        if (!$ownRows || !$provRows) {
            throw new \RuntimeException('Eşleştirme için kendi paneliniz ve en az bir sağlayıcı senkronize edilmiş olmalı.');
        }

        $byMinMax = [];
        foreach ($provRows as $p) {
            $p['_nums'] = self::nums($p['name']);
            $p['_plat'] = self::platform($p['name'] . ' ' . $p['category']);
            $byMinMax[$p['min'] . '|' . $p['max']][] = $p;
        }

        // 1) aday listeleri
        $cands = [];
        foreach ($ownRows as $o) {
            $o['_nums'] = self::nums($o['name']);
            $o['_plat'] = self::platform($o['name'] . ' ' . $o['category']);
            $list = [];
            foreach ($byMinMax[$o['min'] . '|' . $o['max']] ?? [] as $p) {
                if ($o['_plat'] !== '' && $p['_plat'] !== '' && $o['_plat'] !== $p['_plat']) {
                    continue;
                }
                $list[] = $p;
            }
            $cands[] = [$o, $list];
        }

        // 2) fiyat modeli: tek adaylı ve sayıları uyumlu eşleşmelerden satış = a × sağlayıcı + b
        $pts = [];
        foreach ($cands as [$o, $list]) {
            if (count($list) === 1 && $o['rate'] > 0 && $list[0]['rate'] > 0 && self::jaccard($o['_nums'], $list[0]['_nums']) >= 0.5) {
                $pts[] = [(float)$list[0]['rate'], (float)$o['rate']];
            }
        }
        $model = self::fit($pts);

        // 3) skorla ve yaz
        $rejected = [];
        foreach ($this->db->all("SELECT own_service, provider_source, provider_service FROM tm WHERE status = 'rejected' AND own_service IS NOT NULL") as $r) {
            $rejected[$r['own_service'] . ':' . $r['provider_source'] . ':' . $r['provider_service']] = true;
        }
        $mem = new Memory($this->db);
        $high = $review = $none = 0;
        $catVotes = [];
        $this->db->tx(function () use ($cands, $model, $rejected, $mem, &$high, &$review, &$none, &$catVotes): void {
            $this->db->run("DELETE FROM tm WHERE origin = 'import' AND status IN ('ok','review')");
            foreach ($cands as [$o, $list]) {
                $scored = [];
                foreach ($list as $p) {
                    if (isset($rejected[$o['service_id'] . ':' . $p['source_id'] . ':' . $p['service_id']])) {
                        continue;
                    }
                    $price = 0.5;
                    if ($model['mode'] !== 'none' && $o['rate'] > 0 && $p['rate'] > 0) {
                        $pred = $model['a'] * $p['rate'] + $model['b'];
                        $price = max(0.0, 1 - abs($pred - $o['rate']) / max(0.15 * $o['rate'], 1e-9));
                    }
                    $flags = 1.0;
                    foreach (['refill', 'cancel'] as $f) {
                        if ($o[$f] !== null && $p[$f] !== null && (int)$o[$f] !== (int)$p[$f]) {
                            $flags -= 0.5;
                        }
                    }
                    $plat = ($o['_plat'] !== '' && $o['_plat'] === $p['_plat']) ? 1.0 : 0.5;
                    $s = 0.45 * $price + 0.3 * self::jaccard($o['_nums'], $p['_nums']) + 0.1 * $flags + 0.15 * $plat;
                    $scored[] = [$s, $p];
                }
                usort($scored, static fn($a, $b) => $b[0] <=> $a[0]);
                $best = $scored[0] ?? null;
                $second = $scored[1][0] ?? 0.0;
                if ($best === null || $best[0] < self::REVIEW) {
                    $none++;
                    continue;
                }
                $p = $best[1];
                $sure = $best[0] >= self::HIGH && ($best[0] - $second) >= 0.12;
                $mem->learn('service', $p['name'], $o['name'], 'import', [
                    'status' => $sure ? 'ok' : 'review', 'score' => round($best[0], 3),
                    'provider_source' => (int)$p['source_id'], 'provider_service' => (int)$p['service_id'], 'own_service' => (int)$o['service_id'],
                ]);
                if ($sure) {
                    $high++;
                    if ($p['category'] !== '' && $o['category'] !== '') {
                        $catVotes[$p['category']][$o['category']] = ($catVotes[$p['category']][$o['category']] ?? 0) + 1;
                    }
                } else {
                    $review++;
                }
            }
            $this->db->run("DELETE FROM tm WHERE kind = 'category' AND origin = 'import'");
            foreach ($catVotes as $pc => $votes) {
                arsort($votes);
                $mem->learn('category', $pc, (string)array_key_first($votes), 'import');
            }
        });
        $mem->rebuildSegments();

        $report = ['own' => count($ownRows), 'providers' => count($provRows), 'high' => $high, 'review' => $review, 'none' => $none,
            'categories' => count($catVotes), 'model' => $model, 'ms' => (int)((microtime(true) - $t0) * 1000), 'at' => time()];
        $this->db->setJson('match_report', $report);
        $this->db->log('info', 'bank', "Eşleştirme: $high kesin, $review kontrol bekliyor, $none eşleşmedi");
        return $report;
    }

    /**
     * Aykırı değerlere dayanıklı doğrusal uyum (Theil–Sen): eğim = ikili eğimlerin medyanı, sabit = artıkların medyanı.
     * Sonra yalnızca uyumlu noktalarla (±%15) en küçük karelerle inceltilir. Az nokta varsa oran medyanı.
     * @param list<array{0:float,1:float}> $pts [sağlayıcı, satış]
     * @return array{a:float,b:float,points:int,mode:string}
     */
    public static function fit(array $pts): array
    {
        if (count($pts) < 3) {
            return ['a' => 1.0, 'b' => 0.0, 'points' => count($pts), 'mode' => 'none'];
        }
        if (count($pts) < 6) {
            $r = array_map(static fn($p) => $p[1] / $p[0], $pts);
            sort($r);
            return ['a' => $r[intdiv(count($r), 2)], 'b' => 0.0, 'points' => count($pts), 'mode' => 'ratio'];
        }
        $sample = count($pts) > 400 ? array_slice($pts, 0, 400) : $pts;
        $slopes = [];
        $n = count($sample);
        for ($i = 0; $i < $n; $i++) {
            for ($j = $i + 1; $j < $n; $j++) {
                $dx = $sample[$j][0] - $sample[$i][0];
                if (abs($dx) > 1e-9) {
                    $slopes[] = ($sample[$j][1] - $sample[$i][1]) / $dx;
                }
            }
        }
        if (!$slopes) {
            return ['a' => 1.0, 'b' => 0.0, 'points' => count($pts), 'mode' => 'none'];
        }
        $a = self::median($slopes);
        $b = self::median(array_map(static fn($p) => $p[1] - $a * $p[0], $pts));
        $in = array_values(array_filter($pts, static fn($p) => abs($a * $p[0] + $b - $p[1]) <= 0.15 * max(abs($p[1]), 1e-9)));
        if (count($in) >= 6) {
            $k = count($in);
            $sx = $sy = $sxx = $sxy = 0.0;
            foreach ($in as [$x, $y]) {
                $sx += $x;
                $sy += $y;
                $sxx += $x * $x;
                $sxy += $x * $y;
            }
            $den = $k * $sxx - $sx * $sx;
            if (abs($den) > 1e-12) {
                $a = ($k * $sxy - $sx * $sy) / $den;
                $b = ($sy - $a * $sx) / $k;
            }
        }
        return ['a' => round($a, 4), 'b' => round($b, 4), 'points' => count($in), 'mode' => 'linear'];
    }

    private static function median(array $v): float
    {
        sort($v);
        $n = count($v);
        return $n % 2 ? (float)$v[intdiv($n, 2)] : ($v[$n / 2 - 1] + $v[$n / 2]) / 2;
    }
}
