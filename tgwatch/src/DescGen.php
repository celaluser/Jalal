<?php
declare(strict_types=1);

namespace TgWatch;

/**
 * Açıklamasız servislere açıklama önerisi (Çeviri → Açıklamalar → kategori seç → Üret).
 *
 * Temel: aynı kategorideki gerçek bir açıklaman (yapısı en tipik olan); yoksa aynı platform × türün örnek açıklaması;
 * o da yoksa yalnızca öğrenilmiş kurallardan satırlar. Temel açıklama satır satır uyarlanır:
 *   - bilgi satırı (garanti, maks, hız, başlama…): servisin adında o bilgi varsa değer yenisiyle değişir (sayı biçimi korunur),
 *     yoksa satır ÇIKARILIR (uydurulmaz);
 *   - Min/Maks satırı: API'deki min/max (addaki maks varsa o);
 *   - başka sayılı satır: neye ait olduğu bilinmediği için çıkarılır;
 *   - sayısız satır: "Azalma olmaz", "Ani", "Ləğv…" gibi bir bilgiye aitse yalnızca servis o bilgiye sahipse kalır; genel satırlar kalır.
 * Adında olup temelde satırı olmayan bilgiler öğrenilmiş kalıpla eklenir. Sonuç DescLearn::check ile denetlenir.
 * Öneriler desc_props tablosunda: proposed → approved → applied (eklenti yazdı) → verified (Admin API ile okundu) | failed; rejected.
 */
final class DescGen
{
    private const NUM = '(\d+(?:[.,]\d+)*(?:\s?[KkMm](?![\p{L}]))?)';
    private const LINE_FACT = [
        'guarantee' => ['/(?:qarant|zəmanət|zemanet|bərpa|berpa|refill|garanti|telafi)\w*\D{0,15}?' . self::NUM . '\s*(?:gün|gun|day)/iu', '/' . self::NUM . '\s*(?:gün|gun|days?)\w*\s*(?:qarant|zəmanət|zemanet|bərpa|berpa|refill|garanti|telafi)/iu'],
        'max' => ['/(?:maks|max)\w*\.?\s*:?\s*' . self::NUM . '/iu'],
        'speed' => ['/(?:gündə|gunde|günlük|gunluk)\s*:?\s*' . self::NUM . '/iu', '/' . self::NUM . '\s*\/\s*(?:day|gün|gun)/iu', '/(?:sürət|suret|speed)\w*\s*:?\s*' . self::NUM . '/iu'],
        'speed_h' => ['/(?:saatda|saatlik|hourly)\s*:?\s*' . self::NUM . '/iu', '/' . self::NUM . '\s*\/\s*(?:hour|saat)/iu'],
        'min' => ['/(?:min|minimum)\w*\.?\s*:?\s*' . self::NUM . '/iu'],
    ];

    public function __construct(private Db $db)
    {
    }

    /** Aynı biçimde yaz: eski "500K" ise 1M, eski "500.000" ise 1.000.000, eski "500" ise 1000000. */
    public static function like(float $v, string $old): string
    {
        $old = trim($old);
        if (preg_match('/[KkMm]$/', $old)) {
            $s = DescLearn::fmt($v);
            return preg_match('/[km]$/', $old) ? strtolower($s) : $s;
        }
        if (preg_match('/^\d{1,3}([. ,])\d{3}/', $old, $m) || ($v >= 1000 && preg_match('/^\d+[.,]\d{3}$/', $old))) {
            return number_format($v, 0, '', $m[1] ?? '.');
        }
        return fmod($v, 1.0) == 0.0 ? (string)(int)$v : rtrim(rtrim(number_format($v, 2, '.', ''), '0'), '.');
    }

    /** Satırdaki bilgi sayısını yenisiyle değiştir. @return string|null değişmiş satır (bulunamadıysa null) */
    private static function setNum(string $line, string $fact, float $v): ?string
    {
        foreach (self::LINE_FACT[$fact] ?? [] as $re) {
            if (preg_match($re, $line, $m, PREG_OFFSET_CAPTURE)) {
                [$raw, $pos] = $m[1];
                return substr($line, 0, $pos) . self::like($v, $raw) . substr($line, $pos + strlen($raw));
            }
        }
        return null;
    }

