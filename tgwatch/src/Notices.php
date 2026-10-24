<?php
declare(strict_types=1);

namespace TgWatch;

/**
 * Sağlayıcı duyurularından "fiyat değişti" ve "servis kapatıldı" bildirimlerini ayırır.
 * Bunlar yeni servis değildir: panelindeki ilgili servisin fiyatını güncelleme ya da kapatma işidir.
 * Sınıflandırma anahtar kelimelerle yapılır (İngilizce, Türkçe, Azerice); emin olunamayan metin yeni servis olarak işlenmeye devam eder.
 */
final class Notices
{
    public const KINDS = ['price' => 'Fiyat değişti', 'closed' => 'Servis kapatıldı'];
    public const STATUS = ['open' => 'Açık', 'done' => 'Yapıldı', 'ignored' => 'Yoksayıldı'];

    private const NEW = '/\b(?:new\s+services?|newly\s+added|just\s+added|added\s+services?|new\s+arrivals?|yeni\s+(?:servis|xidmət|hizmet)|əlavə\s+edildi|eklendi|yeni\s+gəldi)\b/iu';
    private const CLOSED = '/\b(?:disabled|removed|deleted|discontinued|unavailable|suspended|deactivated|paused|not\s+available|out\s+of\s+(?:service|stock)|temporar(?:il)?y\s+(?:closed|off|down)|closed\s+(?:for|until)|is\s+down|kaldırıldı|silindi|kapatıldı|devre\s+dışı|hizmet\s+dışı|durduruldu|müvəqqəti\s+(?:bağlandı|dayandırıldı)|bağlandı|söndürüldü|deaktiv|xidmət\s+göstərilmir|ləğv\s+edildi|dayandırıldı)\b/iu';
    private const PRICE = '/(?:price|rate|cost|pricing)s?\s+(?:has\s+|have\s+|was\s+|were\s+|is\s+|are\s+)?(?:been\s+)?(?:change[ds]?|updated?|increase[ds]?|decrease[ds]?|reduced?|raised?|drop(?:ped)?|lowered|adjusted|up|down)|(?:price|rate)\s+(?:change|update|drop|increase|decrease)|(?:qiymət|fiyat)\w*\s+(?:dəyiş|değiş|artdı|artı|azal|düş|endir|indir|güncel|yenilən|yeniləndi)\w*|(?:dəyişdi|değişti|artdı|zamlandı|indirildi|endirildi)\b.*(?:qiymət|fiyat)|price\s*[:\-]?\s*\$?\s*[\d.,]+\s*(?:→|->|=>|to)\s*\$?\s*[\d.,]+/iu';

    public function __construct(private Db $db)
    {
    }

    /** @return string|null 'price' | 'closed' | null (yeni servis duyurusu ya da ilgisiz) */
    public static function classify(string $text): ?string
    {
        $t = Normalizer::text($text);
        if (preg_match(self::NEW, $t)) {
            return null; // aynı gönderide yeni servis de duyuruluyorsa iş olarak işlenir
        }
        $closed = (bool)preg_match(self::CLOSED, $t);
        $price = (bool)preg_match(self::PRICE, $t);
        if (!$closed && !$price) {
            return null;
        }
        if (!self::extractIds($t)) {
            return null; // hangi servis olduğu anlaşılmıyorsa bildirim üretme
        }
        return $closed ? 'closed' : 'price';
    }

    /** @return list<int> */
    public static function extractIds(string $text): array
    {
        $t = Normalizer::text($text);
        $ids = [];
        foreach (Parser::parse($t) as $it) {
            $ids[$it['id']] = true;
        }
        if (preg_match_all('/(?:🆔|\bIDs?\b|\bservices?\b|\bservis(?:ler)?\b|\bxidmət(?:lər)?\b|#)\s*[:#№.\-]?\s*((?:\d{2,7}\s*(?:[,;&\/]|\band\b|\bve\b|\bvə\b|\s-\s)?\s*)+)/iu', $t, $m)) {
            foreach ($m[1] as $list) {
                if (preg_match_all('/\d{2,7}/', $list, $n)) {
                    foreach ($n[0] as $id) {
                        $ids[(int)$id] = true;
                    }
                }
            }
        }
        // "3309 servisi", "4455 nömrəli xidmət", "service 12"-dışı: sayı kelimeden önce gelirse
        if (preg_match_all('/(?<![\d.,$])(\d{2,7})\s+(?:nömrəli\s+|numaralı\s+|no\s*:?\s*)?(?:servis\w*|xidmət\w*|services?)\b/iu', $t, $m)) {
            foreach ($m[1] as $id) {
                $ids[(int)$id] = true;
            }
        }
        return array_slice(array_keys($ids), 0, 60);
    }

    /**
     * Duyurudaki yeni fiyatları bulur: "ID 123 ... $1.5" ya da "$1.2 → $1.5" (ikincisi yeni fiyat).
     * @return array<int,array{old:?float,new:?float}>
     */
    public static function extractPrices(string $text): array
    {
        $out = [];
        $cur = null;
        foreach (preg_split('/\R/u', Normalizer::text($text)) ?: [] as $line) {
            $line = trim($line);
            $ids = self::extractIds($line);
            if ($ids) {
                $cur = $ids;
            }
            if (!$cur) {
                continue;
            }
            $p = null;
            if (preg_match('/\$?\s*(\d+(?:[.,]\d+)?)\s*\$?\s*(?:→|->|=>|\bto\b|\bdan\b|\bdən\b|\bna\b)\s*\$?\s*(\d+(?:[.,]\d+)?)/iu', $line, $m)) {
                $p = ['old' => (float)str_replace(',', '.', $m[1]), 'new' => (float)str_replace(',', '.', $m[2])];
            } elseif (preg_match('/(?:\$\s*(\d+(?:[.,]\d+)?)|(\d+(?:[.,]\d+)?)\s*(?:\$|usd\b))/iu', $line, $m)) {
                $v = (float)str_replace(',', '.', $m[1] !== '' ? $m[1] : $m[2]);
                $p = ['old' => null, 'new' => $v];
            }
            if ($p !== null) {
                foreach ($cur as $id) {
                    $out[$id] ??= $p;
                }
                if (!$ids) {
                    $cur = null;
                }
            }
        }
        return $out;
    }

