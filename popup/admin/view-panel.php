<?php
if (!defined('IGFP_ADMIN')) { http_response_code(403); exit('Forbidden'); }
$c   = $cfg;
$cl  = $cfg['client'];
$gr  = $cl['rules'];
$pg  = $cl['pages'];
$stc = $cl['stats'];
$tok = adm_csrf();
$themes = ['' => 'Otomatik (kategoriye göre)', 'instagram' => 'Instagram', 'tiktok' => 'TikTok',
           'whatsapp' => 'WhatsApp', 'telegram' => 'Telegram', 'youtube' => 'YouTube', 'default' => 'Varsayılan'];

/* --- durum özeti --- */
$days = []; $today = new DateTime();
for ($i = 13; $i >= 0; $i--) {
    $d = (clone $today)->modify("-$i day")->format('Y-m-d');
    $days[$d] = $stats[$d] ?? ['view' => 0, 'click' => 0];
}
$sumV = array_sum(array_map(function ($x) { return (int) ($x['view'] ?? 0); }, $days));
$sumC = array_sum(array_map(function ($x) { return (int) ($x['click'] ?? 0); }, $days));
$tdy  = $stats[date('Y-m-d')] ?? ['view' => 0, 'click' => 0];
$totalPopups = count($cfg['autos']) + count($cfg['popups']);
$activePopups = 0;
foreach ($cfg['autos'] as $a)  { if (!empty($a['enabled'])) $activePopups++; }
foreach ($cfg['popups'] as $p) { if (!empty($p['enabled'])) $activePopups++; }

function layout_svg(string $k): string {
  $bg = '<rect width="160" height="100" fill="#20233a"/><rect x="8" y="8" width="60" height="6" rx="3" fill="#3a3f5e"/><rect x="8" y="20" width="110" height="5" rx="2.5" fill="#323653"/><rect x="8" y="30" width="90" height="5" rx="2.5" fill="#323653"/><rect x="8" y="46" width="44" height="24" rx="4" fill="#2b2f4a"/><rect x="58" y="46" width="44" height="24" rx="4" fill="#2b2f4a"/><rect x="108" y="46" width="44" height="24" rx="4" fill="#2b2f4a"/>';
  $dim = '<rect width="160" height="100" fill="#000" opacity=".5"/>';
  $btn = function ($x, $y, $w) { return '<rect x="' . $x . '" y="' . $y . '" width="' . $w . '" height="9" rx="4.5" fill="#6366f1"/>'; };
  $ln  = function ($x, $y, $w, $c = '#c9ccdd') { return '<rect x="' . $x . '" y="' . $y . '" width="' . $w . '" height="4" rx="2" fill="' . $c . '"/>'; };
  $o = '';
  switch ($k) {
    case 'sheet':   $o = $dim . '<rect x="20" y="42" width="120" height="58" rx="8" fill="#fff"/><rect x="72" y="46" width="16" height="3" rx="1.5" fill="#cfd3e0"/>' . $ln(32, 56, 70, '#14161c') . $ln(32, 65, 90) . $btn(32, 78, 96); break;
    case 'drawer':  $o = $dim . '<rect x="96" y="0" width="64" height="100" fill="#0f172a"/>' . $ln(104, 12, 36, '#38bdf8') . $ln(104, 22, 46, '#fff') . '<rect x="104" y="34" width="48" height="10" rx="3" fill="#1e293b"/><rect x="104" y="48" width="48" height="10" rx="3" fill="#1e293b"/><rect x="104" y="62" width="48" height="10" rx="3" fill="#1e293b"/>' . $btn(104, 80, 48); break;
    case 'topbar':  $o = '<rect width="160" height="14" fill="#a855f7"/><rect x="8" y="5" width="70" height="4" rx="2" fill="#fff"/><rect x="128" y="3" width="22" height="8" rx="4" fill="#fff"/>'; break;
    case 'stickybar': $o = '<rect y="84" width="160" height="16" fill="#111827"/><rect x="8" y="90" width="64" height="4" rx="2" fill="#fff"/><rect x="118" y="87" width="32" height="10" rx="5" fill="#2aabee"/>'; break;
    case 'full':    $o = '<rect width="160" height="100" fill="#7c3aed"/><circle cx="80" cy="34" r="12" fill="#fff" opacity=".9"/>' . $ln(40, 54, 80, '#fff') . $ln(52, 63, 56, '#e9d5ff') . '<rect x="58" y="74" width="44" height="11" rx="5.5" fill="#fff"/>'; break;
    case 'split':   $o = $dim . '<rect x="22" y="22" width="116" height="58" rx="8" fill="#fff"/><rect x="22" y="22" width="48" height="58" rx="8" fill="#ec4899"/>' . $ln(78, 36, 50, '#14161c') . $ln(78, 45, 52) . $btn(78, 62, 50); break;
    case 'fab':     $o = '<rect x="88" y="32" width="64" height="40" rx="7" fill="#fff"/>' . $ln(94, 40, 40, '#14161c') . $ln(94, 49, 50) . '<rect x="94" y="58" width="52" height="8" rx="4" fill="#25d366"/><circle cx="140" cy="86" r="9" fill="#25d366"/>'; break;
    case 'wheel':   $o = $dim . '<rect x="38" y="8" width="84" height="86" rx="10" fill="#2e1065"/><circle cx="80" cy="46" r="26" fill="#fbbf24"/><path d="M80 46 L80 20 A26 26 0 0 1 106 46Z" fill="#f43f5e"/><path d="M80 46 L80 72 A26 26 0 0 1 54 46Z" fill="#22c55e"/><circle cx="80" cy="46" r="7" fill="#fff"/>' . $btn(52, 80, 56); break;
    case 'steps':   $o = $dim . '<rect x="30" y="14" width="100" height="72" rx="8" fill="#fff"/><rect x="38" y="22" width="28" height="3" rx="1.5" fill="#6366f1"/><rect x="69" y="22" width="28" height="3" rx="1.5" fill="#6366f1"/><rect x="100" y="22" width="22" height="3" rx="1.5" fill="#dfe2ee"/>' . $ln(38, 32, 60, '#14161c') . '<rect x="38" y="42" width="40" height="12" rx="4" fill="#eef2ff" stroke="#6366f1"/><rect x="82" y="42" width="40" height="12" rx="4" fill="#fff" stroke="#dfe2ee"/><rect x="38" y="58" width="40" height="12" rx="4" fill="#fff" stroke="#dfe2ee"/><rect x="82" y="58" width="40" height="12" rx="4" fill="#fff" stroke="#dfe2ee"/>'; break;
    case 'scratch': $o = $dim . '<rect x="32" y="12" width="96" height="76" rx="10" fill="#fff"/>' . $ln(50, 22, 60, '#14161c') . '<rect x="42" y="36" width="76" height="26" rx="5" fill="#b9bdcc"/><rect x="42" y="36" width="34" height="26" rx="5" fill="#fff"/><rect x="48" y="45" width="22" height="7" rx="2" fill="#e11d48"/>' . $btn(44, 70, 72); break;
  }
  return '<svg viewBox="0 0 160 100" width="100%" style="display:block">' . $bg . $o . '</svg>';
}

function ico($n) {
  $p = [
    'zap'    => '<path d="M13 2L3 14h8l-1 8 10-12h-8l1-8z"/>',
    'gauge'  => '<circle cx="12" cy="12" r="9"/><path d="M12 12l4-3"/>',
    'eye'    => '<path d="M2 12s3.6-7 10-7 10 7 10 7-3.6 7-10 7-10-7-10-7z"/><circle cx="12" cy="12" r="3"/>',
    'text'   => '<path d="M4 6h16M4 12h16M4 18h10"/>',
    'folder' => '<path d="M3 7a2 2 0 012-2h4l2 2h8a2 2 0 012 2v8a2 2 0 01-2 2H5a2 2 0 01-2-2z"/>',
    'route'  => '<circle cx="6" cy="18" r="3"/><circle cx="18" cy="6" r="3"/><path d="M6 15V9a3 3 0 013-3h6"/>',
    'edit'   => '<path d="M12 20h9"/><path d="M16.5 3.5a2.1 2.1 0 013 3L7 19l-4 1 1-4z"/>',
    'chart'  => '<path d="M4 20V10M10 20V4M16 20v-7M22 20H2"/>',
    'lock'   => '<rect x="4" y="11" width="16" height="10" rx="2"/><path d="M8 11V7a4 4 0 018 0v4"/>',
    'menu'   => '<path d="M4 7h16M4 12h16M4 17h16"/>',
    'refresh'=> '<path d="M21 12a9 9 0 11-3-6.7M21 3v6h-6"/>',
    'save'   => '<path d="M5 4h11l3 3v13H5z"/><path d="M9 4v5h6V4M9 20v-6h6v6"/>',
    'out'    => '<path d="M15 17l5-5-5-5"/><path d="M20 12H9"/><path d="M9 4H5v16h4"/>',
    'ok'     => '<circle cx="12" cy="12" r="9"/><path d="M8.5 12.5l2.5 2.5 4.5-5"/>',
    'warn'   => '<circle cx="12" cy="12" r="9"/><path d="M12 8v5M12 16.5v.01"/>',
    'layers' => '<path d="M12 2l9 5-9 5-9-5 9-5z"/><path d="M3 12l9 5 9-5M3 17l9 5 9-5"/>',
    'plus'   => '<path d="M12 5v14M5 12h14"/>',
    'trash'  => '<path d="M4 7h16M9 7V5a1 1 0 011-1h4a1 1 0 011 1v2M6 7l1 13h10l1-13"/>',
    'clock'  => '<circle cx="12" cy="12" r="9"/><path d="M12 7v5l3 2"/>',
    'power'  => '<path d="M12 3v9"/><path d="M18.4 6.6a9 9 0 11-12.8 0"/>',
    'shield' => '<path d="M12 3l8 3v6c0 5-3.4 8.4-8 9-4.6-.6-8-4-8-9V6z"/>',
    'user'   => '<circle cx="12" cy="8" r="4"/><path d="M4 21a8 8 0 0116 0"/>',
    'phone'  => '<rect x="7" y="2" width="10" height="20" rx="2"/><path d="M11 18h2"/>',
    'x'      => '<path d="M18 6L6 18M6 6l12 12"/>',
  ][$n] ?? '';
  return '<svg viewBox="0 0 24 24">' . $p . '</svg>';
}

/* ============ MODAL ALAN ÜRETİCİLERİ (JS değerleri doldurur) ============ */
function f_schedule(): void { ?>
    <div class="row"><div class="lbl">Saat aralığı<small>Boş = tüm gün. Gece yarısını aşan aralık da olur (22:00–06:00)</small></div>
      <div class="dual"><input type="time" name="sc_hfrom"><span class="dash">–</span><input type="time" name="sc_hto"></div></div>
    <div class="row"><div class="lbl">Günler<small>Hiçbiri seçili değilse her gün</small></div>
      <div class="days"><?php foreach (['Paz','Pzt','Sal','Çar','Per','Cum','Cmt'] as $i => $nm): ?>
        <label class="day"><input type="checkbox" name="sc_days[]" value="<?= $i ?>"><span><?= h($nm) ?></span></label>
      <?php endforeach; ?></div></div>
    <div class="row"><div class="lbl">Tarih aralığı<small>Kampanya başlangıç / bitiş · boş = sınırsız</small></div>
      <div class="dual"><input type="date" name="sc_dfrom"><span class="dash">→</span><input type="date" name="sc_dto"></div></div>
<?php }

function f_trigger(): void { ?>
    <div class="row"><div class="lbl">Ne zaman açılsın<small>Çıkış = ayrılmaya yönelince (masaüstü imleç, mobil hızlı yukarı kaydırma)</small></div>
      <select name="sc_trigtype" data-trig>
        <option value="load">Sayfa açılışında</option>
        <option value="exit">Çıkış niyetinde</option>
        <option value="scroll">Kaydırınca</option>
      </select></div>
    <div class="row" data-trigrow hidden><div class="lbl">Kaydırma yüzdesi<small>Sayfanın yüzde kaçında</small></div>
      <input type="number" name="sc_scrollpct" min="1" max="100"></div>
<?php }

