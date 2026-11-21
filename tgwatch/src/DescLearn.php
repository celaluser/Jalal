<?php
declare(strict_types=1);

namespace TgWatch;

/**
 * Açıklama öğrenme (Çeviri → Açıklamalar). Paneldeki Azerice açıklamaları Admin API'den okur, panelde hiçbir şeyi değiştirmez.
 *
 * Öğrendikleri:
 *   - Addaki bilgi → açıklama satırı kuralları: adında "30 Gün Qarantili" olan servislerin açıklamasında 30'un geçtiği satır
 *     ("♻️ Qarantiya: {v} gün") garanti satırının şablonu olur. Maks, günlük hız, saatlik hız, başlama, Non Drop, iptal için de.
 *   - Grup şablonları (platform × tür, ör. Instagram takipçi): açıklamaların çoğunda geçen satırlar, ortalama sıralarıyla.
 *   - Sık ifadeler: birçok açıklamada aynen geçen satırlar.
 *   - Tutarsızlıklar: adı ile açıklaması çelişen servisler (ad 30 gün, açıklama 60 gün; ad Instagram, açıklama TikTok…)
 *     ve adındaki bilgiyi açıklamasında hiç yazmayanlar.
 * Panelde yalnızca Azerice açıksa İngilizce→Azerice çift öğrenilemez; bu bölüm üslubu ve kuralları öğrenir.
 * Sonuç: desc_learn_report (rapor) ve desc_rules (üretim için kurallar ve şablonlar). Açıklamalar panel_desc tablosunda.
 */
final class DescLearn
{
    public const REPORT = 'desc_learn_report';
    public const RULES = 'desc_rules';
    public const FACTS = [
        'guarantee' => 'Garanti (gün)',
        'lifetime' => 'Ömürlük garanti',
        'noguarantee' => 'Garantisiz',
        'max' => 'Maks',
        'speed' => 'Günlük hız',
        'speed_h' => 'Saatlik hız',
        'start' => 'Başlama (süre)',
        'instant' => 'Anında başlar',
        'nondrop' => 'Azalma olmaz',
        'cancel' => 'İptal (ləğv)',
    ];
    public const TYPES = [
        'followers' => ['izləyici|izleyici|follower|takipçi|takipci', 'takipçi'],
        'likes' => ['bəyəni|beyeni|like|beğeni', 'beğeni'],
        'views' => ['baxış|baxis|view|izlenme|görüntülenme', 'izlenme'],
        'comments' => ['şərh|serh|comment|yorum|rəy', 'yorum'],
        'subscribers' => ['abunəçi|abuneci|subscriber|abone', 'abone'],
        'members' => ['üzv|uzv|member|üye', 'üye'],
        'shares' => ['paylaşım|share|repost', 'paylaşım'],
        'reactions' => ['reaksiya|reaction|tepki', 'reaksiya'],
        'saves' => ['yadda saxla|save|kaydet', 'kaydetme'],
        'live' => ['canlı|canli|live', 'canlı yayın'],
        'story' => ['hekayə|hekaye|story|hikaye', 'hikaye'],
        'plays' => ['dinlənmə|play|stream|dinlenme', 'dinlenme'],
    ];
    public const KW = [
        'guarantee' => '/qarant|zəmanət|zemanet|bərpa|berpa|refill|garanti|telafi/iu',
        'max' => '/maks|max|maksimum/iu',
        'speed' => '/sürət|suret|speed|gündə|gunde|günlük|gunluk|\/\s*day/iu',
        'speed_h' => '/saatda|saatlik|\/\s*hour|per hour/iu',
        'start' => '/başla|basla|start/iu',
        'instant' => '/ani|dərhal|derhal|instant/iu',
        'nondrop' => '/azalma|düşmə|dusme|drop/iu',
        'cancel' => '/ləğv|legv|iptal|cancel/iu',
        'lifetime' => '/ömürlük|omurluk|lifetime|ömür boyu/iu',
    ];

    public function __construct(private Db $db, private array $config)
    {
    }

    // ------------------------------------------------------------------ yardımcılar