    public function create(string $channel, int $msgId, string $text, string $kind): ?int
    {
        if ($this->db->one('SELECT id FROM notices WHERE channel = ? AND msg_id = ?', [$channel, $msgId])) {
            return null;
        }
        $ids = self::extractIds($text);
        $details = $kind === 'price' ? ['prices' => self::extractPrices($text)] : [];
        $now = time();
        $this->db->run('INSERT INTO notices(channel, msg_id, kind, ids_json, details_json, text, status, created_at, updated_at) VALUES(?,?,?,?,?,?,?,?,?)',
            [$channel, $msgId, $kind, json_encode($ids), json_encode($details, JSON_UNESCAPED_UNICODE), $text, 'open', $now, $now]);
        $id = (int)$this->db->pdo->lastInsertId();
        $this->db->log('info', 'notice', "Bildirim #$id (" . (self::KINDS[$kind] ?? $kind) . "): @$channel/$msgId, " . count($ids) . ' servis');
        return $id;
    }

    /** Son $days gün içinde aynı türde, servisleri (en az bir ID) kapsayan bildirim varsa numarası. */
    public function findDuplicate(string $kind, array $ids, int $days): ?int
    {
        if ($days <= 0 || !$ids) {
            return null;
        }
        foreach ($this->db->all('SELECT id, ids_json FROM notices WHERE kind = ? AND created_at >= ? ORDER BY id DESC', [$kind, time() - $days * 86400]) as $n) {
            $old = array_map('intval', json_decode((string)$n['ids_json'], true) ?: []);
            if (!array_diff(array_map('intval', $ids), $old)) {
                return (int)$n['id'];
            }
        }
        return null;
    }

    public function addSource(int $id, string $channel, int $msgId): void
    {
        $n = $this->db->one('SELECT channel, msg_id, also_json FROM notices WHERE id = ?', [$id]);
        if (!$n) {
            return;
        }
        $also = json_decode((string)$n['also_json'], true) ?: [];
        foreach ($also['sources'] ?? [] as $s) {
            if ($s['channel'] === $channel && (int)$s['msg'] === $msgId) {
                return;
            }
        }
        $also['sources'][] = ['channel' => $channel, 'msg' => $msgId, 'at' => time()];
        $this->db->run('UPDATE notices SET also_json = ? WHERE id = ?', [json_encode($also), $id]);
    }

    public function get(int $id): ?array
    {
        $n = $this->db->one('SELECT * FROM notices WHERE id = ?', [$id]);
        if ($n !== null) {
            $n['ids'] = json_decode($n['ids_json'], true) ?: [];
            $n['details'] = json_decode($n['details_json'], true) ?: [];
        }
        return $n;
    }

    public function setStatus(int $id, string $status): bool
    {
        return $this->db->run("UPDATE notices SET status = ?, updated_at = ? WHERE id = ? AND status = 'open'", [$status, time(), $id]) === 1;
    }

    public function openCount(): int
    {
        return (int)$this->db->one("SELECT COUNT(*) c FROM notices WHERE status = 'open'")['c'];
    }

    /**
     * Her servis için panelinde ne yapılacağı.
     * @return list<array{id:int, own:?array, provider:?array, old:?float, new:?float, current:?float, suggest:?float, action:string}>
     */
    public function impact(array $n): array
    {
        $en = new Enrich($this->db);
        $src = $en->sourceFor($n['channel']);
        $out = [];
        foreach ($n['ids'] as $id) {
            $id = (int)$id;
            $own = $src ? $en->own((int)$src['id'], $id) : null;
            $prov = $src ? $en->remote((int)$src['id'], $id) : null;
            $pr = $n['details']['prices'][$id] ?? null;
            $new = $pr['new'] ?? null;
            $old = $pr['old'] ?? ($prov['rate'] !== null && $prov ? (float)$prov['rate'] : null);
            $current = $own && ($own['rate'] ?? null) !== null ? (float)$own['rate'] : null;
            $suggest = $new !== null ? $en->suggestPrice((float)$new) : null;
            if ($own === null) {
                $action = $src ? 'Panelinde bu servisle eşleşen bir servis bulunamadı. Gerekirse elle kontrol edin.' : 'Kanal için sağlayıcı seçilmemiş, eşleşme yapılamadı.';
            } elseif ($n['kind'] === 'closed') {
                $action = 'Panelindeki #' . $own['service_id'] . ' servisini gizle ya da kapat.';
            } else {
                $action = $suggest !== null
                    ? 'Panelindeki #' . $own['service_id'] . ' servisinin fiyatını ' . ($current !== null ? number_format($current, 2) . ' yerine ' : '') . number_format($suggest, 2) . ' yap.'
                    : 'Panelindeki #' . $own['service_id'] . ' servisinin fiyatını güncelle (yeni fiyat duyuruda yok).';
            }
            $out[] = ['id' => $id, 'own' => $own, 'provider' => $prov, 'old' => $old, 'new' => $new, 'current' => $current, 'suggest' => $suggest, 'action' => $action];
        }
        return $out;
    }
}
