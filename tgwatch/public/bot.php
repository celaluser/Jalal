<?php
declare(strict_types=1);

// Telegram webhook ucu. Adres config.php içindeki webhook_url ile aynı olmalı.
require __DIR__ . '/app_path.php';
require APP_DIR . '/src/bootstrap.php';

use TgWatch\App;

header('Content-Type: text/plain; charset=utf-8');

if (($_SERVER['REQUEST_METHOD'] ?? '') !== 'POST') {
    // Tarayıcıdan açılınca: dosyaya erişilebildiğini gösterir (Telegram yalnızca POST gönderir).
    exit("tgwatch webhook ucu erişilebilir. Telegram bu adrese POST gönderir.\n");
}

$config = App::config();
$secret = (string)$config['webhook_secret'];
$given = (string)($_SERVER['HTTP_X_TELEGRAM_BOT_API_SECRET_TOKEN'] ?? '');
if ($given === '' && function_exists('getallheaders')) { // bazı FastCGI kurulumlarında başlık $_SERVER'a düşmez
    foreach ((array)getallheaders() as $k => $v) {
        if (strcasecmp((string)$k, 'X-Telegram-Bot-Api-Secret-Token') === 0) {
            $given = (string)$v;
        }
    }
}
if ($secret === '' || !hash_equals($secret, $given)) {
    try {
        App::db()->log('warn', 'webhook', 'İstek reddedildi: ' . ($secret === '' ? 'config.php içinde webhook_secret boş'
            : ($given === '' ? 'gizli anahtar başlığı gelmedi (sunucu X-Telegram-Bot-Api-Secret-Token başlığını iletmiyor olabilir)'
            : 'gizli anahtar uyuşmuyor — webhook_secret değişti mi? Panelde Webhook\'u kur ile yeniden kurun'))
            . ' · IP ' . ($_SERVER['REMOTE_ADDR'] ?? '?'));
    } catch (Throwable) {
    }
    http_response_code(403);
    exit('forbidden');
}

$update = json_decode((string)file_get_contents('php://input'), true);
if (!is_array($update)) {
    http_response_code(400);
    exit('bad request');
}

// Telegram'a hemen cevap ver (sunucu destekliyorsa), işi arkada sürdür: Claude çağrısı gibi uzun işler
// Telegram'ın zaman aşımına düşüp aynı güncellemeyi tekrar göndermesine yol açmasın.
@set_time_limit(150);
ignore_user_abort(true);
echo 'ok';
if (function_exists('fastcgi_finish_request')) {
    fastcgi_finish_request();
} elseif (function_exists('litespeed_finish_request')) {
    litespeed_finish_request();
}

try {
    App::flow()->handleUpdate($update);
} catch (Throwable $e) {
    try {
        App::db()->log('error', 'webhook', get_class($e) . ': ' . $e->getMessage());
    } catch (Throwable) {
    }
}
