<?php
declare(strict_types=1);

namespace TgWatch;

/**
 * Chrome eklentisinin konuştuğu küçük API (public/api.php). Anahtar: Ayarlar → Özellikler → Eklenti anahtarı.
 *   ping       → bağlantı testi
 *   translate  → {items:[{id,name}]} → her ad için banka + kurallarla Azerice ad (botun kullandığı motorun aynısı)
 *   learn      → {items:[{id,src,az,edited}]} → Perfect Panel'de import edilen son adlar bankaya öğretilir
 */
final class ExtApi
{
    public const MAX_ITEMS = 1000;

    public function __construct(private Db $db, private array $config)
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
                'ping' => [200, ['ok' => true, 'version' => VERSION, 'panel' => (string)($this->config['panel_name'] ?? 'tgwatch')]],
                'translate' => [200, ['ok' => true, 'items' => $this->translate(self::items($req))]],
                'learn' => [200, ['ok' => true] + $this->learn(self::items($req))],
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
            $r = $engine->translate($name);
            // Kurallar adın kendisine hiç dokunmadıysa (yalnızca ad kalıbı ön/son ek ekledi) çevrilmemiş sayılır.
            $clean = Engine::stripBy($dict['remove'] ?? [], $name);
            $untouched = $r['via'] === 'rules' && Learner::same($rules->translate($clean)['text'], $clean);
            $none = $untouched || Learner::same($r['text'], $name);
            $out[] = ['id' => $id, 'az' => $none ? '' : $r['text'], 'via' => $none ? 'none' : $r['via'], 'unknown' => $r['unknown']];
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
            $mem->learn('service', $src, $az, !empty($it['edited']) ? 'manual' : 'approved');
            $learned++;
        }
        if ($learned) {
            $this->db->log('info', 'ext', "Eklenti: Perfect Panel import'undan $learned ad bankaya öğrenildi");
        }
        return ['learned' => $learned, 'skipped' => $skipped];
    }
}
