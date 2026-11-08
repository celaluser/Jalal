<?php
declare(strict_types=1);

// Panel şifresi hash'i:     php tools/make_hash.php
//   (SSH yoksa)             php tools/make_hash.php --password='şifre'   → iş bitince cron satırını ve çıktıyı silin!
// Webhook gizli anahtarı:   php tools/make_hash.php --secret

if (PHP_SAPI !== 'cli') {
    http_response_code(403);
    exit;
}

$opts = getopt('', ['password:', 'secret']);

if (isset($opts['secret'])) {
    echo "'webhook_secret' => '", rtrim(strtr(base64_encode(random_bytes(32)), '+/', '-_'), '='), "',\n";
    exit(0);
}

$pw = $opts['password'] ?? null;
if ($pw === null) {
    if (!stream_isatty(STDIN)) {
        fwrite(STDERR, "Şifre girilemedi. --password='...' kullanın.\n");
        exit(1);
    }
    echo 'Panel şifresi (en az 12 karakter): ';
    @shell_exec('stty -echo');
    $pw = rtrim((string)fgets(STDIN), "\r\n");
    @shell_exec('stty echo');
    echo "\n";
}
if (mb_strlen($pw) < 12) {
    fwrite(STDERR, "Şifre en az 12 karakter olmalı.\n");
    exit(1);
}
$algo = defined('PASSWORD_ARGON2ID') ? PASSWORD_ARGON2ID : PASSWORD_DEFAULT;
echo "'admin_password_hash' => '", password_hash($pw, $algo), "',\n";