    /** "10K" → 10000, "1.5K" → 1500, "10.000" / "10 000" → 10000. */
    public static function num(string $s): ?float
    {
        $s = trim(str_replace(["\u{00A0}", ' '], '', $s));
        if (!preg_match('/^(\d+(?:[.,]\d+)*)([KkMm])?$/u', $s, $m)) {
            return null;
        }
        $n = $m[1];
        if (preg_match('/^\d{1,3}([.,]\d{3})+$/', $n) && empty($m[2])) {
            $n = str_replace(['.', ','], '', $n); // binlik ayırıcı
        } else {
            $n = str_replace(',', '.', $n);
            if (substr_count($n, '.') > 1) {
                $n = str_replace('.', '', $n);
            }
        }
        $v = (float)$n;
        return match (strtolower($m[2] ?? '')) {
            'k' => $v * 1000,
            'm' => $v * 1000000,
            default => $v,
        };
    }

    /** Satırdaki sayılar: [ham, değer]. @return list<array{0:string,1:float}> */
    private static function numbers(string $line): array
    {
        preg_match_all('/(?<![\p{L}\d])\d+(?:[.,]\d+)*(?:\s?[KkMm](?![\p{L}]))?/u', $line, $m);
        $out = [];
        foreach ($m[0] as $raw) {
            $v = self::num($raw);
            if ($v !== null) {
                $out[] = [$raw, $v];
            }
        }
        return $out;
    }

    public static function lower(string $s): string
    {
        return str_replace("i\u{0307}", 'i', mb_strtolower(Normalizer::text($s)));
    }

    /** Satır şablonu: sayılar # olur (karşılaştırma ve sayım için). */
    public static function shape(string $line): string
    {
        $l = trim(preg_replace('/\s+/u', ' ', $line) ?? $line);
        return preg_replace('/(?<![\p{L}\d])\d+(?:[.,]\d+)*(?:\s?[KkMm](?![\p{L}]))?/u', '#', $l) ?? $l;
    }

    /** @return list<string> boş olmayan satırlar */
    public static function lines(string $desc): array
    {
        $desc = str_replace(["\r\n", "\r"], "\n", html_entity_decode(strip_tags(str_ireplace(['<br>', '<br/>', '<br />', '</p>'], "\n", $desc)), ENT_QUOTES | ENT_HTML5, 'UTF-8'));
        return array_values(array_filter(array_map(static fn($l) => trim(preg_replace('/\s+/u', ' ', $l) ?? $l), explode("\n", $desc)), static fn($l) => $l !== ''));
    }

    public static function type(string $text): string
    {
        $t = self::lower($text);
        foreach (self::TYPES as $k => [$re]) {
            if (preg_match('/(' . $re . ')/u', $t)) {
                return $k;
            }
        }
        return 'other';
    }

