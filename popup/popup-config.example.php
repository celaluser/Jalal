<?php
/**
 * =============================================================
 *  GİZLİ AYAR DOSYASI  —  ÖRNEK
 *
 *  ⚠️  Bu dosya public_html'in DIŞINDA durmalı, örneğin:
 *      /home/milyonpane/domains/uplow.us/popup-config.php
 *
 *  Sunucunda ZATEN varsa bu dosyayı yükleme — mevcut olan kalsın.
 *  Sadece yeni kurulumda bunu kopyalayıp adını
 *  popup-config.php  yap ve içini doldur.
 * =============================================================
 */

return [

    // --- Panel API'si (SMM panelinin API adresi ve anahtarı) ---
    'api_url' => 'https://panelbaku.com/api/v2',
    'api_key' => 'BURAYA_API_ANAHTARINI_YAZ',

    // --- Admin panel şifresi (bcrypt hash) ---
    // Boş bırakırsan panel ilk açılışta "İlk Kurulum" ekranı gösterir,
    // orada şifreni yazarsın ve sana buraya yapıştıracağın satırı verir.
    'admin_pass' => '',

    // --- Cron güvenliği ---
    // cron-run.php'yi HTTP üzerinden tetiklerken kullanılan anahtar.
    // UZUN ve rastgele olsun. Kod içindeki varsayılanı EZER.
    // Üretmek için:  php -r "echo bin2hex(random_bytes(24));"
    'cron_token' => 'BURAYA_UZUN_RASTGELE_BIR_ANAHTAR',

    // popup-cron.php'yi tarayıcıdan çalıştırmak istersen (genelde gerekmez).
    // Boş bırakırsan tarayıcıdan çalıştırılamaz — en güvenlisi budur.
    'run_token' => '',

    // --- API isteği zaman aşımı (saniye) ---
    'timeout' => 25,

    // --- Ana şalter: false yaparsan popup tamamen durur ---
    'enabled' => true,
];
