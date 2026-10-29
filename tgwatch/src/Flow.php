<?php
declare(strict_types=1);

namespace TgWatch;

use TgWatch\Publisher\AdminApiPublisher;
use TgWatch\Publisher\ManualPublisher;
use TgWatch\Publisher\Publisher;

/**
 * İş akışı: duyuru → iş (new) → bota önizleme (awaiting) → [düzenle (editing)] → onay
 * → yayınlanıyor (publishing) → yayınlandı (published) / hata (failed). Reddet → rejected.
 */
final class Flow
{
    public const LABELS = [
        'new' => 'Yeni (bota gönderilmedi)',
        'awaiting' => 'Onay bekliyor',
        'editing' => 'Düzenleniyor',
        'publishing' => 'Yayınlanıyor',
        'approved' => 'Onaylandı (yayın bekliyor)',
        'published' => 'Yayınlandı',
        'failed' => 'Yayın hatası',
        'rejected' => 'Reddedildi',
    ];

    public function notices(): Notices
    {
        return new Notices($this->db);
    }

    public function enrich(): Enrich
    {
        return new Enrich($this->db);
    }

    private ?Publisher $publisher = null;

    public function __construct(private Db $db, private Telegram $tg, private array $config)
    {
    }

    public function setPublisher(Publisher $p): void
    {
        $this->publisher = $p;
    }

    private function owner(): int
    {
        return (int)$this->config['owner_id'];
    }

    public function translator(): Translator
    {
        return new Translator(Dictionary::load($this->db));
    }

    /** Çeviri motoru: banka + kurallar. */
    public function engine(): Engine
    {
        return new Engine($this->db, Dictionary::load($this->db));
    }

    public function ai(): Ai
    {
        return new Ai($this->db, $this->config);
    }

    public function publisher(): Publisher
    {
        if ($this->publisher === null) {
            $this->publisher = ($this->config['publisher'] ?? 'manual') === 'admin_api'
                ? new AdminApiPublisher((string)$this->config['panel_api_url'], (string)$this->config['panel_api_key'])
                : new ManualPublisher();
        }
        return $this->publisher;
    }

    /**
     * Gönderiden iş oluşturur. Servis yoksa ya da daha önce işlendiyse null döner.
     */
    public function createJob(string $channel, int $msgId, string $text, bool $notify = true): ?int
    {
        $items = Parser::parse($text);
        if (!$items) {
            return null;
        }
        $items = $this->convertCurrency($items);
        $hash = sha1(trim(preg_replace('/\s+/u', ' ', Normalizer::text($text)) ?? $text));
        if ($this->db->one('SELECT id FROM jobs WHERE (channel = ? AND msg_id = ?) OR (channel = ? AND text_hash = ?)', [$channel, $msgId, $channel, $hash])) {
            return null; // aynı gönderi ya da aynı metin daha önce işlendi
        }
        // Aynı servisi başka kanallar da paylaşıyorsa (son N gün, aynı sağlayıcı) tekrar iş açma: mevcut işe kaynak olarak ekle.
        $dups = $this->duplicateItems($channel, $items);
        if ($dups) {
            foreach (array_unique($dups) as $jid) {
                $this->addJobSource($jid, $channel, $msgId);
            }
            $items = array_values(array_filter($items, static fn($i) => !isset($dups[(int)$i['id']])));
            if (!$items) {
                $this->db->log('info', 'flow', "@$channel/$msgId: tüm servisler zaten işte (#" . implode(', #', array_unique($dups)) . '), yeni iş açılmadı');
                return null;
            }
        }
        // Öncelikli sağlayıcı modu: aynı servisi farklı sağlayıcıların kanalları paylaşıyorsa öncelikliyi tercih et.
        $prio = new Priority($this->db, $this->config);
        $supersede = [];
        $elsewhere = [];
        if ($prio->enabled() && $channel !== 'elle') {
            [$skip, $supersede, $elsewhere] = $this->priorityPlan($prio, $channel, $items);
            if ($skip) {
                foreach (array_unique($skip) as $jid) {
                    $this->addJobSource($jid, $channel, $msgId);
                }
                $dups += $skip;
                $items = array_values(array_filter($items, static fn($i) => !isset($skip[(int)$i['id']])));
                if (!$items) {
                    $this->db->log('info', 'flow', "@$channel/$msgId: tüm servisler öncelikli sağlayıcıda/başka işte var, yeni iş açılmadı");
                    return null;
                }
            }
        }
        $items = $this->engine()->translateItems($items);
        if ($prio->enabled() && $this->sourceId($channel) !== $prio->sourceId()) { // öncelikli sağlayıcıda benzeri varsa göster
            foreach ($items as &$it) {
                $hit = $prio->inCatalog((string)$it['name']);
                if ($hit) {
                    $it['prio'] = $hit;
                }
            }
            unset($it);
        }
        $ai = $this->ai();
        $aiState = $ai->active() && array_filter($items, [$ai, 'needs']) ? 'pending' : '';
        $now = time();
        $this->db->run(
            'INSERT INTO jobs(channel, msg_id, text_hash, source_text, items_json, status, created_at, updated_at, ai_state, also_json) VALUES(?,?,?,?,?,?,?,?,?,?)',
            [$channel, $msgId, $hash, $text, json_encode($items, JSON_UNESCAPED_UNICODE), 'new', $now, $now, $aiState,
                json_encode(array_filter(['skipped' => $dups, 'supersedes' => $supersede, 'elsewhere' => $elsewhere])) ?: '{}']
        );
        $id = (int)$this->db->pdo->lastInsertId();
        foreach ($supersede as $oldId => $sids) {
            $this->supersedeItems((int)$oldId, $sids, $id);
        }
        $this->db->log('info', 'flow', "İş #$id oluşturuldu: @$channel/$msgId, " . count($items) . ' servis');
        if ($this->autoApprovable($channel, $items, $aiState)) { // kanal "banka %100 ise otomatik onayla" modunda
            $this->db->log('info', 'flow', "İş #$id otomatik onaylandı (tüm adlar bankadan)");
            $this->approve($id);
            return $id;
        }
        if ($notify) {
            $this->sendPreview($id);
        }
        return $id;
    }

