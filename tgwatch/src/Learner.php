<?php
declare(strict_types=1);

namespace TgWatch;

/**
 * Öğrenme laboratuvarı: bankadaki (sağlayıcı adı EN ↔ senin adın AZ) çiftlerden çeviri mantığını çıkarır.
 *
 * Her çiftte parçalar (" | ") hizalanır ve her parça için ne yaptığın belirlenir:
 *   same  = mevcut kurallar zaten senin sonucunu veriyor
 *   tr    = farklı çevirmişsin (sözlük terimi / kural adayı)
 *   drop  = parçayı silmişsin
 *   add   = EN'de olmayan parça eklemişsin
 * Tutarlı kararlar (en az MIN_N çift, en az MIN_SHARE oranında) öneri olur. Hiçbir öneri onaysız uygulanmaz.
 * Doğruluk ölçümü: çiftlerin ~%20'si saklanır, öneriler kalan %80'den üretilir, saklananlar çevrilip senin adınla karşılaştırılır.
 */
final class Learner
{
    public const MIN_N = 5;
    public const MIN_PHRASE_N = 3;
    public const MIN_SHARE = 0.8;
    public const MAX_PAIRS = 6000;
    /** Öğrenmeye alınan kaynaklar: senin kararların. ("approved" botun kendi çıktısı olabilir, o yüzden yok.) */
    public const ORIGINS = ['import', 'confirmed', 'manual'];

    private const EMOJI = '/[\x{1F000}-\x{1FAFF}\x{2190}-\x{21FF}\x{2300}-\x{23FF}\x{25A0}-\x{25FF}\x{2600}-\x{27BF}\x{2B00}-\x{2BFF}]/u';

    public function __construct(private Db $db)
    {
    }

    /** @return list<array{en:string,az:string}> */
    public function pairs(): array
    {
        $in = implode(',', array_fill(0, count(self::ORIGINS), '?'));
        $rows = $this->db->all("SELECT en, az FROM tm WHERE kind = 'service' AND status = 'ok' AND origin IN ($in) ORDER BY id DESC LIMIT " . self::MAX_PAIRS, self::ORIGINS);
        return array_values(array_filter($rows, static fn($r) => trim((string)$r['en']) !== '' && trim((string)$r['az']) !== ''));
    }

    // ------------------------------------------------------------------ çeviri benzetimi (banka olmadan)

    /** Sözlük + kurallarla, bankaya bakmadan tam ad çevirisi (ölçüm için). */
    public static function simulate(array $dict, string $en, ?Translator $tr = null): string
    {
        $tr ??= new Translator(array_merge($dict, ['options' => []]));
        $out = [];
        foreach (Memory::splitSegments(Engine::stripBy($dict['remove'] ?? [], $en)) as $seg) {
            $out[] = $tr->translate($seg)['text'];
        }
        return NameTemplate::apply($out, $dict['options'] ?? []);
    }

    public static function same(string $a, string $b): bool
    {
        $n = static fn(string $s): string => mb_strtolower(trim(preg_replace('/\s+/u', ' ', preg_replace('/\s*\|\s*/u', ' | ', Normalizer::text($s)) ?? $s) ?? $s));
        return $n($a) === $n($b);
    }

    // ------------------------------------------------------------------ hizalama

    private static function words(string $s): array
    {
        return array_values(array_unique(array_filter(preg_split('/[^\p{L}\p{N}]+/u', mb_strtolower($s)) ?: [])));
    }

    private static function jac(array $a, array $b): float
    {
        if (!$a || !$b) {
            return 0.0;
        }
        return count(array_intersect($a, $b)) / count(array_unique(array_merge($a, $b)));
    }

    private static function emojis(string $s): array
    {
        preg_match_all(self::EMOJI, $s, $m);
        $e = array_values(array_unique($m[0]));
        sort($e);
        return $e;
    }

