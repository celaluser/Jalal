<?php if (!defined('IGFP_ADMIN')) { http_response_code(403); exit('Forbidden'); } ?>
<!DOCTYPE html>
<html lang="tr"><head>
<meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1">
<meta name="robots" content="noindex,nofollow">
<title>Popup Paneli — Giriş</title>
<?php include __DIR__ . '/view-style.php'; ?>
</head><body>
<div class="center">
  <div class="logo"><i><svg viewBox="0 0 24 24"><path d="M13 2L3 14h8l-1 8 10-12h-8l1-8z"/></svg></i> Popup Paneli</div>
  <?php if ($msg): ?>
    <div class="flash <?= $msgType ?>"><svg viewBox="0 0 24 24"><circle cx="12" cy="12" r="9"/><path d="M12 8v5M12 16.5v.01"/></svg>
      <span><?= htmlspecialchars($msg) ?></span></div>
  <?php endif; ?>
  <div class="card">
    <?php if ($wait > 0): ?>
      <h2>Giriş geçici olarak kapalı</h2>
      <p class="hint">Çok fazla hatalı deneme yapıldı. <b><?= ceil($wait / 60) ?> dakika</b> sonra tekrar deneyin.</p>
    <?php else: ?>
      <h2>Giriş</h2>
      <p class="hint">Panele erişmek için şifreni gir.</p>
      <form method="post" class="body">
        <input type="hidden" name="action" value="login">
        <input type="password" name="pass" placeholder="Şifre" autofocus autocomplete="current-password">
        <div style="margin-top:14px"><button class="btn" type="submit" style="width:100%;justify-content:center">Giriş yap</button></div>
      </form>
    <?php endif; ?>
  </div>
</div>
</body></html>
