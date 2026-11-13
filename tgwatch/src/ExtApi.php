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
 *   ext_files  → sunucudaki chrome-extension dosyaları (base64): eklenti kendi klasörünü bunlarla günceller
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

    /** API işlemleri: panel → API sekmesindeki belge. [ad => [açıklama, örnek istek gövdesi]] */
    public const ACTIONS = [
        'ping' => ['Bağlantıyı sınar; tgwatch ve sunucudaki eklenti sürümünü döndürür.', '{"action":"ping"}'],
        'translate' => ['Servis/kategori adlarını çevirir (banka → kurallar → Claude). En çok 1000 ad.', '{"action":"translate","items":[{"id":"1","name":"Instagram Followers | Fast"}]}'],
        'translate_desc' => ['Servis açıklamalarını çevirir (banka + Google + DeepSeek). En çok 20.', '{"action":"translate_desc","items":[{"id":"1","text":"Start: 0-1 hour"}]}'],
        'learn' => ['Import\'ta kaydedilen son adları bankaya öğretir.', '{"action":"learn","items":[{"src":"…","az":"…","kind":"service"}]}'],
        'learn_desc' => ['Açıklama çiftlerini bankaya öğretir.', '{"action":"learn_desc","items":[{"en":"…","az":"…"}]}'],
        'teach' => ['Sözlüğe kelime: karşılık, "aynen kalsın" (keep) ya da "hiç yazılmasın" (remove); bankadaki eski kayıtları da düzeltir.', '{"action":"teach","en":"Almost","az":"Demək olar ki"}'],
        'word_info' => ['Kelimenin sözlükteki durumu ve bankada kaç kayıtta geçtiği.', '{"action":"word_info","en":"Almost"}'],
        'word_undo' => ['Son kelime değişikliğini geri alır (peek: yalnız ne olduğunu söyler).', '{"action":"word_undo","peek":true}'],
        'bank_search' => ['Bankada ve sözlükte arama.', '{"action":"bank_search","q":"almost"}'],
        'bank_save' => ['Banka kaydının Azericesini düzeltir.', '{"action":"bank_save","id":12,"az":"…"}'],
        'bank_del' => ['Banka kaydını siler.', '{"action":"bank_del","id":12}'],
        'balance' => ['Sağlayıcı bakiyeleri: cron\'un son kaydı (force:1 ile anında sorgu).', '{"action":"balance"}'],
        'imported' => ['Import bitti bildirimi: bota özet, istenirse kanala paylaşım.', '{"action":"imported","items":[…]}'],
        'ext_files' => ['Eklentinin güncel dosyaları (kendini güncelleme).', '{"action":"ext_files"}'],
    ];

    /** @return array{0:int,1:array} [HTTP kodu, JSON gövdesi] */
    public function handle(array $req): array
    {
        $action = (string)($req['action'] ?? '');
        try {
            return match ($action) {
                'ping' => [200, ['ok' => true, 'version' => VERSION, 'panel' => (string)($this->config['panel_name'] ?? 'tgwatch'), 'ext' => self::extVersion()]],
                'translate' => [200, ['ok' => true, 'items' => $this->translate(self::items($req))]],
                'translate_desc' => [200, ['ok' => true] + $this->translateDesc($req)],
                'learn_desc' => [200, ['ok' => true] + $this->learnDesc($req)],
                'learn' => [200, ['ok' => true] + $this->learn(self::items($req))],
                'teach' => [200, ['ok' => true] + $this->teach($req)],
                'word_info' => [200, ['ok' => true] + $this->wordInfo($req)],
                'word_undo' => [200, ['ok' => true] + $this->wordUndo($req)],
                'bank_search' => [200, ['ok' => true] + $this->bankSearch($req)],
                'bank_save' => [200, ['ok' => true] + $this->bankEdit($req, false)],
                'bank_del' => [200, ['ok' => true] + $this->bankEdit($req, true)],
                'balance' => [200, ['ok' => true] + $this->balance($req)],
                'imported' => [200, ['ok' => true] + (new ImportShare($this->db, $this->tg ?? App::telegram(), $this->config))->report($req)],
                'ext_files' => [200, ['ok' => true] + self::extFiles()],
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

    /**
     * Açıklama çevirisi: items = [{id, text}], mode = mix | google | deepseek | bank (eklenti mini panelinden; boşsa sunucu ayarı).
     * En çok 20 açıklama (her biri Google + DeepSeek isteği yapabilir).
     * @return array{items: list<array{id:string,az:string,via:string,note:string}>, plan: array}
     */
    private function translateDesc(array $req): array
    {
        $items = self::items($req);
        if (count($items) > 20) {
            throw new \InvalidArgumentException('Bir seferde en çok 20 açıklama gönderilebilir');
        }
        $mode = isset($req['mode']) && is_string($req['mode']) && isset(DescTranslator::MODES[$req['mode']]) ? $req['mode'] : null;
        $dt = new DescTranslator($this->db, $this->config);
        $out = [];
        $n = ['bank' => 0, 'mix' => 0, 'deepseek' => 0, 'google' => 0];
        foreach ($items as $it) {
            if (!is_array($it)) {
                continue;
            }
            $r = $dt->translate((string)($it['text'] ?? ''), $mode);
            $out[] = ['id' => (string)($it['id'] ?? '')] + $r;
            if (isset($n[$r['via']])) {
                $n[$r['via']]++;
            }
        }
        $done = array_sum($n);
        if ($done) {
            $this->db->log('info', 'ext', "Eklenti: $done açıklama çevrildi (banka {$n['bank']}, karma {$n['mix']}, DeepSeek {$n['deepseek']}, Google {$n['google']})");
        }
        $p = $dt->plan($mode);
        return ['items' => $out, 'plan' => ['mode' => $p['mode'], 'google' => $p['google'], 'deepseek' => $p['deepseek']]];
    }

    /** Doldurulan açıklamaları bankaya öğretir: items = [{en, az}]. */
    private function learnDesc(array $req): array
    {
        $dt = new DescTranslator($this->db, $this->config);
        $n = 0;
        foreach (self::items($req) as $it) {
            if (is_array($it) && $dt->learn((string)($it['en'] ?? ''), (string)($it['az'] ?? ''))) {
                $n++;
            }
        }
        return ['learned' => $n];
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

    /** Bakiye: cron'un son kaydı (en çok 3 dk eski) ya da force=1 ile anında sorgu. Kayıt yoksa/eskiyse sorgulanıp kaydedilir. */
    private function balance(array $req): array
    {
        $c = Balances::cached($this->db);
        if (empty($req['force']) && $c && time() - (int)$c['at'] < 180) {
            return $c + ['cached' => true];
        }
        return Balances::checkAndSave($this->db, $this->config, $this->tg) + ['cached' => false];
    }

    private const UNDO_KEY = 'word_undo';
    private const UNDO_MAX = 15;

    private static function cleanWord($v): string
    {
        return trim(preg_replace('/\s+/u', ' ', (string)$v) ?? '');
    }

    /** "Bu kelime hiç yazılmasın" kuralı: sözlüğün "sil" listesine giren tam kelime kalıbı (Engine::stripBy sonuna "u" ekler). */
    private static function removeRule(string $en): string
    {
        return substr(Memory::wordRegex($en), 0, -1); // "/…/iu" → "/…/i"
    }

    /** Kelimenin sözlükteki şimdiki durumu. @return array{az:string,key:?string,keep:bool,guess:bool,remove:bool} */
    private static function wordState(array $dict, string $en): array
    {
        $low = mb_strtolower($en);
        $az = '';
        $key = null;
        foreach ($dict['phrases'] as $k => $v) {
            if (mb_strtolower((string)$k) === $low) {
                $az = (string)$v;
                $key = (string)$k;
            }
        }
        $in = static fn(array $list): bool => in_array($low, array_map(static fn($x) => mb_strtolower((string)$x), $list), true);
        $rule = mb_strtolower(self::removeRule($en));
        $remove = false;
        foreach ($dict['remove'] ?? [] as $r) {
            $r = mb_strtolower((string)$r);
            $remove = $remove || $r === $rule || $r === $low;
        }
        return ['az' => $az, 'key' => $key, 'keep' => $in($dict['keep'] ?? []), 'guess' => $in($dict['guesses'] ?? []), 'remove' => $remove];
    }

    /** Kelimeye ait bütün sözlük kayıtlarını çıkarır (karşılık, tahmin işareti, "aynen kalsın", "sil"). */
    private static function dropWord(array $dict, string $en): array
    {
        $low = mb_strtolower($en);
        foreach (array_keys($dict['phrases']) as $k) {
            if (mb_strtolower((string)$k) === $low) {
                unset($dict['phrases'][$k]);
            }
        }
        $rule = mb_strtolower(self::removeRule($en));
        $not = static fn($g): bool => mb_strtolower((string)$g) !== $low;
        $dict['guesses'] = array_values(array_filter($dict['guesses'] ?? [], $not));
        $dict['keep'] = array_values(array_filter($dict['keep'] ?? [], $not));
        $dict['remove'] = array_values(array_filter($dict['remove'] ?? [], static fn($g): bool => !in_array(mb_strtolower((string)$g), [$low, $rule], true)));
        return $dict;
    }

    /**
     * Sözlüğe tek terim: aynı kelimenin eski karşılığı (büyük/küçük harf fark etmez) değiştirilir, tahmin işareti kalkar.
     * keep: çevrilmesin (aynen kalsın). remove: adlarda hiç yazılmasın (çeviriden önce silinir).
     * Bankadaki eski kayıtlar (adlar + açıklamalar) da düzeltilir; değişiklik word_undo ile geri alınabilir.
     */
    private function teach(array $req): array
    {
        $en = self::cleanWord($req['en'] ?? '');
        $az = self::cleanWord($req['az'] ?? '');
        $keep = !empty($req['keep']);
        $remove = !$keep && !empty($req['remove']);
        if ($en === '' || mb_strlen($en) > 80 || !preg_match('/\p{L}/u', $en)) {
            throw new \InvalidArgumentException('Kaynak kelime 1–80 karakter olmalı');
        }
        if (!$keep && !$remove && ($az === '' || mb_strlen($az) > 120)) {
            throw new \InvalidArgumentException('Azerice karşılık 1–120 karakter olmalı');
        }
        $dict = Dictionary::load($this->db);
        $before = self::wordState($dict, $en);
        $olds = [$en]; // bankada çevrilmeden kalmış hâli ("Almost Azalma olmaz")
        if ($before['az'] !== '') {
            $olds[] = $before['az']; // sözlükteki eski karşılık
        }
        $dict = self::dropWord($dict, $en);
        if ($keep) {
            $dict['keep'][] = $en;
        } elseif ($remove) {
            $dict['remove'][] = self::removeRule($en);
        } else {
            $dict['phrases'][$en] = $az;
        }
        Dictionary::save($this->db, $dict);
        // Banka sözlükten önce gelir: kelimenin eski hâliyle kayıtlı adlar düzeltilmezse eski çeviri gelmeye devam ederdi.
        $rows = [];
        $fix = (new Memory($this->db))->replaceWord($en, $olds, $remove ? '' : ($keep ? $en : $az), $rows);
        $to = $remove ? '(silinsin)' : ($keep ? '(aynen kalsın)' : $az);
        $stack = $this->db->getJson(self::UNDO_KEY, []);
        $stack = is_array($stack) ? $stack : [];
        $stack[] = ['at' => time(), 'en' => $en, 'to' => $to, 'before' => $before, 'rows' => $rows];
        $this->db->setJson(self::UNDO_KEY, array_slice($stack, -self::UNDO_MAX));
        $this->db->log('info', 'ext', 'Eklenti sözlüğe ekledi: ' . $en . ' → ' . $to
            . ($fix['names'] || $fix['desc'] ? "; bankada {$fix['names']} ad, {$fix['desc']} açıklama düzeltildi" : ''));
        return ['en' => $en, 'az' => $remove ? '' : ($keep ? $en : $az), 'keep' => $keep, 'remove' => $remove, 'bank' => $fix['names'], 'desc' => $fix['desc'], 'undo' => true];
    }

    /** Son kelime değişikliğini geri al (peek: yalnızca neyin geri alınacağını söyle). */
    private function wordUndo(array $req): array
    {
        $stack = $this->db->getJson(self::UNDO_KEY, []);
        $stack = is_array($stack) ? array_values($stack) : [];
        $last = $stack ? $stack[count($stack) - 1] : null;
        $label = $last ? ['en' => (string)$last['en'], 'to' => (string)$last['to'], 'at' => (int)$last['at'], 'rows' => count($last['rows'] ?? [])] : null;
        if (!empty($req['peek'])) {
            return ['last' => $label, 'count' => count($stack)];
        }
        if (!$last) {
            throw new \InvalidArgumentException('Geri alınacak kelime değişikliği yok');
        }
        $en = (string)$last['en'];
        $b = is_array($last['before'] ?? null) ? $last['before'] : [];
        $dict = self::dropWord(Dictionary::load($this->db), $en);
        if (($b['az'] ?? '') !== '') {
            $dict['phrases'][(string)($b['key'] ?? $en)] = (string)$b['az'];
        }
        if (!empty($b['keep'])) {
            $dict['keep'][] = $en;
        }
        if (!empty($b['guess'])) {
            $dict['guesses'][] = $en;
        }
        if (!empty($b['remove'])) {
            $dict['remove'][] = self::removeRule($en);
        }
        Dictionary::save($this->db, $dict);
        $n = (new Memory($this->db))->restoreRows(is_array($last['rows'] ?? null) ? $last['rows'] : []);
        array_pop($stack);
        $this->db->setJson(self::UNDO_KEY, $stack);
        $was = ($b['az'] ?? '') !== '' ? (string)$b['az'] : (!empty($b['keep']) ? '(aynen kalsın)' : (!empty($b['remove']) ? '(silinsin)' : '(sözlükte yok)'));
        $this->db->log('info', 'ext', "Eklenti kelime değişikliğini geri aldı: $en → $was; bankada $n kayıt eski hâline döndü");
        $next = $stack ? $stack[count($stack) - 1] : null;
        return ['en' => $en, 'was' => $was, 'restored' => $n, 'last' => $next ? ['en' => (string)$next['en'], 'to' => (string)$next['to'], 'at' => (int)$next['at'], 'rows' => count($next['rows'] ?? [])] : null];
    }

    /** Kelimenin şimdiki durumu: sözlükteki karşılığı / "aynen kalsın" / "silinsin" / yok; bankada kaç adda ve açıklamada geçtiği. */
    private function wordInfo(array $req): array
    {
        $en = self::cleanWord($req['en'] ?? '');
        if ($en === '' || mb_strlen($en) > 80 || !preg_match('/\p{L}/u', $en)) {
            throw new \InvalidArgumentException('Kaynak kelime 1–80 karakter olmalı');
        }
        $st = self::wordState(Dictionary::load($this->db), $en);
        $uses = (new Memory($this->db))->wordUses($en);
        return ['en' => $en, 'az' => $st['az'], 'state' => $st['az'] !== '' ? 'phrase' : ($st['keep'] ? 'keep' : ($st['remove'] ? 'remove' : 'none')), 'guess' => $st['guess'],
            'bank' => $uses['count'], 'desc' => $uses['desc'], 'samples' => $uses['samples']];
    }

    /** Bankada ve sözlükte arama (eklentinin "Banka" sekmesi). */
    private function bankSearch(array $req): array
    {
        $q = self::cleanWord($req['q'] ?? '');
        if (mb_strlen($q) < 2 || mb_strlen($q) > 80) {
            throw new \InvalidArgumentException('Arama 2–80 karakter olmalı');
        }
        $low = mb_strtolower($q);
        $dict = Dictionary::load($this->db);
        $words = [];
        foreach ($dict['phrases'] as $k => $v) {
            if (count($words) < 20 && (str_contains(mb_strtolower((string)$k), $low) || str_contains(mb_strtolower((string)$v), $low))) {
                $words[] = ['en' => (string)$k, 'az' => (string)$v, 'state' => 'phrase'];
            }
        }
        foreach ($dict['keep'] ?? [] as $k) {
            if (count($words) < 30 && str_contains(mb_strtolower((string)$k), $low)) {
                $words[] = ['en' => (string)$k, 'az' => '', 'state' => 'keep'];
            }
        }
        return ['q' => $q, 'words' => $words, 'rows' => (new Memory($this->db))->search($q, 30)];
    }

    /** Bankadaki tek kaydın Azericesini değiştir (az) ya da kaydı sil (delete). */
    private function bankEdit(array $req, bool $delete): array
    {
        $id = (int)($req['id'] ?? 0);
        $row = $this->db->one("SELECT id, kind, en, az FROM tm WHERE id = ? AND kind IN ('service', 'category')", [$id]);
        if (!$row) {
            throw new \InvalidArgumentException('Banka kaydı bulunamadı');
        }
        $mem = new Memory($this->db);
        if ($delete) {
            $this->db->run('DELETE FROM tm WHERE id = ?', [$id]);
            $mem->rebuildSegments();
            $this->db->log('info', 'ext', 'Eklenti bankadan sildi: ' . $row['en'] . ' → ' . $row['az']);
            return ['id' => $id, 'deleted' => true];
        }
        $az = trim((string)($req['az'] ?? ''));
        if ($az === '' || mb_strlen($az) > 500) {
            throw new \InvalidArgumentException('Azerice ad 1–500 karakter olmalı');
        }
        if ($az !== $row['az']) {
            if (!$this->db->run("UPDATE OR IGNORE tm SET az = ?, origin = 'manual', updated_at = ? WHERE id = ?", [$az, time(), $id])) {
                throw new \InvalidArgumentException('Bu kaynak için aynı çeviri zaten kayıtlı');
            }
            $mem->rebuildSegments();
            $this->db->log('info', 'ext', 'Eklenti bankada düzeltti: ' . $row['en'] . ' → ' . $az);
        }
        return ['id' => $id, 'az' => $az];
    }

    /** Sunucudaki eklenti sürümü (chrome-extension/manifest.json): eklenti kendininkiyle karşılaştırıp "yeni sürüm var" der. */
    public static function extVersion(): string
    {
        $f = (defined('TGWATCH_ROOT') ? TGWATCH_ROOT : dirname(__DIR__)) . '/chrome-extension/manifest.json';
        $m = is_file($f) ? json_decode((string)file_get_contents($f), true) : null;
        return is_array($m) && preg_match('/^\d+(\.\d+){0,3}$/', (string)($m['version'] ?? '')) ? (string)$m['version'] : '';
    }

    /** Eklentinin güncelleme sayfası için sunucudaki eklenti dosyaları (yalnızca eklenti dosya türleri). */
    public static function extFiles(): array
    {
        $dir = (defined('TGWATCH_ROOT') ? TGWATCH_ROOT : dirname(__DIR__)) . '/chrome-extension';
        if (!is_dir($dir)) {
            throw new \InvalidArgumentException('Sunucuda chrome-extension klasörü yok: tgwatch-guncelleme.zip\'i tgwatch klasörüne aç.');
        }
        $files = [];
        $it = new \RecursiveIteratorIterator(new \RecursiveDirectoryIterator($dir, \FilesystemIterator::SKIP_DOTS));
        foreach ($it as $f) {
            $rel = str_replace('\\', '/', substr($f->getPathname(), strlen($dir) + 1));
            if (str_starts_with($f->getFilename(), '.') || !preg_match('~^[A-Za-z0-9_\-/]+\.(js|css|html|json|png|svg)$~', $rel) || $f->getSize() > 2_000_000) {
                continue;
            }
            $files[] = ['path' => $rel, 'b64' => base64_encode((string)file_get_contents($f->getPathname()))];
        }
        if (count($files) > 200) {
            throw new \InvalidArgumentException('Eklenti klasöründe beklenmeyen kadar çok dosya var');
        }
        return ['version' => self::extVersion(), 'files' => $files];
    }
}
