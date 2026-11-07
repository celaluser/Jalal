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
    public const DEFAULT_TEMPLATE = "🆕 <b>{kategori}</b>\n\n{servisler}\n\n🛒 Sifariş üçün: {panel}";
    public const DEFAULT_LINE = "🆔 <b>{id}</b> — {ad}\n💰 {qiymet} {valyuta} / 1000 · Min {min} · Maks {max}";
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
            return [0, 'Kendi panelin tgwatch\'a ekli değil (Çeviri bankası → Kaynaklar → kendi panelim): servis ID ve fiyatları alınamadı.'];
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

    private static function num(?float $v): string
    {
        if ($v === null) {
            return '—';
        }
        $s = number_format($v, $v != 0 && abs($v) < 1 ? 4 : 2, '.', '');
        return str_contains($s, '.') ? rtrim(rtrim($s, '0'), '.') : $s;
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
            $vars = ['{kategori}' => Telegram::esc((string)$c['name']), '{say}' => (string)count($c['services']), '{tarix}' => date('d.m.Y'),
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
        return array_values(array_filter($out, static fn($t) => trim(strip_tags($t)) !== ''));
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
        $rec = ['id' => $id, 'at' => time(), 'categories' => $cats, 'note' => mb_substr((string)($p['note'] ?? ''), 0, 300), 'kind' => (string)($p['kind'] ?? ''),
            'counts' => $counts, 'imported' => count($extSvc), 'own_error' => $ownErr, 'chunks' => $this->render($withSvc), 'status' => 'new', 'msg' => 0];
        $mode = $this->mode();
        $chan = $this->channel();
        $canShare = $mode !== 'off' && $chan !== '' && $rec['chunks'];
        if ($canShare && $mode === 'auto') {
            $err = $this->postChunks($rec['chunks']);
            $rec['status'] = $err === '' ? 'posted' : 'failed';
            $rec['error'] = $err;
        } elseif ($canShare) {
            $rec['status'] = 'pending';
        }
        $notify = $this->cfg('import_notify', 'on') !== 'off' || $rec['status'] === 'pending';
        if ($notify && $this->tg->configured() && (int)($this->config['owner_id'] ?? 0)) {
            try {
                $m = $this->tg->sendMessage((int)$this->config['owner_id'], $this->summary($rec), $rec['status'] === 'pending' ? self::buttons($id) : null);
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
        return [[['text' => '📢 Kanalda paylaş', 'callback_data' => "sh:post:$id"], ['text' => 'Paylaşma', 'callback_data' => "sh:skip:$id"]]];
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
            $s .= "\n\n<b>Kanal önizlemesi</b> (" . Telegram::esc($chan) . (count($rec['chunks']) > 1 ? ', ' . count($rec['chunks']) . ' mesaj' : '') . "):\n<blockquote>" . self::clip($prev, 1500) . '</blockquote>';
        } elseif ($rec['status'] === 'posted') {
            $s .= "\n\n📢 Kanalda paylaşıldı: " . Telegram::esc($chan);
        } elseif ($rec['status'] === 'failed') {
            $s .= "\n\n⚠️ Kanalda paylaşılamadı: " . Telegram::esc((string)($rec['error'] ?? ''));
        } elseif ($rec['status'] === 'skipped') {
            $s .= "\n\nKanalda paylaşılmadı.";
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

    /** Düğme: paylaş ya da paylaşma. @return string kısa cevap */
    public function decide(int $id, string $how): string
    {
        $rec = $this->find($id);
        if (!$rec) {
            return 'Kayıt bulunamadı';
        }
        if ($rec['status'] === 'posted') {
            return 'Zaten paylaşıldı';
        }
        if ($how === 'post') {
            $err = $this->postChunks($rec['chunks']);
            $rec['status'] = $err === '' ? 'posted' : 'failed';
            $rec['error'] = $err;
        } else {
            $rec['status'] = 'skipped';
        }
        $this->store($rec);
        if ($rec['msg']) {
            try {
                $this->tg->editMessage((int)$this->config['owner_id'], (int)$rec['msg'], $this->summary($rec), $rec['status'] === 'failed' ? self::buttons($id) : null);
            } catch (\Throwable $e) {
                $this->db->log('warn', 'bot', 'Import bildirimi güncellenemedi: ' . $e->getMessage());
            }
        }
        return $rec['status'] === 'posted' ? 'Kanalda paylaşıldı' : ($rec['status'] === 'failed' ? 'Paylaşılamadı: ' . mb_substr((string)$rec['error'], 0, 150) : 'Tamam, paylaşılmadı');
    }
}
