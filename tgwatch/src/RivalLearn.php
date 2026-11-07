<?php
declare(strict_types=1);

namespace TgWatch;

/**
 * Rakipten öğrenme: rakip panelin (Azerice) servislerini sağlayıcıların (İngilizce/Türkçe) servisleriyle eşleştirir,
 * eşleşen çiftlerden kelime/ifade önerisi çıkarır ("Refill" → "Bərpa", 70/80 eşleşmede).
 *
 * Rakip servislerini çoğunlukla aynı sağlayıcılardan alır; ad çevrilse de şunlar değişmez:
 *   min/max, addaki sayılar, platform (Instagram, TikTok…), refill/cancel, emoji; fiyat ≈ a × sağlayıcı + b.
 * Ek olarak sağlayıcı adı mevcut kurallarla Azericeye çevrilip rakibin adıyla kelime kelime karşılaştırılır.
 *
 * Yalnızca emin olunan çiftler kullanılır. Öneriler bankaya/sözlüğe kendiliğinden girmez: kullanıcı "Uygula" der.
 * Yalnızca sözlük terimi ve sayılı kalıp önerilir (ad biçimi, silinen parçalar rakibin üslubudur, alınmaz).
 * Sözlükte zaten karşılığı olan ifadeye öneri yapılmaz: senin kararın her zaman önce gelir. Banka da kurallardan önce gelir.
 */
final class RivalLearn
{
    public const SURE = 0.7;
    public const GAP = 0.1;
    private const DECIDED = 'rival_learn_decided';
    private const REPORT = 'rival_learn_report';
    private const EMOJI = '/[\x{1F000}-\x{1FAFF}\x{2600}-\x{27BF}\x{2B00}-\x{2BFF}]/u';
    private const STOP = ['max', 'maks', 'min', 'per', 'day', 'days', 'gün', 'gündə', 'saatda', 'the', 'and', 'for', 'with', 'of', 'to', 'in', 'k', 'm'];

    public function __construct(private Db $db)
    {
    }

    /** @return array{rivals:int,rival_services:int,providers:int,provider_services:int} */
    public function readiness(): array
    {
        $c = static fn(array $r) => (int)($r['c'] ?? 0);
        return [
            'rivals' => $c($this->db->one("SELECT COUNT(*) c FROM sources WHERE kind = 'rival'")),
            'rival_services' => $c($this->db->one("SELECT COUNT(*) c FROM remote_services r JOIN sources s ON s.id = r.source_id WHERE s.kind = 'rival'")),
            'providers' => $c($this->db->one("SELECT COUNT(*) c FROM sources WHERE kind = 'provider'")),
            'provider_services' => $c($this->db->one("SELECT COUNT(*) c FROM remote_services r JOIN sources s ON s.id = r.source_id WHERE s.kind = 'provider'")),
        ];
    }

    private static function tokens(string $s): array
    {
        $s = mb_strtolower(NameTemplate::stripEmoji(Normalizer::text($s)));
        $w = preg_split('/[^\p{L}\p{N}]+/u', $s) ?: [];
        return array_values(array_unique(array_filter($w, static fn($x) => $x !== '' && mb_strlen($x) >= 2 && !preg_match('/^\d/u', $x) && !in_array($x, self::STOP, true))));
    }

