<?php
declare(strict_types=1);

// Web cron ucu (SSH/hosting cron yoksa): cron-job.org her dakika bu adresi çağırır.
//   https://ALANADI/.../public/webcron.php?key=CRON_KEY
// ya da anahtarı başlıkta gönderin:  X-Cron-Key: CRON_KEY
// Hata olsa bile 200 döner (cron-job.org art arda hata alınca işi kapatıyor); sonuç gövdede ve panelde görünür.

require __DIR__ . '/app_path.php';
require APP_DIR . '/src/bootstrap.php';

use TgWatch\App;
use TgWatch\CronRunner;

header('Content-Type: text/plain; charset=utf-8');
header('Cache-Control: no-store');
header('X-Robots-Tag: noindex');

try {
    $config = App::config();
} catch (Throwable $e) {
    exit('config hatası: ' . $e->getMessage() . "\n");
}

$key = (string)$config['cron_key'];
$given = (string)($_SERVER['HTTP_X_CRON_KEY'] ?? ($_GET['key'] ?? ''));
if (strlen($key) < 16 || !hash_equals($key, $given)) {
    http_response_code(403);
    exit("forbidden\n");
}

// cron-job.org varsayılan olarak 30 sn bekler: ondan önce bitir.
$limit = max(10, min((int)$config['web_cron_time_limit'], (int)$config['cron_time_limit']));
@set_time_limit($limit + 15);
ignore_user_abort(true);

$lines = [];
try {
    $errors = (new CronRunner($config, static function (string $s) use (&$lines): void {
        $lines[] = $s;
    }))->run(false, 0, null, $limit);
} catch (Throwable $e) {
    $errors = 1;
    $lines[] = 'Hata: ' . $e->getMessage();
}

echo $errors === null ? "busy\n" : ($errors === 0 ? "ok\n" : "errors: $errors\n");
// Kanal adları ve özet; gizli bilgi içermez.
echo implode("\n", array_map(static fn($l) => preg_replace('~bot\d+:[A-Za-z0-9_-]+~', 'bot***', $l), $lines)), "\n";
