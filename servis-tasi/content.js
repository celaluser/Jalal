// Pasif Servis Taşı — Perfect Panel > Services: kategorilerdeki PASİF servisleri Not Assigned'a taşır.
// Akış: Bul → tüm servisleri aç → listeyi baştan sona gez (sanallaştırılmış) → her servisi üstündeki kategori başlığına bağla →
//   pasif servisleri listele (varsayılan: yalnız aktif kategoriler; Not Assigned hariç).
// Taşı → gruplar hâlinde: seçimi sıfırla → listeyi gezip gruptaki her servisin kutucuğunu işaretle (her tıklamadan önce:
//   hâlâ pasif mi, hâlâ aynı kategoride mi; sonra: "N services selected" bir arttı mı) → toplu Actions → yalnızca tam
//   "Assign category" → "Not Assigned" → Save changes → onay "assign N services to category Not Assigned?" (N = işaretlenen) → Assign.
// Sonda listeyi yeniden gezip taşınanların gerçekten Not Assigned altında olduğunu doğrular. "Delete all" vb.'ne asla basılmaz.
(() => {
  if (window.__pasifServisTasi) return;
  window.__pasifServisTasi = true;

  const VERSION = '1.0.0';
  const SETTINGS_KEY = 'pt_settings';
  const DIALOG = '[role=dialog], .modal, .modal-dialog, .modal-content';
  const TOGGLE = '.service-table__header-hide-all, [class*="header-hide-all"]';
  const SEL_RE = /(\d+)\s+services?\s+selected/i;
  const ERR_WORDS = /error|fail|hata|cannot|can't|couldn't|unable|denied|not allowed|invalid|xəta|alınmadı|olmadı/i;

  // ---------------------------------------------------------------- yardımcılar
  const norm = (s) => (s || '').replace(/\s+/g, ' ').trim();
  const lower = (s) => norm(s).toLowerCase().replace(/i̇/g, 'i');
  let port = null; let seq = 0;
  const waiters = new Map();
  function getPort() {
    if (port) return port;
    try {
      port = chrome.runtime.connect({ name: 'pt' });
      port.onMessage.addListener((m) => { const f = m && m.t === 'wake' && waiters.get(m.id); if (f) f(); });
      port.onDisconnect.addListener(() => { void chrome.runtime.lastError; port = null; });
    } catch (e) { port = null; }
    return port;
  }
  /** Bekleme: hem sayfada hem service worker'da başlar, önce biten sayılır (arka plan sekmesinde sayfa zamanlayıcıları kısılır). */
  const sleep = (ms) => new Promise((resolve) => {
    const id = ++seq;
    let h = null;
    const fin = () => { if (!waiters.has(id)) return; waiters.delete(id); clearTimeout(h); resolve(); };
    waiters.set(id, fin);
    h = setTimeout(fin, ms);
    const p = getPort();
    if (p) { try { p.postMessage({ t: 'sleep', id, ms }); } catch (e) { port = null; } }
  });
  function busy(on) { const p = getPort(); if (p) { try { p.postMessage({ t: 'busy', on }); } catch (e) { port = null; } } }
  const btnText = (b) => norm(b.innerText !== undefined ? b.innerText : b.textContent);
  const visible = (el) => !!el && el.isConnected && !!(el.offsetWidth || el.offsetHeight || el.getClientRects().length);
  const catKey = (s) => lower(s).replace(/[^\p{L}\p{N}]+/gu, ' ').replace(/(^| )(new|yeni)(?= |$)/g, ' ').replace(/\s+/g, ' ').trim();
  const onServicesList = () => /^\/admin\/services\/?$/.test(location.pathname);
  const cls = (el) => (el && typeof el.className === 'string' ? el.className : '');

  async function waitFor(fn, timeout = 8000, step = 80) {
    const t0 = Date.now();
    for (;;) {
      const v = fn();
      if (v) return v;
      if (Date.now() - t0 > timeout) return null;
      await sleep(step);
    }
  }
  function textWithAlt(el) {
    let out = '';
    for (const n of el.childNodes) {
      if (n.nodeType === 3) out += n.nodeValue;
      else if (n.nodeType === 1) {
        const tag = n.tagName;
        if (tag === 'IMG') out += n.getAttribute('alt') || '';
        else if (/^(INPUT|TEXTAREA|BUTTON|SELECT|SCRIPT|STYLE)$/.test(tag)) continue;
        else {
          const cs = getComputedStyle(n);
          if (cs.display === 'none' || cs.visibility === 'hidden') continue;
          out += (cs.display.startsWith('inline') ? '' : ' ') + textWithAlt(n) + ' ';
        }
      }
    }
    return out;
  }
  function cleanName(raw) {
    let t = norm(raw.replace(/ᴺᴱᵂ/g, ' NEW ').replace(/\bActions\b\s*[▾▼⌄]?/g, '').replace(/\b(?:Expand|Collapse)\s*\(\s*\d+\s*\)/gi, ''));
    for (let i = 0; i < 3; i++) {
      t = t.replace(/^[^\p{L}\p{N}\p{Extended_Pictographic}]+/u, '').replace(/[^\p{L}\p{N}\p{Extended_Pictographic})\]]+$/u, '');
      t = t.replace(/^(?:NEW|YENİ|Yeni)\s+/, '').replace(/\s+(?:NEW|YENİ|Yeni)$/, '');
    }
    return norm(t);
  }
  const isNA = (t) => catKey(cleanName(t || '')) === 'not assigned';

  // ---------------------------------------------------------------- durum
  const S = {
    phase: 'idle', status: 'Hazır. "Pasif servisleri bul"a bas.', running: false, stop: false,
    all: [], // tüm pasif servisler (Not Assigned hariç): {sid, name, cat, catId, catActive, off}
    excluded: new Set(), stats: null, notes: [],
    res: { moved: [], dry: [], skipped: [], errors: [], pending: [] },
    diagNeeded: false, log: [], seen: { menu: [], dialog: [], toast: [] },
  };
  // Güvenlik: deneme modu her sayfa yüklenişinde AÇIK başlar. Diğer ayarlar hatırlanır.
  const settings = { dryRun: true, max: 500, batch: 50, includePasif: false };
  try {
    const v = JSON.parse(localStorage.getItem(SETTINGS_KEY) || '{}');
    if (v.max) settings.max = Math.max(1, Math.min(10000, parseInt(v.max, 10) || 500));
    if (v.batch) settings.batch = Math.max(1, Math.min(500, parseInt(v.batch, 10) || 50));
    if (typeof v.includePasif === 'boolean') settings.includePasif = v.includePasif;
  } catch (e) { /* yok */ }
  const saveSettings = () => { try { localStorage.setItem(SETTINGS_KEY, JSON.stringify({ max: settings.max, batch: settings.batch, includePasif: settings.includePasif })); } catch (e) { /* yok */ } };
  function patchSettings(p) {
    if (!p) return;
    if (typeof p.dryRun === 'boolean') settings.dryRun = p.dryRun;
    if (p.max !== undefined) settings.max = Math.max(1, Math.min(10000, parseInt(p.max, 10) || 500));
    if (p.batch !== undefined) settings.batch = Math.max(1, Math.min(500, parseInt(p.batch, 10) || 50));
    if (typeof p.includePasif === 'boolean' && !S.running) settings.includePasif = p.includePasif;
    saveSettings();
  }
  function log(msg) { S.log.push(new Date().toTimeString().slice(0, 8) + ' ' + msg); if (S.log.length > 300) S.log.shift(); }
  function setStatus(t) { S.status = t; log(t); render(); }
  const remember = (kind, arr) => { for (const t of arr) if (t && !S.seen[kind].includes(t)) S.seen[kind].push(t); S.seen[kind] = S.seen[kind].slice(-12); };

  // ---------------------------------------------------------------- sayfa okuma
  function catHeaders() {
    const list = [...document.querySelectorAll('.service-table__category, [id^="category-"]')].filter(visible).map((el) => {
      const ne = el.querySelector('[class*="category-name"]');
      const name = cleanName(textWithAlt(ne || el));
      const ce = el.querySelector('[class*="category-collapse"]');
      const m = ce && norm(ce.textContent).match(/(?:expand|collapse)\s*\(\s*(\d+)\s*\)/i);
      return { el, id: el.id || '', name, na: isNA(name), active: !/service-table__category-disabled/.test(cls(el)),
        ce, count: m ? parseInt(m[1], 10) : null, collapsed: !!(ce && (/hide-category/.test(cls(ce)) || /expand/i.test(norm(ce.textContent)))) };
    });
    if (!list.some((h) => h.na)) {
      const box = getBox();
      const scope = box === document.scrollingElement ? document.body : box;
      const leaf = [...scope.querySelectorAll('span, div, td, b, strong, a, p')].find((e) => e.children.length === 0 && visible(e) && isNA(e.textContent)
        && !e.closest('.service-table__row-service, .service-table__body, .dropdown-menu, [role=menu], [role=dialog], .modal'));
      if (leaf) list.push({ el: leaf.closest('.service-table__category, [style*="position: absolute"], [class*="category"]') || leaf.parentElement, id: '', name: 'Not Assigned', na: true, active: true, ce: null, count: null, collapsed: false });
    }
    return list;
  }
  function serviceRows() {
    let bodies = [...document.querySelectorAll('.service-table__body')].filter((b) => b.querySelector('.service-table__row-service'));
    if (!bodies.length) bodies = [...document.querySelectorAll('.service-table__row-service')];
    return bodies.filter(visible).map((b) => {
      const row = b.matches('.service-table__row-service') ? b : b.querySelector('.service-table__row-service');
      const idm = (b.id || row.id || '').match(/(\d+)/);
      const idCell = row.querySelector('.service-table__id');
      const sid = idm ? idm[1] : (idCell ? norm(idCell.textContent) : '');
      const nameCell = row.querySelector('.service-table__service');
      const name = norm(nameCell ? textWithAlt(nameCell) : textWithAlt(row)).slice(0, 140);
      const leaf = [...row.querySelectorAll('*')].find((e) => e.children.length === 0 && /^(enabled|disabled)$/i.test(norm(e.textContent)) && !e.closest('.dropdown-menu, [role=menu]'));
      const st = leaf ? lower(leaf.textContent) : null;
      const clsOff = /(^|\s)service-table__row-disabled(\s|$)/.test(cls(row));
      let state = 'unknown';
      if (st === 'enabled' && !clsOff) state = 'on';
      else if (st === 'disabled' && clsOff) state = 'off';
      // yazı yoksa durum belirsiz: taşımada yanlış servisi seçmemek için ikisi de şart
      const cb = row.querySelector('.service-table__checker input[type=checkbox]') || row.querySelector('input[type=checkbox]');
      return { el: b, row, sid, name, state, st, clsOff, cb };
    }).filter((r) => r.sid);
  }
  const findSvc = (sid) => serviceRows().find((r) => r.sid === sid) || null;

  function getBox() {
    const r = document.querySelector('.service-table__category, .service-table__body, .service-table__row-service');
    for (let el = r && r.parentElement; el && el !== document.body; el = el.parentElement) {
      if (/(auto|scroll)/.test(getComputedStyle(el).overflowY) && el.scrollHeight > el.clientHeight + 40) return el;
    }
    const c = document.querySelector('.service-table__container');
    if (c && c.scrollHeight > c.clientHeight + 40) return c;
    return document.scrollingElement;
  }
  function offsetOf(el) {
    const box = getBox();
    const bt = box === document.scrollingElement ? 0 : box.getBoundingClientRect().top;
    return Math.round(el.getBoundingClientRect().top - bt + box.scrollTop);
  }
  function setScroll(box, top) {
    box.scrollTop = top;
    try { (box === document.scrollingElement ? window : box).dispatchEvent(new Event('scroll')); } catch (e) { /* yok */ }
  }
  /** Listeyi en üstten gez; onStep (async olabilir) true dönerse durur. */
  async function walk(onStep) {
    const box = getBox();
    setScroll(box, 0);
    await sleep(350);
    if (await onStep()) return true;
    let stable = 0;
    for (let i = 0; i < 8000 && !S.stop; i++) {
      const before = box.scrollTop;
      setScroll(box, before + Math.max(150, Math.floor(box.clientHeight * 0.7)));
      await sleep(170);
      if (await onStep()) return true;
      const atEnd = box.scrollTop + box.clientHeight >= box.scrollHeight - 4 || box.scrollTop === before;
      if (!atEnd) { stable = 0; continue; }
      const h = box.scrollHeight;
      await sleep(700);
      if (await onStep()) return true;
      if (box.scrollHeight > h) { stable = 0; continue; }
      if (++stable >= 2) break;
    }
    return false;
  }

  /** Tüm servisleri aç (başlıktaki düğme). */
  async function ensureAllOpen() {
    const t = [...document.querySelectorAll(TOGGLE)].find(visible) || [...document.querySelectorAll('.fa-compress-alt, .fa-expand-alt')].find(visible);
    if (!t) { log('aç/kapa düğmesi bulunamadı'); return serviceRows().length > 0; }
    if (!/fa-compress/.test(cls(t))) {
      t.click();
      await waitFor(() => serviceRows().length > 0, 8000, 100);
    }
    return serviceRows().length > 0;
  }

  /**
   * Gezinti sırasında başlıkları izle: her servis üstündeki en yakın başlığa aittir. Kapalı (Expand) kalmış kategori
   * görülürse açılır ve altındaki konumlar sıfırlanır (yeniden okunur).
   */
  function tracker() {
    const heads = new Map(); // konum → başlık
    const opened = new Set();
    return {
      heads,
      async see(expand) {
        for (const h of catHeaders()) {
          const o = offsetOf(h.el);
          heads.set(o, { name: h.name, id: h.id, na: h.na, active: h.active, count: h.count });
          if (expand && h.collapsed && !h.na && (h.count || 0) > 0 && !opened.has(h.id || h.name) && h.ce) {
            opened.add(h.id || h.name);
            (h.ce.querySelector('[class*="collapse-name"]') || h.ce).click();
            await waitFor(() => serviceRows().some((r) => { const d = offsetOf(r.el) - o; return d > 0 && d < 300; }), 3000, 100);
            for (const k of [...heads.keys()]) if (k > o) heads.delete(k);
            return o; // bu konumun altı kaydı: çağıran oradan sonrasını yeniden okusun
          }
        }
        return null;
      },
      owner(off) { let best = null; let bo = -Infinity; for (const [o, h] of heads) if (o < off && o > bo) { bo = o; best = h; } return best; },
    };
  }

  // ---------------------------------------------------------------- menü / pencere
  function itemsByText(re) {
    const all = [...document.querySelectorAll('a, button, li, span, div, p, [role=menuitem], [role=option]')].filter((e) => visible(e) && re.test(norm(e.textContent)));
    return all.filter((e) => !all.some((o) => o !== e && e.contains(o)));
  }
  const MENU_ANY = /^(enable all|disable all|edit rates|assign category|delete all|hide all|make all visible|edit service|delete service)$/i;
  const click = (el) => (el.closest('a, button, li, [role=menuitem], [role=option]') || el).click();
  function pressEscape() {
    for (const t of [document.activeElement, document.body, document]) {
      if (t) t.dispatchEvent(new KeyboardEvent('keydown', { key: 'Escape', code: 'Escape', keyCode: 27, bubbles: true }));
    }
  }
  async function closeMenu() {
    if (!itemsByText(MENU_ANY).length) return;
    pressEscape(); await sleep(150);
    if (itemsByText(MENU_ANY).length) document.body.click();
    await sleep(150);
  }
  const anyDialog = () => [...document.querySelectorAll(DIALOG)].some(visible);
  function readToasts() {
    const sel = '[role=alert], [role=status], [class*=toast], [class*=Toast], [class*=notif], [class*=snack], [class*=alert], [class*=flash]';
    return [...document.querySelectorAll(sel)].filter(visible).map((e) => norm(e.textContent)).filter((t) => t && t.length < 300);
  }

  // ---- toplu seçim + Assign category (Kategori Temizle'den; gerçek panelde çalıştı)
  /** Üstteki "N services selected" çubuğu: sayı, toplu Actions düğmesi (kategori/servis satırında olmayan), kutucuk. */
  function selection() {
    const labs = [...document.querySelectorAll('label, span, div, b, strong, p')].filter((e) => visible(e) && e.children.length <= 3 && SEL_RE.test(norm(e.textContent)) && norm(e.textContent).length < 40);
    const deep = labs.filter((e) => !labs.some((o) => o !== e && e.contains(o)));
    if (!deep.length) return null;
    const el = deep[0];
    const n = parseInt(norm(el.textContent).match(SEL_RE)[1], 10);
    let box = el; let btn = null; let cb = null;
    for (let i = 0; i < 6 && box && box !== document.body; i++, box = box.parentElement) {
      btn = [...box.querySelectorAll('button, a')].find((b) => visible(b) && /^actions\b/i.test(btnText(b)) && !b.closest('.service-table__category, .service-table__row, .service-table__body, [id^="category-"]'));
      if (btn) break;
    }
    for (let b = el, i = 0; b && b !== document.body && i < 8 && !cb; i++, b = b.parentElement) {
      cb = [...b.querySelectorAll('input[type=checkbox]')].find((c) => visible(c) && (c.name === 'mass_action' || c.closest('.mass-actions') || !c.closest('.service-table__row, .service-table__body, .service-table__category'))) || null;
    }
    return { n, el, btn, cb };
  }

  /** Seçimi temizle (önce çubuktaki kutucuk; olmazsa işaretli servis kutucukları tek tek). */
  async function clearSelection() {
    let s = selection();
    if (!s) return true;
    for (let k = 0; k < 2 && s && s.cb; k++) {
      s.cb.click();
      if (await waitFor(() => !selection(), 3000, 100)) return true;
      s = selection(); // tıklama "hepsini seç" olarak çalıştıysa bir kez daha bas
    }
    for (const r of serviceRows()) if (r.cb && r.cb.checked) r.cb.click();
    if (await waitFor(() => !selection(), 3000, 100)) return true;
    s = selection();
    log('seçim temizlenemedi: ' + (s ? s.n : '?'));
    return false;
  }

  /** "Assign category" penceresi: başlık + Save changes düğmesi. */
  function findAssign() {
    for (const d of [...document.querySelectorAll(DIALOG)].filter(visible)) {
      const t = norm(d.textContent);
      if (!/assign category/i.test(t)) continue;
      const save = [...d.querySelectorAll('button, a, input[type=submit]')].find((b) => visible(b) && /^save changes$/i.test(norm(b.value || btnText(b))));
      if (!save) continue;
      const close = [...d.querySelectorAll('button, a')].find((b) => visible(b) && /^(close|cancel)$/i.test(btnText(b)))
        || d.querySelector('.close, [aria-label=close], [aria-label=Close]');
      return { dlg: d, save, close, text: t };
    }
    return null;
  }
  /**
   * Save changes sonrası panelin onay penceresi: "Assign to category — Are you sure you want to assign N services to category X?"
   * [Assign] [Cancel]. N ve X okunur; Assign'a basmadan önce doğrulanır.
   */
  function findAssignConfirm() {
    for (const d of [...document.querySelectorAll(DIALOG)].filter(visible)) {
      const t = norm(d.innerText || d.textContent); // innerText: satırlar arasında boşluk ("assign 2 services")
      if (!/assign\s*to\s*category|are you sure you want to assign/i.test(t)) continue;
      const ok = [...d.querySelectorAll('button, a, input[type=submit]')].find((b) => visible(b) && /^assign$/i.test(norm(b.value || btnText(b))));
      if (!ok) continue;
      const cancel = [...d.querySelectorAll('button, a')].find((b) => visible(b) && /^(cancel|close|no)$/i.test(btnText(b)));
      const m = t.match(/assign\s*(\d+)\s*services?\s*to\s*category\s*(.+?)\s*\?/i);
      return { dlg: d, ok, cancel, text: t, n: m ? parseInt(m[1], 10) : null, target: m ? m[2] : null };
    }
    return null;
  }
  async function cancelAssignConfirm() {
    const c = findAssignConfirm();
    if (!c) return true;
    if (c.cancel) c.cancel.click(); else pressEscape();
    return !!(await waitFor(() => !findAssignConfirm(), 4000, 100));
  }
  const enabled = (b) => !!b && !b.disabled && !b.classList.contains('disabled') && b.getAttribute('aria-disabled') !== 'true';

  function fire(el, type) {
    const r = el.getBoundingClientRect();
    const init = { bubbles: true, cancelable: true, composed: true, view: window, clientX: r.left + 5, clientY: r.top + 5, button: 0 };
    el.dispatchEvent(type.startsWith('pointer') && window.PointerEvent ? new PointerEvent(type, Object.assign(init, { pointerId: 1, pointerType: 'mouse', isPrimary: true })) : new MouseEvent(type, init));
  }
  /** React kontrollü input'a yaz (yerel value setter + input olayı; blur yok ki açılır liste kapanmasın). */
  function typeInto(input, text) {
    const proto = input.tagName === 'TEXTAREA' ? HTMLTextAreaElement.prototype : HTMLInputElement.prototype;
    Object.getOwnPropertyDescriptor(proto, 'value').set.call(input, text);
    input.dispatchEvent(new Event('input', { bubbles: true }));
  }

  /** Pencerede "Not Assigned"ı seç: native <select> ya da aramalı kutu (yaz → tek "Not Assigned" seçeneğine tıkla). */
  async function pickNotAssigned(a) {
    const native = a.dlg.querySelector('select');
    if (native) {
      const opt = [...native.options].filter((o) => isNA(o.textContent));
      if (opt.length !== 1) return 'seçim listesinde tek bir "Not Assigned" yok';
      Object.getOwnPropertyDescriptor(HTMLSelectElement.prototype, 'value').set.call(native, opt[0].value);
      native.dispatchEvent(new Event('change', { bubbles: true }));
    } else {
      const input = [...a.dlg.querySelectorAll('input')].find((i) => !/^(hidden|checkbox|radio|submit|button)$/i.test(i.type));
      if (!input) return 'pencerede arama kutusu bulunamadı';
      const control = input.closest('[class*="control"], [class*="container"], [class*="select"]') || input.parentElement;
      fire(control, 'mousedown'); input.focus();
      typeInto(input, 'Not assi');
      const opt = await waitFor(() => {
        const os = itemsByText(/^not assigned$/i).filter((o) => !o.closest('.service-table__category, .service-table__row')
          && (o.closest('[role=option], [class*="option"], [role=listbox], [class*="menu"], .select2-results, .chosen-results') || a.dlg.contains(o)));
        return os.length === 1 ? os[0] : null;
      }, 4000, 100);
      if (!opt) return 'arama sonucunda tek bir "Not Assigned" seçeneği çıkmadı';
      const target = opt.closest('[role=option], [class*="option"], li') || opt;
      fire(target, 'mousedown'); fire(target, 'mouseup'); target.click();
    }
    const ok = await waitFor(() => { const d = findAssign(); return d && /not assigned/i.test(d.text) && !/choose category/i.test(d.text) && enabled(d.save) ? d : null; }, 3000, 100);
    return ok ? null : '"Not Assigned" seçildikten sonra Save changes etkinleşmedi';
  }

  async function closeAssign() {
    const a = findAssign();
    if (!a) return true;
    if (a.close) a.close.click(); else pressEscape();
    return !!(await waitFor(() => !findAssign(), 4000, 100));
  }

  // ---------------------------------------------------------------- tarama
  function fail(msg) { S.phase = 'idle'; S.diagNeeded = true; setStatus('⚠ ' + msg); }
  const inScope = (x) => !S.excluded.has(x.sid) && (x.catActive || settings.includePasif);
  const workList = () => S.all.filter(inScope).slice(0, settings.max);

  async function scan() {
    if (S.running) return;
    S.running = true; S.stop = false; S.phase = 'scanning'; busy(true);
    S.all = []; S.excluded = new Set(); S.stats = null; S.notes = []; S.diagNeeded = false;
    S.res = { moved: [], dry: [], skipped: [], errors: [], pending: [] };
    try {
      if (!onServicesList()) return fail('Bu özellik yalnızca /admin/services sayfasında çalışır.');
      if (anyDialog()) return fail('Açık bir pencere var; önce kapat.');
      if (selection() && !(await clearSelection())) return fail('Panelde seçili servisler var ve temizlenemedi.');
      setStatus('Tüm servisler açılıyor…');
      if (!(await ensureAllOpen())) return fail('Servisler açılamadı (aç/kapa düğmesi).');
      setStatus('Liste baştan sona geziliyor…');
      const tr = tracker();
      const col = new Map();
      const perCat = new Map();
      const step = async () => {
        const moved = await tr.see(true);
        if (typeof moved === 'number') for (const [k, v] of col) if (v.off > moved) col.delete(k);
        for (const r of serviceRows()) {
          const off = offsetOf(r.el);
          const h = tr.owner(off);
          if (!h) continue;
          col.set(r.sid, { sid: r.sid, name: r.name, state: r.state, off, cat: h.name, catId: h.id, catActive: h.active, na: h.na, catCount: h.count });
        }
        S.status = 'Liste geziliyor… ' + col.size + ' servis'; render();
        return false;
      };
      await walk(step);
      if (S.stop) { S.phase = 'idle'; return setStatus('Durduruldu; liste eksik olabilir, tarama iptal.'); }
      const list = [...col.values()].sort((a, b) => a.off - b.off);
      if (!list.length) return fail('Hiç servis satırı okunamadı.');
      for (const x of list) { const k = x.catId || x.cat; const e = perCat.get(k) || { name: x.cat, count: x.catCount, seen: 0 }; e.seen++; perCat.set(k, e); }
      const off = list.filter((x) => x.state === 'off' && !x.na);
      const unk = list.filter((x) => x.state === 'unknown' && !x.na);
      S.all = off;
      const cats = new Set(list.map((x) => x.catId || x.cat));
      S.stats = { kategori: cats.size, servis: list.length, pasifAktifKat: off.filter((x) => x.catActive).length, pasifPasifKat: off.filter((x) => !x.catActive).length, belirsiz: unk.length, na: list.filter((x) => x.na).length };
      const short = [...perCat.values()].filter((e) => e.count != null && e.count !== e.seen);
      if (short.length) S.notes.push(short.length + ' kategoride okunan servis sayısı Expand/Collapse (N) ile tutmadı (ör. ' + short.slice(0, 3).map((e) => e.name.slice(0, 30) + ': ' + e.seen + '/' + e.count).join(', ') + ').');
      if (unk.length) S.notes.push(unk.length + ' servisin durumu okunamadı; bunlara dokunulmaz.');
      setScroll(getBox(), 0);
      S.phase = 'ready';
      setStatus(workList().length ? workList().length + ' pasif servis taşınmaya hazır. Henüz hiçbir şey değişmedi.' : 'Taşınacak pasif servis yok.');
    } catch (e) {
      log(String(e && e.stack).slice(0, 400));
      fail('Tarama hatası: ' + (e && e.message));
    } finally {
      S.running = false; busy(false); render();
    }
  }

  // ---------------------------------------------------------------- taşıma (gruplar)
  const label = (c) => c.sid + ' — ' + c.name;

  /** Bir grup: kutucukları işaretle → toplu Actions → Assign category → Not Assigned → Save → onay (N) → Assign. */
  async function processChunk(chunk) {
    const out = { moved: [], dry: [], skipped: [], errors: [], fatal: false };
    const errAll = (why, list) => { for (const c of list) out.errors.push({ c, why }); };
    if (!(await clearSelection())) { out.fatal = true; errAll('önceki seçim temizlenemedi', chunk); return out; }
    if (!(await ensureAllOpen())) { errAll('servisler açılamadı', chunk); return out; }
    const want = new Map(chunk.map((c) => [c.sid, c]));
    const decided = new Set();
    const picked = [];
    let abort = '';
    const tr = tracker();
    setStatus((settings.dryRun ? '[DENEME] ' : '') + 'Grup: ' + chunk.length + ' servis işaretleniyor…');
    await walk(async () => {
      await tr.see(true);
      for (const r of serviceRows()) {
        const c = want.get(r.sid);
        if (!c || decided.has(r.sid)) continue;
        const h = tr.owner(offsetOf(r.el));
        if (!h) continue;
        decided.add(r.sid);
        if (h.na) { out.skipped.push({ c, why: 'zaten Not Assigned\'da' }); continue; }
        if (c.catId ? h.id !== c.catId : catKey(h.name) !== catKey(c.cat)) { out.skipped.push({ c, why: 'kategorisi değişmiş (' + h.name.slice(0, 40) + ')' }); continue; }
        if (r.state !== 'off') { out.skipped.push({ c, why: r.state === 'on' ? 'artık aktif' : 'durumu okunamadı' }); continue; }
        if (!r.cb) { out.skipped.push({ c, why: 'kutucuk yok' }); continue; }
        if (r.cb.checked) { abort = 'servis zaten işaretliydi (' + c.sid + ')'; return true; }
        const want1 = picked.length + 1;
        r.cb.click();
        const ok = await waitFor(() => { const s = selection(); const x = findSvc(r.sid); return s && s.n === want1 && x && x.cb && x.cb.checked; }, 3000, 80);
        if (!ok) { const s = selection(); abort = 'işaretleme sonrası seçim ' + (s ? s.n : 0) + ' ≠ ' + want1; return true; }
        picked.push(c);
        if (S.stop) { abort = 'durduruldu'; return true; }
      }
      return decided.size === want.size;
    });
    for (const c of chunk) if (!decided.has(c.sid) && !abort) out.skipped.push({ c, why: 'listede bulunamadı (taşınmış/silinmiş olabilir)' });
    const bail = async (why, list) => { await closeMenu(); await closeAssign(); await cancelAssignConfirm(); await closeAssign(); await clearSelection(); errAll(why, list); return out; };
    if (abort) {
      if (abort === 'durduruldu') { await clearSelection(); for (const c of picked) out.skipped.push({ c, why: 'durduruldu (taşımadan önce)' }); return out; }
      S.diagNeeded = true;
      return bail(abort, picked.concat(chunk.filter((c) => !decided.has(c.sid))));
    }
    if (!picked.length) return out;
    const n = picked.length;
    const sel = selection();
    if (!sel || sel.n !== n) return bail('seçim sayısı (' + (sel ? sel.n : 0) + ') ≠ işaretlenen (' + n + ')', picked);
    if (!sel.btn) { S.diagNeeded = true; return bail('seçim çubuğunda Actions düğmesi bulunamadı', picked); }

    // toplu Actions → yalnızca tam "Assign category"
    const before = new Set(itemsByText(/^assign category$/i));
    sel.btn.click();
    const item = await waitFor(() => { const v = itemsByText(/^assign category$/i).filter((x) => !before.has(x) && !x.closest('.service-table__category, .service-table__row')); return v.length ? v[v.length - 1] : null; }, 2500, 60);
    if (!item) { S.diagNeeded = true; return bail('"Assign category" toplu menüde bulunamadı', picked); }
    remember('menu', [norm((item.closest('ul, [role=menu], .dropdown-menu') || item.parentElement).textContent).slice(0, 200)]);
    click(item);
    const a = await waitFor(findAssign, 4000, 80);
    if (!a) { S.diagNeeded = true; return bail('"Assign category" penceresi açılmadı', picked); }
    remember('dialog', [a.text.slice(0, 200)]);
    const perr = await pickNotAssigned(a);
    if (perr) { S.diagNeeded = true; return bail(perr, picked); }
    const sel2 = selection();
    if (sel2 && sel2.n !== n) return bail('pencere açıkken seçim değişti (' + sel2.n + ' ≠ ' + n + ')', picked);
    if (settings.dryRun || S.stop) {
      const closed = await closeAssign();
      const cleared = await clearSelection();
      if (!closed || !cleared) { out.fatal = true; errAll('deneme: pencere kapanmadı ya da seçim temizlenemedi', picked); return out; }
      for (const c of picked) (S.stop ? out.skipped : out.dry).push({ c, why: S.stop ? 'durduruldu (taşımadan önce)' : n + ' servislik grup: "Not Assigned" seçildi, Close' });
      return out;
    }
    const fa = findAssign();
    if (!fa || !enabled(fa.save)) return bail('Save changes etkin değil', picked);
    const seen = [];
    fa.save.click();
    const next = await waitFor(() => findAssignConfirm() || (!findAssign() ? 'closed' : null), 8000, 100);
    if (!next) { S.diagNeeded = true; return bail('Save changes sonrası onay penceresi gelmedi', picked); }
    if (next !== 'closed') {
      remember('dialog', [next.text.slice(0, 200)]);
      const bad = next.n !== n ? 'onay penceresindeki sayı (' + next.n + ') ≠ ' + n : !isNA(next.target || '') ? 'onay penceresindeki hedef "' + (next.target || '?') + '" (Not Assigned değil)' : '';
      if (bad || S.stop) {
        if (S.stop && !bad) { await cancelAssignConfirm(); await closeAssign(); await clearSelection(); for (const c of picked) out.skipped.push({ c, why: 'durduruldu (son onaydan önce)' }); return out; }
        S.diagNeeded = true;
        return bail(bad, picked);
      }
      next.ok.click();
    }
    const closed = await waitFor(() => { seen.push(...readToasts()); return !findAssign() && !findAssignConfirm(); }, 15000, 120);
    await sleep(500);
    seen.push(...readToasts());
    remember('toast', [...new Set(seen)]);
    const errToast = [...new Set(seen)].find((x) => ERR_WORDS.test(x));
    if (!closed) { S.diagNeeded = true; return bail('Assign penceresi kapanmadı (sunucu cevap vermedi?)', picked); }
    await clearSelection();
    if (errToast) { errAll('panel hata bildirdi: "' + errToast.slice(0, 100) + '"', picked); return out; }
    for (const c of picked) out.moved.push({ c, why: 'Not Assigned\'a taşındı (' + n + ' servislik grup)' });
    return out;
  }

  /** Sonda: listeyi gez, taşınanlar gerçekten Not Assigned altında mı? */
  async function verify(moved) {
    if (!moved.length) return { ok: [], bad: [] };
    setStatus('Doğrulanıyor: liste yeniden geziliyor…');
    await ensureAllOpen();
    const want = new Map(moved.map((x) => [x.c.sid, x]));
    const where = new Map();
    const tr = tracker();
    await walk(async () => {
      await tr.see(false);
      for (const r of serviceRows()) {
        if (!want.has(r.sid) || where.has(r.sid)) continue;
        const h = tr.owner(offsetOf(r.el));
        if (h) where.set(r.sid, h);
      }
      return where.size === want.size;
    });
    const ok = []; const bad = [];
    for (const x of moved) {
      const h = where.get(x.c.sid);
      if (h && h.na) ok.push(x);
      else bad.push({ c: x.c, why: h ? 'taşınmadı: hâlâ "' + h.name.slice(0, 40) + '" altında' : 'doğrulanamadı (listede görülmedi)' });
    }
    return { ok, bad };
  }

  async function run() {
    if (S.running || S.phase !== 'ready' || !workList().length) return;
    if (anyDialog()) return fail('Açık bir pencere var; önce kapat.');
    S.running = true; S.stop = false; S.phase = 'running'; S.diagNeeded = false; busy(true);
    S.res = { moved: [], dry: [], skipped: [], errors: [], pending: [] };
    const list = workList();
    const K = settings.batch;
    const movedAll = [];
    let i = 0; let badChunks = 0;
    const push = (arr, x) => arr.push({ name: label(x.c), reason: x.why });
    try {
      for (; i < list.length; i += K) {
        if (S.stop) break;
        const chunk = list.slice(i, i + K);
        setStatus((settings.dryRun ? '[DENEME] ' : '') + 'Grup ' + (Math.floor(i / K) + 1) + '/' + Math.ceil(list.length / K) + ' (' + chunk.length + ' servis)');
        let r;
        try { r = await processChunk(chunk); } catch (e) { r = { moved: [], dry: [], skipped: [], errors: chunk.map((c) => ({ c, why: 'beklenmeyen hata: ' + (e && e.message) })), fatal: false }; }
        r.dry.forEach((x) => push(S.res.dry, x));
        r.skipped.forEach((x) => push(S.res.skipped, x));
        r.errors.forEach((x) => push(S.res.errors, x));
        movedAll.push(...r.moved);
        r.moved.forEach((x) => push(S.res.moved, x));
        render();
        badChunks = r.errors.length ? badChunks + 1 : 0;
        if (r.fatal || badChunks >= 2) { S.notes.push(r.fatal ? 'Seçim/pencere kapatılamadı: işlem durduruldu.' : 'Üst üste 2 grupta hata: işlem durduruldu.'); S.diagNeeded = true; i += K; break; }
      }
      S.res.pending = list.slice(i).map(label);
      if (movedAll.length && !S.stop) {
        const v = await verify(movedAll);
        S.res.moved = v.ok.map((x) => ({ name: label(x.c), reason: x.why + ' ✓' }));
        v.bad.forEach((x) => push(S.res.errors, x));
      } else if (movedAll.length) {
        S.notes.push('Durduruldu: taşınanlar doğrulanmadı (Bul ile tekrar tarayabilirsin).');
      }
      const r = S.res;
      const bits = [r.moved.length + ' taşındı', r.dry.length + ' deneme', r.skipped.length + ' atlandı', r.errors.length + ' hata'];
      if (r.pending.length) bits.push(r.pending.length + ' işlenmedi');
      S.phase = 'idle'; S.all = [];
      setStatus((S.stop ? 'Durduruldu. ' : 'Bitti. ') + bits.join(', ') + '.');
    } catch (e) {
      fail('Hata: ' + (e && e.message));
    } finally {
      S.running = false; S.stop = false; busy(false); render();
    }
  }
  function stop() { if (!S.running) return; S.stop = true; setStatus('Durdurma istendi; sıradaki adımdan önce duracak…'); }

  // ---------------------------------------------------------------- teşhis
  function diag() {
    const strip = (h) => h.replace(/\s+/g, ' ').replace(/<svg[\s\S]*?<\/svg>/g, '<svg/>');
    const s = selection();
    return JSON.stringify({
      surum: VERSION, adres: location.pathname, durum: S.status, istatistik: S.stats, notlar: S.notes, sonuc: S.res,
      secim: s ? { n: s.n, actions: !!s.btn, kutucuk: !!s.cb } : null,
      sonBasliklar: catHeaders().slice(-4).map((h) => ({ ad: h.name.slice(0, 50), aktif: h.active, na: h.na, sayi: h.count, kapali: h.collapsed })),
      sonServisler: serviceRows().slice(-5).map((r) => ({ id: r.sid, ad: r.name.slice(0, 50), durum: r.state, yazi: r.st, sinifPasif: r.clsOff, kutucuk: !!r.cb, html: strip(r.row.outerHTML).slice(0, 400) })),
      menuYazilari: S.seen.menu, pencereYazilari: S.seen.dialog, bildirimler: S.seen.toast, adimGunlugu: S.log.slice(-40),
    }, null, 1);
  }
  async function copyText(text) {
    try { await navigator.clipboard.writeText(text); return true; } catch (e) { /* eski yol */ }
    try {
      const ta = document.createElement('textarea');
      ta.value = text; ta.style.cssText = 'position:fixed;left:-9999px;top:0';
      document.body.appendChild(ta); ta.select();
      const ok = document.execCommand('copy');
      ta.remove();
      return ok;
    } catch (e) { return false; }
  }

  // ---------------------------------------------------------------- arayüz (shadow DOM)
  let host = null; let root = null; let minimized = false;
  try { minimized = localStorage.getItem('pt_min') === '1'; } catch (e) { /* yok */ }
  const CSS = `
    :host{all:initial}
    .bar{position:fixed;bottom:12px;z-index:2147483646;width:310px;font:12px/1.4 system-ui,sans-serif;color:#f2ece4;background:#2e2418;border:1px solid #6a5134;border-radius:10px;box-shadow:0 6px 20px rgba(0,0,0,.45)}
    .bar.min{width:auto}.bar.min .body{display:none}
    .hd{display:flex;align-items:center;gap:6px;padding:6px 8px}.hd b{flex:1}
    .hd button{all:unset;cursor:pointer;padding:0 6px;border-radius:4px;color:#d6c7b2}.hd button:hover{background:#45362a}
    .body{padding:0 8px 8px;display:flex;flex-direction:column;gap:6px}
    .row{display:flex;gap:6px;flex-wrap:wrap;align-items:center}
    button.b{all:unset;cursor:pointer;padding:5px 9px;border-radius:6px;background:#5a4128;color:#fff}
    button.b:hover{background:#73542f}button.b[disabled]{opacity:.4;cursor:default}
    button.red{background:#9b2c3a}button.red:hover{background:#b73646}
    .st{white-space:pre-wrap;color:#e6dccd}.warn{color:#ffb4a8}
    .list{max-height:150px;overflow:auto;background:#1c160f;border-radius:6px;padding:4px 6px}
    .list > div{padding:1px 0;border-bottom:1px solid #3a2e22}
    .it{display:flex;gap:4px;align-items:center;padding:1px 0;border-bottom:1px solid #3a2e22}.it span{flex:1}
    .lk{all:unset;cursor:pointer;color:#ffd08f;font-size:11px;margin-left:6px}
    label{display:flex;gap:4px;align-items:center}
    input[type=number]{width:56px;background:#1c160f;color:#fff;border:1px solid #6a5134;border-radius:4px;padding:2px 4px}
    small{color:#c2b29b}
  `;
  const esc = (s) => String(s).replace(/[&<>"]/g, (c) => ({ '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;' }[c]));
  function ensureBar() {
    if (!onServicesList()) { if (host) { host.remove(); host = null; root = null; } return; }
    if (host && host.isConnected) { placeBar(); return; }
    host = document.createElement('div');
    host.id = 'pt-host';
    root = host.attachShadow({ mode: 'open' });
    root.innerHTML = '<style>' + CSS + '</style><div class="bar" id="bar"></div>';
    root.addEventListener('click', onBarClick);
    root.addEventListener('change', onBarChange);
    root.addEventListener('focusout', () => setTimeout(render, 0));
    document.documentElement.appendChild(host);
    render();
  }
  /** Diğer eklentilerin çubukları da varsa yanlarına otur. */
  function placeBar() {
    const b = root && root.getElementById('bar');
    if (!b) return;
    const k = ['kt-host', 'sp-host'].filter((id) => document.getElementById(id)).length;
    b.style.left = (12 + 304 * k) + 'px';
  }
  let pressed = false; let wantRender = false;
  document.addEventListener('pointerdown', (e) => { if (host && e.composedPath().includes(host)) pressed = true; }, true);
  document.addEventListener('pointerup', () => { if (!pressed) return; pressed = false; setTimeout(() => { if (wantRender) render(); }, 0); }, true);

  function render() {
    if (!root) return;
    if (pressed) { wantRender = true; return; }
    wantRender = false;
    const bar = root.getElementById('bar');
    if (!bar) return;
    const focused = root.activeElement;
    if (focused && focused.tagName === 'INPUT' && focused.type === 'number') { const st = bar.querySelector('#st'); if (st) st.textContent = S.status; return; }
    const busyNow = S.running; const r = S.res;
    const shown = S.all.filter((x) => x.catActive || settings.includePasif);
    const work = S.phase === 'ready' ? workList().length : 0;
    const p = [];
    p.push('<div class="hd"><b>⇄ Pasif Servis Taşı' + (settings.dryRun ? ' <small>(deneme)</small>' : '') + '</b><button data-a="min" title="Küçült/Büyüt">' + (minimized ? '▢' : '–') + '</button></div><div class="body">');
    p.push('<div class="row"><button class="b" data-a="scan"' + (busyNow ? ' disabled' : '') + '>Pasif servisleri bul</button></div>');
    p.push('<div class="row"><label><input type="checkbox" data-s="includePasif"' + (settings.includePasif ? ' checked' : '') + (busyNow ? ' disabled' : '') + '> Pasif kategorilerdekileri de dahil et</label></div>');
    if (S.stats) p.push('<small class="sum">' + S.stats.kategori + ' kategori · ' + S.stats.servis + ' servis · <b>' + S.stats.pasifAktifKat + ' pasif servis aktif kategorilerde</b> · ' + S.stats.pasifPasifKat + ' pasif kategorilerde</small>');
    if (S.phase === 'ready' && shown.length) {
      p.push('<div class="row"><small>Not Assigned\'a taşınacak (işareti kaldırılan atlanır):</small><button class="lk" data-a="all">hepsi</button><button class="lk" data-a="none">hiçbiri</button></div>');
      p.push('<div class="list" id="ptList">' + shown.map((x) => '<label class="it"><input type="checkbox" data-x="' + esc(x.sid) + '"' + (S.excluded.has(x.sid) ? '' : ' checked') + '> <span>' + esc(label(x)) + ' <small>· ' + esc(x.cat.slice(0, 40)) + (x.catActive ? '' : ' (pasif kat.)') + '</small></span></label>').join('') + '</div>');
    }
    p.push('<div class="row"><button class="b red" data-a="run"' + (busyNow || S.phase !== 'ready' || !work ? ' disabled' : '') + '>' + (settings.dryRun ? 'Dene' : 'Taşı') + ' (' + work + ')</button><button class="b" data-a="stop"' + (busyNow ? '' : ' disabled') + '>Durdur</button></div>');
    p.push('<div class="row"><label><input type="checkbox" data-s="dry"' + (settings.dryRun ? ' checked' : '') + '> Deneme modu</label><label>bir seferde <input type="number" min="1" max="500" data-s="batch" value="' + settings.batch + '"></label><label>en çok <input type="number" min="1" max="10000" data-s="max" value="' + settings.max + '"></label></div>');
    p.push('<div class="st' + (/^⚠/.test(S.status) ? ' warn' : '') + '" id="st">' + esc(S.status) + '</div>');
    if (r.moved.length || r.dry.length || r.skipped.length || r.errors.length) {
      const sec = (a, t) => a.length ? '<div><b>' + t + ' (' + a.length + ')</b></div>' + a.map((x) => '<div>' + esc(x.name) + ' <small>' + esc(x.reason || '') + '</small></div>').join('') : '';
      p.push('<div class="list" id="ptRes">' + sec(r.moved, 'Taşınan') + sec(r.dry, 'Deneme') + sec(r.skipped, 'Atlanan') + sec(r.errors, 'Hata') + '</div>');
    }
    if (S.notes.length) p.push('<small>' + S.notes.map(esc).join('<br>') + '</small>');
    p.push('<div class="row"><button class="b" data-a="diag">Teşhisi kopyala</button></div></div>');
    bar.className = 'bar' + (minimized ? ' min' : '');
    bar.innerHTML = p.join('');
    placeBar();
  }
  function setAll(off) { for (const x of S.all) if (x.catActive || settings.includePasif) { if (off) S.excluded.add(x.sid); else S.excluded.delete(x.sid); } }
  async function onBarClick(ev) {
    const t = ev.target.closest && ev.target.closest('[data-a]');
    if (!t || t.disabled || t.hasAttribute('disabled')) return;
    const a = t.getAttribute('data-a');
    if (a === 'min') { minimized = !minimized; try { localStorage.setItem('pt_min', minimized ? '1' : '0'); } catch (e) { /* yok */ } render(); }
    else if (a === 'scan') scan();
    else if (a === 'run') run();
    else if (a === 'stop') stop();
    else if (a === 'all' || a === 'none') { setAll(a === 'none'); render(); }
    else if (a === 'diag') { const ok = await copyText(diag()); S.status = ok ? 'Teşhis panoya kopyalandı; sohbete yapıştır.' : '⚠ Panoya kopyalanamadı.'; render(); }
  }
  function onBarChange(ev) {
    const t = ev.target;
    const s = t.getAttribute && t.getAttribute('data-s');
    if (s === 'dry') patchSettings({ dryRun: t.checked });
    if (s === 'max') patchSettings({ max: t.value });
    if (s === 'batch') patchSettings({ batch: t.value });
    if (s === 'includePasif') { patchSettings({ includePasif: t.checked }); if (S.phase === 'ready') S.status = workList().length ? workList().length + ' pasif servis taşınmaya hazır. Henüz hiçbir şey değişmedi.' : 'Taşınacak pasif servis yok.'; }
    const x = t.getAttribute && t.getAttribute('data-x');
    if (x) { if (t.checked) S.excluded.delete(x); else S.excluded.add(x); }
    render();
  }

  // ---------------------------------------------------------------- mini panel ile iletişim
  function publicState() {
    const shown = S.all.filter((x) => x.catActive || settings.includePasif);
    return {
      version: VERSION, onPage: onServicesList(), phase: S.phase, running: S.running, status: S.status, stats: S.stats, notes: S.notes, res: S.res,
      items: shown.map((x) => ({ key: x.sid, name: label(x), cat: x.cat, catActive: x.catActive, off: S.excluded.has(x.sid) })),
      work: S.phase === 'ready' ? workList().length : 0, settings: Object.assign({}, settings),
    };
  }
  try {
    chrome.runtime.onMessage.addListener((msg, _sender, respond) => {
      if (!msg || msg.pt !== true) return false;
      const done = () => respond(publicState());
      switch (msg.cmd) {
        case 'scan': scan(); setTimeout(done, 50); return true;
        case 'run': run(); setTimeout(done, 50); return true;
        case 'stop': stop(); done(); return false;
        case 'settings': patchSettings(msg.patch); render(); done(); return false;
        case 'exclude': if (msg.all !== undefined) setAll(!!msg.all); else if (msg.on) S.excluded.add(msg.key); else S.excluded.delete(msg.key); render(); done(); return false;
        case 'diag': respond({ text: diag() }); return false;
        default: done(); return false;
      }
    });
  } catch (e) { /* eklenti dışı ortam */ }

  let throttle = null;
  new MutationObserver(() => {
    if (throttle) return;
    throttle = setTimeout(() => { throttle = null; ensureBar(); }, 800);
  }).observe(document.documentElement, { childList: true, subtree: true });
  ensureBar();
})();