    private static function jac(array $a, array $b, float $empty = 0.0): float
    {
        if (!$a && !$b) {
            return $empty;
        }
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

    /**
     * Rakip ↔ sağlayıcı eşleşmeleri.
     * @return array{pairs:list<array>,per:array<int,array>,unsure:int,none:int}
     */
    public function match(): array
    {
        $prov = $this->db->all("SELECT r.*, s.name AS source_name FROM remote_services r JOIN sources s ON s.id = r.source_id WHERE s.kind = 'provider'");
        $rivals = $this->db->all("SELECT * FROM sources WHERE kind = 'rival' ORDER BY id");
        if (!$prov) {
            throw new \RuntimeException('Önce Çeviri bankası → Kaynaklar\'dan sağlayıcı ekleyip senkronize et (rakip servisleri sağlayıcı servisleriyle eşleştirilir).');
        }
        if (!$rivals) {
            throw new \RuntimeException('Önce Analiz → Rakip listesi\'nden rakip panel ekle.');
        }
        $dict = Dictionary::load($this->db);
        $tr = new Translator(array_merge($dict, ['options' => []]));
        $byMinMax = [];
        $byMax = [];
        foreach ($prov as $i => $p) {
            $name = (string)$p['name'];
            $az = implode(' ', array_map(static fn($seg) => $tr->translate($seg)['text'], Memory::splitSegments($name)));
            $prov[$i] += ['_nums' => Matcher::nums($name), '_plat' => Matcher::platform($name . ' ' . $p['category']),
                '_tok' => self::tokens($name), '_az' => self::tokens($az), '_emo' => self::emojis($name)];
            if ($p['min'] !== null && $p['max'] !== null) {
                $byMinMax[$p['min'] . '|' . $p['max']][] = $i;
                $byMax[(string)$p['max']][] = $i;
            }
        }
        $pairs = [];
        $per = [];
        $unsure = $none = 0;
        foreach ($rivals as $rv) {
            $rows = $this->db->all('SELECT * FROM remote_services WHERE source_id = ?', [(int)$rv['id']]);
            $cands = [];
            foreach ($rows as $r) {
                if ($r['min'] === null || $r['max'] === null || trim((string)$r['name']) === '') {
                    $none++;
                    continue;
                }
                $name = (string)$r['name'];
                $r += ['_nums' => Matcher::nums($name), '_plat' => Matcher::platform($name . ' ' . $r['category']), '_tok' => self::tokens($name), '_emo' => self::emojis($name)];
                $exact = $byMinMax[$r['min'] . '|' . $r['max']] ?? [];
                $list = [];
                foreach ($exact ?: ($byMax[(string)$r['max']] ?? []) as $i) {
                    $p = $prov[$i];
                    if ($r['_plat'] !== '' && $p['_plat'] !== '' && $r['_plat'] !== $p['_plat']) {
                        continue;
                    }
                    $list[] = $i;
                }
                $cands[] = [$r, $list, $exact ? 1.0 : 0.85];
            }
            // fiyat modeli (rakibin kâr oranı ve kuru): tek adaylı, sayıları uyumlu eşleşmelerden
            $pts = [];
            foreach ($cands as [$r, $list]) {
                if (count($list) === 1 && $r['rate'] > 0 && $prov[$list[0]]['rate'] > 0 && self::jac($r['_nums'], $prov[$list[0]]['_nums'], 1.0) >= 0.5) {
                    $pts[] = [(float)$prov[$list[0]]['rate'], (float)$r['rate']];
                }
            }
            $model = Matcher::fit($pts);
            $n = 0;
            foreach ($cands as [$r, $list, $mm]) {
                $scored = [];
                foreach ($list as $i) {
                    $p = $prov[$i];
                    $price = 0.5;
                    if ($model['mode'] !== 'none' && $r['rate'] > 0 && $p['rate'] > 0) {
                        $pred = $model['a'] * $p['rate'] + $model['b'];
                        $price = max(0.0, 1 - abs($pred - $r['rate']) / max(0.15 * $r['rate'], 1e-9));
                    }
                    $flags = 1.0;
                    foreach (['refill', 'cancel'] as $f) {
                        if ($r[$f] !== null && $p[$f] !== null && (int)$r[$f] !== (int)$p[$f]) {
                            $flags -= 0.5;
                        }
                    }
                    $plat = ($r['_plat'] !== '' && $r['_plat'] === $p['_plat']) ? 1.0 : 0.5;
                    // ad benzerliği: kurallarla çevrilmiş sağlayıcı adı ↔ rakip adı (ve aynen kalan kelimeler)
                    $name = max(self::jac($p['_az'], $r['_tok']), 0.8 * self::jac($p['_tok'], $r['_tok']));
                    if ($p['_emo'] && $p['_emo'] === $r['_emo']) {
                        $name = min(1.0, $name + 0.15);
                    }
                    $s = $mm * (0.3 * $price + 0.25 * self::jac($r['_nums'], $p['_nums'], 0.6) + 0.25 * $name + 0.1 * $flags + 0.1 * $plat);
                    $scored[] = [$s, $i];
                }
                usort($scored, static fn($a, $b) => $b[0] <=> $a[0]);
                $best = $scored[0] ?? null;
                if ($best === null || $best[0] < 0.5) {
                    $none++;
                    continue;
                }
                // İkinci aday aynı adı taşıyorsa (iki sağlayıcı aynı servisi satıyor) öğrenme açısından belirsizlik yok.
                $rival2 = null;
                foreach (array_slice($scored, 1) as [$sc, $j]) {
                    if (Memory::norm((string)$prov[$j]['name']) !== Memory::norm((string)$prov[$best[1]]['name'])) {
                        $rival2 = $sc;
                        break;
                    }
                }
                if ($best[0] < self::SURE || $best[0] - ($rival2 ?? 0.0) < self::GAP) {
                    $unsure++;
                    continue;
                }
                $p = $prov[$best[1]];
                $pairs[] = ['en' => (string)$p['name'], 'az' => (string)$r['name'], 'score' => round($best[0], 3),
                    'rival' => (string)$rv['name'], 'rival_service' => (int)$r['service_id'], 'provider' => (string)$p['source_name'], 'provider_service' => (int)$p['service_id']];
                $n++;
            }
            $per[(int)$rv['id']] = ['name' => (string)$rv['name'], 'services' => count($rows), 'matched' => $n, 'model' => $model];
        }
        return ['pairs' => $pairs, 'per' => $per, 'unsure' => $unsure, 'none' => $none];
    }

    /** Eşleştir + öneri çıkar; sonucu veritabanına yazar. */
    public function run(): array
    {
        @set_time_limit(180);
        $m = $this->match();
        $dict = Dictionary::load($this->db);
        // Aynı rakip adı birden çok sağlayıcıyla eşleşebilir: her (en, az) çifti bir kez sayılır.
        $uniq = [];
        foreach ($m['pairs'] as $p) {
            $uniq[Memory::norm($p['en']) . "\n" . Memory::norm($p['az'])] ??= ['en' => $p['en'], 'az' => $p['az']];
        }
        $sug = [];
        if ($uniq) {
            $a = (new Learner($this->db))->analyze(array_values($uniq), $dict);
            foreach ($a['suggestions'] as $s) {
                if (!in_array($s['kind'], ['phrase', 'rule'], true)) {
                    continue; // ad biçimi ve silinen parçalar rakibin üslubu
                }
                if ($s['kind'] === 'phrase' && isset($dict['phrases'][$s['payload']['en']])) {
                    continue; // sözlükte senin karşılığın var: senin kararın önce gelir
                }
                if (!preg_match('/\p{L}/u', (string)($s['payload']['az'] ?? $s['payload']['replace'] ?? ''))) {
                    continue;
                }
                $s['detail'] = str_replace(['tanesinde böyle çevirmişsin', 'kez geçmiş'], ['tanesinde rakip böyle çevirmiş', 'eşleşmede geçmiş'], $s['detail']);
                $sug[] = $s;
            }
        }
        usort($m['pairs'], static fn($x, $y) => $y['score'] <=> $x['score']);
        $rep = ['at' => time(), 'pairs' => count($uniq), 'unsure' => $m['unsure'], 'none' => $m['none'], 'per' => $m['per'],
            'sample' => array_slice($m['pairs'], 0, 40), 'suggestions' => $sug];
        $this->db->setJson(self::REPORT, $rep);
        $this->db->log('info', 'bank', 'Rakipten öğren: ' . count($uniq) . ' eşleşme, ' . count($sug) . ' öneri');
        return $rep;
    }

    public function report(): ?array
    {
        $r = $this->db->getJson(self::REPORT);
        return is_array($r) ? $r : null;
    }

    public function find(string $id): ?array
    {
        foreach ($this->report()['suggestions'] ?? [] as $s) {
            if ($s['id'] === $id) {
                return $s;
            }
        }
        return null;
    }

    /** @return array<string,string> id => applied|rejected */
    public function decided(): array
    {
        $d = $this->db->getJson(self::DECIDED, []);
        return is_array($d) ? $d : [];
    }

    public function decide(string $id, string $how): void
    {
        $d = $this->decided();
        $d[$id] = $how;
        $this->db->setJson(self::DECIDED, $d);
    }

    /** Öneriyi sözlüğe işler. */
    public function apply(array $s): void
    {
        Dictionary::save($this->db, Learner::applyTo(Dictionary::load($this->db), $s));
        $this->decide($s['id'], 'applied');
    }
}