    private static function score(string $e, string $te, string $a): float
    {
        $t = mb_strtolower($te);
        $x = mb_strtolower($a);
        if ($t === $x) {
            return 1.0;
        }
        $base = max(self::jac(self::words($t), self::words($x)), 0.9 * self::jac(self::words($e), self::words($x)));
        similar_text($t, $x, $pct);
        $base = max($base, 0.8 * $pct / 100);
        $ne = Matcher::nums($e);
        $na = Matcher::nums($a);
        if ($ne && $na) {
            $base = 0.65 * $base + 0.35 * self::jac($ne, $na);
        } elseif ($ne xor $na) {
            $base *= 0.6;
        }
        $ee = self::emojis($e);
        if ($ee && $ee === self::emojis($a) && !preg_match('/\p{L}/u', preg_replace(self::EMOJI, '', $e) ?? '')) {
            $base = max($base, 0.9); // sadece emojiden oluşan parça
        }
        $tt = NameTemplate::classify($te, false);
        if ($tt !== 'other' && $tt === NameTemplate::classify($a, false)) {
            $base += 0.35;
        }
        return min(1.0, $base);
    }

    /**
     * EN ve AZ parçalarını eşler.
     * @param list<string> $E
     * @param list<string> $A
     * @param list<string> $TE E'nin mevcut kurallarla çevirisi
     * @return array{map:array<int,int>,drop:list<int>,add:list<int>}
     */
    public static function align(array $E, array $A, array $TE): array
    {
        $cand = [];
        foreach ($E as $i => $e) {
            foreach ($A as $j => $a) {
                $s = self::score($e, $TE[$i], $a);
                if ($s >= 0.4) {
                    $cand[] = [$s, -abs($i - $j), $i, $j];
                }
            }
        }
        usort($cand, static fn($x, $y) => [$y[0], $y[1]] <=> [$x[0], $x[1]]);
        $map = [];
        $usedA = [];
        foreach ($cand as [, , $i, $j]) {
            if (!isset($map[$i]) && !isset($usedA[$j])) {
                $map[$i] = $j;
                $usedA[$j] = true;
            }
        }
        // Kalanlar: ana ad (ilk parçalar) ve sayıca eşit kalanlar sırayla eşlenir (yeni çeviri terimi sayılır).
        $freeE = array_values(array_diff(array_keys($E), array_keys($map)));
        $freeA = array_values(array_diff(array_keys($A), array_keys($usedA)));
        if ($freeE && $freeA && ($freeE[0] === 0 && $freeA[0] === 0)) {
            $map[0] = 0;
            array_shift($freeE);
            array_shift($freeA);
        }
        if ($freeE && count($freeE) === count($freeA)) {
            foreach ($freeE as $k => $i) {
                $map[$i] = $freeA[$k];
            }
            $freeE = $freeA = [];
        }
        ksort($map);
        return ['map' => $map, 'drop' => $freeE, 'add' => $freeA];
    }

    // ------------------------------------------------------------------ analiz

