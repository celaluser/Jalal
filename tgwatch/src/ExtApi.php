<?php
declare(strict_types=1);

namespace TgWatch;

/**
 * Chrome eklentisinin konuştuğu küçük API (public/api.php). Anahtar: Ayarlar → Özellikler → Eklenti anahtarı.
 *   ping       → bağlantı testi
 *   translate  → {items:[{id,name,kind?}]} → her ad için banka + kurallarla Azerice ad (botun kullandığı motorun aynısı);
 *                kind = "category" ise kategori bankası kullanılır, ad kalıbı uygulanmaz
 *   learn      → {items:[{id,src,az,edited,kind?}]} → Perfect Panel'de import edilen son adlar (ve kategori adları) bankaya öğretilir
 *   teach      → {en, az} → sözlüğe terim ekler (bilinmeyen kelimeyi satırda düzeltme); {en, keep:true} → "olduğu gibi kalsın" listesine
 *   balance    → sağlayıcı bakiyeleri (bot /balans ile aynı)
 *   imported   → import bitti: bota özet, kanal paylaşımı (ImportShare)
 */
final class ExtApi
{
    public const MAX_ITEMS = 1000;

    public function __construct(private Db $db, private array $config, private ?Telegram $tg = null)
    {
    }

    /** Anahtar doğru mu (sabit zamanlı karşılaştırma). */
    public static function authorized(array $config, string $given): bool
    {
        $key = (string)($config['ext_key'] ?? '');
        return strlen($key) >= 16 && $given !== '' && hash_equals($key, $given);
    }

    /** @return array{0:int,1:array} [HTTP kodu, JSON gövdesi] */
    public function handle(array $req): array
    {
        $action = (string)($req['action'] ?? '');
        try {
            return match ($action) {
                'ping' => [200, ['ok' => true, 'version' => VERSION, 'panel' => (string)($this->config['panel_name'] ?? 'tgwatch'), 'ext' => self::extVersion()]],
                'translate' => [200, ['ok' => true, 'items' => $this->translate(self::items($req))]],
                'learn' => [200, ['ok' => true] + $this->learn(self::items($req))],
                'teach' => [200, ['ok' => true] + $this->teach($req)],
                'balance' => [200, ['ok' => true] + Balances::check($this->db)],
                'imported' => [200, ['ok' => true] + (new ImportShare($this->db, $this->tg ?? App::telegram(), $this->config))->report($req)],
                default => [400, ['ok' => false, 'error' => 'Bilinmeyen işlem']],
            };
        } catch (\InvalidArgumentException $e) {
            return [400, ['ok' => false, 'error' => $e->getMessage()]];
        }
    }

    private static function items(array $req): array
    {
        $items = $req['items'] ?? null;
        if (!is_array($items) || !array_is_list($items)) {
            throw new \InvalidArgumentException('items listesi gerekli');
        }
        if (count($items) > self::MAX_ITEMS) {
            throw new \InvalidArgumentException('En çok ' . self::MAX_ITEMS . ' servis gönderilebilir');
        }
        return $items;
    }

    /** @return list<array{id:string,az:string,via:string,unknown:list<string>}> */
    private function translate(array $items): array
    {
        $dict = Dictionary::load($this->db);
        $engine = new Engine($this->db, $dict);
        $rules = new Translator(array_merge($dict, ['options' => []]));
        $out = [];
        $forAi = []; // Claude'a gidebilecek servis adları: $out indeksi => iş öğesi
        foreach ($items as $it) {
            if (!is_array($it)) {
                continue;
            }
            $name = trim((string)($it['name'] ?? ''));
            $id = (string)($it['id'] ?? '');
            if ($name === '' || mb_strlen($name) > 500) {
                $out[] = ['id' => $id, 'az' => '', 'via' => 'none', 'unknown' => []];
                continue;
            }
            if (preg_match('/[əƏ]/u', $name)) { // Azericeye özgü harf: ad zaten çevrilmiş
                $out[] = ['id' => $id, 'az' => '', 'via' => 'already', 'unknown' => []];
                continue;
            }
            if (($it['kind'] ?? '') === 'category') { // kategori adı: ad kalıbı uygulanmaz, kategori bankası
                $clean = Engine::stripBy($dict['remove'] ?? [], $name);
                $hit = $engine->memory()->lookup('category', $clean);
                $rr = $hit === null ? $rules->translate($clean) : ['text' => $hit, 'unknown' => []];
                $none = Learner::same($rr['text'], $clean);
                $out[] = ['id' => $id, 'az' => $none ? '' : $rr['text'], 'via' => $none ? 'none' : ($hit !== null ? 'bank' : 'rules'), 'unknown' => $rr['unknown']];
                continue;
            }
            $r = $engine->translate($name);
            // Kurallar adın kendisine hiç dokunmadıysa (yalnızca ad kalıbı ön/son ek ekledi) çevrilmemiş sayılır.
            $clean = Engine::stripBy($dict['remove'] ?? [], $name);
            $untouched = $r['via'] === 'rules' && Learner::same($rules->translate($clean)['text'], $clean);
            $none = $untouched || Learner::same($r['text'], $name);
            $out[] = ['id' => $id, 'az' => $none ? '' : $r['text'], 'via' => $none ? 'none' : $r['via'], 'unknown' => $r['unknown']];
            $forAi[count($out) - 1] = ['id' => count($out) - 1, 'name' => $name, 'az' => $none ? $name : $r['text'], 'via' => $none ? 'rules' : $r['via'], 'unknown' => $r['unknown']];
        }
        return $this->claude($out, $forAi, $engine, $dict);
    }