function f_targeting(): void { ?>
    <div class="row"><div class="lbl">Cihaz<small>Hiçbiri seçili değilse tüm cihazlar</small></div>
      <div class="days">
        <label class="day"><input type="checkbox" name="sc_dev[]" value="desktop"><span>Masaüstü</span></label>
        <label class="day"><input type="checkbox" name="sc_dev[]" value="mobile"><span>Mobil</span></label>
      </div></div>
    <div class="row"><div class="lbl">Kaynak<small>Her satıra bir kelime · referrer veya UTM'de aranır · boş = herkes</small></div>
      <textarea name="sc_sources" placeholder="instagram&#10;utm_source=tiktok"></textarea></div>
<?php }

function f_segment(): void { ?>
    <p class="hint" style="margin:0 0 12px">Panelin sayfaya verdiği kullanıcı bilgisine göre filtreler.
       Bilgi yoksa ve burada kural varsa popup <b>gösterilmez</b>. Kurulum için "Gösterim kuralları → Segment verisi" bölümüne bak.</p>
    <div class="row"><div class="lbl">Giriş durumu</div>
      <select name="seg_login">
        <option value="">Farketmez</option>
        <option value="in">Sadece giriş yapmışlara</option>
        <option value="out">Sadece giriş yapmamışlara</option>
      </select></div>
    <div class="row"><div class="lbl">Bakiye aralığı<small>Boş = sınırsız</small></div>
      <div class="dual"><input type="text" name="seg_balmin" placeholder="min"><span class="dash">–</span><input type="text" name="seg_balmax" placeholder="maks"></div></div>
    <div class="row"><div class="lbl">Sipariş sayısı<small>Boş = sınırsız</small></div>
      <div class="dual"><input type="text" name="seg_ordmin" placeholder="min"><span class="dash">–</span><input type="text" name="seg_ordmax" placeholder="maks"></div></div>
    <div class="row"><div class="lbl">Ziyaretçi tipi</div>
      <select name="seg_visitor">
        <option value="">Farketmez</option>
        <option value="new">Sadece yeni ziyaretçi</option>
        <option value="returning">Sadece geri dönen</option>
      </select></div>
    <div class="row"><div class="lbl">En az kaçıncı ziyaret<small>0 = kapalı. Örn. 3 → 3. ziyaretten itibaren</small></div>
      <input type="number" name="seg_visitmin" min="0" max="999"></div>
    <div class="row"><div class="lbl">Ülkeler<small>İki harfli kodlar, virgülle. Boş = hepsi. Örn: AZ, TR, RU</small></div>
      <input type="text" name="seg_countries" placeholder="AZ, TR"></div>
<?php }

function f_overrides(): void { ?>
    <div class="row"><div class="lbl">Bu popup'a özel sıklık kuralı<small>Kapalıysa global kurallar geçerli</small></div>
      <label class="sw"><input type="checkbox" name="rules_on" data-toggle="rules"><i></i></label></div>
    <div data-box="rules" hidden>
      <div class="row"><div class="lbl">Günde en fazla</div><input type="number" name="r_perDay" min="0" max="50"></div>
      <div class="row"><div class="lbl">Sekme başına en fazla</div><input type="number" name="r_perSession" min="0" max="50"></div>
      <div class="row"><div class="lbl">İki gösterim arası (dk)</div><input type="number" name="r_minGapMinutes" min="0" max="10080"></div>
      <div class="row"><div class="lbl">Ömür boyu toplam<small>0 = sınırsız</small></div><input type="number" name="r_maxTotal" min="0" max="9999"></div>
      <div class="row"><div class="lbl">Tıklayana kaç gün gösterme</div><input type="number" name="r_clickCooldownDays" min="0" max="3650"></div>
    </div>
    <div class="row"><div class="lbl">Bu popup'a özel sayfa kuralı<small>Kapalıysa global sayfa kuralı geçerli</small></div>
      <label class="sw"><input type="checkbox" name="pages_on" data-toggle="pages"><i></i></label></div>
    <div data-box="pages" hidden>
      <div class="row"><div class="lbl">Kural tipi</div>
        <select name="pp_mode"><option value="exclude">Aşağıdakiler HARİÇ</option><option value="include">SADECE aşağıdakiler</option></select></div>
      <div class="row"><div class="lbl">Adres listesi<small>Her satıra bir adres · <code>*</code> joker</small></div>
        <textarea name="pp_patterns" placeholder="/order*&#10;/checkout*"></textarea></div>
    </div>
<?php }

function f_common_head(array $themes, bool $isAuto): void { ?>
    <div class="row"><div class="lbl">Ad<small>Sadece panelde görünür</small></div>
      <input type="text" name="name" placeholder="<?= $isAuto ? 'TikTok otomatik' : 'Telegram kanalı' ?>"></div>
    <div class="row"><div class="lbl">Yayında</div>
      <label class="sw"><input type="checkbox" name="enabled"><i></i></label></div>
    <div class="row"><div class="lbl">Öncelik<small>Küçük sayı önce gösterilir</small></div>
      <input type="number" name="priority" min="0" max="999"></div>
    <div class="row"><div class="lbl">Renk teması</div>
      <select name="theme"><?php foreach ($themes as $k => $v): if (!$isAuto && $k === '') continue; ?>
        <option value="<?= $k ?>"><?= h($v) ?></option>
      <?php endforeach; ?></select></div>
<?php }

$nav = [
  'durum'      => ['gauge',  'Durum'],
  'popuplar'   => ['layers', 'Popuplar'],
  'sablonlar'  => ['eye',    'Şablonlar'],
  'gorunum'    => ['eye',    'Gösterim kuralları'],
  'sayfalar'   => ['route',  'Sayfa kuralları'],
  'istatistik' => ['chart',  'İstatistik'],
  'bakim'      => ['shield', 'Yedek & günlük'],
  'sifre'      => ['lock',   'Şifre'],
];
?><!DOCTYPE html>
<html lang="tr"><head>
<meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1">
<meta name="robots" content="noindex,nofollow">
<title>Popup Paneli</title>
<?php include __DIR__ . '/view-style.php'; ?>
</head><body>

<div class="scrim" onclick="document.body.classList.remove('nav-open')"></div>