    /**
     * Addaki (ve API'deki) bilgiler.
     * @return array<string,mixed> guarantee:int, lifetime:true, noguarantee:true, max:float, speed:float, speed_h:float, start:string, instant:true, nondrop:true, cancel:true
     */
    public static function facts(string $name, array $svc = []): array
    {
        $t = self::lower($name);
        $f = [];
        $n = '(\d+(?:[.,]\d+)*\s?[km]?)';
        if (preg_match('/(\d+)\s*(?:gün|gun|days?|g\b)[^|]{0,25}?(?:qarant|zəmanət|zemanet|bərpa|berpa|refill|garanti|telafi)/u', $t, $m)
            || preg_match('/(?:qarant\w*|zəmanət\w*|zemanet\w*|bərpa\w*|refill|garanti\w*|telafi\w*)\D{0,12}?(\d+)\s*(?:gün|gun|days?)/u', $t, $m)) {
            $f['guarantee'] = (int)$m[1];
        }
        if (preg_match(self::KW['lifetime'], $t)) {
            $f['lifetime'] = true;
            unset($f['guarantee']);
        }
        if (preg_match('/qarantisiz|zəmanətsiz|zemanetsiz|no refill|refill yox|telafisiz|garantisiz/u', $t)) {
            $f['noguarantee'] = true;
            unset($f['guarantee'], $f['lifetime']);
        }
        if (preg_match('/(?:maks|max)\.?\s*:?\s*' . $n . '/u', $t, $m) && ($v = self::num($m[1])) !== null) {
            $f['max'] = $v;
        }
        if ((preg_match('/(?:gündə|gunde|günlük|gunluk|daily)\s*:?\s*' . $n . '/u', $t, $m) || preg_match('/' . $n . '\s*\/\s*(?:day|gün|gun)/u', $t, $m))
            && ($v = self::num($m[1])) !== null) {
            $f['speed'] = $v;
        }
        if ((preg_match('/(?:saatda|saatlik|hourly)\s*:?\s*' . $n . '/u', $t, $m) || preg_match('/' . $n . '\s*\/\s*(?:hour|saat)/u', $t, $m))
            && ($v = self::num($m[1])) !== null) {
            $f['speed_h'] = $v;
        }
        if (preg_match('/(\d+\s*-\s*\d+)\s*(saat|dəq\w*|deq\w*|hour\w*|min\w*)/u', $t, $m)) {
            $f['start'] = preg_replace('/\s+/', '', $m[1]) . ' ' . $m[2];
        }
        if (preg_match('/(?<![\p{L}])(ani|dərhal|derhal|instant)(?![\p{L}])/u', $t)) {
            $f['instant'] = true;
        }
        if (preg_match('/azalma olmaz|düşmə yox|dusme yox|non ?drop|no drop/u', $t)) {
            $f['nondrop'] = true;
        }
        if (preg_match(self::KW['cancel'], $t) || !empty($svc['cancel'])) {
            $f['cancel'] = true;
        }
        return $f;
    }

    /**
     * Açıklamada bir bilginin yazıldığı satır (ve şablonu: değer {v}, diğer sayılar #).
     * @return array{line:string,tpl:string}|null
     */
    private static function lineFor(string $fact, $value, array $lines): ?array
    {
        $best = null;
        foreach ($lines as $l) {
            $low = self::lower($l);
            if (in_array($fact, ['guarantee', 'max', 'speed', 'speed_h'], true)) {
                foreach (self::numbers($l) as [$raw, $v]) {
                    if (abs($v - (float)$value) < 0.001 && ($fact !== 'guarantee' || preg_match('/gün|gun|day/u', $low))) {
                        $tpl = self::shape(preg_replace('/' . preg_quote($raw, '/') . '/u', "\u{E000}", $l, 1) ?? $l);
                        $cand = ['line' => $l, 'tpl' => str_replace("\u{E000}", '{v}', $tpl)];
                        if (preg_match(self::KW[$fact], $low)) {
                            return $cand; // sayı + anahtar kelime: kesin
                        }
                        $best ??= $cand;
                    }
                }
                continue;
            }
            $kw = self::KW[$fact] ?? null;
            if ($fact === 'start' && is_string($value) && str_contains(preg_replace('/\s+/', '', $low) ?? '', preg_replace('/\s+/', '', explode(' ', $value)[0]) ?? '')) {
                return ['line' => $l, 'tpl' => self::shape($l)];
            }
            if ($kw && $fact !== 'start' && preg_match($kw, $low)) {
                return ['line' => $l, 'tpl' => self::shape($l)];
            }
        }
        return $best;
    }

