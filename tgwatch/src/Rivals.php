<?php
declare(strict_types=1);

namespace TgWatch;

/**
 * Rakip fiyat kıyası. Rakip = servis listesini standart SMM API ile veren başka bir panel (kind = 'rival').
 *
 * Her kendi servisin için her rakipte en benzer servis bulunur (aynı platform, ad benzerliği, min/max, sayılar).
 * Ad benzerliği iki yolla ölçülür: rakibin adı kurallarla Azericeye çevrilip senin adınla, ve senin adının İngilizce
 * karşılığı (bankadan) rakibin adıyla karşılaştırılır. Emin olunamayanlar "kontrol bekliyor" olur; Doğru/Yanlış ile karar verirsin.
 * Fiyatlar karşılaştırılırken rakip fiyatı × kaynağın kur çarpanı (fx) kullanılır (rakibin para birimi seninkinden farklıysa).
 */
final class Rivals
{
    public const MATCH = 0.62;
    public const REVIEW = 0.46;
    private const STOP = ['maks', 'max', 'min', 'gündə', 'saatda', 'day', 'hour', 'per', 'k', 'm', 'the', 'and', 'for', 'with', 'of', 'to', 'in'];

    public function __construct(private Db $db, private array $config = [])
    {
    }

    /** @return list<array> */
    public function sources(): array
    {
        return $this->db->all("SELECT * FROM sources WHERE kind = 'rival' ORDER BY id");
    }

    public function minutes(): int
    {
        return max(30, min(10080, (int)($this->config['rival_minutes'] ?? 360)));
    }

    public function alertPct(): float
    {
        return max(1, min(90, (int)($this->config['rival_alert_pct'] ?? 10))) / 100;
    }

    // ------------------------------------------------------------------ eşleştirme

    private static function tokens(string $s): array
    {
        $s = mb_strtolower(Normalizer::text($s));
        $s = NameTemplate::stripEmoji($s);
        $w = preg_split('/[^\p{L}\p{N}]+/u', $s) ?: [];
        $w = array_filter($w, static fn($x) => $x !== '' && mb_strlen($x) >= 2 && !preg_match('/^\d/u', $x) && !in_array($x, self::STOP, true));
        return array_values(array_unique($w));
    }

    private static function jac(array $a, array $b): float
    {
        if (!$a || !$b) {
            return 0.0;
        }
        return count(array_intersect($a, $b)) / count(array_unique(array_merge($a, $b)));
    }

    private static function minmax(array $o, array $r): float
    {
        if ($o['min'] === null || $o['max'] === null || $r['min'] === null || $r['max'] === null) {
            return 0.0;
        }
        if ((int)$o['min'] === (int)$r['min'] && (int)$o['max'] === (int)$r['max']) {
            return 1.0;
        }
        if ((int)$o['max'] === (int)$r['max']) {
            return 0.6;
        }
        $hi = max((int)$o['max'], (int)$r['max']);
        $lo = max(1, min((int)$o['max'], (int)$r['max']));
        return $hi / $lo <= 1.25 ? 0.25 : 0.0;
    }

