<?php
/**
 * TELEGRAM BOT AYARLARI
 *
 * 1. Telegram-da @BotFather ilə yeni bot yarat -> sənə token verəcək.
 * 2. Həmin botu paylaşım ediləcək KANALA ADMIN et (ən azı "Mesaj göndər" icazəsi ilə).
 * 3. Kanalın ID-sini əlavə et:
 *    - Açıq kanaldırsa: '@kanal_useradi'
 *    - Bağlı/private kanaldırsa: '-100' ilə başlayan rəqəmsal ID (bot vasitəsilə tapıla bilər).
 *
 * Dəyərləri config.local.php-də define() et və ya mühit dəyişəni kimi ver.
 */

if (!defined('TELEGRAM_BOT_TOKEN')) {
    define('TELEGRAM_BOT_TOKEN', getenv('TELEGRAM_BOT_TOKEN') ?: '');
}
if (!defined('TELEGRAM_CHANNEL_ID')) {
    define('TELEGRAM_CHANNEL_ID', getenv('TELEGRAM_CHANNEL_ID') ?: '');
}
