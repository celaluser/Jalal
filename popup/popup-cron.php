<?php
/**
 * =============================================================
 *  CRON GİRİŞ NOKTASI
 *
 *  Tüm mantık popup-core.php içinde, tüm ayarlar admin panelde.
 *  Bu dosyayı düzenlemeniz gerekmez.
 *
 *  Cron komutu:
 *  php /home/milyonpane/domains/uplow.us/public_html/popup/popup-cron.php
 * =============================================================
 */

require __DIR__ . '/popup-core.php';

/* --- Olumcul hatalari da log'a yaz (yoksa cron sessizce oluyor) --- */
register_shutdown_function(function () {
    $e = error_get_last();
    if ($e && in_array($e['type'], [E_ERROR, E_PARSE, E_CORE_ERROR, E_COMPILE_ERROR], true)) {
        @popup_log('OLUMCUL HATA: ' . $e['message'] . ' — ' . $e['file'] . ':' . $e['line']);
    }
});

/* --- Cron'un gercekten tetiklendigini kanitlayan satir --- */
if (PHP_SAPI === 'cli') {
    popup_log('cron basladi (PHP ' . PHP_VERSION . ')');
}

$cfg = popup_config();

// Tarayicidan calistirma: sadece run_token doluysa ve dogruysa
if (PHP_SAPI !== 'cli') {
    if (($cfg['run_token'] ?? '') === '' || (($_GET['token'] ?? '') !== $cfg['run_token'])) {
        http_response_code(403);
        exit('Forbidden');
    }
    header('Content-Type: text/plain; charset=utf-8');
}

if (empty($cfg['enabled'])) {
    $m = 'Popup admin panelden KAPATILMIS, guncelleme yapilmadi.';
    popup_log($m);
    if (PHP_SAPI !== 'cli') echo $m;
    exit(0);
}

$r = popup_run();

if (PHP_SAPI !== 'cli') {
    echo ($r['ok'] ? 'OK — ' : 'HATA: ') . $r['msg'] . "\n\n";
    if (isset($_GET['debug']) && !empty($r['categories'])) {
        echo "API'nin dondurdugu kategoriler (sirayla):\n";
        $i = 1;
        foreach ($r['categories'] as $c) { echo '  ' . $i++ . '. ' . $c . "\n"; if ($i > 15) break; }
        echo "\n";
    }
    if (!empty($r['payload'])) {
        echo json_encode($r['payload'], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_PRETTY_PRINT);
    }
} elseif (!$r['ok']) {
    fwrite(STDERR, 'HATA: ' . $r['msg'] . PHP_EOL);
    exit(1);
}
