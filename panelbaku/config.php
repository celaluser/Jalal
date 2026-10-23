<?php
/**
 * TƏHLÜKƏSİZLİK QEYDİ:
 * API açarını ideal halda server mühit dəyişəni (environment variable) kimi saxla,
 * yəni: putenv('PANEL_API_KEY=...') server konfiqurasiyasında, ya da .env faylında.
 * Heç vaxt bu açarı JavaScript / frontend koduna əlavə etmə.
 */

define('PANEL_API_URL', 'https://panelbaku.com/api/v2');
// Gizli məlumatlar config.local.php (git-ə düşmür) və ya mühit dəyişənindən gəlir
if (is_file(__DIR__ . '/config.local.php')) {
    require __DIR__ . '/config.local.php';
}
if (!defined('PANEL_API_KEY')) {
    define('PANEL_API_KEY', getenv('PANEL_API_KEY') ?: '');
}

// Servisləri neçə saniyə keşdə saxlayaq (API-yə hər dəfə sorğu göndərməmək üçün)
define('CACHE_TTL', 60); // 1 dəqiqə
define('CACHE_FILE', __DIR__ . '/cache/services.json');

// Paylaşım tarixçəsinin saxlanacağı fayl
define('HISTORY_FILE', __DIR__ . '/data/history.json');
define('HISTORY_LIMIT', 100); // ən çox neçə qeyd saxlansın
