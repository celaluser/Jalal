<?php
declare(strict_types=1);

namespace TgWatch;

/**
 * Çeviri bankası (translation memory).
 *
 * - tm: tam ad çiftleri (kind = service | category). status ok = kullanılır, review = onay bekler, rejected = yok sayılır.
 * - tm_segments: " | " ile bölünmüş parça çiftleri. Sayılar şablona çevrilir:
 *   "30 Days Refill" ↔ "30 Gün Qarantili" öğrenilince "#0 days refill" → "#0 Gün Qarantili" olur,
 *   böylece "60 Days Refill" de bankadan çevrilir.
 * Kaynak önceliği: manual (elle) > approved (onaylanan iş) > confirmed (onaylanan eşleşme) > import (otomatik eşleşme).
 */
final class Memory
{
    public const ORIGIN_RANK = ['manual' => 4, 'approved' => 3, 'confirmed' => 2, 'import' => 1];
    private const NUM = '/(?<![\p{L}\p{N}#])\d+(?:[.,]\d+)?\s?[KkMm]?(?![\p{L}\p{N}])/u';

    /** @var array<string,string>|null en_norm => az (en iyi oy) */
    private ?array $segments = null;

    public function __construct(private Db $db)
    {
    }

    public static function norm(string $s): string
    {
        $s = Normalizer::text($s);
        $s = preg_replace('/\s*\|\s*/u', ' | ', $s) ?? $s;
        $s = preg_replace('/\s+/u', ' ', $s) ?? $s;
        return mb_strtolower(trim($s, " \t|"));
    }

    public static function splitSegments(string $s): array
    {
        return array_values(array_filter(array_map('trim', preg_split('/\s*\|\s*/u', Normalizer::text($s)) ?: []), static fn($x) => $x !== ''));
    }

    private static function numKey(string $n): string
    {
        return strtoupper(str_replace([' ', ','], ['', '.'], $n));
    }

    /**
     * Sayıları #0, #1… ile değiştirir.
     * @return array{0:string,1:list<string>} [şablon, sayılar]
     */
    public static function template(string $s): array
    {
        $nums = [];
        $t = preg_replace_callback(self::NUM, static function (array $m) use (&$nums): string {
            $nums[] = trim($m[0]);
            return '#' . (count($nums) - 1);
        }, $s) ?? $s;
        return [$t, $nums];
    }

    /**
     * Çiftin şablonlarını üretir. AZ tarafında EN'de olmayan bir sayı varsa şablon yapılmaz (düz kayıt).
     * @return array{0:string,1:string} [en_şablon_norm, az_şablon]
     */
    public static function templatePair(string $en, string $az): array
    {
        [$enT, $enNums] = self::template($en);
        $keys = array_map([self::class, 'numKey'], $enNums);
        $ok = true;
        $used = [];
        $azT = preg_replace_callback(self::NUM, static function (array $m) use ($keys, &$used, &$ok): string {
            $k = self::numKey(trim($m[0]));
            foreach ($keys as $i => $kk) {
                if ($kk === $k && !isset($used[$i])) {
                    $used[$i] = true;
                    return '#' . $i;
                }
            }
            $ok = false;
            return $m[0];
        }, $az) ?? $az;
        if (!$ok || count($used) !== count($keys)) {
            return [self::norm($en), trim($az)];
        }
        return [self::norm($enT), trim($azT)];
    }

    // ------------------------------------------------------------ öğrenme

    /** Tam ad çiftini ekler/günceller ve parçalarını öğrenir. */
    public function learn(string $kind, string $en, string $az, string $origin, array $meta = []): void
    {
        $en = trim(Normalizer::text($en));
        $az = trim($az);
        if ($en === '' || $az === '') {
            return;
        }
        $status = $meta['status'] ?? 'ok';
        $this->db->run(
            'INSERT INTO tm(kind, en_norm, en, az, origin, status, score, provider_source, provider_service, own_service, updated_at) VALUES(?,?,?,?,?,?,?,?,?,?,?)
             ON CONFLICT(kind, en_norm, az) DO UPDATE SET
               origin = CASE WHEN excluded.status = \'ok\' AND tm.status != \'ok\' THEN excluded.origin ELSE tm.origin END,
               status = CASE WHEN tm.status = \'rejected\' THEN tm.status WHEN excluded.status = \'ok\' THEN \'ok\' ELSE tm.status END,
               updated_at = excluded.updated_at',
            [$kind, self::norm($en), $en, $az, $origin, $status, (float)($meta['score'] ?? 1), $meta['provider_source'] ?? null, $meta['provider_service'] ?? null, $meta['own_service'] ?? null, time()]
        );
        // Daha yüksek öncelikli kaynak gelirse yükselt.
        $this->db->run('UPDATE tm SET origin = ? WHERE kind = ? AND en_norm = ? AND az = ? AND status = \'ok\' AND ? > (CASE origin WHEN \'manual\' THEN 4 WHEN \'approved\' THEN 3 WHEN \'confirmed\' THEN 2 ELSE 1 END)',
            [$origin, $kind, self::norm($en), $az, self::ORIGIN_RANK[$origin] ?? 1]);
        if ($kind === 'service' && $status === 'ok') {
            $this->learnSegments($en, $az, (float)(self::ORIGIN_RANK[$origin] ?? 1));
        }
    }

