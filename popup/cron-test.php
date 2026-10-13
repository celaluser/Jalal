<?php
/**
 * CRON TEST DOSYASI
 * Tek işi: her çalıştığında cron-test.log dosyasına bir satır yazmak.
 * Amaç, sunucudaki cron'un gerçekten çalışıp çalışmadığını kanıtlamak.
 *
 * Cron komutu:
 *   php /home/milyonpane/domains/uplow.us/public_html/popup/cron-test.php
 *
 * 1-2 tur sonra cron-test.log dosyasına bak. Satır varsa cron çalışıyor demektir.
 * Test bitince bu cron'u ve dosyayı silebilirsin.
 */

$line = sprintf(
    "[%s] calisti | SAPI=%s | PHP=%s | user=%s | dir=%s%s",
    date('Y-m-d H:i:s'),
    PHP_SAPI,
    PHP_VERSION,
    function_exists('get_current_user') ? get_current_user() : '?',
    __DIR__,
    PHP_EOL
);

@file_put_contents(__DIR__ . '/cron-test.log', $line, FILE_APPEND | LOCK_EX);

if (PHP_SAPI !== 'cli') {
    header('Content-Type: text/plain; charset=utf-8');
    echo "Yazildi:\n" . $line;
}