    /**
     * Çeviri ayarlarında Claude açıksa (Karma / Tüm adlar) botla aynı kuralla Claude'a sorulur:
     * aynı istem, aynı denetim (sayılar, emojiler, parça sayısı, sözlük terimleri), aynı aylık sınır.
     * Claude hata verirse ya da sınır dolarsa banka + kural sonucu kalır.
     */
    private function claude(array $out, array $forAi, Engine $engine, array $dict): array
    {
        $ai = new Ai($this->db, $this->config);
        if (!$forAi || !$ai->active()) {
            return $out;
        }
        try {
            $r = $ai->improve(array_values($forAi), $engine, $dict);
        } catch (\Throwable $e) {
            $this->db->log('warn', 'ext', 'Eklenti: Claude kullanılamadı, banka + kurallar kullanıldı: ' . $e->getMessage());
            return $out;
        }
        foreach ($r['items'] as $it) {
            $i = (int)$it['id'];
            if (($it['via'] ?? '') === 'claude' && isset($out[$i])) {
                $out[$i]['az'] = (string)$it['az'];
                $out[$i]['via'] = 'claude';
                $out[$i]['unknown'] = [];
            }
        }
        if ($r['sent']) {
            $this->db->log('info', 'ext', "Eklenti: {$r['applied']}/{$r['sent']} ad Claude ile çevrildi" . ($r['rejected'] ? '; denetimden geçmeyen: ' . count($r['rejected']) : ''));
        }
        return $out;
    }

    /** @return array{learned:int,skipped:int} */
    private function learn(array $items): array
    {
        $mem = new Memory($this->db);
        $opt = Dictionary::load($this->db)['options'] ?? [];
        $learned = 0;
        $skipped = 0;
        foreach ($items as $it) {
            if (!is_array($it)) {
                $skipped++;
                continue;
            }
            $src = trim((string)($it['src'] ?? ''));
            $az = trim((string)($it['az'] ?? ''));
            if ($src === '' || $az === '' || mb_strlen($src) > 500 || mb_strlen($az) > 500
                || Learner::same($src, $az) || Learner::same($src, NameTemplate::stripAffixes($az, $opt))) { // yalnızca ek farkı: çeviri değil
                $skipped++;
                continue;
            }
            // Elle düzelttiğin ad en yüksek öncelik; olduğu gibi onayladığın "onaylanan iş" düzeyinde öğrenilir.
            $mem->learn(($it['kind'] ?? '') === 'category' ? 'category' : 'service', $src, $az, !empty($it['edited']) ? 'manual' : 'approved');
            $learned++;
        }
        if ($learned) {
            $this->db->log('info', 'ext', "Eklenti: Perfect Panel import'undan $learned ad bankaya öğrenildi");
        }
        return ['learned' => $learned, 'skipped' => $skipped];
    }

    /** Sözlüğe tek terim: aynı kelimenin eski karşılığı (büyük/küçük harf fark etmez) değiştirilir, tahmin işareti kalkar. */
    private function teach(array $req): array
    {
        $clean = static fn($v): string => trim(preg_replace('/\s+/u', ' ', (string)$v) ?? '');
        $en = $clean($req['en'] ?? '');
        $az = $clean($req['az'] ?? '');
        $keep = !empty($req['keep']);
        if ($en === '' || mb_strlen($en) > 80 || !preg_match('/\p{L}/u', $en)) {
            throw new \InvalidArgumentException('Kaynak kelime 1–80 karakter olmalı');
        }
        if (!$keep && ($az === '' || mb_strlen($az) > 120)) {
            throw new \InvalidArgumentException('Azerice karşılık 1–120 karakter olmalı');
        }
        $dict = Dictionary::load($this->db);
        $low = mb_strtolower($en);
        foreach (array_keys($dict['phrases']) as $k) {
            if (mb_strtolower((string)$k) === $low) {
                unset($dict['phrases'][$k]);
            }
        }
        $dict['guesses'] = array_values(array_filter($dict['guesses'] ?? [], static fn($g) => mb_strtolower((string)$g) !== $low));
        $dict['keep'] = array_values(array_filter($dict['keep'] ?? [], static fn($g) => mb_strtolower((string)$g) !== $low));
        if ($keep) {
            $dict['keep'][] = $en;
        } else {
            $dict['phrases'][$en] = $az;
        }
        Dictionary::save($this->db, $dict);
        $this->db->log('info', 'ext', 'Eklenti sözlüğe ekledi: ' . $en . ($keep ? ' (olduğu gibi kalsın)' : ' → ' . $az));
        return ['en' => $en, 'az' => $keep ? $en : $az, 'keep' => $keep];
    }

    /** Sunucudaki eklenti sürümü (chrome-extension/manifest.json): eklenti kendininkiyle karşılaştırıp "yeni sürüm var" der. */
    public static function extVersion(): string
    {
        $f = (defined('TGWATCH_ROOT') ? TGWATCH_ROOT : dirname(__DIR__)) . '/chrome-extension/manifest.json';
        $m = is_file($f) ? json_decode((string)file_get_contents($f), true) : null;
        return is_array($m) && preg_match('/^\d+(\.\d+){0,3}$/', (string)($m['version'] ?? '')) ? (string)$m['version'] : '';
    }
}