    /** Ayarlar'daki kur tablosu: "RUB=0.011, TRY=0.029" → ['RUB' => 0.011, ...] (1 birim = kaç USD). */
    public static function fxRates(array $config): array
    {
        $out = [];
        if (preg_match_all('/\b([A-Za-z]{3})\s*=\s*(\d+(?:[.,]\d+)?)/', (string)($config['fx_rates'] ?? ''), $m, PREG_SET_ORDER)) {
            foreach ($m as $x) {
                $out[strtoupper($x[1])] = (float)str_replace(',', '.', $x[2]);
            }
        }
        return $out;
    }

    /** USD dışındaki fiyatları kurla dolara çevirir; kur yoksa fiyatı boş bırakıp asıl tutarı saklar. */
    private function convertCurrency(array $items): array
    {
        $fx = self::fxRates($this->config);
        foreach ($items as &$it) {
            $cur = $it['currency'] ?? '';
            if ($cur === '' || $it['price'] === '') {
                continue;
            }
            $it['price_orig'] = $it['price'] . ' ' . $cur;
            $rate = $fx[$cur] ?? 0.0;
            if ($rate > 0) {
                $it['price'] = rtrim(rtrim(number_format((float)$it['price'] * $rate, 4, '.', ''), '0'), '.');
            } else {
                $it['price'] = '';
                $it['fx_missing'] = true;
            }
        }
        return $items;
    }

    private function dedupeDays(): int
    {
        return max(0, (int)($this->config['dedupe_days'] ?? 7));
    }

    /** Kanalın bağlı olduğu sağlayıcı (aynı ID'ler yalnızca aynı sağlayıcıda aynı servistir). */
    private function sourceId(string $channel): int
    {
        return (int)($this->enrich()->sourceFor($channel)['id'] ?? 0);
    }

    /**
     * Son dedupe_days gün içinde, aynı sağlayıcıdaki başka işlerde zaten bulunan servisler.
     * Elle eklenen işler (kanal "elle") bilerek tekrar edilebilir.
     * @return array<int,int> servis ID => iş no
     */
    private function duplicateItems(string $channel, array $items): array
    {
        $days = $this->dedupeDays();
        if ($days === 0 || $channel === 'elle') {
            return [];
        }
        $ids = array_map(static fn($i) => (int)$i['id'], $items);
        $mine = $this->sourceId($channel);
        $cache = [];
        $out = [];
        foreach ($this->db->all("SELECT id, channel, items_json FROM jobs WHERE created_at >= ? AND status != 'failed' ORDER BY id", [time() - $days * 86400]) as $j) {
            $cache[$j['channel']] ??= $this->sourceId($j['channel']);
            if ($cache[$j['channel']] !== $mine) {
                continue;
            }
            foreach ((array)json_decode((string)$j['items_json'], true) as $it) {
                $sid = (int)($it['id'] ?? 0);
                if (in_array($sid, $ids, true) && !isset($out[$sid])) {
                    $out[$sid] = (int)$j['id'];
                }
            }
        }
        return $out;
    }

    /**
     * Öncelikli sağlayıcı kuralı. Yalnızca iki tarafın sağlayıcısı da biliniyorsa çalışır.
     * @return array{0:array<int,int>,1:array<int,list<int>>,2:array<int,int>} [atlanacak servis => iş, öncelik nedeniyle düşecek: eski iş => servisler, başka sağlayıcıda zaten eklenmiş: servis => iş]
     */
    private function priorityPlan(Priority $prio, string $channel, array $items): array
    {
        $mine = $this->sourceId($channel);
        $days = max(1, $this->dedupeDays());
        $skip = [];
        $supersede = [];
        $elsewhere = [];
        if ($mine <= 0) {
            return [$skip, $supersede, $elsewhere];
        }
        $cache = [];
        foreach ($this->db->all("SELECT id, channel, status, items_json FROM jobs WHERE created_at >= ? AND status != 'failed' ORDER BY id", [time() - $days * 86400]) as $j) {
            $cache[$j['channel']] ??= $this->sourceId($j['channel']);
            $theirs = $cache[$j['channel']];
            if ($theirs <= 0 || $theirs === $mine) {
                continue;
            }
            foreach ((array)json_decode((string)$j['items_json'], true) as $old) {
                foreach ($items as $it) {
                    $sid = (int)$it['id'];
                    if (isset($skip[$sid]) || !Priority::twin((string)$it['name'], (string)($old['name'] ?? ''))) {
                        continue;
                    }
                    if ($mine === $prio->sourceId()) {
                        if (in_array($j['status'], ['new', 'awaiting'], true)) {
                            $supersede[(int)$j['id']][] = (int)$old['id'];
                        } else {
                            $elsewhere[$sid] = (int)$j['id'];
                        }
                    } else {
                        $skip[$sid] = (int)$j['id']; // öncelikli (ya da önce gelen) sağlayıcının işi var
                    }
                }
            }
        }
        return [$skip, $supersede, $elsewhere];
    }

