<?php
declare(strict_types=1);

/**
 * Bu skript HEÇ VAXT HTML/boş 500 səhifəsi qaytarmamalıdır - yalnız JSON.
 * Ona görə bütün PHP xətalarını (adi Exception-lar və fatal error-lar daxil)
 * özümüz tutub aydın JSON mesajı kimi qaytarırıq.
 */

error_reporting(E_ALL);
ini_set('display_errors', '0');

header('Content-Type: application/json; charset=utf-8');

register_shutdown_function(function () {
    $error = error_get_last();
    $fatalTypes = [E_ERROR, E_PARSE, E_CORE_ERROR, E_COMPILE_ERROR, E_USER_ERROR];
    if ($error !== null && in_array($error['type'], $fatalTypes, true)) {
        if (!headers_sent()) {
            http_response_code(500);
            header('Content-Type: application/json; charset=utf-8');
        }
        echo json_encode([
            'ok'    => false,
            'error' => 'Server xətası: ' . $error['message']
                . ' (' . basename($error['file']) . ':' . $error['line'] . ')',
        ]);
    }
});

function escapeForTelegramHtml(string $text): string
{
    return str_replace(['&', '<', '>'], ['&amp;', '&lt;', '&gt;'], $text);
}

function sendViaCurl(string $text): array
{
    $url = 'https://api.telegram.org/bot' . TELEGRAM_BOT_TOKEN . '/sendMessage';
    $boldText = '<b>' . escapeForTelegramHtml($text) . '</b>';

    $ch = curl_init($url);
    curl_setopt_array($ch, [
        CURLOPT_POST           => true,
        CURLOPT_POSTFIELDS     => http_build_query([
            'chat_id'                  => TELEGRAM_CHANNEL_ID,
            'text'                     => $boldText,
            'parse_mode'               => 'HTML',
            'disable_web_page_preview' => true,
        ]),
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_SSL_VERIFYPEER => true,
        CURLOPT_TIMEOUT        => 20,
    ]);

    $response = curl_exec($ch);

    if ($response === false) {
        $error = curl_error($ch);
        curl_close($ch);
        return ['ok' => false, 'error' => "Telegram-a bağlanarkən xəta (curl): {$error}"];
    }
    curl_close($ch);

    return parseTelegramResponse((string)$response);
}

function sendViaStreamContext(string $text): array
{
    $url = 'https://api.telegram.org/bot' . TELEGRAM_BOT_TOKEN . '/sendMessage';
    $boldText = '<b>' . escapeForTelegramHtml($text) . '</b>';

    $postData = http_build_query([
        'chat_id'                  => TELEGRAM_CHANNEL_ID,
        'text'                     => $boldText,
        'parse_mode'               => 'HTML',
        'disable_web_page_preview' => true,
    ]);

    $context = stream_context_create([
        'http' => [
            'method'        => 'POST',
            'header'        => "Content-Type: application/x-www-form-urlencoded\r\n",
            'content'       => $postData,
            'timeout'       => 20,
            'ignore_errors' => true,
        ],
    ]);

    $response = @file_get_contents($url, false, $context);

    if ($response === false) {
        return ['ok' => false, 'error' => 'Telegram-a bağlanarkən xəta (stream context, allow_url_fopen bağlı ola bilər).'];
    }

    return parseTelegramResponse($response);
}

function parseTelegramResponse(string $response): array
{
    $data = json_decode($response, true);

    if (!($data['ok'] ?? false)) {
        $desc = $data['description'] ?? 'Naməlum xəta';
        return ['ok' => false, 'error' => "Telegram xətası: {$desc}"];
    }

    return ['ok' => true];
}

try {
    require __DIR__ . '/config.php';
    require __DIR__ . '/telegram_config.php';
    require __DIR__ . '/HistoryStore.php';

    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        http_response_code(405);
        echo json_encode(['ok' => false, 'error' => 'Yalnız POST sorğusu qəbul olunur.']);
        exit;
    }

    $rawBody  = file_get_contents('php://input');
    $input    = json_decode((string)$rawBody, true);
    $text     = trim((string)($input['text'] ?? ''));
    $category = trim((string)($input['category'] ?? ''));

    if ($text === '') {
        http_response_code(400);
        echo json_encode(['ok' => false, 'error' => 'Göndəriləcək mətn boşdur.']);
        exit;
    }

    if (TELEGRAM_BOT_TOKEN === '' || TELEGRAM_CHANNEL_ID === '') {
        http_response_code(400);
        echo json_encode([
            'ok'    => false,
            'error' => 'Telegram bot hələ qoşulmayıb. telegram_config.php faylında BOT_TOKEN və CHANNEL_ID doldurulmalıdır.',
        ]);
        exit;
    }

    $result = function_exists('curl_init')
        ? sendViaCurl($text)
        : sendViaStreamContext($text);

    if (!$result['ok']) {
        http_response_code(502);
        echo json_encode(['ok' => false, 'error' => $result['error']]);
        exit;
    }

    $entry = [
        'id'       => uniqid('h_', true),
        'category' => $category !== '' ? $category : 'Naməlum kateqoriya',
        'preview'  => mb_substr($text, 0, 120, 'UTF-8'),
        'time'     => date('d.m.Y H:i'),
    ];

    $store = new HistoryStore(HISTORY_FILE, HISTORY_LIMIT);
    $store->add($entry);

    echo json_encode(['ok' => true, 'entry' => $entry], JSON_UNESCAPED_UNICODE);

} catch (Throwable $e) {
    if (!headers_sent()) {
        http_response_code(500);
    }
    echo json_encode([
        'ok'    => false,
        'error' => 'PHP xətası: ' . $e->getMessage() . ' (' . basename($e->getFile()) . ':' . $e->getLine() . ')',
    ]);
}