    /** Satır hangi bilgiye ait? (ilk eşleşen) */
    private static function factOf(string $line): ?string
    {
        $low = DescLearn::lower($line);
        foreach (['guarantee', 'speed_h', 'speed', 'max'] as $f) {
            foreach (self::LINE_FACT[$f] as $re) {
                if (preg_match($re, $low)) {
                    return $f;
                }
            }
        }
        if (preg_match('/\d+\s*-\s*\d+\s*(saat|dəq|deq|hour|min)/u', $low) && preg_match(DescLearn::KW['start'], $low)) {
            return 'start';
        }
        foreach (['lifetime', 'instant', 'nondrop', 'cancel', 'guarantee'] as $f) {
            if (preg_match(DescLearn::KW[$f], $low)) {
                return $f;
            }
        }
        return null;
    }

    /** Sayısız kalıp (ör. "🚀 Başlama: Ani"): grup kuralı, yoksa genel kural. */
    private static function plainRule(array $rules, string $group, string $fact): ?string
    {
        $tpl = $rules['groups'][$group][$fact]['tpl'] ?? $rules['*'][$fact]['tpl'] ?? null;
        return is_string($tpl) && !str_contains($tpl, '#') && !str_contains($tpl, '{v}') ? $tpl : null;
    }

    private static function negative(string $line): bool
    {
        return (bool)preg_match('/yox|olmaz|deyil|mümkün deyil|\bno\b|not available|yoxdur|❌|🚫/u', DescLearn::lower($line));
    }

