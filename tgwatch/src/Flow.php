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
        'published' => 'Yayınlandı',
        'failed' => 'Yayın hatası',
        'rejected' => 'Reddedildi',
    ];

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

    public function publisher(): Publisher
    {
        if ($this->publisher === null) {
            $this->publisher = ($this->config['publisher'] ?? 'manual') === 'admin_api'
                ? new AdminApiPublisher((string)$this->config['panel_api_url'], (string)$this->config['panel_api_key'])
                : new ManualPublisher($this->tg, $this->owner());
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
        $hash = sha1(trim(preg_replace('/\s+/u', ' ', Normalizer::text($text)) ?? $text));
        if ($this->db->one('SELECT id FROM jobs WHERE (channel = ? AND msg_id = ?) OR (channel = ? AND text_hash = ?)', [$channel, $msgId, $channel, $hash])) {
            return null; // aynı gönderi ya da aynı metin daha önce işlendi
        }
        $items = $this->translator()->translateItems($items);
        $now = time();
        $this->db->run(
            'INSERT INTO jobs(channel, msg_id, text_hash, source_text, items_json, status, created_at, updated_at) VALUES(?,?,?,?,?,?,?,?)',
            [$channel, $msgId, $hash, $text, json_encode($items, JSON_UNESCAPED_UNICODE), 'new', $now, $now]
        );
        $id = (int)$this->db->pdo->lastInsertId();
        $this->db->log('info', 'flow', "İş #$id oluşturuldu: @$channel/$msgId, " . count($items) . ' servis');
        if ($notify) {
            $this->sendPreview($id);
        }
        return $id;
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
        $head = "🆕 <b>Yeni servisler</b> — @" . $e($job['channel']) . " (gönderi {$job['msg_id']}) · iş #$id\n";
        $cats = array_unique(array_filter(array_map(static fn($i) => $i['category_az'] ?? '', $job['items'])));
        if ($cats) {
            $head .= '📁 ' . $e(implode(' / ', $cats)) . "\n";
        }
        $head .= "\n";
        $body = '';
        $shown = 0;
        foreach ($job['items'] as $it) {
            $block = '<b>' . $it['id'] . '</b>' . ($it['price'] !== '' ? ' · $' . $e($it['price']) : '') . ($it['edited'] ? ' ✏️' : '') . "\n"
                . '📝 ' . $e($it['name']) . "\n"
                . '🇦🇿 <b>' . $e($it['az']) . "</b>\n"
                . ($it['unknown'] ? '⚠️ Sözlükte yok: ' . $e(implode(', ', $it['unknown'])) . "\n" : '')
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
            $icon = ['publishing' => '⏳', 'published' => '✅', 'rejected' => '❌'][$status] ?? '•';
            $foot = $icon . ' ' . (self::LABELS[$status] ?? $status) . ($job['note'] !== '' ? ': ' . $e($job['note']) : '');
        }
        return [$head . $body . $foot, $kb];
    }

    // --- eylemler (bot ve web panel ortak) ---

    public function approve(int $id): string
    {
        if (!$this->db->transition($id, ['new', 'awaiting', 'editing', 'failed'], 'publishing')) {
            return 'Bu iş zaten işlendi.';
        }
        $this->clearEditing($id);
        $job = $this->db->job($id);
        try {
            $r = $this->publisher()->publish($job);
        } catch (\Throwable $ex) {
            $r = ['ok' => false, 'message' => $ex->getMessage()];
        }
        $this->db->setStatus($id, $r['ok'] ? 'published' : 'failed', $r['message']);
        $this->db->log($r['ok'] ? 'info' : 'error', 'publish', "İş #$id: " . $r['message']);
        $this->sendPreview($id);
        return $r['ok'] ? 'Onaylandı: ' . $r['message'] : 'Yayın hatası: ' . $r['message'];
    }

    public function reject(int $id): string
    {
        if (!$this->db->transition($id, ['new', 'awaiting', 'editing', 'failed'], 'rejected')) {
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
        $items = (new Translator($dict))->translateItems(array_map(static fn($i) => [
            'id' => $i['id'], 'name' => $i['name'], 'price' => $i['price'], 'category' => $i['category'] ?? '',
        ], $job['items']));
        $how = 'kurallarla (güncel sözlük)';
        $claude = new Claude((string)$this->config['claude_api_key'], (string)$this->config['claude_model']);
        if ($claude->configured()) {
            try {
                $map = $claude->translate($items, $dict);
                foreach ($items as &$it) {
                    if (isset($map[(int)$it['id']])) {
                        $it['az'] = $map[(int)$it['id']];
                        $it['unknown'] = [];
                    }
                }
                unset($it);
                $how = 'Claude ile';
            } catch (\Throwable $e) {
                $this->db->log('error', 'claude', "İş #$id: " . $e->getMessage());
                $how = 'kurallarla (Claude hatası: ' . mb_substr($e->getMessage(), 0, 100) . ')';
            }
        }
        $this->db->saveItems($id, $items);
        $this->db->transition($id, ['editing'], 'awaiting');
        $this->clearEditing($id);
        $this->db->log('info', 'flow', "İş #$id yeniden oluşturuldu: $how");
        $this->sendPreview($id);
        return "Yeniden oluşturuldu: $how";
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
        if ($text === '/durum') {
            $rows = $this->db->all('SELECT status, COUNT(*) c FROM jobs GROUP BY status');
            $s = "📊 <b>Durum</b>\n";
            foreach ($rows as $r) {
                $s .= (self::LABELS[$r['status']] ?? $r['status']) . ': ' . $r['c'] . "\n";
            }
            $s .= 'Son cron: ' . date('Y-m-d H:i:s', (int)$this->db->get('cron_last_run', '0'));
            $this->safe(fn() => $this->tg->sendMessage($chat, $s));
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
