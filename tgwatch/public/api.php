<?php
declare(strict_types=1);

// Chrome eklentisinin API'si. POST, JSON gövde, X-Tgwatch-Key başlığı (Ayarlar → Özellikler → Eklenti anahtarı).
require __DIR__ . '/app_path.php';
require APP_DIR . '/src/bootstrap.php';

use TgWatch\App;
use TgWatch\ExtApi;

header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store');
header('X-Robots-Tag: noindex');
header('X-Content-Type-Options: nosniff');

$send = static function (int $code, array $body): void {
    http_response_code($code);
    echo json_encode($body, JSON_UNESCAPED_UNICODE);
};

if (($_SERVER['REQUEST_METHOD'] ?? '') !== 'POST') {
    $send(405, ['ok' => false, 'error' => 'POST gerekli']);
    exit;
}
try {
    $config = App::config();
    if (!ExtApi::authorized($config, (string)($_SERVER['HTTP_X_TGWATCH_KEY'] ?? ''))) {
        $send(403, ['ok' => false, 'error' => 'Anahtar yanlış ya da tanımlı değil (Ayarlar → Özellikler → Eklenti anahtarı)']);
        exit;
    }
    $raw = (string)file_get_contents('php://input', false, null, 0, 2_000_000);
    $req = json_decode($raw, true);
    if (!is_array($req)) {
        $send(400, ['ok' => false, 'error' => 'Geçerli JSON gövde gerekli']);
        exit;
    }
    [$code, $body] = (new ExtApi(App::db(), $config))->handle($req);
    $send($code, $body);
} catch (Throwable $e) {
    error_log('tgwatch api: ' . $e->getMessage());
    $send(500, ['ok' => false, 'error' => 'Sunucu hatası']);
}