    /**
     * Tek servis için öneri.
     * @param array{id:int,name:string,min:?int,max:?int,cancel:int|bool} $svc
     * @param list<string> $base temel açıklamanın satırları
     * @return array{text:string,notes:list<string>}
     */
    public static function build(array $svc, array $base, array $rules, string $group): array
    {
        $facts = DescLearn::facts($svc['name'], ['cancel' => !empty($svc['cancel'])]);
        $out = [];
        $notes = [];
        $used = [];
        foreach ($base as $line) {
            $f = self::factOf($line);
            $hasNum = (bool)preg_match('/\d/', $line);
            if ($f === null) {
                if (!$hasNum) {
                    $out[] = $line; // genel satır
                    continue;
                }
                $low = DescLearn::lower($line);
                if (preg_match('/min/u', $low) && $svc['min'] !== null) {
                    $l = self::setNum($line, 'min', (float)$svc['min']);
                    if ($l !== null) {
                        $out[] = $l;
                        continue;
                    }
                }
                if (isset(self::knownShapes($rules)[DescLearn::shape($line)])) {
                    $out[] = $line; // birçok açıklamada aynen geçen satır (ör. "eyni linkə 2-ci sifariş verməyin")
                    continue;
                }
                $notes[] = 'Çıkarıldı (neye ait olduğu bilinmeyen sayı): ' . $line;
                continue;
            }
            switch ($f) {
                case 'guarantee':
                case 'max':
                case 'speed':
                case 'speed_h':
                    $v = $facts[$f] ?? null;
                    if ($f === 'max' && $v === null && $svc['max'] !== null) {
                        $v = (float)$svc['max']; // addaki maks yoksa API'deki max
                    }
                    if ($f === 'guarantee' && $v === null) {
                        // Aynı yere: ömürlük ya da garantisiz satırı (öğrenilmiş kalıp varsa)
                        foreach (['lifetime', 'noguarantee'] as $alt) {
                            $tpl = self::plainRule($rules, $group, $alt);
                            if (!empty($facts[$alt]) && $tpl !== null) {
                                $out[] = $tpl;
                                $used[$alt] = true;
                                $notes[] = 'Değiştirildi (' . ($alt === 'lifetime' ? 'ömürlük' : 'garantisiz') . '): ' . $line . ' → ' . $tpl;
                                continue 3;
                            }
                        }
                        $notes[] = 'Çıkarıldı (adında garanti süresi yok): ' . $line;
                        continue 2;
                    }
                    if ($v === null) {
                        $notes[] = 'Çıkarıldı (adında yok): ' . $line;
                        continue 2;
                    }
                    $l = self::setNum($line, $f, (float)$v);
                    if ($f === 'max' && $svc['min'] !== null && $l !== null && preg_match('/min/iu', $l)) {
                        $l = self::setNum($l, 'min', (float)$svc['min']) ?? $l;
                    }
                    if ($l === null) {
                        $notes[] = 'Çıkarıldı (sayı değiştirilemedi): ' . $line;
                        continue 2;
                    }
                    $out[] = $l;
                    $used[$f] = true;
                    break;
                case 'start':
                    if (!isset($facts['start'])) {
                        $tpl = self::plainRule($rules, $group, 'instant');
                        if (!empty($facts['instant']) && $tpl !== null && !isset($used['instant'])) {
                            $out[] = $tpl;
                            $used['instant'] = true;
                            $notes[] = 'Değiştirildi (anında başlıyor): ' . $line . ' → ' . $tpl;
                            continue 2;
                        }
                        $ani = !empty($facts['instant']) && !isset($used['instant']) ? preg_replace('/\d+\s*-\s*\d+\s*[\p{L}.]*/u', 'Ani', $line, 1) : null;
                        if ($ani !== null && $ani !== $line) {
                            $out[] = $ani;
                            $used['instant'] = true;
                            $notes[] = 'Değiştirildi (adında “Ani”): ' . $line . ' → ' . $ani;
                            continue 2;
                        }
                        if (!empty($facts['instant'])) {
                            $notes[] = 'Çıkarıldı (servis anında başlıyor, süre yok): ' . $line;
                        } else {
                            $notes[] = 'Çıkarıldı (adında başlama süresi yok): ' . $line;
                        }
                        continue 2;
                    }
                    $out[] = preg_replace('/\d+\s*-\s*\d+/u', explode(' ', (string)$facts['start'])[0], $line, 1) ?? $line;
                    $used['start'] = true;
                    break;
                case 'cancel':
                    $neg = self::negative($line);
                    if ($neg === !empty($facts['cancel'])) {
                        $notes[] = 'Çıkarıldı (iptal durumu farklı): ' . $line;
                        continue 2;
                    }
                    $out[] = $line;
                    $used['cancel'] = true;
                    break;
                default: // lifetime, instant, nondrop
                    if (empty($facts[$f])) {
                        $notes[] = 'Çıkarıldı (adında yok): ' . $line;
                        continue 2;
                    }
                    $out[] = $line;
                    $used[$f] = true;
            }
        }
        // Adında olup temelde satırı olmayan bilgiler: öğrenilmiş kalıpla ekle
        foreach ($facts as $f => $v) {
            if (isset($used[$f]) || $f === 'cancel') {
                continue;
            }
            $r = $rules['groups'][$group][$f] ?? $rules['*'][$f] ?? null;
            if (!$r) {
                continue;
            }
            $tpl = (string)$r['tpl'];
            if (str_contains($tpl, '#') && !str_contains($tpl, '{v}')) {
                if ($f === 'start' && is_string($v)) {
                    $line = preg_replace('/#\s*-\s*#/u', explode(' ', $v)[0], $tpl, 1) ?? $tpl;
                } else {
                    continue; // başka sayı ister, bilinmiyor
                }
            } else {
                $line = str_replace('{v}', is_float($v) ? DescLearn::fmt($v) : (string)$v, $tpl);
                if (str_contains($line, '#')) {
                    if ($f === 'max' && $svc['min'] !== null) {
                        $line = preg_replace('/#/', (string)$svc['min'], $line, 1) ?? $line;
                    }
                    if (str_contains($line, '#')) {
                        continue;
                    }
                }
            }
            $out[] = $line;
            $notes[] = 'Eklendi (adında var, öğrenilmiş kalıp): ' . $line;
        }
        $out = array_values(array_unique($out));
        $out = self::enrich($out, $svc, $rules, $group, $notes);
        foreach (DescLearn::check($facts, $out, $svc['name']) as $p) {
            if ($p['kind'] === 'conflict') {
                $notes[] = '⚠ Denetim: ' . $p['msg'];
            }
        }
        return ['text' => implode("\n", $out), 'notes' => $notes];
    }

