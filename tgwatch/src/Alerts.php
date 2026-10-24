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
        'stale' => ['Uzun süredir bekleyen iş', 'Bir iş 12 saatten uzun süredir onay bekliyorsa (günde bir kez)'],
        'change' => ['Servis değişikliği', 'Fiyat değişikliği ya da kapatılan servis duyurusu gelince'],
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

    private function deliver(string $html): bool
    {
        $owner = (int)($this->config['owner_id'] ?? 0);
        if ($owner <= 0) {
            return false;
        }
        try {
            $this->tg->sendMessage($owner, $html);
            return true;
        } catch (\Throwable $e) {
            $this->db->log('error', 'alert', 'Uyarı gönderilemedi: ' . $e->getMessage());
            return false;
        }
    }

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
            $this->checkDaily($now);
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
}
