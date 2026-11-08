<?php
declare(strict_types=1);

namespace TgWatch\Web;

use TgWatch\Alerts;
use TgWatch\App;
use TgWatch\Backup;
use TgWatch\Telegram;

/** İki adımlı giriş (Telegram kodu), uyarı ayarları ve yedek. Admin içinde kullanılır. */
trait SecurityPages
{
    private const TF_TTL = 300;
    private const TF_RESEND = 30;

    // ---------------------------------------------------------------- iki adımlı giriş

    private function twoFactorOn(): bool
    {
        if (($this->config['login_2fa'] ?? null) === false) {
            return false; // config.php'den zorla kapatma: kilitlenirsen kurtarma yolu
        }
        $tg = new Telegram((string)$this->config['bot_token']);
        return $this->db->get('login_2fa', '0') === '1' && (int)$this->config['owner_id'] > 0 && $tg->configured();
    }

    private function ua(): string
    {
        return mb_strimwidth(preg_replace('/\s+/', ' ', (string)($_SERVER['HTTP_USER_AGENT'] ?? '')) ?? '', 0, 90, '…');
    }

    /** Kodu üretir, oturumda hash'ini tutar, bota gönderir. @return string|null hata mesajı */
    private function sendCode(string $sessionKey, string $purpose): ?string
    {
        $prev = $_SESSION[$sessionKey] ?? null;
        if (is_array($prev) && time() - (int)$prev['sent'] < self::TF_RESEND) {
            return 'Yeni kod için ' . (self::TF_RESEND - (time() - (int)$prev['sent'])) . ' saniye bekleyin.';
        }
        $code = (string)random_int(100000, 999999);
        try {
            App::telegram()->sendMessage((int)$this->config['owner_id'],
                "🔐 <b>$purpose</b>\nKod: <code>$code</code>\n" . self::TF_TTL / 60 . " dakika geçerli.\nIP: " . Telegram::esc($this->ip()) . "\n" . Telegram::esc($this->ua()) . "\n\nBu işlemi sen yapmadıysan kodu kimseyle paylaşma ve panel şifreni değiştir.");
        } catch (\Throwable $e) {
            $this->db->log('error', 'web', 'Giriş kodu gönderilemedi: ' . $e->getMessage());
            return 'Kod Telegram’a gönderilemedi. Bot ayarlarını kontrol edin (Sağlık). Kilitlenirseniz config.php içine \'login_2fa\' => false ekleyin.';
        }
        $_SESSION[$sessionKey] = ['h' => hash_hmac('sha256', $code, (string)$_SESSION['csrf']), 'exp' => time() + self::TF_TTL, 'tries' => 0, 'sent' => time()];
        return null;
    }

    private function checkCode(string $sessionKey, string $given): string
    {
        $tf = $_SESSION[$sessionKey] ?? null;
        if (!is_array($tf) || time() > (int)$tf['exp']) {
            unset($_SESSION[$sessionKey]);
            return 'expired';
        }
        if ((int)$tf['tries'] >= 5) {
            unset($_SESSION[$sessionKey]);
            return 'locked';
        }
        $_SESSION[$sessionKey]['tries']++;
        $ok = hash_equals((string)$tf['h'], hash_hmac('sha256', preg_replace('/\D/', '', $given) ?? '', (string)$_SESSION['csrf']));
        if ($ok) {
            unset($_SESSION[$sessionKey]);
        }
        return $ok ? 'ok' : 'wrong';
    }

    private function codeForm(string $action, string $msg, string $extra = ''): string
    {
        return '<form method="post" action="?p=' . $action . '" class="stack">' . ($msg !== '' ? '<div class="flash bad">' . Ui::icon('alert') . '<span>' . Ui::e($msg) . '</span></div>' : '') . $this->ui->csrfField()
            . '<label>6 haneli kod<input type="text" name="code" inputmode="numeric" autocomplete="one-time-code" pattern="[0-9 ]{6,7}" maxlength="7" autofocus required></label>'
            . '<button class="btn primary" name="do" value="verify">Doğrula</button>'
            . '<button class="btn ghost" name="do" value="resend" formnovalidate>Yeni kod gönder</button>' . $extra
            . ($action === 'login2' ? '<a class="btn ghost" href="?p=login&amp;reset=1">Şifre ekranına dön</a>' : '') . '</form>';
    }