    /** Öğrenilmiş ortak satırların kalıpları (sayılı olsa da korunur). */
    private static function knownShapes(array $rules): array
    {
        $k = [];
        foreach (array_merge($rules['common'] ?? [], ...array_values($rules['type_lines'] ?? [])) as $l) {
            $k[$l['shape']] = true;
        }
        return $k;
    }

    /** Satırdaki başka platform adını bu servisin platformuyla değiştir (örnek başka platformdan alındıysa). */
    public static function swapPlatform(string $line, string $platform): string
    {
        if ($platform === '' || $platform === 'diğer') {
            return $line;
        }
        foreach (Matcher::PLATFORMS as $p) {
            if ($p !== $platform && in_array($p, ['google', 'kick'], true) === false) {
                $line = preg_replace('/(?<![\p{L}])' . preg_quote(DescLearn::pname($p), '/') . '(?![\p{L}])/iu', DescLearn::pname($platform), $line) ?? $line;
            }
        }
        return $line;
    }

    /**
     * Dolgunlaştır: türün sık satırları (Link: …), bütün açıklamalardaki ortak uyarılar ve Min/Maks (API'den) eksikse eklenir.
     * Satırlar öğrenildikleri ortalama konuma yerleşir.
     */
    private static function enrich(array $out, array $svc, array $rules, string $group, array &$notes): array
    {
        [$plat, $type] = array_pad(explode('|', $group), 2, 'other');
        $have = [];
        $keys = [];
        // "🔗 Link: Profil linki" ile "🔗 Link: Paylaşım linki" aynı başlık: ikincisi eklenmez
        $key = static fn(string $l): string => str_contains($l, ':') ? trim(preg_replace('/[^\p{L}\p{N} ]+/u', '', DescLearn::lower(explode(':', $l, 2)[0])) ?? '') : '';
        foreach ($out as $l) {
            $have[DescLearn::shape($l)] = true;
            if ($key($l) !== '') {
                $keys[$key($l)] = true;
            }
        }
        $add = [];
        foreach ([[$rules['type_lines'][$type] ?? [], 'türün sık satırı'], [$rules['common'] ?? [], 'bütün açıklamalarda ortak']] as [$list, $why]) {
            foreach ($list as $l) {
                if (isset($have[$l['shape']]) || ($key($l['line']) !== '' && isset($keys[$key($l['line'])])) || self::factOf($l['line']) !== null || preg_match('/\b(min|maks|max)\b/iu', $l['line'])) {
                    continue;
                }
                $line = self::swapPlatform($l['line'], $plat);
                $have[$l['shape']] = true;
                if ($key($line) !== '') {
                    $keys[$key($line)] = true;
                }
                $add[] = [$line, (float)$l['pos']];
                $notes[] = 'Eklendi (' . $why . ', %' . round($l['share'] * 100) . '): ' . $line;
            }
        }
        $hasMinMax = (bool)array_filter($out, static fn($l) => preg_match('/(?<![\p{L}])(min|maks|max)/iu', $l));
        if (!$hasMinMax && ($svc['min'] !== null || $svc['max'] !== null)) {
            $tpl = (string)($rules['*']['max']['tpl'] ?? '');
            $mn = $svc['min'] !== null ? DescLearn::fmt((float)$svc['min']) : '';
            $mx = $svc['max'] !== null ? DescLearn::fmt((float)$svc['max']) : '';
            if ($tpl !== '' && str_contains($tpl, '{v}') && preg_match('/min/iu', $tpl) && substr_count($tpl, '#') === 1 && $mn !== '' && $mx !== '') {
                $line = str_replace(['#', '{v}'], [$mn, $mx], $tpl);
            } else {
                $line = '📏 Min: ' . ($mn ?: '-') . ' / Maks: ' . ($mx ?: '-');
            }
            $add[] = [$line, 0.6];
            $notes[] = 'Eklendi (Admin API min/max): ' . $line;
        }
        foreach ($add as [$line, $pos]) {
            array_splice($out, (int)round($pos * count($out)), 0, [$line]);
        }
        return $out;
    }

