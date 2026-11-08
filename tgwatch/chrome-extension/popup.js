const $ = (id) => document.getElementById(id);
const get = (d) => new Promise((r) => chrome.storage.local.get(d, r));
const today = () => new Date().toISOString().slice(0, 10);
let tabId = null;
let tgBase = '';

function conn(text, kind) { const c = $('conn'); c.textContent = text; c.className = 'pill ' + kind; }

async function init() {
  const s = await get({ panel: '', api: '', key: '', dry: false, skipAz: true, autoCat: true, catTop: true, notify: true, bracketSpace: true, stripMinMax: true, stripSpeed: true, descTranslate: true, descMode: '', stats: { day: today(), today: 0, total: 0, learned: 0 } });
  $('dry').checked = !!s.dry;
  $('skipAz').checked = s.skipAz !== false;
  $('bracketSpace').checked = s.bracketSpace !== false;
  $('stripMinMax').checked = s.stripMinMax !== false;
  $('stripSpeed').checked = s.stripSpeed !== false;
  $('descTranslate').checked = s.descTranslate !== false;
  $('descMode').value = s.descMode || '';
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
  $('v-mine').textContent = mine;
  if (u && u.latest !== undefined) $('v-server').textContent = u.latest || 'bilinmiyor';
  const has = !!(u && u.has && newerV(u.latest, mine));
  $('upd-dot').hidden = !has;
  $('v-server').className = has ? 'new' : '';
  $('upd-msg').textContent = has ? 'Yeni sürüm hazır: ' + u.latest + '.' : (u && u.latest ? 'Eklenti güncel.' : '');
}
$('upd-go').onclick = () => { chrome.tabs.create({ url: chrome.runtime.getURL('update.html') }); window.close(); };
$('upd-dl').onclick = (e) => {
  e.preventDefault();
  if (!tgBase) { $('upd-msg').textContent = 'tgwatch adresi bilinmiyor: Bağlantı ayarlarını kontrol et.'; return; }
  chrome.tabs.create({ url: tgBase + '?p=ext_zip' });
};
$('upd-reload').onclick = (e) => { e.preventDefault(); chrome.runtime.sendMessage({ type: 'reload' }, () => { void chrome.runtime.lastError; window.close(); }); };

$('v-mine').textContent = chrome.runtime.getManifest().version;

// ---- sekmeler (son açılan hatırlanır) ----
let balLoaded = false;
function showTab(name) {
  for (const b of document.querySelectorAll('.tabs [role=tab]')) {
    const on = b.dataset.tab === name;
    b.setAttribute('aria-selected', on ? 'true' : 'false');
    b.tabIndex = on ? 0 : -1;
    $(b.getAttribute('aria-controls')).hidden = !on;
  }
  if (name === 'bal' && !balLoaded) { balLoaded = true; balances(false); }
  try { chrome.storage.local.set({ popTab: name }); } catch (e) { /* yok */ }
}
for (const b of document.querySelectorAll('.tabs [role=tab]')) {
  b.addEventListener('click', () => showTab(b.dataset.tab));
  b.addEventListener('keydown', (e) => {
    const all = [...document.querySelectorAll('.tabs [role=tab]')];
    const i = all.indexOf(b);
    const j = e.key === 'ArrowRight' ? (i + 1) % all.length : e.key === 'ArrowLeft' ? (i + all.length - 1) % all.length : -1;
    if (j >= 0) { e.preventDefault(); all[j].focus(); showTab(all[j].dataset.tab); }
  });
}
get({ popTab: 'panel' }).then((s) => showTab(['panel', 'bal', 'set', 'upd'].includes(s.popTab) ? s.popTab : 'panel'));

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
$('bracketSpace').onchange = (e) => chrome.storage.local.set({ bracketSpace: e.target.checked });
$('stripMinMax').onchange = (e) => chrome.storage.local.set({ stripMinMax: e.target.checked });
$('stripSpeed').onchange = (e) => chrome.storage.local.set({ stripSpeed: e.target.checked });
$('descTranslate').onchange = (e) => chrome.storage.local.set({ descTranslate: e.target.checked });
$('descMode').onchange = (e) => chrome.storage.local.set({ descMode: e.target.value });
$('opt').onclick = (e) => { e.preventDefault(); chrome.runtime.openOptionsPage(); };
$('tg').onclick = (e) => { e.preventDefault(); chrome.tabs.create({ url: $('tg').href }); };
init();
