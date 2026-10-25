<?php
declare(strict_types=1);

namespace TgWatch;

const VERSION = '1.1.0';
if (PHP_SAPI !== 'cli') { // hata ayrıntıları (dosya yolları) ziyaretçiye gösterilmesin; sunucu loguna yazılır
    @ini_set('display_errors', '0');
    @ini_set('log_errors', '1');
}
define('TGWATCH_ROOT', dirname(__DIR__));

spl_autoload_register(static function (string $class): void {
    if (strncmp($class, 'TgWatch\\', 8) !== 0) {
        return;
    }
    $file = __DIR__ . '/' . str_replace('\\', '/', substr($class, 8)) . '.php';
    if (is_file($file)) {
        require $file;
    }
});

final class App
{
    private static ?array $config = null;
    private static ?Db $db = null;
    private static bool $overlaying = false;

    /** Ayarları yükler. config.php yoksa config.sample.php varsayılanları kullanılır. */
    public static function config(): array
    {
        if (self::$config === null) {
            $defaults = require TGWATCH_ROOT . '/config.sample.php';
            $file = TGWATCH_ROOT . '/config.php';
            try {
                $user = is_file($file) ? require $file : [];
            } catch (\ParseError $e) {
                throw new \RuntimeException('config.php yazım hatası (satır ' . $e->getLine() . '): ' . $e->getMessage()
                    . ". Tırnakları ve satır sonlarındaki virgülleri kontrol edin.");
            }
            if (!is_array($user)) {
                throw new \RuntimeException('config.php bir dizi döndürmeli: return [ ... ];');
            }
            self::$config = array_merge($defaults, $user);
            // Yanlış yazılmış anahtarlar (ör. token'ı 'bot_token' yerine anahtar olarak yazmak) Sağlık'ta gösterilir.
            self::$config['_unknown_keys'] = array_values(array_map(static function ($k): string {
                $k = (string)$k;
                return mb_strlen($k) > 8 ? mb_substr($k, 0, 4) . '…' : $k;
            }, array_diff(array_keys($user), array_keys($defaults))));
            self::$config['_has_config_file'] = is_file($file);
            date_default_timezone_set((string)self::$config['timezone']);
            self::reloadOverrides();
        }
        return self::$config;
    }

    /**
     * Panelden (Ayarlar sayfası) kaydedilen değerleri config.php değerlerinin üstüne yazar.
     * Kilitlenirseniz config.php'ye 'panel_settings' => false yazın: yalnızca config.php kullanılır.
     */
    public static function reloadOverrides(): void
    {
        if (self::$overlaying || self::$config === null || (self::$config['panel_settings'] ?? true) === false) {
            return;
        }
        self::$overlaying = true;
        try {
            $o = self::db()->getJson('cfg_overrides', []);
            $hash = self::$db->get('admin_hash', '');
            if ($hash !== null && $hash !== '') { // panelden değiştirilen şifre
                self::$config['admin_password_hash'] = $hash;
            }
            if (is_array($o)) {
                foreach (Settings::apply($o) as $k => $v) {
                    self::$config[$k] = $v;
                }
                date_default_timezone_set((string)self::$config['timezone']);
            }
        } catch (\Throwable) {
            // Veritabanı açılamıyorsa config.php değerleriyle devam edilir.
        } finally {
            self::$overlaying = false;
        }
    }

    /** Testler için ayarları değiştirir. */
    public static function setConfig(array $config): void
    {
        $defaults = require TGWATCH_ROOT . '/config.sample.php';
        self::$config = array_merge($defaults, $config);
        self::$config['_has_config_file'] = true;
        self::$db = null;
    }

    public static function db(): Db
    {
        if (self::$db === null) {
            $dir = (string)self::config()['data_dir'];
            if (!is_dir($dir) && !@mkdir($dir, 0700, true)) {
                throw new \RuntimeException("data_dir oluşturulamadı: $dir");
            }
            self::$db = new Db(self::dbPath($dir));
        }
        return self::$db;
    }

    /**
     * Veritabanı dosya adı rastgeledir (data/secret.php içinde saklanır). Klasör public_html içindeyse ve
     * sunucu .htaccess'i dikkate almıyorsa (ör. nginx) bile dosya adı tahmin edilip indirilemez.
     * secret.php bir PHP dosyası olduğu için web'den açılınca içeriği görünmez.
     */
    public static function dbPath(string $dir): string
    {
        $file = $dir . '/secret.php';
        $fh = @fopen($file, 'x'); // yalnızca yoksa oluştur (iki süreç aynı anda başlarsa yarış olmaz)
        if ($fh !== false) {
            fwrite($fh, "<?php\n// Otomatik oluşturuldu. Silmeyin: veritabanı dosyasının adı buna bağlı.\nreturn '" . bin2hex(random_bytes(12)) . "';\n");
            fclose($fh);
            @chmod($file, 0600);
        }
        $secret = '';
        for ($i = 0; $i < 20 && $secret === ''; $i++) { // diğer süreç yazmayı bitirene kadar kısa bekle
            $v = @include $file;
            $secret = is_string($v) && preg_match('/^[a-f0-9]{24}$/', $v) ? $v : '';
            if ($secret === '') {
                usleep(50000);
            }
        }
        if ($secret === '') {
            throw new \RuntimeException("data/secret.php okunamadı: $file");
        }
        $path = $dir . '/tgwatch_' . $secret . '.sqlite';
        if (!is_file($path) && is_file($dir . '/tgwatch.sqlite')) {
            @rename($dir . '/tgwatch.sqlite', $path); // eski sürümden taşı
        }
        return $path;
    }

    /** Web sunucusunun kök klasörü altındaysa true (ör. public_html/...). */
    public static function insideDocRoot(string $path): bool
    {
        $root = isset($_SERVER['DOCUMENT_ROOT']) ? realpath((string)$_SERVER['DOCUMENT_ROOT']) : false;
        $real = realpath($path);
        return $root && $real && str_starts_with($real . '/', rtrim($root, '/') . '/');
    }

    public static function telegram(): Telegram
    {
        return new Telegram((string)self::config()['bot_token'], (string)(self::config()['telegram_api_base'] ?? ''));
    }

    public static function flow(): Flow
    {
        return new Flow(self::db(), self::telegram(), self::config());
    }
}