    /** Öncelikli sağlayıcıdan gelen iş, karar bekleyen eski işteki aynı servislerin yerine geçer. */
    private function supersedeItems(int $oldId, array $sids, int $newId): void
    {
        $old = $this->db->job($oldId);
        if ($old === null || !in_array($old['status'], ['new', 'awaiting'], true)) {
            return;
        }
        $left = array_values(array_filter($old['items'], static fn($i) => !in_array((int)$i['id'], $sids, true)));
        $also = json_decode((string)($old['also_json'] ?? '{}'), true) ?: [];
        foreach ($sids as $s) {
            $also['superseded'][(string)$s] = $newId;
        }
        $this->db->run('UPDATE jobs SET also_json = ? WHERE id = ?', [json_encode($also), $oldId]);
        if (!$left) {
            $this->db->setStatus($oldId, 'rejected', "Öncelikli sağlayıcıdan geldi (iş #$newId)");
            $this->db->log('info', 'flow', "İş #$oldId, öncelikli sağlayıcının #$newId işi geldiği için kapatıldı");
        } else {
            $this->db->saveItems($oldId, $left);
            $this->db->log('info', 'flow', "İş #$oldId: " . count($sids) . " servis öncelikli sağlayıcının #$newId işine taşındı");
        }
        $this->sendPreview($oldId);
    }

    /** Mevcut işe "bu kanal da paylaştı" bilgisini ekler; karar bekliyorsa bot mesajını günceller. */
    private function addJobSource(int $jobId, string $channel, int $msgId): void
    {
        $job = $this->db->job($jobId);
        if ($job === null) {
            return;
        }
        $also = json_decode((string)($job['also_json'] ?? '{}'), true) ?: [];
        foreach ($also['sources'] ?? [] as $s) {
            if ($s['channel'] === $channel && (int)$s['msg'] === $msgId) {
                return;
            }
        }
        if ($job['channel'] === $channel && (int)$job['msg_id'] === $msgId) {
            return;
        }
        $also['sources'][] = ['channel' => $channel, 'msg' => $msgId, 'at' => time()];
        $this->db->run('UPDATE jobs SET also_json = ? WHERE id = ?', [json_encode($also), $jobId]);
        $this->db->log('info', 'flow', "İş #$jobId: @$channel/$msgId de aynı servisleri paylaştı (yeni iş açılmadı)");
        if (in_array($job['status'], ['new', 'awaiting'], true)) {
            $this->sendPreview($jobId);
        }
    }

    /** Kanalda otomatik onay açıksa ve her ad bankadan (eksik kelime yok, Claude gerekmiyor) geldiyse true. */
    private function autoApprovable(string $channel, array $items, string $aiState): bool
    {
        $ch = $this->db->one('SELECT auto_approve FROM channels WHERE username = ?', [$channel]);
        if (!$ch || !(int)$ch['auto_approve'] || $aiState !== '' || !$items) {
            return false;
        }
        foreach ($items as $it) {
            if (($it['via'] ?? '') !== 'bank' || !empty($it['unknown'])) {
                return false;
            }
        }
        return true;
    }

    /** Bota gönderilememiş işleri tekrar dener (cron çağırır). */
    public function retryUnsent(): void
    {
        foreach ($this->db->all("SELECT id FROM jobs WHERE status = 'new' ORDER BY id LIMIT 5") as $row) {
            $this->sendPreview((int)$row['id']);
        }
    }

    public function sendPreview(int $id): bool
    {
        $job = $this->db->job($id);
        if ($job === null) {
            return false;
        }
        [$html, $kb] = $this->render($job);
        try {
            if ($job['tg_message_id']) {
                $this->tg->editMessage($this->owner(), (int)$job['tg_message_id'], $html, $kb);
            } else {
                $msg = $this->tg->sendMessage($this->owner(), $html, $kb);
                $this->db->run('UPDATE jobs SET tg_message_id = ? WHERE id = ?', [(int)$msg['message_id'], $id]);
            }
            if ($job['status'] === 'new') {
                $this->db->setStatus($id, 'awaiting');
            }
            return true;
        } catch (\Throwable $e) {
            $this->db->log('error', 'bot', "İş #$id bota gönderilemedi: " . $e->getMessage());
            $this->db->run('UPDATE jobs SET note = ? WHERE id = ?', ['Bot: ' . $e->getMessage(), $id]);
            return false;
        }
    }

