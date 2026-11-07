const $ = (id) => document.getElementById(id);
const get = (d) => new Promise((r) => chrome.storage.local.get(d, r));
const today = () => new Date().toISOString().slice(0, 10);
let tabId = null;
let tgBase = '';

function conn(text, kind) { const c = $('conn'); c.textContent = text; c.className = 'pill ' + kind; }

async function init() {
  const s = await get({ panel: '', api: '', key: '', dry: false, skipAz: true, autoCat: true, catTop: true, notify: true, stats: { day: today(), today: 0, total: 0, learned: 0 } });
  $('dry').checked = !!s.dry;
  $('skipAz').checked = s.skipAz !== false;
  $('autoCat').checked = s.autoCat !== false;
  $('catTop').checked = s.catTop !== false;
  $('notify').checked = s.notify !== false;
  $('s-today').textContent = s.stats.day === today() ? s.stats.today : 0;
  $('s-total').textContent = s.stats.total;
  $('s-learned').textContent = s.stats.learned;
  if (s.api) {
    try { const u = new URL(s.api); tgBase = u.origin + u.pathname.replace(/api\.php$/, ''); $('tg').href = tgBase; $('tg').hidden = false; } catch (e) { /* geçersiz */ }
  }
  if (!s.panel || !s.api || !s.key) {
    conn('Ayar yok', 'bad');
    $('tab-msg').textContent = 'Önce bağlantı ayarlarını gir (aşağıda).';
    return;
  }
  balances(false);
  showUpd((await get({ upd: null })).upd);
  chrome.runtime.sendMessage({ type: 'api', action: 'ping' }, (r) => {
    if (r && r.ok && r.ext !== undefined) {
      const mine = chrome.runtime.getManifest().version;
      showUpd({ latest: r.ext, mine, has: !!r.ext && newerV(r.ext, mine) });
    }
    if (r && r.ok) conn('Bağlı · ' + r.version, 'ok');
    else { conn('Bağlantı yok', 'bad'); $('conn').title = (r && r.error) || ''; }
  });
  const [tab] = await chrome.tabs.query({ active: true, currentWindow: true });
  tabId = tab && tab.id;
  const onPanel = tab && tab.url && tab.url.startsWith(s.panel);
  if (!onPanel) {
    $('tab-msg').textContent = 'Bu sekme panel değil.';
    $('tab-open').hidden = false;
    $('open-panel').onclick = () => chrome.tabs.create({ url: s.panel + '/admin/services' });
    return;
  }
  refresh();
}

// ---- sağlayıcı bakiyeleri: 10 dakikadan eskiyse kendiliğinden yenilenir, "Yenile" ile hemen ----
const money = (v) => {
  const [i, d] = (v !== 0 && Math.abs(v) < 1 ? v.toFixed(4) : v.toFixed(2)).split('.');
  return i.replace(/\B(?=(\d{3})+(?!\d))/g, ' ') + '.' + d;
};
function ago(t) {
  const m = Math.round((Date.now() - t) / 60000);
  return m < 1 ? 'az önce' : m < 60 ? m + ' dk önce' : Math.round(m / 60) + ' sa önce';
}
function drawBal(b) {
  const list = $('bal-list');
  list.textContent = '';
  $('bal').hidden = false;
  if (!b.items.length) {
    $('bal-msg').textContent = 'tgwatch\'ta sağlayıcı yok (Ayarlar → Kaynaklar).';
    $('bal-tot').hidden = true;
    return;
  }
  for (const it of b.items) {
    const li = document.createElement('li');
    const n = document.createElement('span');
    n.className = 'n';
    n.textContent = it.name;
    const v = document.createElement('span');
    v.className = 'v';
    if (it.error) {
      li.className = 'err';
      v.textContent = '⚠ ' + it.error;
      v.title = it.error;
    } else {
      if (it.balance <= 0) li.className = 'zero';
      v.textContent = money(it.balance) + (it.currency ? ' ' + it.currency : '');
    }
    li.append(n, v);
    list.appendChild(li);
  }
  const tot = Object.entries(b.totals || {});
  const okN = b.items.filter((i) => !i.error).length;
  $('bal-tot').hidden = okN < 2;
  $('bal-tot').innerHTML = '';
  if (okN >= 2) {
    $('bal-tot').append('Toplam: ');
    tot.forEach(([c, v], i) => { const e = document.createElement('b'); e.textContent = money(v) + (c ? ' ' + c : ''); if (i) $('bal-tot').append(' · '); $('bal-tot').append(e); });
  }
  $('bal-msg').textContent = 'Son kontrol: ' + ago(b.at * 1000);
}
async function balances(force) {
  const { bal } = await get({ bal: null });
  if (bal && bal.items) drawBal(bal);
  if (!force && bal && Date.now() - bal.at * 1000 < 10 * 60000) return;
  $('bal').hidden = false;
  $('bal-ref').disabled = true;
  $('bal-msg').textContent = 'Bakiyeler soruluyor…';
  chrome.runtime.sendMessage({ type: 'api', action: 'balance' }, (r) => {
    $('bal-ref').disabled = false;
    if (chrome.runtime.lastError || !r || !r.ok) {
      $('bal-msg').textContent = (r && r.error) || 'tgwatch cevap vermedi';
      if (r && /Bilinmeyen işlem/.test(r.error || '')) $('bal-msg').textContent = 'tgwatch eski sürüm: sunucudaki src klasörünü güncelle.';
      return;
    }
    const b = { items: r.items, totals: r.totals, at: r.at };
    chrome.storage.local.set({ bal: b });
    drawBal(b);
  });
}
$('bal-ref').onclick = () => balances(true);

