<?php
declare(strict_types=1);

namespace TgWatch;

/**
 * Telefondan panel yönetimi (Admin API v2 servis işlemleri):
 *   /servis 9001[,9002]   servis kartı: kategori, sağlayıcı, satış/maliyet/kâr, min/max, durum + Kapat/Aç
 *   /kapat 9001,9002      onaylı toplu kapatma
 *   /ac 9001              onaylı toplu açma
 *   /kategori instagram   kategori kartı (ad parçası ya da ID): açık/kapalı sayısı + Hepsini kapat/aç (onaylı)
 *   /zarar                zararına ya da düşük kârla satılan servisler
 * Düğmeler: ps:off|on:ID (kart üstünden tek servis, hemen), pk:show|off|on:KAT (kategori), pa:yes|no:N (bekleyen onay).
 * Toplu işlemler hep onaylıdır; bekleyen onay 15 dakika geçerlidir. Düğmelere yalnızca sahip basabilir (Flow denetler).
 */
final class PanelBot
{
    private const PENDING = 'panel_pending';
    private const TTL = 900;
    public const LOW_MARGIN = 0.1;

    private PanelServices $api;

    public function __construct(private Db $db, private Telegram $tg, private array $config)
    {
        $this->api = new PanelServices($db, $config);
    }

    private function say(int|string $chat, string $html, ?array $kb = null): void
    {
        try {
            $this->tg->sendMessage($chat, $html, $kb);
        } catch (\Throwable $e) {
            $this->db->log('error', 'bot', $e->getMessage());
        }
    }

    private function ready(int|string $chat): bool
    {
        if ($this->api->configured()) {
            return true;
        }
        $this->say($chat, 'Panel API bağlı değil: tgwatch → Ayarlar → Yayın ve panel API\'de adres ve anahtarı gir. Panelde anahtara <b>Get service list</b> ve <b>Change service status</b> izinleri gerekir.');
        return false;
    }

    /** @return list<int> */
    public static function ids(string $arg): array
    {
        preg_match_all('/\d{1,10}/', $arg, $m);
        return array_slice(array_values(array_unique(array_map('intval', $m[0]))), 0, 100);
    }

    private static function num(?float $v): string
    {
        return $v === null ? '—' : rtrim(rtrim(number_format($v, 4, '.', ''), '0'), '.');
    }

    /** Kâr oranı: (satış − maliyet) / maliyet. */
    public static function margin(array $s): ?float
    {
        return $s['rate'] !== null && $s['provider_rate'] !== null && $s['provider_rate'] > 0 ? ($s['rate'] - $s['provider_rate']) / $s['provider_rate'] : null;
    }

    private static function statusIcon(array $s): string
    {
        return ($s['status'] === 'enabled' ? '✅ açık' : '⛔ kapalı') . ($s['hidden'] ? ' · 👁 gizli' : '');
    }

    private function card(array $s): string
    {
        $m = self::margin($s);
        $cur = $s['currency'] !== '' ? ' ' . Telegram::esc($s['currency']) : '';
        return '🆔 <b>' . $s['id'] . '</b> · ' . self::statusIcon($s) . "\n"
            . '<b>' . Telegram::esc($s['name']) . "</b>\n"
            . '📂 ' . Telegram::esc($s['category_name']) . ($s['category_id'] ? ' <i>(#' . $s['category_id'] . ($s['category_status'] === 'disabled' ? ', kategori kapalı' : '') . ')</i>' : '') . "\n"
            . ($s['provider'] !== '' ? '🔗 ' . Telegram::esc($s['provider']) . ($s['provider_service_id'] !== '' ? ' · #' . Telegram::esc($s['provider_service_id']) : '') . "\n" : '')
            . '💰 satış ' . self::num($s['rate']) . $cur . ($s['provider_rate'] !== null ? ' · maliyet ' . self::num($s['provider_rate']) . $cur : '')
            . ($m !== null ? ' · kâr ' . ($m < 0 ? '🔴 ' : ($m < self::LOW_MARGIN ? '🟠 ' : '')) . round($m * 100) . '%' : '') . "\n"
            . '📏 min ' . ($s['min'] ?? '—') . ' · max ' . ($s['max'] ?? '—');
    }