    /** Kategorideki temel açıklama: o kategorideki açıklamalardan yapısı en tipik olan. @return list<string> */
    private function baseFor(int $catId, array $rules, string $group, string &$src): array
    {
        $descs = array_map(static fn($r) => DescLearn::lines((string)$r['description']), $this->db->all("SELECT description FROM panel_desc WHERE category_id = ? AND description != '' ORDER BY status = 'enabled' DESC, id DESC LIMIT 60", [$catId]));
        if ($descs) {
            $count = [];
            foreach ($descs as $d) {
                foreach (array_unique(array_map([DescLearn::class, 'shape'], $d)) as $sh) {
                    $count[$sh] = ($count[$sh] ?? 0) + 1;
                }
            }
            $best = $descs[0];
            $bestScore = -1;
            foreach ($descs as $d) {
                $sc = array_sum(array_map(static fn($l) => $count[DescLearn::shape($l)] ?? 0, $d));
                if ($sc > $bestScore) {
                    [$best, $bestScore] = [$d, $sc];
                }
            }
            $src = 'bu kategorideki açıklaman (' . count($descs) . ' açıklamadan en tipik olanı)';
            return $best;
        }
        if (!empty($rules['examples'][$group])) {
            $src = 'aynı platform ve türün örnek açıklaması (' . str_replace('|', ' · ', $group) . ')';
            return $rules['examples'][$group];
        }
        [$plat, $type] = array_pad(explode('|', $group), 2, 'other');
        if (!empty($rules['type_examples'][$type])) {
            $src = 'aynı türün (' . $type . ') en dolgun açıklaması, platform adı uyarlandı';
            return array_map(static fn($l) => self::swapPlatform($l, $plat), $rules['type_examples'][$type]);
        }
        $src = 'yalnızca öğrenilmiş kurallar (benzer açıklama yok)';
        return [];
    }