    /**
     * @param list<array{en:string,az:string}> $pairs
     * @return array{stats:array,suggestions:list<array>,pairs:int}
     */
    public function analyze(array $pairs, array $dict): array
    {
        $tr = new Translator(array_merge($dict, ['options' => []]));
        $opt = NameTemplate::normalize($dict['options'] ?? []);
        $keep = array_map('mb_strtolower', array_map('strval', $dict['keep'] ?? []));
        $types = []; // tür => [n, drop]
        $segSeen = []; // anahtar => [kept, dropped, ex]
        $trans = []; // en şablonu => [az şablonu => [n, ex]]
        $added = []; // az şablonu => n
        $emoPairs = 0;
        $emoLost = 0;
        $ord = ['n' => 0, 'tidy' => 0, 'source' => 0];
        $same = 0;
        $segTotal = 0;

        [$sep, $pre, $suf, $sepF, $preF, $sufF] = self::detectFormat($pairs, $dict);
        foreach ($pairs as &$p) {
            $p['az'] = self::unwrap($p['az'], $pre, $suf, $opt);
        }
        unset($p);
        foreach ($pairs as $p) {
            $clean = Engine::stripBy($dict['remove'] ?? [], $p['en']);
            $E = Memory::splitSegments($clean);
            $A = self::splitBy($p['az'], $sep);
            if (!$E || !$A) {
                continue;
            }
            $TE = array_map(static fn($s) => $tr->translate($s)['text'], $E);
            $al = self::align($E, $A, $TE);
            $ex = $p['en'] . '  →  ' . $p['az'];

            if (self::emojis($p['en'])) {
                $emoPairs++;
                if (!self::emojis($p['az'])) {
                    $emoLost++;
                }
            }
            foreach ($E as $i => $e) {
                $segTotal++;
                $type = NameTemplate::classify($TE[$i], $i === 0);
                $tkey = mb_strtolower(Memory::template($e)[0]);
                if (isset($al['map'][$i])) {
                    $j = $al['map'][$i];
                    $a = $A[$j];
                    if (isset(NameTemplate::TYPES[$type])) {
                        $types[$type]['n'] = ($types[$type]['n'] ?? 0) + 1;
                    }
                    if (self::same($TE[$i], $a)) {
                        $same++;
                    } else {
                        [$ek, $ak] = Memory::templatePair($e, $a);
                        [$ek, $ak] = self::trimBrand($ek, $ak, $keep);
                        if ($ek !== '' && $ak !== '' && $ek !== mb_strtolower($ak)) {
                            $trans[$ek][$ak]['n'] = ($trans[$ek][$ak]['n'] ?? 0) + 1;
                            $trans[$ek][$ak]['ex'] ??= $ex;
                        }
                    }
                    $segSeen[$tkey]['kept'] = ($segSeen[$tkey]['kept'] ?? 0) + 1;
                    $segSeen[$tkey]['ex'] ??= $ex;
                } else {
                    if (isset(NameTemplate::TYPES[$type])) {
                        $types[$type]['n'] = ($types[$type]['n'] ?? 0) + 1;
                        $types[$type]['drop'] = ($types[$type]['drop'] ?? 0) + 1;
                    } elseif ($i > 0) {
                        $segSeen[$tkey]['dropped'] = ($segSeen[$tkey]['dropped'] ?? 0) + 1;
                        $segSeen[$tkey]['ex'] ??= $ex;
                        $segSeen[$tkey]['raw'] ??= $e;
                    }
                }
            }
            foreach ($al['add'] as $j) {
                $k = Memory::template($A[$j])[0];
                $added[$k] = ($added[$k] ?? 0) + 1;
            }
            // Parça sırası: eşleşen parçaların AZ tarafındaki sırası kaynakla aynı mı, "derli toplu" mu?
            $typed = [];
            foreach ($al['map'] as $i => $j) {
                $typed[] = [$j, NameTemplate::classify($A[$j], $j === 0)];
            }
            if (count($typed) >= 3) {
                $ord['n']++;
                $js = array_column($typed, 0);
                $sorted = $js;
                sort($sorted);
                $ord['source'] += $js === $sorted ? 1 : 0;
                usort($typed, static fn($x, $y) => $x[0] <=> $y[0]);
                $azTypes = array_map(static fn($t) => $t[1], $typed);
                $rank = array_map(static fn($t) => $t === 'main' ? 0 : (NameTemplate::TIDY_ORDER[$t] ?? 5), $azTypes);
                $r2 = $rank;
                sort($r2);
                $ord['tidy'] += $rank === $r2 ? 1 : 0;
            }
        }

        $sug = [];
        // 0) ayraç, ön ek, son ek
        foreach ([['separator', $sepF, $opt['separator'], 'Ayraç', 'Adlarının çoğunda parçalar “%s” ile ayrılmış.'], ['prefix', $preF, $opt['prefix'], 'Ön ek', 'Adlarının çoğu “%s” ile başlıyor.'], ['suffix', $sufF, $opt['suffix'], 'Son ek', 'Adlarının çoğu “%s” ile bitiyor.']] as [$key, $found, $cur, $label, $why]) {
            if ($found['value'] !== null && trim((string)$found['value']) !== trim((string)$cur) && $found['n'] >= self::MIN_N) {
                $sug[] = self::sug('opt', "$key=" . $found['value'], $label . ($found['value'] === '' ? ' boş olsun' : ': “' . trim((string)$found['value']) . '”'),
                    ($found['value'] === '' ? 'Adlarının çoğunda “' . trim((string)$cur) . '” yok (%' . round($found['share'] * 100) . ').' : sprintf($why, trim((string)$found['value'])) . ' (%' . round($found['share'] * 100) . ', ' . $found['n'] . ' ad)'),
                    $found['n'], $found['share'], ['key' => $key, 'value' => $found['value']]);
            }
        }
        // 1) parça türü kalsın/silinsin
        foreach (NameTemplate::TYPES as $type => [$label, $key]) {
            $n = (int)($types[$type]['n'] ?? 0);
            if ($n < self::MIN_N) {
                continue;
            }
            $dropped = (int)($types[$type]['drop'] ?? 0);
            $dropShare = $dropped / $n;
            if ($dropShare >= self::MIN_SHARE && $opt[$key]) {
                $sug[] = self::sug('opt', "$key=0", "“" . $label . "” parçaları addan silinsin", "$n çiftin $dropped tanesinde bu parçayı silmişsin (%" . round($dropShare * 100) . ").", $n, $dropShare, ['key' => $key, 'value' => false]);
            } elseif ((1 - $dropShare) >= self::MIN_SHARE && !$opt[$key]) {
                $sug[] = self::sug('opt', "$key=1", "“" . $label . "” parçaları adda kalsın", "$n çiftin " . ($n - $dropped) . " tanesinde bu parçayı korumuşsun (%" . round((1 - $dropShare) * 100) . ").", $n, 1 - $dropShare, ['key' => $key, 'value' => true]);
            }
        }
        // 2) emoji
        if ($emoPairs >= self::MIN_N && $emoLost / $emoPairs >= self::MIN_SHARE && $opt['emoji'] === 'keep') {
            $sug[] = self::sug('opt', 'emoji=remove', 'Emojiler silinsin', "Emojili $emoPairs adın $emoLost tanesinde emojiyi atmışsın (%" . round($emoLost / $emoPairs * 100) . ').', $emoPairs, $emoLost / $emoPairs, ['key' => 'emoji', 'value' => 'remove']);
        }
        // 3) sıra
        if ($ord['n'] >= self::MIN_N && $opt['order'] === 'source') {
            $t = $ord['tidy'] / $ord['n'];
            $s = $ord['source'] / $ord['n'];
            if ($t >= self::MIN_SHARE && $t > $s + 0.1) {
                $sug[] = self::sug('opt', 'order=tidy', 'Parça sırası “derli toplu” olsun', "{$ord['n']} adın %" . round($t * 100) . " kadarı ana ad, kalite, garanti, …, Min, Maks, hız sırasında; kaynak sırasını koruyanlar %" . round($s * 100) . '.', $ord['n'], $t, ['key' => 'order', 'value' => 'tidy']);
            }
        }
        // 4) hep silinen ifadeler
        $removed = array_map('mb_strtolower', array_map('strval', $dict['remove'] ?? []));
        foreach ($segSeen as $k => $v) {
            $d = (int)($v['dropped'] ?? 0);
            $n = $d + (int)($v['kept'] ?? 0);
            if ($d >= self::MIN_PHRASE_N && $d / $n >= 0.85 && $k !== '' && !in_array($k, $removed, true)) {
                [$val, $isRe] = str_contains($k, '#') ? [self::removeRegex($k), true] : [$k, false];
                $sug[] = self::sug('remove', $k, 'Addan her zaman silinen ifade: “' . $k . '”', "$n kez geçmiş, $d tanesinde silmişsin (%" . round($d / $n * 100) . ').', $n, $d / $n, ['value' => $val], $v['ex'] ?? '');
            }
        }
        // 5) çeviri terimleri
        foreach ($trans as $ek => $variants) {
            uasort($variants, static fn($a, $b) => $b['n'] <=> $a['n']);
            $total = array_sum(array_column($variants, 'n'));
            $top = array_key_first($variants);
            $topN = $variants[$top]['n'];
            if ($topN < self::MIN_PHRASE_N || $topN / $total < 0.7) {
                continue;
            }
            $cur = $dict['phrases'][$ek] ?? null;
            if ($cur !== null && self::same((string)$cur, $top)) {
                continue;
            }
            if (str_contains($ek, '#')) {
                [$pat, $rep] = self::ruleFrom($ek, $top);
                $sug[] = self::sug('rule', $ek . '=' . $top, 'Sayılı kalıp: “' . $ek . '” → “' . $top . '”', "$total kez geçmiş, $topN tanesinde böyle çevirmişsin (%" . round($topN / $total * 100) . ').', $total, $topN / $total, ['pattern' => $pat, 'replace' => $rep], $variants[$top]['ex']);
            } else {
                $sug[] = self::sug('phrase', $ek . '=' . $top, 'Sözlük terimi: “' . $ek . '” → “' . $top . '”', "$total kez geçmiş, $topN tanesinde böyle çevirmişsin (%" . round($topN / $total * 100) . ').' . ($cur !== null ? ' Sözlükte şu an: “' . $cur . '”.' : ''), $total, $topN / $total, ['en' => $ek, 'az' => $top], $variants[$top]['ex']);
            }
        }
        usort($sug, static fn($a, $b) => [$b['n'] * $b['share']] <=> [$a['n'] * $a['share']]);

        arsort($added);
        return [
            'pairs' => count($pairs),
            'stats' => [
                'segments' => $segTotal, 'same' => $same,
                'types' => $types, 'added' => array_slice($added, 0, 10, true),
                'emoji' => [$emoPairs, $emoLost], 'order' => $ord,
            ],
            'suggestions' => $sug,
        ];
    }

