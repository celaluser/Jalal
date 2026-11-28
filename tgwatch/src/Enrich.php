<?php
declare(strict_types=1);

namespace TgWatch;

/**
 * Bir işi panele eklerken gereken bilgiyi hazırlar:
 *   - hangi sağlayıcı (kanal → sağlayıcı ayarı; tek sağlayıcı varsa o)
 *   - sağlayıcıdaki fiyat, min/max, iade/iptal (son senkronizasyondan)
 *   - önerilen satış fiyatı: satış = a × sağlayıcı + b (eşleştirmenin bulduğu formül ya da elle girilen)
 *   - panelinde zaten var mı (eşleştirme bağlantısı ya da aynı ad)
 *   - Azerice kategori adı ve ikonu (kendi panelinin kategorilerinden öğrenilir)
 */
final class Enrich
{
    private ?array $icons = null;
    private ?array $ownCats = null;

    public function __construct(private Db $db)
    {
    }

    public function providers(): array
    {
        return $this->db->all("SELECT * FROM sources WHERE kind = 'provider' ORDER BY id");
    }

    public function sourceFor(string $channel): ?array
    {
        if (preg_match('/^api-(\d+)$/', $channel, $m)) { // API izlemesinden gelen işler doğrudan sağlayıcıya bağlıdır
            $s = $this->db->one("SELECT * FROM sources WHERE id = ? AND kind = 'provider'", [(int)$m[1]]);
            if ($s) {
                return $s;
            }
        }
        $ch = $this->db->one('SELECT source_id FROM channels WHERE username = ?', [$channel]);
        if ($ch && $ch['source_id']) {
            $s = $this->db->one("SELECT * FROM sources WHERE id = ? AND kind = 'provider'", [(int)$ch['source_id']]);
            if ($s) {
                return $s;
            }
        }
        $all = $this->providers();
        return count($all) === 1 ? $all[0] : null;
    }

    /** @return array{a:float,b:float,mode:string,round:int} */
    public function model(): array
    {
        $round = max(0, min(4, (int)$this->db->get('markup_round', '2')));
        $a = trim((string)$this->db->get('markup_a', ''));
        $b = trim((string)$this->db->get('markup_b', ''));
        if ($a !== '' && is_numeric(str_replace(',', '.', $a))) {
            return ['a' => (float)str_replace(',', '.', $a), 'b' => (float)str_replace(',', '.', $b === '' ? '0' : $b), 'mode' => 'manual', 'round' => $round];
        }
        $rep = $this->db->getJson('match_report');
        $m = is_array($rep) ? ($rep['model'] ?? null) : null;
        if (is_array($m) && ($m['mode'] ?? 'none') !== 'none') {
            return ['a' => (float)$m['a'], 'b' => (float)$m['b'], 'mode' => 'auto', 'round' => $round];
        }
        return ['a' => 1.0, 'b' => 0.0, 'mode' => 'none', 'round' => $round];
    }

    public function suggestPrice(float $rate): ?float
    {
        $m = $this->model();
        if ($m['mode'] === 'none' || $rate <= 0) {
            return null;
        }
        return round(max(0.0, $m['a'] * $rate + $m['b']), $m['round']);
    }

    public function formula(): ?string
    {
        $m = $this->model();
        if ($m['mode'] === 'none') {
            return null;
        }
        return 'satış = sağlayıcı × ' . rtrim(rtrim(number_format($m['a'], 4, '.', ''), '0'), '.') . ($m['b'] >= 0 ? ' + ' : ' − ') . rtrim(rtrim(number_format(abs($m['b']), 4, '.', ''), '0'), '.')
            . ($m['mode'] === 'manual' ? ' (elle girilen)' : ' (eşleştirmeden bulunan)');
    }

    public function remote(int $sourceId, int $serviceId): ?array
    {
        return $this->db->one('SELECT * FROM remote_services WHERE source_id = ? AND service_id = ?', [$sourceId, $serviceId]);
    }

    /** Sağlayıcı servisine bağlı, panelinde olan servis (eşleştirme bağlantısından). */
    public function own(int $sourceId, int $serviceId): ?array
    {
        $tm = $this->db->one("SELECT own_service, az FROM tm WHERE provider_source = ? AND provider_service = ? AND own_service IS NOT NULL AND status IN ('ok','review') ORDER BY status = 'ok' DESC LIMIT 1", [$sourceId, $serviceId]);
        if ($tm === null) {
            return null;
        }
        $own = $this->db->one("SELECT r.* FROM remote_services r JOIN sources s ON s.id = r.source_id WHERE s.kind = 'own' AND r.service_id = ?", [(int)$tm['own_service']]);
        return $own ?? ['service_id' => (int)$tm['own_service'], 'name' => $tm['az'], 'rate' => null, 'category' => '', 'min' => null, 'max' => null];
    }