    /**
     * Kategorideki açıklamasız servisler için öneri üret (onaylanmış/yazılmış öneriler korunur).
     * @return array{made:int,skipped:int,kept:int,base:string}
     */
    /** @param list<int>|null $sids yalnızca bu servisler (null: kategorideki bütün açıklamasızlar) */
    public function generate(int $catId, ?array $sids = null): array
    {
        $rules = $this->db->getJson(DescLearn::RULES);
        if (!is_array($rules) || empty($rules['*'])) {
            throw new \InvalidArgumentException('Önce Açıklamalar → Panelden oku (ya da paneldeki açıklamalı servislerden kalıp çıkarılamadı: birkaç serviste elle açıklama yazıp yeniden oku).');
        }
        $rows = $this->db->all("SELECT * FROM panel_desc WHERE category_id = ? AND description = '' ORDER BY id", [$catId]);
        if ($sids !== null) {
            $want = array_flip(array_map('intval', $sids));
            $rows = array_values(array_filter($rows, static fn($r) => isset($want[(int)$r['id']])));
        }
        if (!$rows) {
            throw new \InvalidArgumentException('Bu kategoride açıklamasız servis yok (son öğrenmeye göre).');
        }
        $made = $skipped = $kept = 0;
        $src = '';
        $now = time();
        foreach ($rows as $r) {
            $cur = $this->db->one('SELECT status FROM desc_props WHERE sid = ?', [(int)$r['id']]);
            if ($cur && in_array($cur['status'], ['approved', 'applied', 'verified'], true)) {
                $kept++;
                continue;
            }
            $group = (Matcher::platform($r['name'] . ' ' . $r['category_name']) ?: 'diğer') . '|' . DescLearn::type($r['name'] . ' ' . $r['category_name']);
            $base = $this->baseFor($catId, $rules, $group, $src);
            $p = self::build(['id' => (int)$r['id'], 'name' => (string)$r['name'], 'min' => $r['min'] !== null ? (int)$r['min'] : null, 'max' => $r['max'] !== null ? (int)$r['max'] : null, 'cancel' => (int)$r['cancel']], $base, $rules, $group);
            if (trim($p['text']) === '') {
                $skipped++;
                $p['notes'][] = 'Öneri boş: adında ve temelde kullanılabilir bilgi yok';
            }
            $this->db->run('INSERT INTO desc_props(sid, category_id, category_name, name, text, base, notes, status, error, attempts, created_at, updated_at) VALUES(?,?,?,?,?,?,?,?,?,0,?,?)
                ON CONFLICT(sid) DO UPDATE SET category_id = excluded.category_id, category_name = excluded.category_name, name = excluded.name, text = excluded.text, base = excluded.base,
                notes = excluded.notes, status = excluded.status, error = \'\', attempts = 0, updated_at = excluded.updated_at',
                [(int)$r['id'], $catId, (string)$r['category_name'], (string)$r['name'], $p['text'], $src, json_encode($p['notes'], JSON_UNESCAPED_UNICODE), trim($p['text']) === '' ? 'rejected' : 'proposed', '', $now, $now]);
            $made++;
        }
        $this->db->log('info', 'bank', "Açıklama önerisi: kategori #$catId, $made öneri" . ($kept ? ", $kept onaylı korundu" : ''));
        return ['made' => $made - $skipped, 'skipped' => $skipped, 'kept' => $kept, 'base' => $src];
    }

    public const ORDER = 'panel_cat_order';

    /** Sitedeki kategori sırası (eklenti gönderir): normalize ad → sıra. En az 3 kategori gerekir (yarım liste yazılmasın). */
    public static function saveOrder(Db $db, array $names): int
    {
        $map = [];
        foreach (array_slice($names, 0, 5000) as $n) {
            $k = self::catKey((string)$n);
            if ($k !== '' && !isset($map[$k])) {
                $map[$k] = count($map) + 1;
            }
        }
        if (count($map) < 3) {
            return 0;
        }
        $db->setJson(self::ORDER, ['at' => time(), 'map' => $map]);
        return count($map);
    }

    /** Sitedeki kategori sırası, kategori ID'leriyle (panelin herkese açık /services sayfası). */
    public static function saveOrderIds(Db $db, array $ids, string $from): int
    {
        $map = [];
        foreach (array_slice($ids, 0, 5000) as $id) {
            $id = (int)$id;
            if ($id > 0 && !isset($map[$id])) {
                $map[$id] = count($map) + 1;
            }
        }
        if (count($map) < 3) {
            return 0;
        }
        $old = $db->getJson(self::ORDER);
        $db->setJson(self::ORDER, ['at' => time(), 'ids' => $map, 'from' => $from] + (is_array($old) && !empty($old['map']) ? ['map' => $old['map']] : []));
        return count($map);
    }

    /** Sayfa HTML'inden kategori ID'leri (Perfect Panel: data-filter-table-category-id="12"), sırayla. */
    public static function parseOrderIds(string $html): array
    {
        preg_match_all('/data-filter-table-category-id\s*=\s*["\']?(\d+)/i', $html, $m);
        return array_values(array_unique(array_map('intval', $m[1] ?? [])));
    }