    /** Düz ya da özel ayraçla parçalama ("A • B"). */
    public static function splitBy(string $s, string $sep): array
    {
        $s = Normalizer::text($s);
        if (trim($sep) === '|' || trim($sep) === '') {
            return Memory::splitSegments($s);
        }
        return array_values(array_filter(array_map('trim', preg_split('/\s*' . preg_quote(trim($sep), '/') . '\s*/u', $s) ?: []), static fn($x) => $x !== ''));
    }

    /** AZ adlarındaki baskın ayraç / ön ek / son ek. Her biri ['value'=>?string,'share'=>float,'n'=>int]. */
    private static function detectFormat(array $pairs, array $dict): array
    {
        $opt = NameTemplate::normalize($dict['options'] ?? []);
        $multi = 0;
        $votes = array_fill_keys(array_keys(NameTemplate::SEPARATORS), 0);
        $first = [];
        $last = [];
        $n = 0;
        foreach ($pairs as $p) {
            $az = Normalizer::text($p['az']);
            $en = mb_strtolower(Normalizer::text($p['en']));
            if (count(Memory::splitSegments($p['en'])) >= 2) {
                $multi++;
                foreach ($votes as $sep => $_) {
                    if (str_contains($az, $sep) || (trim($sep) === '|' && str_contains($az, '|'))) {
                        $votes[$sep]++;
                    }
                }
            }
            $tok = preg_split('/\s+/u', trim($az)) ?: [];
            if (count($tok) >= 2) {
                $n++;
                $f = $tok[0];
                $l = $tok[count($tok) - 1];
                if (self::isMark($f) && !str_contains($en, mb_strtolower($f))) {
                    $first[$f] = ($first[$f] ?? 0) + 1;
                }
                if (self::isMark($l) && !str_contains($en, mb_strtolower($l))) {
                    $last[$l] = ($last[$l] ?? 0) + 1;
                }
            }
        }
        $sep = $opt['separator'];
        $sepF = ['value' => null, 'share' => 0.0, 'n' => $multi];
        if ($multi >= self::MIN_N) {
            arsort($votes);
            $top = (string)array_key_first($votes);
            if ($votes[$top] / $multi >= self::MIN_SHARE) {
                $sep = $top;
                $sepF = ['value' => $top, 'share' => $votes[$top] / $multi, 'n' => $multi];
            }
        }
        $pick = static function (array $c, string $cur) use ($n): array {
            arsort($c);
            $t = (string)array_key_first($c);
            if ($c && $n >= self::MIN_N && $c[$t] / $n >= 0.85) {
                return ['value' => $t, 'share' => $c[$t] / $n, 'n' => $n];
            }
            // Ayarda tanımlı ama adların çoğunda yok → boşalt önerisi
            return ['value' => null, 'share' => 0.0, 'n' => $n];
        };
        $preF = $pick($first, $opt['prefix']);
        $sufF = $pick($last, $opt['suffix']);
        $clear = static function (array $f, string $cur) use ($pairs, $n): array {
            if ($f['value'] === null && trim($cur) !== '' && $n >= self::MIN_N) {
                $has = 0;
                foreach ($pairs as $p) {
                    $has += self::wraps($p['az'], $cur) ? 1 : 0;
                }
                if ($has / max(1, $n) <= 0.2) {
                    return ['value' => '', 'share' => 1 - $has / max(1, $n), 'n' => $n];
                }
            }
            return $f;
        };
        $preF = $clear($preF, $opt['prefix']);
        $sufF = $clear($sufF, $opt['suffix']);
        return [$sep, $preF['value'] ?? '', $sufF['value'] ?? '', $sepF, $preF, $sufF];
    }

