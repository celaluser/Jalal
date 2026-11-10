// Panel Araçları — üç aracın içerik betiklerinden önce yüklenir (aynı izole dünyayı paylaşırlar).
// Sayfada üç çubuk birden görünmesin diye sağ altta tek bir "dok" durur: [🗑 Kategori] [⊘ Pasifle] [⇄ Taşı].
// Tıklanan aracın çubuğu dokun üstünde açılır, diğerleri gizlenir (gizli araç çalışmaya devam eder).
// Noktalar: sarı = çalışıyor, yeşil = otomatik mod açık, kırmızı = uyarı var.
globalThis.__PANEL_ARACLARI = true;
(() => {
  if (globalThis.__paDock) return;
  const TOOLS = [['kt', '🗑', 'Kategori', 'Kategori Temizle — boş/pasif kategoriler'], ['sp', '⊘', 'Pasifle', 'Servis Pasifle — Not Assigned\'daki aktif servisler'], ['pt', '⇄', 'Taşı', 'Pasif Servis Taşı — pasif servisleri Not Assigned\'a']];
  const refs = {}; // key → { host, bar }
  let sel = '';
  try { sel = localStorage.getItem('pa_sec') || ''; } catch (e) { /* yok */ }
  let host = null; let root = null;
  const CSS = `
    :host{all:initial}
    .dock{position:fixed;right:12px;bottom:12px;z-index:2147483647;display:flex;align-items:center;gap:2px;padding:3px;border-radius:999px;
      background:#141922;border:1px solid #3a4560;box-shadow:0 6px 20px rgba(0,0,0,.4);font:600 12px/1 system-ui,-apple-system,"Segoe UI",sans-serif;color:#cfd6e4}
    .lb{padding:0 8px 0 9px;color:#8f9bb8;font-weight:600;white-space:nowrap}
    button{all:unset;position:relative;cursor:pointer;display:flex;align-items:center;gap:5px;height:28px;padding:0 11px;border-radius:999px;white-space:nowrap;color:#cfd6e4}
    button:hover{background:#232b3a}
    button[aria-pressed=true]{background:#e8ecf4;color:#141922}
    button:focus-visible{outline:2px solid #8fb0ff;outline-offset:1px}
    .i{font-size:13px;line-height:1}
    .d{position:absolute;top:3px;right:4px;width:7px;height:7px;border-radius:50%;box-shadow:0 0 0 2px #141922}
    button[aria-pressed=true] .d{box-shadow:0 0 0 2px #e8ecf4}
    .d.run{background:#f2c14e}.d.auto{background:#4fd18b}.d.warn{background:#ff6b6b}
  `;
  function ensure() {
    const any = Object.values(refs).some((r) => r.host && r.host.isConnected);
    if (!any) { if (host) { host.remove(); host = null; root = null; } return; }
    if (host && host.isConnected) return;
    host = document.createElement('div');
    host.id = 'pa-dock';
    root = host.attachShadow({ mode: 'open' });
    root.innerHTML = '<style>' + CSS + '</style><div class="dock" role="toolbar" aria-label="Panel Araçları"><span class="lb">Panel Araçları</span>'
      + TOOLS.map(([k, ic, name, title]) => '<button data-k="' + k + '" title="' + title + '" aria-pressed="false"><span class="i">' + ic + '</span>' + name + '<span class="d" hidden></span></button>').join('') + '</div>';
    root.addEventListener('click', (e) => {
      const b = e.target.closest && e.target.closest('button[data-k]');
      if (!b) return;
      select(sel === b.dataset.k ? '' : b.dataset.k);
    });
    document.documentElement.appendChild(host);
    paint();
  }
  function select(k) {
    sel = k;
    try { localStorage.setItem('pa_sec', k); } catch (e) { /* yok */ }
    apply(); paint();
  }
  /** Seçili aracın çubuğu dokun üstünde, sağda; diğerleri gizli. */
  function apply() {
    const dock = root && root.querySelector('.dock');
    const bottom = dock ? Math.round(dock.getBoundingClientRect().height) + 20 : 52;
    for (const [k, r] of Object.entries(refs)) {
      if (!r.host || !r.host.isConnected) continue;
      const on = k === sel;
      r.host.style.display = on ? '' : 'none';
      if (on && r.bar) { r.bar.style.left = 'auto'; r.bar.style.right = '12px'; r.bar.style.bottom = bottom + 'px'; r.bar.style.maxHeight = 'calc(100vh - ' + (bottom + 20) + 'px)'; r.bar.style.overflow = 'auto'; }
    }
  }
  function stateOf(k) {
    const r = refs[k];
    if (document.documentElement.getAttribute('data-panel-arac') === k) return ['run', 'çalışıyor'];
    const st = r && r.host && r.host.shadowRoot && r.host.shadowRoot.getElementById('st');
    if (st && /^⚠/.test(st.textContent || '')) return ['warn', 'uyarı var'];
    try { const a = JSON.parse(sessionStorage.getItem(k + '_auto') || 'null'); if (a && a.on) return ['auto', 'otomatik mod açık']; } catch (e) { /* yok */ }
    return null;
  }
  function paint() {
    if (!root) return;
    for (const b of root.querySelectorAll('button[data-k]')) {
      const k = b.dataset.k;
      b.setAttribute('aria-pressed', k === sel ? 'true' : 'false');
      b.hidden = !(refs[k] && refs[k].host && refs[k].host.isConnected);
      const s = stateOf(k);
      const d = b.querySelector('.d');
      d.hidden = !s;
      d.className = 'd' + (s ? ' ' + s[0] : '');
      b.title = TOOLS.find((t) => t[0] === k)[3] + (s ? ' · ' + s[1] : '');
    }
  }
  globalThis.__paDock = {
    /** Araç her çizimde çağırır. */
    place(k, h, bar) { refs[k] = { host: h, bar }; ensure(); apply(); paint(); },
    select,
  };
  setInterval(() => { ensure(); paint(); }, 1000);
})();
