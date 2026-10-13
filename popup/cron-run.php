<?php
/**
 * =============================================================
 *  HTTP ÜZERİNDEN CRON TETİKLEYİCİ
 *
 *  Sunucunun komut satırı cron'u çalışmadığında kullanılır.
 *  Cron komutu (DirectAdmin > Cron Jobs):
 *
 *  wget -q -O /dev/null "https://uplow.us/popup/cron-run.php?key=HSgHq-5ktxUngNgjwg_nl8X8VrECe-sJ"
 *
 *  Anahtarı değiştirmek istersen popup-config.php içine ekle:
 *      'cron_token' => 'yeni-anahtar',
 * =============================================================
 */

require __DIR__ . '/popup-core.php';

$KEY = 'HSgHq-5ktxUngNgjwg_nl8X8VrECe-sJ';   // popup-config.php'deki 'cron_token' bunu ezer

register_shutdown_function(function () {
    $e = error_get_last();
    if ($e && in_array($e['type'], [E_ERROR, E_PARSE, E_CORE_ERROR, E_COMPILE_ERROR], true)) {
        @popup_log('OLUMCUL HATA (web-cron): ' . $e['message'] . ' — ' . $e['file'] . ':' . $e['line']);
    }
});

$cfg = popup_config();
$key = isset($cfg['cron_token']) && $cfg['cron_token'] !== '' ? $cfg['cron_token'] : $KEY;

if (!isset($_GET['key']) || !hash_equals($key, (string) $_GET['key'])) {
    http_response_code(403);
    exit('Forbidden');
}

header('Content-Type: text/plain; charset=utf-8');
header('Cache-Control: no-store');

popup_log('web-cron tetiklendi (PHP ' . PHP_VERSION . ')');

if (empty($cfg['enabled'])) {
    popup_log('Popup kapali, guncelleme yapilmadi.');
    exit("KAPALI\n");
}

$r = popup_run();
echo ($r['ok'] ? 'OK — ' : 'HATA: ') . $r['msg'] . "\n";
