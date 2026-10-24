<?php
declare(strict_types=1);

namespace TgWatch;

/**
 * Ayarlar sayfasının şeması. Panelden kaydedilen değerler veritabanında (cfg_overrides) tutulur
 * ve App::config() içinde config.php değerlerinin üstüne yazılır.
 */
final class Settings
{
    public const GROUPS = [
        'general' => ['Genel', 'Panelin adı ve saat dilimi.'],
        'telegram' => ['Telegram botu', 'Onay mesajlarını gönderen bot ve butonları alan webhook.'],
        'publish' => ['Yayın ve panel API', 'Onaydan sonra servislerin sitede nasıl yayınlanacağı.'],
        'reading' => ['Kanal okuma ve cron', 'Kanalların ne kadar bekleyerek okunacağı ve cron-job.org anahtarı.'],
        'security' => ['Güvenlik', 'Oturum süresi ve hatalı giriş kilidi. Şifre ve iki adımlı giriş aşağıda.'],
        'appearance' => ['Görünüm', 'Tema, vurgu rengi ve yoğunluk. Bu tarayıcıya değil tüm panele uygulanır.'],
        'data' => ['Veri ve bakım', 'Eski kayıtların otomatik temizliği.'],
    ];

    private const ZONES = ['Asia/Baku', 'Europe/Istanbul', 'Europe/Moscow', 'Europe/London', 'Europe/Berlin', 'Asia/Dubai', 'UTC'];

    /**
     * type: text | secret | int | select | url | key (rastgele anahtar üretilebilir)
     * @return array<string,array<string,mixed>>
     */
    public static function fields(): array
    {
        return [
            'panel_name' => ['g' => 'general', 'label' => 'Panel adı', 'type' => 'text', 'max' => 30, 'help' => 'Kenar menüde ve sekme başlığında görünür.'],
            'timezone' => ['g' => 'general', 'label' => 'Saat dilimi', 'type' => 'select', 'options' => array_combine(self::ZONES, self::ZONES), 'help' => 'Saatler, günlük özet ve grafikler bu saate göre gösterilir.'],

            'bot_token' => ['g' => 'telegram', 'label' => 'Bot token', 'type' => 'secret', 'help' => 'BotFather\'dan alınır. Boş bırakırsan mevcut değer korunur.'],
            'owner_id' => ['g' => 'telegram', 'label' => 'Sahip Telegram ID', 'type' => 'int', 'min' => 0, 'help' => 'Butonlara yalnızca bu hesap basabilir. Bota /start yazınca ID\'n gösterilir.'],
            'bot_mode' => ['g' => 'telegram', 'label' => 'Bot modu', 'type' => 'select', 'options' => ['webhook' => 'Webhook (anında, HTTPS gerekir)', 'polling' => 'Polling (cron aralığında)'], 'help' => 'Mod değişince Sağlık sayfasından webhook\'u kur ya da sil.'],
            'webhook_url' => ['g' => 'telegram', 'label' => 'Webhook adresi', 'type' => 'url', 'help' => 'https://alanadiniz.com/…/bot.php'],
            'webhook_secret' => ['g' => 'telegram', 'label' => 'Webhook anahtarı', 'type' => 'key', 'help' => 'Telegram her istekte bu anahtarı gönderir. Değişince webhook\'u yeniden kur.'],
            'telegram_api_base' => ['g' => 'telegram', 'label' => 'Telegram API adresi', 'type' => 'url', 'optional' => true, 'help' => 'Genelde boş. Hosting Telegram\'a erişemiyorsa kendi ara sunucunun adresi.'],

            'publisher' => ['g' => 'publish', 'label' => 'Yayın yöntemi', 'type' => 'select', 'options' => ['manual' => 'Elle (ekleme paketi panelde)', 'admin_api' => 'Admin API (henüz desteklenmiyor)'], 'help' => 'Perfect Panel servis metotlarını yayınlayınca "Admin API" açılacak.'],
            'panel_api_url' => ['g' => 'publish', 'label' => 'Panel API adresi', 'type' => 'url', 'help' => 'Örn. https://panelbaku.com/adminapi/v2'],
            'panel_api_key' => ['g' => 'publish', 'label' => 'Panel API anahtarı', 'type' => 'secret', 'help' => 'Boş bırakırsan mevcut değer korunur.'],

            'cron_key' => ['g' => 'reading', 'label' => 'Cron anahtarı', 'type' => 'key', 'help' => 'cron-job.org adresindeki key=… değeri. Değişirse cron-job.org adresini de güncelle.'],
            'fetch_timeout' => ['g' => 'reading', 'label' => 'Kanal bekleme süresi (sn)', 'type' => 'int', 'min' => 5, 'max' => 60, 'help' => 'Bir kanal bu sürede cevap vermezse atlanır.'],
            'cron_time_limit' => ['g' => 'reading', 'label' => 'Cron en uzun süre (sn)', 'type' => 'int', 'min' => 20, 'max' => 600, 'help' => 'Bir cron turunun üst sınırı.'],
            'web_cron_time_limit' => ['g' => 'reading', 'label' => 'Web cron en uzun süre (sn)', 'type' => 'int', 'min' => 10, 'max' => 25, 'help' => 'Arka planda çalışamayan hostinglerde cron-job.org 30 sn bekler.'],

            'session_minutes' => ['g' => 'security', 'label' => 'Oturum boşta süresi (dk)', 'type' => 'int', 'min' => 5, 'max' => 1440, 'help' => 'Bu kadar işlem yapılmazsa yeniden şifre sorulur.'],
            'max_fails' => ['g' => 'security', 'label' => 'Hatalı giriş hakkı', 'type' => 'int', 'min' => 3, 'max' => 20, 'help' => 'Aşılınca o IP kilitlenir.'],
            'lock_minutes' => ['g' => 'security', 'label' => 'Kilit süresi (dk)', 'type' => 'int', 'min' => 1, 'max' => 1440, 'help' => ''],

            'ui_theme' => ['g' => 'appearance', 'label' => 'Tema', 'type' => 'select', 'options' => ['auto' => 'Sistemi izle', 'light' => 'Açık', 'dark' => 'Koyu']],
            'ui_accent' => ['g' => 'appearance', 'label' => 'Vurgu rengi', 'type' => 'select', 'options' => self::ACCENTS],
            'ui_density' => ['g' => 'appearance', 'label' => 'Yoğunluk', 'type' => 'select', 'options' => ['comfortable' => 'Rahat', 'compact' => 'Sıkı (daha çok satır)']],

            'retention_logs_days' => ['g' => 'data', 'label' => 'Kayıtları sakla (gün)', 'type' => 'int', 'min' => 1, 'max' => 3650, 'help' => 'Daha eski kayıtlar günde bir silinir.'],
            'retention_jobs_days' => ['g' => 'data', 'label' => 'Bitmiş işleri sakla (gün)', 'type' => 'int', 'min' => 0, 'max' => 3650, 'help' => '0: hiç silme. Onaylanan, reddedilen ve yayınlanan işler silinir; çeviri bankası etkilenmez.'],
        ];
    }