<div class="app">
  <aside class="side" id="side">
    <div class="logo"><i><?= ico('zap') ?></i> Popup Paneli</div>
    <nav id="nav">
      <?php foreach ($nav as $k => $v): ?>
        <a data-t="<?= $k ?>" class="<?= $k === 'durum' ? 'on' : '' ?>"><?= ico($v[0]) ?><span><?= h($v[1]) ?></span></a>
      <?php endforeach; ?>
    </nav>
    <div class="side-foot">
      <?= $activePopups ?>/<?= $totalPopups ?> popup aktif<br>
      <?= isset($payload['updated']) ? 'Son veri: ' . date('d.m H:i', $payload['updated']) : 'Veri yok' ?>
    </div>
  </aside>

  <div class="main">
    <div class="top">
      <button class="burger" type="button" onclick="document.body.classList.toggle('nav-open')" aria-label="Menü"><?= ico('menu') ?></button>
      <h1 id="ptitle">Durum</h1>
      <?php if ($kill['off']): ?>
        <span class="pill off"><b></b>ACİL DURDURULDU</span>
      <?php else: ?>
        <span class="pill <?= $c['enabled'] ? 'on' : 'off' ?>"><b></b><?= $c['enabled'] ? 'Yayında' : 'Kapalı' ?></span>
      <?php endif; ?>
      <form method="post" style="display:inline">
        <input type="hidden" name="_t" value="<?= $tok ?>"><input type="hidden" name="action" value="run">
        <button class="btn sec sm" type="submit"><?= ico('refresh') ?><span>Şimdi güncelle</span></button>
      </form>
      <a class="btn sec sm" href="?logout=1"><?= ico('out') ?></a>
    </div>

    <div class="inner">
    <?php if ($msg): ?>
      <div class="flash <?= $msgType ?>"><?= ico($msgType === 'ok' ? 'ok' : 'warn') ?><span><?= h($msg) ?></span></div>
    <?php endif; ?>

    <div class="split">
    <div>

    <!-- ═════════ DURUM (form dışında: acil durdurma kendi formu) ═════════ -->
    <div class="pane" data-p="durum">
      <?php if ($kill['off']): ?>
        <div class="flash err"><?= ico('power') ?><span><b>Popuplar acil durdurulmuş durumda.</b>
          <?= $kill['ts'] ? date('d.m.Y H:i', $kill['ts']) : '' ?><?= $kill['note'] ? ' — ' . h($kill['note']) : '' ?>
          Sitede hiçbir popup açılmıyor.</span></div>
      <?php endif; ?>

      <div class="tiles">
        <div class="tile"><div class="t-lbl">Bugün gösterim</div><div class="t-val"><?= (int) ($tdy['view'] ?? 0) ?></div>
          <div class="t-sub"><?= (int) ($tdy['click'] ?? 0) ?> tıklama</div></div>
        <div class="tile"><div class="t-lbl">14 günlük</div><div class="t-val"><?= $sumV ?></div>
          <div class="t-sub">oran %<?= $sumV ? round($sumC / $sumV * 100, 1) : 0 ?></div></div>
        <div class="tile"><div class="t-lbl">Aktif popup</div><div class="t-val"><?= $activePopups ?></div>
          <div class="t-sub"><?= count($cfg['autos']) ?> oto · <?= count($cfg['popups']) ?> manuel</div></div>
        <div class="tile"><div class="t-lbl">Veri yaşı</div><div class="t-val"><?=
            $embedAge === null ? '—' : ($embedAge < 3600 ? floor($embedAge / 60) . 'dk' : floor($embedAge / 3600) . 'sa') ?></div>
          <div class="t-sub">embed.js</div></div>
      </div>

      <div class="card kill-card <?= $kill['off'] ? 'on' : '' ?>">
        <h2><?= ico('power') ?> Acil durdurma</h2>
        <p class="hint">Tüm popupları <b>anında</b> durdurur — embed.js önbelleğini bekletmeden.
           Ayarların ve popupların silinmez, sadece gösterim durur.</p>
        <div class="body">
          <form method="post" style="display:flex;gap:10px;flex-wrap:wrap;align-items:center">
            <input type="hidden" name="_t" value="<?= $tok ?>">
            <?php if ($kill['off']): ?>
              <input type="hidden" name="action" value="kill_off">
              <button class="btn" type="submit"><?= ico('ok') ?><span>Yayını yeniden başlat</span></button>
              <span class="note">Şu an durduruldu.</span>
            <?php else: ?>
              <input type="hidden" name="action" value="kill_on">
              <input type="text" name="note" placeholder="Sebep (opsiyonel)" style="max-width:260px">
              <button class="btn danger" type="submit"><?= ico('power') ?><span>ACİL DURDUR</span></button>
            <?php endif; ?>
          </form>
        </div>
      </div>

      <div class="card">
        <h2><?= ico('gauge') ?> Son durum</h2>
        <div class="body kvs">
          <div class="kv"><span class="k">Yayındaki kategori</span><span class="v"><?= h($payload['category'] ?? '—') ?></span></div>
          <div class="kv"><span class="k">Son güncelleme</span><span class="v"><?= isset($payload['updated']) ? date('d.m.Y H:i', $payload['updated']) : '—' ?></span></div>
          <div class="kv"><span class="k">embed.js</span><span class="v"><code><?= h(popup_base_url()) ?>/embed.js</code></span></div>
          <div class="kv"><span class="k">Şema</span><span class="v">v<?= (int) ($cfg['schema_version'] ?? 0) ?></span></div>
        </div>
      </div>

      <div class="card">
        <h2><?= ico('chart') ?> Kayıt defteri</h2>
        <p class="hint">Son 12 satır.</p>
        <div class="body"><pre><?= $logTail ? h(implode("\n", $logTail)) : 'Henüz kayıt yok.' ?></pre></div>
      </div>
    </div>

    <!-- ═════════ POPUPLAR (kısa liste — düzenleme modalda) ═════════ -->
    <div class="pane" data-p="popuplar" hidden>

      <?php
        $lb = null; foreach ($cfg['popups'] as $x) { if (($x['id'] ?? '') === 'lowbal') { $lb = $x; break; } }
        $lbOn  = $lb && !empty($lb['enabled']);
        $lbMax = ($lb && ($lb['segment']['balanceMax'] ?? '') !== '') ? (float) $lb['segment']['balanceMax'] : 0.99;
        $lbThr = rtrim(rtrim(number_format($lbMax + 0.01, 2, '.', ''), '0'), '.');
      ?>
      <div class="card">
        <h2><?= ico('zap') ?> Düşük bakiye uyarısı
          <span class="pill <?= $lbOn ? 'on' : 'off' ?>" style="margin-left:8px"><b></b><?= $lbOn ? 'Açık' : 'Kapalı' ?></span></h2>
        <p class="hint">Giriş yapmış ve bakiyesi <b>eşiğin altında</b> olanlara "Bakiye ekle" şeridi gösterir.
           Bakiye, sitede görünen bakiye yazısından okunur. Anahtarla istediğin an açıp kapatabilirsin.</p>
        <div class="body">
          <form method="post">
            <input type="hidden" name="_t" value="<?= $tok ?>"><input type="hidden" name="action" value="lowbal_save">
            <div class="rows">
              <div class="row"><div class="lbl">Uyarı açık<small>Kapatınca hemen kaydedilir, popup gösterilmez</small></div>
                <label class="sw"><input type="checkbox" name="lb_on" <?= $lbOn ? 'checked' : '' ?> onchange="this.form.submit()"><i></i></label></div>
              <div class="row"><div class="lbl">Bakiye eşiği (₼)<small>Bakiye bu değerin <b>altındaysa</b> gösterilir</small></div>
                <input type="text" name="lb_thr" value="<?= h($lbThr) ?>" placeholder="1"></div>
              <div class="row"><div class="lbl">Başlık</div>
                <input type="text" name="lb_title" value="<?= h($lb['title'] ?? 'Balansınız azdır') ?>"></div>
              <div class="row"><div class="lbl">Açıklama</div>
                <input type="text" name="lb_sub" value="<?= h($lb['subtitle'] ?? 'Sifarişlərin dayanmaması üçün balansı artırın.') ?>"></div>
              <div class="row"><div class="lbl">Buton yazısı</div>
                <input type="text" name="lb_btn" value="<?= h($lb['btn_text'] ?? 'Balans artır') ?>"></div>
              <div class="row"><div class="lbl">Buton linki<small>Panelinin bakiye yükleme sayfası</small></div>
                <input type="text" name="lb_url" value="<?= h($lb['btn_url'] ?? '/addfunds') ?>"></div>
              <div class="row"><div class="lbl">Bakiye seçicisi (CSS)<small>Sayfada bakiye yazan kutu. Genelde değiştirmen gerekmez.</small></div>
                <input type="text" name="lb_sel" value="<?= h($cl['segment']['balanceSelector'] ?? '.default-dropdown__toggle') ?>"></div>
            </div>
            <div style="margin-top:14px"><button class="btn" type="submit"><?= ico('save') ?><span>Kaydet</span></button>
              <?php if ($lb): ?><button class="btn sec" type="button" data-edit="manual" data-id="lowbal"><?= ico('edit') ?><span>Gelişmiş düzenle</span></button><?php endif; ?></div>
          </form>
        </div>
      </div>

      <?php
        $layers = [];
        foreach ($cfg['autos'] as $a)   { $layers[] = ['k' => 'a:' . $a['id'], 'n' => $a['name'] ?? $a['id'], 't' => 'Otomatik', 'pr' => (int) ($a['priority'] ?? 10), 'on' => !empty($a['enabled'])]; }
        foreach ($cfg['popups'] as $m)  { $layers[] = ['k' => 'm:' . $m['id'], 'n' => $m['name'] ?? $m['id'], 't' => 'Manuel',   'pr' => (int) ($m['priority'] ?? 20), 'on' => !empty($m['enabled'])]; }
        usort($layers, function ($x, $y) { return $x['pr'] <=> $y['pr']; });
      ?>
      <?php if (count($layers) > 1): ?>
      <div class="card">
        <h2><?= ico('layers') ?> Katman sırası</h2>
        <p class="hint">Popupların <b>hangisi önce çıkacağını</b> belirler. Üstteki önce gösterilir, alttakiler
           sırayla gelir (aynı anda en fazla <i>Sayfa başına en fazla</i> (Global ayarlar) kadar). Sürükle ya da ▲▼ ile değiştir, sonra kaydet.</p>
        <div class="body">
          <form method="post" id="orderform">
            <input type="hidden" name="_t" value="<?= $tok ?>"><input type="hidden" name="action" value="order_save">
            <input type="hidden" name="order" id="orderval">
            <ol id="layerlist" style="list-style:none;margin:0;padding:0">
              <?php foreach ($layers as $i => $l): ?>
              <li class="poprow" draggable="true" data-k="<?= h($l['k']) ?>" style="cursor:grab">
                <span class="pill on" style="min-width:28px;justify-content:center"><b></b><span class="lnum"><?= $i + 1 ?></span></span>
                <div class="poprow-main">
                  <div class="poprow-name"><?= h($l['n']) ?> <span class="ptheme"><?= $l['t'] ?><?= $l['on'] ? '' : ' · kapalı' ?></span></div>
                </div>
                <button class="btn sec sm" type="button" data-mv="-1" title="Yukarı">▲</button>
                <button class="btn sec sm" type="button" data-mv="1" title="Aşağı">▼</button>
              </li>
              <?php endforeach; ?>
            </ol>
            <div style="margin-top:14px"><button class="btn" type="submit"><span>Sırayı kaydet</span></button></div>
          </form>
        </div>
      </div>
      <script>
      (function () {
        var ol = document.getElementById('layerlist'); if (!ol) return;
        var drag = null;
        function renum() { var n = ol.children; for (var i = 0; i < n.length; i++) n[i].querySelector('.lnum').textContent = i + 1; }
        ol.addEventListener('click', function (e) {
          var b = e.target.closest('[data-mv]'); if (!b) return;
          var li = b.closest('li'), d = parseInt(b.getAttribute('data-mv'), 10);
          if (d < 0 && li.previousElementSibling) ol.insertBefore(li, li.previousElementSibling);
          else if (d > 0 && li.nextElementSibling) ol.insertBefore(li.nextElementSibling, li);
          renum();
        });
        ol.addEventListener('dragstart', function (e) { drag = e.target.closest('li'); if (e.dataTransfer) e.dataTransfer.setData('text/plain', ''); });
        ol.addEventListener('dragover', function (e) {
          e.preventDefault(); var t = e.target.closest('li'); if (!t || !drag || t === drag) return;
          var r = t.getBoundingClientRect();
          ol.insertBefore(drag, (e.clientY - r.top) < r.height / 2 ? t : t.nextSibling);
        });
        ol.addEventListener('dragend', function () { drag = null; renum(); });
        document.getElementById('orderform').addEventListener('submit', function () {
          var k = []; for (var i = 0; i < ol.children.length; i++) k.push(ol.children[i].getAttribute('data-k'));
          document.getElementById('orderval').value = k.join(',');
        });
      })();
      </script>
      <?php endif; ?>

      <div class="card">
        <h2><?= ico('refresh') ?> Otomatik servis popupları</h2>
        <p class="hint">Panel API'sinden servisleri çeker. Her biri kendi kategorisi, fiyat biçimi ve
           metinleriyle bağımsız çalışır — API yine de <b>tek sefer</b> çağrılır.</p>
        <div class="body">
          <?php if (!$cfg['autos']): ?>
            <p class="hint" style="margin:0">Henüz otomatik popup yok.</p>
          <?php else: foreach ($cfg['autos'] as $a): ?>
            <div class="poprow">
              <div class="poprow-main">
                <div class="poprow-name"><?= h($a['name'] ?? $a['id']) ?>
                  <span class="ptheme"><?= h($a['category_mode'] === 'manual' ? ($a['category_manual'] ?: 'elle') : ($a['category_mode'] === 'newest' ? 'en yeni' : 'ilk kategori')) ?></span></div>
                <div class="poprow-sub"><?= ico('clock') ?><?= h(sched_summary((array) ($a['schedule'] ?? []))) ?>
                  · <?= h(trig_summary((array) ($a['trigger'] ?? []))) ?>
                  · <?= (int) $a['count'] ?> servis · öncelik <?= (int) $a['priority'] ?>
                  <?php $ss = seg_summary($a['segment'] ?? null); if ($ss): ?>· <span class="segtag"><?= ico('user') ?><?= h($ss) ?></span><?php endif; ?>
                </div>
              </div>
              <span class="pill <?= !empty($a['enabled']) ? 'on' : 'off' ?>"><b></b><?= !empty($a['enabled']) ? 'Açık' : 'Kapalı' ?></span>
              <button class="btn sec sm" type="button" data-edit="auto" data-id="<?= h($a['id']) ?>"><?= ico('edit') ?><span>Düzenle</span></button>
              <form method="post" style="display:inline">
                <input type="hidden" name="_t" value="<?= $tok ?>"><input type="hidden" name="action" value="auto_toggle">
                <input type="hidden" name="pid" value="<?= h($a['id']) ?>">
                <button class="btn sec sm" type="submit"><?= !empty($a['enabled']) ? 'Durdur' : 'Başlat' ?></button>
              </form>
              <form method="post" style="display:inline">
                <input type="hidden" name="_t" value="<?= $tok ?>"><input type="hidden" name="action" value="auto_dup">
                <input type="hidden" name="pid" value="<?= h($a['id']) ?>">
                <button class="btn sec sm" type="submit" title="Kopyala"><?= ico('layers') ?><span>Kopyala</span></button>
              </form>
              <form method="post" style="display:inline" onsubmit="return confirm('Bu otomatik popup silinsin mi?')">
                <input type="hidden" name="_t" value="<?= $tok ?>"><input type="hidden" name="action" value="auto_del">
                <input type="hidden" name="pid" value="<?= h($a['id']) ?>">
                <button class="btn sec sm" type="submit" title="Sil"><?= ico('trash') ?></button>
              </form>
            </div>
          <?php endforeach; endif; ?>
          <div style="margin-top:14px">
            <button class="btn" type="button" data-edit="auto" data-id=""><?= ico('plus') ?><span>Yeni otomatik popup</span></button>
          </div>
        </div>
      </div>

      <div class="card">
        <h2><?= ico('layers') ?> Manuel popuplar</h2>
        <p class="hint">Elle hazırladıkların: Telegram, duyuru, kampanya… Otomatiklerle birlikte kuyruğa girer.</p>
        <div class="body">
          <?php if (!$cfg['popups']): ?>
            <p class="hint" style="margin:0">Henüz manuel popup yok.</p>
          <?php else: foreach ($cfg['popups'] as $p): ?>
            <div class="poprow">
              <div class="poprow-main">
                <div class="poprow-name"><?= h($p['name'] ?? $p['id']) ?>
                  <span class="ptheme"><?= h($p['theme'] ?? '') ?></span></div>
                <div class="poprow-sub"><?= ico('clock') ?><?= h(sched_summary((array) ($p['schedule'] ?? []))) ?>
                  · <?= h(trig_summary((array) ($p['trigger'] ?? []))) ?>
                  · öncelik <?= (int) ($p['priority'] ?? 20) ?>
                  <?php if (!empty($p['countdown'])): ?>· <span class="tagx">sayaç</span><?php endif; ?>
                  <?php if (!empty($p['coupon'])): ?>· <span class="tagx">kupon</span><?php endif; ?>
                  <?php if (!empty($p['limit_on'])): $cu = $couponUse[$p['id']] ?? ['used' => 0, 'users' => 0]; ?>· <span class="tagx">limit: <?= (int) $cu['used'] ?><?= !empty($p['coupon_total']) ? '/' . (int) $p['coupon_total'] : '' ?> verildi</span>
                    <form method="post" style="display:inline" onsubmit="return confirm('Kupon sayaçları sıfırlansın mı? Herkes yeniden hak kazanabilir.')">
                      <input type="hidden" name="_t" value="<?= $tok ?>"><input type="hidden" name="action" value="coupon_reset"><input type="hidden" name="pid" value="<?= h($p['id']) ?>">
                      <button class="tagx" type="submit" style="cursor:pointer;border:0">sıfırla</button></form><?php endif; ?>
                  <?php $ss = seg_summary($p['segment'] ?? null); if ($ss): ?>· <span class="segtag"><?= ico('user') ?><?= h($ss) ?></span><?php endif; ?>
                </div>
              </div>
              <span class="pill <?= !empty($p['enabled']) ? 'on' : 'off' ?>"><b></b><?= !empty($p['enabled']) ? 'Açık' : 'Kapalı' ?></span>
              <button class="btn sec sm" type="button" data-edit="manual" data-id="<?= h($p['id']) ?>"><?= ico('edit') ?><span>Düzenle</span></button>
              <form method="post" style="display:inline">
                <input type="hidden" name="_t" value="<?= $tok ?>"><input type="hidden" name="action" value="popup_toggle">
                <input type="hidden" name="pid" value="<?= h($p['id']) ?>">
                <button class="btn sec sm" type="submit"><?= !empty($p['enabled']) ? 'Durdur' : 'Başlat' ?></button>
              </form>
              <form method="post" style="display:inline">
                <input type="hidden" name="_t" value="<?= $tok ?>"><input type="hidden" name="action" value="popup_dup">
                <input type="hidden" name="pid" value="<?= h($p['id']) ?>">
                <button class="btn sec sm" type="submit" title="Kopyala"><?= ico('layers') ?><span>Kopyala</span></button>
              </form>
              <button class="btn sec sm" type="button" data-mytpl-save="<?= h($p['id']) ?>" title="Şablonlarıma kaydet"><?= ico('save') ?><span>Şablon</span></button>
              <form method="post" style="display:inline" onsubmit="return confirm('Bu popup silinsin mi?')">
                <input type="hidden" name="_t" value="<?= $tok ?>"><input type="hidden" name="action" value="popup_del">
                <input type="hidden" name="pid" value="<?= h($p['id']) ?>">
                <button class="btn sec sm" type="submit" title="Sil"><?= ico('trash') ?></button>
              </form>
            </div>
          <?php endforeach; endif; ?>
          <div style="margin-top:14px">
            <button class="btn" type="button" data-edit="manual" data-id=""><?= ico('plus') ?><span>Yeni manuel popup</span></button>
          </div>
        </div>
      </div>
    </div>

    <!-- ═════════ ŞABLONLAR (hazır görünümler) ═════════ -->
    <div class="pane" data-p="sablonlar" hidden>
      <div class="card">
        <h2><?= ico('save') ?> Şablonlarım</h2>
        <p class="hint">Beğendiğin popup'ı "Popuplar" sekmesindeki <b>Şablon</b> butonuyla buraya kaydet; sonra tek tıkla aynı tasarımla yeni popup aç.</p>
        <div class="body">
          <?php if (!$myTpls): ?><p class="hint" style="margin:0">Henüz kayıtlı şablonun yok.</p>
          <?php else: foreach ($myTpls as $tid => $t): ?>
            <div class="poprow">
              <div class="poprow-main"><div class="poprow-name"><?= h($t['name'] ?? $tid) ?>
                <span class="ptheme"><?= h(popup_layouts()[$t['data']['layout'] ?? ''] ?? 'Klasik') ?></span></div></div>
              <button class="btn sm" type="button" data-mytpl-use="<?= h($tid) ?>">Kullan</button>
              <form method="post" style="display:inline" onsubmit="return confirm('Bu şablon silinsin mi?')">
                <input type="hidden" name="_t" value="<?= $tok ?>"><input type="hidden" name="action" value="mytpl_del"><input type="hidden" name="tid" value="<?= h($tid) ?>">
                <button class="btn sec sm" type="submit" title="Sil"><?= ico('trash') ?></button></form>
            </div>
          <?php endforeach; endif; ?>
        </div>
      </div>

      <div class="card">
        <h2><?= ico('layers') ?> Yerleşimler (popup biçimleri)</h2>
        <p class="hint">Popup'ın <b>ekrandaki şeklini</b> belirler: alt panel, yan panel, şerit, çark… Birini seç; hazır içerikle açılır.
           Renk ve metinlerin hepsini <b>Stil</b> ve <b>İçerik</b> sekmelerinden değiştirebilirsin.</p>
        <div class="body">
          <div class="tplgrid">
          <?php foreach (popup_layouts() as $lk => $ln): ?>
            <div class="tplc">
              <div class="tplp" style="padding:0;height:auto"><?= layout_svg($lk) ?></div>
              <div class="tplf"><span><?= h($ln) ?></span>
                <button class="btn sm" type="button" data-lay-use="<?= h($lk) ?>">Kullan</button></div>
            </div>
          <?php endforeach; ?>
          </div>
        </div>
      </div>

      <div class="card">
        <h2><?= ico('eye') ?> Hazır şablonlar</h2>
        <p class="hint">Beğendiğin şablonu seç: başlık, metin, buton ve renkler hazır dolu gelir. Sadece linki ve
           kuponu kendine göre değiştirip kaydet. Sonradan her şey düzenlenebilir.</p>
        <div class="body">
          <div class="tplgrid">
          <?php $ti = 0; foreach (popup_skins() as $sk => $v): $ti++; ?>
            <div class="tplc">
              <div class="tplp"><div class="tplm" style="background:<?= h($v['bg']) ?>;color:<?= h($v['fg']) ?>;border-radius:<?= h($v['r']) ?>;<?= h($v['x']) ?>">
                <span class="tplb" style="background:<?= h($v['bb']) ?>;color:<?= h($v['bf']) ?>"><?= h($v['t']['badge'] ?: 'Yeni') ?></span>
                <b><?= h($v['t']['title']) ?></b>
                <i style="background:<?= h($v['btn']) ?>;color:<?= h($v['btnf']) ?>;border-radius:<?= h($v['br']) ?>"><?= h($v['t']['btn_text']) ?></i>
              </div></div>
              <div class="tplf"><span><b><?= $ti ?>.</b> <?= h($v['n']) ?></span>
                <button class="btn sm" type="button" data-tpl="<?= h($sk) ?>">Kullan</button></div>
            </div>
          <?php endforeach; ?>
          </div>
        </div>
      </div>
    </div>

    <!-- ═════════ GLOBAL AYARLAR (tek form) ═════════ -->
    <form method="post" id="mainform">
    <input type="hidden" name="_t" value="<?= $tok ?>">
    <input type="hidden" name="action" value="save">

    <div class="pane" data-p="gorunum" hidden>
      <div class="card">
        <h2><?= ico('zap') ?> Ana şalter</h2>
        <p class="hint">Kapatırsan hiçbir popup gösterilmez ve cron güncelleme yapmaz.</p>
        <div class="body"><label class="sw"><input type="checkbox" name="enabled_global" <?= $c['enabled'] ? 'checked' : '' ?>><i></i>
          <span>Popup sistemi açık</span></label></div>
      </div>

      <div class="card">
        <h2><?= ico('eye') ?> Varsayılan gösterim kuralları</h2>
        <p class="hint">Kendi kuralı olmayan popuplar bunları kullanır. <b>0 = o kural kapalı.</b>
           Sayaçlar ziyaretçinin tarayıcısında tutulur.</p>
        <div class="rows">
          <div class="row"><div class="lbl">Günde en fazla</div><input type="number" name="perDay" min="0" max="50" value="<?= (int) $gr['perDay'] ?>"></div>
          <div class="row"><div class="lbl">Sekme başına en fazla</div><input type="number" name="perSession" min="0" max="50" value="<?= (int) $gr['perSession'] ?>"></div>
          <div class="row"><div class="lbl">İki gösterim arası (dk)</div><input type="number" name="minGapMinutes" min="0" max="10080" value="<?= (int) $gr['minGapMinutes'] ?>"></div>
          <div class="row"><div class="lbl">Ömür boyu toplam<small>0 = sınırsız</small></div><input type="number" name="maxTotal" min="0" max="9999" value="<?= (int) $gr['maxTotal'] ?>"></div>
          <div class="row"><div class="lbl">Tıklayana kaç gün gösterme</div><input type="number" name="clickCooldownDays" min="0" max="3650" value="<?= (int) $gr['clickCooldownDays'] ?>"></div>
        </div>
      </div>

      <div class="card">
        <h2><?= ico('layers') ?> Kuyruk & zaman</h2>
        <div class="rows">
          <div class="row"><div class="lbl">Sayfa başına en fazla<small>Bir açılışta kaç popup (sırayla)</small></div>
            <input type="number" name="maxPerLoad" min="1" max="6" value="<?= (int) ($cl['maxPerLoad'] ?? 2) ?>"></div>
          <div class="row"><div class="lbl">Sıradaki arası bekleme<small>Milisaniye</small></div>
            <input type="number" name="queueGapMs" min="0" max="60000" step="100" value="<?= (int) ($cl['queueGapMs'] ?? 700) ?>"></div>
          <div class="row"><div class="lbl">Saat dilimi (dakika)<small>Zamanlama buna göre · Baku = 240 (UTC+4)</small></div>
            <input type="number" name="tzOffset" min="-720" max="840" step="15" value="<?= (int) ($cl['tzOffset'] ?? 240) ?>"></div>
          <div class="row"><div class="lbl">Açılma gecikmesi<small>Milisaniye — 1000 = 1 saniye</small></div>
            <input type="number" name="delay" min="0" max="60000" step="100" value="<?= (int) $cl['delay'] ?>"></div>
        </div>
      </div>

      <div class="card">
        <h2><?= ico('text') ?> Görünüm</h2>
        <div class="rows">
          <div class="row"><div class="lbl">Alt yazı</div><input type="text" name="footer" value="<?= h($cl['footer']) ?>"></div>
          <div class="row"><div class="lbl">Renk teması (global)<small>Popup kendi temasını seçmezse</small></div>
            <select name="forceTheme"><?php foreach ($themes as $k => $v): ?>
              <option value="<?= $k ?>" <?= $cl['forceTheme'] === $k ? 'selected' : '' ?>><?= h($v) ?></option>
            <?php endforeach; ?></select></div>
          <div class="row"><div class="lbl">Yeni sekmede aç</div>
            <label class="sw"><input type="checkbox" name="openInNewTab" <?= $cl['openInNewTab'] ? 'checked' : '' ?>><i></i></label></div>
          <div class="row"><div class="lbl">Veri bayatlarsa gizle<small>Kaç saat sonra · 0 = kapalı</small></div>
            <input type="number" name="maxAgeHours" min="0" max="720" value="<?= (int) $cl['maxAgeHours'] ?>"></div>
        </div>
      </div>

      <div class="card">
        <h2><?= ico('user') ?> Segment verisi (panel entegrasyonu)</h2>
        <p class="hint">Segmentasyon (giriş/bakiye/sipariş) için panelin sayfaya kullanıcı bilgisi vermesi gerekir.
           Sitenin <code>&lt;head&gt;</code>'ine şunu ekle:</p>
        <div class="body">
          <pre>&lt;script&gt;