    // ------------------------------------------------------------------ komutlar

    public function service(int|string $chat, string $arg): void
    {
        $ids = self::ids($arg);
        if (!$ids) {
            $this->say($chat, 'Kullanım: <code>/servis 9001</code> (birden çok: <code>/servis 9001,9002</code>)');
            return;
        }
        if (!$this->ready($chat)) {
            return;
        }
        try {
            $found = $this->api->byIds(array_slice($ids, 0, 10));
        } catch (\Throwable $e) {
            $this->say($chat, '⚠️ Panel API: ' . Telegram::esc($e->getMessage()));
            return;
        }
        foreach (array_slice($ids, 0, 10) as $id) {
            $s = $found[$id] ?? null;
            if (!$s) {
                $this->say($chat, "🆔 $id: panelde bulunamadı.");
                continue;
            }
            $btn = $s['status'] === 'enabled' ? ['text' => '⛔ Kapat', 'callback_data' => 'ps:off:' . $id] : ['text' => '✅ Aç', 'callback_data' => 'ps:on:' . $id];
            $this->say($chat, $this->card($s), [[$btn]]);
        }
        if (count($ids) > 10) {
            $this->say($chat, 'İlk 10 servis gösterildi.');
        }
    }

    /** /kapat ve /ac: onay sor. */
    public function toggle(int|string $chat, string $arg, string $status): void
    {
        $ids = self::ids($arg);
        $cmd = $status === 'disabled' ? 'kapat' : 'ac';
        if (!$ids) {
            $this->say($chat, "Kullanım: <code>/$cmd 9001</code> ya da <code>/$cmd 9001,9002,9003</code> (en çok 100)");
            return;
        }
        if (!$this->ready($chat)) {
            return;
        }
        try {
            $found = $this->api->byIds($ids);
        } catch (\Throwable $e) {
            $this->say($chat, '⚠️ Panel API: ' . Telegram::esc($e->getMessage()));
            return;
        }
        $missing = array_values(array_diff($ids, array_keys($found)));
        $todo = array_values(array_filter($ids, static fn($id) => isset($found[$id]) && $found[$id]['status'] !== $status));
        $already = count($ids) - count($missing) - count($todo);
        if (!$todo) {
            $this->say($chat, ($already ? "$already servis zaten " . ($status === 'disabled' ? 'kapalı' : 'açık') . '. ' : '') . ($missing ? 'Bulunamayan: ' . implode(', ', $missing) : ''));
            return;
        }
        $this->confirm($chat, $todo, $status, array_map(static fn($id) => $found[$id]['name'], $todo),
            ($already ? "\n<i>$already servis zaten " . ($status === 'disabled' ? 'kapalı' : 'açık') . '.</i>' : '') . ($missing ? "\n<i>Bulunamayan: " . implode(', ', $missing) . '</i>' : ''));
    }

    /** @param list<int> $ids @param list<string> $names */
    private function confirm(int|string $chat, array $ids, string $status, array $names, string $extra = '', ?int $editMsg = null): void
    {
        $all = $this->pending();
        $n = (int)$this->db->get('panel_pending_seq', '0') + 1;
        $this->db->set('panel_pending_seq', (string)$n);
        $all[$n] = ['ids' => $ids, 'status' => $status, 'at' => time()];
        $this->db->setJson(self::PENDING, array_slice($all, -20, null, true));
        $verb = $status === 'disabled' ? 'kapatılsın' : 'açılsın';
        $list = '';
        foreach (array_slice($ids, 0, 15) as $k => $id) {
            $list .= "\n• <b>$id</b> " . Telegram::esc(mb_substr($names[$k] ?? '', 0, 70));
        }
        $html = '❓ <b>' . count($ids) . ' servis ' . $verb . ' mı?</b>' . $list . (count($ids) > 15 ? "\n… ve " . (count($ids) - 15) . ' servis daha' : '') . $extra;
        $kb = [[['text' => ($status === 'disabled' ? '⛔ Evet, kapat' : '✅ Evet, aç') . ' (' . count($ids) . ')', 'callback_data' => "pa:yes:$n"], ['text' => '↩️ Vazgeç', 'callback_data' => "pa:no:$n"]]];
        if ($editMsg) {
            $this->tg->editMessage($chat, $editMsg, $html, $kb);
        } else {
            $this->say($chat, $html, $kb);
        }
    }