    /** Açıklamada adla çelişenler ve eksikler. @return list<array{kind:string,fact:string,msg:string,line:string}> */
    public static function check(array $facts, array $lines, string $name): array
    {
        $out = [];
        $N = '(\d+(?:[.,]\d+)*(?:\s?[km](?![\p{L}]))?)';
        // Bilgi için açıklamada yazılan sayılar: [ham, değer, satır] — sayı anahtar kelimenin hemen yanından alınır.
        $found = static function (array $res) use ($lines): array {
            $hits = [];
            foreach ($lines as $l) {
                $low = self::lower($l);
                foreach ($res as $re) {
                    if (preg_match_all($re, $low, $m)) {
                        foreach ($m[1] as $raw) {
                            if (($v = self::num($raw)) !== null) {
                                $hits[] = [$raw, $v, $l];
                            }
                        }
                    }
                }
            }
            return $hits;
        };
        $judge = static function (string $fact, float $want, array $hits, string $label, string $unit) use (&$out): void {
            if (!$hits) {
                if ($fact === 'guarantee') {
                    $out[] = ['kind' => 'missing', 'fact' => $fact, 'msg' => 'Adında ' . self::fmt($want) . $unit . ' ' . $label . ', açıklamada yazmıyor', 'line' => ''];
                }
                return;
            }
            foreach ($hits as [, $v]) {
                if (abs($v - $want) < 0.001) {
                    return; // doğru yazılmış
                }
            }
            $out[] = ['kind' => 'conflict', 'fact' => $fact, 'msg' => 'Adında ' . $label . ' ' . self::fmt($want) . $unit . ', açıklamada ' . trim($hits[0][0]) . $unit, 'line' => $hits[0][2]];
        };
        if (isset($facts['guarantee'])) {
            $judge('guarantee', (float)$facts['guarantee'], $found([
                '/(?:qarant|zəmanət|zemanet|bərpa|berpa|refill|garanti|telafi)\w*\D{0,15}?(\d+)\s*(?:gün|gun|day)/u',
                '/(\d+)\s*(?:gün|gun|days?)\w*\s*(?:qarant|zəmanət|zemanet|bərpa|berpa|refill|garanti|telafi)/u',
            ]), 'garanti', ' gün');
        }
        if (isset($facts['max'])) {
            $judge('max', (float)$facts['max'], $found(['/(?:maks|max)\w*\.?\s*:?\s*' . $N . '/u']), 'maks', '');
        }
        if (isset($facts['speed'])) {
            $judge('speed', (float)$facts['speed'], $found(['/(?:gündə|gunde|günlük|gunluk)\s*:?\s*' . $N . '/u', '/' . $N . '\s*\/\s*(?:day|gün|gun)/u']), 'günlük hız', '');
        }
        $text = self::lower(implode("\n", $lines));
        if (!empty($facts['noguarantee']) && preg_match('/(\d+)\s*(gün|gun|day)[^\n]{0,20}(qarant|zəmanət|bərpa|refill)|(qarant\w*|zəmanət\w*|bərpa\w*|refill)\D{0,12}\d+\s*(gün|gun|day)/u', $text)) {
            $out[] = ['kind' => 'conflict', 'fact' => 'noguarantee', 'msg' => 'Adında garantisiz, açıklamada garanti süresi var', 'line' => ''];
        }
        if (!empty($facts['lifetime']) && !preg_match(self::KW['lifetime'], $text) && preg_match('/(\d+)\s*(gün|gun|day)/u', $text)) {
            $out[] = ['kind' => 'conflict', 'fact' => 'lifetime', 'msg' => 'Adında ömürlük garanti, açıklamada gün sayısı var', 'line' => ''];
        }
        $plat = Matcher::platform($name);
        if ($plat !== '') {
            foreach (Matcher::PLATFORMS as $p) {
                if ($p !== $plat && strlen($p) > 3 && preg_match('/(?<![\p{L}])' . preg_quote($p, '/') . '(?![\p{L}])/u', $text) && !preg_match('/(?<![\p{L}])' . preg_quote($plat, '/') . '(?![\p{L}])/u', $text)) {
                    $out[] = ['kind' => 'conflict', 'fact' => 'platform', 'msg' => 'Ad ' . self::pname($plat) . ', açıklama ' . self::pname($p) . ' diyor', 'line' => ''];
                    break;
                }
            }
        }
        return $out;
    }

    /** Platform adının yazılışı (tiktok → TikTok). */
    public static function pname(string $p): string
    {
        return ['tiktok' => 'TikTok', 'youtube' => 'YouTube', 'linkedin' => 'LinkedIn', 'soundcloud' => 'SoundCloud', 'vk' => 'VK', 'diğer' => 'Diğer'][$p] ?? ucfirst($p);
    }

    public static function fmt(float $v): string
    {
        if ($v >= 1000000 && fmod($v, 1000000) == 0.0) {
            return ($v / 1000000) . 'M';
        }
        if ($v >= 1000 && fmod($v, 1000) == 0.0) {
            return ($v / 1000) . 'K';
        }
        return rtrim(rtrim(number_format($v, 2, '.', ''), '0'), '.');
    }

