// Panel Araçları — üç aracın içerik betiklerinden önce yüklenir (aynı izole dünyayı paylaşırlar).
// Sayfada üç çubuk birden görünmesin diye tek bir "dok" durur: [🗑 Kategori] [⊘ Pasifle] [⇄ Taşı].
// - Tıklanan aracın çubuğu dokun yanında açılır, diğerleri gizlenir (gizli araç çalışmaya devam eder).
// - Dok tutamacından (⠿) sürüklenir, yeri hatırlanır; çift tıklayınca köşeye döner. "–" ile tek düğmeye küçülür.
// - Noktalar: sarı = çalışıyor, yeşil = otomatik mod açık, kırmızı = uyarı var.
globalThis.__PANEL_ARACLARI = true;
(() => {
  if (globalThis.__paDock) return;
  const TOOLS = [['kt', '🗑', 'Kategori', 'Kategori Temizle — boş/pasif kategoriler', '#3b5bdb'], ['sp', '⊘', 'Pasifle', 'Servis Pasifle — Not Assigned\'daki aktif servisler', '#1f8a5b'], ['pt', '⇄', 'Taşı', 'Pasif Servis Taşı — pasif servisleri Not Assigned\'a', '#c26a1b']];
  const refs = {}; // key → { host, bar }
  const ls = (k, v) => { try { if (v === undefined) return localStorage.getItem(k); if (v === null) localStorage.removeItem(k); else localStorage.setItem(k, v); } catch (e) { /* yok */ } return null; };
  let sel = ls('pa_sec') || '';
  let small = ls('pa_small') === '1';
  let pos = null; // { r, b } sağ/alt kenara uzaklık (px)
  try { pos = JSON.parse(ls('pa_pos') || 'null'); } catch (e) { pos = null; }
  let host = null; let root = null;
  const CSS = `
    :host{all:initial;--ink:#16202a;--mut:#5d6b78;--line:#e2e7ec;--bg:#ffffff;--soft:#f4f6f9}
    @media (prefers-color-scheme:dark){:host{--ink:#e8edf2;--mut:#9aa7b4;--line:#2a3441;--bg:#18202b;--soft:#121821}}
    .dock{position:fixed;z-index:2147483647;display:flex;align-items:center;gap:2px;padding:4px;border-radius:14px;background:var(--bg);border:1px solid var(--line);
      box-shadow:0 12px 32px rgba(15,23,42,.18),0 2px 6px rgba(15,23,42,.08);font:600 12.5px/1 system-ui,-apple-system,"Segoe UI",Roboto,sans-serif;color:var(--ink);user-select:none}
    .grip{all:unset;cursor:grab;width:18px;height:30px;display:grid;place-items:center;color:var(--mut);border-radius:8px;font-size:14px;letter-spacing:-1px}
    .grip:active{cursor:grabbing}.grip:hover{background:var(--soft)}
    .brand{display:flex;align-items:center;gap:6px;padding:0 8px 0 2px;color:var(--mut);font-size:11.5px;white-space:nowrap}
    .brand svg{flex:none}
    .t{all:unset;position:relative;cursor:pointer;display:flex;align-items:center;gap:6px;height:30px;padding:0 11px;border-radius:10px;white-space:nowrap;color:var(--ink)}
    .t:hover{background:var(--soft)}
    .t[aria-pressed=true]{background:var(--ac);color:#fff}
    .t .i{font-size:13px;line-height:1}
    .t:focus-visible,.x:focus-visible,.grip:focus-visible{outline:2px solid #3b5bdb;outline-offset:1px}
    .d{position:absolute;top:4px;right:4px;width:7px;height:7px;border-radius:50%;box-shadow:0 0 0 2px var(--bg)}
    .t[aria-pressed=true] .d{box-shadow:0 0 0 2px var(--ac)}
    .d.run{background:#f2b33d}.d.auto{background:#2fb36e}.d.warn{background:#e5484d}
    .x{all:unset;cursor:pointer;width:24px;height:30px;display:grid;place-items:center;color:var(--mut);border-radius:8px}.x:hover{background:var(--soft);color:var(--ink)}
    .dock.small .brand,.dock.small .t,.dock.small .grip{display:none}
    .dock.small{padding:3px}.dock.small .x{width:32px;height:32px}
    .dot{position:absolute;top:3px;right:3px;width:8px;height:8px;border-radius:50%;box-shadow:0 0 0 2px var(--bg)}
  `;
  const LOGO = '<svg viewBox="0 0 32 32" width="18" height="18" aria-hidden="true"><path fill="#243b6b" d="M3.2 28.5V20C3.2 15 6.11 12 8.1 9c1.7 3.2 3.08 6 2.82 11V28.5z"/><path fill="#b3362f" d="M12.4 28.5V13.6C12.4 8.6 15.4 5.6 17.6 2.6c1.76 3.2 2.96 6 2.64 11V28.5z"/><path fill="#d9a21b" d="M21.6 28.5V23.5C21.6 18.5 24.42 15.5 26.2 12.5c1.64 3.2 3.2 6 3 11V28.5z"/></svg>';
  function ensure() {
    const any = Object.values(refs).some((r) => r.host && r.host.isConnected);
    if (!any) { if (host) { host.remove(); host = null; root = null; } return; }
    if (host && host.isConnected) return;
    host = document.createElement('div');
    host.id = 'pa-dock';
    root = host.attachShadow({ mode: 'open' });
    root.innerHTML = '<style>' + CSS + '</style><div class="dock" role="toolbar" aria-label="Panel Araçları">'
      + '<button class="grip" title="Sürükle: yerini değiştir · çift tık: köşeye döndür" aria-label="Doku taşı">⠿</button>'
      + '<span class="brand">' + LOGO + '<span>Panel Araçları</span></span>'
      + TOOLS.map(([k, ic, name, title, ac]) => '<button class="t" data-k="' + k + '" style="--ac:' + ac + '" title="' + title + '" aria-pressed="false"><span class="i">' + ic + '</span>' + name + '<span class="d" hidden></span></button>').join('')
      + '<button class="x" title="Küçült / büyüt" aria-label="Küçült"></button></div>';
    root.addEventListener('click', (e) => {
      const t = e.target.closest && e.target.closest('button');
      if (!t) return;
      if (t.dataset.k) select(sel === t.dataset.k ? '' : t.dataset.k);
      else if (t.classList.contains('x')) { small = !small; ls('pa_small', small ? '1' : '0'); if (small) sel = ''; render(); apply(); }
    });
    drag(root.querySelector('.grip'));
    document.documentElement.appendChild(host);
    render(); paint();
  }
  /** Dokun konumu: kayıtlı yer (ekrana sığdırılır) ya da sağ alt köşe. */
  function render() {
    const d = root && root.querySelector('.dock');
    if (!d) return;
    d.classList.toggle('small', small);
    const x = root.querySelector('.x');
    x.innerHTML = small ? LOGO + '<span class="dot" hidden></span>' : '–';
    x.setAttribute('aria-label', small ? 'Panel Araçları — aç' : 'Küçült');
    const r = d.getBoundingClientRect();
    const R = Math.max(4, Math.min(innerWidth - r.width - 4, pos ? pos.r : 12));
    const B = Math.max(4, Math.min(innerHeight - r.height - 4, pos ? pos.b : 12));
    d.style.right = R + 'px'; d.style.bottom = B + 'px';
  }
  function drag(grip) {
    let start = null;
    grip.addEventListener('pointerdown', (e) => {
      const d = root.querySelector('.dock').getBoundingClientRect();
      start = { x: e.clientX, y: e.clientY, r: innerWidth - d.right, b: innerHeight - d.bottom };
      grip.setPointerCapture(e.pointerId);
      e.preventDefault();
    });
    grip.addEventListener('pointermove', (e) => {
      if (!start) return;
      pos = { r: start.r - (e.clientX - start.x), b: start.b - (e.clientY - start.y) };
      render(); apply();
    });
    const end = () => { if (!start) return; start = null; if (pos) ls('pa_pos', JSON.stringify(pos)); };
    grip.addEventListener('pointerup', end);
    grip.addEventListener('pointercancel', end);
    grip.addEventListener('dblclick', () => { pos = null; ls('pa_pos', null); render(); apply(); });
    grip.addEventListener('keydown', (e) => { // klavyeyle de taşınır (ok tuşları, 20 px)
      const m = { ArrowLeft: [20, 0], ArrowRight: [-20, 0], ArrowUp: [0, 20], ArrowDown: [0, -20] }[e.key];
      if (!m) return;
      e.preventDefault();
      const d = root.querySelector('.dock').getBoundingClientRect();
      pos = { r: innerWidth - d.right + m[0], b: innerHeight - d.bottom + m[1] };
      ls('pa_pos', JSON.stringify(pos)); render(); apply();
    });
  }
  addEventListener('resize', () => { render(); apply(); });
  function select(k) {
    sel = k;
    ls('pa_sec', k);
    apply(); paint();
  }
  /** Seçili aracın çubuğu dokun yanında: dok ekranın alt yarısındaysa üstünde, üst yarısındaysa altında; dokla aynı kenara hizalı. */
  function apply() {
    const dock = root && root.querySelector('.dock');
    const dr = dock ? dock.getBoundingClientRect() : null;
    for (const [k, r] of Object.entries(refs)) {
      if (!r.host || !r.host.isConnected) continue;
      const on = k === sel && !small;
      r.host.style.display = on ? '' : 'none';
      if (!on || !r.bar) continue;
      const b = r.bar.style;
      b.left = 'auto'; b.top = 'auto'; b.bottom = 'auto';
      if (!dr) { b.right = '12px'; b.bottom = '60px'; continue; }
      const right = Math.max(8, innerWidth - dr.right);
      b.right = right + 'px';
      if (dr.top + dr.height / 2 > innerHeight / 2) { b.bottom = (innerHeight - dr.top + 8) + 'px'; b.maxHeight = (dr.top - 16) + 'px'; }
      else { b.top = (dr.bottom + 8) + 'px'; b.maxHeight = (innerHeight - dr.bottom - 16) + 'px'; }
      b.overflow = 'auto';
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
    let top = null;
    for (const b of root.querySelectorAll('.t[data-k]')) {
      const k = b.dataset.k;
      b.setAttribute('aria-pressed', k === sel ? 'true' : 'false');
      b.hidden = !(refs[k] && refs[k].host && refs[k].host.isConnected);
      const s = stateOf(k);
      const d = b.querySelector('.d');
      d.hidden = !s;
      d.className = 'd' + (s ? ' ' + s[0] : '');
      b.title = TOOLS.find((t) => t[0] === k)[3] + (s ? ' · ' + s[1] : '');
      if (s && (!top || s[0] === 'warn' || (s[0] === 'run' && top !== 'warn'))) top = s[0];
    }
    const dot = root.querySelector('.x .dot'); // küçükken en önemli durum tek noktada
    if (dot) { dot.hidden = !top; dot.className = 'dot d ' + (top || ''); }
  }
  globalThis.__paDock = {
    /** Araç her çizimde çağırır. */
    place(k, h, bar) { refs[k] = { host: h, bar }; ensure(); apply(); paint(); },
    select,
  };
  setInterval(() => { ensure(); paint(); }, 1000);
})();