    /** @return array{0:string,1:array} mesaj HTML'i ve butonlar */
    public function render(array $job): array
    {
        $e = [Telegram::class, 'esc'];
        $id = (int)$job['id'];
        $head = preg_match('/^api-(\d+)$/', (string)$job['channel'], $am)
            ? "🆕 <b>Yeni servisler (API)</b> — " . $e((string)($this->db->one('SELECT name FROM sources WHERE id = ?', [(int)$am[1]])['name'] ?? 'sağlayıcı')) . " panelinde bulundu · iş #$id\n"
            : "🆕 <b>Yeni servisler</b> — @" . $e($job['channel']) . " (gönderi {$job['msg_id']}) · iş #$id\n";
        $cats = array_unique(array_filter(array_map(static fn($i) => $i['category_az'] ?? '', $job['items'])));
        if ($cats) {
            $head .= '📁 ' . $e(implode(' / ', $cats)) . "\n";
        }
        $also = json_decode((string)($job['also_json'] ?? '{}'), true) ?: [];
        if (!empty($also['sources'])) {
            $head .= '📡 Ayrıca paylaşan: ' . implode(', ', array_map(static fn($s) => '@' . $e($s['channel']) . ' (' . (int)$s['msg'] . ')', array_slice($also['sources'], 0, 6))) . "\n";
        }
        if (!empty($also['skipped'])) {
            $head .= 'ℹ️ ' . count($also['skipped']) . ' servis başka işte zaten var (#' . implode(', #', array_unique(array_values($also['skipped']))) . ") — burada tekrar yok\n";
        }
        if (!empty($also['supersedes'])) {
            $head .= '⭐ Öncelikli sağlayıcı: ' . array_sum(array_map('count', $also['supersedes'])) . ' servis #' . implode(', #', array_keys($also['supersedes'])) . " işindekilerin yerine geçti\n";
        }
        if (!empty($also['elsewhere'])) {
            $head .= 'ℹ️ ' . count($also['elsewhere']) . ' servis başka sağlayıcıdan #' . implode(', #', array_unique(array_values($also['elsewhere']))) . " işinde zaten eklenmiş\n";
        }
        if (!empty($also['superseded'])) {
            $head .= '🔁 ' . count($also['superseded']) . ' servis öncelikli sağlayıcının #' . implode(', #', array_unique(array_values($also['superseded']))) . " işine taşındı\n";
        }
        $info = null;
        if (in_array($job['status'], ['new', 'awaiting', 'editing'], true)) {
            try {
                $info = $this->enrich()->forJob($job);
            } catch (\Throwable) {
            }
        }
        if ($info && $info['provider']) {
            $head .= '🏷 Sağlayıcı: ' . $e($info['provider']['name']) . "\n";
        }
        if ($info && $info['category']['az'] !== '') {
            $head .= '📁 Kategori: ' . $e($info['category']['az']) . ($info['category']['exists'] ? ' (panelde var)' : ' (yeni)') . "\n";
        }
        if ($info && $info['duplicates'] > 0) {
            $head .= '⚠️ <b>' . $info['duplicates'] . ' servis panelinde zaten var</b>' . "\n";
        }
        $head .= "\n";
        $body = '';
        $shown = 0;
        foreach ($job['items'] as $it) {
            $x = $info['items'][(int)$it['id']] ?? null;
            $extra = '';
            if ($x && $x['rate'] !== null) {
                $extra = '💲 ' . number_format($x['rate'], 2) . ($x['suggest'] !== null ? ' → öneri ' . number_format($x['suggest'], 2) : '')
                    . ($x['min'] !== null ? ' · ' . $x['min'] . '–' . $x['max'] : '') . "\n";
            }
            if ($x && $x['own']) {
                $extra .= '⚠️ Panelinde var: #' . (int)$x['own']['service_id'] . "\n";
            }
            if (!empty($it['prio'])) {
                $extra .= '⭐ Öncelikli sağlayıcıda benzeri: #' . (int)$it['prio']['id'] . ($it['prio']['rate'] !== null ? ' · $' . number_format((float)$it['prio']['rate'], 2) : '') . "\n";
            }
            if (!empty($it['price_orig'])) {
                $extra .= !empty($it['fx_missing'])
                    ? '⚠️ Fiyat ' . $e($it['price_orig']) . ' — kur tanımlı değil (Ayarlar → Kanal okuma → Kurlar)' . "\n"
                    : '💱 ' . $e($it['price_orig']) . ' ≈ $' . $e($it['price']) . "\n";
            }
            $block = '<b>' . $it['id'] . '</b>' . ($it['price'] !== '' && !$x ? ' · $' . $e($it['price']) : '') . ($it['edited'] ? ' ✏️' : '') . "\n"
                . '📝 ' . $e($it['name']) . "\n"
                . '🇦🇿 <b>' . $e($it['az']) . "</b>\n"
                . ($it['unknown'] ? '⚠️ Sözlükte yok: ' . $e(implode(', ', $it['unknown'])) . "\n" : '')
                . (!empty($it['ai_note']) ? '🤖 ' . $e(mb_strimwidth($it['ai_note'], 0, 160, '…')) . "\n" : '')
                . $extra
                . "\n";
            if (mb_strlen($head . $body . $block) > 3500) {
                break;
            }
            $body .= $block;
            $shown++;
        }
        $total = count($job['items']);
        if ($shown < $total) {
            $body .= '… +' . ($total - $shown) . " servis daha (tam liste web panelde)\n\n";
        }
        $status = $job['status'];
        $kb = [];
        if (in_array($status, ['new', 'awaiting', 'editing'], true)) {
            $foot = $status === 'editing' ? '✏️ Düzeltme bekleniyor (ID | yeni ad).' : '❓ <b>Çeviriyi onaylıyor musun?</b>';
            $kb = [
                [['text' => '✅ Onayla', 'callback_data' => "ok:$id"], ['text' => '✏️ Düzenle', 'callback_data' => "ed:$id"]],
                [['text' => '🔄 Yeniden oluştur', 'callback_data' => "rg:$id"], ['text' => '❌ Reddet', 'callback_data' => "rj:$id"]],
            ];
        } elseif ($status === 'failed') {
            $foot = '⛔ Yayın hatası: ' . $e($job['note']);
            $kb = [[['text' => '🔁 Tekrar yayınla', 'callback_data' => "ok:$id"], ['text' => '❌ Reddet', 'callback_data' => "rj:$id"]]];
        } else {
            $icon = ['publishing' => '⏳', 'approved' => '✅', 'published' => '🌐', 'rejected' => '❌'][$status] ?? '•';
            $foot = $icon . ' ' . (self::LABELS[$status] ?? $status) . ($job['note'] !== '' ? ': ' . $e($job['note']) : '');
        }
        return [$head . $body . $foot, $kb];
    }

