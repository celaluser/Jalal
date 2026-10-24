<?php
declare(strict_types=1);

namespace TgWatch;

/** Bot komutları (yalnızca sahip): panele girmeden telefondan sorgu ve toplu işlem. */
final class BotCommands
{
    private const HELP = [
        '/bekleyen' => 'Karar bekleyen işler ve "hepsini onayla"',
        '/is 5' => 'İş numarasını onay butonlarıyla yeniden göster',
        '/onayla 5' => 'İşi onayla',
        '/reddet 5' => 'İşi reddet',
        '/ara 13125' => 'Servis ID ya da kelimeyle işlerde ara',
        '/fiyat 0.44' => 'Sağlayıcı fiyatından önerilen satış fiyatı',
        '/ozet' => 'Bugünün ve genel durumun özeti',
        '/kanallar' => 'Kanalların durumu',
        '/degisiklik' => 'Bekleyen fiyat/kapanma bildirimleri',
        '/yedek' => 'Yedeği şimdi dosya olarak gönder',
        '/durum' => 'İş sayıları ve son cron',
        '/iptal' => 'Düzenlemeyi iptal et',
    ];

    public function __construct(private Db $db, private Telegram $tg, private Flow $flow, private array $config)
    {
    }

    private function say(int|string $chat, string $html, ?array $kb = null): void
    {
        try {
            $this->tg->sendMessage($chat, $html, $kb);
        } catch (\Throwable $e) {
            $this->db->log('error', 'bot', $e->getMessage());
        }
    }

    /** @return bool komut işlendi mi */
    public function handle(int|string $chat, string $text): bool
    {
        if (!preg_match('~^/([a-zçğıöşü_]+)(?:@\w+)?(?:\s+(.*))?$~iu', $text, $m)) {
            return false;
        }
        $cmd = mb_strtolower($m[1]);
        $arg = trim($m[2] ?? '');
        switch ($cmd) {
            case 'yardim':
            case 'yardım':
            case 'komutlar':
            case 'help':
                $s = "🤖 <b>Komutlar</b>\n";
                foreach (self::HELP as $c => $d) {
                    $s .= "<code>$c</code> — " . Telegram::esc($d) . "\n";
                }
                $this->say($chat, $s);
                return true;
            case 'bekleyen':
                $this->pending($chat);
                return true;
            case 'is':
            case 'iş':
                $this->show($chat, (int)$arg);
                return true;
            case 'onayla':
            case 'reddet':
                $id = (int)$arg;
                if ($id <= 0) {
                    $this->say($chat, "Kullanım: <code>/$cmd 5</code> (iş numarası)");
                    return true;
                }
                $r = $cmd === 'onayla' ? $this->flow->approve($id) : $this->flow->reject($id);
                $this->say($chat, "İş #$id: " . Telegram::esc($r));
                return true;
            case 'ara':
                $this->search($chat, $arg);
                return true;
            case 'fiyat':
                $this->price($chat, $arg);
                return true;
            case 'ozet':
            case 'özet':
                $this->summary($chat);
                return true;
            case 'kanallar':
                $this->channels($chat);
                return true;
            case 'degisiklik':
            case 'değişiklik':
                $this->notices($chat);
                return true;
            case 'yedek':
                $r = (new AutoBackup($this->db, $this->config, $this->tg))->run();
                $this->say($chat, ($r['ok'] ? '💾 ' : '⚠️ ') . Telegram::esc($r['message']));
                return true;
        }
        return false;
    }

    private function jobLine(array $j): string
    {
        $items = json_decode((string)$j['items_json'], true) ?: [];
        $first = $items[0] ?? [];
        return "<b>#{$j['id']}</b> @" . Telegram::esc($j['channel']) . ' · ' . count($items) . ' servis · ' . Telegram::esc(Flow::LABELS[$j['status']] ?? $j['status'])
            . ($first ? "\n   " . (int)($first['id'] ?? 0) . ' ' . Telegram::esc(mb_strimwidth((string)($first['az'] ?? ''), 0, 60, '…')) : '');
    }