    /** @return array<int,array{ids:list<int>,status:string,at:int}> */
    private function pending(): array
    {
        $all = $this->db->getJson(self::PENDING, []);
        return is_array($all) ? array_filter($all, static fn($p) => is_array($p) && time() - (int)($p['at'] ?? 0) < self::TTL) : [];
    }

    public function category(int|string $chat, string $arg): void
    {
        $arg = trim($arg);
        if ($arg === '') {
            $this->say($chat, 'Kullanım: <code>/kategori instagram</code> (ad parçası) ya da <code>/kategori 15</code> (kategori ID)');
            return;
        }
        if (!$this->ready($chat)) {
            return;
        }
        if (ctype_digit($arg)) {
            $this->categoryCard($chat, (int)$arg);
            return;
        }
        // Ad parçası: son anlık görüntüden ara (yoksa panelden çek).
        $cats = $this->categories();
        if (!$cats) {
            try {
                $this->snapshot();
                $cats = $this->categories();
            } catch (\Throwable $e) {
                $this->say($chat, '⚠️ Panel API: ' . Telegram::esc($e->getMessage()));
                return;
            }
        }
        $q = self::key($arg);
        $hits = array_values(array_filter($cats, static fn($c) => str_contains(self::key($c['name']), $q)));
        if (!$hits) {
            $this->say($chat, '“' . Telegram::esc($arg) . '” geçen kategori yok. Kategori ID\'siyle dene: <code>/kategori 15</code>');
            return;
        }
        if (count($hits) === 1) {
            $this->categoryCard($chat, (int)$hits[0]['id']);
            return;
        }
        $kb = [];
        foreach (array_slice($hits, 0, 12) as $c) {
            $kb[] = [['text' => mb_substr($c['name'], 0, 40) . ' (' . $c['on'] . '/' . $c['n'] . ')', 'callback_data' => 'pk:show:' . $c['id']]];
        }
        $this->say($chat, '📂 <b>' . count($hits) . ' kategori</b> bulundu' . (count($hits) > 12 ? ' (ilk 12)' : '') . ". Birini seç (açık/toplam servis):", $kb);
    }

    private static function key(string $s): string
    {
        return trim((string)preg_replace('/\s+/u', ' ', str_replace("i\u{0307}", 'i', mb_strtolower(Normalizer::text($s)))));
    }

    /** Anlık görüntüdeki kategoriler. @return list<array{id:int,name:string,n:int,on:int}> */
    private function categories(): array
    {
        return array_map(static fn($r) => ['id' => (int)$r['category_id'], 'name' => (string)$r['category_name'], 'n' => (int)$r['n'], 'on' => (int)$r['on_n']],
            $this->db->all("SELECT category_id, category_name, COUNT(*) n, SUM(status = 'enabled') on_n FROM panel_services WHERE category_id IS NOT NULL GROUP BY category_id ORDER BY category_name"));
    }

    /** Servis listesini panelden çekip anlık görüntüyü yeniler (eşleştirme yapmadan). */
    private function snapshot(): void
    {
        $list = $this->api->services();
        $this->db->tx(function () use ($list): void {
            $this->db->run('DELETE FROM panel_services');
            $st = $this->db->pdo->prepare('INSERT OR REPLACE INTO panel_services(id, name, category_id, category_name, category_status, provider, provider_service_id, rate, provider_rate, currency, min, max, status, hidden, at) VALUES(?,?,?,?,?,?,?,?,?,?,?,?,?,?,?)');
            foreach ($list as $s) {
                $st->execute([$s['id'], $s['name'], $s['category_id'], $s['category_name'], $s['category_status'], $s['provider'], $s['provider_service_id'], $s['rate'], $s['provider_rate'], $s['currency'], $s['min'], $s['max'], $s['status'], $s['hidden'] ? 1 : 0, time()]);
            }
        });
    }