    // --- eylemler (bot ve web panel ortak) ---

    /**
     * @param bool $allowApproved true: "Onaylandı (yayın bekliyor)" işi de yayınla (yalnızca web panelden, bilinçli olarak).
     *                            Bot butonu false geçer: çift basış aynı servisleri iki kez yayınlamasın.
     */
    public function approve(int $id, bool $allowApproved = false): string
    {
        $from = $allowApproved ? ['new', 'awaiting', 'editing', 'failed', 'approved'] : ['new', 'awaiting', 'editing', 'failed'];
        if (!$this->db->transition($id, $from, 'publishing')) {
            return 'Bu iş zaten işlendi.';
        }
        $this->clearEditing($id);
        $job = $this->db->job($id);
        try {
            $r = $this->publisher()->publish($job);
        } catch (\Throwable $ex) {
            $r = ['ok' => false, 'message' => $ex->getMessage()];
        }
        $status = $r['ok'] ? (($r['status'] ?? 'published') === 'approved' ? 'approved' : 'published') : 'failed';
        $this->db->setStatus($id, $status, $r['message']);
        $this->db->log($r['ok'] ? 'info' : 'error', 'publish', "İş #$id: " . $r['message']);
        if ($r['ok']) {
            $this->learnFromJob($job);
        }
        $this->sendPreview($id);
        if ($status === 'published') {
            $this->notifyPublished($job);
        }
        return $r['ok'] ? 'Onaylandı: ' . $r['message'] : 'Yayın hatası: ' . $r['message'];
    }

    /** Yalnızca servisler sitede gerçekten yayınlanınca gönderilen kısa bildirim. */
    private function notifyPublished(array $job): void
    {
        $ids = array_map(static fn($i) => (string)$i['id'], $job['items']);
        $list = implode(', ', array_slice($ids, 0, 30)) . (count($ids) > 30 ? ' …' : '');
        $this->safe(fn() => $this->tg->sendMessage($this->owner(),
            "✅ <b>Sitede yayınlandı</b> — iş #{$job['id']} · " . count($ids) . " servis\n<code>" . Telegram::esc($list) . '</code>'));
    }

    /** API yokken: servisler panelde elle eklendiyse "yayınlandı" olarak işaretler (bildirim gönderilmez). */
    public function markPublished(int $id): string
    {
        if (!$this->db->transition($id, ['approved', 'failed'], 'published', 'Elle yayınlandı olarak işaretlendi')) {
            return 'Yalnızca onaylanmış işler işaretlenebilir.';
        }
        $this->db->log('info', 'publish', "İş #$id elle yayınlandı olarak işaretlendi");
        $this->sendPreview($id);
        return 'Yayınlandı olarak işaretlendi.';
    }

    public function reject(int $id): string
    {
        if (!$this->db->transition($id, ['new', 'awaiting', 'editing', 'failed', 'approved'], 'rejected')) {
            return 'Bu iş zaten işlendi.';
        }
        $this->clearEditing($id);
        $this->db->log('info', 'flow', "İş #$id reddedildi");
        $this->sendPreview($id);
        return 'Reddedildi.';
    }

    public function startEdit(int $id): string
    {
        if (!$this->db->transition($id, ['new', 'awaiting', 'editing'], 'editing')) {
            return 'Bu iş artık düzenlenemez.';
        }
        $this->db->set('editing_job', (string)$id);
        $job = $this->db->job($id);
        $this->sendPreview($id);
        $list = '';
        foreach ($job['items'] as $it) {
            $list .= $it['id'] . ' | ' . $it['az'] . "\n";
        }
        $this->tg->sendMessage($this->owner(),
            "✏️ <b>İş #$id düzenleme</b>\nAşağıdaki listeyi kopyala, yalnızca değiştirmek istediğin satırları düzelt ve gönder.\n"
            . "Biçim: <code>ID | yeni ad</code> (her satır bir servis). Vazgeçmek için /iptal\n\n<pre>" . Telegram::esc(rtrim($list)) . '</pre>');
        return 'Düzeltmeleri bota yaz.';
    }

    /**
     * "ID | ad" satırlarını uygular.
     * @return array{changed:int, errors:list<string>}
     */
    public function applyEdits(int $id, string $text): array
    {
        $job = $this->db->job($id);
        if ($job === null || !in_array($job['status'], ['awaiting', 'editing', 'new'], true)) {
            return ['changed' => 0, 'errors' => ['Bu iş artık düzenlenemez.']];
        }
        $byId = [];
        foreach ($job['items'] as $k => $it) {
            $byId[(int)$it['id']] = $k;
        }
        $changed = 0;
        $errors = [];
        foreach (preg_split('/\R/u', $text) as $line) {
            $line = trim($line);
            if ($line === '') {
                continue;
            }
            if (!preg_match('/^(\d{2,7})\s*(?:\||:|=|->|→|–|-)\s*(.+)$/u', $line, $m)) {
                $errors[] = 'Anlaşılmadı: ' . mb_substr($line, 0, 60);
                continue;
            }
            $sid = (int)$m[1];
            if (!isset($byId[$sid])) {
                $errors[] = "ID $sid bu işte yok";
                continue;
            }
            $k = $byId[$sid];
            $new = trim($m[2]);
            if ($new !== $job['items'][$k]['az']) {
                $job['items'][$k]['az'] = $new;
                $job['items'][$k]['edited'] = true;
                $job['items'][$k]['via'] = 'manual';
                unset($job['items'][$k]['ai_note']);
                $job['items'][$k]['unknown'] = [];
                $changed++;
            }
        }
        $this->db->saveItems($id, $job['items']);
        if ($changed > 0 || !$errors) {
            $this->db->transition($id, ['editing'], 'awaiting');
            $this->clearEditing($id);
        }
        $this->db->log('info', 'flow', "İş #$id: $changed ad düzenlendi");
        return ['changed' => $changed, 'errors' => $errors];
    }

