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

    /** Panelde elle ekleme için düz metin paket (ID<TAB>ad<TAB>fiyat). */
    public static function package(array $job): string
    {
        $lines = [];
        $lines[] = "# tgwatch iş #{$job['id']} — @{$job['channel']} / {$job['msg_id']}";
        $cats = [];
        foreach ($job['items'] as $it) {
            if (!empty($it['category_az'])) {
                $cats[$it['category_az']] = true;
            }
        }
        if ($cats) {
            $lines[] = '# Kategori: ' . implode(' / ', array_keys($cats));
        }
        $lines[] = '# ID<TAB>Ad<TAB>Fiyat(sağlayıcı)';
        foreach ($job['items'] as $it) {
            $lines[] = $it['id'] . "\t" . $it['az'] . "\t" . ($it['price'] ?? '');
        }
        return implode("\n", $lines) . "\n";
    }

    public function publish(array $job): array
    {
        return ['ok' => true, 'status' => 'approved', 'message' => 'Sitede yayın bekliyor (Perfect Panel servis API\'si henüz yok)'];
    }
}
