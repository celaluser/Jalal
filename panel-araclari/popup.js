// Panel Araçları — mini panel: üç aracın kendi paneli birer sekmede (iframe), Geçmiş sekmesi burada.
// Çerçeve yalnız sekmesi seçilince yüklenir; yüksekliği içeriğine göre ayarlanır. Son seçilen sekme hatırlanır.
const $ = (id) => document.getElementById(id);
const TABS = ['kt', 'sp', 'pt', 'hist', 'prot'];
const NAMES = { kt: 'Kategori Temizle', sp: 'Servis Pasifle', pt: 'Pasif Servis Taşı' };
const qs = location.search || ''; // testte ?tab=<sekme no> çerçevelere aktarılır
$('ver').textContent = 'v' + chrome.runtime.getManifest().version;
function fit(f) {
  try {
    const h = f.contentDocument && f.contentDocument.body ? f.contentDocument.body.scrollHeight : 0;
    if (h) f.style.height = Math.max(160, Math.min(500, h)) + 'px';
  } catch (e) { /* henüz yüklenmedi */ }
}
function show(t) {
  for (const k of TABS) {
    const on = k === t;
    const b = $('t-' + k);
    const p = $(k === 'hist' || k === 'prot' ? 'p-' + k : 'f-' + k);
    b.setAttribute('aria-selected', on ? 'true' : 'false');
    b.tabIndex = on ? 0 : -1;
    p.hidden = !on;
    if (on && p.tagName === 'IFRAME' && !p.src) { p.addEventListener('load', () => fit(p)); p.src = k + '.html' + qs; }
    if (on && p.tagName === 'IFRAME') fit(p);
  }
  if (t === 'hist') drawHist();
  if (t === 'prot') loadProt();
  try { localStorage.setItem('pa_tab', t); } catch (e) { /* yok */ }
}
for (const k of TABS) {
  const b = $('t-' + k);
  b.addEventListener('click', () => show(k));
  b.addEventListener('keydown', (e) => {
    const d = e.key === 'ArrowRight' ? 1 : e.key === 'ArrowLeft' ? -1 : 0;
    if (!d) return;
    e.preventDefault();
    const n = TABS[(TABS.indexOf(k) + d + TABS.length) % TABS.length];
    $('t-' + n).focus();
    show(n);
  });
}

// ---- sekmelerdeki durum noktaları (çalışıyor / otomatik / uyarı): açık panel sekmesindeki araçlara sorulur ----
async function panelTab() {
  const q = new URLSearchParams(location.search).get('tab');
  if (q) return Number(q);
  const [t] = await chrome.tabs.query({ active: true, currentWindow: true });
  return t && t.url && /^https:\/\/panelbaku\.com\/admin\/services/.test(t.url) ? t.id : null;
}
let tabId = null;
const ask = (k) => new Promise((res) => { if (tabId == null) return res(null); chrome.tabs.sendMessage(tabId, { [k]: true, cmd: 'state' }, (r) => { void chrome.runtime.lastError; res(r || null); }); });
async function dots() {
  for (const k of ['kt', 'sp', 'pt']) {
    const s = await ask(k);
    const d = $('t-' + k).querySelector('.d');
    const st = !s ? null : s.running ? 'run' : /^⚠/.test(s.status || '') ? 'warn' : s.auto && s.auto.on ? 'auto' : null;
    d.hidden = !st;
    d.className = 'd' + (st ? ' ' + st : '');
  }
}

