<?php
declare(strict_types=1);

namespace TgWatch;

/**
 * Öncelikli sağlayıcı modu. Ayarlar'dan bir sağlayıcı seçilirse (priority_source), aynı servisi birden çok
 * sağlayıcının kanalı duyurduğunda sistem o sağlayıcıyı tercih eder:
 *   - öncelikli sağlayıcının kanalı gelirse: karar bekleyen diğer sağlayıcı işlerindeki aynı servisler oradan çıkarılır,
 *   - başka sağlayıcının kanalı, öncelikli sağlayıcının zaten duyurduğu servisi paylaşırsa yeni iş açılmaz,
 *   - başka sağlayıcıdan gelen yeni servis için öncelikli sağlayıcının katalogunda benzeri varsa mesajda gösterilir.
 * Aynı servis "aynı sayılır": aynı platform, aynı sayılar (50K, 30 gün…) ve ad kelimeleri en az %80 benzer.
 */
final class Priority
{
    public const SIMILARITY = 0.8;

    public function __construct(private Db $db, private array $config)
    {
    }

    public function sourceId(): int
    {
        $id = (int)($this->config['priority_source'] ?? 0);
        if ($id <= 0) {
            return 0;
        }
        return $this->db->one("SELECT id FROM sources WHERE id = ? AND kind = 'provider'", [$id]) ? $id : 0;
    }

    public function enabled(): bool
    {
        return $this->sourceId() > 0;
    }

    public function name(): string
    {
        $r = $this->db->one('SELECT name FROM sources WHERE id = ?', [$this->sourceId()]);
        return $r ? (string)$r['name'] : '';
    }

    /** @return list<string> */
    private static function words(string $name): array
    {
        $w = preg_split('/[^\p{L}\p{N}]+/u', mb_strtolower(Normalizer::text($name))) ?: [];
        return array_values(array_unique(array_filter($w, static fn($x) => $x !== '' && !ctype_digit($x))));
    }

    /** İki ad aynı servisi mi anlatıyor? */
    public static function twin(string $a, string $b): bool
    {
        $pa = Matcher::platform($a);
        if ($pa === '' || $pa !== Matcher::platform($b)) {
            return false;
        }
        $na = Matcher::nums($a);
        $nb = Matcher::nums($b);
        sort($na);
        sort($nb);
        if ($na !== $nb) {
            return false;
        }
        $wa = self::words($a);
        $wb = self::words($b);
        $u = count(array_unique(array_merge($wa, $wb)));
        return $u > 0 && count(array_intersect($wa, $wb)) / $u >= self::SIMILARITY;
    }

    /** Öncelikli sağlayıcının katalogunda bu adın benzeri (ID, ad, fiyat) ya da null. */
    public function inCatalog(string $name): ?array
    {
        $p = Matcher::platform($name);
        if (!$this->enabled() || $p === '') {
            return null;
        }
        foreach ($this->db->all('SELECT service_id, name, rate FROM remote_services WHERE source_id = ? AND name LIKE ?', [$this->sourceId(), '%' . $p . '%']) as $r) {
            if (self::twin($name, (string)$r['name'])) {
                return ['id' => (int)$r['service_id'], 'name' => (string)$r['name'], 'rate' => $r['rate'] !== null ? (float)$r['rate'] : null];
            }
        }
        return null;
    }
}
