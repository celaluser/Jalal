<?php
declare(strict_types=1);

namespace TgWatch;

/**
 * Otomatik yedek: Backup JSON'u günde/haftada bir Telegram'a (sahibe) ve/veya e-postaya dosya olarak gönderir.
 * Hosting çökse bile çeviri bankası, sözlük ve kanal listesi sende kalır. API anahtarları ve şifre yedeğe girmez.
 */
final class AutoBackup
{
    /** @var (callable(string,string,string,string,string):bool)|null testler için: (to, subject, body, filename, content) */
    public static $mailer = null;

    public function __construct(private Db $db, private array $config, private ?Telegram $tg = null)
    {
        $this->tg ??= App::telegram();
    }

    public function mode(): string
    {
        $m = (string)($this->config['backup_auto'] ?? 'daily');
        return in_array($m, ['off', 'daily', 'weekly'], true) ? $m : 'daily';
    }

    public function due(?int $now = null): bool
    {
        $now ??= time();
        if ($this->mode() === 'off') {
            return false;
        }
        if ((int)date('G', $now) < max(0, min(23, (int)($this->config['backup_hour'] ?? 4)))) {
            return false;
        }
        if ($now - (int)$this->db->get('backup_try_at', '0') < 1800) {
            return false; // hata sonrası 30 dk bekle
        }
        $every = $this->mode() === 'weekly' ? 6.5 * 86400 : 20 * 3600;
        return $now - (int)$this->db->get('backup_last_at', '0') >= $every;
    }

    /** Cron her turda çağırır; zamanı gelmişse yedekler. */
    public function maybeRun(): void
    {
        if ($this->due()) {
            $this->run();
        }
    }

    /** @return array{ok:bool,message:string} */
    public function run(): array
    {
        $this->db->set('backup_try_at', (string)time());
        $json = json_encode((new Backup($this->db))->export(), JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_PRETTY_PRINT);
        $name = 'tgwatch-yedek-' . date('Y-m-d') . '.json';
        $to = (string)($this->config['backup_to'] ?? 'telegram');
        $via = [];
        $err = [];
        if (in_array($to, ['telegram', 'both'], true)) {
            try {
                $owner = (int)($this->config['owner_id'] ?? 0);
                if ($owner <= 0) {
                    throw new \RuntimeException('owner_id yok');
                }
                $this->tg->sendDocument($owner, $name, $json, '💾 tgwatch yedeği · ' . date('d.m.Y H:i') . ' · ' . round(strlen($json) / 1024) . ' KB');
                $via[] = 'Telegram';
            } catch (\Throwable $e) {
                $err[] = 'Telegram: ' . $e->getMessage();
            }
        }
        if (in_array($to, ['email', 'both'], true)) {
            $addr = trim((string)($this->config['alert_email'] ?? ''));
            if (!filter_var($addr, FILTER_VALIDATE_EMAIL)) {
                $err[] = 'E-posta: adres yok (Ayarlar → Telegram → Uyarı e-postası)';
            } elseif ($this->mail($addr, $name, $json)) {
                $via[] = 'e-posta';
            } else {
                $err[] = 'E-posta gönderilemedi';
            }
        }
        $ok = $via !== [];
        $msg = $ok ? 'Yedek gönderildi (' . implode(' + ', $via) . ', ' . round(strlen($json) / 1024) . ' KB)' . ($err ? '; ' . implode('; ', $err) : '') : 'Yedek gönderilemedi: ' . implode('; ', $err ?: ['hedef yok']);
        if ($ok) {
            $this->db->set('backup_last_at', (string)time());
        }
        $this->db->setJson('backup_last_result', ['at' => time(), 'ok' => $ok, 'message' => $msg]);
        $this->db->log($ok ? 'info' : 'error', 'backup', $msg);
        if (!$ok) {
            try {
                (new Alerts($this->db, $this->tg, $this->config))->send('backup', 'backup', "💾 <b>Otomatik yedek gönderilemedi</b>\n" . Telegram::esc($msg), 86400);
            } catch (\Throwable) {
            }
        } else {
            try {
                (new Alerts($this->db, $this->tg, $this->config))->resolve('backup', 'backup', '✅ Otomatik yedek yeniden çalışıyor.');
            } catch (\Throwable) {
            }
        }
        return ['ok' => $ok, 'message' => $msg];
    }

    private function mail(string $to, string $name, string $json): bool
    {
        $subject = 'tgwatch yedeği ' . date('Y-m-d');
        $body = "tgwatch yedeği ektedir.\nGeri yüklemek için: Panel → Ayarlar → Veri ve bakım → Yedekten geri yükle.";
        if (self::$mailer) {
            return (bool)(self::$mailer)($to, $subject, $body, $name, $json);
        }
        $host = preg_replace('/[^A-Za-z0-9.\-]/', '', (string)($_SERVER['HTTP_HOST'] ?? 'localhost')) ?: 'localhost';
        $b = 'tgw' . bin2hex(random_bytes(8));
        $headers = "From: tgwatch <tgwatch@$host>\r\nMIME-Version: 1.0\r\nContent-Type: multipart/mixed; boundary=\"$b\"";
        $msg = "--$b\r\nContent-Type: text/plain; charset=UTF-8\r\nContent-Transfer-Encoding: base64\r\n\r\n" . chunk_split(base64_encode($body))
            . "--$b\r\nContent-Type: application/json; name=\"$name\"\r\nContent-Transfer-Encoding: base64\r\nContent-Disposition: attachment; filename=\"$name\"\r\n\r\n" . chunk_split(base64_encode($json))
            . "--$b--";
        return (bool)@mail($to, '=?UTF-8?B?' . base64_encode($subject) . '?=', $msg, $headers);
    }
}
