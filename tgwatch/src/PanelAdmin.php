<?php
declare(strict_types=1);

namespace TgWatch;

/**
 * Kendi panelinin Admin API v2'si (Ayarlar → Yayın → Panel API adresi/anahtarı; X-Api-Key başlığı).
 * Bu, servisleri çeken standart SMM API'den (SmmApi) farklı, panelin kendi yönetim API'si.
 * Şu an yalnızca siparişleri okumak için kullanılır: her siparişte "provider" (sağlayıcı adı/takma adı)
 * alanı geliyor. Bunu servis ID'sine göre gruplayıp "bu servisi gerçekte hangi sağlayıcıdan alıyorum"
 * bilgisini tahmin değil, panelin kendi kaydından öğreniriz. Servis başına sağlayıcı servis ID'si
 * (Perfect Panel'in ayrı duyurduğu, henüz yayınlanmamış özellik) bu uçta yok; yalnızca sağlayıcı adı var.
 * DENENMEDİ: gerçek bir panele istek bu ortamdan yapılamadı; cevabın sarmalayıcı anahtarı (data/orders/…)
 * doğrulanmadı, bu yüzden parse esnek tutuldu.
 */
final class PanelAdmin
{
    /** Testler için: ağ yerine bu fonksiyon çağrılır (tam url) → ham cevap gövdesi. */
    public static ?\Closure $fake = null;

    /**
     * @param list<int> $serviceIds
     * @return list<array{service_id:int,provider:string,created_timestamp:int}>
     */
    public static function orders(string $url, string $key, array $serviceIds, int $limit = 1000, int $timeout = 30): array
    {
        if (!preg_match('~^https://~i', $url)) {
            throw new \InvalidArgumentException('Panel API adresi https:// ile başlamalı.');
        }
        $qs = http_build_query([
            'service_ids' => implode(',', array_map('intval', $serviceIds)),
            'limit' => min(1000, max(1, $limit)),
            'sort' => 'date-desc',
            'created_from' => 946684800, // 2000-01-01: varsayılan 90 gün sınırını aşmak için geniş aralık
        ]);
        $full = rtrim($url, '/') . '/orders?' . $qs;
        if (self::$fake !== null) {
            $body = (string)(self::$fake)($full);
        } else {
            $r = Http::request('GET', $full, ['X-Api-Key: ' . $key, 'Accept: application/json'], null, $timeout);
            if ($r['error'] !== '') {
                throw new \RuntimeException('Bağlantı hatası: ' . $r['error']);
            }
            if ($r['status'] !== 200) {
                throw new \RuntimeException('Beklenmeyen cevap (HTTP ' . $r['status'] . '): ' . mb_substr(trim(strip_tags($r['body'])), 0, 160));
            }
            $body = $r['body'];
        }
        $d = json_decode($body, true);
        if (!is_array($d)) {
            throw new \RuntimeException('Cevap okunamadı (geçerli JSON değil).');
        }
        $list = null;
        foreach ([$d, $d['data'] ?? null, $d['orders'] ?? null, $d['items'] ?? null, $d['result'] ?? null] as $cand) {
            if (is_array($cand) && $cand !== [] && array_is_list($cand)) {
                $list = $cand;
                break;
            }
        }
        if ($list === null) {
            return []; // boş sayfa (bu servislerde hiç sipariş yok) ya da tanınmayan sarmalayıcı
        }
        $out = [];
        foreach ($list as $row) {
            if (!is_array($row) || !isset($row['service_id'])) {
                continue;
            }
            $out[] = ['service_id' => (int)$row['service_id'], 'provider' => trim((string)($row['provider'] ?? '')), 'created_timestamp' => (int)($row['created_timestamp'] ?? 0)];
        }
        return $out;
    }

    /**
     * Servis başına en son siparişteki sağlayıcı adı/takma adı.
     * @param list<int> $serviceIds
     * @return array<int,string> service_id => provider
     */
    public static function providersFor(string $url, string $key, array $serviceIds): array
    {
        $out = [];
        foreach (array_chunk(array_values(array_unique(array_map('intval', $serviceIds))), 150) as $i => $chunk) {
            if ($i > 0) {
                usleep(250000); // panel sınırı: saniyede 5 istek
            }
            foreach (self::orders($url, $key, $chunk) as $o) {
                if ($o['provider'] !== '' && !isset($out[$o['service_id']])) {
                    $out[$o['service_id']] = $o['provider']; // sort=date-desc: önce gelen en yeni sipariş
                }
            }
        }
        return $out;
    }

    /** Çekilen sağlayıcı adlarını veritabanına kaydeder. @param array<int,string> $map */
    public static function save(Db $db, array $map): void
    {
        $st = $db->pdo->prepare('INSERT INTO order_provider(own_service, provider, at) VALUES(?,?,?) ON CONFLICT(own_service) DO UPDATE SET provider = excluded.provider, at = excluded.at');
        foreach ($map as $sid => $provider) {
            $st->execute([$sid, $provider, time()]);
        }
    }

    /** @return array<int,string> own_service => provider (kaydedilmiş) */
    public static function known(Db $db): array
    {
        return array_column($db->all('SELECT own_service, provider FROM order_provider'), 'provider', 'own_service');
    }
}
