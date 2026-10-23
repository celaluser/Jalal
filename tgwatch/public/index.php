<?php
declare(strict_types=1);

// tgwatch web yönetim paneli (JavaScript'siz).
require __DIR__ . '/app_path.php';
require APP_DIR . '/src/bootstrap.php';

(new TgWatch\Web\Admin(TgWatch\App::config()))->handle();
