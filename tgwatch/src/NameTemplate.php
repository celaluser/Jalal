<?php
declare(strict_types=1);

namespace TgWatch;

/**
 * Ad kalıbı: çevrilmiş servis adının parçalarını (" | " ile ayrılmış) seçilen biçime sokar.
 *   - hangi parçalar adda kalsın (Maks, Min, hız, garanti, başlama, iptal, kalite)
 *   - parça sırası ("kaynak sırası" ya da "derli toplu": ana ad, kalite, garanti, … Min, Maks, hız)
 *   - ayraç, emoji, ön/son ek ve en çok uzunluk (sığmazsa önemsiz parçalar sırayla atılır)
 */
final class NameTemplate
{
    public const SEPARATORS = [' | ' => 'Dikey çizgi:  A | B', ' - ' => 'Tire:  A - B', ' • ' => 'Nokta:  A • B', ' / ' => 'Eğik çizgi:  A / B', ' · ' => 'Orta nokta:  A · B', ', ' => 'Virgül:  A, B'];
    public const ORDERS = ['source' => 'Kaynaktaki sıra', 'tidy' => 'Derli toplu (ana ad, kalite, garanti, …, Min, Maks, hız)'];
    public const EMOJI = ['keep' => 'Emojiler kalsın', 'remove' => 'Emojileri sil'];

    /** Parça türleri: ad => [etiket, seçenek anahtarı]. */
    public const TYPES = [
        'quality' => ['Kalite (Azalma olmaz, Real, HQ, Sürətli…)', 'keep_quality'],
        'refill' => ['Garanti (30 Gün Qarantili, Qarantisiz…)', 'keep_refill'],
        'start' => ['Başlama süresi', 'keep_start'],
        'cancel' => ['İptal (Ləğv aktivdir)', 'keep_cancel'],
        'min' => ['En az (Min 100)', 'keep_min'],
        'max' => ['En çok (Maks 50K)', 'keep_max'],
        'speed' => ['Hız (Gündə 5K, Saatda 1K)', 'keep_speed'],
    ];

    /** Sığmazsa atılma sırası (ana ad hiç atılmaz). */
    private const DROP_ORDER = ['start', 'cancel', 'speed', 'other', 'min', 'max', 'refill', 'quality'];
    private const TIDY_ORDER = ['quality' => 1, 'refill' => 2, 'start' => 3, 'cancel' => 4, 'other' => 5, 'min' => 6, 'max' => 7, 'speed' => 8];

    public static function defaults(): array
    {
        return ['keep_max' => true, 'keep_speed' => true, 'keep_min' => true, 'keep_refill' => true, 'keep_start' => true, 'keep_cancel' => true, 'keep_quality' => true,
            'separator' => ' | ', 'order' => 'source', 'emoji' => 'keep', 'prefix' => '', 'suffix' => '', 'max_len' => 0];
    }

    /** Panelden/DB'den gelen seçenekleri doğrular ve eksikleri varsayılanla doldurur. */
    public static function normalize(array $o): array
    {
        $d = self::defaults();
        foreach (self::TYPES as [, $key]) {
            $d[$key] = array_key_exists($key, $o) ? !empty($o[$key]) : $d[$key];
        }
        $d['separator'] = isset(self::SEPARATORS[$o['separator'] ?? '']) ? (string)$o['separator'] : $d['separator'];
        $d['order'] = isset(self::ORDERS[$o['order'] ?? '']) ? (string)$o['order'] : $d['order'];
        $d['emoji'] = isset(self::EMOJI[$o['emoji'] ?? '']) ? (string)$o['emoji'] : $d['emoji'];
        $d['prefix'] = mb_substr(preg_replace('/[\r\n|]+/u', ' ', (string)($o['prefix'] ?? '')) ?? '', 0, 20);
        $d['suffix'] = mb_substr(preg_replace('/[\r\n|]+/u', ' ', (string)($o['suffix'] ?? '')) ?? '', 0, 20);
        $d['max_len'] = max(0, min(255, (int)($o['max_len'] ?? 0)));
        return $d;
    }

    /** Parçanın türü (Azerice çıktıya ve çevrilmemiş İngilizce kalıplara göre). */
    public static function classify(string $seg, bool $first): string
    {
        if ($first) {
            return 'main';
        }
        return match (true) {
            (bool)preg_match('/^(Maks|Max)\b/iu', $seg) => 'max',
            (bool)preg_match('/^Min(imum)?\b/iu', $seg) => 'min',
            (bool)preg_match('/^(Gündə|Saatda|Dəqiqədə)\b|\/\s*(Day|Hour)\b/iu', $seg) => 'speed',
            (bool)preg_match('/Qarantil|Qarantisiz|Garantiya|Refill|Guarantee/iu', $seg) => 'refill',
            (bool)preg_match('/^(Start|Başlama|Dərhal Başlayır|Başlayır)\b/iu', $seg) => 'start',
            (bool)preg_match('/Ləğv|Cancel/iu', $seg) => 'cancel',
            (bool)preg_match('/^(Azalma olmaz|Az azalma|Real|HQ|Premium|VIP|Sürətli|Yavaş|Keyfiyyətli)\b/iu', $seg) => 'quality',
            default => 'other',
        };
    }

    public static function stripEmoji(string $s): string
    {
        $s = preg_replace('/[\x{1F000}-\x{1FAFF}\x{2190}-\x{21FF}\x{2300}-\x{23FF}\x{25A0}-\x{25FF}\x{2600}-\x{27BF}\x{2900}-\x{297F}\x{2B00}-\x{2BFF}\x{FE0F}\x{200D}\x{20E3}]/u', '', $s) ?? $s;
        return trim(preg_replace('/\s{2,}/u', ' ', $s) ?? $s);
    }

    /**
     * @param list<string> $segments çevrilmiş parçalar
     */
    public static function apply(array $segments, array $options): string
    {
        $o = self::normalize($options);
        $items = [];
        foreach (array_values($segments) as $i => $seg) {
            $seg = trim((string)$seg);
            if ($o['emoji'] === 'remove') {
                $seg = self::stripEmoji($seg);
            }
            if ($seg === '') {
                continue;
            }
            $type = self::classify($seg, $i === 0);
            if (isset(self::TYPES[$type]) && !$o[self::TYPES[$type][1]]) {
                continue;
            }
            $items[] = ['t' => $type, 's' => $seg, 'i' => $i];
        }
        if ($o['order'] === 'tidy') {
            usort($items, static fn($a, $b) => [$a['t'] === 'main' ? 0 : self::TIDY_ORDER[$a['t']] ?? 5, $a['i']] <=> [$b['t'] === 'main' ? 0 : self::TIDY_ORDER[$b['t']] ?? 5, $b['i']]);
        }
        $glue = $o['separator'];
        $room = $o['max_len'] > 0 ? $o['max_len'] - mb_strlen($o['prefix'] . $o['suffix']) : 0;
        if ($room > 0) {
            foreach (self::DROP_ORDER as $drop) {
                if (mb_strlen(implode($glue, array_column($items, 's'))) <= $room) {
                    break;
                }
                $items = array_values(array_filter($items, static fn($x) => $x['t'] !== $drop));
            }
        }
        $text = implode($glue, array_column($items, 's'));
        if ($room > 0 && mb_strlen($text) > $room) {
            $text = rtrim(mb_substr($text, 0, max(1, $room - 1))) . '…';
        }
        return trim($o['prefix'] . ($o['prefix'] !== '' ? ' ' : '') . $text . ($o['suffix'] !== '' ? ' ' : '') . $o['suffix']);
    }
}
