<?php
declare(strict_types=1);

namespace TgWatch;

/** Kurulum ve çalışma kontrolleri (tools/check.php ve web paneldeki Sağlık sayfası). */
final class Health
{
    public const OK = 'ok';
    public const WARN = 'warn';
    public const FAIL = 'fail';

    /** @return list<array{0:string,1:string,2:string}> [başlık, durum, ayrıntı] */
    public static function local(array $c): array
    {
        $r = [];
        $r[] = ['PHP sürümü', version_compare(PHP_VERSION, '8.1.0', '>=') ? self::OK : self::FAIL, PHP_VERSION . ' (8.1+ gerekli)'];
        foreach (['curl', 'mbstring', 'dom', 'pdo_sqlite', 'json'] as $ext) {
            $r[] = ["Eklenti: $ext", extension_loaded($ext) ? self::OK : self::FAIL, extension_loaded($ext) ? 'yüklü' : 'YOK — PHP seçicisinden açın'];
        }
        $r[] = ['config.php', !empty($c['_has_config_file']) ? self::OK : self::FAIL, (!empty($c['_has_config_file']) ? 'okunan dosya: ' : 'yok — config.sample.php dosyasını şu adla kopyalayın: ') . TGWATCH_ROOT . '/config.php'];
        if (!empty($c['_unknown_keys'])) {
            $r[] = ['config.php anahtarları', self::FAIL, 'tanınmayan: ' . implode(', ', $c['_unknown_keys'])
                . " — değerler sağ tarafa yazılmalı, ör. 'bot_token' => '123:AA...', 'owner_id' => 123"];
        }
        $tokOk = (new Telegram((string)$c['bot_token']))->configured();
        $r[] = ['bot_token', $tokOk ? self::OK : self::FAIL, Telegram::describeToken((string)$c['bot_token'])];
        $r[] = ['owner_id', (int)$c['owner_id'] > 0 ? self::OK : self::FAIL, (int)$c['owner_id'] > 0 ? 'ayarlı' : 'boş — bota /start yazın, gelen ID\'yi girin'];
        $r[] = ['admin_password_hash', str_starts_with((string)$c['admin_password_hash'], '$') ? self::OK : self::FAIL, $c['admin_password_hash'] === '' ? 'boş — php tools/make_hash.php' : 'ayarlı'];
        if ($c['bot_mode'] === 'webhook') {
            $okUrl = str_starts_with((string)$c['webhook_url'], 'https://');
            $r[] = ['webhook_url', $okUrl ? self::OK : self::FAIL, $okUrl ? $c['webhook_url'] : 'https:// ile başlamalı (ya da bot_mode => polling)'];
            $r[] = ['webhook_secret', preg_match('/^[A-Za-z0-9_-]{16,256}$/', (string)$c['webhook_secret']) ? self::OK : self::FAIL, $c['webhook_secret'] === '' ? 'boş — php tools/make_hash.php --secret' : 'ayarlı'];
        } else {
            $r[] = ['bot_mode', self::OK, 'polling (butonlar cron aralığında işlenir)'];
        }
        $r[] = ['cron_key', preg_match('/^[A-Za-z0-9_-]{16,256}$/', (string)$c['cron_key']) ? self::OK : self::WARN,
            $c['cron_key'] === '' ? 'boş — cron-job.org kullanacaksanız gerekli' : 'ayarlı'];
        $r[] = ['publisher', $c['publisher'] === 'manual' ? self::OK : self::WARN, $c['publisher'] === 'manual' ? 'manual (import paketi)' : 'admin_api henüz desteklenmiyor'];
        $r[] = ['Claude API', $c['claude_api_key'] !== '' ? self::OK : self::WARN, $c['claude_api_key'] !== '' ? 'ayarlı (denenmedi)' : 'yok — Yeniden oluştur kural tabanlı çalışır'];

        $dir = (string)$c['data_dir'];
        $r[] = ['data_dir yazılabilir', is_dir($dir) && is_writable($dir) ? self::OK : self::FAIL, $dir];
        if (App::insideDocRoot(TGWATCH_ROOT)) {
            $missing = array_filter(['', '/src', '/tools', '/tests', '/data'], static fn($d) => !is_file(TGWATCH_ROOT . $d . '/.htaccess'));
            $r[] = ['Web koruması', $missing ? self::FAIL : self::OK, $missing
                ? '.htaccess eksik: ' . implode(', ', array_map(static fn($d) => ($d ?: '/') , $missing)) . ' — zip\'teki gizli dosyalar (.htaccess) açılmamış olabilir'
                : 'tgwatch public_html içinde; .htaccess dosyaları yerinde, veritabanı adı rastgele'];
        }
        if (is_dir($dir) && App::insideDocRoot($dir) && !is_file($dir . '/.htaccess')) {
            $r[] = ['data_dir konumu', self::WARN, 'public_html içinde ve .htaccess yok (veritabanı adı rastgele olduğu için yine de tahmin edilemez)'];
        }
        if (is_file(TGWATCH_ROOT . '/config.php') && (fileperms(TGWATCH_ROOT . '/config.php') & 0o004)) {
            $r[] = ['config.php izinleri', self::WARN, 'herkes okuyabilir — 600 ya da 640 yapın'];
        }

        try {
            $db = App::db();
            $r[] = ['Veritabanı', self::OK, 'açıldı'];
            $last = (int)$db->get('cron_last_run', '0');
            $age = time() - $last;
            $r[] = ['Cron son çalışma', $last === 0 ? self::FAIL : ($age < 600 ? self::OK : self::WARN),
                $last === 0 ? 'hiç çalışmadı' : date('Y-m-d H:i:s', $last) . " ($age sn önce)"];
            $res = $db->getJson('cron_last_result');
            if (is_array($res)) {
                $r[] = ['Cron son sonuç', $res['errors'] ? self::WARN : self::OK, "{$res['errors']} hata, {$res['seconds']} sn"];
            }
            foreach ($db->all('SELECT * FROM channels ORDER BY username') as $ch) {
                $state = !$ch['enabled'] ? self::WARN : ($ch['last_error'] ? self::FAIL : ($ch['last_ok_at'] ? self::OK : self::WARN));
                $detail = !$ch['enabled'] ? 'kapalı' : ($ch['last_error'] ?: ($ch['last_ok_at'] ? 'son okuma ' . date('H:i:s', (int)$ch['last_ok_at']) : 'henüz okunmadı'));
                $r[] = ['Kanal @' . $ch['username'], $state, $detail];
            }
        } catch (\Throwable $e) {
            $r[] = ['Veritabanı', self::FAIL, $e->getMessage()];
        }
        return $r;
    }