    /**
     * Kendi servislerini rakiple eşleştirir; verilmiş (confirmed/rejected) kararlar korunur.
     * @return array{matched:int,review:int,none:int}
     */
    public function match(int $rivalId): array
    {
        $own = $this->db->one("SELECT id FROM sources WHERE kind = 'own' ORDER BY id LIMIT 1");
        if (!$own) {
            throw new \RuntimeException('Önce Çeviri bankası → Kaynaklar\'dan kendi panelini ekle.');
        }
        $ownRows = $this->db->all('SELECT * FROM remote_services WHERE source_id = ?', [(int)$own['id']]);
        $rivRows = $this->db->all('SELECT * FROM remote_services WHERE source_id = ?', [$rivalId]);
        $en = [];
        foreach ($this->db->all("SELECT own_service, en FROM tm WHERE kind = 'service' AND status = 'ok' AND own_service IS NOT NULL") as $r) {
            $en[(int)$r['own_service']] ??= (string)$r['en'];
        }
        $dict = Dictionary::load($this->db);
        $tr = new Translator(array_merge($dict, ['options' => []]));
        $rv = [];
        foreach ($rivRows as $r) {
            $az = implode(' ', array_map(static fn($seg) => $tr->translate($seg)['text'], Memory::splitSegments((string)$r['name'])));
            $rv[] = ['row' => $r, 'plat' => Matcher::platform((string)$r['name']), 'tok' => self::tokens((string)$r['name']), 'az' => self::tokens($az), 'nums' => Matcher::nums((string)$r['name'])];
        }
        $byPlat = [];
        foreach ($rv as $i => $x) {
            $byPlat[$x['plat']][] = $i;
        }
        $keep = [];
        foreach ($this->db->all('SELECT * FROM rival_map WHERE rival_source = ?', [$rivalId]) as $m) {
            $keep[(int)$m['own_service']] = $m;
        }
        $rejected = [];
        foreach ($keep as $o => $m) {
            if ($m['status'] === 'rejected') {
                $rejected[$o . ':' . $m['rival_service']] = true;
            }
        }
        $out = ['matched' => 0, 'review' => 0, 'none' => 0];
        $this->db->tx(function () use ($ownRows, $rv, $byPlat, $en, $keep, $rejected, $rivalId, &$out): void {
            foreach ($ownRows as $o) {
                $oid = (int)$o['service_id'];
                if (isset($keep[$oid]) && $keep[$oid]['status'] === 'confirmed') {
                    $out['matched']++;
                    continue;
                }
                $oPlat = Matcher::platform((string)$o['name']) ?: Matcher::platform($en[$oid] ?? '');
                $oAz = self::tokens((string)$o['name']);
                $oEn = self::tokens($en[$oid] ?? '');
                $oNums = Matcher::nums((string)$o['name']);
                $pool = $oPlat !== '' ? ($byPlat[$oPlat] ?? []) : array_keys($rv);
                $scored = [];
                foreach ($pool as $i) {
                    $x = $rv[$i];
                    if (isset($rejected[$oid . ':' . $x['row']['service_id']])) {
                        continue;
                    }
                    $sim = max(self::jac($oAz, $x['az']), $oEn ? self::jac($oEn, $x['tok']) : 0.0);
                    if ($sim < 0.2) {
                        continue;
                    }
                    $nj = ($oNums && $x['nums']) ? self::jac($oNums, $x['nums']) : 0.5;
                    $scored[] = [0.55 * $sim + 0.25 * self::minmax($o, $x['row']) + 0.2 * $nj, $x['row']];
                }
                usort($scored, static fn($a, $b) => $b[0] <=> $a[0]);
                $best = $scored[0] ?? null;
                if ($best === null || $best[0] < self::REVIEW) {
                    $this->db->run('DELETE FROM rival_map WHERE own_service = ? AND rival_source = ? AND status = \'auto\'', [$oid, $rivalId]);
                    $out['none']++;
                    continue;
                }
                $sure = $best[0] >= self::MATCH && ($best[0] - ($scored[1][0] ?? 0.0)) >= 0.04;
                $this->db->run('INSERT INTO rival_map(own_service, rival_source, rival_service, score, status) VALUES(?,?,?,?,?)
                    ON CONFLICT(own_service, rival_source) DO UPDATE SET rival_service = excluded.rival_service, score = excluded.score, status = excluded.status',
                    [$oid, $rivalId, (int)$best[1]['service_id'], round($best[0], 3), $sure ? 'auto' : 'review']);
                $sure ? $out['matched']++ : $out['review']++;
            }
        });
        return $out;
    }

    // ------------------------------------------------------------------ kıyas

