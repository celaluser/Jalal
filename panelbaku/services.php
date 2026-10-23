<?php
declare(strict_types=1);

header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store');

try {
    require __DIR__ . '/config.php';
    require __DIR__ . '/PanelApi.php';

    $api = new PanelApi(PANEL_API_URL, PANEL_API_KEY);

    // ?refresh=1 -> keşi atlayıb birbaşa paneldən çəkir
    $ttl = isset($_GET['refresh']) ? 0 : CACHE_TTL;
    $services = $api->getServicesCached(CACHE_FILE, $ttl);

    if (isset($services['error'])) {
        throw new RuntimeException('API xətası: ' . $services['error']);
    }

    echo json_encode(
        ['ok' => true, 'categories' => PanelApi::buildCategories($services)],
        JSON_UNESCAPED_UNICODE
    );
} catch (Throwable $e) {
    http_response_code(502);
    echo json_encode(['ok' => false, 'error' => $e->getMessage()], JSON_UNESCAPED_UNICODE);
}