    /** Aynı ad kendi panelinde var mı? (eşleştirmeden bağımsız ikinci kontrol) */
    private function ownByName(string $az): ?array
    {
        if ($az === '') {
            return null;
        }
        return $this->db->one("SELECT r.* FROM remote_services r JOIN sources s ON s.id = r.source_id WHERE s.kind = 'own' AND lower(r.name) = lower(?) LIMIT 1", [$az]);
    }

    private function iconMap(): array
    {
        if ($this->icons === null) {
            $this->icons = [];
            $count = [];
            foreach ($this->ownCategories() as $c) {
                $icon = self::leadingIcon($c);
                $plat = Matcher::platform($c);
                if ($icon !== '' && $plat !== '') {
                    $count[$plat][$icon] = ($count[$plat][$icon] ?? 0) + 1;
                }
            }
            foreach ($count as $plat => $icons) {
                arsort($icons);
                $this->icons[$plat] = (string)array_key_first($icons);
            }
        }
        return $this->icons;
    }

    /** @return list<string> */
    private function ownCategories(): array
    {
        if ($this->ownCats === null) {
            $rows = $this->db->all("SELECT DISTINCT r.category FROM remote_services r JOIN sources s ON s.id = r.source_id WHERE s.kind = 'own' AND r.category != ''");
            $this->ownCats = array_column($rows, 'category');
        }
        return $this->ownCats;
    }

    public static function leadingIcon(string $s): string
    {
        return preg_match('/^[^\p{L}\p{N}]+/u', trim($s), $m) ? trim($m[0]) : '';
    }

    public static function stripIcon(string $s): string
    {
        return trim((string)preg_replace('/^[^\p{L}\p{N}]+/u', '', trim($s)));
    }

    /**
     * Kategori önerisi: bankadaki kategori çifti → yoksa çeviri; başında paneldeki ikon.
     * @return array{en:string, az:string, exists:bool, source:string}
     */
    public function category(string $en, Engine $engine): array
    {
        $en = trim($en);
        if ($en === '') {
            return ['en' => '', 'az' => '', 'exists' => false, 'source' => ''];
        }
        $bank = (new Memory($this->db))->lookup('category', $en);
        $az = $bank ?? $engine->translateCategory(self::stripIcon($en));
        if (self::leadingIcon($az) === '') {
            $icon = $this->iconMap()[Matcher::platform($en)] ?? '';
            if ($icon !== '') {
                $az = $icon . ' ' . $az;
            }
        }
        $exists = false;
        $plain = mb_strtolower(self::stripIcon($az));
        foreach ($this->ownCategories() as $c) {
            if (mb_strtolower(self::stripIcon($c)) === $plain) {
                $exists = true;
                $az = $c; // panelde yazıldığı gibi (ikon dahil)
                break;
            }
        }
        return ['en' => $en, 'az' => $az, 'exists' => $exists, 'source' => $bank !== null ? 'bank' : 'çeviri'];
    }

    /**
     * @return array{provider:?array, items:array<int,array>, category:array, formula:?string, duplicates:int, synced:bool}
     */
    public function forJob(array $job, ?Engine $engine = null): array
    {
        $engine ??= new Engine($this->db, Dictionary::load($this->db));
        $src = $this->sourceFor((string)$job['channel']);
        $out = ['provider' => $src, 'items' => [], 'category' => ['en' => '', 'az' => '', 'exists' => false, 'source' => ''],
            'formula' => $this->formula(), 'duplicates' => 0, 'synced' => false];
        $catEn = '';
        foreach ($job['items'] as $it) {
            $id = (int)$it['id'];
            $row = $src ? $this->remote((int)$src['id'], $id) : null;
            $own = $src ? $this->own((int)$src['id'], $id) : null;
            $own ??= $this->ownByName((string)($it['az'] ?? ''));
            $rate = $row && $row['rate'] !== null ? (float)$row['rate'] : ($it['price'] !== '' ? (float)$it['price'] : null);
            $out['items'][$id] = [
                'remote' => $row, 'rate' => $rate, 'suggest' => $rate !== null ? $this->suggestPrice($rate) : null,
                'min' => $row['min'] ?? null, 'max' => $row['max'] ?? null, 'refill' => $row['refill'] ?? null, 'cancel' => $row['cancel'] ?? null,
                'own' => $own,
            ];
            if ($own) {
                $out['duplicates']++;
            }
            if ($row) {
                $out['synced'] = true;
            }
            if ($catEn === '') {
                $catEn = (string)(($row['category'] ?? '') !== '' ? $row['category'] : ($it['category'] ?? ''));
            }
        }
        $out['category'] = $this->category($catEn, $engine);
        return $out;
    }
}
