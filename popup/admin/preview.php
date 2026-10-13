<?php
define('IGFP_ADMIN', true);
/**
 * Admin panelin sağındaki canlı önizleme çerçevesi.
 * Girişi olmayan kimse açamaz.
 */
require __DIR__ . '/auth.php';
if (!adm_logged_in()) { http_response_code(403); exit('Forbidden'); }

$embed = popup_base_url() . '/embed.js?v=' . (is_file(popup_paths()['embed']) ? filemtime(popup_paths()['embed']) : time());
$pvpid = preg_replace('/[^0-9A-Za-z_-]/', '', (string) ($_GET['pid'] ?? ''));
?><!DOCTYPE html>
<html lang="tr"><head>
<meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1">
<meta name="robots" content="noindex,nofollow">
<title>Önizleme</title>
<style>
  html,body{margin:0;height:100%}
  body{background:radial-gradient(600px 400px at 20% 10%,rgba(139,92,246,.22),transparent 60%),
                  radial-gradient(500px 380px at 85% 85%,rgba(236,72,153,.14),transparent 60%),#07060c;
       font-family:-apple-system,BlinkMacSystemFont,"Segoe UI",Roboto,sans-serif}
  .note{position:fixed;left:0;right:0;bottom:0;padding:8px;text-align:center;
        color:#6f6c82;font-size:11.5px;background:rgba(0,0,0,.35)}
  .warn{color:#fca5a5;padding:20px;font-size:13px;line-height:1.6}
</style>
</head><body>
<?php if (!is_file(popup_paths()['embed'])): ?>
  <div class="warn">embed.js henüz üretilmemiş. Panelde <b>“Şimdi güncelle”</b> butonuna bas.</div>
<?php else: ?>
  <script>window.__IGFP_PREVIEW = 1; window.__IGFP_PREVIEW_PID = <?= json_encode($pvpid) ?>;</script>
  <script src="<?= htmlspecialchars($embed, ENT_QUOTES, 'UTF-8') ?>"></script>
  <div class="note">Önizleme — gösterim kuralları burada uygulanmaz</div>
<?php endif; ?>
</body></html>
