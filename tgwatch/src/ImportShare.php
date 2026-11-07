<?php
declare(strict_types=1);

namespace TgWatch;

/**
 * Import sonrası: Chrome eklentisi işi bitirince (kategori çevrildi, kaydedildi, en üste taşındı) tgwatch'a haber verir.
 *   1) Sahibe Telegram özeti: kategori, servis sayısı, çeviri durumu, kanal önizlemesi.
 *   2) Telegram kanalında paylaşım: kategorinin servisleri kendi panelin API'sinden çekilir (ID, ad, fiyat, min/max),
 *      Ayarlar → Telegram'daki şablonla mesaj hazırlanır. Mod: kapalı / bildirimde "Kanalda paylaş" düğmesi / otomatik.
 * Kayıtlar "import_shares" (son 30) olarak saklanır; düğmeler sh:post:ID / sh:skip:ID.
 */
final class ImportShare
{
    /** PanelBaku paylaşım panelinin (panelbaku/script.js → buildTemplate) biçimi. */
    public const DEFAULT_TEMPLATE = "{ikon} {kategori}\n\n{servisler}\n\n50 azn və yuxarı Balans artırmalarda + %5 Bonus 🎁\n\n🔗 https://panelbaku.com";
    public const DEFAULT_LINE = "🆔 {id} - {ad} -- {qiymet}";
    private const ICONS = ['instagram' => '📸', 'facebook' => '📘', 'tiktok' => '🎵', 'youtube' => '▶️', 'telegram' => '✈️', 'twitter' => '🐦',
        'discord' => '🎮', 'pinterest' => '📌', 'snapchat' => '👻', 'twitch' => '🎥', 'linkedin' => '💼', 'whatsapp' => '💬', 'kick' => '🥊'];

    /** Kategori simgesi (paylaşım panelindeki iconFor ile aynı). */
    public static function icon(string $name): string
    {
        $n = mb_strtolower($name);
        foreach (self::ICONS as $k => $e) {
            if (str_contains($n, $k)) {
                return $e;
            }
        }
        return str_contains($n, ' x ') || str_starts_with($n, 'x ') ? '🐦' : '⭐';
    }
    private const KEY = 'import_shares';

    public function __construct(private Db $db, private Telegram $tg, private array $config)
    {
    }

    private function cfg(string $k, string $def = ''): string
    {
        $v = $this->config[$k] ?? null;
        return is_string($v) && $v !== '' ? $v : $def;
    }

    public function mode(): string
    {
        $m = $this->cfg('share_mode', 'ask');
        return in_array($m, ['off', 'ask', 'auto'], true) ? $m : 'ask';
    }

    public function channel(): string
    {
        return trim($this->cfg('share_channel'));
    }

    private static function key(string $s): string
    {
        $s = str_replace("i\u{0307}", 'i', mb_strtolower(Normalizer::text($s))); // "İ" küçülünce i + birleşik nokta olur
        $s = preg_replace('/[^\p{L}\p{N}]+/u', ' ', $s) ?? $s;
        return trim(preg_replace('/\s+/u', ' ', $s) ?? $s);
    }

    /** Kendi panelin servis listesini tazeler (izleme kaydı tutmadan). Hata metni ya da ''. */
    private function syncOwn(): array
    {
        $own = $this->db->one("SELECT id FROM sources WHERE kind = 'own' ORDER BY id LIMIT 1");
        if (!$own) {
            return [0, 'Kendi panelin tgwatch\'a ekli değil (Ayarlar → Kaynaklar → kendi panelim): servis ID ve fiyatları alınamadı.'];
        }
        try {
            SmmApi::syncDiff($this->db, (int)$own['id'], false, false);
            return [(int)$own['id'], ''];
        } catch (\Throwable $e) {
            return [(int)$own['id'], 'Kendi panelin okunamadı: ' . mb_substr($e->getMessage(), 0, 120)];
        }
    }