window.<?= h(($cl['segment']['varName'] ?? 'PANEL_USER')) ?> = {
  loggedIn: true,      // giriş yapmış mı
  balance: 12.50,      // bakiye
  orders: 34,          // toplam sipariş sayısı
  country: "AZ"        // opsiyonel
};
&lt;/script&gt;</pre>
          <p class="hint" style="margin-top:10px">Alternatif: <code>&lt;body data-igfp-user='{"loggedIn":1,"balance":12.5}'&gt;</code></p>
        </div>
        <div class="rows" style="margin-top:6px">
          <div class="row"><div class="lbl">Değişken adı</div>
            <input type="text" name="seg_varname" value="<?= h($cl['segment']['varName'] ?? 'PANEL_USER') ?>"></div>
          <div class="row"><div class="lbl">Bakiye seçicisi (CSS)<small>Sayfada bakiye yazan kutunun CSS seçicisi. <code>PANEL_USER</code> yoksa bakiye buradan okunur ("1,00 ₼" → 1). Boş = kapalı.</small></div>
            <input type="text" name="seg_balsel" value="<?= h($cl['segment']['balanceSelector'] ?? '.default-dropdown__toggle') ?>" placeholder=".default-dropdown__toggle"></div>
        </div>
      </div>

      <div class="card">
        <h2><?= ico('power') ?> Acil durdurma kontrolü</h2>
        <p class="hint">Açıkken embed.js her sayfa açılışında küçük bir durum sorgusu yapar; böylece acil
           durdurma önbelleği beklemeden etkili olur. Sorgu başarısız olursa popuplar <b>çalışmaya devam eder</b>.</p>
        <div class="rows">
          <div class="row"><div class="lbl">Durum kontrolü açık</div>
            <label class="sw"><input type="checkbox" name="kill_check" <?= !empty($cl['killCheck']['enabled']) ? 'checked' : '' ?>><i></i></label></div>
          <div class="row"><div class="lbl">Durum adresi<small>Boş bırakırsan otomatik bulunur</small></div>
            <input type="text" name="kill_url" value="<?= h($cl['killCheck']['url'] ?? '') ?>"></div>
          <div class="row"><div class="lbl">Bot filtresi<small>Arama motoru, önizleme ve otomasyon botlarına popup gösterme ve sayma</small></div>
            <label class="sw"><input type="checkbox" name="bot_filter" <?= ($cl['botFilter'] ?? true) ? 'checked' : '' ?>><i></i></label></div>
        </div>
      </div>

      <div class="card">
        <h2><?= ico('chart') ?> İstatistik toplama</h2>
        <div class="rows">
          <div class="row"><div class="lbl">İstatistik topla<small>IP veya kişisel veri saklanmaz · 400 gün</small></div>
            <label class="sw"><input type="checkbox" name="stats_enabled" <?= $stc['enabled'] ? 'checked' : '' ?>><i></i></label></div>
          <div class="row"><div class="lbl">Sayaç adresi<small>Boş bırakırsan otomatik bulunur</small></div>
            <input type="text" name="stats_url" value="<?= h($stc['url']) ?>"></div>
        </div>
      </div>
    </div>

    <div class="pane" data-p="sayfalar" hidden>
      <div class="card">
        <h2><?= ico('route') ?> Hangi sayfalarda çıksın (global)</h2>
        <p class="hint">Sipariş ve ödeme sayfalarında popup açmak dönüşümü düşürür. Boş bırakırsan her sayfada çıkar.
           Popup başına ayrı kural da tanımlayabilirsin.</p>
        <div class="rows">
          <div class="row"><div class="lbl">Kural tipi</div>
            <select name="pages_mode">
              <option value="exclude" <?= $pg['mode'] === 'exclude' ? 'selected' : '' ?>>Aşağıdakiler HARİÇ her sayfada</option>
              <option value="include" <?= $pg['mode'] === 'include' ? 'selected' : '' ?>>SADECE aşağıdaki sayfalarda</option>
            </select></div>
          <div class="row"><div class="lbl">Adres listesi<small>Her satıra bir adres · <code>*</code> joker</small></div>
            <textarea name="pages_patterns" placeholder="/order*&#10;/account*"><?= h(implode("\n", (array) $pg['patterns'])) ?></textarea></div>
        </div>
      </div>
    </div>

    <div class="savebar" id="savebar">
      <button class="btn" type="submit"><?= ico('save') ?><span>Kaydet ve yayınla</span></button>
      <span class="note">Kaydettiğinde embed.js anında yenilenir.</span>
    </div>
    </form>

    <!-- ═════════ İSTATİSTİK ═════════ -->
    <div class="pane" data-p="istatistik" hidden>
      <div class="chips">
        <?php foreach (st_ranges() as $rk => $rv): ?>
          <a href="?tab=istatistik&range=<?= $rk ?>" class="chip <?= (string) $rk === $range ? 'on' : '' ?>"><?= h($rv) ?></a>
        <?php endforeach; ?>
      </div>

      <div class="tiles">
        <div class="tile"><div class="t-lbl">Gösterim</div><div class="t-val"><?= number_format($stSumV, 0, ',', '.') ?></div>
          <div class="t-sub"><?= h(date('d.m.Y', strtotime($stFrom))) ?> — <?= h(date('d.m.Y', strtotime($stTo))) ?></div></div>
        <div class="tile"><div class="t-lbl">Tıklama</div><div class="t-val"><?= number_format($stSumC, 0, ',', '.') ?></div>
          <div class="t-sub">oran %<?= st_ctr($stSumV, $stSumC) ?></div></div>
        <div class="tile"><div class="t-lbl">Tekil ziyaretçi</div><div class="t-val"><?= number_format($stEng['uniq'], 0, ',', '.') ?></div>
          <div class="t-sub">günlük tekil toplamı</div></div>
        <div class="tile"><div class="t-lbl">Ort. açık kalma</div><div class="t-val"><?= $stEng['avgSec'] ?>sn</div>
          <div class="t-sub">%<?= $stEng['quickPct'] ?> hemen kapattı</div></div>
      </div>

      <div class="card">
        <h2><?= ico('chart') ?> Gösterim grafiği</h2>
        <p class="hint"><?= count($stDaily) > 130 ? 'Aylık toplamlar.' : (count($stDaily) > 31 ? 'Haftalık toplamlar.' : 'Günlük toplamlar.') ?>
           Sütunun üzerine gelince tam değer görünür.</p>
        <div class="body">
          <?php if (!$stBuckets): ?>
            <p class="hint" style="margin:0">Bu aralıkta veri yok.</p>
          <?php else: ?>
            <div class="chart">
              <?php foreach ($stBuckets as $b): $hh = round($b['view'] / $stBest * 100); ?>
                <div class="bar" style="height:<?= max(3, $hh) ?>%" title="<?= h($b['full']) ?> — <?= (int) $b['view'] ?> gösterim, <?= (int) $b['click'] ?> tıklama">
                  <?php if ($b['view'] > 0 && count($stBuckets) <= 20): ?><span><?= (int) $b['view'] ?></span><?php endif; ?>
                </div>
              <?php endforeach; ?>
            </div>
            <div class="chart-x"><?php foreach ($stBuckets as $b): ?><div><?= h($b['label']) ?></div><?php endforeach; ?></div>
          <?php endif; ?>
        </div>
      </div>

      <?php if ($stPopups): ?>
      <div class="card">
        <h2><?= ico('layers') ?> Popup bazında</h2>
        <div class="body tbl-wrap">
          <table><tr><th>Popup</th><th class="num">Gösterim</th><th class="num">Tıklama</th><th class="num">Oran</th></tr>
            <?php foreach ($stPopups as $pid => $ps): ?>
              <tr><td><?= h($popupNames[$pid] ?? $pid) ?> <span class="dim">(<?= h($pid) ?>)</span></td>
                  <td class="num"><b><?= number_format($ps['view'], 0, ',', '.') ?></b></td>
                  <td class="num"><?= number_format($ps['click'], 0, ',', '.') ?></td>
                  <td class="num">%<?= st_ctr($ps['view'], $ps['click']) ?></td></tr>
            <?php endforeach; ?>
          </table>
        </div>
      </div>
      <?php endif; ?>

      <div class="grid2">
        <?php if ($stDev): ?>
        <div class="card">
          <h2><?= ico('phone') ?> Cihaz</h2>
          <div class="body tbl-wrap">
            <table><tr><th>Cihaz</th><th class="num">Gösterim</th><th class="num">Oran</th></tr>
              <?php foreach ($stDev as $k => $v): ?>
                <tr><td><?= h(st_device_name((string) $k)) ?></td>
                    <td class="num"><b><?= number_format($v['view'], 0, ',', '.') ?></b></td>
                    <td class="num">%<?= st_ctr($v['view'], $v['click']) ?></td></tr>
              <?php endforeach; ?>
            </table>
          </div>
        </div>
        <?php endif; ?>

        <?php if ($stSrc): ?>
        <div class="card">
          <h2><?= ico('route') ?> Trafik kaynağı</h2>
          <div class="body tbl-wrap">
            <table><tr><th>Kaynak</th><th class="num">Gösterim</th><th class="num">Oran</th></tr>
              <?php foreach (array_slice($stSrc, 0, 12, true) as $k => $v): ?>
                <tr><td><?= h($k) ?></td>
                    <td class="num"><b><?= number_format($v['view'], 0, ',', '.') ?></b></td>
                    <td class="num">%<?= st_ctr($v['view'], $v['click']) ?></td></tr>
              <?php endforeach; ?>
            </table>
          </div>
        </div>
        <?php endif; ?>
      </div>

      <?php if ($stPg): ?>
      <div class="card">
        <h2><?= ico('folder') ?> Sayfa bazında</h2>
        <p class="hint">Popup hangi sayfalarda daha iyi çalışıyor.</p>
        <div class="body tbl-wrap">
          <table><tr><th>Sayfa</th><th class="num">Gösterim</th><th class="num">Tıklama</th><th class="num">Oran</th></tr>
            <?php foreach (array_slice($stPg, 0, 15, true) as $k => $v): ?>
              <tr><td><code><?= h($k) ?></code></td>
                  <td class="num"><b><?= number_format($v['view'], 0, ',', '.') ?></b></td>
                  <td class="num"><?= number_format($v['click'], 0, ',', '.') ?></td>
                  <td class="num">%<?= st_ctr($v['view'], $v['click']) ?></td></tr>
            <?php endforeach; ?>
          </table>
        </div>
      </div>
      <?php endif; ?>

      <div class="card">
        <h2><?= ico('folder') ?> Sürüm geçmişi</h2>
        <p class="hint">Popup içeriği her değiştiğinde yeni sürüm oluşur. Satıra tıklayınca servisleri açılır.</p>
        <div class="body">
          <?php if (!$stVersions): ?>
            <p class="hint" style="margin:0">Bu aralıkta sürüm kaydı yok.</p>
          <?php else: foreach ($stVersions as $vid => $vs):
                $meta = $vhist[$vid] ?? null;
                $vname = $meta['title'] ?? ($vid === 'bilinmiyor' ? 'Sürüm bilgisi olmayan kayıtlar' : $vid);
                $isNow = ($vid === $stCurrent); ?>
            <details class="ver">
              <summary>
                <span class="ver-name"><?= h($vname) ?>
                  <?php if ($isNow): ?><em class="tag">yayında</em><?php endif; ?>
                  <small><?= h(date('d.m.Y', strtotime($vs['first']))) ?> — <?= h(date('d.m.Y', strtotime($vs['last']))) ?> · <?= (int) $vs['days'] ?> gün</small>
                </span>
                <span class="ver-num"><b><?= number_format($vs['view'], 0, ',', '.') ?></b><small>gösterim</small></span>
                <span class="ver-num"><b><?= number_format($vs['click'], 0, ',', '.') ?></b><small>tıklama</small></span>
                <span class="ver-num"><b>%<?= st_ctr($vs['view'], $vs['click']) ?></b><small>oran</small></span>
              </summary>
              <div class="ver-body">
                <?php if ($meta && !empty($meta['services'])): ?>
                  <div class="tbl-wrap">
                    <table><tr><th>ID</th><th>Servis</th><th class="num">Fiyat</th><th class="num">Tıklama</th></tr>
                    <?php foreach ($meta['services'] as $sv): $sid = (string) $sv['id']; ?>
                      <tr><td><?= h($sid) ?></td><td><?= h($sv['name']) ?></td>
                          <td class="num"><?= h($sv['price']) ?></td>
                          <td class="num"><b><?= (int) ($vs['svc'][$sid] ?? 0) ?></b></td></tr>
                    <?php endforeach; ?></table>
                  </div>
                <?php else: ?>
                  <p class="hint" style="margin:0">Bu sürümün içerik kaydı yok.</p>
                <?php endif; ?>
              </div>
            </details>
          <?php endforeach; endif; ?>
        </div>
      </div>

      <?php if ($stServices): ?>
      <div class="card">
        <h2><?= ico('text') ?> En çok tıklanan servisler</h2>
        <div class="body tbl-wrap">
          <table><tr><th>ID</th><th>Servis</th><th class="num">Tıklama</th></tr>
            <?php foreach (array_slice($stServices, 0, 15, true) as $sid => $n): $nm = st_service_name($vhist, (string) $sid); ?>
              <tr><td><?= h($sid) ?></td><td><?= $nm ? h($nm) : '<span class="dim">—</span>' ?></td>
                  <td class="num"><b><?= (int) $n ?></b></td></tr>
            <?php endforeach; ?>
          </table>
        </div>
      </div>
      <?php endif; ?>
    </div>

    <!-- ═════════ YEDEK & GÜNLÜK ═════════ -->
    <div class="pane" data-p="bakim" hidden>
      <div class="card">
        <h2><?= ico('shield') ?> Ayar yedekleri</h2>
        <p class="hint">Her kayıttan <b>önce</b> otomatik yedek alınır (son 20 tutulur).
           Yanlış bir değişiklik yaptıysan tek tıkla geri dönebilirsin.</p>
        <div class="body">
          <?php if (!$backups): ?>
            <p class="hint" style="margin:0">Henüz yedek yok — ilk kayıttan sonra burada görünecek.</p>
          <?php else: ?>
            <div class="tbl-wrap">
              <table><tr><th>Tarih</th><th>Açıklama</th><th class="num">İçerik</th><th class="num"></th></tr>
                <?php foreach ($backups as $b): ?>
                  <tr>
                    <td><?= h(date('d.m.Y H:i', $b['ts'])) ?></td>
                    <td><?= $b['note'] ? h($b['note']) : '<span class="dim">—</span>' ?></td>
                    <td class="num"><?= (int) $b['autos'] ?> oto · <?= (int) $b['popups'] ?> manuel</td>
                    <td class="num">
                      <form method="post" style="display:inline" onsubmit="return confirm('Bu yedek geri yüklensin mi? Mevcut ayarların önce yedeklenir.')">
                        <input type="hidden" name="_t" value="<?= $tok ?>"><input type="hidden" name="action" value="restore">
                        <input type="hidden" name="ts" value="<?= (int) $b['ts'] ?>">
                        <button class="btn sec sm" type="submit"><?= ico('refresh') ?><span>Geri al</span></button>
                      </form>
                    </td>
                  </tr>
                <?php endforeach; ?>
              </table>
            </div>
          <?php endif; ?>
        </div>
      </div>

      <div class="card">
        <h2><?= ico('clock') ?> Değişiklik günlüğü</h2>
        <p class="hint">Panelde yapılan son işlemler (son 40).</p>
        <div class="body">
          <?php if (!$audit): ?>
            <p class="hint" style="margin:0">Henüz kayıt yok.</p>
          <?php else: ?>
            <div class="tbl-wrap">
              <table><tr><th>Tarih</th><th>İşlem</th><th>Ayrıntı</th><th>IP</th></tr>
                <?php foreach ($audit as $a): ?>
                  <tr><td><?= h(date('d.m H:i', (int) ($a['ts'] ?? 0))) ?></td>
                      <td><code><?= h($a['action'] ?? '') ?></code></td>
                      <td><?= h($a['detail'] ?? '') ?></td>
                      <td class="dim"><?= h($a['ip'] ?? '') ?></td></tr>
                <?php endforeach; ?>
              </table>
            </div>
          <?php endif; ?>
        </div>
      </div>
    </div>

    <!-- ═════════ ŞİFRE ═════════ -->
    <div class="pane" data-p="sifre" hidden>
      <?php if (!empty($newHash)): ?>
      <div class="card">
        <h2><?= ico('ok') ?> Yeni hash hazır</h2>
        <p class="hint">Bu satırı <code>popup-config.php</code> içindeki eski <code>admin_pass</code> satırının yerine yapıştır.</p>
        <div class="body"><textarea readonly onclick="this.select()" style="min-height:76px">    'admin_pass' => '<?= h($newHash) ?>',</textarea></div>
      </div>
      <?php endif; ?>
      <div class="card">
        <h2><?= ico('lock') ?> Şifre değiştir</h2>
        <p class="hint">Şifre sunucuda düz metin tutulmaz. Yeni şifreyi yaz, üretilen satırı ayar dosyasına yapıştır.</p>
        <div class="body">
          <form method="post">
            <input type="hidden" name="_t" value="<?= $tok ?>">
            <input type="hidden" name="action" value="newpass">
            <input type="password" name="pass" placeholder="Yeni şifre (en az 8 karakter)" style="max-width:320px">
            <div style="margin-top:12px"><button class="btn sec" type="submit"><?= ico('lock') ?><span>Hash üret</span></button></div>
          </form>
        </div>
      </div>
    </div>

    </div>

    <!-- ═════════ ÖNİZLEME ═════════ -->
    <div>
      <div class="card preview-card">
        <h2><?= ico('eye') ?> Canlı önizleme</h2>
        <p class="hint">Kayıtlı ayarlarla. Zamanlama, tetik ve kurallar burada uygulanmaz.</p>
        <?php
          $pvopts = ['' => 'İlk sıradaki popup'];
          foreach ($cfg['autos']  as $pp) { $pvopts[$pp['id']] = ($pp['name'] ?? $pp['id']) . ' (oto)'; }
          foreach ($cfg['popups'] as $pp) { $pvopts[$pp['id']] = $pp['name'] ?? $pp['id']; }
        ?>
        <div class="body">
          <select id="pvsel" style="margin-bottom:10px">
            <?php foreach ($pvopts as $k => $v): ?><option value="<?= h($k) ?>"><?= h($v) ?></option><?php endforeach; ?>
          </select>
          <iframe src="preview.php?v=<?= time() ?>" id="pv" title="Önizleme"></iframe>
        </div>
        <div style="margin-top:12px"><button class="btn sec sm" type="button" id="pvrefresh"><?= ico('refresh') ?><span>Yenile</span></button></div>
      </div>
    </div>

    </div><!-- /split -->
    </div><!-- /inner -->
  </div><!-- /main -->