    /** Şifre doğru olduktan sonra: kodu gönder ve kod formunu göster. */
    private function beginTwoFactor(): void
    {
        unset($_SESSION['tf']);
        $_SESSION['pw_ok'] = time();
        $err = $this->sendCode('tf', 'Panel giriş kodu');
        $this->bare('Doğrulama kodu', 'Telegram botuna bir kod gönderdik.', $this->codeForm('login2', $err ?? ''));
    }

    /** Kod adımı (POST ?p=login2) ya da bekleyen doğrulamanın yeniden gösterimi. */
    private function loginStep2(): bool
    {
        if (isset($_GET['reset'])) {
            unset($_SESSION['pw_ok'], $_SESSION['tf']);
            return false;
        }
        $pending = isset($_SESSION['pw_ok']) && time() - (int)$_SESSION['pw_ok'] < self::TF_TTL;
        if (!$pending || !$this->twoFactorOn()) {
            unset($_SESSION['pw_ok'], $_SESSION['tf']);
            return false;
        }
        if (($_GET['p'] ?? '') !== 'login2' || $_SERVER['REQUEST_METHOD'] !== 'POST') {
            $this->bare('Doğrulama kodu', 'Telegram botuna gönderilen kodu girin.', $this->codeForm('login2', ''));
            return true;
        }
        if (!hash_equals((string)$_SESSION['csrf'], (string)($_POST['csrf'] ?? ''))) {
            $this->bare('Doğrulama kodu', 'Sayfanın süresi doldu.', $this->codeForm('login2', 'Sayfanın süresi doldu. Tekrar deneyin.'));
            return true;
        }
        if (($_POST['do'] ?? '') === 'resend') {
            $err = $this->sendCode('tf', 'Panel giriş kodu');
            $this->bare('Doğrulama kodu', 'Telegram botuna bir kod gönderdik.', $this->codeForm('login2', $err ?? 'Yeni kod gönderildi.'));
            return true;
        }
        $row = $this->db->one('SELECT * FROM login_attempts WHERE ip = ?', [$this->ip()]);
        if ($row && (int)$row['locked_until'] > time()) {
            $this->bare('Doğrulama kodu', '', $this->codeForm('login2', 'Çok fazla hatalı deneme. Daha sonra tekrar deneyin.'));
            return true;
        }
        $r = $this->checkCode('tf', (string)($_POST['code'] ?? ''));
        if ($r === 'ok') {
            unset($_SESSION['pw_ok']);
            $this->completeLogin();
            return true;
        }
        $this->registerLoginFail($row, 'kod');
        if ($r === 'locked' || $r === 'expired') {
            unset($_SESSION['pw_ok']);
            $this->bare('tgwatch', 'Servis duyurularını çevirme ve onaylama paneli', '<div class="flash bad">' . Ui::icon('alert') . '<span>' . ($r === 'locked' ? 'Çok fazla yanlış kod. Şifreyle yeniden başlayın.' : 'Kodun süresi doldu. Şifreyle yeniden başlayın.') . '</span></div>'
                . '<a class="btn primary" href="?p=login">Girişe dön</a>');
            return true;
        }
        $this->bare('Doğrulama kodu', 'Telegram botuna gönderilen kodu girin.', $this->codeForm('login2', 'Kod yanlış.'));
        return true;
    }

    // ---------------------------------------------------------------- uyarılar ve güvenlik sayfası

