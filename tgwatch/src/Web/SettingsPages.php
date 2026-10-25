<?php
declare(strict_types=1);

namespace TgWatch\Web;

use TgWatch\App;
use TgWatch\Cleaner;
use TgWatch\Enrich;
use TgWatch\Settings;
use TgWatch\Telegram;

/** Ayarlar sayfası: config.php'deki her şey panelden değiştirilir; ayrıca şifre, oturumlar ve bakım. */
trait SettingsPages
{
    /** Bunlar değişirse mevcut panel şifresi sorulur: çalınmış bir oturum botu/uyarıları başka yere yönlendiremesin. */
    private static function sensitiveKeys(): array
    {
        return ['bot_token', 'owner_id', 'telegram_api_base', 'webhook_url', 'webhook_secret', 'cron_key', 'monitor_key',
            'panel_api_url', 'panel_api_key', 'publisher', 'alert_email', 'alert_email_mode'];
    }

    /** @return array<string,string> (trait'te sabit PHP 8.2 ister; 8.1 için metot) */
    private static function settingsTabs(): array
    {
        return ['general' => 'Genel', 'telegram' => 'Telegram', 'publish' => 'Yayın', 'reading' => 'Kanal okuma',
            'security' => 'Güvenlik', 'appearance' => 'Görünüm', 'data' => 'Veri ve bakım'];
    }

    private function settingsPage(): void
    {
        $tab = (string)($_GET['tab'] ?? 'general');
        $tab = isset(self::settingsTabs()[$tab]) ? $tab : 'general';
        $nav = '<nav class="tabs" aria-label="Ayar bölümleri">';
        foreach (self::settingsTabs() as $k => $label) {
            $nav .= '<a href="?p=settings&amp;tab=' . $k . '"' . ($tab === $k ? ' aria-current="page"' : '') . '>' . Ui::e($label) . '</a>';
        }
        $nav .= '</nav>';

        [$title, $intro] = Settings::GROUPS[$tab];
        $h = $nav . '<p class="muted lead">' . Ui::e($intro) . '</p>' . $this->settingsForm($tab);
        $h .= match ($tab) {
            'telegram' => $this->telegramTools(),
            'publish' => $this->publishTools(),
            'reading' => $this->readingTools(),
            'security' => $this->securityTools(),
            'data' => $this->dataTools(),
            default => '',
        };
        $this->render('Ayarlar', $h, ['active' => 'settings', 'sub' => 'Bütün ayarlar burada. config.php\'ye dokunmadan değiştirebilirsin.']);
    }