    /** Panelin /services sayfasını sunucudan okuyup sırayı kaydeder; olmazsa 0 (eklenti tarayıcıdan dener). */
    public static function fetchSiteOrder(Db $db, array $config): int
    {
        $u = parse_url((string)($config['panel_api_url'] ?? ''));
        if (empty($u['host'])) {
            return 0;
        }
        try {
            $r = Http::get(($u['scheme'] ?? 'https') . '://' . $u['host'] . '/services', 20);
            return $r['status'] === 200 ? self::saveOrderIds($db, self::parseOrderIds($r['body']), 'sunucu') : 0;
        } catch (\Throwable) {
            return 0;
        }
    }

    public static function catKey(string $n): string
    {
        return mb_strtolower(trim(preg_replace('/\s+/u', ' ', $n) ?? $n));
    }

    /**
     * Açıklamasızı olan kategoriler, sitedeki sırayla (eklenti göndermişse), yoksa Admin API listesindeki sırayla.
     * @return array{cats:list<array>,source:string}
     */
    public function emptyCategories(): array
    {
        $cats = $this->db->all("SELECT category_id, MAX(category_name) category_name, COUNT(*) n, SUM(description = '') e, SUM(description = '' AND status = 'enabled') eon, MIN(pos) pos
            FROM panel_desc GROUP BY category_id HAVING e > 0");
        $order = $this->db->getJson(self::ORDER);
        $map = is_array($order) && !empty($order['map']) ? $order['map'] : [];
        $ids = is_array($order) && !empty($order['ids']) ? $order['ids'] : [];
        $hit = 0;
        foreach ($cats as &$c) {
            $c['site'] = $ids[(int)$c['category_id']] ?? $ids[(string)$c['category_id']] ?? $map[self::catKey((string)$c['category_name'])] ?? null;
            $hit += $c['site'] !== null ? 1 : 0;
        }
        unset($c);
        usort($cats, static fn($a, $b) => [$a['site'] === null ? 1 : 0, $a['site'] ?? 0, (int)$a['pos'], (int)$a['category_id']] <=> [$b['site'] === null ? 1 : 0, $b['site'] ?? 0, (int)$b['pos'], (int)$b['category_id']]);
        return ['cats' => $cats, 'source' => $hit ? 'site' : 'api', 'site_at' => (int)($order['at'] ?? 0), 'from' => (string)($order['from'] ?? 'eklenti')];
    }

    public function setStatus(array $sids, string $status, ?string $text = null): int
    {
        $n = 0;
        foreach (array_map('intval', $sids) as $sid) {
            if ($text !== null) {
                $n += $this->db->run("UPDATE desc_props SET text = ?, status = ?, error = '', attempts = 0, updated_at = ? WHERE sid = ? AND status NOT IN ('verified')", [$text, $status, time(), $sid]);
            } else {
                $n += $this->db->run("UPDATE desc_props SET status = ?, error = '', attempts = 0, updated_at = ? WHERE sid = ? AND status NOT IN ('verified')", [$status, time(), $sid]);
            }
        }
        return $n;
    }

    /** Eklentiden vazgeç: onaylı (henüz yazılmamış) açıklamalar öneriye döner. @param list<int>|null $sids null: hepsi */
    public function cancel(?array $sids): int
    {
        if ($sids === null) {
            $n = $this->db->run("UPDATE desc_props SET status = 'proposed', error = '', attempts = 0, updated_at = ? WHERE status = 'approved'", [time()]);
        } else {
            $n = 0;
            foreach (array_slice(array_map('intval', $sids), 0, 500) as $sid) {
                $n += $this->db->run("UPDATE desc_props SET status = 'proposed', error = '', attempts = 0, updated_at = ? WHERE sid = ? AND status = 'approved'", [time(), $sid]);
            }
        }
        if ($n) {
            $this->db->log('info', 'bank', "Eklentiden vazgeçildi: $n açıklama öneriye döndü");
        }
        return $n;
    }

    /** Eklentinin yazacakları. @return list<array{sid:int,text:string,category:string,name:string}> */
    public function queue(int $limit = 50): array
    {
        return array_map(static fn($r) => ['sid' => (int)$r['sid'], 'text' => (string)$r['text'], 'category' => (string)$r['category_name'], 'name' => (string)$r['name']],
            $this->db->all("SELECT sid, text, category_name, name FROM desc_props WHERE status = 'approved' AND attempts < 3 ORDER BY category_id, sid LIMIT " . max(1, min(200, $limit))));
    }

    public function queueCount(): array
    {
        $r = $this->db->all("SELECT category_name, COUNT(*) n FROM desc_props WHERE status = 'approved' AND attempts < 3 GROUP BY category_id ORDER BY n DESC");
        return ['count' => array_sum(array_column($r, 'n')), 'categories' => array_map(static fn($x) => ['name' => (string)$x['category_name'], 'n' => (int)$x['n']], array_slice($r, 0, 5))];
    }

    /**
     * Eklenti raporu: yazılanlar Admin API ile panelden okunup doğrulanır.
     * @param list<array{sid:int,ok:bool,error?:string}> $items
     * @return array{verified:int,applied:int,failed:int}
     */
    public function done(array $items, array $config): array
    {
        $ok = [];
        $failed = 0;
        foreach ($items as $it) {
            $sid = (int)($it['sid'] ?? 0);
            if (!$sid) {
                continue;
            }
            if (!empty($it['ok'])) {
                $ok[] = $sid;
                $this->db->run("UPDATE desc_props SET status = 'applied', error = '', updated_at = ? WHERE sid = ? AND status = 'approved'", [time(), $sid]);
            } else {
                $failed++;
                // final: tekrar denemenin anlamı yok (ör. panelde zaten açıklama var) → hemen "hatalı"
                $this->db->run("UPDATE desc_props SET attempts = attempts + 1, error = ?, status = CASE WHEN attempts + 1 >= CAST(? AS INTEGER) THEN 'failed' ELSE status END, updated_at = ? WHERE sid = ? AND status = 'approved'",
                    [mb_substr((string)($it['error'] ?? 'eklenti yazamadı'), 0, 200), empty($it['final']) ? 3 : 0, time(), $sid]);
            }
        }
        $verified = 0;
        $vfail = 0;
        $api = new PanelServices($this->db, $config);
        if ($ok && $api->configured()) {
            try {
                $active = $api->activeLangs($ok[0]);
                $nd = $api->namesDescriptions($ok, in_array('az', $active, true) ? 'az' : '');
                $norm = static fn(string $s): string => trim(preg_replace('/\s+/u', ' ', html_entity_decode(strip_tags(str_ireplace(['<br>', '<br/>', '<br />', '</p>'], "\n", $s)), ENT_QUOTES | ENT_HTML5, 'UTF-8')) ?? $s);
                foreach ($ok as $sid) {
                    $want = (string)($this->db->one('SELECT text FROM desc_props WHERE sid = ?', [$sid])['text'] ?? '');
                    $row = $nd[$sid] ?? [];
                    $got = (string)(($row['az'] ?? ($row ? reset($row) : []))['description'] ?? '');
                    if ($got !== '' && $norm($got) === $norm($want)) {
                        $this->db->run("UPDATE desc_props SET status = 'verified', updated_at = ? WHERE sid = ?", [time(), $sid]);
                        $this->db->run('UPDATE panel_desc SET description = ? WHERE id = ?', [$got, $sid]);
                        $verified++;
                    } else {
                        $this->db->run("UPDATE desc_props SET status = 'failed', error = ?, updated_at = ? WHERE sid = ?", [$got === '' ? 'panelde açıklama hâlâ boş' : 'panelde farklı metin var', time(), $sid]);
                        $vfail++;
                    }
                }
            } catch (\Throwable $e) {
                $this->db->log('warn', 'bank', 'Açıklama doğrulaması yapılamadı: ' . $e->getMessage());
            }
        }
        $this->db->log('info', 'bank', 'Eklenti açıklama yazdı: ' . count($ok) . " servis, $verified doğrulandı, " . ($failed + $vfail) . ' hata');
        return ['verified' => $verified, 'applied' => count($ok) - $verified - $vfail, 'failed' => $failed + $vfail];
    }
}
