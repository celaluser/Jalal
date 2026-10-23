<?php
declare(strict_types=1);

require __DIR__ . '/config.php';
require __DIR__ . '/PanelApi.php';
require __DIR__ . '/HistoryStore.php';

$api = new PanelApi(PANEL_API_URL, PANEL_API_KEY);

$errorMessage = null;
$grouped = [];

try {
    $services = $api->getServicesCached(CACHE_FILE, CACHE_TTL);

    if (isset($services['error'])) {
        throw new RuntimeException('API xətası: ' . $services['error']);
    }

    // Sıralama edilmir - API-dən gələn təbii ardıcıllıq saxlanır
    $grouped = PanelApi::groupByCategory($services);
} catch (Throwable $e) {
    $errorMessage = $e->getMessage();
}

// Debug: API-dən real olaraq nə gəldiyini görmək üçün ?debug=1 əlavə et
if (isset($_GET['debug'])) {
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode(array_slice($services ?? [], 0, 5), JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
    exit;
}

$categoriesForJs = PanelApi::buildCategories($services ?? []);
$totalServices = array_sum(array_map(static fn(array $c): int => count($c['services']), $categoriesForJs));

$historyItems = (new HistoryStore(HISTORY_FILE, HISTORY_LIMIT))->all();

$today = date('d.m.Y');
$sharesToday = 0;
foreach ($historyItems as $h) {
    if (str_starts_with((string)($h['time'] ?? ''), $today)) {
        $sharesToday++;
    }
}
?>
<!DOCTYPE html>
<html lang="az">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0, viewport-fit=cover">
<meta name="theme-color" content="#6366f1">
<title>PanelBaku.com | Servis Paylaşımı</title>
<link rel="preconnect" href="https://fonts.googleapis.com">
<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
<link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&display=swap" rel="stylesheet">
<link rel="stylesheet" href="style.css?v=<?= (int)@filemtime(__DIR__ . '/style.css') ?>">
<script>
(function () {
    try {
        var saved = localStorage.getItem('panelbaku_theme');
        var theme = saved || (window.matchMedia('(prefers-color-scheme: dark)').matches ? 'dark' : 'light');
        document.documentElement.setAttribute('data-theme', theme);
    } catch (e) {}
})();
</script>
</head>
<body>

<div class="page">

    <div class="navbar">
        <div class="brand">
            <div class="brand-mark">P</div>
            <div class="brand-text">
                <h1>PanelBaku</h1>
                <p>Servis paylaşım paneli</p>
            </div>
        </div>
        <div class="navbar-right">
            <button type="button" class="theme-toggle" id="refreshBtn" title="Servisləri yenilə">🔄</button>
            <button type="button" class="theme-toggle" id="themeToggle" title="Tema dəyiş">🌙</button>
            <div class="live-badge">
                <span class="live-dot"></span>
                API Bağlı
            </div>
        </div>
    </div>

    <div class="hero">
        <h2>Kateqoriya seç, paylaş.</h2>
        <p>Kateqoriyanı seç, mesaj avtomatik sənin şablonuna uyğun hazırlansın, istəsən redaktə et, Telegram kanalına birbaşa göndər.</p>
    </div>

    <?php if ($errorMessage): ?>
        <div class="error-box">
            Servislər çəkilərkən xəta baş verdi:<br>
            <?= htmlspecialchars($errorMessage, ENT_QUOTES, 'UTF-8') ?>
        </div>
    <?php else: ?>

        <div class="stats-row">
            <div class="stat-card">
                <div class="stat-icon">📂</div>
                <div>
                    <div class="stat-value" id="statCategories"><?= count($categoriesForJs) ?></div>
                    <div class="stat-label">Kateqoriya</div>
                </div>
            </div>
            <div class="stat-card">
                <div class="stat-icon">⚙️</div>
                <div>
                    <div class="stat-value" id="statServices"><?= $totalServices ?></div>
                    <div class="stat-label">Servis</div>
                </div>
            </div>
            <div class="stat-card">
                <div class="stat-icon">📤</div>
                <div>
                    <div class="stat-value"><?= $sharesToday ?></div>
                    <div class="stat-label">Bugünkü paylaşım</div>
                </div>
            </div>
        </div>

        <div class="layout">

            <div class="main-col">
                <div class="card">
                    <div class="field-label">Kateqoriya</div>
                    <div class="select-wrap">
                        <button type="button" class="select-trigger" id="categoryTrigger">
                            <span id="categoryTriggerLabel">Kateqoriya seç...</span>
                            <svg class="chevron" width="14" height="14" viewBox="0 0 24 24" fill="none">
                                <path d="M6 9L12 15L18 9" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/>
                            </svg>
                        </button>
                        <div class="select-dropdown" id="categoryDropdown">
                            <input type="text" class="select-search" id="categorySearch" placeholder="Axtarış...">
                            <div class="select-options" id="categoryOptions"></div>
                        </div>
                    </div>
                </div>

                <div class="card">
                    <div class="field-label"><span>Mesaj <span class="field-hint">— istəsən dəyiş, paylaşılan versiya budur</span></span><span class="char-count" id="charCount">0 / 4096</span></div>
                    <textarea
                        class="message-textarea"
                        id="messageBox"
                        placeholder="Kateqoriya seçdikdən sonra mesaj burada avtomatik hazırlanacaq..."
                    ></textarea>

                    <div class="actions-row">
                        <button type="button" class="btn btn-secondary" id="copyBtn" disabled>📋 Kopyala</button>
                        <button type="button" class="btn btn-primary" id="shareBtn" disabled>📤 Paylaş</button>
                    </div>
                    <div class="status-box" id="statusBox"></div>
                </div>
            </div>

            <div class="side-col">
                <div class="card tg-card" id="tgCard">
                    <div class="tg-head">
                        <div class="tg-avatar">✈️</div>
                        <div class="tg-title-wrap">
                            <div class="tg-title" id="tgTitle">Telegram kanalı</div>
                            <div class="tg-sub" id="tgSub">Önizləmə yüklənir...</div>
                        </div>
                        <a class="tg-join" id="tgJoin" href="#" target="_blank" rel="noopener noreferrer" hidden>JOIN ↗</a>
                        <button type="button" class="tg-reload" id="tgReload" title="Önizləməni yenilə">🔄</button>
                    </div>
                    <div class="tg-feed" id="tgFeed">
                        <div class="tg-empty">Yüklənir...</div>
                    </div>
                </div>

                <div class="card history-card">
                    <div class="history-card-head">
                        <div class="field-label" style="margin:0;">Tarixçə</div>
                        <?php if (!empty($historyItems)): ?>
                            <button type="button" class="clear-history-btn" id="clearHistoryBtn">Hamısını sil</button>
                        <?php endif; ?>
                    </div>
                    <div class="history-list" id="historyList">
                        <?php if (empty($historyItems)): ?>
                            <div class="history-empty" id="historyEmpty">Hələ paylaşım yoxdur</div>
                        <?php else: ?>
                            <?php foreach ($historyItems as $item): ?>
                                <div class="history-item" data-id="<?= htmlspecialchars($item['id'] ?? '', ENT_QUOTES, 'UTF-8') ?>">
                                    <div class="history-item-top">
                                        <span class="history-cat"><?= htmlspecialchars($item['category'] ?? '', ENT_QUOTES, 'UTF-8') ?></span>
                                        <div class="history-item-right">
                                            <span class="history-time"><?= htmlspecialchars($item['time'] ?? '', ENT_QUOTES, 'UTF-8') ?></span>
                                            <button type="button" class="history-delete-btn" title="Sil">🗑</button>
                                        </div>
                                    </div>
                                    <div class="history-preview"><?= htmlspecialchars($item['preview'] ?? '', ENT_QUOTES, 'UTF-8') ?>…</div>
                                </div>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </div>
                </div>
            </div>

        </div>

    <?php endif; ?>

</div>

<script id="categoriesData" type="application/json"><?= json_encode($categoriesForJs, JSON_UNESCAPED_UNICODE | JSON_HEX_TAG) ?></script>
<script src="script.js?v=<?= (int)@filemtime(__DIR__ . '/script.js') ?>"></script>

</body>
</html>