    /** @return list<array{id:string,name:string,rate:?float,min:?int,max:?int}> */
    private function servicesOf(int $ownId, string $category): array
    {
        if (!$ownId || $category === '') {
            return [];
        }
        $k = self::key($category);
        $out = [];
        foreach ($this->db->all('SELECT * FROM remote_services WHERE source_id = ? ORDER BY rowid', [$ownId]) as $r) {
            if (self::key((string)$r['category']) === $k) {
                $out[] = ['id' => (string)$r['service_id'], 'name' => (string)$r['name'], 'rate' => $r['rate'] !== null ? (float)$r['rate'] : null,
                    'min' => $r['min'] !== null ? (int)$r['min'] : null, 'max' => $r['max'] !== null ? (int)$r['max'] : null];
            }
        }
        return $out;
    }

    /** Fiyat: 2 ondalık (paylaşım panelindeki formatRate gibi: 0.45, 2.00). */
    private static function num(?float $v): string
    {
        return $v === null ? '—' : number_format($v, 2, '.', '');
    }

    private static function int(?int $v): string
    {
        return $v === null ? '—' : number_format($v, 0, '.', ' ');
    }

    /**
     * Kanal mesajı (4096 sınırına göre parçalanmış). Şablon HTML olabilir; yerleştirilen değerler kaçışlanır.
     * @param list<array> $cats [{name, services:[...]}]
     * @return list<string>
     */
    public function render(array $cats): array
    {
        $tpl = $this->cfg('share_template', self::DEFAULT_TEMPLATE);
        $line = $this->cfg('share_line', self::DEFAULT_LINE);
        $cur = $this->cfg('share_currency', '₼');
        $panel = $this->cfg('share_link', '');
        $out = [];
        foreach ($cats as $c) {
            $lines = [];
            foreach ($c['services'] as $s) {
                $lines[] = strtr($line, [
                    '{id}' => Telegram::esc((string)$s['id']), '{ad}' => Telegram::esc((string)$s['name']), '{qiymet}' => self::num($s['rate']),
                    '{min}' => self::int($s['min']), '{max}' => self::int($s['max']), '{valyuta}' => Telegram::esc($cur),
                ]);
            }
            $vars = ['{kategori}' => Telegram::esc((string)$c['name']), '{ikon}' => self::icon((string)$c['name']), '{say}' => (string)count($c['services']), '{tarix}' => date('d.m.Y'),
                '{panel}' => Telegram::esc($panel), '{valyuta}' => Telegram::esc($cur)];
            [$head, $tail] = array_pad(explode('{servisler}', strtr($tpl, $vars), 2), 2, '');
            // Uzun listeler: başlık ilk parçada, kapanış son parçada; araya sığdığı kadar satır.
            $chunk = $head;
            foreach ($lines as $i => $l) {
                $sep = $i === 0 ? '' : "\n\n";
                if (mb_strlen($chunk . $sep . $l . $tail) > 3900 && $chunk !== $head) {
                    $out[] = rtrim($chunk);
                    $chunk = $l;
                    continue;
                }
                $chunk .= $sep . $l;
            }
            $out[] = rtrim($chunk . $tail);
        }
        $out = array_values(array_filter($out, static fn($t) => trim(strip_tags($t)) !== ''));
        // Paylaşım panelindeki gibi bütün mesaj kalın (şablonda kendi <b> etiketin varsa kapat).
        return $this->cfg('share_bold', 'on') === 'off' ? $out : array_map(static fn($t) => '<b>' . str_replace(['<b>', '</b>'], '', $t) . '</b>', $out);
    }

    private function all(): array
    {
        $v = $this->db->getJson(self::KEY, []);
        return is_array($v) ? $v : [];
    }

    private function store(array $rec): void
    {
        $all = array_filter($this->all(), static fn($r) => (int)($r['id'] ?? 0) !== (int)$rec['id']);
        $all[] = $rec;
        usort($all, static fn($a, $b) => (int)$a['id'] <=> (int)$b['id']);
        $this->db->setJson(self::KEY, array_slice($all, -30));
    }

    public function find(int $id): ?array
    {
        foreach ($this->all() as $r) {
            if ((int)$r['id'] === $id) {
                return $r;
            }
        }
        return null;
    }

