<?php
/**
 * Gösterim / tıklama / kapanma sayacı.
 *
 * Popup buraya sendBeacon ile gönderir:
 *   e=view|click|close  [&p=popupID] [&s=servisID] [&v=surumID]
 *   [&d=m|d]  cihaz     [&sr=kaynak] [&pg=/sayfa]  [&u=1] tekil  [&ms=süre]
 *
 * Saklanan veri (stats.json), gün başına:
 *   { "2026-09-09": {
 *       "view":187, "click":15, "close":170, "quick":40, "msSum":812340, "uniq":96,
 *       "v":   { "a1b2c3d4e5": {"view":120,"click":9,"svc":{"4479":5}} },
 *       "p":   { "auto": {"view":100,"click":8,"svc":{"4479":5}} },
 *       "dev": { "m": {"view":110,"click":9}, "d": {"view":77,"click":6} },
 *       "src": { "instagram": {"view":40,"click":6}, "direct": {...} },
 *       "pg":  { "/": {"view":90,"click":7} },
 *       "svc": { "4479":5 }
 *   }}
 *
 * Kişisel veri, IP veya çerez saklanmaz — sadece toplu günlük sayaçlar.
 */

require __DIR__ . '/popup-core.php';

header('Access-Control-Allow-Origin: *');
header('Cache-Control: no-store');

if (($_SERVER['REQUEST_METHOD'] ?? '') !== 'POST') { http_response_code(405); exit; }
if (popup_is_bot_ua((string) ($_SERVER['HTTP_USER_AGENT'] ?? ''))) { http_response_code(204); exit; }   // bot trafiği sayılmaz

$raw = file_get_contents('php://input');
if ($raw === false || strlen($raw) > 1024) { http_response_code(204); exit; }

parse_str($raw, $q);

$ev = (string) ($q['e'] ?? '');
if ($ev !== 'view' && $ev !== 'click' && $ev !== 'close') { http_response_code(204); exit; }

/** Anahtarları temizle: sadece güvenli karakterler, sınırlı uzunluk */
$clean = function ($v, string $pattern, int $max): string {
    $v = preg_replace($pattern, '', (string) $v);
    return $v === '' ? '' : substr($v, 0, $max);
};

$pp = $clean($q['p']  ?? '', '/[^0-9A-Za-z_-]/', 32);              // popup id
$sv = $clean($q['s']  ?? '', '/[^0-9A-Za-z_-]/', 32);              // servis id
$vr = $clean($q['v']  ?? '', '/[^0-9a-f]/',      16);              // sürüm id
$dv = ($q['d'] ?? '') === 'm' ? 'm' : 'd';                          // cihaz
$sr = $clean(strtolower((string) ($q['sr'] ?? '')), '/[^0-9a-z.:_-]/', 40);  // kaynak
$pg = $clean((string) ($q['pg'] ?? ''), '/[^0-9A-Za-z\/._-]/', 60);          // sayfa yolu
$uq = !empty($q['u']);                                              // tekil ziyaretçi
$ms = isset($q['ms']) ? max(0, min(3600000, (int) $q['ms'])) : null; // açık kalma süresi

if ($sr === '') $sr = 'direct';
if ($pg === '') $pg = '/';

$file = popup_paths()['stats'];
$day  = date('Y-m-d');

$fp = @fopen($file, 'c+');
if (!$fp) { http_response_code(204); exit; }

