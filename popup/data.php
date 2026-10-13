<?php
/**
 * =============================================================
 *  İKİ İŞİ VAR:
 *
 *  1) services.json'i BASKA BIR SITEYE acar (CORS).
 *     https://uplow.us/popup/data.php
 *
 *  2) ACIL DURDURMA DURUMU  (?state=1)
 *     https://uplow.us/popup/data.php?state=1  ->  {"off":0}
 *     embed.js her sayfa acilisinda bunu okur; "off":1 ise HICBIR popup
 *     acilmaz. Bu uc ONBELLEKLENMEZ, yani panelden acil durdurmaya
 *     bastigin an sitede etkili olur (embed.js 5 dk cache'li olsa bile).
 *
 *  Bu dosyayi popup-cron.php ile ayni klasore koyun.
 * =============================================================
 */

/* ---------- 1) ACİL DURDURMA DURUMU ---------- */
if (isset($_GET['state'])) {
    require_once __DIR__ . '/popup-core.php';

    header('Access-Control-Allow-Origin: *');
    header('Content-Type: application/json; charset=utf-8');
    header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
    header('Pragma: no-cache');
    header('X-Content-Type-Options: nosniff');

    $k = popup_kill_get();
    echo json_encode(['off' => $k['off'] ? 1 : 0, 'ts' => $k['ts']]);
    exit;
}

/* ---------- 2) services.json ---------- */

// Sadece kendi siteleriniz okusun isterseniz '*' yerine
// tek tek domain yazip asagidaki blogu kullanabilirsiniz.
$ALLOWED = ['https://panelbaku.com', 'https://www.panelbaku.com'];
// Herkese acmak isterseniz: $ALLOWED = ['*'];

$origin = $_SERVER['HTTP_ORIGIN'] ?? '';
if (in_array('*', $ALLOWED, true)) {
    header('Access-Control-Allow-Origin: *');
} elseif ($origin !== '' && in_array($origin, $ALLOWED, true)) {
    header('Access-Control-Allow-Origin: ' . $origin);
    header('Vary: Origin');
}

header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: public, max-age=300');
header('X-Content-Type-Options: nosniff');

$file = __DIR__ . '/services.json';

if (!is_file($file)) {
    http_response_code(404);
    echo json_encode([
        'error' => 'services.json bulunamadi. Once popup-cron.php calistirilmali.'
    ], JSON_UNESCAPED_UNICODE);
    exit;
}

header('Last-Modified: ' . gmdate('D, d M Y H:i:s', filemtime($file)) . ' GMT');
readfile($file);
