<?php
declare(strict_types=1);

// Webhook'u kurar:   php tools/set_webhook.php
// Kaldırır:          php tools/set_webhook.php --delete   (polling moduna geçerken)
// Bilgi:             php tools/set_webhook.php --info

if (PHP_SAPI !== 'cli') {
    http_response_code(403);
    exit;
}
require dirname(__DIR__) . '/src/bootstrap.php';

use TgWatch\App;

$c = App::config();
$tg = App::telegram();

try {
    if (in_array('--delete', $argv, true)) {
        $tg->call('deleteWebhook', ['drop_pending_updates' => false]);
        echo "✓ Webhook kaldırıldı.\n";
    } elseif (in_array('--info', $argv, true)) {
        $i = $tg->call('getWebhookInfo');
        echo 'URL: ', $i['url'] ?: '(yok)', "\nBekleyen: ", $i['pending_update_count'] ?? 0, "\n";
        if (!empty($i['last_error_message'])) {
            echo 'Son hata: ', $i['last_error_message'], ' (', date('Y-m-d H:i', (int)$i['last_error_date']), ")\n";
        }
    } else {
        if (!str_starts_with((string)$c['webhook_url'], 'https://')) {
            throw new RuntimeException("config.php: webhook_url https:// ile başlamalı");
        }
        if (!preg_match('/^[A-Za-z0-9_-]{16,256}$/', (string)$c['webhook_secret'])) {
            throw new RuntimeException("config.php: webhook_secret boş ya da geçersiz (php tools/make_hash.php --secret)");
        }
        $tg->call('setWebhook', [
            'url' => $c['webhook_url'],
            'secret_token' => $c['webhook_secret'],
            'allowed_updates' => ['message', 'callback_query'],
            'max_connections' => 5,
        ]);
        echo "✓ Webhook kuruldu: {$c['webhook_url']}\nBirkaç saniye sonra kontrol: php tools/set_webhook.php --info\n";
    }
} catch (Throwable $e) {
    echo '✗ ', $e->getMessage(), "\n";
    exit(1);
}