    public function regenerate(int $id): string
    {
        $job = $this->db->job($id);
        if ($job === null || !in_array($job['status'], ['new', 'awaiting', 'editing'], true)) {
            return 'Bu iş artık değiştirilemez.';
        }
        $dict = Dictionary::load($this->db);
        $engine = new Engine($this->db, $dict);
        $items = $engine->translateItems(array_map(static fn($i) => [
            'id' => $i['id'], 'name' => $i['name'], 'price' => $i['price'], 'category' => $i['category'] ?? '',
        ], $job['items']));
        $how = 'banka ve kurallarla';
        $ai = $this->ai();
        if ($ai->active()) {
            try {
                $r = $ai->improve($items, $engine, $dict);
                $items = $r['items'];
                $how = $r['sent'] ? "Claude ile ({$r['applied']}/{$r['sent']} ad" . ($r['rejected'] ? ', ' . count($r['rejected']) . ' öneri kontrolden geçmedi' : '') . ')' : 'bankadan (Claude gerekmedi)';
            } catch (\Throwable $e) {
                $this->db->log('error', 'claude', "İş #$id: " . $e->getMessage());
                $how = 'banka ve kurallarla (Claude hatası: ' . mb_substr($e->getMessage(), 0, 120) . ')';
            }
        }
        $this->db->saveItems($id, $items);
        $this->db->run("UPDATE jobs SET ai_state = '' WHERE id = ?", [$id]);
        $this->db->transition($id, ['editing'], 'awaiting');
        $this->clearEditing($id);
        $this->db->log('info', 'flow', "İş #$id yeniden oluşturuldu: $how");
        $this->sendPreview($id);
        return "Yeniden oluşturuldu: $how";
    }

    /**
     * Claude kuyruğu: karma/claude modunda yeni işlerin adlarını Claude ile iyileştirir (cron çağırır, tur başına bir iş).
     * @return int|null işlenen iş id'si
     */
    public function processAiQueue(int $deadline): ?int
    {
        $ai = $this->ai();
        if (!$ai->active() || $deadline - time() < 20) {
            return null;
        }
        $row = $this->db->one("SELECT id FROM jobs WHERE ai_state = 'pending' AND status IN ('new','awaiting','editing') ORDER BY id LIMIT 1");
        if ($row === null) {
            return null;
        }
        $id = (int)$row['id'];
        $this->db->run("UPDATE jobs SET ai_state = 'running' WHERE id = ?", [$id]);
        $job = $this->db->job($id);
        $dict = Dictionary::load($this->db);
        try {
            $r = $ai->improve($job['items'], new Engine($this->db, $dict), $dict);
            // Bu sırada elle düzenlenen ad varsa onu koru.
            $fresh = $this->db->job($id);
            $edited = [];
            foreach ($fresh['items'] as $it) {
                if (!empty($it['edited'])) {
                    $edited[(int)$it['id']] = $it;
                }
            }
            $items = array_map(static fn($it) => $edited[(int)$it['id']] ?? $it, $r['items']);
            $this->db->saveItems($id, $items);
            $this->db->run("UPDATE jobs SET ai_state = 'done' WHERE id = ?", [$id]);
            $this->db->log('info', 'claude', "İş #$id: {$r['applied']}/{$r['sent']} ad Claude ile çevrildi" . ($r['rejected'] ? '; kontrolden geçmeyen: ' . implode(' | ', $r['rejected']) : ''));
            $this->sendPreview($id);
        } catch (\Throwable $e) {
            $this->db->run("UPDATE jobs SET ai_state = 'failed' WHERE id = ?", [$id]);
            $this->db->log('error', 'claude', "İş #$id: " . $e->getMessage());
        }
        return $id;
    }

    /**
     * claude.ai'den yapıştırılan "ID | ad" cevabını uygular (kontrolden geçenler yazılır).
     * @return array{applied:int, rejected:list<string>, missing:int}
     */
    public function applyPaste(int $id, string $text): array
    {
        $job = $this->db->job($id);
        if ($job === null || !in_array($job['status'], ['new', 'awaiting', 'editing'], true)) {
            throw new \RuntimeException('Bu iş artık değiştirilemez.');
        }
        $map = Ai::parseLines($text);
        if (!$map) {
            throw new \RuntimeException('Yapıştırılan metinde "ID | ad" satırı bulunamadı.');
        }
        $r = Ai::apply($job['items'], $map, Dictionary::load($this->db));
        $this->db->saveItems($id, $r['items']);
        $this->db->run("UPDATE jobs SET ai_state = '' WHERE id = ?", [$id]);
        $this->db->transition($id, ['editing'], 'awaiting');
        $this->clearEditing($id);
        $this->sendPreview($id);
        $ids = array_map(static fn($i) => (int)$i['id'], $job['items']);
        return ['applied' => $r['applied'], 'rejected' => $r['rejected'], 'missing' => count(array_diff($ids, array_keys($map)))];
    }

