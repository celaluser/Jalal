<?php
define('IGFP_ADMIN', true);
// IGFP-BUILD:10
/**
 * Kurulum / sürüm kontrolü: hangi dosyalar güncel, hangileri eski? Bu dosya tek başına çalışır.
 * Adres: /popup/admin/surum.php  (giriş gerekir)
 */
require __DIR__ . '/auth.php';
if (!adm_logged_in()) { header('Location: ./'); exit; }
const EXPECT = 10;
function e($s) { return htmlspecialchars((string) $s, ENT_QUOTES, 'UTF-8'); }

$msg = '';
if (($_SERVER['REQUEST_METHOD'] ?? '') === 'POST' && adm_csrf_ok()) {
    $ok = function_exists('opcache_reset') ? @opcache_reset() : false;
    $msg = $ok ? 'PHP önbelleği temizlendi. Panel sayfasını Ctrl+F5 ile yenile.' : 'Önbellek temizlenemedi (sunucu izin vermiyor ya da önbellek yok).';
}

$root = dirname(__DIR__);
$list = ['popup-core.php', 'popup-template.js', 'stat.php', 'coupon.php', 'data.php', 'popup-cron.php',
         'admin/index.php', 'admin/view-panel.php', 'admin/view-pstats.php', 'admin/popup-stats.php', 'admin/stats-lib.php',
         'admin/view-style.php', 'admin/preview-live.php', 'admin/preview.php', 'admin/surum.php'];
$rows = []; $bad = 0;
foreach ($list as $f) {
    $p = $root . '/' . $f;
    if (!is_file($p)) { $rows[] = [$f, 'yok', '', '', 'bad']; $bad++; continue; }
    $head = (string) @file_get_contents($p, false, null, 0, 2500);
    $b = preg_match('/IGFP-BUILD:(\d+)/', $head, $m) ? (int) $m[1] : 0;
    $st = $b === EXPECT ? 'güncel' : ($b === 0 ? 'ESKİ (işaretsiz)' : 'ESKİ (sürüm ' . $b . ')');
    if ($b !== EXPECT) $bad++;
    $rows[] = [$f, $st, date('d.m.Y H:i', (int) filemtime($p)), number_format(filesize($p) / 1024, 1) . ' KB', $b === EXPECT ? 'ok' : 'bad'];
}
$oc = function_exists('opcache_get_status') ? @opcache_get_status(false) : false;
$ini = function_exists('ini_get') ? [ini_get('opcache.enable'), ini_get('opcache.validate_timestamps'), ini_get('opcache.revalidate_freq')] : [];
?><!DOCTYPE html><html lang="tr"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1">
<meta name="robots" content="noindex,nofollow"><title>Sürüm kontrolü</title>
<style>body{margin:0;background:#0a0a11;color:#f2f0f8;font:14px system-ui,sans-serif;padding:24px}
.w{max-width:780px;margin:0 auto}h1{font-size:20px}a{color:#a78bfa}table{width:100%;border-collapse:collapse;margin:14px 0}
td,th{padding:9px 10px;border-bottom:1px solid #ffffff14;text-align:left}th{font-size:11px;letter-spacing:.06em;text-transform:uppercase;color:#9a96ad}
.ok{color:#4ade80;font-weight:600}.bad{color:#f87171;font-weight:700}.box{background:#13131c;border:1px solid #ffffff14;border-radius:12px;padding:14px 16px;margin:14px 0}
button{background:linear-gradient(135deg,#ec4899,#8b5cf6);color:#fff;border:0;border-radius:10px;padding:10px 16px;font-weight:600;cursor:pointer}
.msg{background:#0f2a1c;border:1px solid #14532d;color:#86efac;padding:10px 14px;border-radius:10px}</style></head><body><div class="w">
<h1>Sürüm kontrolü <small style="color:#9a96ad;font-weight:400">· beklenen sürüm <?= EXPECT ?></small></h1>
<p><a href="./">← Panele dön</a></p>
<?php if ($msg): ?><div class="msg"><?= e($msg) ?></div><?php endif; ?>
<div class="box"><?php if ($bad === 0): ?><span class="ok">Tüm dosyalar güncel.</span> Panelde eski görünüm görüyorsan PHP önbelleği (aşağıdaki buton) ya da tarayıcı önbelleği (Ctrl+F5) sebebidir.
<?php else: ?><span class="bad"><?= (int) $bad ?> dosya eski ya da eksik.</span> Zip'i <b>public_html</b> içine açıp mevcut dosyaların <b>üzerine yazdığından</b> emin ol
(klasör yapısı <code>public_html/popup/admin/...</code> olmalı, <code>popup/popup/...</code> olmamalı). Kırmızı satırlardaki dosyaları yeniden yükle.<?php endif; ?></div>
<table><tr><th>Dosya</th><th>Durum</th><th>Değiştirilme</th><th>Boyut</th></tr>
<?php foreach ($rows as $r): ?><tr><td><code><?= e($r[0]) ?></code></td><td class="<?= $r[4] ?>"><?= e($r[1]) ?></td><td><?= e($r[2]) ?></td><td><?= e($r[3]) ?></td></tr><?php endforeach; ?></table>
<div class="box">PHP <?= e(PHP_VERSION) ?> · opcache: <?= $oc ? 'açık' : 'kapalı/erişilemiyor' ?>
<?php if ($ini): ?> · validate_timestamps=<?= e($ini[1]) ?> · revalidate_freq=<?= e($ini[2]) ?>sn<?php endif; ?>
<form method="post" style="margin-top:12px"><input type="hidden" name="_t" value="<?= e(adm_csrf()) ?>"><button type="submit">PHP önbelleğini temizle</button></form></div>
</div></body></html>
