<?php
declare(strict_types=1);

// Dış izleme adresi (UptimeRobot vb.): her şey yolundaysa 200 "OK", sorun varsa 503 ve nedeni.
// Adres: .../monitor.php?key=İZLEME_ANAHTARI (Ayarlar → Kanal okuma).
require __DIR__ . '/app_path.php';
require APP_DIR . '/src/bootstrap.php';

use TgWatch\App;

header('Content-Type: text/plain; charset=utf-8');
header('Cache-Control: no-store');
header('X-Robots-Tag: noindex');

try {
    $config = App::config();
    $key = (string)($config['monitor_key'] ?? '');
    $given = (string)($_SERVER['HTTP_X_MONITOR_KEY'] ?? ($_GET['key'] ?? ''));
    if (strlen($key) < 16 || !hash_equals($key, $given)) {
        http_response_code(403);
        exit("forbidden\n");
    }
    $problems = TgWatch\Monitor::problems(App::db());
} catch (Throwable $e) {
    http_response_code(503);
    exit('HATA: ' . preg_replace('~bot\d+:[A-Za-z0-9_-]+~', 'bot***', $e->getMessage()) . "\n");
}
if ($problems) {
    http_response_code(503);
    echo "HATA\n" . implode("\n", $problems) . "\n";
} else {
    echo "OK\n";
}