    /** Onaylanan işin adlarını bankaya öğretir (elle düzeltilenler en yüksek öncelikle). */
    private function learnFromJob(array $job): void
    {
        try {
            $engine = $this->engine();
            $mem = $engine->memory();
            foreach ($job['items'] as $it) {
                if (trim((string)$it['az']) !== '') {
                    $mem->learn('service', $engine->strip($it['name']), $it['az'], !empty($it['edited']) ? 'manual' : 'approved');
                }
            }
        } catch (\Throwable $e) {
            $this->db->log('error', 'bank', "İş #{$job['id']} bankaya eklenemedi: " . $e->getMessage());
        }
    }

    /**
     * Sözlük değişince açık işin adlarını yeniden çevirir; elle düzenlenen adlara dokunmaz.
     * Değişiklik varsa bottaki onay mesajını da günceller.
     */
    public function refreshTranslations(int $id): bool
    {
        $job = $this->db->job($id);
        if ($job === null || !in_array($job['status'], ['new', 'awaiting', 'editing'], true)) {
            return false;
        }
        $engine = $this->engine();
        $changed = false;
        foreach ($job['items'] as &$it) {
            if (($it['category'] ?? '') !== '') {
                $it['category_az'] = $engine->translateCategory($it['category']);
            }
            if (!empty($it['edited']) || ($it['via'] ?? '') === 'claude') {
                continue; // elle ya da Claude ile çevrilen adlara dokunma
            }
            $r = $engine->translate($it['name']);
            if ($r['text'] !== $it['az'] || $r['unknown'] !== ($it['unknown'] ?? []) || $r['via'] !== ($it['via'] ?? '')) {
                $it['az'] = $r['text'];
                $it['unknown'] = $r['unknown'];
                $it['via'] = $r['via'];
                $changed = true;
            }
        }
        unset($it);
        if ($changed) {
            $this->db->saveItems($id, $job['items']);
            $this->sendPreview($id);
        }
        return $changed;
    }

    // --- fiyat değişikliği / kapatılan servis bildirimleri ---

    public function createNotice(string $channel, int $msgId, string $text, string $kind): ?int
    {
        $dup = $this->notices()->findDuplicate($kind, Notices::extractIds($text), $this->dedupeDays());
        if ($dup !== null) { // aynı değişikliği başka kanal da duyurdu: yeni bildirim açma
            $this->notices()->addSource($dup, $channel, $msgId);
            $this->db->log('info', 'notice', "Bildirim #$dup: @$channel/$msgId de aynı değişikliği duyurdu");
            $n = $this->notices()->get($dup);
            if ($n && $n['status'] === 'open') {
                $this->sendNotice($dup);
            }
            return null;
        }
        $id = $this->notices()->create($channel, $msgId, $text, $kind);
        // Mesajın kendisi bildirimdir; "Servis değişikliği" uyarısı kapalıysa yalnızca panelde görünür.
        if ($id !== null && (new Alerts($this->db, $this->tg, $this->config))->enabled('change')) {
            $this->sendNotice($id);
        }
        return $id;
    }

    /** @return array{0:string,1:array} */
    public function renderNotice(array $n): array
    {
        $e = [Telegram::class, 'esc'];
        $id = (int)$n['id'];
        $icon = $n['kind'] === 'closed' ? '🚫' : '💲';
        $head = $icon . ' <b>' . $e(Notices::KINDS[$n['kind']] ?? $n['kind']) . '</b> — @' . $e($n['channel']) . " (gönderi {$n['msg_id']}) · bildirim #$id\n\n";
        $also = json_decode((string)($n['also_json'] ?? '{}'), true) ?: [];
        if (!empty($also['sources'])) {
            $head .= '📡 Ayrıca duyuran: ' . implode(', ', array_map(static fn($s) => '@' . $e($s['channel']), array_slice($also['sources'], 0, 6))) . "\n\n";
        }
        $body = '';
        foreach ($this->notices()->impact($n) as $x) {
            $line = '<b>' . $x['id'] . '</b>';
            if ($n['kind'] === 'price' && $x['new'] !== null) {
                $line .= ' · ' . ($x['old'] !== null ? '$' . number_format($x['old'], 2) . ' → ' : '→ ') . '$' . number_format($x['new'], 2);
            }
            $line .= "\n" . ($x['own'] ? '✅ ' : '❔ ') . $e($x['action']) . "\n\n";
            if (mb_strlen($head . $body . $line) > 3600) {
                $body .= "… devamı web panelde\n\n";
                break;
            }
            $body .= $line;
        }
        if ($n['status'] === 'open') {
            return [$head . $body . '❓ <b>Panelinde bunu yaptın mı?</b>', [[['text' => '✅ Yaptım', 'callback_data' => "nd:$id"], ['text' => '🚫 Yoksay', 'callback_data' => "ni:$id"]]]];
        }
        return [$head . $body . (Notices::STATUS[$n['status']] ?? $n['status']), []];
    }

    public function sendNotice(int $id): bool
    {
        $n = $this->notices()->get($id);
        if ($n === null) {
            return false;
        }
        [$html, $kb] = $this->renderNotice($n);
        try {
            if ($n['tg_message_id']) {
                $this->tg->editMessage($this->owner(), (int)$n['tg_message_id'], $html, $kb);
            } else {
                $msg = $this->tg->sendMessage($this->owner(), $html, $kb);
                $this->db->run('UPDATE notices SET tg_message_id = ? WHERE id = ?', [(int)$msg['message_id'], $id]);
            }
            return true;
        } catch (\Throwable $e) {
            $this->db->log('error', 'bot', "Bildirim #$id bota gönderilemedi: " . $e->getMessage());
            return false;
        }
    }