    private function alertsPage(): void
    {
        $al = new Alerts($this->db);
        $d = $al->daily();
        $h = '<form method="post" action="?p=alerts_save" class="stack">' . $this->ui->csrfField()
            . '<h2 class="first">Bota gelecek uyarılar</h2><div class="panel panel-pad stack">';
        foreach (Alerts::TYPES as $k => [$name, $desc]) {
            $h .= '<label class="check"><input type="checkbox" name="types[]" value="' . $k . '"' . ($al->enabled($k) ? ' checked' : '') . '><span><b>' . Ui::e($name) . '</b><br><span class="muted">' . Ui::e($desc) . '</span></span></label>';
        }
        $hours = '';
        for ($i = 0; $i < 24; $i++) {
            $hours .= '<option value="' . $i . '"' . ($d['hour'] === $i ? ' selected' : '') . '>' . sprintf('%02d:00', $i) . '</option>';
        }
        $h .= '</div><h2>Günlük özet</h2><div class="panel panel-pad field-row"><label class="check"><input type="checkbox" name="daily" value="1"' . ($d['on'] ? ' checked' : '') . '><span><b>Her gün kısa özet gönder</b><br><span class="muted">Gelen ve onaylanan iş, bekleyenler, hata sayısı ve Claude kullanımı</span></span></label>'
            . '<label>Saat<select name="hour">' . $hours . '</select></label></div>'
            . '<div class="row"><button class="btn primary">' . Ui::icon('check') . '<span>Kaydet</span></button></div></form>'
            . '<div class="row gap-top">' . $this->ui->action('alerts_test', 'Test mesajı gönder', [], 'quiet', 'send') . '</div>'
            . '<p class="muted">Kanal okumanın durması, okumanın kendisine bağlı olduğu için yalnızca bota gelen bir istek ya da panel ziyareti sırasında fark edilir. cron-job.org’un kendi hata e-postasını da açık tutun.</p>';

        // iki adımlı giriş
        $on = $this->twoFactorOn();
        $forcedOff = ($this->config['login_2fa'] ?? null) === false;
        $h .= '<h2>Panel girişi</h2><div class="panel panel-pad stack"><div class="row">' . Ui::dot($on ? 'ok' : 'warn', $on ? 'açık' : 'kapalı') . '<b>İki adımlı giriş: ' . ($on ? 'açık' : 'kapalı') . '</b></div>'
            . '<p class="muted">Şifreden sonra Telegram botuna 6 haneli bir kod gelir; kod girilmeden panele girilemez. Şifre çalınsa bile bota erişimi olmayan biri giremez.</p>';
        if ($forcedOff) {
            $h .= '<div class="flash bad">' . Ui::icon('alert') . '<span>config.php içinde <code>login_2fa => false</code> var, bu yüzden zorla kapalı.</span></div>';
        } elseif ($on) {
            $h .= '<div class="row">' . $this->ui->action('tf_off', 'İki adımlı girişi kapat', [], 'danger', 'x', 'İki adımlı giriş kapatılsın mı?') . '</div>';
        } elseif (!empty($_SESSION['tf_en'])) {
            $h .= $this->codeForm('tf_confirm', (string)($_SESSION['tf_msg'] ?? ''), '') . '<p class="muted">Botunuza gelen kodu girince açılır. Kod gelmediyse owner_id ve bot ayarlarını kontrol edin.</p>';
            unset($_SESSION['tf_msg']);
        } else {
            $h .= '<div class="row">' . $this->ui->action('tf_start', 'Aç ve doğrula', [], 'primary', 'check') . '</div>'
                . '<p class="muted">Açmadan önce bota bir deneme kodu gönderilir; kodu girerek botun çalıştığını doğrularsınız, böylece panelin dışında kalmazsınız. Yine de kilitlenirseniz config.php içine <code>\'login_2fa\' => false</code> ekleyin.</p>';
        }
        $h .= '</div>';
        $this->render('Uyarılar ve güvenlik', $h, ['active' => 'alerts', 'sub' => 'Telegram botuna ne zaman mesaj gelsin, panele girişte ek doğrulama.']);
    }

    // ---------------------------------------------------------------- yedek

    private function backupDownload(): void
    {
        $json = json_encode((new Backup($this->db))->export(), JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_PRETTY_PRINT);
        $this->db->log('info', 'backup', 'Yedek indirildi');
        header('Content-Type: application/json; charset=utf-8');
        header('Content-Disposition: attachment; filename="tgwatch-yedek-' . date('Y-m-d') . '.json"');
        echo $json;
    }