    /**
     * @return array{rows:list<array>,total:int,summary:array,rivals:list<array>}
     */
    public function compare(string $filter = 'all', string $q = '', int $page = 1, int $per = 40): array
    {
        $own = $this->db->one("SELECT id FROM sources WHERE kind = 'own' ORDER BY id LIMIT 1");
        $rivals = $this->sources();
        $empty = ['rows' => [], 'total' => 0, 'summary' => ['compared' => 0, 'pricier' => 0, 'cheaper' => 0, 'same' => 0, 'review' => 0, 'avg' => 0.0, 'unmatched' => 0], 'rivals' => $rivals];
        if (!$own || !$rivals) {
            return $empty;
        }
        $ownRows = $this->db->all('SELECT * FROM remote_services WHERE source_id = ? ORDER BY category, service_id', [(int)$own['id']]);
        $map = [];
        foreach ($this->db->all("SELECT m.*, r.name rname, r.rate rrate, r.min rmin, r.max rmax FROM rival_map m LEFT JOIN remote_services r ON r.source_id = m.rival_source AND r.service_id = m.rival_service WHERE m.status != 'rejected'") as $m) {
            $map[(int)$m['own_service']][(int)$m['rival_source']] = $m;
        }
        $fx = array_column($rivals, 'fx', 'id');
        $pct = $this->alertPct();
        $rows = [];
        $sum = $empty['summary'];
        $diffs = [];
        foreach ($ownRows as $o) {
            $oid = (int)$o['service_id'];
            $cells = [];
            $worst = null;
            $hasReview = false;
            foreach ($rivals as $rv) {
                $m = $map[$oid][(int)$rv['id']] ?? null;
                if (!$m || $m['rrate'] === null) {
                    $cells[(int)$rv['id']] = null;
                    continue;
                }
                $rate = (float)$m['rrate'] * (float)($fx[$rv['id']] ?? 1);
                $mine = (float)$o['rate'];
                $diff = $rate > 0 ? ($mine - $rate) / $rate : 0.0; // + : ben pahalıyım
                $cells[(int)$rv['id']] = ['name' => (string)$m['rname'], 'rate' => $rate, 'diff' => $diff, 'status' => $m['status'], 'service' => (int)$m['rival_service'], 'score' => (float)$m['score']];
                $worst = $worst === null ? $diff : max($worst, $diff);
                $hasReview = $hasReview || $m['status'] === 'review';
            }
            if ($worst === null) {
                $sum['unmatched']++;
                $state = 'none';
            } else {
                $sum['compared']++;
                $diffs[] = $worst;
                $state = $worst > $pct ? 'pricier' : ($worst < -$pct ? 'cheaper' : 'same');
                $sum[$state]++;
                $sum['review'] += $hasReview ? 1 : 0;
            }
            if ($filter === 'pricier' && $state !== 'pricier' || $filter === 'cheaper' && $state !== 'cheaper' || $filter === 'review' && !$hasReview || $filter === 'none' && $state !== 'none') {
                continue;
            }
            if ($filter === 'all' && $state === 'none') {
                continue;
            }
            if ($q !== '' && stripos($o['name'] . ' ' . $oid, $q) === false) {
                continue;
            }
            $rows[] = ['id' => $oid, 'name' => (string)$o['name'], 'category' => (string)$o['category'], 'rate' => (float)$o['rate'], 'cells' => $cells, 'state' => $state, 'worst' => $worst];
        }
        $sum['avg'] = $diffs ? array_sum($diffs) / count($diffs) : 0.0;
        if ($filter === 'pricier') {
            usort($rows, static fn($a, $b) => $b['worst'] <=> $a['worst']);
        }
        $total = count($rows);
        return ['rows' => array_slice($rows, ($page - 1) * $per, $per), 'total' => $total, 'summary' => $sum, 'rivals' => $rivals];
    }

    // ------------------------------------------------------------------ senkron + bildirim

    /** Rakibin listesini çeker, eşleştirir ve bildirim gerekenleri döndürür. */
    public function refresh(int $rivalId): array
    {
        SmmApi::syncDiff($this->db, $rivalId, false, false);
        $m = $this->match($rivalId);
        $this->db->run('UPDATE sources SET watch_at = ?, watch_note = ? WHERE id = ?', [time(), "{$m['matched']} eşleşti, {$m['review']} kontrol bekliyor", $rivalId]);
        return $m;
    }