    /** Ağ gerektiren kontroller (yavaş olabilir). */
    public static function network(array $c): array
    {
        $r = [];
        $t = Http::get('https://t.me/s/telegram', 15);
        $parsed = $t['error'] === '' ? ChannelReader::parse($t['body']) : null;
        $r[] = ['t.me erişimi', $t['error'] === '' && $t['status'] === 200 ? self::OK : self::FAIL,
            $t['error'] !== '' ? $t['error'] : 'HTTP ' . $t['status'] . ($parsed ? ', ' . count($parsed['posts']) . ' gönderi ayrıştırıldı' : '')];
        if ($parsed && $t['status'] === 200 && !$parsed['posts']) {
            $r[] = ['t.me/s ayrıştırma', self::FAIL, $parsed['error'] ?: 'gönderi bulunamadı'];
        }

        $tg = new Telegram((string)$c['bot_token']);
        if (!$tg->configured()) {
            $r[] = ['Bot API', self::FAIL, 'bot_token: ' . Telegram::describeToken((string)$c['bot_token'])];
            return $r;
        }
        try {
            $me = $tg->call('getMe', [], 15);
            $r[] = ['Bot API (getMe)', self::OK, '@' . ($me['username'] ?? '?')];
        } catch (\Throwable $e) {
            $r[] = ['Bot API (getMe)', self::FAIL, $e->getMessage()];
            return $r;
        }
        try {
            $wh = $tg->call('getWebhookInfo', [], 15);
            $url = (string)($wh['url'] ?? '');
            if ($c['bot_mode'] === 'webhook') {
                $state = $url === $c['webhook_url'] ? self::OK : self::FAIL;
                $detail = $url === '' ? 'kurulu değil — php tools/set_webhook.php' : ($url === $c['webhook_url'] ? 'kurulu' : "farklı adres: $url");
                if (!empty($wh['last_error_message'])) {
                    $state = self::WARN;
                    $detail .= ' · son hata: ' . $wh['last_error_message'] . ' (' . date('Y-m-d H:i', (int)($wh['last_error_date'] ?? 0)) . ')';
                }
                if (!empty($wh['pending_update_count'])) {
                    $detail .= ' · bekleyen: ' . $wh['pending_update_count'];
                }
                $r[] = ['Webhook', $state, $detail];
            } else {
                $r[] = ['Webhook', $url === '' ? self::OK : self::FAIL, $url === '' ? 'yok (polling için doğru)' : "kurulu ($url) — polling çalışmaz, tools/set_webhook.php --delete"];
            }
        } catch (\Throwable $e) {
            $r[] = ['Webhook', self::FAIL, $e->getMessage()];
        }
        if ($c['claude_api_key'] !== '') {
            $a = Http::request('GET', 'https://api.anthropic.com/v1/models', ['x-api-key: ' . $c['claude_api_key'], 'anthropic-version: 2023-06-01'], null, 15);
            $r[] = ['Claude API', $a['status'] === 200 ? self::OK : self::FAIL, $a['error'] ?: 'HTTP ' . $a['status']];
        }
        return $r;
    }
}
