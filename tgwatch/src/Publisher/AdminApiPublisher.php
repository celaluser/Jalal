<?php
declare(strict_types=1);

namespace TgWatch\Publisher;

/**
 * Perfect Panel Admin API v2 ile otomatik yayın.
 *
 * BEKLEMEDE: Admin API v2'de şu an servis/kategori/sağlayıcı metotları yok
 * (yalnızca Orders, Cancel, Refill, Payments, Users, Tickets). Perfect Panel
 * metotları yayınlayınca burası doldurulacak. Bilinenler:
 *   - Adres: {panel_api_url}, ör. https://panelbaku.com/adminapi/v2
 *   - Kimlik doğrulama: X-Api-Key başlığı
 *   - Sınır: panel başına saniyede 5 istek (aşılırsa HTTP 429) → istekler arasında ≥250 ms bekle.
 */
final class AdminApiPublisher implements Publisher
{
    public function __construct(private string $apiUrl, private string $apiKey)
    {
    }

    public static function name(): string
    {
        return 'admin_api';
    }

    public function publish(array $job): array
    {
        return [
            'ok' => false,
            'message' => 'admin_api yayını henüz mümkün değil: Perfect Panel servis metotları yayınlanmadı. config.php içinde publisher => manual kullanın.',
        ];
    }
}
