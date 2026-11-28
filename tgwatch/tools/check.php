<?php
declare(strict_types=1);

// Kurulum kontrolü:  php tools/check.php            (yerel kontroller + ağ testleri)
//                    php tools/check.php --offline  (ağ testleri olmadan)
// Çıktıda token/anahtar yazılmaz; yine de paylaşmadan önce gözden geçirin.

if (PHP_SAPI !== 'cli') {
    http_response_code(403);
    exit;
}
if (version_compare(PHP_VERSION, '8.1.0', '<')) {
    echo '✗ PHP ', PHP_VERSION, " — 8.1 veya üstü gerekli. Bu PHP CLI yolu: ", PHP_BINARY, "\n";
    exit(1);
}
require dirname(__DIR__) . '/src/bootstrap.php';

use TgWatch\App;
use TgWatch\Health;

$config = App::config();
echo 'tgwatch ', TgWatch\VERSION, ' · PHP ', PHP_VERSION, ' (', PHP_BINARY, ")\n\n";

$rows = Health::local($config);
if (!in_array('--offline', $argv, true)) {
    $rows = array_merge($rows, Health::network($config));
}
$fail = 0;
foreach ($rows as [$name, $st, $detail]) {
    $icon = ['ok' => '✓', 'warn' => '!', 'fail' => '✗'][$st];
    $fail += $st === 'fail' ? 1 : 0;
    printf("%s %-24s %s\n", $icon, $name, $detail);
}
echo "\n", $fail ? "$fail sorun var (✗). Düzeltip tekrar çalıştırın.\n" : "Her şey hazır görünüyor.\n";
exit($fail ? 1 : 0);
