// Panel Araçları — üç aracın içerik betiklerinden önce yüklenir (aynı izole dünyayı paylaşırlar).
// Sayfada TEK pencere: başlık (sürükle), araç sekmeleri, seçili aracın içeriği. Araçların kendi çubukları bu pencerenin içine
// yerleşir (data-embed: kendi başlıkları/gölgeleri gizlenir). Pencere "–" ile küçük bir hapa döner; yeri ve seçili araç hatırlanır.
globalThis.__PANEL_ARACLARI = true;
(() => {
  if (globalThis.__paDock) return;
  const TOOLS = [['kt', 'Kategori', 'Boş/pasif kategorileri temizle'], ['sp', 'Pasifle', 'Not Assigned\'daki aktif servisleri pasife al'], ['pt', 'Taşı', 'Pasif servisleri Not Assigned\'a taşı']];
  const ICON = {
    kt: '<svg viewBox="0 0 16 16" width="14" height="14" aria-hidden="true"><path fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" d="M2.5 4.5h11M6.5 2.5h3M4 4.5l.7 8.2c.1.7.6 1.3 1.3 1.3h4c.7 0 1.2-.6 1.3-1.3l.7-8.2M6.8 7v4.5M9.2 7v4.5"/></svg>',
    sp: '<svg viewBox="0 0 16 16" width="14" height="14" aria-hidden="true"><circle cx="8" cy="8" r="5.6" fill="none" stroke="currentColor" stroke-width="1.5"/><path d="M4.2 11.8l7.6-7.6" stroke="currentColor" stroke-width="1.5" stroke-linecap="round"/></svg>',
    pt: '<svg viewBox="0 0 16 16" width="14" height="14" aria-hidden="true"><path fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round" d="M2.5 5.5h9.5M9.5 3l2.5 2.5L9.5 8M13.5 10.5H4M6.5 8L4 10.5 6.5 13"/></svg>',
  };
  const LOGO = '<svg viewBox="0 0 32 32" width="18" height="18" aria-hidden="true"><path fill="#243b6b" d="M3.2 28.5V20C3.2 15 6.11 12 8.1 9c1.7 3.2 3.08 6 2.82 11V28.5z"/><path fill="#b3362f" d="M12.4 28.5V13.6C12.4 8.6 15.4 5.6 17.6 2.6c1.76 3.2 2.96 6 2.64 11V28.5z"/><path fill="#d9a21b" d="M21.6 28.5V23.5C21.6 18.5 24.42 15.5 26.2 12.5c1.64 3.2 3.2 6 3 11V28.5z"/></svg>';
  const refs = {}; // key → { host, bar }
  const ls = (k, v) => { try { if (v === undefined) return localStorage.getItem(k); if (v === null) localStorage.removeItem(k); else localStorage.setItem(k, v); } catch (e) { /* yok */ } return null; };
  let sel = ls('pa_sec') || 'kt';
  if (!TOOLS.some((t) => t[0] === sel)) sel = 'kt';
  let small = ls('pa_small') !== '0'; // başta küçük hap: sayfayı kapatmasın
  let pos = null; // { r, b } sağ/alt kenara uzaklık
  try { pos = JSON.parse(ls('pa_pos') || 'null'); } catch (e) { pos = null; }
  let win = null; let root = null;
  const CSS = `
    :host{all:initial;--bg:#ffffff;--sf:#f6f7f9;--sf2:#eef0f3;--bd:#e3e6ea;--tx:#111827;--mt:#6b7280;--ac:#4f46e5;--dn:#dc2626;--ok:#059669;--wr:#d97706;
      --sh:0 1px 2px rgba(16,24,40,.06),0 16px 40px rgba(16,24,40,.16)}
    @media (prefers-color-scheme:dark){:host{--bg:#14161b;--sf:#1b1e25;--sf2:#22262f;--bd:#2b303b;--tx:#e6e8ec;--mt:#9aa1ad;--ac:#818cf8;--dn:#f87171;--ok:#34d399;--wr:#fbbf24;--sh:0 1px 2px rgba(0,0,0,.4),0 18px 44px rgba(0,0,0,.55)}}
    *{box-sizing:border-box}
    .w{position:fixed;z-index:2147483646;width:380px;max-width:calc(100vw - 16px);max-height:calc(100vh - 16px);display:flex;flex-direction:column;background:var(--bg);color:var(--tx);
      border:1px solid var(--bd);border-radius:12px;box-shadow:var(--sh);font:13px/1.45 ui-sans-serif,system-ui,-apple-system,"Segoe UI",Roboto,Arial,sans-serif;overflow:hidden}
    .hd{flex:none;display:flex;align-items:center;gap:8px;padding:8px 8px 8px 12px;cursor:grab;user-select:none}
    .hd:active{cursor:grabbing}
    .hd b{font-size:13px;font-weight:600}
    .hd .st{flex:1;min-width:0;color:var(--mt);font-size:11.5px;white-space:nowrap;overflow:hidden;text-overflow:ellipsis}
    .ib{all:unset;cursor:pointer;width:28px;height:28px;display:grid;place-items:center;border-radius:6px;color:var(--mt)}.ib:hover{background:var(--sf);color:var(--tx)}
    .ib:focus-visible,.t:focus-visible,.pill:focus-visible{outline:2px solid var(--ac);outline-offset:1px}
    .tabs{flex:none;display:flex;gap:2px;margin:0 10px;padding:2px;background:var(--sf2);border-radius:8px}
    .t{all:unset;position:relative;cursor:pointer;flex:1;display:flex;align-items:center;justify-content:center;gap:6px;height:30px;border-radius:6px;color:var(--mt);font-weight:600;font-size:12.5px}
    .t:hover{color:var(--tx)}
    .t[aria-selected=true]{background:var(--bg);color:var(--tx);box-shadow:0 1px 2px rgba(16,24,40,.12)}
    .t[aria-selected=true] svg{color:var(--ac)}
    .d{width:6px;height:6px;border-radius:50%;flex:none}
    .d.run{background:var(--wr)}.d.auto{background:var(--ok)}.d.warn{background:var(--dn)}
    .bd{flex:1 1 auto;min-height:0;overflow:auto;margin-top:8px;border-top:1px solid var(--bd)}
    .bd::-webkit-scrollbar{width:8px}.bd::-webkit-scrollbar-thumb{background:var(--bd);border-radius:8px}
    .pill{all:unset;position:fixed;z-index:2147483646;cursor:pointer;display:flex;align-items:center;gap:8px;height:36px;padding:0 12px 0 10px;border-radius:999px;background:var(--bg);color:var(--tx);
      border:1px solid var(--bd);box-shadow:var(--sh);font:600 12.5px/1 ui-sans-serif,system-ui,sans-serif;user-select:none}
    .pill:hover{background:var(--sf)}
    .pill .ds{display:flex;gap:3px}
    [hidden]{display:none !important}
  `;
  function ensure() {
    const any = Object.values(refs).some((r) => r.host && r.host.isConnected);
    if (!any) { if (win) { win.remove(); win = null; root = null; } return; }
    if (win && win.isConnected) return;
    win = document.createElement('div');
    win.id = 'pa-dock';
    root = win.attachShadow({ mode: 'open' });
    root.innerHTML = '<style>' + CSS + '</style>'
      + '<button class="pill" title="Panel Araçları — aç" aria-label="Panel Araçları penceresini aç">' + LOGO + '<span>Panel Araçları</span><span class="ds"></span></button>'
      + '<section class="w" role="dialog" aria-label="Panel Araçları"><div class="hd" title="Sürükle: taşı · çift tık: köşeye döndür">' + LOGO + '<b>Panel Araçları</b><span class="st"></span>'
      + '<button class="ib" data-x="min" title="Küçült" aria-label="Küçült"><svg viewBox="0 0 16 16" width="14" height="14" aria-hidden="true"><path d="M3.5 8h9" stroke="currentColor" stroke-width="1.6" stroke-linecap="round"/></svg></button></div>'
      + '<div class="tabs" role="tablist">' + TOOLS.map(([k, n, t]) => '<button class="t" role="tab" data-k="' + k + '" title="' + t + '">' + ICON[k] + n + '<span class="d" hidden></span></button>').join('') + '</div>'
      + '<div class="bd"><slot></slot></div></section>';
    root.addEventListener('click', (e) => {
      const b = e.target.closest && e.target.closest('button');
      if (!b) return;
      if (b.dataset.k) select(b.dataset.k);
      else if (b.dataset.x === 'min') setSmall(true);
      else if (b.classList.contains('pill')) { if (moved) { moved = false; return; } setSmall(false); }
    });
    drag(root.querySelector('.hd'));
    drag(root.querySelector('.pill'));
    document.documentElement.appendChild(win);
    for (const r of Object.values(refs)) adopt(r.host);
    layout(); paint();
  }
  function adopt(h) {
    if (!win || !h || !h.isConnected) return;
    h.setAttribute('data-embed', '');
    if (h.parentNode !== win) win.appendChild(h);
  }
  function setSmall(v) { small = v; ls('pa_small', v ? '1' : '0'); layout(); paint(); }
  function select(k) { sel = k; ls('pa_sec', k); layout(); paint(); }
  /** Konum: kayıtlı yer (ekrana sığdırılır) ya da sağ alt köşe. Küçükken hap, değilken pencere. */
  function layout() {
    if (!root) return;
    const w = root.querySelector('.w'); const p = root.querySelector('.pill');
    w.hidden = small; p.hidden = !small;
    for (const [k, r] of Object.entries(refs)) if (r.host && r.host.isConnected) r.host.style.display = k === sel ? '' : 'none';
    const el = small ? p : w;
    const rc = el.getBoundingClientRect();
    const R = Math.max(4, Math.min(innerWidth - rc.width - 4, pos ? pos.r : 12));
    const B = Math.max(4, Math.min(innerHeight - rc.height - 4, pos ? pos.b : 12));
    for (const x of [w, p]) { x.style.right = R + 'px'; x.style.bottom = B + 'px'; }
  }
  let moved = false;
  function drag(handle) {
    let st = null;
    handle.addEventListener('pointerdown', (e) => {
      if (e.target.closest && e.target.closest('.ib')) return;
      const el = small ? root.querySelector('.pill') : root.querySelector('.w');
      const r = el.getBoundingClientRect();
      st = { x: e.clientX, y: e.clientY, r: innerWidth - r.right, b: innerHeight - r.bottom, id: e.pointerId };
      moved = false;
    });
    handle.addEventListener('pointermove', (e) => {
      if (!st) return;
      const dx = e.clientX - st.x; const dy = e.clientY - st.y;
      if (!moved && Math.abs(dx) + Math.abs(dy) < 4) return;
      if (!moved) { moved = true; try { handle.setPointerCapture(st.id); } catch (er) { /* yok */ } }
      pos = { r: st.r - dx, b: st.b - dy };
      layout();
    });
    const end = () => { if (!st) return; st = null; if (moved && pos) ls('pa_pos', JSON.stringify(pos)); };
    handle.addEventListener('pointerup', end);
    handle.addEventListener('pointercancel', end);
    handle.addEventListener('dblclick', () => { pos = null; ls('pa_pos', null); layout(); });
  }
  addEventListener('resize', layout);
  const NAME = { kt: 'Kategori', sp: 'Pasifle', pt: 'Taşı' };
  function stateOf(k) {
    const r = refs[k];
    if (document.documentElement.getAttribute('data-panel-arac') === k) return ['run', 'çalışıyor'];
    const st = r && r.host && r.host.shadowRoot && r.host.shadowRoot.getElementById('st');
    if (st && /^⚠/.test(st.textContent || '')) return ['warn', 'uyarı var'];
    try { const j = JSON.parse(localStorage.getItem(k + '_job') || 'null'); if (j && Array.isArray(j.keys) && j.keys.some((x) => !j.ok.includes(x)) && Date.now() - j.at < 864e5) return ['warn', 'yarım kalan iş var']; } catch (e) { /* yok */ }
    try { const a = JSON.parse(sessionStorage.getItem(k + '_auto') || 'null'); if (a && a.on) return ['auto', 'otomatik açık']; } catch (e) { /* yok */ }
    return null;
  }
  function paint() {
    if (!root) return;
    const notes = [];
    let dots = '';
    for (const b of root.querySelectorAll('.t[data-k]')) {
      const k = b.dataset.k;
      b.setAttribute('aria-selected', k === sel ? 'true' : 'false');
      b.tabIndex = k === sel ? 0 : -1;
      b.hidden = !(refs[k] && refs[k].host && refs[k].host.isConnected);
      const s = stateOf(k);
      const d = b.querySelector('.d');
      d.hidden = !s; d.className = 'd' + (s ? ' ' + s[0] : '');
      if (s) { notes.push(NAME[k] + ' ' + s[1]); dots += '<span class="d ' + s[0] + '" title="' + NAME[k] + ' ' + s[1] + '"></span>'; }
    }
    root.querySelector('.hd .st').textContent = notes.join(' · ');
    root.querySelector('.pill .ds').innerHTML = dots;
  }
  globalThis.__paDock = {
    /** Araç her çizimde çağırır: çubuğu pencerenin içine alır. */
    place(k, h, bar) { refs[k] = { host: h, bar }; ensure(); adopt(h); if (root) { h.style.display = k === sel ? '' : 'none'; } paint(); },
    select,
  };
  setInterval(() => { ensure(); paint(); }, 1000);
})();
