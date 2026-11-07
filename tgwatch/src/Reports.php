<?php
declare(strict_types=1);

namespace TgWatch;

/** Rapor ve denetim hesapları (Raporlar ve Fiyat denetimi sayfaları). */
final class Reports
{
    public function __construct(private Db $db)
    {
    }

    /**
     * Kanal başına iş sayıları ve ortalama karar süresi.
     * @return array{channels:list<array>, total:array, via:array<string,int>, notices:array<string,int>, days:int}
     */
    public function channels(int $days): array
    {
        $days = max(1, min(365, $days));
        $cut = time() - $days * 86400;
        $rows = [];
        foreach ($this->db->all('SELECT channel, status, COUNT(*) c, SUM(updated_at - created_at) span FROM jobs WHERE created_at >= ? GROUP BY channel, status', [$cut]) as $r) {
            $ch = &$rows[$r['channel']];
            $ch ??= ['channel' => $r['channel'], 'jobs' => 0, 'approved' => 0, 'rejected' => 0, 'open' => 0, 'span' => 0, 'decided' => 0];
            $n = (int)$r['c'];
            $ch['jobs'] += $n;
            if (in_array($r['status'], ['approved', 'published'], true)) {
                $ch['approved'] += $n;
                $ch['span'] += (int)$r['span'];
                $ch['decided'] += $n;
            } elseif ($r['status'] === 'rejected') {
                $ch['rejected'] += $n;
                $ch['span'] += (int)$r['span'];
                $ch['decided'] += $n;
            } else {
                $ch['open'] += $n;
            }
            unset($ch);
        }
        $total = ['jobs' => 0, 'approved' => 0, 'rejected' => 0, 'open' => 0, 'span' => 0, 'decided' => 0];
        foreach ($rows as &$r) {
            $r['avg_seconds'] = $r['decided'] > 0 ? (int)round($r['span'] / $r['decided']) : null;
            $r['rate'] = $r['decided'] > 0 ? round(100 * $r['approved'] / $r['decided']) : null;
            foreach (['jobs', 'approved', 'rejected', 'open', 'span', 'decided'] as $k) {
                $total[$k] += $r[$k];
            }
        }
        unset($r);
        $total['avg_seconds'] = $total['decided'] > 0 ? (int)round($total['span'] / $total['decided']) : null;
        $total['rate'] = $total['decided'] > 0 ? round(100 * $total['approved'] / $total['decided']) : null;
        usort($rows, static fn($a, $b) => $b['jobs'] <=> $a['jobs']);

        $via = [];
        $services = 0;
        foreach ($this->db->all('SELECT items_json FROM jobs WHERE created_at >= ?', [$cut]) as $j) {
            foreach ((array)json_decode((string)$j['items_json'], true) as $it) {
                $v = (string)($it['via'] ?? 'rules');
                $via[$v] = ($via[$v] ?? 0) + 1;
                $services++;
            }
        }
        $total['services'] = $services;
        $notices = [];
        foreach ($this->db->all('SELECT kind, COUNT(*) c FROM notices WHERE created_at >= ? GROUP BY kind', [$cut]) as $n) {
            $notices[$n['kind']] = (int)$n['c'];
        }
        return ['channels' => array_values($rows), 'total' => $total, 'via' => $via, 'notices' => $notices, 'days' => $days];
    }

    /**
     * Fiyat denetimi: eşleşmiş servis çiftlerinde kendi fiyatın ile sağlayıcı fiyatından beklenen fiyat.
     * flag: loss (sağlayıcıdan ucuz, zarar), low (beklenenin altında), high (beklenenin üstünde), ok
     * @return list<array>
     */
    public function audit(float $tolerance = 0.10): array
    {
        $enrich = new Enrich($this->db);
        $out = [];
        $seen = [];
        $links = $this->db->all("SELECT DISTINCT provider_source, provider_service, own_service FROM tm WHERE kind = 'service' AND status IN ('ok','review') AND provider_source IS NOT NULL AND provider_service IS NOT NULL AND own_service IS NOT NULL");
        foreach ($links as $l) {
            if (isset($seen[$l['own_service']])) {
                continue;
            }
            $seen[$l['own_service']] = true;
            $prov = $enrich->remote((int)$l['provider_source'], (int)$l['provider_service']);
            $own = $this->db->one("SELECT r.* FROM remote_services r JOIN sources s ON s.id = r.source_id WHERE s.kind = 'own' AND r.service_id = ?", [(int)$l['own_service']]);
            if (!$prov || !$own || $prov['rate'] === null || $own['rate'] === null) {
                continue;
            }
            $pr = (float)$prov['rate'];
            $or = (float)$own['rate'];
            $want = $enrich->suggestPrice($pr);
            $flag = 'ok';
            if ($or < $pr) {
                $flag = 'loss';
            } elseif ($want !== null && $want > 0) {
                if ($or < $want * (1 - $tolerance)) {
                    $flag = 'low';
                } elseif ($or > $want * (1 + $tolerance)) {
                    $flag = 'high';
                }
            }
            $out[] = ['own_id' => (int)$own['service_id'], 'own_name' => (string)$own['name'], 'own_rate' => $or,
                'prov_id' => (int)$prov['service_id'], 'prov_name' => (string)$prov['name'], 'prov_rate' => $pr,
                'want' => $want, 'delta' => $want !== null && $want > 0 ? round(100 * ($or - $want) / $want, 1) : null, 'flag' => $flag];
        }
        $order = ['loss' => 0, 'low' => 1, 'high' => 2, 'ok' => 3];
        usort($out, static fn($a, $b) => [$order[$a['flag']], $a['own_id']] <=> [$order[$b['flag']], $b['own_id']]);
        return $out;
    }

    /** @return array{rows:list<array>, total:int} */
    public function catalog(string $q, string $source, int $page, int $per = 40): array
    {
        $where = [];
        $args = [];
        if ($source === 'own') {
            $where[] = "s.kind = 'own'";
        } elseif (ctype_digit($source) && $source !== '0') {
            $where[] = 's.id = ?';
            $args[] = (int)$source;
        }
        $q = trim($q);
        if ($q !== '') {
            if (ctype_digit($q)) {
                $where[] = '(r.service_id = ? OR r.name LIKE ?)';
                $args[] = (int)$q;
                $args[] = "%$q%";
            } else {
                foreach (preg_split('/\s+/u', $q) as $w) {
                    $where[] = '(r.name LIKE ? OR r.category LIKE ?)';
                    $args[] = '%' . $w . '%';
                    $args[] = '%' . $w . '%';
                }
            }
        }
        $w = $where ? 'WHERE ' . implode(' AND ', $where) : '';
        $from = "FROM remote_services r JOIN sources s ON s.id = r.source_id $w";
        $total = (int)$this->db->one("SELECT COUNT(*) c $from", $args)['c'];
        $rows = $this->db->all("SELECT r.*, s.name source_name, s.kind source_kind $from ORDER BY s.kind DESC, s.id, r.category, r.service_id LIMIT $per OFFSET " . (max(1, $page) - 1) * $per, $args);
        return ['rows' => $rows, 'total' => $total];
    }
}
