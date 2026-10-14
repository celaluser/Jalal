<?php
define('IGFP_ADMIN', true);
/**
 * Panelde, kaydedilmemiş form durumunun canlı önizlemesi.
 * Form alanlarını POST olarak alır, tek popup'lık geçici bir sayfa döndürür (hiçbir şey kaydedilmez).
 */
require __DIR__ . '/auth.php';
if (!adm_logged_in() || !adm_csrf_ok()) { http_response_code(403); exit('Forbidden'); }

$m = array_merge(popup_manual_default(), popup_manual_fields_from($_POST), ['id' => 'live', 'enabled' => true]);
if (trim($m['btn_text']) !== '' && trim($m['btn_url']) === '') $m['btn_url'] = '#';
$p = popup_build_manual($m, popup_config());

header('Content-Type: text/html; charset=utf-8');
header('Cache-Control: no-store');
header('X-Robots-Tag: noindex');
if ($p === null) {
    echo '<!doctype html><meta charset="utf-8"><body style="margin:0;background:#0a0a11;color:#9a96ad;font:13px system-ui;padding:24px">Önizleme için başlık, açıklama ya da buton yaz.</body>';
    exit;
}
$p['pid'] = 'live'; $p['v'] = 'live'; $p['trigger'] = ['type' => 'load', 'scrollPercent' => 50];
$tplFile = popup_paths()['template'];
$client = popup_config()['client'];
$client['enabled'] = true; $client['stats'] = ['enabled' => false, 'url' => '']; $client['killCheck'] = ['enabled' => false, 'url' => ''];
$js = popup_embed_js((string) file_get_contents($tplFile), [$p], $client);
?><!DOCTYPE html><html><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1">
<style>html,body{margin:0;height:100%}body{background:linear-gradient(135deg,#2c3045,#14151c);font-family:system-ui}
.l{height:10px;background:#ffffff14;border-radius:5px;margin:14px 18px}</style></head><body>
<div class="l" style="width:40%"></div><div class="l"></div><div class="l" style="width:70%"></div><div class="l" style="width:55%"></div>
<script>window.__IGFP_PREVIEW = 1; window.__IGFP_PREVIEW_PID = "live";</script>
<script><?= str_replace('</script', '<\/script', $js) ?></script>
</body></html>