    /**
     * Eklentiden gelen rapor.
     * $p: {categories:[{name, src, moved}], note, kind, counts:{ok,warn,bad,done}, services:[{name, rate}]}
     */
    public function report(array $p): array
    {
        $cats = [];
        foreach (array_slice(is_array($p['categories'] ?? null) ? $p['categories'] : [], 0, 5) as $c) {
            if (!is_array($c)) {
                continue;
            }
            $name = trim(mb_substr((string)($c['name'] ?? ''), 0, 200));
            if ($name !== '') {
                $cats[] = ['name' => $name, 'src' => trim(mb_substr((string)($c['src'] ?? ''), 0, 200)), 'moved' => !empty($c['moved']), 'services' => []];
            }
        }
        [$ownId, $ownErr] = $cats ? $this->syncOwn() : [0, ''];
        foreach ($cats as &$c) {
            $c['services'] = $this->servicesOf($ownId, $c['name']);
        }
        unset($c);
        // Eklentinin import penceresinde gördüğü servisler: yalnızca sayı için.
        $extSvc = [];
        foreach (array_slice(is_array($p['services'] ?? null) ? $p['services'] : [], 0, 300) as $s) {
            if (is_array($s) && trim((string)($s['name'] ?? '')) !== '') {
                $r = $s['rate'] ?? null;
                $extSvc[] = ['id' => '', 'name' => trim(mb_substr((string)$s['name'], 0, 300)), 'rate' => is_numeric($r) ? (float)$r : null, 'min' => null, 'max' => null];
            }
        }
        // Kendi panelden okunamadıysa kanal mesajı hazırlanmaz: ID ve fiyatsız liste paylaşılmaya değmez (özette nedeni yazar).
        $counts = is_array($p['counts'] ?? null) ? $p['counts'] : [];
        $id = (int)$this->db->get('import_share_seq', '0') + 1;
        $this->db->set('import_share_seq', (string)$id);
        $withSvc = array_values(array_filter($cats, static fn($c) => $c['services']));
        $rec = ['aux' => [], 'id' => $id, 'at' => time(), 'categories' => $cats, 'note' => mb_substr((string)($p['note'] ?? ''), 0, 300), 'kind' => (string)($p['kind'] ?? ''),
            'counts' => $counts, 'imported' => count($extSvc), 'own_error' => $ownErr, 'chunks' => $this->render($withSvc), 'status' => 'new', 'msg' => 0];
        $mode = $this->mode();
        $chan = $this->channel();
        if ($mode !== 'off' && $rec['chunks']) {
            // Kanal mesajı hazırlandıysa düğmeler hep gelir (kanal ayarlı değilse Paylaş nedenini söyler).
            if ($mode === 'auto' && $chan !== '') {
                $err = $this->postChunks($rec['chunks']);
                $rec['status'] = $err === '' ? 'posted' : 'failed';
                $rec['error'] = $err;
            } else {
                $rec['status'] = 'pending';
            }
        }
        $notify = $this->cfg('import_notify', 'on') !== 'off' || $rec['status'] === 'pending';
        if ($notify && $this->tg->configured() && (int)($this->config['owner_id'] ?? 0)) {
            try {
                $m = $this->tg->sendMessage((int)$this->config['owner_id'], $this->summary($rec), in_array($rec['status'], ['pending', 'failed'], true) ? self::buttons($id) : null);
                $rec['msg'] = (int)($m['message_id'] ?? 0);
            } catch (\Throwable $e) {
                $this->db->log('error', 'bot', 'Import bildirimi gönderilemedi: ' . $e->getMessage());
            }
        }
        $this->store($rec);
        $this->db->log('info', 'ext', 'Import raporu #' . $id . ': ' . count($cats) . ' kategori, ' . array_sum(array_map(static fn($c) => count($c['services']), $cats)) . ' servis, kanal: ' . $rec['status']);
        return ['id' => $id, 'status' => $rec['status'], 'services' => array_sum(array_map(static fn($c) => count($c['services']), $cats)), 'notified' => $rec['msg'] > 0];
    }

    private static function buttons(int $id): array
    {
        return [[['text' => '📢 Kanalda paylaş', 'callback_data' => "sh:post:$id"], ['text' => '✏️ Düzenle', 'callback_data' => "sh:edit:$id"], ['text' => 'Paylaşma', 'callback_data' => "sh:skip:$id"]]];
    }

    /** HTML parçayı düz metne çevir (düzenlemek için kopyalanacak hâl). */
    private static function plain(string $html): string
    {
        return html_entity_decode(strip_tags($html), ENT_QUOTES | ENT_HTML5, 'UTF-8');
    }