    private function settingsForm(string $tab): string
    {
        $stored = Settings::stored($this->db);
        $rows = '';
        foreach (Settings::fields() as $k => $f) {
            if ($f['g'] !== $tab) {
                continue;
            }
            $cur = $this->config[$k] ?? '';
            $from = array_key_exists($k, $stored) ? 'panelden' : 'config.php';
            $help = $f['help'] ?? '';
            $id = 'f_' . $k;
            $input = match ($f['type']) {
                'select' => $this->selectInput($id, "f[$k]", $f['options'], (string)$cur),
                'source' => $this->selectInput($id, "f[$k]", ['' => 'Kapalı (ilk gelen kazanır)'] + array_column(array_map(static fn($s) => [(string)$s['id'], '⭐ ' . $s['name']], (new Enrich($this->db))->providers()), 1, 0), (string)$cur),
                'int' => '<input type="number" id="' . $id . '" name="f[' . $k . ']" value="' . Ui::e((string)$cur) . '"' . (isset($f['min']) ? ' min="' . $f['min'] . '"' : '') . (isset($f['max']) ? ' max="' . $f['max'] . '"' : '') . '>',
                'secret' => '<input type="password" id="' . $id . '" name="f[' . $k . ']" value="" autocomplete="off" placeholder="' . ((string)$cur !== '' ? '•••••••• (ayarlı, değiştirmek için yaz)' : 'Henüz girilmedi') . '">'
                    . ((string)$cur !== '' ? '<label class="check sm"><input type="checkbox" name="clear[' . $k . ']" value="1"><span>Temizle</span></label>' : ''),
                'key' => '<input type="password" id="' . $id . '" name="f[' . $k . ']" value="" autocomplete="off" placeholder="' . ((string)$cur !== '' ? '•••••••• (ayarlı, değiştirmek için yaz)' : 'Henüz girilmedi') . '">'
                    . '<label class="check sm"><input type="checkbox" name="gen[' . $k . ']" value="1"><span>Yeni rastgele anahtar üret</span></label>',
                default => '<input type="' . (['url' => 'url', 'email' => 'email'][$f['type']] ?? 'text') . '" id="' . $id . '" name="f[' . $k . ']" value="' . Ui::e((string)$cur) . '"' . ($f['type'] === 'url' ? ' placeholder="https://"' : ($f['type'] === 'email' ? ' placeholder="ornek@mail.com"' : '')) . '>',
            };
            $rows .= '<div class="setting"><div class="setting-l"><label for="' . $id . '">' . Ui::e($f['label']) . '</label>'
                . ($help !== '' ? '<p class="muted">' . Ui::e($help) . '</p>' : '') . '<span class="src">' . $from . '</span></div><div class="setting-r">' . $input . '</div></div>';
        }
        if ($rows === '') {
            return '';
        }
        $sens = array_filter(array_keys(array_filter(Settings::fields(), static fn($f) => $f['g'] === $tab)), static fn($k) => in_array($k, self::sensitiveKeys(), true));
        $confirm = $sens ? '<div class="panel panel-pad field-row"><label class="grow">Panel şifren <span class="muted">(token, adres, anahtar, e-posta gibi hassas bir ayarı değiştiriyorsan gerekli)</span><input type="password" name="confirm_pw" autocomplete="current-password" placeholder="••••••••"></label></div>' : '';
        return '<form method="post" action="?p=settings_save" class="stack">' . $this->ui->csrfField() . '<input type="hidden" name="tab" value="' . $tab . '">'
            . '<div class="panel settings">' . $rows . '</div>' . $confirm
            . '<div class="row"><button class="btn primary">' . Ui::icon('check') . '<span>Kaydet</span></button></div></form>';
    }

    private function selectInput(string $id, string $name, array $opts, string $cur): string
    {
        $o = '';
        foreach ($opts as $v => $label) {
            $o .= '<option value="' . Ui::e((string)$v) . '"' . ((string)$v === $cur ? ' selected' : '') . '>' . Ui::e((string)$label) . '</option>';
        }
        return '<select id="' . $id . '" name="' . $name . '">' . $o . '</select>';
    }

    private function telegramTools(): string
    {
        return '<h2>Bağlantıyı dene</h2><div class="row">'
            . $this->ui->action('tg_test', 'Botu sına', [], 'quiet', 'check')
            . $this->ui->action('alerts_test', 'Test mesajı gönder', [], 'quiet', 'send')
            . $this->ui->action('tg_commands', 'Bot komut menüsünü güncelle', [], 'quiet', 'send')
            . $this->ui->action('mail_test', 'Test e-postası gönder', [], 'quiet', 'send')
            . Ui::link('?p=health', 'Webhook’u kur (Sağlık)', 'quiet', 'link') . '</div>'
            . '<p class="muted">Token, webhook adresi ya da anahtarı değiştirdiysen Sağlık sayfasından “Webhook’u kur”a bir kez bas.</p>';
    }

    private function publishTools(): string
    {
        return '<h2>Claude ve sağlayıcılar</h2><div class="row">' . Ui::link('?p=ai', 'Çeviri ayarları (Claude)', 'quiet', 'sliders')
            . Ui::link('?p=bank', 'Sağlayıcılar ve fiyat formülü', 'quiet', 'bank') . '</div>'
            . '<p class="muted">Claude anahtarı, model ve aylık limit Çeviri ayarları sayfasında; sağlayıcı API anahtarları Çeviri bankası sayfasındadır.</p>';
    }