    private function pending(int|string $chat): void
    {
        $rows = $this->db->all("SELECT * FROM jobs WHERE status IN ('new','awaiting','editing') ORDER BY id LIMIT 12");
        $total = (int)$this->db->one("SELECT COUNT(*) c FROM jobs WHERE status IN ('new','awaiting','editing')")['c'];
        if (!$rows) {
            $this->say($chat, '✅ Karar bekleyen iş yok.');
            return;
        }
        $s = "⏳ <b>$total iş karar bekliyor</b>\n\n";
        foreach ($rows as $j) {
            $s .= $this->jobLine($j) . "\n";
        }
        if ($total > count($rows)) {
            $s .= "\n… +" . ($total - count($rows)) . ' iş daha (panelde)';
        }
        $s .= "\n\nBir işi görmek için <code>/is NO</code>";
        $this->say($chat, $s, [[['text' => '✅ Hepsini onayla', 'callback_data' => 'ba:ask']]]);
    }

    private function show(int|string $chat, int $id): void
    {
        $job = $id > 0 ? $this->db->job($id) : null;
        if ($job === null) {
            $this->say($chat, 'Kullanım: <code>/is 5</code>. Bu numarada iş yok.');
            return;
        }
        [$html, $kb] = $this->flow->render($job);
        $this->say($chat, $html, $kb ?: null);
    }

    private function search(int|string $chat, string $q): void
    {
        if (mb_strlen($q) < 2) {
            $this->say($chat, 'Kullanım: <code>/ara 13125</code> ya da <code>/ara threads followers</code>');
            return;
        }
        $like = '%' . str_replace(['%', '_'], ['\%', '\_'], $q) . '%';
        $rows = $this->db->all("SELECT * FROM jobs WHERE items_json LIKE ? ESCAPE '\\' OR source_text LIKE ? ESCAPE '\\' ORDER BY id DESC LIMIT 8", [$like, $like]);
        if (!$rows) {
            $this->say($chat, 'Bulunamadı: <b>' . Telegram::esc($q) . '</b>');
            return;
        }
        $s = '🔎 <b>' . Telegram::esc($q) . "</b>\n\n";
        foreach ($rows as $j) {
            $s .= $this->jobLine($j) . "\n";
        }
        $this->say($chat, $s . "\n<code>/is NO</code> ile aç");
    }

    private function price(int|string $chat, string $arg): void
    {
        $n = str_replace(',', '.', trim($arg, " $"));
        if (!is_numeric($n)) {
            $this->say($chat, 'Kullanım: <code>/fiyat 0.44</code> (sağlayıcı fiyatı, $/1000)');
            return;
        }
        $e = new Enrich($this->db);
        $s = $e->suggestPrice((float)$n);
        $this->say($chat, $s === null
            ? 'Fiyat formülü yok. Panel → Çeviri bankası → Eşleştirmeyi çalıştır ya da Çeviri ayarları → Fiyat önerisi.'
            : '💲 Sağlayıcı $' . Telegram::esc($n) . ' → önerilen satış <b>$' . rtrim(rtrim(number_format($s, 4, '.', ''), '0'), '.') . '</b>' . "\n" . Telegram::esc((string)$e->formula()));
    }

    private function summary(int|string $chat): void
    {
        $day = strtotime('today');
        $c = fn(string $sql, array $a = []) => (int)$this->db->one($sql, $a)['c'];
        $s = "📊 <b>Özet</b>\n"
            . 'Bugün gelen iş: ' . $c('SELECT COUNT(*) c FROM jobs WHERE created_at >= ?', [$day]) . "\n"
            . 'Bugün onaylanan: ' . $c("SELECT COUNT(*) c FROM jobs WHERE updated_at >= ? AND status IN ('approved','published')", [$day]) . "\n"
            . 'Karar bekleyen: ' . $c("SELECT COUNT(*) c FROM jobs WHERE status IN ('new','awaiting','editing')") . "\n"
            . 'Panele eklenmeyi bekleyen: ' . $c("SELECT COUNT(*) c FROM jobs WHERE status = 'approved'") . "\n"
            . 'Açık değişiklik bildirimi: ' . (new Notices($this->db))->openCount() . "\n"
            . 'Okunamayan gönderi: ' . $c("SELECT COUNT(*) c FROM unparsed WHERE status = 'open'") . "\n"
            . 'Son kanal okuma: ' . ($this->db->get('cron_last_run', '0') !== '0' ? date('H:i:s', (int)$this->db->get('cron_last_run')) : 'hiç');
        $problems = Monitor::problems($this->db);
        $s .= $problems ? "\n\n⚠️ " . Telegram::esc(implode("\n⚠️ ", $problems)) : "\n\n✅ Sorun görünmüyor.";
        $this->say($chat, $s);
    }

