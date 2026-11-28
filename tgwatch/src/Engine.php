<?php
declare(strict_types=1);

namespace TgWatch;

/**
 * Çeviri motoru: önce çeviri bankası, sonra kurallar.
 *   1) Tam ad bankada varsa → bankadaki ad ("bank")
 *   2) Yoksa her " | " parçası: bankadaki parça/şablon → yoksa kurallar ("mixed" / "rules")
 * Ad kalıbı seçenekleri (Maks/Gündə) ve silinecek ifadeler her sonuca uygulanır.
 */
final class Engine
{
    public const VIA_LABELS = ['bank' => 'Bankadan', 'mixed' => 'Kısmen bankadan', 'rules' => 'Kurallarla', 'claude' => 'Claude', 'manual' => 'Elle'];

    private Translator $rules;
    private Memory $mem;
    private array $keep = [];

    public function __construct(Db $db, private array $dict)
    {
        // Ad kalıbı parça başına değil, adın tamamına bu sınıfta uygulanır; Translator'a kalıp verilmez.
        $this->rules = new Translator(array_merge($dict, ['options' => []]));
        $this->mem = new Memory($db);
        foreach ($dict['keep'] ?? [] as $w) {
            $this->keep[mb_strtolower((string)$w)] = true;
        }
    }

    public function memory(): Memory
    {
        return $this->mem;
    }

    /** Silinecek ifadeleri addan çıkarır. Düz metin büyük/küçük harf duyarsız, /…/ biçimi regex. */
    public function strip(string $name): string
    {
        return self::stripBy($this->dict['remove'] ?? [], $name);
    }

    /**
     * Anlamı olumsuzluk kelimesinden gelen ifadeler: silme kuralı bunların yalnızca bir parçasını (ör. "No") kesemez.
     * Yoksa "No Refill" → "Refill" → "Qarantili" olur, yani anlam tersine döner.
     */
    private const PROTECTED = [
        '/(?<![\p{L}\p{N}])(?:non|no)[\s\-]*(?:refill|refil|guaranteed?|drop|cancel)(?![\p{L}\p{N}])/iu',
        '/(?<![\p{L}\p{N}])without[\s\-]+(?:refill|guarantee)(?![\p{L}\p{N}])/iu',
    ];

    /** Tek bir silme kuralını uygular. Düz metin yalnızca tam kelime/ifade olarak silinir ("no", "Normal"in içinden silinmez). */
    private static function applyRemove(string $r, string $s): string
    {
        if (strlen($r) > 2 && $r[0] === '/' && strrpos($r, '/') > 0) {
            return @preg_replace($r . 'u', '', $s) ?? $s;
        }
        $q = preg_quote($r, '/');
        $pre = preg_match('/^[\p{L}\p{N}]/u', $r) ? '(?<![\p{L}\p{N}])' : '';
        $post = preg_match('/[\p{L}\p{N}]$/u', $r) ? '(?![\p{L}\p{N}])' : '';
        return @preg_replace('/' . $pre . $q . $post . '/iu', '', $s) ?? str_ireplace($r, '', $s);
    }

    public static function stripBy(array $remove, string $name): string
    {
        $s = Normalizer::text($name);
        // Korunan ifadeleri yer tutucuyla sakla; ifadenin TAMAMINI silen kural (ör. elle eklenmiş "No Refill") yine çalışır
        $kept = [];
        foreach (self::PROTECTED as $p) {
            $s = preg_replace_callback($p, static function (array $m) use (&$kept, $remove): string {
                foreach ($remove as $r) {
                    $r = (string)$r;
                    if ($r !== '' && trim(self::applyRemove($r, $m[0])) === '') {
                        return $m[0];   // bu kural ifadeni bütünüyle silmek istiyor
                    }
                }
                $kept[] = $m[0];
                return "\u{E000}" . str_repeat("\u{E002}", count($kept) - 1) . "\u{E001}";   // rakamsız: /\d/ kuralı dokunmasın
            }, $s) ?? $s;
        }
        foreach ($remove as $r) {
            $r = (string)$r;
            if ($r === '') {
                continue;
            }
            $s = self::applyRemove($r, $s);
        }
        if ($kept) {
            $s = preg_replace_callback("/\u{E000}(\u{E002}*)\u{E001}/u", static fn(array $m): string => $kept[mb_strlen($m[1])] ?? '', $s) ?? $s;
        }
        $s = preg_replace('/(\s*\|\s*){2,}/u', ' | ', $s) ?? $s;
        $s = preg_replace('/\s{2,}/u', ' ', $s) ?? $s;
        return trim($s, " \t|");
    }

    /** @return array{text:string, unknown:list<string>, via:string} */
    public function translate(string $name): array
    {
        $clean = $this->strip($name);
        $exact = $this->mem->lookup('service', $clean);
        if ($exact !== null) {
            return ['text' => NameTemplate::apply(Memory::splitSegments($exact), $this->dict['options'] ?? []), 'unknown' => [], 'via' => 'bank'];
        }
        $out = [];
        $unknown = [];
        $bank = 0;
        $segs = Memory::splitSegments($clean);
        foreach ($segs as $seg) {
            $t = $this->mem->lookupSegment($seg, $this->keep);
            if ($t !== null) {
                $bank++;
            } else {
                $r = $this->rules->translate($seg);
                $t = $r['text'];
                $unknown = array_merge($unknown, $r['unknown']);
            }
            $out[] = $t;
        }
        $via = $segs && $bank === count($segs) ? 'bank' : ($bank > 0 ? 'mixed' : 'rules');
        return ['text' => NameTemplate::apply($out, $this->dict['options'] ?? []), 'unknown' => array_values(array_unique($unknown)), 'via' => $via];
    }

    public function translateCategory(string $cat): string
    {
        $clean = $this->strip($cat);
        return $this->mem->lookup('category', $clean) ?? $this->rules->translate($clean)['text'];
    }

    /** @param list<array{id:int,name:string,price:string,category:string}> $items */
    public function translateItems(array $items): array
    {
        foreach ($items as &$it) {
            $r = $this->translate($it['name']);
            $it['az'] = $r['text'];
            $it['unknown'] = $r['unknown'];
            $it['via'] = $r['via'];
            $it['edited'] = false;
            if (($it['category'] ?? '') !== '') {
                $it['category_az'] = $this->translateCategory($it['category']);
            }
        }
        return $items;
    }
}