    /** Sohbetteki yardımcı mesajları sil (düzenleme metni, kullanıcının yazdığı metin). */
    private function cleanup(array &$rec, bool $summaryToo = false): void
    {
        $owner = (int)$this->config['owner_id'];
        $ids = $rec['aux'] ?? [];
        if ($summaryToo && $rec['msg']) {
            $ids[] = (int)$rec['msg'];
            $rec['msg'] = 0;
        }
        foreach (array_unique(array_filter(array_map('intval', $ids))) as $mid) {
            try {
                $this->tg->deleteMessage($owner, $mid);
            } catch (\Throwable $e) {
                // 48 saatten eski ya da zaten silinmiş
            }
        }
        $rec['aux'] = [];
    }

    /** Düzenleme başlat: metin kod bloğu içinde gelir (Telegram'da dokununca / Kopyala ile panoya), sahip değiştirip geri yazar. */
    public function startEdit(int $id): string
    {
        $rec = $this->find($id);
        if (!$rec || !$rec['chunks']) {
            return 'Düzenlenecek mesaj yok';
        }
        if ($rec['status'] === 'posted') {
            return 'Zaten paylaşıldı';
        }
        $this->db->set('editing_job', '');
        $this->db->set('editing_share', (string)$id);
        $owner = (int)$this->config['owner_id'];
        $this->cleanup($rec);
        $text = implode("\n\n", array_map([self::class, 'plain'], $rec['chunks']));
        $m1 = $this->tg->sendMessage($owner, "✏️ <b>Kanal mesajını düzenle</b>\n1) Aşağıdaki kutuya dokun ya da sağ üstteki <b>Kopyala</b>'ya bas: metin panona gelir.\n2) Mesaj kutusuna yapıştır, değiştir, gönder.\nVazgeçmek için /iptal");
        $rec['aux'][] = (int)($m1['message_id'] ?? 0);
        foreach (self::split($text, 3900) as $part) {
            $m = $this->tg->sendMessage($owner, '<pre>' . Telegram::esc($part) . '</pre>');
            $rec['aux'][] = (int)($m['message_id'] ?? 0);
        }
        $this->store($rec);
        return 'Metin geldi: kopyala, düzenle, gönder';
    }

    /** Uzun düz metni paragraf sınırlarından böl. @return list<string> */
    private static function split(string $text, int $max): array
    {
        $out = [];
        $cur = '';
        foreach (preg_split('/\n{2,}/u', $text) ?: [] as $para) {
            $add = ($cur === '' ? '' : "\n\n") . $para;
            if ($cur !== '' && mb_strlen($cur . $add) > $max) {
                $out[] = $cur;
                $cur = $para;
            } else {
                $cur .= $add;
            }
        }
        if ($cur !== '') {
            $out[] = $cur;
        }
        return $out;
    }

    /** Düzenleme modunda gelen metin: yardımcı mesajlar ve eski önizleme silinir, yeni önizleme düğmelerle gelir. */
    public function applyEdit(int $id, string $text, int $userMsg = 0): void
    {
        $this->db->set('editing_share', '');
        $rec = $this->find($id);
        $owner = (int)$this->config['owner_id'];
        if (!$rec) {
            $this->tg->sendMessage($owner, 'Kayıt bulunamadı, düzenleme bırakıldı.');
            return;
        }
        $text = trim(str_replace(["\r\n", "\r"], "\n", $text));
        $chunks = array_map([Telegram::class, 'esc'], self::split($text, 3900));
        if ($this->cfg('share_bold', 'on') !== 'off') {
            $chunks = array_map(static fn($t) => '<b>' . $t . '</b>', $chunks);
        }
        $rec['chunks'] = array_values(array_filter($chunks, static fn($t) => trim(strip_tags($t)) !== ''));
        $rec['edited'] = true;
        if ($rec['status'] !== 'posted') {
            $rec['status'] = $rec['chunks'] ? 'pending' : $rec['status'];
        }
        if ($userMsg) {
            $rec['aux'][] = $userMsg;
        }
        $this->cleanup($rec, true); // talimat, kopyalanan metin, senin mesajın ve eski önizleme
        try {
            $m = $this->tg->sendMessage($owner, $this->summary($rec), $rec['status'] === 'pending' ? self::buttons($id) : null);
            $rec['msg'] = (int)($m['message_id'] ?? 0);
        } catch (\Throwable $e) {
            $this->db->log('error', 'bot', 'Düzenlenen önizleme gönderilemedi: ' . $e->getMessage());
        }
        $this->store($rec);
    }

