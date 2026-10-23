<?php
declare(strict_types=1);

header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store');

try {
    require __DIR__ . '/config.php';
    require __DIR__ . '/telegram_config.php';
    require __DIR__ . '/TelegramPreview.php';

    $username = TelegramPreview::usernameFrom((string)TELEGRAM_CHANNEL_ID);
    if ($username === null) {
        echo json_encode([
            'ok'    => false,
            'error' => 'Önizləmə yalnız açıq kanal (@username) üçün işləyir.',
        ], JSON_UNESCAPED_UNICODE);
        exit;
    }

    $cacheFile = __DIR__ . '/cache/tg_preview.json';
    $ttl       = isset($_GET['refresh']) ? 0 : 60;
    $data      = null;

    if (is_file($cacheFile) && (time() - filemtime($cacheFile) < $ttl)) {
        $cached = json_decode((string)file_get_contents($cacheFile), true);
        if (is_array($cached) && ($cached['username'] ?? '') === $username) {
            $data = $cached;
        }
    }

    if ($data === null) {
        try {
            $data = TelegramPreview::fetch($username);
            $tmp = $cacheFile . '.' . getmypid() . '.tmp';
            file_put_contents($tmp, json_encode($data, JSON_UNESCAPED_UNICODE));
            rename($tmp, $cacheFile);
        } catch (Throwable $e) {
            // Şəbəkə xətasında köhnə keşi göstər
            $stale = is_file($cacheFile) ? json_decode((string)file_get_contents($cacheFile), true) : null;
            if (!is_array($stale) || ($stale['username'] ?? '') !== $username) {
                throw $e;
            }
            $data = $stale;
        }
    }

    echo json_encode(['ok' => true] + $data, JSON_UNESCAPED_UNICODE);
} catch (Throwable $e) {
    http_response_code(502);
    echo json_encode(['ok' => false, 'error' => $e->getMessage()], JSON_UNESCAPED_UNICODE);
}