    /** Parça sayıları eşitse parçaları sırayla eşler; ortak baş/son kelimeleri (marka, emoji) ayırıp çekirdeği de öğrenir. */
    public function learnSegments(string $en, string $az, float $weight = 1.0): int
    {
        $es = self::splitSegments($en);
        $as = self::splitSegments($az);
        if (count($es) !== count($as) || !$es) {
            return 0;
        }
        $n = 0;
        foreach ($es as $i => $e) {
            foreach (self::variants($e, $as[$i]) as [$ve, $va]) {
                [$te, $ta] = self::templatePair($ve, $va);
                if ($te === '' || $ta === '' || $te === mb_strtolower($ta)) {
                    continue; // değişmeyen parça (ör. "♻️") öğrenmeye değmez
                }
                $this->db->run('INSERT INTO tm_segments(en_norm, az, n) VALUES(?,?,?) ON CONFLICT(en_norm, az) DO UPDATE SET n = n + excluded.n', [$te, $ta, $weight]);
                $n++;
            }
        }
        $this->segments = null;
        return $n;
    }

    /** Parçanın kendisi + ortak baş/son kelimeleri atılmış çekirdeği. */
    private static function variants(string $en, string $az): array
    {
        $out = [[$en, $az]];
        $et = preg_split('/\s+/u', $en) ?: [];
        $at = preg_split('/\s+/u', $az) ?: [];
        $lead = 0;
        while ($lead < count($et) - 1 && $lead < count($at) - 1 && mb_strtolower($et[$lead]) === mb_strtolower($at[$lead])) {
            $lead++;
        }
        $trail = 0;
        while ($trail < count($et) - $lead - 1 && $trail < count($at) - $lead - 1
            && mb_strtolower($et[count($et) - 1 - $trail]) === mb_strtolower($at[count($at) - 1 - $trail])) {
            $trail++;
        }
        if ($lead + $trail > 0) {
            $ce = implode(' ', array_slice($et, $lead, count($et) - $lead - $trail));
            $ca = implode(' ', array_slice($at, $lead, count($at) - $lead - $trail));
            if ($ce !== '' && $ca !== '') {
                $out[] = [$ce, $ca];
            }
        }
        return $out;
    }

    /** Parça tablosunu bankadaki onaylı çiftlerden baştan kurar (silme/düzeltme sonrası). */
    public function rebuildSegments(): int
    {
        $this->db->run('DELETE FROM tm_segments');
        $n = 0;
        $this->db->tx(function () use (&$n): void {
            foreach ($this->db->all("SELECT en, az, origin FROM tm WHERE kind = 'service' AND status = 'ok'") as $r) {
                $n += $this->learnSegments($r['en'], $r['az'], (float)(self::ORIGIN_RANK[$r['origin']] ?? 1));
            }
        });
        $this->segments = null;
        return $n;
    }

    // ------------------------------------------------------------ arama

