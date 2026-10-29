<?php
declare(strict_types=1);

namespace TgWatch;

/**
 * Duyuru metninden servisleri çıkarır.
 * Desteklenen satırlar (örnekler):
 *   🆔 13125 Threads Followers | No Drop | 30 Days Refill
 *   🆔 13125 - Threads Followers ...
 *   ID: 13125 | Threads Followers
 *   13125 - Threads Followers           (sayıdan sonra ayraç şart)
 *   Threads Followers - ID: 13125       (ID sonda)
 * ID satırından sonraki satırlarda ($ / Price / Rate) fiyat aranır.
 */
final class Parser
{
    private const ID_FIRST = '/^[^\p{L}\p{N}]*?(?:(?<mark>🆔|\bID\b|\bService\s*ID\b)\s*[:#№.\-]?\s*)?(?<id>\d{2,7})\s*(?<sep>[-–—:|•»>)\].]+)?\s*(?<name>.*)$/iu';
    private const ID_LAST = '/^(?<name>.+?)\s*[-–—|:•(\[]*\s*(?:🆔|\bID\b)\s*[:#№]?\s*(?<id>\d{2,7})\s*[)\]]?\s*$/iu';
    /** Satır sonunda "-- 8.50", "— 12,75 AZN", "- 3.20₼" gibi fiyat ("--" ile her sayı, tek tire ile ondalıklı sayı). */
    private const CUR = '\$|usd\b|azn\b|₼|manat\b|man\b|rub\b|руб\.?|₽|₺|tl\b|try\b|eur\b|€';
    private const TRAILING_PRICE = '/\s+(?:--+\s*(?<a>\d+(?:[.,]\d+)?)|[-–—=]\s*(?<b>\d+[.,]\d+))\s*(?<cu>' . self::CUR . ')?\s*$/iu';
    private const PRICE = '/(?:(?:price|rate|cost|qiymət|fiyat|цена)\s*[:=\-]?\s*)?(?:(?<cs>[$₽₺₼€])\s*(?<a>\d+(?:[.,]\d+)?)|(?<b>\d+(?:[.,]\d+)?)\s*(?<cu>' . self::CUR . '))/iu';

    /**
     * @return list<array{id:int, name:string, price:string, category:string}>
     */
    public static function parse(string $raw): array
    {
        $text = Normalizer::text($raw);
        $lines = array_map('trim', explode("\n", $text));
        $items = [];
        $category = '';
        $pendingHeader = '';
        $current = null;

        foreach ($lines as $line) {
            if ($line === '') {
                $current = null;
                continue;
            }
            $hit = self::matchId($line);
            if ($hit !== null) {
                if ($pendingHeader !== '') {
                    $category = $pendingHeader;
                    $pendingHeader = '';
                }
                [$id, $name] = $hit;
                $price = '';
                $cur = 'USD';
                if (preg_match(self::TRAILING_PRICE, $name, $pm)) {
                    $price = self::priceOf($pm);
                    $cur = self::currencyOf($pm);
                    $name = (string)preg_replace(self::TRAILING_PRICE, '', $name);
                } elseif (preg_match(self::PRICE, $name, $pm)) {
                    $price = self::priceOf($pm);
                    $cur = self::currencyOf($pm);
                    $name = str_replace($pm[0], '', $name);
                    $name = preg_replace('/\s*\|\s*(?=\||$)/u', '', $name) ?? $name; // boş kalan "| " parçası
                }
                $items[] = ['id' => $id, 'name' => self::cleanName($name), 'price' => $price, 'category' => $category] + ($cur !== 'USD' && $price !== '' ? ['currency' => $cur] : []);
                $current = count($items) - 1;
                continue;
            }
            if ($current !== null && $items[$current]['price'] === '' && preg_match(self::PRICE, $line, $pm)) {
                $items[$current]['price'] = self::priceOf($pm);
                if (($c = self::currencyOf($pm)) !== 'USD') {
                    $items[$current]['currency'] = $c;
                }
                continue;
            }
            if ($current !== null && $items[$current]['name'] === '' && preg_match('/\p{L}{2,}/u', $line)) {
                $items[$current]['name'] = self::cleanName($line); // ID tek başına satırdaysa ad alttadır
                continue;
            }
            // Fiyat içermeyen kısa satır: muhtemelen kategori başlığı.
            if (mb_strlen($line) <= 80 && !preg_match(self::PRICE, $line) && preg_match('/\p{L}{3,}/u', $line)) {
                $pendingHeader = self::cleanName($line);
            }
        }

        return array_values(array_filter($items, static fn($i) => preg_match('/\p{L}{2,}/u', $i['name']) === 1));
    }

    /** @return array{0:int,1:string}|null */
    private static function matchId(string $line): ?array
    {
        if (preg_match(self::ID_LAST, $line, $m) && preg_match('/\p{L}{2,}/u', $m['name'])) {
            return [(int)$m['id'], $m['name']];
        }
        if (!preg_match(self::ID_FIRST, $line, $m)) {
            return null;
        }
        $hasMark = ($m['mark'] ?? '') !== '';
        $hasSep = ($m['sep'] ?? '') !== '';
        $name = trim($m['name'] ?? '');
        // "30 Days Refill" gibi sayıyla başlayan sıradan satırları ID sanmamak için
        // işaret (🆔/ID) ya da sayıdan hemen sonra ayraç şart.
        if (!$hasMark && !$hasSep) {
            return null;
        }
        if (!$hasMark && $hasSep && preg_match('/^[.)]$/', trim($m['sep'])) && (int)$m['id'] < 100) {
            return null; // "1. ..." / "2) ..." numaralı liste
        }
        if (!$hasMark && ($name === '' || preg_match('/^\d/', $name))) {
            return null; // "24-48 Hours", "0-1 Hour start" gibi aralıklar
        }
        return [(int)$m['id'], $name];
    }

    /** Fiyatın para birimi: USD (varsayılan), RUB, TRY, AZN, EUR. */
    public static function currencyOf(array $pm): string
    {
        $c = mb_strtolower(trim(($pm['cs'] ?? '') !== '' ? $pm['cs'] : ($pm['cu'] ?? ''), ' .'));
        return match (true) {
            in_array($c, ['₽', 'rub', 'руб'], true) => 'RUB',
            in_array($c, ['₺', 'tl', 'try'], true) => 'TRY',
            in_array($c, ['₼', 'azn', 'man', 'manat'], true) => 'AZN',
            in_array($c, ['€', 'eur'], true) => 'EUR',
            default => 'USD',
        };
    }

    private static function priceOf(array $pm): string
    {
        $v = ($pm['a'] ?? '') !== '' ? $pm['a'] : ($pm['b'] ?? '');
        return str_replace(',', '.', $v);
    }

    public static function cleanName(string $s): string
    {
        $s = preg_replace('/\s+/u', ' ', $s) ?? $s;
        // Çok baytlı karakterler trim() listesinde bayt bayt işlenir ve 🚀 gibi emojileri bozar; bu yüzden regex.
        $s = preg_replace('/^[\s\-–—:|•»]+|[\s\-–—:|•»]+$/u', '', $s) ?? $s;
        return trim($s);
    }
}
