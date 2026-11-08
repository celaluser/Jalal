<?php
declare(strict_types=1);

namespace TgWatch;

/**
 * Telegram uyarıları. Her uyarı bir anahtara bağlıdır; aynı sorun için bekleme süresi dolmadan tekrar gönderilmez,
 * sorun düzelince bir kez "düzeldi" mesajı gider.
 *
 * Kanal okuma durması (cron) sorununun kendisi cron'a bağlı olduğu için yalnızca bot webhook'una gelen istekler
 * ve panel ziyaretleri sırasında fark edilir. cron-job.org'un kendi hata e-postasını da açık tutun.
 */
final class Alerts
{
    public const TYPES = [
        'channel' => ['Kanal okunamıyor', 'Bir kanal 3 saatten uzun süredir okunamıyorsa'],
        'cron' => ['Kanal okuma durdu', 'Cron 15 dakikadan uzun süredir çalışmadıysa'],
        'webhook' => ['Telegram webhook hatası', 'Telegram bot adresine ulaşamıyorsa'],
        'claude' => ['Claude kullanım sınırı', 'Aylık istek sınırının %80 ve %100\'üne gelince'],
        'backup' => ['Otomatik yedek gönderilemedi', 'Günlük/haftalık yedek Telegram ya da e-postaya gidemediyse'],
        'gap' => ['Kaçırılmış gönderi', 'Cron uzun süre durup dönünce kanalın eski gönderileri okunamadıysa'],
        'unpublished' => ['Onaylandı ama panele eklenmedi', 'Onaylı servisler 24 saatten uzun süredir "yayın bekliyor" durumundaysa (günde bir kez)'],
        'stale' => ['Uzun süredir bekleyen iş', 'Bir iş 12 saatten uzun süredir onay bekliyorsa (günde bir kez)'],
        'change' => ['Servis değişikliği', 'Fiyat değişikliği ya da kapatılan servis duyurusu gelince'],
        'rival' => ['Rakip fiyatı', 'Bir rakip senden belirlediğin yüzde kadar ucuza düşünce ya da fiyatı değişince'],
        'weekly' => ['Haftalık özet', 'Her pazartesi 09:00\'dan sonra geçen haftanın özeti'],
        'login' => ['Panele giriş', 'Panele her başarılı girişte (kim, nereden)'],
    ];
    private const DEFAULT_OFF = ['login'];

    public function __construct(private Db $db, private ?Telegram $tg = null, private array $config = [])
    {
        $this->tg ??= App::telegram();
        if (!$this->config) {
            $this->config = App::config();
        }
    }

    public function enabled(string $type): bool
    {
        $m = $this->db->getJson('alerts_enabled', []);
        return array_key_exists($type, $m) ? (bool)$m[$type] : !in_array($type, self::DEFAULT_OFF, true);
    }

    public function setEnabled(array $types, bool $daily, int $hour): void
    {
        $m = [];
        foreach (array_keys(self::TYPES) as $t) {
            $m[$t] = in_array($t, $types, true);
        }
        $this->db->setJson('alerts_enabled', $m);
        $this->db->set('alerts_daily', $daily ? '1' : '0');
        $this->db->set('alerts_daily_hour', (string)max(0, min(23, $hour)));
    }

    public function daily(): array
    {
        return ['on' => $this->db->get('alerts_daily', '0') === '1', 'hour' => max(0, min(23, (int)$this->db->get('alerts_daily_hour', '21')))];
    }

    /**
     * Önce Telegram; alert_email tanımlıysa Telegram gitmediğinde (ya da alert_email_mode = all ise her zaman) e-posta da gönderilir.
     * Telegram çalışmazsa uyarının ulaşabileceği ikinci yol budur.
     */
    private function deliver(string $html): bool
    {
        $tgOk = false;
        $owner = (int)($this->config['owner_id'] ?? 0);
        if ($owner > 0) {
            try {
                $this->tg->sendMessage($owner, $html);
                $tgOk = true;
            } catch (\Throwable $e) {
                $this->db->log('error', 'alert', 'Uyarı gönderilemedi: ' . $e->getMessage());
            }
        }
        $mailOk = false;
        if ($this->emailTo() !== '' && (!$tgOk || ($this->config['alert_email_mode'] ?? 'fallback') === 'all')) {
            $mailOk = $this->mail($html);
        }
        return $tgOk || $mailOk;
    }