    public const ACCENTS = ['blue' => 'Mavi', 'green' => 'Yeşil', 'purple' => 'Mor', 'orange' => 'Turuncu', 'pink' => 'Pembe'];

    /** Kayıtlı değerlerden yalnızca şemadakileri ve geçerli olanları döndürür. */
    public static function apply(array $stored): array
    {
        $out = [];
        foreach (self::fields() as $k => $f) {
            if (!array_key_exists($k, $stored)) {
                continue;
            }
            [$ok, $v] = self::check($k, $stored[$k]);
            if ($ok) {
                $out[$k] = $v;
            }
        }
        return $out;
    }

    /** @return array{0:bool,1:mixed} [geçerli mi, değer ya da hata metni] */
    public static function check(string $key, $raw): array
    {
        $f = self::fields()[$key] ?? null;
        if (!$f) {
            return [false, 'bilinmeyen ayar'];
        }
        $raw = is_string($raw) ? trim($raw) : $raw;
        switch ($f['type']) {
            case 'int':
                if (!is_numeric($raw) || (string)(int)$raw !== (string)(int)$raw) {
                    return [false, $f['label'] . ': sayı girin'];
                }
                $v = (int)$raw;
                if ($v < ($f['min'] ?? 0) || (isset($f['max']) && $v > $f['max'])) {
                    return [false, $f['label'] . ': ' . ($f['min'] ?? 0) . ' ile ' . ($f['max'] ?? '∞') . ' arasında olmalı'];
                }
                return [true, $v];
            case 'select':
                return isset($f['options'][$raw]) ? [true, (string)$raw] : [false, $f['label'] . ': geçersiz seçim'];
            case 'url':
                if ($raw === '' && ($f['optional'] ?? false)) {
                    return [true, ''];
                }
                if ($raw === '' || !preg_match('~^https?://[^\s]+$~i', (string)$raw)) {
                    return [false, $f['label'] . ': http:// ya da https:// ile başlayan bir adres girin'];
                }
                return [true, (string)$raw];
            case 'key':
                return preg_match('/^[A-Za-z0-9_-]{16,256}$/', (string)$raw) ? [true, (string)$raw] : [false, $f['label'] . ': 16–256 karakter, yalnızca harf, rakam, _ ve -'];
            case 'secret':
                return is_string($raw) && !preg_match('/\s/', $raw) ? [true, $raw] : [false, $f['label'] . ': boşluk içeremez'];
            default:
                $v = (string)$raw;
                return $v !== '' && mb_strlen($v) <= ($f['max'] ?? 200) ? [true, $v] : [false, $f['label'] . ': boş olamaz'];
        }
    }

    public static function stored(Db $db): array
    {
        $o = $db->getJson('cfg_overrides', []);
        return is_array($o) ? $o : [];
    }

    /** Tek bir değeri kaydeder (geçerliyse). */
    public static function save(Db $db, array $values): array
    {
        $errors = [];
        $o = self::stored($db);
        foreach ($values as $k => $raw) {
            [$ok, $v] = self::check($k, $raw);
            if ($ok) {
                $o[$k] = $v;
            } else {
                $errors[] = (string)$v;
            }
        }
        $db->setJson('cfg_overrides', $o);
        App::reloadOverrides();
        return $errors;
    }

    public static function reset(Db $db, string $key): void
    {
        $o = self::stored($db);
        unset($o[$key]);
        $db->setJson('cfg_overrides', $o);
    }
}
