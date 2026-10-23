<?php
declare(strict_types=1);

/**
 * Her dakika cron ile çalıştırın:
 *   * * * * * /PHP/YOLU /home/KULLANICI/tgwatch/cron.php >/dev/null 2>&1
 *
 * Seçenekler:
 *   --test              Kanalları okur, bulduklarını yazar; hiçbir şey kaydetmez/göndermez.
 *   --backfill=N        Her kanalın son N gönderisini işler (ilk gerçek deneme için).
 *   --channel=ad        Sadece bu kanal.
 *   --add-channel=ad    Kanal ekler (web panel yerine).
 */

use TgWatch\App;
use TgWatch\ChannelReader;
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

$config = App::config();
$limit = (int)$config['cron_time_limit'];
@set_time_limit($limit + 10);
$deadline = time() + $limit;

try {
    $db = App::db();
} catch (Throwable $e) {
    fwrite(STDERR, 'Veritabanı açılamadı: ' . $e->getMessage() . "\n");
    exit(1);
}

// Aynı anda iki cron çalışmasın.
$lock = fopen($config['data_dir'] . '/cron.lock', 'c');
if (!$lock || !flock($lock, LOCK_EX | LOCK_NB)) {
    $say('Başka bir cron çalışıyor, çıkılıyor.');
    exit(0);
}

$flow = App::flow();
$watcher = new Watcher($db, $flow, new ChannelReader((int)$config['fetch_timeout']));

if (isset($opts['add-channel'])) {
    try {
        $u = $watcher->addChannel((string)$opts['add-channel']);
        $say("Kanal eklendi: @$u");
    } catch (Throwable $e) {
        fwrite(STDERR, $e->getMessage() . "\n");
        exit(1);
    }
    exit(0);
}

$started = microtime(true);
$errors = 0;

try {
    // 1) Bot güncellemeleri (polling modunda)
    if (!$test && $config['bot_mode'] === 'polling') {
        try {
            $offset = (int)$db->get('last_update_id', '0') + 1;
            $updates = App::telegram()->call('getUpdates', ['offset' => $offset, 'timeout' => 0, 'allowed_updates' => ['message', 'callback_query']]);
            foreach ($updates as $u) {
                $flow->handleUpdate($u);
            }
            $say('Bot güncellemesi: ' . count($updates));
        } catch (Throwable $e) {
            $errors++;
            $hint = str_contains($e->getMessage(), 'webhook') ? ' (webhook kurulu: polling için tools/set_webhook.php --delete)' : '';
            $db->log('error', 'bot', 'getUpdates: ' . $e->getMessage() . $hint);
            $say('getUpdates hatası: ' . $e->getMessage() . $hint);
        }
    }

    // 2) Bota gönderilemeyen işleri tekrar dene
    if (!$test) {
        $flow->retryUnsent();
    }

    // 3) Kanallar
    $reports = $watcher->runAll($backfill, $test, $only, $deadline);
    if (!$reports) {
        $say('Etkin kanal yok. Web panelden ya da --add-channel=ad ile ekleyin.');
    }
    foreach ($reports as $u => $r) {
        if (!$r['ok']) {
            $errors++;
            $say("@$u  ✗ {$r['error']}");
            continue;
        }
        $say("@$u  ✓ {$r['posts']} gönderi okundu, {$r['new']} yeni, " . count($r['found']) . ' servis duyurusu' . ($test ? '' : ', iş: ' . (implode(',', $r['jobs']) ?: '-')));
        if ($test || $backfill > 0) {
            foreach ($r['found'] as $f) {
                $say("   gönderi {$f['id']}:");
                foreach ($f['items'] as $it) {
                    $az = $flow->translator()->translate($it['name']);
                    $say("     {$it['id']}  {$it['name']}" . ($it['price'] !== '' ? "  (\${$it['price']})" : ''));
                    $say("       → {$az['text']}" . ($az['unknown'] ? '   ⚠ sözlükte yok: ' . implode(', ', $az['unknown']) : ''));
                }
            }
        }
    }
} catch (Throwable $e) {
    $errors++;
    $db->log('error', 'cron', get_class($e) . ': ' . $e->getMessage());
    fwrite(STDERR, 'Hata: ' . $e->getMessage() . "\n");
}

if (!$test) {
    $db->set('cron_last_run', (string)time());
    $db->setJson('cron_last_result', ['errors' => $errors, 'seconds' => round(microtime(true) - $started, 2), 'at' => time()]);
}
$say(sprintf('Bitti: %.1f sn, %d hata.', microtime(true) - $started, $errors));
exit($errors > 0 ? 2 : 0);