    private function readingTools(): string
    {
        $key = (string)$this->config['cron_key'];
        $url = $key !== '' ? $this->siblingUrl('webcron.php') . '?key=' . ($this->loginOff() ? '••••••••' : $key) : '';
        $mk = (string)$this->config['monitor_key'];
        $mon = $mk !== '' ? $this->siblingUrl('monitor.php') . '?key=' . ($this->loginOff() ? '••••••••' : $mk) : '';
        $monCard = '<h2>Dış izleme adresi</h2><div class="panel panel-pad stack">' . ($mon !== ''
            ? '<div class="row"><code id="mon-url">' . Ui::e($mon) . '</code>' . Ui::copy('mon-url') . '</div><p class="muted">UptimeRobot gibi ücretsiz bir servise “HTTP(s) monitor” olarak ekle (5 dk aralık). Cron durursa ya da bir kanal 3 saat okunamazsa adres 503 döner ve o servis sana e-posta atar. Telegram bozulsa da haber alırsın.</p>'
            : '<p class="muted">Önce yukarıdan “İzleme anahtarı” için “Yeni rastgele anahtar üret”i işaretleyip kaydet.</p>') . '</div>';
        return $monCard . '<h2>cron-job.org adresi</h2><div class="panel panel-pad stack">' . ($url !== ''
            ? '<div class="row"><code id="cron-url2">' . Ui::e($url) . '</code>' . Ui::copy('cron-url2') . '</div><p class="muted">Her dakika çağrılsın. Anahtar içerir, paylaşma.</p>'
            : '<p class="lvl-error">Önce bir cron anahtarı üret.</p>')
            . '<div class="row">' . $this->ui->action('run_cron', 'Kanalları şimdi oku', ['back' => 'settings'], 'quiet', 'refresh') . '</div></div>';
    }

    private function securityTools(): string
    {
        if ($this->loginOff()) {
            return '<h2>Giriş kapalı</h2><div class="panel panel-pad stack"><p>config.php içinde <code>\'login_required\' => false</code> olduğu için panel şifresiz açılıyor. Şifre, oturum ve iki adımlı giriş ayarları kullanılmıyor.</p><p class="muted">Tekrar açmak için config.php’de <code>\'login_required\' => true</code> yazın (ya da satırı silin). Erişimi sınırlamak için <code>\'allowed_ips\' => \'1.2.3.4, 10.0.0.0/24\'</code> ekleyebilirsiniz.</p></div>';
        }
        $h = '<h2>Şifreyi değiştir</h2><form method="post" action="?p=password_change" class="panel panel-pad stack">' . $this->ui->csrfField()
            . '<div class="grid-2"><label>Mevcut şifre<input type="password" name="current" required autocomplete="current-password"></label><span></span>'
            . '<label>Yeni şifre (en az 10 karakter)<input type="password" name="new" required minlength="10" autocomplete="new-password"></label>'
            . '<label>Yeni şifre (tekrar)<input type="password" name="new2" required minlength="10" autocomplete="new-password"></label></div>'
            . '<div class="row"><button class="btn primary">' . Ui::icon('check') . '<span>Şifreyi değiştir</span></button></div>'
            . '<p class="muted">Şifre değişince açık tüm oturumlar kapanır. Kilitlenirsen config.php içine <code>\'panel_settings\' => false</code> ekle: config.php’deki şifre geçerli olur.</p></form>';

        $h .= '<h2>Oturumlar ve girişler</h2><div class="panel panel-pad stack"><div class="row">'
            . $this->ui->action('sessions_revoke', 'Tüm oturumları kapat', [], 'danger', 'logout', 'Bu cihaz dahil tüm oturumlar kapatılsın mı? Yeniden giriş yapman gerekir.')
            . Ui::link('?p=alerts', 'İki adımlı giriş ve uyarılar', 'quiet', 'bell') . '</div>';
        $logins = $this->db->all("SELECT ts, message FROM logs WHERE message LIKE 'Panele giriş%' ORDER BY id DESC LIMIT 8");
        if ($logins) {
            $h .= '<div class="table-wrap"><table><thead><tr><th>Zaman</th><th>IP</th></tr></thead><tbody>';
            foreach ($logins as $l) {
                $h .= '<tr><td class="tight">' . Ui::e(date('d.m.Y H:i', (int)$l['ts'])) . ' <span class="muted">(' . Ui::ago((int)$l['ts']) . ')</span></td><td>' . Ui::e(trim(substr($l['message'], strlen('Panele giriş:')))) . '</td></tr>';
            }
            $h .= '</tbody></table></div>';
        }
        $locked = $this->db->all('SELECT ip, fails, locked_until FROM login_attempts WHERE fails > 0 ORDER BY last_at DESC LIMIT 5');
        if ($locked) {
            $h .= '<b>Hatalı denemeler</b><div class="table-wrap"><table><thead><tr><th>IP</th><th>Hata</th><th>Kilit</th></tr></thead><tbody>';
            foreach ($locked as $l) {
                $h .= '<tr><td>' . Ui::e($l['ip']) . '</td><td>' . (int)$l['fails'] . '</td><td>' . ((int)$l['locked_until'] > time() ? 'kilitli, ' . ceil(((int)$l['locked_until'] - time()) / 60) . ' dk' : '—') . '</td></tr>';
            }
            $h .= '</tbody></table></div>' . '<div class="row">' . $this->ui->action('attempts_clear', 'Kilitleri kaldır', [], 'quiet', 'check') . '</div>';
        }
        return $h . '</div>';
    }