    public function cancelEdit(): void
    {
        $id = (int)$this->db->get('editing_share', '0');
        $this->db->set('editing_share', '');
        $rec = $id ? $this->find($id) : null;
        if ($rec) {
            $this->cleanup($rec);
            $this->store($rec);
        }
    }

    /** Sahibe giden özet. */
    public function summary(array $rec): string
    {
        $bad = in_array($rec['kind'], ['bad', 'warn'], true);
        $s = ($bad ? '⚠️' : '✅') . ' <b>Import ' . ($bad ? 'bitti, kontrol et' : 'tamamlandı') . '</b>';
        $c = $rec['counts'];
        if ($rec['imported'] || $c) {
            $parts = [];
            if (!empty($c['ok']) || !empty($c['done'])) {
                $parts[] = ((int)($c['ok'] ?? 0) + (int)($c['done'] ?? 0)) . ' hazır';
            }
            if (!empty($c['warn'])) {
                $parts[] = (int)$c['warn'] . ' kontrol et';
            }
            if (!empty($c['bad'])) {
                $parts[] = (int)$c['bad'] . ' çevrilmedi';
            }
            $s .= "\n⚙️ " . ($rec['imported'] ? (int)$rec['imported'] . ' servis' : 'Servisler') . ($parts ? ' · ' . implode(', ', $parts) : '');
        }
        foreach ($rec['categories'] as $cat) {
            $s .= "\n📂 <b>" . Telegram::esc($cat['name']) . '</b>' . ($cat['src'] !== '' && $cat['src'] !== $cat['name'] ? ' <i>(' . Telegram::esc($cat['src']) . ')</i>' : '')
                . ($cat['moved'] ? ' · en üstte' : '') . ($cat['services'] ? ' · panelde ' . count($cat['services']) . ' servis' : '');
        }
        if (!$rec['categories']) {
            $s .= "\n📂 Yeni kategori yok: servisler mevcut bir kategoriye eklenmiş.";
        }
        if ($rec['note'] !== '' && $bad) {
            $s .= "\nℹ️ " . Telegram::esc($rec['note']);
        }
        if ($rec['own_error'] !== '') {
            $s .= "\n⚠️ " . Telegram::esc($rec['own_error']);
        }
        $chan = $this->channel();
        if ($rec['status'] === 'pending' && $rec['chunks']) {
            $prev = $rec['chunks'][0];
            $s .= "\n\n<b>Kanal önizlemesi</b>" . (!empty($rec['edited']) ? ' ✏️ düzenlendi' : '') . ($chan !== '' ? ' (' . Telegram::esc($chan) . (count($rec['chunks']) > 1 ? ', ' . count($rec['chunks']) . ' mesaj' : '') . ')' : '') . ":\n<blockquote>" . self::clip($prev, 1500) . '</blockquote>'
                . ($chan === '' ? "\n<i>Paylaşım kanalı ayarlı değil: tgwatch → Ayarlar → Telegram botu → Paylaşım kanalı.</i>" : '');
        } elseif ($rec['status'] === 'posted') {
            $s .= "\n\n📢 Kanalda paylaşıldı: " . Telegram::esc($chan);
        } elseif ($rec['status'] === 'failed') {
            $s .= "\n\n⚠️ Kanalda paylaşılamadı: " . Telegram::esc((string)($rec['error'] ?? ''));
        } elseif ($rec['status'] === 'skipped') {
            $s .= "\n\nKanalda paylaşılmadı.";
        } elseif ($rec['status'] === 'replaced') {
            $s .= "\n\n✏️ Mesaj düzenlendi: yeni önizleme aşağıda.";
        } elseif ($this->mode() !== 'off' && $rec['categories'] && !$rec['chunks'] && $rec['own_error'] === '') {
            $s .= "\n\n<i>Kanal mesajı hazırlanamadı: kendi panelinde bu adla kategori bulunamadı (panel henüz güncellenmemiş olabilir).</i>";
        } elseif ($this->mode() !== 'off' && $chan === '' && $rec['categories']) {
            $s .= "\n\n<i>Kanalda paylaşmak için Ayarlar → Telegram botu → Paylaşım kanalı'nı gir.</i>";
        }
        return $s;
    }

