<?php
if (!defined('IGFP_ADMIN')) { http_response_code(403); exit('Forbidden'); }
/**
 * İstatistik hesaplama yardımcıları.
 * stats.json ve versions.json'ı okuyup panele hazır tablolar üretir.
 */

/** Seçilebilir aralıklar */
function st_ranges(): array {
    return [
        '7'   => 'Son 7 gün',
        '30'  => 'Son 30 gün',
        '90'  => 'Son 3 ay',
        '180' => 'Son 6 ay',
        '365' => 'Son 1 yıl',
        'all' => 'Tümü',
    ];
}

/** Aralığın başlangıç tarihi (Y-m-d) — 'all' ise en eski kayıt */
function st_start_date(array $stats, string $range): string {
    if ($range === 'all') {
        $keys = array_keys($stats);
        sort($keys);
        return $keys ? $keys[0] : date('Y-m-d');
    }
    $n = max(1, (int) $range);
    return date('Y-m-d', strtotime('-' . ($n - 1) . ' days'));
}

/** Aralıktaki günleri boşlukları doldurarak döndürür: [ 'Y-m-d' => ['view'=>x,'click'=>y] ] */
function st_daily(array $stats, string $from, string $to): array {
    $out = [];
    $cur = strtotime($from);
    $end = strtotime($to);
    if ($cur > $end) return $out;
    // çok uzun aralıklarda gün sayısını sınırla (güvenlik)
    $guard = 0;
    while ($cur <= $end && $guard++ < 800) {
        $d = date('Y-m-d', $cur);
        $out[$d] = [
            'view'  => (int) ($stats[$d]['view'] ?? 0),
            'click' => (int) ($stats[$d]['click'] ?? 0),
        ];
        $cur = strtotime('+1 day', $cur);
    }
    return $out;
}

/**
 * Grafik için kova (bucket) üretir. Gün sayısı çoksa haftalık/aylık gruplar.
 * @return array [ ['label'=>'12.08','full'=>'12.08.2026','view'=>x,'click'=>y], ... ]
 */
function st_buckets(array $daily): array {
    $n = count($daily);
    if ($n === 0) return [];

    if ($n <= 31)      $mode = 'day';
    elseif ($n <= 130) $mode = 'week';
    else               $mode = 'month';

    $buckets = [];
    foreach ($daily as $d => $v) {
        $ts = strtotime($d);
        if ($mode === 'day') {
            $key   = $d;
            $label = date('d', $ts);
            $full  = date('d.m.Y', $ts);
        } elseif ($mode === 'week') {
            $key   = date('o-W', $ts);
            $mon   = strtotime('monday this week', $ts);
            $label = date('d.m', $mon);
            $full  = date('d.m', $mon) . ' haftası';
        } else {
            $key   = date('Y-m', $ts);
            $label = date('m.y', $ts);
            $full  = date('m.Y', $ts);
        }
        if (!isset($buckets[$key])) {
            $buckets[$key] = ['label' => $label, 'full' => $full, 'view' => 0, 'click' => 0];
        }
        $buckets[$key]['view']  += $v['view'];
        $buckets[$key]['click'] += $v['click'];
    }
    return array_values($buckets);
}

/**
 * Aralıktaki sürüm kırılımı.
 * @return array [ vid => ['view'=>x,'click'=>y,'svc'=>[id=>n],'days'=>k,'first'=>'Y-m-d','last'=>'Y-m-d'] ]
 */
