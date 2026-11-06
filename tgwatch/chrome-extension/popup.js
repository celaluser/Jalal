const $ = (id) => document.getElementById(id);
const get = (d) => new Promise((r) => chrome.storage.local.get(d, r));
const today = () => new Date().toISOString().slice(0, 10);
let tabId = null;

function conn(text, kind) { const c = $('conn'); c.textContent = text; c.className = 'pill ' + kind; }

async function init() {
  const s = await get({ panel: '', api: '', key: '', dry: false, skipAz: true, stats: { day: today(), today: 0, total: 0, learned: 0 } });
  $('dry').checked = !!s.dry;
  $('skipAz').checked = s.skipAz !== false;
  $('s-today').textContent = s.stats.day === today() ? s.stats.today : 0;
  $('s-total').textContent = s.stats.total;
  $('s-learned').textContent = s.stats.learned;
  if (s.api) {
    try { const u = new URL(s.api); $('tg').href = u.origin + u.pathname.replace(/api\.php$/, ''); $('tg').hidden = false; } catch (e) { /* geçersiz */ }
  }
  if (!s.panel || !s.api || !s.key) {
    conn('Ayar yok', 'bad');
    $('tab-msg').textContent = 'Önce bağlantı ayarlarını gir (aşağıda).';
    return;
  }
  chrome.runtime.sendMessage({ type: 'api', action: 'ping' }, (r) => {
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

function refresh() {
  if (!tabId) return;
  chrome.tabs.sendMessage(tabId, { type: 'tgw-status' }, (r) => {
    if (chrome.runtime.lastError || !r) {
      $('tab-msg').textContent = 'Eklenti bu sayfada henüz çalışmıyor: sayfayı yenile.';
      return;
    }
    if (!r.screen) {
      $('tab-msg').textContent = 'Services → Import services → servisleri seç → inceleme ekranına geç.';
      $('tab-actions').hidden = true;
      $('tab-counts').hidden = true;
      return;
    }
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
$('opt').onclick = (e) => { e.preventDefault(); chrome.runtime.openOptionsPage(); };
$('tg').onclick = (e) => { e.preventDefault(); chrome.tabs.create({ url: $('tg').href }); };
init();