    public function emailTo(): string
    {
        $m = trim((string)($this->config['alert_email'] ?? ''));
        return filter_var($m, FILTER_VALIDATE_EMAIL) ? $m : '';
    }

    /** E-posta gönderimi (hosting mail()). Gönderim kabul edildi mi bilgisi döner; teslim garanti değildir. */
    public function mail(string $html, string $subject = 'tgwatch uyarısı'): bool
    {
        $to = $this->emailTo();
        if ($to === '') {
            return false;
        }
        $host = preg_replace('/[^A-Za-z0-9.\-]/', '', (string)($_SERVER['HTTP_HOST'] ?? 'localhost')) ?: 'localhost';
        $text = html_entity_decode(strip_tags(str_replace(['<br>', '<br/>'], "\n", $html)), ENT_QUOTES | ENT_HTML5, 'UTF-8');
        $headers = "From: tgwatch <tgwatch@$host>\r\nContent-Type: text/plain; charset=UTF-8\r\nMIME-Version: 1.0";
        try {
            $ok = $this->mailer ? ($this->mailer)($to, $subject, $text) : @mail($to, '=?UTF-8?B?' . base64_encode($subject) . '?=', $text, $headers);
        } catch (\Throwable) {
            $ok = false;
        }
        if (!$ok) {
            $this->db->log('error', 'alert', 'E-posta gönderilemedi: ' . $to);
        }
        return (bool)$ok;
    }

    /** @var (callable(string,string,string):bool)|null testler için */
    public $mailer = null;

    /** Anahtar için uyarı gönder; bekleme süresi dolmadıysa gönderme. */
    public function send(string $key, string $type, string $html, int $cooldown = 21600): bool
    {
        if (!$this->enabled($type)) {
            return false;
        }
        $st = $this->db->getJson('alerts_state', []);
        $now = time();
        if (!empty($st[$key]['active']) && $now - (int)$st[$key]['last'] < $cooldown) {
            return false;
        }
        if (!$this->deliver($html)) {
            return false;
        }
        $st[$key] = ['active' => true, 'last' => $now];
        $this->db->setJson('alerts_state', $st);
        $this->db->log('info', 'alert', 'Uyarı gönderildi: ' . $key);
        return true;
    }

    /** Sorun düzeldiyse bir kez haber ver. */
    public function resolve(string $key, string $type, string $html): void
    {
        $st = $this->db->getJson('alerts_state', []);
        if (empty($st[$key]['active'])) {
            return;
        }
        $st[$key]['active'] = false;
        $this->db->setJson('alerts_state', $st);
        if ($this->enabled($type)) {
            $this->deliver($html);
        }
    }

    /** Anlık bildirim (değişiklik, giriş): bekleme süresi yok. */
    public function notify(string $type, string $html): bool
    {
        return $this->enabled($type) && $this->deliver($html);
    }

    public function test(): bool
    {
        return $this->deliver("🔔 <b>tgwatch uyarı testi</b>\nUyarılar bu sohbete gelecek.");
    }

    /** En fazla 5 dakikada bir değerlendirir. cron, bot webhook'u ve panel ziyareti çağırır. */
    public function check(bool $fromCron = false): void
    {
        $now = time();
        if ($now - (int)$this->db->get('alerts_checked_at', '0') < 300) {
            return;
        }
        $this->db->set('alerts_checked_at', (string)$now);
        try {
            $this->checkChannels($now);
            $this->checkCron($now, $fromCron);
            $this->checkClaude($now);
            $this->checkStale($now);
            $this->checkUnpublished($now);
            $this->checkDaily($now);
            $this->checkWeekly($now);
        } catch (\Throwable $e) {
            $this->db->log('error', 'alert', 'Uyarı kontrolü: ' . $e->getMessage());
        }
    }

    private function checkChannels(int $now): void
    {
        foreach ($this->db->all('SELECT * FROM channels WHERE enabled = 1') as $c) {
            $key = 'ch:' . $c['username'];
            $since = (int)($c['last_ok_at'] ?: $c['created_at']);
            if ($c['last_error'] !== null && $now - $since > 3 * 3600) {
                $this->send($key, 'channel', "⚠️ <b>@{$c['username']} okunamıyor</b>\n" . Telegram::esc((string)$c['last_error']) . "\nSon başarılı okuma: " . ($c['last_ok_at'] ? date('d.m H:i', (int)$c['last_ok_at']) : 'hiç'), 86400);
            } elseif ($c['last_error'] === null) {
                $this->resolve($key, 'channel', "✅ @{$c['username']} yeniden okunuyor.");
            }
        }
    }

