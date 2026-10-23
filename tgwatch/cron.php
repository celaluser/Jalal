<?php
declare(strict_types=1);

/**
 * Komut satırı cron'u (SSH/hosting cron varsa):
 *   * * * * * /PHP/YOLU /TAM/YOL/tgwatch/cron.php >/dev/null 2>&1
 * SSH/hosting cron yoksa: cron-job.org ile public/webcron.php adresini çağırın (README).
 *
 * Seçenekler:
 *   --test              Kanalları okur, bulduklarını yazar; hiçbir şey kaydetmez/göndermez.
 *   --backfill=N        Her kanalın son N gönderisini işler (ilk gerçek deneme için).
 *   --channel=ad        Sadece bu kanal.
 *   --add-channel=ad    Kanal ekler (web panel yerine).
 */

use TgWatch\App;
use TgWatch\ChannelReader;
use TgWatch\CronRunner;
use TgWatch\Watcher;

if (PHP_SAPI !== 'cli') {
    http_response_code(403);
    exit("Yalnızca komut satırından çalışır.\n");
}

require __DIR__ . '/src/bootstrap.php';

$opts = getopt('', ['test', 'backfill:', 'channel:', 'add-channel:', 'verbose']);
$test = isset($opts['test']);
$backfill = max(0, min(50, (int)($opts['backfill'] ?? 0)));
$only = isset($opts['channel']) ? ChannelReader::cleanUsername((string)$opts['channel']) : null;
$verbose = $test || $backfill > 0 || isset($opts['verbose']) || isset($opts['add-channel']);
$say = static function (string $s) use ($verbose): void {
    if ($verbose) {
        echo $s, "\n";
    }
};

try {
    $config = App::config();
    $db = App::db();
} catch (Throwable $e) {
    fwrite(STDERR, $e->getMessage() . "\n");
    exit(1);
}
$limit = (int)$config['cron_time_limit'];
@set_time_limit($limit + 10);

if (isset($opts['add-channel'])) {
    try {
        $u = (new Watcher($db, App::flow(), new ChannelReader()))->addChannel((string)$opts['add-channel']);
        $say("Kanal eklendi: @$u");
    } catch (Throwable $e) {
        fwrite(STDERR, $e->getMessage() . "\n");
        exit(1);
    }
    exit(0);
}

$errors = (new CronRunner($config, $say))->run($test, $backfill, $only, $limit);
exit($errors === null ? 0 : ($errors > 0 ? 2 : 0));