    public function resolveNotice(int $id, string $status): string
    {
        if (!$this->notices()->setStatus($id, $status)) {
            return 'Bu bildirim zaten işlendi.';
        }
        $this->db->log('info', 'notice', "Bildirim #$id: " . (Notices::STATUS[$status] ?? $status));
        $this->sendNotice($id);
        return $status === 'done' ? 'Yapıldı olarak işaretlendi.' : 'Yoksayıldı.';
    }

    private function clearEditing(int $id): void
    {
        if ($this->db->get('editing_job') === (string)$id) {
            $this->db->set('editing_job', '');
        }
    }

    // --- Telegram güncellemeleri (webhook ya da polling) ---

    public function handleUpdate(array $u): void
    {
        $uid = (int)($u['update_id'] ?? 0);
        $last = (int)$this->db->get('last_update_id', '0');
        if ($uid > 0 && $uid <= $last) {
            return; // aynı güncelleme tekrar geldi
        }
        if ($uid > 0) {
            $this->db->set('last_update_id', (string)$uid);
        }

        if (isset($u['callback_query'])) {
            $this->handleCallback($u['callback_query']);
        } elseif (isset($u['message'])) {
            $this->handleMessage($u['message']);
        }
    }

    private function handleCallback(array $cb): void
    {
        $from = (int)($cb['from']['id'] ?? 0);
        if ($from !== $this->owner() || $this->owner() === 0) {
            $this->db->log('warn', 'bot', "Yetkisiz buton denemesi: kullanıcı $from");
            $this->safe(fn() => $this->tg->answerCallback((string)$cb['id'], 'Yetkiniz yok.'));
            return;
        }
        if (preg_match('/^(nd|ni):(\d+)$/', (string)($cb['data'] ?? ''), $nm)) {
            $this->safe(fn() => $this->tg->answerCallback((string)$cb['id'], 'Tamam'));
            $this->resolveNotice((int)$nm[2], $nm[1] === 'nd' ? 'done' : 'ignored');
            return;
        }
        if (preg_match('/^ba:(ask|yes|no)$/', (string)($cb['data'] ?? ''), $bm)) {
            $reply = (new BotCommands($this->db, $this->tg, $this, $this->config))->callback($bm[1], $cb['message']['chat']['id'] ?? $this->owner(), (int)($cb['message']['message_id'] ?? 0));
            $this->safe(fn() => $this->tg->answerCallback((string)$cb['id'], $reply));
            return;
        }
        if (!preg_match('/^(ok|ed|rg|rj):(\d+)$/', (string)($cb['data'] ?? ''), $m)) {
            $this->safe(fn() => $this->tg->answerCallback((string)$cb['id']));
            return;
        }
        $id = (int)$m[2];
        // Telegram callback'e kısa sürede cevap bekler: önce yanıtla, sonra işi yap.
        $this->safe(fn() => $this->tg->answerCallback((string)$cb['id'], 'İşleniyor…'));
        $res = match ($m[1]) {
            'ok' => $this->approve($id),
            'ed' => $this->startEdit($id),
            'rg' => $this->regenerate($id),
            'rj' => $this->reject($id),
        };
        $this->db->log('info', 'bot', "Buton {$m[1]} iş #$id: $res");
    }

    private function handleMessage(array $msg): void
    {
        $from = (int)($msg['from']['id'] ?? 0);
        $chat = $msg['chat']['id'] ?? $from;
        $text = trim((string)($msg['text'] ?? ''));
        if (($msg['chat']['type'] ?? 'private') !== 'private') {
            return;
        }
        if (preg_match('~^/(start|id)\b~', $text)) {
            $isOwner = $from === $this->owner() && $from !== 0;
            $this->safe(fn() => $this->tg->sendMessage($chat,
                "Telegram ID'niz: <code>$from</code>\n" . ($isOwner ? '✅ tgwatch hazır. /durum' : "Bu ID'yi config.php içinde owner_id olarak yazın.")));
            return;
        }
        if ($from !== $this->owner() || $this->owner() === 0) {
            return;
        }
        if ($text !== '' && $text[0] === '/' && $text !== '/iptal' && (new BotCommands($this->db, $this->tg, $this, $this->config))->handle($chat, $text)) {
            return;
        }
        $editing = (int)$this->db->get('editing_job', '0');
        if ($text === '/iptal') {
            if ($editing) {
                $this->db->transition($editing, ['editing'], 'awaiting');
                $this->db->set('editing_job', '');
                $this->sendPreview($editing);
            }
            $this->safe(fn() => $this->tg->sendMessage($chat, 'Düzenleme iptal edildi.'));
            return;
        }
        if ($editing && $text !== '' && $text[0] !== '/') {
            $r = $this->applyEdits($editing, $text);
            $this->sendPreview($editing);
            $reply = "✏️ {$r['changed']} ad güncellendi." . ($r['errors'] ? "\n⚠️ " . Telegram::esc(implode("\n⚠️ ", $r['errors'])) : '');
            $this->safe(fn() => $this->tg->sendMessage($chat, $reply));
        }
    }

    private function safe(callable $fn): void
    {
        try {
            $fn();
        } catch (\Throwable $e) {
            $this->db->log('error', 'bot', $e->getMessage());
        }
    }
}
