<?php
declare(strict_types=1);

// tgwatch web yönetim paneli (JavaScript'siz).
require __DIR__ . '/app_path.php';
require APP_DIR . '/src/bootstrap.php';

try {
    $config = TgWatch\App::config();
} catch (Throwable $e) {
    http_response_code(500);
    header('Content-Type: text/plain; charset=utf-8');
    exit($e->getMessage() . "\n");
}
(new TgWatch\Web\Admin($config))->handle();
