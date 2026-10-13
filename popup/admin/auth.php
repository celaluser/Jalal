<?php
if (!defined('IGFP_ADMIN')) { http_response_code(403); exit('Forbidden'); }
/**
 * Admin girişi: oturum, CSRF, kaba kuvvet koruması.
 * Şifre hash'i public_html DIŞINDAKİ popup-config.php içinde durur.
 */

require_once dirname(__DIR__) . '/popup-core.php';

const ADM_MAX_TRY   = 5;      // kac yanlis denemeden sonra
const ADM_BLOCK_MIN = 15;     // kac dakika bloklansin

function adm_start(): void {
    if (session_status() === PHP_SESSION_ACTIVE) return;
    session_name('igfp_adm');
    session_set_cookie_params([
        'lifetime' => 0,
        'path'     => '/',
        'httponly' => true,
        'samesite' => 'Lax',
        'secure'   => (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off'),
    ]);
    session_start();
}

function adm_hash(): string {
    $c = popup_config();
    return (string) ($c['admin_pass'] ?? '');
}

function adm_ip(): string {
    return (string) ($_SERVER['REMOTE_ADDR'] ?? '0.0.0.0');
}

function adm_throttle_read(): array {
    $f = popup_paths()['throttle'];
    if (!is_file($f)) return [];
    $j = json_decode((string) @file_get_contents($f), true);
    return is_array($j) ? $j : [];
}

function adm_throttle_write(array $d): void {
    foreach ($d as $ip => $row) {                       // eskileri temizle
        if (($row['t'] ?? 0) < time() - ADM_BLOCK_MIN * 60) unset($d[$ip]);
    }
    popup_write_atomic(popup_paths()['throttle'], json_encode($d));
}

/** @return int kalan blok suresi (saniye), 0 = serbest */
function adm_blocked_for(): int {
    $d = adm_throttle_read();
    $r = $d[adm_ip()] ?? null;
    if (!$r || ($r['n'] ?? 0) < ADM_MAX_TRY) return 0;
    $left = ($r['t'] ?? 0) + ADM_BLOCK_MIN * 60 - time();
    return $left > 0 ? $left : 0;
}

function adm_fail(): void {
    $d = adm_throttle_read();
    $ip = adm_ip();
    $n  = (int) ($d[$ip]['n'] ?? 0);
    if ((int) ($d[$ip]['t'] ?? 0) < time() - ADM_BLOCK_MIN * 60) $n = 0;
    $d[$ip] = ['n' => $n + 1, 't' => time()];
    adm_throttle_write($d);
}

function adm_ok_reset(): void {
    $d = adm_throttle_read();
    unset($d[adm_ip()]);
    adm_throttle_write($d);
}

function adm_logged_in(): bool {
    adm_start();
    if (empty($_SESSION['igfp_ok'])) return false;
    if (($_SESSION['igfp_ua'] ?? '') !== substr((string) ($_SERVER['HTTP_USER_AGENT'] ?? ''), 0, 120)) return false;
    if ((int) ($_SESSION['igfp_t'] ?? 0) < time() - 7200) return false;   // 2 saat hareketsizlik
    $_SESSION['igfp_t'] = time();
    return true;
}

function adm_login(string $pass): bool {
    $h = adm_hash();
    if ($h === '' || !password_verify($pass, $h)) { adm_fail(); return false; }
    adm_start();
    session_regenerate_id(true);
    $_SESSION['igfp_ok']   = true;
    $_SESSION['igfp_ua']   = substr((string) ($_SERVER['HTTP_USER_AGENT'] ?? ''), 0, 120);
    $_SESSION['igfp_t']    = time();
    $_SESSION['igfp_csrf'] = bin2hex(random_bytes(16));
    adm_ok_reset();
    return true;
}

function adm_logout(): void {
    adm_start();
    $_SESSION = [];
    if (ini_get('session.use_cookies')) {
        $p = session_get_cookie_params();
        setcookie(session_name(), '', time() - 42000, $p['path'], $p['domain'] ?? '', $p['secure'], $p['httponly']);
    }
    session_destroy();
}

function adm_csrf(): string {
    adm_start();
    if (empty($_SESSION['igfp_csrf'])) $_SESSION['igfp_csrf'] = bin2hex(random_bytes(16));
    return $_SESSION['igfp_csrf'];
}

function adm_csrf_ok(): bool {
    adm_start();
    return isset($_POST['_t'], $_SESSION['igfp_csrf'])
        && hash_equals((string) $_SESSION['igfp_csrf'], (string) $_POST['_t']);
}
