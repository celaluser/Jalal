// Panel Araçları — mini panel: üç aracın kendi mini paneli birer sekmede (iframe) açılır.
// Çerçeve yalnız sekmesi seçilince yüklenir; yüksekliği içeriğine göre ayarlanır. Son seçilen sekme hatırlanır.
const TABS = ['kt', 'sp', 'pt'];
const qs = location.search || ''; // testte ?tab=<sekme no> çerçevelere aktarılır
function fit(f) {
  try {
    const h = f.contentDocument && f.contentDocument.body ? f.contentDocument.body.scrollHeight : 0;
    if (h) f.style.height = Math.max(160, Math.min(560, h)) + 'px';
  } catch (e) { /* henüz yüklenmedi */ }
}
function show(t) {
  for (const k of TABS) {
    const on = k === t;
    const b = document.getElementById('t-' + k);
    const f = document.getElementById('f-' + k);
    b.setAttribute('aria-selected', on ? 'true' : 'false');
    b.tabIndex = on ? 0 : -1;
    f.hidden = !on;
    if (on && !f.src) { f.addEventListener('load', () => fit(f)); f.src = k + '.html' + qs; }
    if (on) fit(f);
  }
  try { localStorage.setItem('pa_tab', t); } catch (e) { /* yok */ }
}
for (const k of TABS) {
  const b = document.getElementById('t-' + k);
  b.addEventListener('click', () => show(k));
  b.addEventListener('keydown', (e) => {
    const d = e.key === 'ArrowRight' ? 1 : e.key === 'ArrowLeft' ? -1 : 0;
    if (!d) return;
    e.preventDefault();
    const n = TABS[(TABS.indexOf(k) + d + TABS.length) % TABS.length];
    document.getElementById('t-' + n).focus();
    show(n);
  });
}
let first = 'kt';
try { const s = localStorage.getItem('pa_tab'); if (TABS.includes(s)) first = s; } catch (e) { /* yok */ }
show(first);
setInterval(() => { const f = document.querySelector('iframe:not([hidden])'); if (f) fit(f); }, 400);