    private function checkCron(int $now, bool $fromCron): void
    {
        $last = (int)$this->db->get('cron_last_run', '0');
        if ($fromCron || ($last > 0 && $now - $last < 900)) {
            $this->resolve('cron', 'cron', '✅ Kanal okuma yeniden çalışıyor.');
            return;
        }
        if ($last > 0) {
            $this->send('cron', 'cron', "⚠️ <b>Kanal okuma durdu</b>\nSon çalışma " . intdiv($now - $last, 60) . " dakika önce. cron-job.org işini ve adresini kontrol edin.", 21600);
        }
    }

    private function checkClaude(int $now): void
    {
        $ai = new Ai($this->db, $this->config);
        $limit = $ai->monthlyLimit();
        if (!$limit || !$ai->configured()) {
            return;
        }
        $n = (int)$ai->usage()['requests'];
        $month = date('Y-m');
        if ($n >= $limit) {
            $this->send("claude100:$month", 'claude', "🛑 <b>Claude aylık sınırı doldu</b> ($n / $limit). Yeni adlar banka ve kurallarla çevrilecek. Sınırı Çeviri ayarlarından artırabilirsiniz.", 40 * 86400);
        } elseif ($n >= (int)ceil($limit * 0.8)) {
            $this->send("claude80:$month", 'claude', "⚠️ Claude aylık sınırının %80'ine gelindi ($n / $limit).", 40 * 86400);
        }
    }

    private function checkUnpublished(int $now): void
    {
        $row = $this->db->one("SELECT COUNT(*) c, MIN(updated_at) first FROM jobs WHERE status = 'approved' AND updated_at < ?", [$now - 24 * 3600]);
        if ($row && (int)$row['c'] > 0) {
            $this->send('unpublished', 'unpublished', '📦 <b>' . (int)$row['c'] . ' onaylı iş</b> ' . intdiv($now - (int)$row['first'], 3600) . " saattir panele eklenmeyi bekliyor.\nPanel → İşler → \"Onaylandı\" filtresinden ekleme paketini alıp Perfect Panel'e ekleyin, sonra \"Sitede yayınlandı\" işaretleyin.", 86400);
        } else {
            $st = $this->db->getJson('alerts_state', []);
            if (!empty($st['unpublished']['active'])) {
                $st['unpublished']['active'] = false;
                $this->db->setJson('alerts_state', $st);
            }
        }
    }

    private function checkStale(int $now): void
    {
        $row = $this->db->one("SELECT COUNT(*) c, MIN(created_at) first FROM jobs WHERE status IN ('new','awaiting','editing') AND created_at < ?", [$now - 12 * 3600]);
        if ($row && (int)$row['c'] > 0) {
            $this->send('stale', 'stale', '⏰ <b>' . (int)$row['c'] . ' iş</b> ' . intdiv($now - (int)$row['first'], 3600) . " saattir onay bekliyor.\nBot mesajlarındaki butonlarla ya da panelden karar verebilirsiniz.", 86400);
        } else {
            $st = $this->db->getJson('alerts_state', []);
            if (!empty($st['stale']['active'])) {
                $st['stale']['active'] = false;
                $this->db->setJson('alerts_state', $st);
            }
        }
    }