// ---- yeni sürüm ----
function newerV(a, b) {
  const x = String(a || '').split('.').map(Number);
  const y = String(b || '').split('.').map(Number);
  for (let i = 0; i < Math.max(x.length, y.length); i++) if ((x[i] || 0) !== (y[i] || 0)) return (x[i] || 0) > (y[i] || 0);
  return false;
}
function showUpd(u) {
  const mine = chrome.runtime.getManifest().version;
  if (!u || !u.has || !newerV(u.latest, mine)) { $('upd').hidden = true; return; }
  $('upd').hidden = false;
  $('upd-msg').textContent = 'tgwatch\'ta ' + u.latest + ' var, sende ' + mine + '.';
}
$('upd-dl').onclick = () => {
  if (!tgBase) { $('upd-msg').textContent = 'tgwatch adresi bilinmiyor: Bağlantı ayarlarını kontrol et.'; return; }
  chrome.tabs.create({ url: tgBase + '?p=ext_zip' });
};
$('upd-reload').onclick = () => chrome.runtime.sendMessage({ type: 'reload' }, () => { void chrome.runtime.lastError; window.close(); });

function refresh() {
  if (!tabId) return;
  chrome.tabs.sendMessage(tabId, { type: 'tgw-status' }, (r) => {
    if (chrome.runtime.lastError || !r) {
      $('tab-msg').textContent = 'Eklenti bu sayfada henüz çalışmıyor: sayfayı yenile.';
      return;
    }
    if (!r.screen) {
      $('tab-counts').hidden = true;
      $('undo').hidden = true;
      if (r.ctx === 'category' || r.ctx === 'list') {
        $('tab-msg').textContent = r.ctx === 'category' ? 'Edit category penceresi açık.' : 'Services listesi: en sondaki aktif kategori çevrilir, kaydedilir, en üste taşınır.';
        $('run').textContent = r.ctx === 'category' ? 'Bu kategorinin adını çevir' : 'Son kategorinin adını çevir';
        $('tab-actions').hidden = false;
        $('run').disabled = r.running;
      } else {
        $('tab-msg').textContent = 'Services → Import services → servisleri seç → inceleme ekranına geç.';
        $('tab-actions').hidden = true;
      }
      return;
    }
    $('run').textContent = 'Servisleri çevir';
    $('tab-msg').textContent = r.running ? 'Çeviriyor…' : r.rows + ' servis seçili.';
    $('tab-counts').hidden = !(r.counts.ok + r.counts.warn + r.counts.bad + r.counts.done);
    $('c-ok').textContent = r.counts.ok + r.counts.done;
    $('c-warn').textContent = r.counts.warn;
    $('c-bad').textContent = r.counts.bad;
    $('tab-actions').hidden = false;
    $('run').disabled = r.running;
    $('undo').hidden = !r.changed || r.running;
  });
}

$('run').onclick = () => chrome.tabs.sendMessage(tabId, { type: 'tgw-run' }, () => { void chrome.runtime.lastError; window.close(); });
$('undo').onclick = () => chrome.tabs.sendMessage(tabId, { type: 'tgw-undo' }, () => { void chrome.runtime.lastError; window.close(); });
$('dry').onchange = (e) => chrome.storage.local.set({ dry: e.target.checked });
$('skipAz').onchange = (e) => chrome.storage.local.set({ skipAz: e.target.checked });
$('autoCat').onchange = (e) => chrome.storage.local.set({ autoCat: e.target.checked });
$('catTop').onchange = (e) => chrome.storage.local.set({ catTop: e.target.checked });
$('notify').onchange = (e) => chrome.storage.local.set({ notify: e.target.checked });
$('opt').onclick = (e) => { e.preventDefault(); chrome.runtime.openOptionsPage(); };
$('tg').onclick = (e) => { e.preventDefault(); chrome.tabs.create({ url: $('tg').href }); };
init();
