<?php
declare(strict_types=1);

namespace TgWatch;

/** Kural ve sözlük tabanlı İngilizce → Azerice servis adı çevirisi. */
final class Translator
{
    private array $rules;
    private array $phrases;
    private array $keep;
    private array $options;
    private ?string $phraseRegex = null;

    public function __construct(array $dict)
    {
        $this->rules = $dict['rules'] ?? [];
        $this->phrases = [];
        foreach ($dict['phrases'] ?? [] as $en => $az) {
            $this->phrases[mb_strtolower((string)$en)] = (string)$az;
        }
        $this->keep = [];
        foreach ($dict['keep'] ?? [] as $w) {
            $lw = mb_strtolower((string)$w);
            $this->keep[$lw] = true;
            // Marka adları sözlükteki yazımıyla düzeltilir: "instagram" → "Instagram" (tek harfliler hariç).
            if (mb_strlen($lw) > 1 && !isset($this->phrases[$lw])) {
                $this->phrases[$lw] = (string)$w;
            }
        }
        $this->options = $dict['options'] ?? [];
    }

    public static function ruleRegex(string $pattern): string
    {
        return '~(?<![\p{L}\p{N}])(?:' . str_replace('~', '\~', $pattern) . ')(?![\p{L}])~iu';
    }

    /**
     * @return array{text:string, unknown:list<string>}
     */
    public function translate(string $name): array
    {
        $name = Normalizer::text($name);
        $segments = preg_split('/\s*\|\s*/u', trim($name)) ?: [];
        $out = [];
        $unknown = [];
        foreach ($segments as $seg) {
            if ($seg === '') {
                continue;
            }
            [$t, $u] = $this->segment($seg);
            $unknown = array_merge($unknown, $u);
            if (empty($this->options['keep_max']) && preg_match('/^Maks\b/u', $t)) {
                continue;
            }
            if (empty($this->options['keep_speed']) && preg_match('/^(Gündə|Saatda)\b/u', $t)) {
                continue;
            }
            $out[] = $t;
        }
        return ['text' => implode(' | ', $out), 'unknown' => array_values(array_unique($unknown))];
    }

    /** @return array{0:string,1:list<string>} */
    private function segment(string $s): array
    {
        $slots = [];
        $hold = static function (string $value) use (&$slots): string {
            $slots[] = $value;
            return "\u{E000}" . (count($slots) - 1) . "\u{E001}";
        };

        foreach ($this->rules as [$pattern, $replace]) {
            $re = self::ruleRegex($pattern);
            $s = preg_replace_callback($re, static function (array $m) use ($re, $replace, $hold): string {
                return $hold((string)preg_replace($re, $replace, $m[0]));
            }, $s) ?? $s;
        }

        if ($this->phrases) {
            $s = preg_replace_callback($this->phraseRegex(), function (array $m) use ($hold): string {
                return $hold($this->phrases[mb_strtolower($m[0])] ?? $m[0]);
            }, $s) ?? $s;
        }

        // Çevrilmemiş Latin kelimeleri uyarı olarak topla (sayıya yapışık 50K gibi birimler hariç).
        $unknown = [];
        if (preg_match_all('/(?<![\p{L}\p{N}\x{E000}])[A-Za-z][A-Za-z\'’-]*[A-Za-z]/u', $s, $mm)) {
            foreach ($mm[0] as $w) {
                if (!isset($this->keep[mb_strtolower($w)])) {
                    $unknown[] = $w;
                }
            }
        }

        $s = preg_replace_callback('/\x{E000}(\d+)\x{E001}/u', static fn($m) => $slots[(int)$m[1]], $s) ?? $s;
        $s = preg_replace('/\s+/u', ' ', $s) ?? $s;
        return [trim($s), $unknown];
    }

    private function phraseRegex(): string
    {
        if ($this->phraseRegex === null) {
            $keys = array_keys($this->phrases);
            usort($keys, static fn($a, $b) => mb_strlen($b) <=> mb_strlen($a));
            $alts = array_map(static function (string $k): string {
                return str_replace(' ', '\s+', preg_quote($k, '~'));
            }, $keys);
            $this->phraseRegex = '~(?<![\p{L}\p{N}])(?:' . implode('|', $alts) . ')(?![\p{L}\p{N}])~iu';
        }
        return $this->phraseRegex;
    }

    /**
     * Bir duyurudaki tüm servisleri çevirir.
     * @param list<array{id:int,name:string,price:string,category:string}> $items
     */
    public function translateItems(array $items): array
    {
        foreach ($items as &$it) {
            $r = $this->translate($it['name']);
            $it['az'] = $r['text'];
            $it['unknown'] = $r['unknown'];
            $it['edited'] = false;
            if (($it['category'] ?? '') !== '') {
                $it['category_az'] = $this->translate($it['category'])['text'];
            }
        }
        return $items;
    }
}