    /** Ön/son ek sayılabilecek işaret: harfsiz simge/emoji ya da [TG] (TG) gibi parantezli kısa etiket. */
    private static function isMark(string $t): bool
    {
        return mb_strlen($t) <= 20 && (bool)preg_match('/^[^\p{L}\p{N}]+$|^[\[\(\{<][^\]\)\}>]{1,16}[\]\)\}>]$/u', $t);
    }

    private static function wraps(string $az, string $tok): bool
    {
        $az = trim(Normalizer::text($az));
        $tok = trim($tok);
        return $tok !== '' && (str_starts_with($az, $tok) || str_ends_with($az, $tok));
    }

    /** Ön/son eki (bulunan ya da ayardaki) adın başından/sonundan atar. */
    private static function unwrap(string $az, string $pre, string $suf, array $opt): string
    {
        $az = trim(Normalizer::text($az));
        foreach (array_unique(array_filter([trim($pre), trim((string)$opt['prefix'])])) as $x) {
            if (str_starts_with($az, $x)) {
                $az = trim(mb_substr($az, mb_strlen($x)));
            }
        }
        foreach (array_unique(array_filter([trim($suf), trim((string)$opt['suffix'])])) as $x) {
            if (str_ends_with($az, $x)) {
                $az = trim(mb_substr($az, 0, mb_strlen($az) - mb_strlen($x)));
            }
        }
        return $az;
    }