function st_versions(array $stats, string $from, string $to): array {
    $out = [];
    foreach ($stats as $day => $row) {
        if ($day < $from || $day > $to) continue;
        if (!isset($row['v']) || !is_array($row['v'])) continue;
        foreach ($row['v'] as $vid => $v) {
            if (!isset($out[$vid])) {
                $out[$vid] = ['view' => 0, 'click' => 0, 'svc' => [], 'days' => 0,
                              'first' => $day, 'last' => $day];
            }
            $out[$vid]['view']  += (int) ($v['view'] ?? 0);
            $out[$vid]['click'] += (int) ($v['click'] ?? 0);
            $out[$vid]['days']++;
            if ($day < $out[$vid]['first']) $out[$vid]['first'] = $day;
            if ($day > $out[$vid]['last'])  $out[$vid]['last']  = $day;
            foreach ((array) ($v['svc'] ?? []) as $sid => $n) {
                $out[$vid]['svc'][$sid] = ((int) ($out[$vid]['svc'][$sid] ?? 0)) + (int) $n;
            }
        }
    }
    uasort($out, function ($a, $b) {
        if ($b['last'] !== $a['last']) return strcmp($b['last'], $a['last']);
        return $b['view'] <=> $a['view'];
    });
    return $out;
}

/**
 * Aralıktaki popup kırılımı.
 * @return array [ pid => ['view'=>x,'click'=>y] ]  (görüntüye göre azalan)
 */
function st_popups(array $stats, string $from, string $to): array {
    $out = [];
    foreach ($stats as $day => $row) {
        if ($day < $from || $day > $to) continue;
        if (!isset($row['p']) || !is_array($row['p'])) continue;
        foreach ($row['p'] as $pid => $v) {
            if (!isset($out[$pid])) $out[$pid] = ['view' => 0, 'click' => 0];
            $out[$pid]['view']  += (int) ($v['view'] ?? 0);
            $out[$pid]['click'] += (int) ($v['click'] ?? 0);
        }
    }
    uasort($out, function ($a, $b) { return $b['view'] <=> $a['view']; });
    return $out;
}

/** Aralıktaki toplam servis tıklamaları: [ servisID => adet ] */
function st_services(array $stats, string $from, string $to): array {
    $out = [];
    foreach ($stats as $day => $row) {
        if ($day < $from || $day > $to) continue;
        foreach ((array) ($row['svc'] ?? []) as $sid => $n) {
            $out[$sid] = ((int) ($out[$sid] ?? 0)) + (int) $n;
        }
    }
    arsort($out);
    return $out;
}

/** Servis ID'sinden okunabilir isim bul (sürüm geçmişinden) */
function st_service_name(array $versions, string $sid): string {
    foreach ($versions as $v) {
        foreach ((array) ($v['services'] ?? []) as $s) {
            if ((string) ($s['id'] ?? '') === $sid) return (string) ($s['name'] ?? '');
        }
    }
    return '';
}

/** Tıklama oranı yüzdesi */
function st_ctr(int $view, int $click): string {
    return $view > 0 ? number_format($click / $view * 100, 1) : '0.0';
}

/**
 * Genel kırılım okuyucu: 'dev' | 'src' | 'pg'
 * @return array [ anahtar => ['view'=>x,'click'=>y] ]  görüntüye göre azalan
 */
function st_breakdown(array $stats, string $from, string $to, string $key): array {
    $out = [];
    foreach ($stats as $day => $row) {
        if ($day < $from || $day > $to) continue;
        if (!isset($row[$key]) || !is_array($row[$key])) continue;
        foreach ($row[$key] as $k => $v) {
            if (!is_array($v)) continue;
            if (!isset($out[$k])) $out[$k] = ['view' => 0, 'click' => 0];
            $out[$k]['view']  += (int) ($v['view'] ?? 0);
            $out[$k]['click'] += (int) ($v['click'] ?? 0);
        }
    }
    uasort($out, function ($a, $b) { return $b['view'] <=> $a['view']; });
    return $out;
}