</div><!-- /app -->

<!-- ═════════════ MODAL: OTOMATİK POPUP ═════════════ -->
<div class="mdl" id="m-auto" hidden>
  <div class="mdl-scrim" data-close></div>
  <div class="mdl-box" role="dialog" aria-modal="true" aria-labelledby="m-auto-t">
    <form method="post">
      <input type="hidden" name="_t" value="<?= $tok ?>">
      <input type="hidden" name="action" value="auto_save">
      <input type="hidden" name="pid" value="">
      <div class="mdl-head">
        <h3 id="m-auto-t" class="mdl-title">Otomatik popup</h3>
        <button type="button" class="mdl-x" data-close aria-label="Kapat"><?= ico('x') ?></button>
      </div>
      <div class="mdl-tabs">
        <button type="button" class="on" data-s="genel">Genel</button>
        <button type="button" data-s="servis">Servis & fiyat</button>
        <button type="button" data-s="zaman">Zamanlama</button>
        <button type="button" data-s="hedef">Hedefleme</button>
        <button type="button" data-s="segment">Segment</button>
        <button type="button" data-s="kural">Kurallar</button>
      </div>
      <div class="mdl-body">
        <div data-sec="genel"><div class="rows"><?php f_common_head($themes, true); ?></div></div>

        <div data-sec="servis" hidden><div class="rows">
          <div class="row"><div class="lbl">Kategori seçimi</div>
            <select name="category_mode">
              <option value="first">API'nin ilk kategorisi</option>
              <option value="newest">En yüksek ID'li servisin kategorisi</option>
              <option value="manual">Elle sabitle</option>
            </select></div>
          <div class="row"><div class="lbl">Sabit kategori adı<small>"Elle sabitle" seçiliyse · kısmi ad da yeter</small></div>
            <input type="text" name="category_manual" placeholder="TikTok Bəyəni"></div>
          <div class="row"><div class="lbl">Atlanacak kategoriler<small>Her satıra bir kelime</small></div>
            <textarea name="skip_categories" placeholder="test&#10;deneme"></textarea></div>
          <div class="row"><div class="lbl">Kaç servis gösterilsin</div><input type="number" name="count" min="1" max="6"></div>
          <div class="row"><div class="lbl">Sıralama</div>
            <select name="sort"><option value="cheapest">Ucuzdan pahalıya</option><option value="default">Paneldeki sıra</option></select></div>
          <div class="row"><div class="lbl">Fiyatı 0 olanları atla</div>
            <label class="sw"><input type="checkbox" name="skip_zero"><i></i></label></div>
          <div class="row"><div class="lbl">Para birimi</div><input type="text" name="currency" maxlength="6" style="max-width:120px"></div>
          <div class="row"><div class="lbl">Simge rakamın önünde<small>Kapalıysa: 0.38 ₼</small></div>
            <label class="sw"><input type="checkbox" name="currency_before"><i></i></label></div>
          <div class="row"><div class="lbl">Ondalık basamak</div>
            <select name="decimals"><option value="auto">Otomatik</option><option value="2">2</option><option value="3">3</option><option value="4">4</option></select></div>
          <div class="row"><div class="lbl">Fiyat çarpanı<small>1 = dokunma · 1.10 = %10 ekle</small></div>
            <input type="text" name="price_multiplier" style="max-width:140px"></div>
          <div class="row"><div class="lbl">Sipariş linki<small><code>{id}</code> servis ID'si ile değişir</small></div>
            <input type="text" name="order_url"></div>
          <div class="row"><div class="lbl">Rozet metni<small><code>{category}</code> = kategori adı</small></div>
            <input type="text" name="badge_template"></div>
          <div class="row"><div class="lbl">Alt açıklama<small><code>{category}</code> kullanabilirsin</small></div>
            <input type="text" name="subtitle"></div>
          <div class="row"><div class="lbl">Emojileri temizle<small>Kapalıysa kategori adındaki emojiler rozet ve başlıkta aynen görünür</small></div>
            <label class="sw"><input type="checkbox" name="strip_emoji"><i></i></label></div>
        </div></div>

        <div data-sec="zaman" hidden><div class="rows"><?php f_schedule(); f_trigger(); ?></div></div>
        <div data-sec="hedef"  hidden><div class="rows"><?php f_targeting(); ?></div></div>
        <div data-sec="segment" hidden><?php f_segment(); ?></div>
        <div data-sec="kural"  hidden><div class="rows"><?php f_overrides(); ?></div></div>
      </div>
      <div class="mdl-foot">
        <button class="btn" type="submit"><?= ico('save') ?><span>Kaydet</span></button>
        <button class="btn sec" type="button" data-close>İptal</button>
      </div>
    </form>
  </div>
