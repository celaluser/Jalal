<?php if (!defined('IGFP_ADMIN')) { http_response_code(403); exit('Forbidden'); } ?>
<!DOCTYPE html>
<html lang="tr"><head>
<meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1">
<meta name="robots" content="noindex,nofollow">
<title>Popup Paneli — İlk Kurulum</title>
<?php include __DIR__ . '/view-style.php'; ?>
</head><body>
<div class="center" style="max-width:600px">
  <div class="logo"><i><svg viewBox="0 0 24 24"><path d="M13 2L3 14h8l-1 8 10-12h-8l1-8z"/></svg></i> İlk Kurulum</div>
  <?php if ($msg): ?>
    <div class="flash <?= $msgType ?>"><svg viewBox="0 0 24 24"><circle cx="12" cy="12" r="9"/><path d="M12 8v5M12 16.5v.01"/></svg>
      <span><?= htmlspecialchars($msg) ?></span></div>
  <?php endif; ?>

  <?php if ($new === ''): ?>
    <div class="card">
      <h2>Panel şifreni belirle</h2>
      <p class="hint">Şifre sunucuya düz metin olarak kaydedilmez. Yazdığında bcrypt hash'ini üreteceğim,
         onu ayar dosyasına yapıştıracaksın.</p>
      <form method="post" class="body">
        <input type="hidden" name="action" value="makehash">
        <input type="password" name="pass" placeholder="En az 8 karakter" autofocus>
        <div style="margin-top:14px"><button class="btn" type="submit" style="width:100%;justify-content:center">Hash üret</button></div>
      </form>
    </div>
  <?php else: ?>
    <div class="card">
      <h2>Son adım</h2>
      <p class="hint">Aşağıdaki satırı
        <code><?= htmlspecialchars($secret ?: '/home/.../domains/uplow.us/popup-config.php') ?></code>
        dosyasındaki <code>return [</code> satırının altına ekle ve kaydet.</p>
      <div class="body">
        <textarea readonly onclick="this.select()" style="min-height:76px">    'admin_pass' => '<?= htmlspecialchars($new) ?>',</textarea>
        <div style="margin-top:14px"><a class="btn" href="index.php">Kaydettim, sayfayı yenile</a></div>
      </div>
    </div>
  <?php endif; ?>

  <?php if (!$secret): ?>
    <div class="flash err"><svg viewBox="0 0 24 24"><circle cx="12" cy="12" r="9"/><path d="M12 8v5M12 16.5v.01"/></svg>
      <span>Ayar dosyası (popup-config.php) bulunamadı. Önce onu oluşturman gerekiyor.</span></div>
  <?php endif; ?>
</div>
</body></html>