    /** cron: aralığı dolan rakipleri yeniler, bota haber verilecek durumları gönderir. */
    public function cron(int $deadline = 0, bool $force = false): array
    {
        $out = [];
        foreach ($this->sources() as $s) {
            if ($deadline && time() > $deadline - 12) {
                break;
            }
            if (!$force && $s['watch_at'] && (int)$s['watch_at'] > time() - $this->minutes() * 60 + 30) {
                continue;
            }
            try {
                $out[(int)$s['id']] = ['name' => $s['name'], 'ok' => true] + $this->refresh((int)$s['id']);
            } catch (\Throwable $e) {
                $this->db->run('UPDATE sources SET watch_at = ?, watch_note = ? WHERE id = ?', [time(), 'Hata: ' . mb_substr($e->getMessage(), 0, 160), (int)$s['id']]);
                $this->db->log('error', 'rival', "{$s['name']}: " . $e->getMessage());
                $out[(int)$s['id']] = ['name' => $s['name'], 'ok' => false, 'error' => $e->getMessage()];
            }
        }
        if ($out) {
            $this->notify();
        }
        return $out;
    }

    /**
     * Yeni "rakip ucuz" durumlarını ve büyük fiyat değişimlerini tek mesajla bildirir.
     * @return list<string> gönderilen satırlar
     */
    public function notify(?Alerts $alerts = null): array
    {
        $pct = $this->alertPct();
        $alerts ??= new Alerts($this->db, null, $this->config);
        $c = $this->compare('all', '', 1, 100000);
        $state = [];
        foreach ($this->db->all('SELECT * FROM rival_state') as $r) {
            $state[$r['own_service'] . ':' . $r['rival_source']] = $r;
        }
        $lines = [];
        $upd = $this->db->pdo->prepare('INSERT INTO rival_state(own_service, rival_source, flag, rival_rate, my_rate, at) VALUES(?,?,?,?,?,?)
            ON CONFLICT(own_service, rival_source) DO UPDATE SET flag = excluded.flag, rival_rate = excluded.rival_rate, my_rate = excluded.my_rate, at = excluded.at');
        $names = array_column($c['rivals'], 'name', 'id');
        foreach ($c['rows'] as $r) {
            foreach ($r['cells'] as $rid => $cell) {
                if ($cell === null || $cell['status'] === 'review') {
                    continue;
                }
                $key = $r['id'] . ':' . $rid;
                $prev = $state[$key] ?? null;
                $flag = $cell['diff'] > $pct ? 'pricier' : '';
                $changed = $prev && $prev['rival_rate'] > 0 && abs($cell['rate'] - (float)$prev['rival_rate']) / (float)$prev['rival_rate'] >= $pct;
                if ($flag === 'pricier' && (!$prev || $prev['flag'] !== 'pricier' || $changed)) {
                    $lines[] = '• <b>' . Telegram::esc(mb_substr($r['name'], 0, 60)) . '</b> (#' . $r['id'] . ")\n   sen " . self::n($r['rate']) . ', ' . Telegram::esc((string)($names[$rid] ?? '?')) . ' ' . self::n($cell['rate']) . ' (sen %' . round($cell['diff'] * 100) . ' pahalı)';
                }
                if (!$prev || $prev['flag'] !== $flag || $changed) {
                    $upd->execute([$r['id'], $rid, $flag, $cell['rate'], $r['rate'], time()]);
                }
            }
        }
        if ($lines) {
            $more = count($lines) > 15 ? "\n… ve " . (count($lines) - 15) . ' servis daha (panel → Rakipler)' : '';
            $alerts->notify('rival', '⚖️ <b>Rakip senden ucuz</b> (%' . round($pct * 100) . "+ fark)\n" . implode("\n", array_slice($lines, 0, 15)) . $more);
        }
        return $lines;
    }

    private static function n(float $v): string
    {
        return rtrim(rtrim(number_format($v, 4, '.', ''), '0'), '.');
    }
}