if (flock($fp, LOCK_EX)) {
    $size = filesize($file);
    $data = $size ? json_decode((string) fread($fp, $size), true) : [];
    if (!is_array($data)) $data = [];

    if (!isset($data[$day]) || !is_array($data[$day])) $data[$day] = [];
    $d =& $data[$day];

    foreach (['v', 'p', 'dev', 'src', 'pg'] as $k) {
        if (!isset($d[$k]) || !is_array($d[$k])) $d[$k] = [];
    }

    /* --- günlük toplamlar --- */
    $d[$ev] = ((int) ($d[$ev] ?? 0)) + 1;
    if ($uq && $ev === 'view') $d['uniq'] = ((int) ($d['uniq'] ?? 0)) + 1;
    if ($ev === 'close' && $ms !== null) {
        $d['msSum'] = ((int) ($d['msSum'] ?? 0)) + $ms;
        if ($ms < 3000) $d['quick'] = ((int) ($d['quick'] ?? 0)) + 1;   // 3 sn'den kısa = hemen kapattı
    }

    /* --- popup bazında ayrıntı (popup'a özel istatistik sayfası için) --- */
    $pkey0 = $pp !== '' ? $pp : 'bilinmiyor';
    if (!isset($d['p'][$pkey0]) || !is_array($d['p'][$pkey0])) $d['p'][$pkey0] = ['view' => 0, 'click' => 0, 'svc' => []];
    $pe =& $d['p'][$pkey0];
    if ($ev === 'close') {
        $pe['close'] = ((int) ($pe['close'] ?? 0)) + 1;
        if ($ms !== null) {
            $pe['msSum'] = ((int) ($pe['msSum'] ?? 0)) + $ms;
            if ($ms < 3000) $pe['quick'] = ((int) ($pe['quick'] ?? 0)) + 1;
        }
    }
    if ($ev === 'view' && $uq) $pe['uniq'] = ((int) ($pe['uniq'] ?? 0)) + 1;
    if ($ev === 'view' || $ev === 'click') {
        foreach ([['dev', $dv, 2], ['src', $sr, 40], ['pg', $pg, 40]] as $bk) {
            list($bn, $bv, $lim) = $bk;
            if (!isset($pe[$bn]) || !is_array($pe[$bn])) $pe[$bn] = [];
            if (isset($pe[$bn][$bv]) || count($pe[$bn]) < $lim) {
                if (!isset($pe[$bn][$bv])) $pe[$bn][$bv] = ['view' => 0, 'click' => 0];
                $pe[$bn][$bv][$ev] = ((int) ($pe[$bn][$bv][$ev] ?? 0)) + 1;
            }
        }
        // popup içi eylemler (çark, kazıma, kupon al/kopyala) — servis sayılmaz
        if ($ev === 'click' && in_array($sv, ['coupon', 'spin', 'scratch', 'claim'], true)) {
            if (!isset($pe['act']) || !is_array($pe['act'])) $pe['act'] = [];
            $pe['act'][$sv] = ((int) ($pe['act'][$sv] ?? 0)) + 1;
        }
    }
    unset($pe);

    /* --- kırılımlar (view/click sayılır) --- */
    if ($ev === 'view' || $ev === 'click') {

        // sürüm bazında
        $vkey = $vr !== '' ? $vr : 'bilinmiyor';
        if (!isset($d['v'][$vkey])) $d['v'][$vkey] = ['view' => 0, 'click' => 0, 'svc' => []];
        $d['v'][$vkey][$ev] = ((int) ($d['v'][$vkey][$ev] ?? 0)) + 1;

        // popup bazında
        $pkey = $pp !== '' ? $pp : 'bilinmiyor';
        if (!isset($d['p'][$pkey]) || !is_array($d['p'][$pkey])) $d['p'][$pkey] = ['view' => 0, 'click' => 0, 'svc' => []];
        $d['p'][$pkey][$ev] = ((int) ($d['p'][$pkey][$ev] ?? 0)) + 1;

        // cihaz bazında
        if (!isset($d['dev'][$dv])) $d['dev'][$dv] = ['view' => 0, 'click' => 0];
        $d['dev'][$dv][$ev] = ((int) ($d['dev'][$dv][$ev] ?? 0)) + 1;

        // kaynak bazında (kardinalite sınırı: 80 anahtar)
        if (isset($d['src'][$sr]) || count($d['src']) < 80) {
            if (!isset($d['src'][$sr])) $d['src'][$sr] = ['view' => 0, 'click' => 0];
            $d['src'][$sr][$ev] = ((int) ($d['src'][$sr][$ev] ?? 0)) + 1;
        }

        // sayfa bazında (kardinalite sınırı: 80 anahtar)
        if (isset($d['pg'][$pg]) || count($d['pg']) < 80) {
            if (!isset($d['pg'][$pg])) $d['pg'][$pg] = ['view' => 0, 'click' => 0];
            $d['pg'][$pg][$ev] = ((int) ($d['pg'][$pg][$ev] ?? 0)) + 1;
        }

        // servis kırılımı (sadece tıklama)
        $isAct = in_array($sv, ['coupon', 'spin', 'scratch', 'claim'], true);
        if ($ev === 'click' && $sv !== '' && !$isAct) {
            $d['v'][$vkey]['svc'][$sv] = ((int) ($d['v'][$vkey]['svc'][$sv] ?? 0)) + 1;
            $d['p'][$pkey]['svc'][$sv] = ((int) ($d['p'][$pkey]['svc'][$sv] ?? 0)) + 1;
            if (!isset($d['svc']) || !is_array($d['svc'])) $d['svc'] = [];
            $d['svc'][$sv] = ((int) ($d['svc'][$sv] ?? 0)) + 1;
        }
    }
    unset($d);

    // 400 gün sakla (yaklaşık 13 ay)
    if (count($data) > 400) { krsort($data); $data = array_slice($data, 0, 400, true); ksort($data); }

    ftruncate($fp, 0);
    rewind($fp);
    fwrite($fp, json_encode($data, JSON_UNESCAPED_UNICODE));
    fflush($fp);
    flock($fp, LOCK_UN);
}
fclose($fp);

http_response_code(204);
