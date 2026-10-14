<?php
/**
 * Kupon limiti sunucusu (çark, kazıma kartı ve limitli kuponlar).
 *
 * Popup şuraya POST eder:  a=claim & p=popupID & k=ziyaretçiKimliği
 * Yanıt (JSON):
 *   {"ok":true,  "code":"KOD", "label":"10%", "idx":2, "again":false}
 *   {"ok":false, "reason":"out"}      kuponlar bitti
 *
 * Kodlar embed.js'e GİRMEZ; sadece buradan, hak kazanıldığında verilir.
 * Kişi başı 1 kez kuralı: ziyaretçi kimliği VEYA IP özeti eşleşirse aynı sonuç tekrar verilir.
 * Saklanan: coupons.json (ham IP/kimlik yok, tuzlu özet).
 */
require __DIR__ . '/popup-core.php';

header('Access-Control-Allow-Origin: *');
header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store');
header('X-Content-Type-Options: nosniff');

$out = function (array $a) { echo json_encode($a, JSON_UNESCAPED_UNICODE); exit; };

if (($_SERVER['REQUEST_METHOD'] ?? '') === 'OPTIONS') { http_response_code(204); exit; }
if (($_SERVER['REQUEST_METHOD'] ?? '') !== 'POST') { http_response_code(405); $out(['ok' => false, 'reason' => 'method']); }

$raw = file_get_contents('php://input');
if ($raw === false || strlen($raw) > 1024) $out(['ok' => false, 'reason' => 'bad']);
parse_str($raw, $q);

if (popup_is_bot_ua((string) ($_SERVER['HTTP_USER_AGENT'] ?? ''))) $out(['ok' => false, 'reason' => 'bot']);
if (($q['a'] ?? '') !== 'claim') $out(['ok' => false, 'reason' => 'bad']);

$pid = preg_replace('/[^0-9A-Za-z_-]/', '', (string) ($q['p'] ?? ''));
$vid = preg_replace('/[^0-9a-zA-Z]/', '', (string) ($q['k'] ?? ''));
if ($pid === '' || strlen($vid) < 8) $out(['ok' => false, 'reason' => 'bad']);

$cfg = popup_config();
$pop = null;
foreach ((array) $cfg['popups'] as $m) {
    if (is_array($m) && ($m['id'] ?? '') === $pid && !empty($m['enabled'])) { $pop = popup_merge(popup_manual_default(), $m); break; }
}
if (!$pop || empty($pop['limit_on'])) $out(['ok' => false, 'reason' => 'nopop']);

$ip   = (string) ($_SERVER['REMOTE_ADDR'] ?? '');
$once = !empty($pop['coupon_once']);
$isWheel = ($pop['layout'] ?? '') === 'wheel';
$prizes  = $isWheel ? popup_parse_prizes((string) $pop['prizes']) : [];
$code    = trim((string) $pop['coupon']);
$total   = max(0, (int) $pop['coupon_total']);
if ($isWheel && count($prizes) < 2) $out(['ok' => false, 'reason' => 'bad']);
if (!$isWheel && $code === '') $out(['ok' => false, 'reason' => 'bad']);

$file = popup_paths()['coupons'];
$fp = @fopen($file, 'c+');
if (!$fp) $out(['ok' => false, 'reason' => 'io']);
if (!flock($fp, LOCK_EX)) { fclose($fp); $out(['ok' => false, 'reason' => 'io']); }

$size = filesize($file);
$db = $size ? json_decode((string) fread($fp, $size), true) : [];
if (!is_array($db)) $db = [];
if (empty($db['_salt'])) $db['_salt'] = bin2hex(random_bytes(16));
$vh = substr(hash('sha256', $db['_salt'] . '|v|' . $vid), 0, 20);
$ih = substr(hash('sha256', $db['_salt'] . '|i|' . $ip), 0, 20);

if (!isset($db[$pid]) || !is_array($db[$pid])) $db[$pid] = ['n' => [], 'u' => []];
$d =& $db[$pid];
if (!isset($d['n']) || !is_array($d['n'])) $d['n'] = [];
if (!isset($d['u']) || !is_array($d['u'])) $d['u'] = [];

$res = null;
$prev = null;
if ($once) { $prev = $d['u'][$vh] ?? ($d['u'][$ih] ?? null); }

if (is_array($prev)) {
    $res = ['ok' => true, 'again' => true, 'code' => (string) ($prev['c'] ?? ''), 'label' => (string) ($prev['l'] ?? ''), 'idx' => (int) ($prev['i'] ?? 0)];
} elseif ($isWheel) {
    $cand = [];
    foreach ($prizes as $i => $pr) {
        $c = $pr[1]; $stock = (int) $pr[2];
        if ($c === '' || $stock === 0 || (int) ($d['n'][$c] ?? 0) < $stock) $cand[] = $i;
    }
    if (!$cand) { $res = ['ok' => false, 'reason' => 'out']; }
    else {
        $idx = $cand[random_int(0, count($cand) - 1)];
        $pr = $prizes[$idx];
        if ($pr[1] !== '') $d['n'][$pr[1]] = (int) ($d['n'][$pr[1]] ?? 0) + 1;
        $res = ['ok' => true, 'again' => false, 'code' => $pr[1], 'label' => $pr[0], 'idx' => $idx];
    }
} else {
    if ($total > 0 && (int) ($d['n'][$code] ?? 0) >= $total) { $res = ['ok' => false, 'reason' => 'out']; }
    else {
        $d['n'][$code] = (int) ($d['n'][$code] ?? 0) + 1;
        $res = ['ok' => true, 'again' => false, 'code' => $code, 'label' => '', 'idx' => 0];
    }
}

if (!empty($res['ok']) && empty($res['again']) && $once) {
    $rec = ['c' => $res['code'], 'l' => $res['label'], 'i' => $res['idx'], 't' => time()];
    $d['u'][$vh] = $rec; $d['u'][$ih] = $rec;
    if (count($d['u']) > 6000) { uasort($d['u'], function ($x, $y) { return ($y['t'] ?? 0) <=> ($x['t'] ?? 0); }); $d['u'] = array_slice($d['u'], 0, 5000, true); }
}
unset($d);

ftruncate($fp, 0); rewind($fp);
fwrite($fp, json_encode($db, JSON_UNESCAPED_UNICODE));
fflush($fp); flock($fp, LOCK_UN); fclose($fp);

$out($res);