    private function checkDaily(int $now): void
    {
        $d = $this->daily();
        if (!$d['on'] || (int)date('G', $now) < $d['hour'] || $this->db->get('alerts_daily_sent') === date('Y-m-d', $now)) {
            return;
        }
        $day = strtotime('today', $now);
        $jobs = (int)$this->db->one('SELECT COUNT(*) c FROM jobs WHERE created_at >= ?', [$day])['c'];
        $approved = (int)$this->db->one("SELECT COUNT(*) c FROM jobs WHERE updated_at >= ? AND status IN ('approved','published')", [$day])['c'];
        $pending = (int)$this->db->one("SELECT COUNT(*) c FROM jobs WHERE status IN ('new','awaiting','editing')")['c'];
        $waiting = (int)$this->db->one("SELECT COUNT(*) c FROM jobs WHERE status = 'approved'")['c'];
        $changes = (new Notices($this->db))->openCount();
        $errors = (int)$this->db->one("SELECT COUNT(*) c FROM logs WHERE level = 'error' AND ts >= ?", [$day])['c'];
        $ai = new Ai($this->db, $this->config);
        $u = $ai->usage();
        $msg = "📊 <b>Günlük özet</b> · " . date('d.m.Y', $now) . "\n"
            . "Gelen iş: $jobs · onaylanan: $approved\n"
            . "Onay bekleyen: $pending · sitede yayın bekleyen: $waiting\n"
            . "Açık servis değişikliği: $changes · bugünkü hata: $errors"
            . ($ai->configured() ? "\nClaude bu ay: {$u['requests']} istek, ~$" . number_format((float)$u['cost'], 2) : '');
        if ($this->deliver($msg)) {
            $this->db->set('alerts_daily_sent', date('Y-m-d', $now));
        }
    }

    /** Pazartesi 09:00'dan sonraki ilk kontrolde, haftada bir. */
    private function checkWeekly(int $now): void
    {
        $week = date('o-\\WW', $now);
        if (!$this->enabled('weekly') || (int)date('N', $now) !== 1 || (int)date('G', $now) < 9 || $this->db->get('alerts_weekly_sent') === $week) {
            return;
        }
        $msg = $this->weeklyText($now);
        if ($this->deliver($msg)) {
            $this->db->set('alerts_weekly_sent', $week);
        }
    }

    /** Son 7 günün özeti. */
    public function weeklyText(int $now): string
    {
        $from = $now - 7 * 86400;
        $one = fn(string $sql, array $a = []): int => (int)($this->db->one($sql, $a)['c'] ?? 0);
        $jobs = $one('SELECT COUNT(*) c FROM jobs WHERE created_at >= ?', [$from]);
        $items = 0;
        $bank = 0;
        foreach ($this->db->all('SELECT items_json FROM jobs WHERE created_at >= ?', [$from]) as $j) {
            foreach ((array)json_decode((string)$j['items_json'], true) as $it) {
                $items++;
                $bank += ($it['via'] ?? '') === 'bank' ? 1 : 0;
            }
        }
        $approved = $one("SELECT COUNT(*) c FROM jobs WHERE updated_at >= ? AND status IN ('approved','published')", [$from]);
        $rejected = $one("SELECT COUNT(*) c FROM jobs WHERE updated_at >= ? AND status = 'rejected'", [$from]);
        $pending = $one("SELECT COUNT(*) c FROM jobs WHERE status IN ('new','awaiting','editing')");
        $waiting = $one("SELECT COUNT(*) c FROM jobs WHERE status = 'approved'");
        $apiNew = $one('SELECT COUNT(*) c FROM api_seen WHERE first_seen >= ?', [$from]);
        $errors = $one("SELECT COUNT(*) c FROM logs WHERE level = 'error' AND ts >= ?", [$from]);
        $msg = "🗓 <b>Haftalık özet</b> · " . date('d.m', $from) . ' – ' . date('d.m.Y', $now) . "\n"
            . "Gelen iş: $jobs ($items servis) · onaylanan: $approved · reddedilen: $rejected\n"
            . ($items ? 'Bankadan çevrilen: %' . round($bank * 100 / $items) . "\n" : '')
            . "API'den yakalanan yeni servis: $apiNew\n"
            . "Şu an onay bekleyen: $pending · sitede yayın bekleyen: $waiting\n"
            . "Hata kaydı: $errors";
        $rep = $this->db->getJson('learn_report');
        if (is_array($rep) && !empty($rep['eval']['ready']) && $rep['eval']['test'] > 0) {
            $msg .= "\nÇeviri doğruluğu (ölçüm): %" . round($rep['eval']['base'] * 100 / $rep['eval']['test']) . ' → öneriyle %' . round($rep['eval']['after'] * 100 / $rep['eval']['test']);
        }
        try {
            $r = (new Rivals($this->db, $this->config))->compare('all', '', 1, 1);
            if ($r['summary']['compared'] > 0) {
                $msg .= "\nRakip kıyası: {$r['summary']['compared']} servis, rakipten pahalı olduğun: {$r['summary']['pricier']}, ucuz olduğun: {$r['summary']['cheaper']}";
            }
        } catch (\Throwable) {
        }
        return $msg;
    }
}
