<?php
declare(strict_types=1);

// Telegram webhook ucu. Adres config.php içindeki webhook_url ile aynı olmalı.
require __DIR__ . '/app_path.php';
require APP_DIR . '/src/bootstrap.php';

use TgWatch\App;

header('Content-Type: text/plain; charset=utf-8');

if (($_SERVER['REQUEST_METHOD'] ?? '') !== 'POST') {
    http_response_code(405);
    exit('POST only');
}

$config = App::config();
$secret = (string)$config['webhook_secret'];
$given = (string)($_SERVER['HTTP_X_TELEGRAM_BOT_API_SECRET_TOKEN'] ?? '');
if ($secret === '' || !hash_equals($secret, $given)) {
    http_response_code(403);
    exit('forbidden');
}

$update = json_decode((string)file_get_contents('php://input'), true);
if (!is_array($update)) {
    http_response_code(400);
    exit('bad request');
}

try {
    @set_time_limit(60);
    App::flow()->handleUpdate($update);
} catch (Throwable $e) {
    try {
        App::db()->log('error', 'webhook', get_class($e) . ': ' . $e->getMessage());
    } catch (Throwable) {
    }
}
// Hata olsa bile 200 dön: aksi halde Telegram aynı güncellemeyi tekrar tekrar gönderir.
echo 'ok';