// ---- Geçmiş ----
const hm = (t) => { const d = new Date(t); const today = new Date().toDateString() === d.toDateString(); return (today ? '' : d.toLocaleDateString('tr-TR', { day: '2-digit', month: '2-digit' }) + ' ') + d.toTimeString().slice(0, 5); };
const el = (tag, cls, text) => { const e = document.createElement(tag); if (cls) e.className = cls; if (text !== undefined) e.textContent = text; return e; };
let hist = [];
async function drawHist() {
  hist = (await chrome.storage.local.get({ pa_history: [] })).pa_history || [];
  const tool = $('h-tool').value;
  const real = $('h-real').checked;
  const rows = hist.filter((h) => (!tool || h.tool === tool) && (!real || !h.dry));
  $('h-list').replaceChildren(...rows.map((h) => {
    const li = el('li');
    const head = el('div', 'h');
    head.append(el('b', '', NAMES[h.tool] || h.tool), el('span', 'tag' + (h.dry ? '' : ' real'), h.dry ? 'deneme' : 'gerçek'));
    if (h.auto) head.append(el('span', 'tag auto', 'otomatik'));
    const tm = el('time', '', hm(h.at)); tm.dateTime = new Date(h.at).toISOString(); head.append(tm);
    li.append(head, el('p', '', h.text));
    if ((h.items || []).length || (h.errorItems || []).length) {
      const det = el('details');
      det.append(el('summary', '', (h.items || []).length + ' öğe' + ((h.errorItems || []).length ? ' · ' + h.errorItems.length + ' hata' : '')));
      for (const x of h.items || []) det.append(el('div', '', x));
      for (const x of h.errorItems || []) det.append(el('div', 'warn', '⚠ ' + x));
      li.append(det);
    }
    return li;
  }));
  $('h-empty').hidden = rows.length > 0;
  $('h-empty').textContent = hist.length ? 'Bu filtreye uyan iş yok.' : 'Henüz biten iş yok. Bir araç işini bitirince burada görünür (deneme dahil).';
}
$('h-tool').onchange = drawHist;
$('h-real').onchange = drawHist;
$('h-copy').onclick = async () => {
  const txt = hist.map((h) => hm(h.at) + ' · ' + (NAMES[h.tool] || h.tool) + ' · ' + (h.dry ? 'deneme' : 'gerçek') + (h.auto ? ' · otomatik' : '') + '\n  ' + h.text + (h.items && h.items.length ? '\n  - ' + h.items.join('\n  - ') : '')).join('\n');
  try { await navigator.clipboard.writeText(txt || '(boş)'); $('h-msg').textContent = 'Panoya kopyalandı.'; } catch (e) { $('h-msg').textContent = 'Panoya kopyalanamadı.'; }
};
let armed = false;
$('h-clear').onclick = async () => {
  if (!armed) { armed = true; $('h-clear').textContent = 'Emin misin? Temizle'; setTimeout(() => { armed = false; $('h-clear').textContent = 'Temizle'; }, 4000); return; }
  await chrome.storage.local.set({ pa_history: [] });
  armed = false; $('h-clear').textContent = 'Temizle'; $('h-msg').textContent = 'Geçmiş temizlendi.';
  drawHist();
};
chrome.storage.onChanged.addListener((ch, area) => { if (area === 'local' && ch.pa_history && !$('p-hist').hidden) drawHist(); });

// ---- bildirim ayarı ----
chrome.storage.local.get({ pa_notify: true }, (o) => { $('notif').checked = o.pa_notify !== false; });
$('notif').onchange = () => chrome.storage.local.set({ pa_notify: $('notif').checked });

let first = 'kt';
try { const s = localStorage.getItem('pa_tab'); if (TABS.includes(s)) first = s; } catch (e) { /* yok */ }
show(first);
(async () => { tabId = await panelTab(); $('sub').textContent = tabId == null ? 'Bu sekme panelin Services sayfası değil' : 'Perfect Panel · Services'; dots(); setInterval(dots, 1500); })();
setInterval(() => { const f = document.querySelector('iframe:not([hidden])'); if (f) fit(f); }, 400);

// ---- Koruma listesi (pa_protect): araçlar bu ID'leri hiç işlemez ----
async function loadProt() {
  const { pa_protect: ids } = await chrome.storage.local.get({ pa_protect: [] });
  $('pr-in').value = (ids || []).join(', ');
  $('pr-msg').className = 'muted small';
  $('pr-msg').textContent = (ids || []).length ? (ids.length + ' ID korunuyor.') : 'Korunan ID yok.';
}
$('pr-save').onclick = async () => {
  const ids = [...new Set($('pr-in').value.match(/\d{2,}/g) || [])];
  await chrome.storage.local.set({ pa_protect: ids });
  $('pr-in').value = ids.join(', ');
  $('pr-msg').className = 'small ok-msg';
  $('pr-msg').textContent = '✓ Kaydedildi: ' + ids.length + ' ID korunuyor. Açık paneldeki listeler hemen güncellenir.';
};
$('pr-copy').onclick = async () => { try { await navigator.clipboard.writeText(($('pr-in').value.match(/\d{2,}/g) || []).join(',')); $('pr-msg').textContent = 'Panoya kopyalandı.'; } catch (e) { $('pr-msg').textContent = 'Panoya kopyalanamadı.'; } };