    private function categoryCard(int|string $chat, int $catId, ?int $editMsg = null): void
    {
        try {
            $list = $this->api->services(5000, ['category_id' => $catId]);
        } catch (\Throwable $e) {
            $this->say($chat, '⚠️ Panel API: ' . Telegram::esc($e->getMessage()));
            return;
        }
        if (!$list) {
            $this->say($chat, "📂 #$catId: bu kategoride servis yok (ya da kategori yok). Boş kategoriler API'de görünmez; silmek için panel ekranı gerekir.");
            return;
        }
        $on = array_values(array_filter($list, static fn($s) => $s['status'] === 'enabled'));
        $off = count($list) - count($on);
        $c = $list[0];
        $html = '📂 <b>' . Telegram::esc($c['category_name']) . '</b> <i>(#' . $catId . ($c['category_status'] === 'disabled' ? ', kategori kapalı' : '') . ")</i>\n"
            . count($list) . ' servis · ✅ ' . count($on) . ' açık · ⛔ ' . $off . ' kapalı';
        foreach (array_slice($list, 0, 12) as $s) {
            $html .= "\n" . ($s['status'] === 'enabled' ? '✅' : '⛔') . ' <b>' . $s['id'] . '</b> ' . Telegram::esc(mb_substr($s['name'], 0, 60));
        }
        if (count($list) > 12) {
            $html .= "\n… ve " . (count($list) - 12) . ' servis daha';
        }
        $html .= "\n<i>Kategorinin kendisi API ile kapatılamaz/silinemez; düğmeler içindeki servisleri kapatır/açar.</i>";
        $row = [];
        if ($on) {
            $row[] = ['text' => '⛔ Hepsini kapat (' . count($on) . ')', 'callback_data' => 'pk:off:' . $catId];
        }
        if ($off) {
            $row[] = ['text' => '✅ Hepsini aç (' . $off . ')', 'callback_data' => 'pk:on:' . $catId];
        }
        if ($editMsg) {
            $this->tg->editMessage($chat, $editMsg, $html, $row ? [$row] : null);
        } else {
            $this->say($chat, $html, $row ? [$row] : null);
        }
    }

    public function loss(int|string $chat): void
    {
        if (!$this->ready($chat)) {
            return;
        }
        try {
            $list = $this->api->services();
        } catch (\Throwable $e) {
            $this->say($chat, '⚠️ Panel API: ' . Telegram::esc($e->getMessage()));
            return;
        }
        $rows = [];
        $noCost = 0;
        foreach ($list as $s) {
            if ($s['status'] !== 'enabled') {
                continue;
            }
            $m = self::margin($s);
            if ($m === null) {
                $noCost++;
                continue;
            }
            if ($m < self::LOW_MARGIN) {
                $rows[] = [$m, $s];
            }
        }
        if (!$rows) {
            $this->say($chat, '✅ Açık servislerde zarar ya da %' . round(self::LOW_MARGIN * 100) . '\'un altında kâr yok.' . ($noCost ? "\n<i>$noCost serviste maliyet bilgisi yok (sağlayıcı bilgisi gizli ya da elle servis).</i>" : ''));
            return;
        }
        usort($rows, static fn($a, $b) => $a[0] <=> $b[0]);
        $loss = count(array_filter($rows, static fn($r) => $r[0] < 0));
        $html = '🔴 <b>' . $loss . ' servis zararda</b>, 🟠 ' . (count($rows) - $loss) . ' servis %' . round(self::LOW_MARGIN * 100) . '\'un altında kârda (açık olanlar):';
        foreach (array_slice($rows, 0, 25) as [$m, $s]) {
            $html .= "\n" . ($m < 0 ? '🔴' : '🟠') . ' <b>' . $s['id'] . '</b> ' . round($m * 100) . '% · ' . self::num($s['rate']) . ' / ' . self::num($s['provider_rate']) . ' · ' . Telegram::esc(mb_substr($s['name'], 0, 45));
        }
        if (count($rows) > 25) {
            $html .= "\n… ve " . (count($rows) - 25) . ' servis daha';
        }
        $html .= "\n<i>satış / maliyet. Kartı ve Kapat düğmesi için: /servis ID</i>";
        $this->say($chat, $html);
    }

