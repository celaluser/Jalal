<?php
declare(strict_types=1);

namespace TgWatch;

const VERSION = '1.0.0';
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

    /** Ayarları yükler. config.php yoksa config.sample.php varsayılanları kullanılır. */
    public static function config(): array
    {
        if (self::$config === null) {
            $defaults = require TGWATCH_ROOT . '/config.sample.php';
            $file = TGWATCH_ROOT . '/config.php';
            $user = is_file($file) ? require $file : [];
            self::$config = array_merge($defaults, is_array($user) ? $user : []);
            self::$config['_has_config_file'] = is_file($file);
            date_default_timezone_set((string)self::$config['timezone']);
        }
        return self::$config;
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
            self::$db = new Db($dir . '/tgwatch.sqlite');
        }
        return self::$db;
    }

    public static function telegram(): Telegram
    {
        return new Telegram((string)self::config()['bot_token']);
    }

    public static function flow(): Flow
    {
        return new Flow(self::db(), self::telegram(), self::config());
    }
}