    /** HTML'i bozmadan kısalt: uzunsa düz metne çevirip kes. */
    private static function clip(string $html, int $n): string
    {
        if (mb_strlen($html) <= $n) {
            return $html;
        }
        return Telegram::esc(mb_substr(html_entity_decode(strip_tags($html), ENT_QUOTES, 'UTF-8'), 0, $n)) . '…';
    }

    /** @return string hata metni ya da '' */
    private function postChunks(array $chunks): string
    {
        $chan = $this->channel();
        if ($chan === '') {
            return 'Paylaşım kanalı ayarlı değil';
        }
        try {
            foreach ($chunks as $t) {
                $this->tg->sendMessage($chan, $t);
            }
            return '';
        } catch (\Throwable $e) {
            $m = $e->getMessage();
            $this->db->log('error', 'bot', 'Kanal paylaşımı: ' . $m);
            if (preg_match('/chat not found|not enough rights|not a member|have no rights|CHAT_ADMIN_REQUIRED|forbidden/i', $m)) {
                return 'Bot kanalda yönetici değil ya da kanal adı yanlış (' . $chan . '). Botu kanala yönetici olarak ekle (mesaj gönderme izni).';
            }
            if (preg_match('/can\'t parse entities/i', $m)) {
                return 'Şablondaki HTML hatalı (kapanmamış etiket?). Ayarlar → Telegram botu → Kanal şablonu.';
            }
            return mb_substr($m, 0, 160);
        }
    }

    /** Düğme: paylaş ya da paylaşma. Paylaşma → bildirim ekrandan silinir. @return string kısa cevap */
    public function decide(int $id, string $how): string
    {
        $rec = $this->find($id);
        if (!$rec) {
            return 'Kayıt bulunamadı';
        }
        if ($rec['status'] === 'posted') {
            return 'Zaten paylaşıldı';
        }
        if ((int)$this->db->get('editing_share', '0') === $id) {
            $this->db->set('editing_share', '');
        }
        if ($how === 'skip') {
            $rec['status'] = 'skipped';
            $this->cleanup($rec, true);
            $this->store($rec);
            return 'Paylaşılmadı, mesaj silindi';
        }
        if ($this->channel() === '') {
            return 'Paylaşım kanalı ayarlı değil: tgwatch → Ayarlar → Telegram botu → Paylaşım kanalı';
        }
        $err = $this->postChunks($rec['chunks']);
        $rec['status'] = $err === '' ? 'posted' : 'failed';
        $rec['error'] = $err;
        if ($err === '') {
            $this->cleanup($rec);
        }
        $this->store($rec);
        if ($rec['msg']) {
            try {
                $this->tg->editMessage((int)$this->config['owner_id'], (int)$rec['msg'], $this->summary($rec), $rec['status'] === 'failed' ? self::buttons($id) : null);
            } catch (\Throwable $e) {
                $this->db->log('warn', 'bot', 'Import bildirimi güncellenemedi: ' . $e->getMessage());
            }
        }
        return $rec['status'] === 'posted' ? 'Kanalda paylaşıldı' : 'Paylaşılamadı: ' . mb_substr((string)$rec['error'], 0, 150);
    }

    /** /paylas: son importun önizlemesini düğmeleriyle yeniden gönder. @return string hata/bilgi metni ya da '' */
    public function resendLast(): string
    {
        $all = $this->all();
        $rec = null;
        foreach (array_reverse($all) as $r) {
            if (!empty($r['chunks'])) {
                $rec = $r;
                break;
            }
        }
        if (!$rec) {
            return 'Kanala hazırlanmış bir import yok. Eklentiyle import yapınca önizleme gelir.';
        }
        if ($rec['status'] === 'posted') {
            return 'Son import (' . ($rec['categories'][0]['name'] ?? '') . ') zaten kanalda paylaşıldı.';
        }
        $rec['aux'] ??= [];
        $this->cleanup($rec, true);
        $rec['status'] = 'pending';
        $m = $this->tg->sendMessage((int)$this->config['owner_id'], $this->summary($rec), self::buttons((int)$rec['id']));
        $rec['msg'] = (int)($m['message_id'] ?? 0);
        $this->store($rec);
        return '';
    }
}