/** Tekil ziyaretçi, kapanma, hızlı kapatma ve ortalama açık kalma süresi */
function st_engagement(array $stats, string $from, string $to): array {
    $o = ['uniq' => 0, 'close' => 0, 'quick' => 0, 'msSum' => 0];
    foreach ($stats as $day => $row) {
        if ($day < $from || $day > $to) continue;
        $o['uniq']  += (int) ($row['uniq']  ?? 0);
        $o['close'] += (int) ($row['close'] ?? 0);
        $o['quick'] += (int) ($row['quick'] ?? 0);
        $o['msSum'] += (int) ($row['msSum'] ?? 0);
    }
    $o['avgSec']   = $o['close'] > 0 ? round($o['msSum'] / $o['close'] / 1000, 1) : 0;
    $o['quickPct'] = $o['close'] > 0 ? round($o['quick'] / $o['close'] * 100, 1) : 0;
    return $o;
}

/** Cihaz kodunu okunabilir yap */
function st_device_name(string $k): string {
    return $k === 'm' ? 'Mobil' : ($k === 'd' ? 'Masaüstü' : $k);
}

/**
 * Tek bir popup'ın ayrıntılı istatistiği.
 * Günlük görüntüleme/tıklama eski kayıtlarda da vardır; cihaz/kaynak/sayfa/kapanma ayrıntısı
 * stat.php'nin bu sürümünden sonra toplanır.
 */
function st_popup_detail(array $stats, string $from, string $to, string $pid): array {
    $o = ['view' => 0, 'click' => 0, 'uniq' => 0, 'close' => 0, 'quick' => 0, 'msSum' => 0,
          'daily' => [], 'dev' => [], 'src' => [], 'pg' => [], 'act' => [], 'svc' => [], 'hasDetail' => false];
    $cur = strtotime($from); $end = strtotime($to); $guard = 0;
    while ($cur <= $end && $guard++ < 800) { $o['daily'][date('Y-m-d', $cur)] = ['view' => 0, 'click' => 0]; $cur = strtotime('+1 day', $cur); }
    foreach ($stats as $day => $row) {
        if ($day < $from || $day > $to) continue;
        $p = $row['p'][$pid] ?? null;
        if (!is_array($p)) continue;
        $v = (int) ($p['view'] ?? 0); $c = (int) ($p['click'] ?? 0);
        $o['view'] += $v; $o['click'] += $c;
        if (isset($o['daily'][$day])) { $o['daily'][$day]['view'] += $v; $o['daily'][$day]['click'] += $c; }
        foreach (['uniq', 'close', 'quick', 'msSum'] as $k) $o[$k] += (int) ($p[$k] ?? 0);
        foreach (['dev', 'src', 'pg'] as $bk) {
            foreach ((array) ($p[$bk] ?? []) as $k => $x) {
                if (!is_array($x)) continue;
                $o['hasDetail'] = true;
                if (!isset($o[$bk][$k])) $o[$bk][$k] = ['view' => 0, 'click' => 0];
                $o[$bk][$k]['view'] += (int) ($x['view'] ?? 0); $o[$bk][$k]['click'] += (int) ($x['click'] ?? 0);
            }
        }
        foreach ((array) ($p['act'] ?? []) as $k => $n) $o['act'][$k] = ((int) ($o['act'][$k] ?? 0)) + (int) $n;
        foreach ((array) ($p['svc'] ?? []) as $k => $n) $o['svc'][$k] = ((int) ($o['svc'][$k] ?? 0)) + (int) $n;
    }
    foreach (['dev', 'src', 'pg'] as $bk) uasort($o[$bk], function ($a, $b) { return $b['view'] <=> $a['view']; });
    arsort($o['act']); arsort($o['svc']);
    $o['avgSec']   = $o['close'] > 0 ? round($o['msSum'] / $o['close'] / 1000, 1) : 0;
    $o['quickPct'] = $o['close'] > 0 ? round($o['quick'] / $o['close'] * 100, 1) : 0;
    return $o;
}

/** Eylem kodunu okunabilir yap */
function st_action_name(string $k): string {
    $m = ['spin' => 'Çark çevrildi', 'scratch' => 'Kart kazındı', 'claim' => 'Kupon alındı', 'coupon' => 'Kupon kopyalandı'];
    return $m[$k] ?? $k;
}