    private function backupCard(): string
    {
        return '<h2>Yedek</h2><div class="panel panel-pad stack"><p class="muted">Çeviri bankası, sözlük, kurallar, ayarlar, kanal listesi ve kaynak adresleri. API anahtarları, şifre ve işler yedeğe girmez. Geri yüklemeden sonra sağlayıcı anahtarlarını Çeviri bankası sayfasından yeniden girin.</p>'
            . '<div class="row">' . Ui::link('?p=backup', 'Yedeği indir', 'primary', 'download') . '</div>'
            . '<form method="post" action="?p=backup_import" enctype="multipart/form-data" class="field-row">' . $this->ui->csrfField()
            . '<label class="grow">Yedekten geri yükle (mevcut veriler korunur, yedektekiler eklenir)<input type="file" name="file" accept="application/json,.json" required></label>'
            . '<button class="btn quiet">' . Ui::icon('refresh') . '<span>Geri yükle</span></button></form></div>';
    }

    // ---------------------------------------------------------------- POST

    /** @return bool işlendi mi */
    private function postSecurity(string $p): bool
    {
        switch ($p) {
            case 'alerts_save':
                $types = array_values(array_intersect(array_map('strval', (array)($_POST['types'] ?? [])), array_keys(Alerts::TYPES)));
                (new Alerts($this->db))->setEnabled($types, !empty($_POST['daily']), (int)($_POST['hour'] ?? 21));
                $this->say('ok', 'Uyarı ayarları kaydedildi.');
                $this->redirect('alerts');
                return true;
            case 'alerts_test':
                $ok = (new Alerts($this->db))->test();
                $this->say($ok ? 'ok' : 'bad', $ok ? 'Test mesajı bota gönderildi.' : 'Test mesajı gönderilemedi. owner_id ve bot ayarlarını Sağlık sayfasından kontrol edin.');
                $this->redirect('alerts');
                return true;
            case 'tf_start':
            case 'tf_confirm':
                if ($p === 'tf_start' || ($_POST['do'] ?? '') === 'resend') {
                    $err = $this->sendCode('tf_en', 'Panel girişi doğrulama kodu (açma testi)');
                    $_SESSION['tf_en'] = $_SESSION['tf_en'] ?? true;
                    $_SESSION['tf_msg'] = $err ?? ($p === 'tf_start' ? '' : 'Yeni kod gönderildi.');
                    if ($err !== null && $p === 'tf_start') {
                        unset($_SESSION['tf_en']);
                        $this->say('bad', $err);
                    }
                    $this->redirect('alerts');
                    return true;
                }
                $r = $this->checkCode('tf_en', (string)($_POST['code'] ?? ''));
                if ($r === 'ok') {
                    $this->db->set('login_2fa', '1');
                    unset($_SESSION['tf_en']);
                    $this->db->log('info', 'web', 'İki adımlı giriş açıldı');
                    $this->say('ok', 'İki adımlı giriş açıldı. Bir sonraki girişte kod istenecek.');
                } else {
                    $_SESSION['tf_msg'] = $r === 'wrong' ? 'Kod yanlış.' : 'Kodun süresi doldu ya da çok fazla deneme yapıldı. Yeniden başlayın.';
                    if ($r !== 'wrong') {
                        unset($_SESSION['tf_en']);
                        $this->say('bad', $_SESSION['tf_msg']);
                    }
                }
                $this->redirect('alerts');
                return true;
            case 'tf_off':
                $this->db->set('login_2fa', '0');
                $this->db->log('warn', 'web', 'İki adımlı giriş kapatıldı');
                $this->say('ok', 'İki adımlı giriş kapatıldı.');
                $this->redirect('alerts');
                return true;
            case 'backup_import':
                $f = $_FILES['file'] ?? null;
                if (!is_array($f) || ($f['error'] ?? 1) !== UPLOAD_ERR_OK || (int)$f['size'] > 8 * 1024 * 1024) {
                    throw new \InvalidArgumentException('Dosya yüklenemedi ya da 8 MB’tan büyük.');
                }
                $n = (new Backup($this->db))->import((string)file_get_contents($f['tmp_name']));
                $this->refreshOpenJobs();
                $this->say('ok', "Geri yüklendi: {$n['tm']} çeviri çifti, {$n['channels']} kanal, {$n['settings']} ayar, {$n['sources']} kaynak.");
                $this->redirect('health');
                return true;
        }
        return false;
    }
}