    private static function sug(string $kind, string $key, string $title, string $detail, int $n, float $share, array $payload, string $ex = ''): array
    {
        return ['id' => substr(sha1($kind . '|' . $key . '|' . json_encode($payload)), 0, 10), 'kind' => $kind, 'title' => $title, 'detail' => $detail,
            'n' => $n, 'share' => round($share, 3), 'payload' => $payload, 'ex' => $ex];
    }

    /** "instagram followers" ↔ "Instagram İzləyici": başta/sonda ortak marka kelimesi atılır, çekirdek öğrenilir. */
    private static function trimBrand(string $ek, string $ak, array $keep): array
    {
        $e = preg_split('/\s+/u', $ek) ?: [];
        $a = preg_split('/\s+/u', $ak) ?: [];
        while (count($e) > 1 && count($a) > 1 && in_array($e[0], $keep, true) && mb_strtolower($a[0]) === $e[0]) {
            array_shift($e);
            array_shift($a);
        }
        while (count($e) > 1 && count($a) > 1 && in_array(end($e), $keep, true) && mb_strtolower(end($a)) === end($e)) {
            array_pop($e);
            array_pop($a);
        }
        return [implode(' ', $e), implode(' ', $a)];
    }

    private const NUMPAT = '(\d+(?:[.,]\d+)?\s?[KkMm]?)';

    private static function pattern(string $template, array &$map): string
    {
        $g = 0;
        $pat = '';
        foreach (preg_split('/(#\d+)/', $template, -1, PREG_SPLIT_DELIM_CAPTURE) ?: [] as $part) {
            if (preg_match('/^#(\d+)$/', $part, $m)) {
                $map[$m[1]] = ++$g;
                $pat .= self::NUMPAT;
            } else {
                $pat .= str_replace(' ', '\s+', preg_quote($part, '/'));
            }
        }
        return $pat;
    }