</div>

<!-- ═════════════ MODAL: MANUEL POPUP ═════════════ -->
<div class="mdl" id="m-manual" hidden>
  <div class="mdl-scrim" data-close></div>
  <div class="mdl-box" role="dialog" aria-modal="true" aria-labelledby="m-man-t">
    <form method="post">
      <input type="hidden" name="_t" value="<?= $tok ?>">
      <input type="hidden" name="action" value="popup_save">
      <input type="hidden" name="pid" value="">
      <div class="mdl-head">
        <h3 id="m-man-t" class="mdl-title">Manuel popup</h3>
        <button type="button" class="mdl-x" data-close aria-label="Kapat"><?= ico('x') ?></button>
      </div>
      <div class="mdl-tabs">
        <button type="button" class="on" data-s="genel">Genel</button>
        <button type="button" data-s="icerik">İçerik</button>
        <button type="button" data-s="stil">Stil</button>
        <button type="button" data-s="zaman">Zamanlama</button>
        <button type="button" data-s="hedef">Hedefleme</button>
        <button type="button" data-s="segment">Segment</button>
        <button type="button" data-s="kural">Kurallar</button>
      </div>
      <div class="mdl-body">
        <div data-sec="genel"><div class="rows"><?php f_common_head($themes, false); ?></div></div>

        <div data-sec="icerik" hidden><div class="rows">
          <div class="row"><div class="lbl">Rozet<small>Üstteki küçük etiket</small></div>
            <input type="text" name="badge" placeholder="TELEGRAM"></div>
          <div class="row"><div class="lbl">İkon<small>Emoji, opsiyonel</small></div><input type="text" name="icon" placeholder="🔥"></div>
          <div class="row"><div class="lbl">Başlık<small>Kişiye özel: <code>{ad}</code> <code>{bakiye}</code> <code>{siparis}</code> · yedek: <code>{ad|Dostum}</code></small></div><input type="text" name="title" placeholder="Salam {ad|dostum}, kampaniya sənin üçündür"></div>
          <div class="row"><div class="lbl">Açıklama</div><input type="text" name="subtitle" placeholder="Kampanyalardan ilk sen haberdar ol."></div>
          <div class="row"><div class="lbl">Liste satırları<small>Her satıra: <code>Ad | Fiyat</code></small></div>
            <textarea name="items" placeholder="Kart2Kart | 0% komisyon&#10;Birbank | Dərhal"></textarea></div>
          <div class="row"><div class="lbl">Görsel URL<small>Opsiyonel</small></div><input type="text" name="image" placeholder="https://…/afis.jpg"></div>
          <div class="row"><div class="lbl">Buton yazısı</div><input type="text" name="btn_text" placeholder="Telegram'a Katıl"></div>
          <div class="row"><div class="lbl">Buton linki</div><input type="text" name="btn_url" placeholder="https://t.me/kanaladi"></div>
          <div class="row"><div class="lbl">Geri sayım bitişi<small>Canlı sayaç gösterilir; bu ana gelince popup açılmaz</small></div>
            <input type="datetime-local" name="countdown"></div>
          <div class="row"><div class="lbl">Kupon kodu<small>Tek tıkla kopyalanır</small></div>
            <input type="text" name="coupon" placeholder="INDIRIM20"></div>
          <div class="row"><div class="lbl">Kupon etiketi</div><input type="text" name="coupon_label" placeholder="Bu kodu kullan:"></div>
          <div class="row"><div class="lbl">Kupon limiti (sunucuda)<small>Açıkken kod sayfa kaynağında görünmez, hak kazanınca sunucudan gelir. Çark, kazıma kartı ve kuponlu popuplarda geçerli.</small></div>
            <label class="sw"><input type="checkbox" name="limit_on" data-toggle="limit"><i></i></label></div>
          <div data-box="limit" hidden>
            <div class="row"><div class="lbl">Toplam kullanım<small>Bu kupon en çok kaç kişiye verilsin · 0 = sınırsız. Çarkta limiti her satıra yaz: <code>Etiket | KOD | stok</code></small></div>
              <input type="number" name="coupon_total" min="0" max="1000000" placeholder="0"></div>
            <div class="row"><div class="lbl">Kişi başı 1 kez<small>Aynı kişi (tarayıcı ya da IP) ikinci kez alamaz, önceki sonucu görür</small></div>
              <label class="sw"><input type="checkbox" name="coupon_once"><i></i></label></div>
          </div>
        </div></div>

        <div data-sec="stil" hidden>
          <div class="livepv">
            <div class="livepv-bar"><b>Canlı önizleme</b><span class="hint" style="margin:0">Kaydetmeden, yaptığın her değişikliği gösterir</span>
              <span class="livepv-sw"><button type="button" class="btn sec sm on" data-pvw="100%">Masaüstü</button><button type="button" class="btn sec sm" data-pvw="375px">Telefon</button></span></div>
            <div class="livepv-box"><iframe id="livepv" title="Canlı önizleme" sandbox="allow-scripts"></iframe></div>
          </div>
          <div class="rows">
          <div class="row"><div class="lbl">Yerleşim<small>Popup'ın ekrandaki biçimi</small></div>
            <select name="layout" data-lay-sel><option value="">Klasik pencere (ortada)</option>
              <?php foreach (popup_layouts() as $lk => $ln): ?><option value="<?= h($lk) ?>"><?= h($ln) ?></option><?php endforeach; ?></select></div>
          <div class="row"><div class="lbl">Renk şablonu<small>Hazır renk ve köşe düzeni</small></div>
            <select name="skin" data-skin-sel><option value="">Klasik (koyu, çerçeveli)</option>
              <?php foreach (popup_skins() as $sk => $v): ?><option value="<?= h($sk) ?>"><?= h($v['n']) ?></option><?php endforeach; ?></select></div>
          <div class="row" data-lay="wheel"><div class="lbl">Çark ödülleri<small>Her satıra: <code>Etiket | KUPON | stok</code> (2–8 satır). Kuponsuz satır = boş dilim, stok boş/0 = sınırsız. Stok için İçerik'te "Kupon limiti"ni aç</small></div>
            <textarea name="prizes" placeholder="5% | CARX5&#10;10% | CARX10&#10;Heç nə |"></textarea></div>
          <div class="row" data-lay="steps"><div class="lbl">Adımlar<small>Her satıra: <code>Soru :: Seçenek1, Seçenek2</code> (en çok 4 adım). Son ekranda İçerik sekmesindeki metin ve buton gelir.</small></div>
            <textarea name="steps" placeholder="Hansı platforma lazımdır? :: Instagram, TikTok, YouTube"></textarea></div>
          <div class="row"><div class="lbl">Özel tasarım<small>Kapalıysa seçili şablonun hazır ayarları kullanılır. Açınca aşağıdakilerin hepsini istediğin gibi değiştirebilirsin.</small></div>
            <label class="sw"><input type="checkbox" name="st_on" data-toggle="stil"><i></i></label></div>
          <div data-box="stil" hidden>
          <div class="row"><div class="lbl">Arka plan<small>Renk ya da gradyan</small></div>
            <div class="colr"><input type="color" data-cp aria-label="Arka plan"><input type="text" name="st_bg" placeholder="Şablon rengi"></div></div>
          <div class="row"><div class="lbl">Yazı rengi</div>
            <div class="colr"><input type="color" data-cp aria-label="Yazı rengi"><input type="text" name="st_fg" placeholder="Şablon rengi"></div></div>
          <div class="row"><div class="lbl">Buton rengi<small>Renk ya da gradyan</small></div>
            <div class="colr"><input type="color" data-cp aria-label="Buton rengi"><input type="text" name="st_btn" placeholder="Şablon rengi"></div></div>
          <div class="row"><div class="lbl">Buton yazısı</div>
            <div class="colr"><input type="color" data-cp aria-label="Buton yazısı"><input type="text" name="st_btnf" placeholder="Şablon rengi"></div></div>
          <div class="row"><div class="lbl">Rozet arka planı</div>
            <div class="colr"><input type="color" data-cp aria-label="Rozet arka planı"><input type="text" name="st_bb" placeholder="Şablon rengi"></div></div>
          <div class="row"><div class="lbl">Rozet yazısı</div>
            <div class="colr"><input type="color" data-cp aria-label="Rozet yazısı"><input type="text" name="st_bf" placeholder="Şablon rengi"></div></div>
            <div class="row"><div class="lbl">Köşe yuvarlaklığı<small>px · 0–60</small></div><input type="number" name="st_r" min="0" max="60" placeholder="20"></div>
            <div class="grp">Ölçüler</div>
            <div class="row"><div class="lbl">Genişlik<small>px · 200–1000</small></div><input type="number" name="st_w" min="200" max="1000" placeholder="380"></div>
            <div class="row"><div class="lbl">İç boşluk<small>px · 0–80</small></div><input type="number" name="st_pad" min="0" max="80" placeholder="28"></div>
            <div class="row"><div class="lbl">Başlık boyutu<small>px</small></div><input type="number" name="st_tsz" min="10" max="64" placeholder="23"></div>
            <div class="row"><div class="lbl">Açıklama boyutu<small>px</small></div><input type="number" name="st_ssz" min="9" max="32" placeholder="14"></div>
            <div class="row"><div class="lbl">Buton yazı boyutu<small>px</small></div><input type="number" name="st_bsz" min="10" max="28" placeholder="14"></div>
            <div class="row"><div class="lbl">Buton köşesi<small>px · 0–60</small></div><input type="number" name="st_br" min="0" max="60" placeholder="12"></div>
            <div class="grp">Kenarlık, gölge, hizalama</div>
            <div class="row"><div class="lbl">Kenarlık kalınlığı<small>px · 0–12</small></div><input type="number" name="st_bw" min="0" max="12" placeholder="0"></div>
            <div class="row"><div class="lbl">Kenarlık rengi</div><div class="colr"><input type="color" data-cp aria-label="Kenarlık rengi"><input type="text" name="st_bc" placeholder="Şablon rengi"></div></div>
            <div class="row"><div class="lbl">Gölge</div><select name="st_sh"><option value="">Şablondaki</option><option value="none">Yok</option><option value="soft">Hafif</option><option value="strong">Güçlü</option></select></div>
            <div class="row"><div class="lbl">Yazı hizası</div><select name="st_al"><option value="">Şablondaki</option><option value="left">Sol</option><option value="center">Orta</option><option value="right">Sağ</option></select></div>
            <div class="grp">Efekt</div>
            <div class="row"><div class="lbl">Arka plan karartma<small>% · 0–95 (şerit ve yüzen düğmede yok)</small></div><input type="number" name="st_ov" min="0" max="95" placeholder="62"></div>
            <div class="row"><div class="lbl">Açılış animasyonu</div><select name="st_anim"><option value="">Varsayılan</option><option value="fade">Solarak</option><option value="slide">Aşağıdan kayarak</option><option value="zoom">Büyüyerek</option><option value="bounce">Zıplayarak</option></select></div>
            <div class="grp">Gelişmiş</div>
            <div class="row"><div class="lbl">Özel CSS<small>İstersen kendi kodun. Sınıflar: <code>.igfp-box</code> <code>.igfp-h</code> <code>.igfp-sub</code> <code>.igfp-btn</code> <code>.igfp-badge</code> <code>.igfp-x</code></small></div>
              <textarea name="st_css" spellcheck="false" placeholder=".igfp-h{text-shadow:0 2px 8px #0008}"></textarea></div>
          </div>
        </div></div>

        <div data-sec="zaman" hidden><div class="rows"><?php f_schedule(); f_trigger(); ?></div></div>
        <div data-sec="hedef"  hidden><div class="rows"><?php f_targeting(); ?></div></div>
        <div data-sec="segment" hidden><?php f_segment(); ?></div>
        <div data-sec="kural"  hidden><div class="rows"><?php f_overrides(); ?></div></div>
      </div>
      <div class="mdl-foot">
        <button class="btn" type="submit"><?= ico('save') ?><span>Kaydet</span></button>
        <button class="btn sec" type="button" data-close>İptal</button>
      </div>
    </form>
  </div>
