<?php

class PanelApi
{
    private string $apiUrl;
    private string $apiKey;

    public function __construct(string $apiUrl, string $apiKey)
    {
        $this->apiUrl = $apiUrl;
        $this->apiKey = $apiKey;
    }

    /**
     * Panelə POST sorğusu göndərir və JSON cavabı array kimi qaytarır.
     */
    private function request(array $params): array
    {
        $params['key'] = $this->apiKey;

        $ch = curl_init($this->apiUrl);
        curl_setopt_array($ch, [
            CURLOPT_POST           => true,
            CURLOPT_POSTFIELDS     => http_build_query($params),
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_SSL_VERIFYPEER => true,
            CURLOPT_TIMEOUT        => 30,
        ]);

        $response = curl_exec($ch);

        if ($response === false) {
            $error = curl_error($ch);
            curl_close($ch);
            throw new RuntimeException("API sorğusu zamanı xəta: {$error}");
        }
        curl_close($ch);

        $data = json_decode($response, true);

        if (json_last_error() !== JSON_ERROR_NONE) {
            throw new RuntimeException('API cavabı düzgün JSON deyil: ' . substr($response, 0, 300));
        }

        return $data;
    }

    /**
     * Bütün servisləri (id, name, category, rate, min, max və s.) qaytarır.
     */
    public function getServices(): array
    {
        return $this->request(['action' => 'services']);
    }

    /**
     * Balans məlumatını qaytarır (istəyə görə istifadə oluna bilər).
     */
    public function getBalance(): array
    {
        return $this->request(['action' => 'balance']);
    }

    /**
     * Servisləri keşdən, ya da (keş köhnəlibsə) birbaşa API-dən oxuyur.
     * QEYD: API xəta qaytararsa (məs. yanlış açar), bunu HEÇ VAXT keşləmirik -
     * əks halda açar düzəlsə belə köhnə xəta bir müddət göstərilməyə davam edərdi.
     */
    public function getServicesCached(string $cacheFile, int $ttl): array
    {
        if (is_file($cacheFile) && (time() - filemtime($cacheFile) < $ttl)) {
            $cached = json_decode(file_get_contents($cacheFile), true);
            if (is_array($cached) && !isset($cached['error'])) {
                return $cached;
            }
        }

        $services = $this->getServices();

        // Xəta gələrsə keşə yazmırıq ki, növbəti sorğuda yenidən cəhd olunsun
        if (is_array($services) && isset($services['error'])) {
            return $services;
        }

        $dir = dirname($cacheFile);
        if (!is_dir($dir)) {
            mkdir($dir, 0775, true);
        }
        $tmp = $cacheFile . '.' . getmypid() . '.tmp';
        file_put_contents($tmp, json_encode($services));
        rename($tmp, $cacheFile);

        return $services;
    }

    /**
     * Bəzi panellər öz vizual bölmə başlıqlarını (---- Facebook Xidmətləri ----)
     * adi bir "servis"/"kateqoriya" kimi API-yə də qatır. Bunlar real sifariş
     * verilə bilən deyil, sadəcə dizayn elementidir — filtrləyib atırıq.
     */
    public static function isDivider(string $text): bool
    {
        $trimmed = trim($text);
        if ($trimmed === '') {
            return true;
        }
        // Adında 4+ ardıcıl tire olan, ya da başı/sonu tirə ilə bitən mətnlər
        return (bool) preg_match('/-{4,}/', $trimmed);
    }

    /**
     * Servisləri kateqoriyaya görə qruplaşdırır (divider qeydləri xaric).
     * Qaytarır: ['Kateqoriya adı' => [servis, servis, ...], ...]
     */
    public static function groupByCategory(array $services): array
    {
        $grouped = [];
        foreach ($services as $service) {
            $category = trim((string)($service['category'] ?? 'Digər'));
            $name     = (string)($service['name'] ?? '');

            // Həm kateqoriya adı, həm servis adı divider-dirsə - atla
            if (self::isDivider($category) || self::isDivider($name)) {
                continue;
            }

            $grouped[$category][] = $service;
        }
        return $grouped;
    }

    /**
     * Frontend üçün hazır kateqoriya siyahısı (index.php və services.php ortaq istifadə edir).
     */
    public static function buildCategories(array $services): array
    {
        $result = [];
        foreach (self::groupByCategory($services) as $name => $items) {
            $result[] = [
                'name'     => $name,
                'services' => array_map(static fn(array $s): array => [
                    'id'   => (string)($s['service'] ?? ''),
                    'name' => (string)($s['name'] ?? ''),
                    'rate' => (string)($s['rate'] ?? ''),
                ], $items),
            ];
        }
        return $result;
    }
}