    /** @return array{0:string,1:string} [regex, değiştirme] */
    private static function ruleFrom(string $enT, string $azT): array
    {
        $map = [];
        $pat = self::pattern($enT, $map);
        $rep = preg_replace_callback('/#(\d+)/', static fn($m) => '${' . ($map[$m[1]] ?? 1) . '}', str_replace(['\\', '$'], ['\\\\', '\\$'], $azT)) ?? $azT;
        return [$pat, $rep];
    }

    private static function removeRegex(string $template): string
    {
        $map = [];
        return '/(?i)(?<![\p{L}\p{N}])' . self::pattern($template, $map) . '(?![\p{L}\p{N}])/';
    }

    // ------------------------------------------------------------------ öneriyi sözlüğe işleme

    public static function applyTo(array $dict, array $s): array
    {
        $pl = $s['payload'];
        switch ($s['kind']) {
            case 'opt':
                $dict['options'] = NameTemplate::normalize(array_merge($dict['options'] ?? [], [$pl['key'] => $pl['value']]));
                break;
            case 'phrase':
                $dict['phrases'][$pl['en']] = $pl['az'];
                break;
            case 'rule':
                $dict['rules'] = array_values(array_filter($dict['rules'] ?? [], static fn($r) => ($r[0] ?? '') !== $pl['pattern']));
                array_unshift($dict['rules'], [$pl['pattern'], $pl['replace']]);
                break;
            case 'remove':
                $dict['remove'] = array_values(array_unique(array_merge($dict['remove'] ?? [], [$pl['value']])));
                break;
        }
        return $dict;
    }

    // ------------------------------------------------------------------ doğruluk ölçümü

    /**
     * Çiftlerin ~%20'si (adın özetine göre sabit) test olur; öneriler kalan %80'den üretilir.
     * @return array{test:int,base:int,after:int,miss:list<array>}
     */
    public function evaluate(array $pairs, array $dict): array
    {
        $train = [];
        $test = [];
        foreach ($pairs as $p) {
            if (crc32(Memory::norm($p['en'])) % 5 === 0) {
                $test[] = $p;
            } else {
                $train[] = $p;
            }
        }
        if (count($test) < 5 || count($train) < 20) {
            return ['test' => count($test), 'base' => 0, 'after' => 0, 'miss' => [], 'ready' => false];
        }
        $proj = $dict;
        foreach ($this->analyze($train, $dict)['suggestions'] as $s) {
            $proj = self::applyTo($proj, $s);
        }
        $trBase = new Translator(array_merge($dict, ['options' => []]));
        $trProj = new Translator(array_merge($proj, ['options' => []]));
        $base = $after = 0;
        $miss = [];
        foreach ($test as $p) {
            $b = self::same(self::simulate($dict, $p['en'], $trBase), $p['az']);
            $o = self::simulate($proj, $p['en'], $trProj);
            $a = self::same($o, $p['az']);
            $base += $b ? 1 : 0;
            $after += $a ? 1 : 0;
            if (!$a && count($miss) < 15) {
                $miss[] = ['en' => $p['en'], 'want' => $p['az'], 'got' => $o];
            }
        }
        return ['test' => count($test), 'base' => $base, 'after' => $after, 'miss' => $miss, 'ready' => true];
    }

    /** Tüm süreç: analiz + ölçüm; sonucu veritabanına yazar. */
    public function run(): array
    {
        @set_time_limit(120);
        $pairs = $this->pairs();
        $dict = Dictionary::load($this->db);
        $a = $this->analyze($pairs, $dict);
        $a['eval'] = $this->evaluate($pairs, $dict);
        $a['at'] = time();
        $this->db->setJson('learn_report', $a);
        return $a;
    }

    /** @return array<string,string> id => applied|rejected */
    public function decided(): array
    {
        $d = $this->db->getJson('learn_decided', []);
        return is_array($d) ? $d : [];
    }

    public function decide(string $id, string $how): void
    {
        $d = $this->decided();
        $d[$id] = $how;
        $this->db->setJson('learn_decided', $d);
    }
}
