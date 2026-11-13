<?php
declare(strict_types=1);

namespace TgWatch\Publisher;

/**
 * Perfect Panel servis API'si gelene kadar: onaylanan iş "Onaylandı (yayın bekliyor)" durumuna geçer.
 * Bota ayrı mesaj gönderilmez; import paketi (ID → Azerice ad) web panelde işin sayfasındadır.
 */
final class ManualPublisher implements Publisher
{
    public static function name(): string
    {
        return 'manual';
    }

    /**
     * Panelde elle ekleme için düz metin paket. $info (Enrich::forJob) verilirse sağlayıcı, öneri ve kategori de eklenir.
     */
    public static function package(array $job, ?array $info = null): string
    {
        $lines = [];
        $lines[] = "# tgwatch iş #{$job['id']} — @{$job['channel']} / {$job['msg_id']}";
        $cats = [];
        foreach ($job['items'] as $it) {
            if (!empty($it['category_az'])) {
                $cats[$it['category_az']] = true;
            }
        }
        if ($info && ($info['category']['az'] ?? '') !== '') {
            $lines[] = '# Kategori: ' . $info['category']['az'] . ($info['category']['exists'] ? ' (panelde var)' : ' (yeni)');
        } elseif ($cats) {
            $lines[] = '# Kategori: ' . implode(' / ', array_keys($cats));
        }
        if ($info && $info['provider']) {
            $lines[] = '# Sağlayıcı: ' . $info['provider']['name'];
        }
        if ($info && $info['formula']) {
            $lines[] = '# ' . $info['formula'];
        }
        $lines[] = $info ? "# ID\tAd\tSağlayıcı fiyatı\tÖnerilen satış\tMin\tMax\tPanelde" : "# ID\tAd\tFiyat(sağlayıcı)";
        foreach ($job['items'] as $it) {
            if ($info) {
                $x = $info['items'][(int)$it['id']] ?? [];
                $lines[] = implode("\t", [$it['id'], $it['az'], $x['rate'] ?? ($it['price'] ?? ''), $x['suggest'] ?? '', $x['min'] ?? '', $x['max'] ?? '',
                    !empty($x['own']) ? 'VAR #' . (int)$x['own']['service_id'] : '']);
            } else {
                $lines[] = $it['id'] . "\t" . $it['az'] . "\t" . ($it['price'] ?? '');
            }
        }
        return implode("\n", $lines) . "\n";
    }

    public function publish(array $job): array
    {
        return ['ok' => true, 'status' => 'approved', 'message' => 'Sitede yayın bekliyor (Perfect Panel servis API\'si henüz yok)'];
    }
}