    // ------------------------------------------------------------------ düğmeler

    /** @return string kısa cevap (callback bildirimi) */
    public function callback(string $data, int|string $chat, int $msgId): string
    {
        try {
            if (preg_match('/^ps:(off|on):(\d+)$/', $data, $m)) { // servis kartındaki tek düğme: hemen
                $status = $m[1] === 'off' ? 'disabled' : 'enabled';
                $r = $this->api->changeStatus([(int)$m[2]], $status);
                $s = $this->api->byIds([(int)$m[2]])[(int)$m[2]] ?? null;
                if ($s) {
                    $btn = $s['status'] === 'enabled' ? ['text' => '⛔ Kapat', 'callback_data' => 'ps:off:' . $s['id']] : ['text' => '✅ Aç', 'callback_data' => 'ps:on:' . $s['id']];
                    $this->tg->editMessage($chat, $msgId, $this->card($s), [[$btn]]);
                }
                return $r['ids'] ? ($status === 'disabled' ? 'Kapatıldı' : 'Açıldı') : 'Panel işlemedi (servis silinmiş olabilir)';
            }
            if (preg_match('/^pk:(show|off|on):(\d+)$/', $data, $m)) {
                $cat = (int)$m[2];
                if ($m[1] === 'show') {
                    $this->categoryCard($chat, $cat);
                    return 'Kategori';
                }
                $status = $m[1] === 'off' ? 'disabled' : 'enabled';
                $list = array_values(array_filter($this->api->services(5000, ['category_id' => $cat]), static fn($s) => $s['status'] !== $status));
                if (!$list) {
                    return 'Değişecek servis yok';
                }
                $this->confirm($chat, array_column($list, 'id'), $status, array_column($list, 'name'), "\n📂 " . Telegram::esc($list[0]['category_name']), $msgId);
                return 'Emin misin?';
            }
            if (preg_match('/^pa:(yes|no):(\d+)$/', $data, $m)) {
                $all = $this->pending();
                $p = $all[(int)$m[2]] ?? null;
                if (!$p) {
                    $this->tg->editMessage($chat, $msgId, '⌛ Bu onayın süresi doldu (15 dk). Komutu yeniden yaz.');
                    return 'Süresi doldu';
                }
                unset($all[(int)$m[2]]);
                $this->db->setJson(self::PENDING, $all);
                if ($m[1] === 'no') {
                    $this->tg->editMessage($chat, $msgId, '↩️ Vazgeçildi, hiçbir servis değişmedi.');
                    return 'Vazgeçildi';
                }
                $r = $this->api->changeStatus($p['ids'], $p['status']);
                $done = count($r['ids']);
                $this->tg->editMessage($chat, $msgId, ($p['status'] === 'disabled' ? '⛔ ' : '✅ ') . '<b>' . $done . ' servis ' . ($p['status'] === 'disabled' ? 'kapatıldı' : 'açıldı') . '</b>'
                    . ($r['ids'] ? "\n" . implode(', ', array_slice($r['ids'], 0, 40)) . (count($r['ids']) > 40 ? ' …' : '') : '')
                    . ($r['skipped'] ? "\n⚠️ Panel işlemedi (silinmiş ya da yok): " . implode(', ', array_slice($r['skipped'], 0, 30)) : ''));
                return $done . ' servis ' . ($p['status'] === 'disabled' ? 'kapatıldı' : 'açıldı');
            }
        } catch (\Throwable $e) {
            $this->db->log('error', 'bot', 'Panel düğmesi: ' . $e->getMessage());
            $this->say($chat, '⚠️ Panel API: ' . Telegram::esc($e->getMessage()));
            return 'Hata';
        }
        return '';
    }
}
