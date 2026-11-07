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
        'features' => ['Özellikler', 'Yeni özelliklerin hepsi burada: aç, kapat, çalıştır.'],
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
            'admin_name' => ['g' => 'general', 'label' => 'Yönetici adı', 'type' => 'text', 'max' => 40, 'help' => 'Profil menüsünde ve profil sayfasında görünür.'],
            'panel_name' => ['g' => 'general', 'label' => 'Panel adı', 'type' => 'text', 'max' => 30, 'help' => 'Kenar menüde ve sekme başlığında görünür.'],
            'timezone' => ['g' => 'general', 'label' => 'Saat dilimi', 'type' => 'select', 'options' => array_combine(self::ZONES, self::ZONES), 'help' => 'Saatler, günlük özet ve grafikler bu saate göre gösterilir.'],

            'bot_token' => ['g' => 'telegram', 'label' => 'Bot token', 'type' => 'secret', 'help' => 'BotFather\'dan alınır. Boş bırakırsan mevcut değer korunur.'],
            'owner_id' => ['g' => 'telegram', 'label' => 'Sahip Telegram ID', 'type' => 'int', 'min' => 0, 'help' => 'Butonlara yalnızca bu hesap basabilir. Bota /start yazınca ID\'n gösterilir.'],
            'bot_mode' => ['g' => 'telegram', 'label' => 'Bot modu', 'type' => 'select', 'options' => ['webhook' => 'Webhook (anında, HTTPS gerekir)', 'polling' => 'Polling (cron aralığında)'], 'help' => 'Mod değişince Sağlık sayfasından webhook\'u kur ya da sil.'],
            'webhook_url' => ['g' => 'telegram', 'label' => 'Webhook adresi', 'type' => 'url', 'optional' => true, 'help' => 'https://alanadiniz.com/…/bot.php'],
            'webhook_secret' => ['g' => 'telegram', 'label' => 'Webhook anahtarı', 'type' => 'key', 'help' => 'Telegram her istekte bu anahtarı gönderir. Değişince webhook\'u yeniden kur.'],
            'telegram_api_base' => ['g' => 'telegram', 'label' => 'Telegram API adresi', 'type' => 'url', 'optional' => true, 'help' => 'Genelde boş. Hosting Telegram\'a erişemiyorsa kendi ara sunucunun adresi.'],

            'publisher' => ['g' => 'publish', 'label' => 'Yayın yöntemi', 'type' => 'select', 'options' => ['manual' => 'Elle (ekleme paketi panelde)', 'admin_api' => 'Admin API (henüz desteklenmiyor)'], 'help' => 'Perfect Panel servis oluşturma/düzenleme metotlarını yayınlayınca "Admin API" açılacak.'],
            'panel_api_url' => ['g' => 'publish', 'label' => 'Panel API adresi', 'type' => 'url', 'help' => 'Örn. https://panelbaku.com/adminapi/v2. Aşağıdaki anahtarla birlikte girilince şimdiden Çeviri bankası → "Sipariş geçmişinden sağlayıcıyı öğren" için kullanılır (Orders uç noktası zaten yayında).'],
            'panel_api_key' => ['g' => 'publish', 'label' => 'Panel API anahtarı', 'type' => 'secret', 'help' => 'Panelin kendi Admin API v2 anahtarı (X-Api-Key). Boş bırakırsan mevcut değer korunur.'],

            'cron_key' => ['g' => 'reading', 'label' => 'Cron anahtarı', 'type' => 'key', 'help' => 'cron-job.org adresindeki key=… değeri. Değişirse cron-job.org adresini de güncelle.'],
            'monitor_key' => ['g' => 'reading', 'label' => 'İzleme anahtarı', 'type' => 'key', 'help' => 'UptimeRobot gibi dış bir servisin sorgulayacağı adresin anahtarı (aşağıda adres görünür). Cron durursa ya da kanal okunamazsa adres hata döner ve o servis sana e-posta atar: Telegram\'dan bağımsız ikinci uyarı yolu.'],
            'alert_email' => ['g' => 'telegram', 'label' => 'Uyarı e-postası', 'type' => 'email', 'optional' => true, 'help' => 'Boşsa kapalı. Uyarılar Telegram\'a gitmezse (bot bozuk, engelli) bu adrese e-posta gider. Hosting e-postası gönderebiliyor olmalı.'],
            'alert_email_mode' => ['g' => 'telegram', 'label' => 'E-posta ne zaman', 'type' => 'select', 'options' => ['fallback' => 'Yalnızca Telegram gitmezse', 'all' => 'Her uyarı e-postaya da gitsin']],
            'import_notify' => ['g' => 'telegram', 'default' => 'on', 'label' => 'Import bildirimi', 'type' => 'select', 'options' => ['on' => 'Açık: import bitince bota özet gelsin', 'off' => 'Kapalı'], 'help' => 'Chrome eklentisi import sonrası kategoriyi çevirip en üste taşıyınca bota özet gelir: kategori, servis sayısı, çeviri durumu.'],
            'share_mode' => ['g' => 'telegram', 'default' => 'ask', 'label' => 'Kanalda paylaşım', 'type' => 'select', 'options' => ['ask' => 'Bildirimde "Kanalda paylaş" düğmesi (ben onaylarım)', 'auto' => 'Otomatik paylaş', 'off' => 'Kapalı'], 'help' => 'Yeni kategorinin servisleri (ID, ad, fiyat, min/max) kendi panelinin API\'sinden çekilir, aşağıdaki şablonla kanala gönderilir.'],
            'share_channel' => ['g' => 'telegram', 'label' => 'Paylaşım kanalı', 'type' => 'channel', 'optional' => true, 'help' => '@kanaladi ya da -100… ile başlayan kanal ID\'si. Bot kanalda yönetici olmalı (mesaj gönderme izni).'],
            'share_link' => ['g' => 'telegram', 'label' => 'Sipariş adresi', 'type' => 'text', 'optional' => true, 'max' => 120, 'help' => 'Şablondaki {panel} yerine yazılır. Örn: https://panelbaku.com'],
            'share_currency' => ['g' => 'telegram', 'default' => '₼', 'label' => 'Para birimi', 'type' => 'text', 'optional' => true, 'max' => 8, 'help' => 'Şablondaki {valyuta}. Fiyat, kendi panelinin API\'sinin verdiği 1000 adet fiyatıdır.'],
            'share_template' => ['g' => 'telegram', 'default' => ImportShare::DEFAULT_TEMPLATE, 'label' => 'Kanal şablonu', 'type' => 'textarea', 'optional' => true, 'max' => 3000, 'help' => 'Yer tutucular: {kategori} {servisler} {say} {tarix} {panel} {valyuta}. Telegram HTML: <b>kalın</b>, <i>eğik</i>, <a href="…">bağlantı</a>. Boş bırakırsan varsayılan.'],
            'share_line' => ['g' => 'telegram', 'default' => ImportShare::DEFAULT_LINE, 'label' => 'Servis satırı', 'type' => 'textarea', 'optional' => true, 'max' => 600, 'help' => '{servisler} içinde her servis için tekrarlanır. Yer tutucular: {id} {ad} {qiymet} {min} {max} {valyuta}.'],
            'priority_source' => ['g' => 'reading', 'label' => 'Öncelikli sağlayıcı', 'type' => 'source', 'help' => 'Seçersen aynı servisi farklı sağlayıcıların kanalları duyurunca bu sağlayıcı tercih edilir: onun işi diğerlerinin yerine geçer, diğerlerinden gelen aynı servis için yeni iş açılmaz. Boş: ilk gelen kazanır. Kanalların sağlayıcısı Kanallar sayfasından seçilmiş olmalı.'],
            'fx_auto' => ['g' => 'reading', 'hidden' => true, 'label' => 'Kurları otomatik güncelle', 'type' => 'bool'],
            'fx_rates' => ['g' => 'reading', 'label' => 'Manuel kurlar (1 birim = kaç $)', 'type' => 'text', 'max' => 200, 'help' => 'Otomatik kapalıysa ya da çekilemezse kullanılır. Kanal fiyatı $ dışındaysa (₽, ₺, ₼, €) dolara çevirmek için. Örnek: RUB=0.011, TRY=0.029, AZN=0.588, EUR=1.08. Boşsa fiyat çevrilmez ve mesajda uyarı çıkar.'],
            'api_watch_minutes' => ['g' => 'features', 'default' => 30, 'label' => 'Kaç dakikada bir kontrol edilsin', 'type' => 'int', 'min' => 5, 'max' => 1440, 'help' => 'Yukarıda açık sağlayıcılar bu aralıkla kontrol edilir. Cron bundan sık çalışsa da en az bu kadar beklenir.'],
            'ext_key' => ['g' => 'features', 'label' => 'Eklenti anahtarı', 'type' => 'key', 'help' => 'Chrome eklentisinin tgwatch\'a bağlanması için. "Yeni rastgele anahtar üret"i işaretleyip kaydet, sonra yukarıdaki kartta görünen adres ve anahtarı eklentinin ayarlarına gir.'],
            'rival_minutes' => ['g' => 'features', 'default' => 360, 'label' => 'Rakip fiyatları kaç dakikada bir çekilsin', 'type' => 'int', 'min' => 30, 'max' => 10080, 'help' => 'Rakipler sekmesindeki rakiplerin servis listesi bu aralıkla cron ile çekilir ve kıyas yenilenir. 360 = günde 4 kez.'],
            'rival_alert_pct' => ['g' => 'features', 'default' => 10, 'label' => 'Rakip bu kadar ucuzsa bota haber ver (%)', 'type' => 'int', 'min' => 1, 'max' => 90, 'help' => 'Rakibin fiyatı seninkinden en az bu kadar düşükse (ya da bu kadar değiştiyse) bota mesaj gelir. Uyarılar sayfasından kapatılabilir.'],
            'dedupe_days' => ['g' => 'reading', 'default' => 7, 'label' => 'Aynı servisi tekrar sayma (gün)', 'type' => 'int', 'min' => 0, 'max' => 90, 'help' => 'Aynı sağlayıcıdaki aynı servis ID\'si birden çok kanalda paylaşılırsa tek iş açılır, diğer kanallar "ayrıca paylaşan" olarak eklenir. 0: kapalı.'],
            'fetch_timeout' => ['g' => 'reading', 'label' => 'Kanal bekleme süresi (sn)', 'type' => 'int', 'min' => 5, 'max' => 60, 'help' => 'Bir kanal bu sürede cevap vermezse atlanır.'],
            'cron_time_limit' => ['g' => 'reading', 'label' => 'Cron en uzun süre (sn)', 'type' => 'int', 'min' => 20, 'max' => 600, 'help' => 'Bir cron turunun üst sınırı.'],
            'web_cron_time_limit' => ['g' => 'reading', 'label' => 'Web cron en uzun süre (sn)', 'type' => 'int', 'min' => 10, 'max' => 25, 'help' => 'Arka planda çalışamayan hostinglerde cron-job.org 30 sn bekler.'],

            'session_minutes' => ['g' => 'security', 'label' => 'Oturum boşta süresi (dk)', 'type' => 'int', 'min' => 5, 'max' => 1440, 'help' => 'Bu kadar işlem yapılmazsa yeniden şifre sorulur.'],
            'max_fails' => ['g' => 'security', 'label' => 'Hatalı giriş hakkı', 'type' => 'int', 'min' => 3, 'max' => 20, 'help' => 'Aşılınca o IP kilitlenir.'],
            'lock_minutes' => ['g' => 'security', 'label' => 'Kilit süresi (dk)', 'type' => 'int', 'min' => 1, 'max' => 1440, 'help' => ''],

            'ui_style' => ['g' => 'appearance', 'label' => 'Tasarım stili', 'type' => 'select', 'options' => ['buta' => 'Buta: kervansaray defteri (nila, rövənd, zəfəran; slab serif, dokuma şerit)', 'impuls' => 'İmpuls: koyu kumanda paneli (sintetik firuzə, mono rakamlar, ızgara)', 'cam' => 'Cam: buzlu cam, iOS tarzı (önceki)'], 'help' => 'Vurgu rengi yalnızca Cam stilinde geçerlidir; Buta ve İmpuls kendi boya paletini kullanır.'],
            'ui_theme' => ['g' => 'appearance', 'label' => 'Tema', 'type' => 'select', 'options' => ['auto' => 'Sistemi izle', 'light' => 'Açık', 'dark' => 'Koyu']],
            'ui_accent' => ['g' => 'appearance', 'label' => 'Vurgu rengi', 'type' => 'select', 'options' => self::ACCENTS],
            'ui_density' => ['g' => 'appearance', 'label' => 'Yoğunluk', 'type' => 'select', 'options' => ['comfortable' => 'Rahat', 'compact' => 'Sıkı (daha çok satır)']],

            'backup_auto' => ['g' => 'data', 'label' => 'Otomatik yedek', 'type' => 'select', 'options' => ['off' => 'Kapalı', 'daily' => 'Her gün', 'weekly' => 'Haftada bir'], 'help' => 'Yedek dosyası (çeviri bankası, sözlük, kanal listesi, ayarlar) cron ile gönderilir. API anahtarı ve şifre içermez.'],
            'backup_to' => ['g' => 'data', 'label' => 'Yedek nereye', 'type' => 'select', 'options' => ['telegram' => 'Telegram (sana dosya olarak)', 'email' => 'E-posta (Uyarı e-postasına)', 'both' => 'İkisine de']],
            'backup_hour' => ['g' => 'data', 'label' => 'Yedek saati', 'type' => 'int', 'min' => 0, 'max' => 23, 'help' => 'Bu saatten sonraki ilk cron turunda gönderilir.'],
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
            case 'email':
                if ($raw === '' && ($f['optional'] ?? false)) {
                    return [true, ''];
                }
                return filter_var($raw, FILTER_VALIDATE_EMAIL) ? [true, (string)$raw] : [false, $f['label'] . ': geçerli bir e-posta adresi girin'];
            case 'source':
                return $raw === '' || ctype_digit((string)$raw) ? [true, (string)$raw] : [false, $f['label'] . ': geçersiz sağlayıcı'];
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
            case 'channel':
                if ($raw === '') {
                    return [true, ''];
                }
                $raw = (string)$raw;
                if (preg_match('~^(?:https?://)?t\.me/([A-Za-z0-9_]{4,64})/?$~i', $raw, $m)) {
                    $raw = '@' . $m[1];
                }
                return preg_match('/^(@[A-Za-z][A-Za-z0-9_]{3,63}|-100\d{5,20})$/', $raw) ? [true, $raw] : [false, $f['label'] . ': @kanaladi ya da -100… ile başlayan ID girin'];
            case 'textarea':
                $v = str_replace(["\r\n", "\r"], "\n", (string)$raw);
                if ($v === '' && ($f['optional'] ?? false)) {
                    return [true, ''];
                }
                return $v !== '' && mb_strlen($v) <= ($f['max'] ?? 2000) ? [true, $v] : [false, $f['label'] . ': en çok ' . ($f['max'] ?? 2000) . ' karakter'];
            default:
                $v = (string)$raw;
                if ($v === '' && ($f['optional'] ?? false)) {
                    return [true, ''];
                }
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