    private function channels(int|string $chat): void
    {
        $rows = $this->db->all('SELECT * FROM channels ORDER BY username');
        if (!$rows) {
            $this->say($chat, 'Kanal yok. Panel → Kanallar.');
            return;
        }
        $s = "📡 <b>Kanallar</b>\n";
        foreach ($rows as $r) {
            $state = !$r['enabled'] ? '⏸ duraklatıldı' : ($r['last_error'] ? '🔴 ' . Telegram::esc(mb_strimwidth((string)$r['last_error'], 0, 60, '…')) : ($r['last_ok_at'] ? '🟢 ' . intdiv(time() - (int)$r['last_ok_at'], 60) . ' dk önce' : '🟡 okunmadı'));
            $s .= '@' . Telegram::esc($r['username']) . ' — ' . $state . "\n";
        }
        $this->say($chat, $s);
    }

    private function notices(int|string $chat): void
    {
        $rows = $this->db->all("SELECT id FROM notices WHERE status = 'open' ORDER BY id DESC LIMIT 5");
        if (!$rows) {
            $this->say($chat, '✅ Bekleyen değişiklik bildirimi yok.');
            return;
        }
        foreach (array_reverse($rows) as $r) {
            $n = $this->flow->notices()->get((int)$r['id']);
            if ($n) {
                [$html, $kb] = $this->flow->renderNotice($n);
                $this->say($chat, $html, $kb ?: null);
            }
        }
    }

    /** "Hepsini onayla" düğmeleri: ba:ask → onay sorusu, ba:yes → uygula, ba:no → vazgeç. */
    public function callback(string $action, int|string $chat, int $messageId): string
    {
        $safe = "status IN ('new','awaiting') AND ai_state = ''";
        $jobs = $this->db->all("SELECT id, items_json FROM jobs WHERE $safe ORDER BY id LIMIT 30");
        $ok = array_values(array_filter($jobs, static function ($j) {
            foreach (json_decode((string)$j['items_json'], true) ?: [] as $it) {
                if (!empty($it['unknown'])) {
                    return false;
                }
            }
            return true;
        }));
        $skipped = (int)$this->db->one("SELECT COUNT(*) c FROM jobs WHERE status IN ('new','awaiting','editing')")['c'] - count($ok);
        try {
            if ($action === 'ask') {
                if (!$ok) {
                    $this->tg->editMessage($chat, $messageId, '⚠️ Toplu onaylanabilir iş yok: bekleyenlerde eksik kelime ya da Claude sırası var. Tek tek bakın: /bekleyen');
                    return 'Yok';
                }
                $this->tg->editMessage($chat, $messageId, '❓ <b>' . count($ok) . ' iş onaylansın mı?</b>' . ($skipped > 0 ? "\n$skipped iş atlanacak (eksik kelime ya da düzenleniyor)." : '') . "\nOnaylanan işler yayın için hazırlanır.",
                    [[['text' => '✅ Evet, ' . count($ok) . ' işi onayla', 'callback_data' => 'ba:yes'], ['text' => '↩️ Vazgeç', 'callback_data' => 'ba:no']]]);
                return 'Emin misin?';
            }
            if ($action === 'no') {
                $this->tg->editMessage($chat, $messageId, '↩️ Toplu onay iptal edildi.');
                return 'İptal';
            }
            $done = 0;
            foreach ($ok as $j) {
                if (str_starts_with($this->flow->approve((int)$j['id']), 'Onaylandı')) {
                    $done++;
                }
            }
            $this->db->log('info', 'bot', "Toplu onay: $done / " . count($ok) . ' iş');
            $this->tg->editMessage($chat, $messageId, "✅ <b>$done iş onaylandı</b>" . ($skipped > 0 ? "\n$skipped iş atlandı (eksik kelime ya da düzenleniyor): /bekleyen" : ''));
            return "$done iş onaylandı";
        } catch (\Throwable $e) {
            $this->db->log('error', 'bot', 'Toplu onay: ' . $e->getMessage());
            return 'Hata';
        }
    }
}