    // ------------------------------------------------------------------ çalıştır

    /** @return array<string,mixed> rapor */
    public function run(): array
    {
        @set_time_limit(300);
        $api = new PanelServices($this->db, $this->config);
        if (!$api->configured()) {
            throw new \InvalidArgumentException('Önce Ayarlar → Yayın ve panel API\'de panel API adresi ve anahtarını gir.');
        }
        $t0 = microtime(true);
        $list = $api->services();
        if (!$list) {
            throw new \RuntimeException('Panelden servis gelmedi.');
        }
        $active = $api->activeLangs($list[0]['id']);
        $lang = in_array('az', $active, true) ? 'az' : '';
        $nd = $api->namesDescriptions(array_column($list, 'id'), $lang);
        DescGen::fetchSiteOrder($this->db, $this->config); // sitedeki kategori sırası (olmazsa eklenti gönderir)
        return $this->learn($list, $nd, $active, (int)((microtime(true) - $t0) * 1000));
    }

    /**
     * Öğrenme (ağsız, test edilebilir).
     * @param list<array> $list servisler (PanelServices::services)
     * @param array<int,array<string,array{name:string,description:string}>> $nd ad/açıklamalar
     */
    public function learn(array $list, array $nd, array $active = ['az'], int $ms = 0): array
    {
        $rows = [];
        $cats = [];
        $votes = [];      // fact => grup|* => tpl => n
        $examples = [];   // fact => tpl => örnek satır
        $groups = [];     // grup => [n, lines: shape => [count, possum], descs]
        $phrase = [];     // shape => count
        $phraseInfo = []; // shape => [örnek satır, sıra toplamı]
        $types = [];      // tür (platformdan bağımsız) => [n, lines: shape => [count, possum, sample], descs]
        $lineCounts = [];
        $issues = [];
        $withDesc = 0;
        foreach ($list as $s) {
            $byLang = $nd[$s['id']] ?? [];
            $own = $byLang['az'] ?? ($byLang ? reset($byLang) : ['name' => '', 'description' => '']);
            $desc = trim((string)($own['description'] ?? ''));
            $rows[] = [$s['id'], $s['name'], $s['category_id'], $s['category_name'], $s['status'], $desc, $s['min'] ?? null, $s['max'] ?? null, !empty($s['refill']) ? 1 : 0, !empty($s['cancel']) ? 1 : 0];
            $ck = (int)($s['category_id'] ?? 0);
            $cats[$ck] ??= ['id' => $ck, 'name' => $s['category_name'], 'n' => 0, 'empty' => 0, 'conflict' => 0, 'missing' => 0, 'on' => 0];
            $cats[$ck]['n']++;
            $cats[$ck]['on'] += $s['status'] === 'enabled' ? 1 : 0;
            if ($desc === '') {
                $cats[$ck]['empty']++;
                continue;
            }
            $withDesc++;
            $lines = self::lines($desc);
            $facts = self::facts($s['name'], $s);
            $group = (Matcher::platform($s['name'] . ' ' . $s['category_name']) ?: 'diğer') . '|' . self::type($s['name'] . ' ' . $s['category_name']);
            foreach ($facts as $f => $v) {
                $hit = self::lineFor($f, $v, $lines);
                if ($hit) {
                    foreach ([$group, '*'] as $g) {
                        $votes[$f][$g][$hit['tpl']] = ($votes[$f][$g][$hit['tpl']] ?? 0) + 1;
                    }
                    $examples[$f][$hit['tpl']] ??= $hit['line'];
                }
            }
            $groups[$group] ??= ['n' => 0, 'lines' => [], 'descs' => []];
            $groups[$group]['n']++;
            if (count($groups[$group]['descs']) < 40) {
                $groups[$group]['descs'][] = $lines;
            }
            $type = explode('|', $group)[1];
            $types[$type] ??= ['n' => 0, 'lines' => [], 'descs' => []];
            $types[$type]['n']++;
            if (count($types[$type]['descs']) < 60) {
                $types[$type]['descs'][] = $lines;
            }
            $lineCounts[] = count($lines);
            $seen = [];
            foreach ($lines as $i => $l) {
                $sh = self::shape($l);
                if (isset($seen[$sh])) {
                    continue;
                }
                $seen[$sh] = true;
                $rel = $i / max(1, count($lines) - 1);
                $types[$type]['lines'][$sh][0] = ($types[$type]['lines'][$sh][0] ?? 0) + 1;
                $types[$type]['lines'][$sh][1] = ($types[$type]['lines'][$sh][1] ?? 0) + $rel;
                $types[$type]['lines'][$sh][2] ??= $l;
                $phraseInfo[$sh][0] ??= $l;
                $phraseInfo[$sh][1] = ($phraseInfo[$sh][1] ?? 0) + $rel;
                $groups[$group]['lines'][$sh][0] = ($groups[$group]['lines'][$sh][0] ?? 0) + 1;
                $groups[$group]['lines'][$sh][1] = ($groups[$group]['lines'][$sh][1] ?? 0) + $i / max(1, count($lines) - 1);
                $groups[$group]['lines'][$sh][2] ??= $l;
                $phrase[$sh] = ($phrase[$sh] ?? 0) + 1;
            }
            foreach (self::check($facts, $lines, $s['name']) as $p) {
                $cats[$ck][$p['kind']]++;
                if (count($issues) < 400) {
                    $issues[] = ['id' => $s['id'], 'name' => $s['name'], 'category' => $s['category_name'], 'status' => $s['status']] + $p;
                }
            }
        }
        // Kurallar: bilgi başına en çok oy alan satır şablonu (genel ve grup başına)
        $rules = ['*' => [], 'groups' => []];
        $ruleRows = [];
        foreach ($votes as $f => $byGroup) {
            foreach ($byGroup as $g => $tpls) {
                arsort($tpls);
                $top = (string)array_key_first($tpls);
                $total = array_sum($tpls);
                $r = ['tpl' => $top, 'n' => $tpls[$top], 'total' => $total];
                if ($g === '*') {
                    $rules['*'][$f] = $r;
                    $ruleRows[] = ['fact' => $f, 'label' => self::FACTS[$f] ?? $f, 'example' => $examples[$f][$top] ?? '', 'alts' => array_slice(array_keys($tpls), 1, 3)] + $r;
                } elseif ($tpls[$top] >= 2) {
                    $rules['groups'][$g][$f] = $r;
                }
            }
        }
        usort($ruleRows, static fn($a, $b) => $b['total'] <=> $a['total']);
        // Grup şablonları: grubun en az %35'inde geçen satırlar, ortalama sıralarına göre
        $tplRows = [];
        foreach ($groups as $g => $gd) {
            if ($gd['n'] < 3) {
                continue;
            }
            $keep = [];
            foreach ($gd['lines'] as $sh => [$cnt, $pos, $sample]) {
                if ($cnt / $gd['n'] >= 0.35) {
                    $keep[] = ['shape' => $sh, 'sample' => $sample, 'share' => round($cnt / $gd['n'], 2), 'pos' => $pos / $cnt];
                }
            }
            if (!$keep) {
                continue;
            }
            usort($keep, static fn($a, $b) => $a['pos'] <=> $b['pos']);
            // Örnek: şablon satırlarını en çok içeren açıklama
            $shapes = array_flip(array_column($keep, 'shape'));
            $best = [];
            $bestScore = -1;
            foreach ($gd['descs'] as $d) {
                $sc = count(array_filter($d, static fn($l) => isset($shapes[self::shape($l)])));
                if ($sc > $bestScore) {
                    [$best, $bestScore] = [$d, $sc];
                }
            }
            [$plat, $type] = explode('|', $g);
            $tplRows[] = ['group' => $g, 'platform' => $plat, 'type' => $type, 'n' => $gd['n'], 'lines' => array_map(static fn($k) => ['shape' => $k['shape'], 'share' => $k['share']], $keep), 'example' => $best];
            $rules['templates'][$g] = array_column($keep, 'shape');
            $rules['examples'][$g] = $best;
        }
        // Tür başına (bütün platformlar): sık satırlar (ör. beğenide "🔗 Link: Paylaşım linki") ve en dolgun tipik örnek
        foreach ($types as $t => $td) {
            if ($td['n'] < 2) {
                continue;
            }
            $keep = [];
            foreach ($td['lines'] as $sh => [$cnt, $pos, $sample]) {
                if ($cnt >= 2 && $cnt / $td['n'] >= 0.3) {
                    $keep[$sh] = ['line' => $sample, 'shape' => $sh, 'share' => round($cnt / $td['n'], 2), 'pos' => round($pos / $cnt, 2)];
                }
            }
            $rules['type_lines'][$t] = array_values($keep);
            $best = [];
            $bestScore = -1;
            foreach ($td['descs'] as $d) {
                $sc = count(array_filter($d, static fn($l) => isset($keep[self::shape($l)]))) * 10 + min(count($d), 12);
                if ($sc > $bestScore) {
                    [$best, $bestScore] = [$d, $sc];
                }
            }
            $rules['type_examples'][$t] = $best;
        }
        // Bütün açıklamaların en az %20'sinde geçen satırlar (ör. "Gizli hesablara sifariş verməyin")
        $rules['common'] = [];
        foreach ($phrase as $sh => $n) {
            if ($withDesc >= 3 && $n >= 2 && $n / $withDesc >= 0.2) {
                $rules['common'][] = ['line' => $phraseInfo[$sh][0], 'shape' => $sh, 'share' => round($n / $withDesc, 2), 'pos' => round($phraseInfo[$sh][1] / $n, 2)];
            }
        }
        sort($lineCounts);
        $rules['median_lines'] = $lineCounts ? $lineCounts[intdiv(count($lineCounts), 2)] : 0;
        usort($tplRows, static fn($a, $b) => $b['n'] <=> $a['n']);
        arsort($phrase);
        $phrases = [];
        foreach ($phrase as $sh => $n) {
            if ($n < 3 || count($phrases) >= 40) {
                break;
            }
            $phrases[] = ['shape' => $sh, 'n' => $n];
        }
        $this->db->tx(function () use ($rows): void {
            $this->db->run('DELETE FROM panel_desc');
            // pos: Admin API listesindeki sıra (panelin kendi sırası); sitedeki kategori sırası eklentiden gelirse o kullanılır
            $st = $this->db->pdo->prepare('INSERT OR REPLACE INTO panel_desc(id, name, category_id, category_name, status, description, min, max, refill, cancel, at, pos) VALUES(?,?,?,?,?,?,?,?,?,?,?,?)');
            $now = time();
            $i = 0;
            foreach ($rows as $r) {
                $st->execute([...$r, $now, ++$i]);
            }
        });
        $catRows = array_values($cats);
        usort($catRows, static fn($a, $b) => [$b['empty'], $b['conflict']] <=> [$a['empty'], $a['conflict']]);
        $conf = count(array_filter($issues, static fn($i) => $i['kind'] === 'conflict'));
        $rep = [
            'at' => time(), 'ms' => $ms, 'langs' => $active, 'services' => count($list), 'with_desc' => $withDesc, 'empty' => count($list) - $withDesc,
            'conflicts' => array_sum(array_column($catRows, 'conflict')), 'missing' => array_sum(array_column($catRows, 'missing')),
            'rules' => $ruleRows, 'templates' => array_slice($tplRows, 0, 40), 'phrases' => $phrases,
            'issues' => $issues, 'issues_conflict' => $conf, 'categories' => array_slice($catRows, 0, 800),
            'pairs' => (int)($this->db->one("SELECT COUNT(*) c FROM tm WHERE kind = 'desc' AND status = 'ok'")['c'] ?? 0),
        ];
        $this->db->setJson(self::REPORT, $rep);
        $this->db->setJson(self::RULES, $rules + ['at' => time()]);
        $this->db->log('info', 'bank', "Açıklama öğrenme: {$rep['services']} servis, {$withDesc} açıklamalı, " . count($ruleRows) . ' kural, ' . count($tplRows) . " şablon, {$rep['conflicts']} çelişki");
        return $rep;
    }

    public function report(): ?array
    {
        $r = $this->db->getJson(self::REPORT);
        return is_array($r) ? $r : null;
    }
}