    private function dataTools(): string
    {
        $dir = (string)$this->config['data_dir'];
        $dbBytes = 0;
        foreach (glob($dir . '/tgwatch_*.sqlite*') ?: [] as $f) {
            $dbBytes += (int)@filesize($f);
        }
        $counts = [];
        foreach (['jobs' => 'İş', 'logs' => 'Kayıt', 'tm' => 'Çeviri bankası satırı', 'notices' => 'Bildirim'] as $t => $label) {
            $counts[] = $label . ': <b>' . (int)$this->db->one("SELECT COUNT(*) c FROM $t")['c'] . '</b>';
        }
        $cleaned = (int)$this->db->get('cleaned_at', '0');
        $bk = $this->db->getJson('backup_last_result');
        $h = '<h2>Otomatik yedek</h2><div class="panel panel-pad stack"><div class="row">' . Ui::dot(is_array($bk) ? ($bk['ok'] ? 'ok' : 'bad') : 'warn')
            . '<span>' . (is_array($bk) ? Ui::e((string)$bk['message']) . ' · ' . Ui::ago((int)$bk['at']) : 'Henüz yedek gönderilmedi') . '</span></div>'
            . '<div class="row">' . $this->ui->action('backup_send', 'Şimdi yedekle ve gönder', [], 'quiet', 'send') . '</div>'
            . '<p class="muted">Ayarlar yukarıdaki "Otomatik yedek" satırlarından yapılır. Dosya: Ayarlar → Veri ve bakım → "Yedekten geri yükle" ile geri açılır.</p></div>'
            . '<h2>Temizlik</h2><div class="panel panel-pad stack"><p>' . implode(' · ', $counts) . ' · Veritabanı: <b>' . round($dbBytes / 1048576, 2) . ' MB</b></p>'
            . '<p class="muted">Son temizlik: ' . ($cleaned ? Ui::ago($cleaned) : 'hiç') . '. Yukarıdaki sürelerden eski kayıtlar günde bir kez cron ile silinir.</p>'
            . '<div class="row">' . $this->ui->action('clean_now', 'Şimdi temizle', [], 'quiet', 'trash', 'Süresi geçen kayıtlar ve (süre verildiyse) eski işler silinsin mi?')
            . $this->ui->action('vacuum', 'Veritabanını sıkıştır', [], 'quiet', 'refresh') . '</div></div>';
        $h .= '<h2>Dışa aktar</h2><div class="panel panel-pad row">' . Ui::link('?p=export', 'Tüm servisleri CSV indir', 'quiet', 'download')
            . '<span class="muted">İş, tarih, kanal, durum, servis ID, kaynak ad, Azerice ad, fiyat</span></div>';
        $h .= $this->backupCard();
        $h .= '<h2>Sistem</h2><div class="table-wrap"><table><tbody>'
            . '<tr><td class="tight">tgwatch</td><td>' . Ui::e(\TgWatch\VERSION) . '</td></tr>'
            . '<tr><td class="tight">PHP</td><td>' . Ui::e(PHP_VERSION) . ' · ' . Ui::e(PHP_SAPI) . '</td></tr>'
            . '<tr><td class="tight">Klasör</td><td><code>' . Ui::e(TGWATCH_ROOT) . '</code></td></tr>'
            . '<tr><td class="tight">Boş disk</td><td>' . (($free = @disk_free_space($dir)) !== false ? round($free / 1048576) . ' MB' : '—') . '</td></tr>'
            . '</tbody></table></div>';
        return $h;
    }

