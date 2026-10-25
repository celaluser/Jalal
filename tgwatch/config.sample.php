<?php
// tgwatch ayarları. Bu dosyayı config.php adıyla kopyalayıp doldurun.
// config.php gizli bilgiler içerir: sohbetlere, git'e veya public_html'e koymayın.
return [
    'timezone' => 'Asia/Baku',

    // Veritabanı, log ve kilit dosyalarının klasörü (public_html DIŞINDA olmalı).
    'data_dir' => __DIR__ . '/data',

    // BotFather'dan alınan token. Örn: 123456789:AA...
    'bot_token' => '',

    // Butonları kullanabilecek tek Telegram hesabının sayısal ID'si.
    'owner_id' => 0,

    // 'webhook': Telegram butonlara basılınca bot.php'yi çağırır (HTTPS gerekir, anında).
    // 'polling': cron her çalıştığında getUpdates ile yoklar (HTTPS gerekmez, cron aralığı kadar gecikir).
    'bot_mode' => 'webhook',

    // Webhook adresi, örn: https://alanadiniz.com/tgadmin/bot.php
    'webhook_url' => '',

    // Webhook doğrulama anahtarı: tools/make_hash.php --secret ile üretin (yalnızca A-Z a-z 0-9 _ -).
    'webhook_secret' => '',

    // Panel girişi. false: şifresiz açılır, adresi bilen herkes paneli yönetir! Kapalıyken token/adres/anahtar gibi
    // hassas ayarlar panelden değiştirilemez. Güvenli alternatif: açık bırakıp 'allowed_ips' ile IP sınırı koymak.
    'login_required' => true,

    // Panele yalnızca bu IP'ler girebilir (virgülle; tek IP ya da 10.0.0.0/24). Boş: kısıtlama yok.
    'allowed_ips' => '',

    // Web panel şifresinin hash'i: php tools/make_hash.php ile üretin.
    'admin_password_hash' => '',

    // İsteğe bağlı: "Yeniden oluştur" butonu için Claude API anahtarı. Boşsa kural tabanlı çeviri kullanılır.
    'claude_api_key' => '',
    'claude_model' => 'claude-sonnet-5-5',

    // Yayın yöntemi: 'manual' (onaydan sonra import paketi bota gönderilir).
    // 'admin_api' Perfect Panel servis metotları yayınlanınca doldurulacak.
    'publisher' => 'manual',

    // Perfect Panel Admin API v2 (şimdilik kullanılmıyor).
    'panel_api_url' => 'https://panelbaku.com/adminapi/v2',
    'panel_api_key' => '',

    // Web cron (cron-job.org) anahtarı: public/webcron.php?key=... Panelin Kurulum ekranı üretir (yalnızca A-Z a-z 0-9 _ -).
    'cron_key' => '',

    // İki adımlı giriş (Telegram kodu) panelden açılır. Kilitlenirseniz buraya false yazarak zorla kapatın.
    'login_2fa' => null,

    // Telegram API adresi. Boş bırakın; hosting Telegram'a erişemiyorsa kendi ara sunucunuzun adresini yazın.
    'telegram_api_base' => '',

    // Ayarlar sayfasından kaydedilen değerler config.php'nin üstüne yazılır. Kilitlenirseniz false yapın.
    'panel_settings' => true,

    // Panel ve güvenlik (Ayarlar sayfasından da değiştirilir)
    'panel_name' => 'tgwatch',
    'session_minutes' => 120,
    'max_fails' => 5,
    'lock_minutes' => 15,
    'retention_logs_days' => 30,
    'retention_jobs_days' => 0,   // 0: işleri hiç silme
    'ui_theme' => 'auto',         // auto | light | dark
    'ui_accent' => 'blue',
    'ui_density' => 'comfortable',

    // Aynı servisi birden çok kanal paylaşırsa, bu kadar gün içinde tekrar iş açılmaz (0: kapalı).
    'dedupe_days' => 7,

    // Öncelikli sağlayıcının numarası (Ayarlar'dan seçilir); boş: kapalı.
    'priority_source' => '',

    // Kanal fiyatı $ dışındaysa kurlar (1 birim = kaç $). Örnek: 'RUB=0.011, TRY=0.029'
    'fx_rates' => '',

    // Uyarı e-postası (boş: kapalı) ve dış izleme adresi anahtarı (public/monitor.php?key=...)
    'alert_email' => '',
    'alert_email_mode' => 'fallback',
    'monitor_key' => '',

    // Otomatik yedek: off | daily | weekly; hedef: telegram | email | both; saat 0-23
    'backup_auto' => 'daily',
    'backup_to' => 'telegram',
    'backup_hour' => 4,

    // Kanal okuma ayarları
    'fetch_timeout' => 20,
    'cron_time_limit' => 50,      // komut satırı cron'u için saniye
    'web_cron_time_limit' => 20,  // web cron için saniye (cron-job.org 30 sn bekler)
];