    /** Tam ad için bankadaki en güvenilir karşılık. */
    public function lookup(string $kind, string $en): ?string
    {
        $row = $this->db->one("SELECT az FROM tm WHERE kind = ? AND en_norm = ? AND status = 'ok'
            ORDER BY CASE origin WHEN 'manual' THEN 4 WHEN 'approved' THEN 3 WHEN 'confirmed' THEN 2 ELSE 1 END DESC, updated_at DESC LIMIT 1", [$kind, self::norm($en)]);
        return $row['az'] ?? null;
    }

    private function segmentMap(): array
    {
        if ($this->segments === null) {
            $this->segments = [];
            $best = [];
            foreach ($this->db->all('SELECT en_norm, az, n FROM tm_segments') as $r) {
                if (!isset($best[$r['en_norm']]) || $r['n'] > $best[$r['en_norm']]) {
                    $best[$r['en_norm']] = $r['n'];
                    $this->segments[$r['en_norm']] = $r['az'];
                }
            }
        }
        return $this->segments;
    }

    /**
     * Tek parçayı bankadan çevirir. Önce tam parça, sonra baştaki marka/sonraki emoji gibi kelimeler ayrılıp çekirdek denenir.
     * @param array<string,bool> $keep küçük harf marka kelimeleri
     */
    public function lookupSegment(string $seg, array $keep = []): ?string
    {
        $map = $this->segmentMap();
        if (!$map) {
            return null;
        }
        $try = function (string $s) use ($map): ?string {
            [$t, $nums] = self::template($s);
            $k = self::norm($t);
            if (!isset($map[$k])) {
                return null;
            }
            return preg_replace_callback('/#(\d+)/', static fn($m) => $nums[(int)$m[1]] ?? $m[0], $map[$k]);
        };
        $hit = $try($seg);
        if ($hit !== null) {
            return $hit;
        }
        // Baştaki marka kelimelerini ve sondaki emoji/simge kelimelerini ayır, çekirdeği dene.
        $tok = preg_split('/\s+/u', trim($seg)) ?: [];
        $lead = [];
        while (count($tok) > 1 && (isset($keep[mb_strtolower($tok[0])]) || !preg_match('/\p{L}/u', $tok[0]))) {
            $lead[] = array_shift($tok);
        }
        $trail = [];
        while (count($tok) > 1 && !preg_match('/[\p{L}\p{N}]/u', end($tok))) {
            array_unshift($trail, array_pop($tok));
        }
        if (!$lead && !$trail) {
            return null;
        }
        $core = $try(implode(' ', $tok));
        return $core === null ? null : trim(implode(' ', array_merge($lead, [$core], $trail)));
    }

    /**
     * Claude'a örnek olarak verilecek en benzer onaylı çiftler (ortak kelime skoruna göre).
     * @return list<array{en:string,az:string}>
     */
    public function similar(string $en, int $limit = 8): array
    {
        $words = self::words($en);
        if (!$words) {
            return [];
        }
        $scored = [];
        foreach ($this->db->all("SELECT en, az, origin FROM tm WHERE kind = 'service' AND status = 'ok' ORDER BY id DESC LIMIT 3000") as $r) {
            $w = self::words($r['en']);
            $common = count(array_intersect_key($words, $w));
            if ($common === 0) {
                continue;
            }
            $score = $common / (count($words) + count($w) - $common) + 0.02 * (self::ORIGIN_RANK[$r['origin']] ?? 1);
            $scored[] = [$score, $r['en'], $r['az']];
        }
        usort($scored, static fn($a, $b) => $b[0] <=> $a[0]);
        $out = [];
        $seen = [];
        foreach ($scored as [, $e, $a]) {
            if (isset($seen[$a])) {
                continue;
            }
            $seen[$a] = true;
            $out[] = ['en' => $e, 'az' => $a];
            if (count($out) >= $limit) {
                break;
            }
        }
        return $out;
    }

    /** Kendi panelindeki Azerice adlardan (eşleşmemiş olanlar dahil) aynı platformdan tarz örnekleri. */
    public function styleExamples(string $en, int $limit = 6): array
    {
        $own = $this->db->one("SELECT id FROM sources WHERE kind = 'own' ORDER BY id LIMIT 1");
        if ($own === null) {
            return [];
        }
        $first = mb_strtolower((string)(preg_split('/\s+/u', Normalizer::text(trim($en)))[0] ?? ''));
        if ($first === '' || mb_strlen($first) < 2) {
            return [];
        }
        $rows = $this->db->all('SELECT name FROM remote_services WHERE source_id = ? AND lower(name) LIKE ? ORDER BY service_id DESC LIMIT ?', [(int)$own['id'], $first . '%', $limit]);
        return array_column($rows, 'name');
    }

    private static function words(string $s): array
    {
        preg_match_all('/\p{L}{2,}/u', mb_strtolower(Normalizer::text($s)), $m);
        return array_fill_keys($m[0], true);
    }

    public function stats(): array
    {
        $r = [];
        foreach ($this->db->all('SELECT kind, status, COUNT(*) c FROM tm GROUP BY kind, status') as $x) {
            $r[$x['kind']][$x['status']] = (int)$x['c'];
        }
        $r['segments'] = (int)$this->db->one('SELECT COUNT(DISTINCT en_norm) c FROM tm_segments')['c'];
        return $r;
    }
}