    private function exportCsv(): void
    {
        header('Content-Type: text/csv; charset=utf-8');
        header('Content-Disposition: attachment; filename="tgwatch-servisler-' . date('Y-m-d') . '.csv"');
        $out = fopen('php://output', 'w');
        fwrite($out, "\xEF\xBB\xBF"); // Excel'de Azerice harfler doğru açılsın
        fputcsv($out, ['İş', 'Tarih', 'Kanal', 'Durum', 'Servis ID', 'Kaynak ad', 'Azerice ad', 'Fiyat'], ';', '"', '');
        foreach ($this->db->all('SELECT * FROM jobs ORDER BY id') as $j) {
            foreach ((array)json_decode((string)$j['items_json'], true) as $it) {
                fputcsv($out, array_map([Ui::class, 'csvCell'], [$j['id'], date('Y-m-d H:i', (int)$j['created_at']), '@' . $j['channel'], $j['status'], $it['id'] ?? '', $it['name'] ?? '', $it['az'] ?? '', $it['price'] ?? '']), ';', '"', '');
            }
        }
        fclose($out);
    }

    /** @return bool işlendi mi */
    private function postSettings(string $p): bool
    {
        switch ($p) {
            case 'settings_save':
                $tab = (string)($_POST['tab'] ?? 'general');
                $vals = [];
                foreach ((array)($_POST['f'] ?? []) as $k => $v) {
                    $f = Settings::fields()[(string)$k] ?? null;
                    if (!$f || $f['g'] !== $tab || !is_string($v)) {
                        continue;
                    }
                    if (in_array($f['type'], ['secret', 'key'], true) && trim($v) === '') {
                        continue; // boş: mevcut değer korunur
                    }
                    if (!in_array($f['type'], ['secret', 'key'], true) && trim($v) === trim((string)($this->config[(string)$k] ?? ''))) {
                        continue; // değişmemiş: kaydetme, "panelden" olarak işaretleme
                    }
                    $vals[(string)$k] = $v;
                }
                foreach ((array)($_POST['gen'] ?? []) as $k => $_) {
                    $f = Settings::fields()[(string)$k] ?? null;
                    if ($f && $f['g'] === $tab && $f['type'] === 'key') {
                        $vals[(string)$k] = Admin::randomKey();
                    }
                }
                foreach ((array)($_POST['clear'] ?? []) as $k => $_) {
                    $f = Settings::fields()[(string)$k] ?? null;
                    if ($f && $f['g'] === $tab && $f['type'] === 'secret') {
                        $vals[(string)$k] = '';
                    }
                }
                if (array_intersect(array_keys($vals), self::sensitiveKeys())) {
                    $row = $this->db->one('SELECT * FROM login_attempts WHERE ip = ?', [$this->ip()]);
                    if ($row && (int)$row['locked_until'] > time()) {
                        $this->say('bad', 'Çok fazla hatalı deneme. Biraz sonra tekrar deneyin.');
                        $this->redirect('settings', ['tab' => $tab]);
                        return true;
                    }
                    if ($this->loginOff()) {
                        $this->say('bad', 'Panel girişi kapalıyken token, adres, anahtar ve e-posta gibi hassas ayarlar panelden değiştirilemez (adresi bilen biri botu yönlendirebilirdi). config.php’den değiştirin ya da girişi açın.');
                        $this->redirect('settings', ['tab' => $tab]);
                        return true;
                    }
                    if (!password_verify((string)($_POST['confirm_pw'] ?? ''), (string)$this->config['admin_password_hash'])) {
                        $this->registerLoginFail($row, 'ayar onayı');
                        $this->say('bad', 'Hassas bir ayar değişiyor: değişiklik kaydedilmedi. Sayfanın altındaki kutuya panel şifreni yazıp tekrar kaydet.');
                        $this->redirect('settings', ['tab' => $tab]);
                        return true;
                    }
                }
                $errors = Settings::save($this->db, $vals);
                foreach ($errors as $e) {
                    $this->say('bad', $e);
                }
                if ($vals && count($errors) < count($vals)) {
                    $this->db->log('info', 'web', 'Ayarlar kaydedildi: ' . implode(', ', array_diff(array_keys($vals), ['bot_token', 'webhook_secret', 'cron_key', 'panel_api_key'])));
                    $this->say('ok', 'Ayarlar kaydedildi.' . (isset($vals['bot_token']) || isset($vals['webhook_secret']) || isset($vals['webhook_url']) ? ' Webhook’u Sağlık sayfasından yeniden kur.' : '')
                        . (isset($vals['cron_key']) ? ' cron-job.org adresini yeni anahtarla güncelle.' : ''));
                } elseif (!$vals) {
                    $this->say('ok', 'Değişiklik yok.');
                }
                ($_POST['back'] ?? '') === 'channels' ? $this->redirect('channels') : $this->redirect('settings', ['tab' => $tab]);
                return true;
            case 'channel_filter':
                $words = implode("\n", array_filter(array_map('trim', preg_split('/[,\n]+/u', mb_substr((string)($_POST['skip_words'] ?? ''), 0, 400)))));
                $this->db->run('UPDATE channels SET skip_words = ?, auto_approve = ?, note = ? WHERE id = ?', [$words, empty($_POST['auto_approve']) ? 0 : 1, mb_substr(trim((string)($_POST['note'] ?? '')), 0, 120), (int)($_POST['id'] ?? 0)]);
                $this->say('ok', 'Kanal filtresi kaydedildi.');
                $this->redirect('channels');
                return true;
            case 'mail_test':
                $ok = (new \TgWatch\Alerts($this->db))->mail('tgwatch test e-postası: uyarılar bu adrese de gelecek.', 'tgwatch test');
                $this->say($ok ? 'ok' : 'bad', $ok ? 'Test e-postası gönderildi (kutuyu ve spam klasörünü kontrol edin).' : 'E-posta gönderilemedi: adres boş/geçersiz ya da hosting mail() kapalı.');
                $this->redirect('settings', ['tab' => 'telegram']);
                return true;
            case 'backup_send':
                $r = (new \TgWatch\AutoBackup($this->db, $this->config))->run();
                $this->say($r['ok'] ? 'ok' : 'bad', $r['message']);
                $this->redirect('settings', ['tab' => 'data']);
                return true;
            case 'tg_commands':
                try {
                    App::telegram()->setCommands(\TgWatch\BotRegistry::menu($this->db));
                    $this->say('ok', 'Bot komut menüsü güncellendi (Telegram’da / yazınca komutlar çıkar).');
                } catch (\Throwable $e) {
                    $this->say('bad', 'Güncellenemedi: ' . preg_replace('~bot\d+:[A-Za-z0-9_-]+~', 'bot***', $e->getMessage()));
                }
                $this->redirect('settings', ['tab' => 'telegram']);
                return true;
            case 'tg_test':
                try {
                    $tg = App::telegram();
                    $me = $tg->call('getMe', [], 15);
                    $this->say('ok', 'Bot çalışıyor: @' . ($me['username'] ?? '?'));
                } catch (\Throwable $e) {
                    $this->say('bad', 'Bot yanıt vermedi: ' . preg_replace('~bot\d+:[A-Za-z0-9_-]+~', 'bot***', $e->getMessage()));
                }
                $this->redirect('settings', ['tab' => 'telegram']);
                return true;
            case 'password_change':
                $hash = (string)$this->config['admin_password_hash'];
                $new = (string)($_POST['new'] ?? '');
                if (!password_verify((string)($_POST['current'] ?? ''), $hash)) {
                    $this->say('bad', 'Mevcut şifre yanlış.');
                } elseif ($new !== (string)($_POST['new2'] ?? '')) {
                    $this->say('bad', 'Yeni şifreler aynı değil.');
                } elseif (mb_strlen($new) < 10) {
                    $this->say('bad', 'Yeni şifre en az 10 karakter olmalı.');
                } else {
                    $this->db->set('admin_hash', password_hash($new, defined('PASSWORD_ARGON2ID') ? PASSWORD_ARGON2ID : PASSWORD_BCRYPT));
                    $this->db->set('session_epoch', bin2hex(random_bytes(8)));
                    $this->db->log('info', 'web', 'Panel şifresi değiştirildi: ' . $this->ip());
                    try {
                        (new \TgWatch\Alerts($this->db))->notify('login', "🔑 <b>Panel şifresi değiştirildi</b>\nIP: " . Telegram::esc($this->ip()));
                    } catch (\Throwable) {
                    }
                    $_SESSION = [];
                    session_regenerate_id(true);
                    $_SESSION['csrf'] = bin2hex(random_bytes(32));
                    $this->say('ok', 'Şifre değişti. Yeni şifrenle tekrar giriş yap.');
                    $this->redirect('dash');
                    return true;
                }
                $this->redirect('settings', ['tab' => 'security']);
                return true;
            case 'sessions_revoke':
                $this->db->set('session_epoch', bin2hex(random_bytes(8)));
                $this->db->log('info', 'web', 'Tüm oturumlar kapatıldı: ' . $this->ip());
                $_SESSION = [];
                session_regenerate_id(true);
                $_SESSION['csrf'] = bin2hex(random_bytes(32));
                $this->redirect('dash');
                return true;
            case 'attempts_clear':
                $this->db->run('DELETE FROM login_attempts');
                $this->say('ok', 'Giriş kilitleri kaldırıldı.');
                $this->redirect('settings', ['tab' => 'security']);
                return true;
            case 'clean_now':
                $r = (new Cleaner($this->db, $this->config))->run();
                $this->say('ok', "Temizlendi: {$r['logs']} kayıt, {$r['jobs']} iş, {$r['notices']} bildirim.");
                $this->redirect('settings', ['tab' => 'data']);
                return true;
            case 'vacuum':
                try {
                    (new Cleaner($this->db, $this->config))->vacuum();
                    $this->say('ok', 'Veritabanı sıkıştırıldı.');
                } catch (\Throwable $e) {
                    $this->say('bad', 'Sıkıştırılamadı: ' . $e->getMessage());
                }
                $this->redirect('settings', ['tab' => 'data']);
                return true;
        }
        return false;
    }
}