</div>

<script>
var PDATA = {
  autos:      <?= json_encode($formAutos,     JSON_UNESCAPED_UNICODE) ?>,
  popups:     <?= json_encode($formPopups,    JSON_UNESCAPED_UNICODE) ?>,
  defAuto:    <?= json_encode($formDefAuto,   JSON_UNESCAPED_UNICODE) ?>,
  defManual:  <?= json_encode($formDefManual, JSON_UNESCAPED_UNICODE) ?>,
  tpls:       <?= json_encode(array_map(function ($v) { return $v['t']; }, popup_skins()), JSON_UNESCAPED_UNICODE) ?>,
  skins:      <?= json_encode(array_map(function ($v) { return array_intersect_key($v, array_flip(['bg','fg','btn','btnf','bb','bf','r'])); }, popup_skins()), JSON_UNESCAPED_UNICODE) ?>,
  laytpls:    <?= json_encode(popup_layout_tpls(), JSON_UNESCAPED_UNICODE) ?>,
  mytpls:     <?= json_encode(array_map(function ($t) { return $t['data'] ?? []; }, $myTpls), JSON_UNESCAPED_UNICODE) ?>
};

(function () {
  /* ---------- sekmeler ---------- */
  var links = document.querySelectorAll('#nav a');
  var panes = document.querySelectorAll('.pane');
  var titles = <?= json_encode(array_map(function ($v) { return $v[1]; }, $nav), JSON_UNESCAPED_UNICODE) ?>;
  var SAVE_TABS = ['gorunum', 'sayfalar'];
  var savebar = document.getElementById('savebar');

  function go(name) {
    if (!titles[name]) name = 'durum';
    links.forEach(function (a) { a.classList.toggle('on', a.dataset.t === name); });
    panes.forEach(function (p) { p.hidden = (p.dataset.p !== name); });
    document.getElementById('ptitle').textContent = titles[name];
    document.body.classList.remove('nav-open');
    if (savebar) savebar.style.display = SAVE_TABS.indexOf(name) === -1 ? 'none' : '';
    try { localStorage.setItem('igfp_tab', name); } catch (e) {}
  }
  links.forEach(function (a) { a.addEventListener('click', function () { go(a.dataset.t); }); });
  var forced = <?= json_encode($forceTab ?: '') ?>;
  var saved = 'durum';
  try { saved = localStorage.getItem('igfp_tab') || 'durum'; } catch (e) {}
  go(forced || saved);

  /* ---------- form doldurucu ---------- */
  function fillForm(form, d) {
    Object.keys(d).forEach(function (k) {
      var v = d[k];
      var els = form.querySelectorAll('[name="' + k + '"], [name="' + k + '[]"]');
      if (!els.length) return;
      els.forEach(function (el) {
        if (el.type === 'checkbox') {
          if (el.name.slice(-2) === '[]') {
            el.checked = Array.isArray(v) && v.indexOf(el.value) !== -1;
          } else {
            el.checked = !!(v && v !== '0');
          }
        } else if (el.type === 'radio') {
          el.checked = (String(el.value) === String(v));
        } else {
          el.value = (v == null) ? '' : v;
        }
      });
    });
  }

  /* renk seçici <-> metin kutusu */
  function syncCp(cp) {
    var t = cp.parentNode.querySelector('input[type=text]');
    if (t && /^#[0-9a-f]{6}$/i.test(t.value.trim())) cp.value = t.value.trim();
  }
  var STK = ['bg', 'fg', 'btn', 'btnf', 'bb', 'bf', 'r'];
  function fillStyle(form, force) {
    var sel = form.querySelector('[data-skin-sel]'), v = PDATA.skins[sel && sel.value ? sel.value : 'sk01'] || {};
    STK.forEach(function (k) {
      var el = form.querySelector('[name="st_' + k + '"]'); if (!el) return;
      if (force || !el.value) el.value = k === 'r' ? parseInt(v.r, 10) || '' : (v[k] || '');
    });
    form.querySelectorAll('[data-cp]').forEach(syncCp);
  }

  /* koşullu kutular (kaydırma yüzdesi / kural / sayfa) */
  function syncConditionals(form) {
    var trig = form.querySelector('select[data-trig]');
    var trow = form.querySelector('[data-trigrow]');
    if (trig && trow) trow.hidden = (trig.value !== 'scroll');
    var ls = form.querySelector('[data-lay-sel]');
    if (ls) form.querySelectorAll('[data-lay]').forEach(function (r) { r.hidden = (r.dataset.lay !== ls.value); });
    form.querySelectorAll('[data-cp]').forEach(function (cp) { syncCp(cp); });
    ['rules', 'pages', 'stil', 'limit'].forEach(function (nm) {
      var cb = form.querySelector('[data-toggle="' + nm + '"]');
      var bx = form.querySelector('[data-box="' + nm + '"]');
      if (cb && bx) bx.hidden = !cb.checked;
    });
  }

  /* ---------- modal ---------- */
  var openMdl = null, lastFocus = null;

  function showSec(mdl, name) {
    mdl.querySelectorAll('.mdl-tabs button').forEach(function (b) { b.classList.toggle('on', b.dataset.s === name); });
    mdl.querySelectorAll('[data-sec]').forEach(function (s) { s.hidden = (s.dataset.sec !== name); });
    var body = mdl.querySelector('.mdl-body'); if (body) body.scrollTop = 0;
  }

  function openModal(kind, id, over) {
    var mdl = document.getElementById(kind === 'auto' ? 'm-auto' : 'm-manual');
    var data = id
      ? (kind === 'auto' ? PDATA.autos[id] : PDATA.popups[id])
      : (kind === 'auto' ? PDATA.defAuto : PDATA.defManual);
    if (!data) return;
    if (over) { data = Object.assign({}, data, over); }
    var form = mdl.querySelector('form');
    form.reset();
    fillForm(form, data);
    if (!id) form.querySelector('input[name="pid"]').value = '';
    mdl.querySelector('.mdl-title').textContent = id
      ? ((data.name || 'Düzenle'))
      : (kind === 'auto' ? 'Yeni otomatik popup' : 'Yeni manuel popup');
    showSec(mdl, 'genel');
    syncConditionals(form);
    lastFocus = document.activeElement;
    mdl.hidden = false;
    document.body.classList.add('mdl-open');
    openMdl = mdl;
    setTimeout(function () { var f = form.querySelector('input[name="name"]'); if (f) f.focus(); }, 40);
  }

  function closeModal() {
    if (!openMdl) return;
    openMdl.hidden = true;
    document.body.classList.remove('mdl-open');
    openMdl = null;
    if (lastFocus && lastFocus.focus) lastFocus.focus();
  }

  document.addEventListener('input', function (e) {
    var t = e.target; if (!t.closest || !t.closest('.colr')) return;
    if (t.matches('[data-cp]')) t.parentNode.querySelector('input[type=text]').value = t.value;
    else syncCp(t.parentNode.querySelector('[data-cp]'));
  });

  /* ---------- canlı önizleme (Stil sekmesi) ---------- */
  var pvT = null, pvFrame = document.getElementById('livepv');
  function livePreview() {
    var mdl = document.getElementById('m-manual');
    if (!pvFrame || !mdl || mdl.hidden) return;
    var sec = mdl.querySelector('[data-sec="stil"]'); if (!sec || sec.hidden) return;
    var fd = new FormData(mdl.querySelector('form'));
    fetch('preview-live.php', { method: 'POST', body: fd, credentials: 'same-origin' })
      .then(function (r) { return r.text(); })
      .then(function (h) { pvFrame.srcdoc = h; })
      .catch(function () {});
  }
  function livePreviewSoon() { clearTimeout(pvT); pvT = setTimeout(livePreview, 350); }
  var mm = document.getElementById('m-manual');
  if (mm) {
    mm.addEventListener('input', livePreviewSoon);
    mm.addEventListener('change', livePreviewSoon);
    mm.querySelectorAll('.mdl-tabs button').forEach(function (b) { b.addEventListener('click', function () { if (b.dataset.s === 'stil') livePreviewSoon(); }); });
    mm.querySelectorAll('[data-pvw]').forEach(function (b) {
      b.addEventListener('click', function () {
        mm.querySelectorAll('[data-pvw]').forEach(function (x) { x.classList.toggle('on', x === b); });
        pvFrame.style.width = b.dataset.pvw; pvFrame.style.margin = '0 auto';
      });
    });
  }
  document.querySelectorAll('[data-mytpl-use]').forEach(function (b) {
    b.addEventListener('click', function () {
      var d = PDATA.mytpls[b.dataset.mytplUse]; if (!d) return;
      var o = Object.assign({}, d); o.name = ''; openModal('manual', '', o);
    });
  });
  document.querySelectorAll('[data-mytpl-save]').forEach(function (b) {
    b.addEventListener('click', function () {
      var nm = prompt('Şablon adı:'); if (!nm || !nm.trim()) return;
      var f = document.createElement('form'); f.method = 'post';
      [['_t', <?= json_encode($tok) ?>], ['action', 'mytpl_save'], ['pid', b.dataset.mytplSave], ['tpl_name', nm.trim()]].forEach(function (kv) {
        var i = document.createElement('input'); i.type = 'hidden'; i.name = kv[0]; i.value = kv[1]; f.appendChild(i);
      });
      document.body.appendChild(f); f.submit();
    });
  });
  document.querySelectorAll('[data-lay-use]').forEach(function (b) {
    b.addEventListener('click', function () {
      var k = b.dataset.layUse, t = PDATA.laytpls[k]; if (!t) return;
      openModal('manual', '', {
        name: t.title || k, layout: k, skin: t.skin || '', icon: t.icon || '', badge: t.badge || '', title: t.title || '',
        subtitle: t.subtitle || '', btn_text: t.btn_text || '', btn_url: location.origin + '/', coupon: t.coupon || '',
        coupon_label: t.coupon ? 'Kupon kodu:' : '', items: t.items || '', prizes: t.prizes || '', steps: t.steps || ''
      });
    });
  });
  document.querySelectorAll('[data-tpl]').forEach(function (b) {
    b.addEventListener('click', function () {
      var t = PDATA.tpls[b.dataset.tpl]; if (!t) return;
      var cd = '';
      if (t.cd) { var d = new Date(Date.now() + 7 * 864e5), z = function (n) { return (n < 10 ? '0' : '') + n; };
        cd = d.getFullYear() + '-' + z(d.getMonth() + 1) + '-' + z(d.getDate()) + 'T' + z(d.getHours()) + ':' + z(d.getMinutes()); }
      openModal('manual', '', {
        name: t.name, skin: t.skin, icon: t.icon, badge: t.badge, title: t.title, subtitle: t.subtitle,
        btn_text: t.btn_text, btn_url: location.origin + '/', coupon: t.coupon, coupon_label: t.coupon ? 'Kupon kodu:' : '',
        items: t.items, countdown: cd
      });
    });
  });
  document.querySelectorAll('[data-edit]').forEach(function (b) {
    b.addEventListener('click', function () { openModal(b.dataset.edit, b.dataset.id || ''); });
  });
  document.querySelectorAll('.mdl [data-close]').forEach(function (b) {
    b.addEventListener('click', closeModal);
  });
  document.querySelectorAll('.mdl-tabs button').forEach(function (b) {
    b.addEventListener('click', function () { showSec(b.closest('.mdl'), b.dataset.s); });
  });
  document.addEventListener('change', function (e) {
    var t = e.target;
    if (!t.closest || !t.closest('.mdl')) return;
    if (t.matches('select[data-trig]') || t.matches('[data-toggle]') || t.matches('[data-lay-sel]')) syncConditionals(t.closest('form'));
    if (t.matches('[data-toggle="stil"]') && t.checked) fillStyle(t.closest('form'), false);
    if (t.matches('[data-skin-sel]')) { var fm = t.closest('form'), on = fm.querySelector('[data-toggle="stil"]'); if (on && on.checked) fillStyle(fm, true); }
  });
  document.addEventListener('keydown', function (e) {
    if (!openMdl) return;
    if (e.key === 'Escape') { closeModal(); return; }
    if (e.key === 'Tab') {                       // odak tuzağı
      var f = openMdl.querySelectorAll('a[href],button:not([disabled]),input:not([type=hidden]),select,textarea');
      if (!f.length) return;
      var first = f[0], last = f[f.length - 1];
      if (e.shiftKey && document.activeElement === first) { e.preventDefault(); last.focus(); }
      else if (!e.shiftKey && document.activeElement === last) { e.preventDefault(); first.focus(); }
    }
  });

  /* ---------- önizleme ---------- */
  var pv = document.getElementById('pv'), pvsel = document.getElementById('pvsel'), pvr = document.getElementById('pvrefresh');
  function pvload() { if (pv) pv.src = 'preview.php?pid=' + encodeURIComponent(pvsel ? pvsel.value : '') + '&v=' + Date.now(); }
  if (pvsel) pvsel.addEventListener('change', pvload);
  if (pvr) pvr.addEventListener('click', pvload);
})();
</script>
</body></html>
