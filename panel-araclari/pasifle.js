// OTOMATİK ÜRETİLDİ — düzenleme: kaynak servis-pasifle/content.js (build-panel-araclari.py kopyalar).
// Servis Pasifle — Perfect Panel > Services: "Not Assigned" kategorisindeki AKTİF servisleri tek tek pasife alır.
// Akış: Bul → kategori görünümü → listeyi sona kadar gez → Not Assigned başlığını bul ve aç → altındaki servisleri oku →
//   aktif olanları listele. Pasife al → her servis: satırı kimliğinden (service-NNNN) bul → hâlâ Not Assigned'da ve
//   "Enabled" mı? → Actions → yalnızca tam "Disable service" → (onay penceresi çıkarsa onayla) → "Disabled" oldu mu?
// "Delete service", "Enable service" gibi diğer menü öğelerine asla basılmaz. Deneme modu: menüyü açar, kapatır.
// React yeniden çizdiği için öğe referansı saklanmaz; liste sanallaştırılmış olduğu için satırlar kaydırarak aranır.
(() => {
  if (window.__servisPasifle) return;
  window.__servisPasifle = true;

  const VERSION = '1.3.0';
  const SETTINGS_KEY = 'sp_settings';
  const DIALOG = '[role=dialog], .modal, .modal-dialog, .modal-content';
  const TOGGLE = '.service-table__header-hide-all, [class*="header-hide-all"]';
  const ERR_WORDS = /error|fail|hata|cannot|can't|couldn't|unable|denied|not allowed|invalid|xəta|alınmadı|olmadı/i;

  // ---------------------------------------------------------------- yardımcılar
  const norm = (s) => (s || '').replace(/\s+/g, ' ').trim();
  const lower = (s) => norm(s).toLowerCase().replace(/i̇/g, 'i');
  // Arka plan sekmesi: sayfanın zamanlayıcıları kısılır. Her bekleme hem burada hem service worker'da başlar; önce biten sayılır.
  let port = null; let seq = 0;
  const waiters = new Map();
  function getPort() {
    if (port) return port;
    try {
      port = chrome.runtime.connect({ name: 'sp' });
      port.onMessage.addListener((m) => {
        if (m && m.t === 'autoInfo') { S.autoInfo = m.info; render(); return; }
        const f = m && m.t === 'wake' && waiters.get(m.id); if (f) f();
      });
      port.onDisconnect.addListener(() => { void chrome.runtime.lastError; port = null; });
    } catch (e) { port = null; }
    return port;
  }
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

  async function waitFor(fn, timeout = 8000, step = 80) {
    const t0 = Date.now();
    for (;;) {
      const v = fn();
      if (v) return v;
      if (Date.now() - t0 > timeout) return null;
      await sleep(step);
    }
  }

  /** Görünen yazı (gizli menüler hariç; blok çocuklar arasına boşluk). */
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

  // ---------------------------------------------------------------- araçlar arası kilit
  // Aynı sayfada birden çok araç (Kategori Temizle / Servis Pasifle / Pasif Servis Taşı) aynı anda listeyi kullanmasın:
  // çalışan araç <html data-panel-arac="…"> yazar. Sayfa yenilenince kendiliğinden temizlenir.
  const ME = 'sp'; const LOCK = 'data-panel-arac';
  const LOCK_NAME = { kt: 'Kategori Temizle', sp: 'Servis Pasifle', pt: 'Pasif Servis Taşı' };
  const lockHolder = () => document.documentElement.getAttribute(LOCK) || '';
  const lockFree = () => { const h = lockHolder(); return !h || h === ME; };
  function lockTake() { if (!lockFree()) return false; document.documentElement.setAttribute(LOCK, ME); return true; }
  function lockDrop() { if (lockHolder() === ME) document.documentElement.removeAttribute(LOCK); }
  /** Elle başlatılan iş: başka araç çalışıyorsa başlamaz; bitince kilidi bırakır (otomatik tur kilidi kendisi tutar). */
  const guarded = (fn) => async (...args) => {
    if (autoBusy) return undefined;
    if (!lockTake()) { setStatus('⚠ Şu an "' + (LOCK_NAME[lockHolder()] || lockHolder()) + '" çalışıyor: önce o bitsin.'); return undefined; }
    try { return await fn(...args); } finally { if (!S.running) lockDrop(); }
  };

  // ---------------------------------------------------------------- durum
  const S = {
    phase: 'idle', // idle | scanning | ready | running
    status: 'Hazır. "Not Assigned\'daki aktif servisleri bul"a bas.',
    running: false,
    stop: false,
    targets: [], // [{sid, name, off}]
    excluded: new Set(),
    stats: null,
    notes: [],
    res: { done: [], dry: [], skipped: [], errors: [], pending: [] },
    naOff: null, // Not Assigned başlığının liste içi konumu (geçerli çizimde)
    autoInfo: null, // service worker'dan: { next, every }
    diagNeeded: false,
    log: [],
    seen: { menu: [], dialog: [], toast: [] },
  };
  // Güvenlik: deneme modu kalıcı değil; her sayfa yüklenişinde AÇIK başlar. "En çok N" hatırlanır.
  // method: 'mass' = kutucukları işaretle → toplu Actions → Disable all · 'single' = her servis için Actions → Disable service
  const settings = { dryRun: true, max: 100, every: 5, method: 'mass' };
  try { const v = JSON.parse(localStorage.getItem(SETTINGS_KEY) || '{}'); if (v.max) settings.max = Math.max(1, Math.min(5000, parseInt(v.max, 10) || 100)); if (v.every) settings.every = Math.max(1, Math.min(120, parseInt(v.every, 10) || 5)); if (v.method === 'mass' || v.method === 'single') settings.method = v.method; } catch (e) { /* yok */ }
  const saveSettings = () => { try { localStorage.setItem(SETTINGS_KEY, JSON.stringify({ max: settings.max, every: settings.every, method: settings.method })); } catch (e) { /* yok */ } };
  function patchSettings(p) {
    if (p && typeof p.dryRun === 'boolean') { settings.dryRun = p.dryRun; const a = autoGet(); if (a && a.on) { a.real = !p.dryRun; autoSet(a); } }
    if (p && p.max !== undefined) settings.max = Math.max(1, Math.min(5000, parseInt(p.max, 10) || 100));
    if (p && (p.method === 'mass' || p.method === 'single') && !S.running) settings.method = p.method;
    saveSettings();
  }
  function log(msg) { S.log.push(new Date().toTimeString().slice(0, 8) + ' ' + msg); if (S.log.length > 300) S.log.shift(); }
  function setStatus(t) { S.status = t; log(t); render(); }
  /** Biten işi geçmişe yaz (mini panel → Geçmiş) ve gerçek değişiklik/hata varsa arka plana bildir (masaüstü bildirimi). */
  function recordRun(text) {
    try {
      const r = S.res || {};
      const doneArr = [].concat(r.done || [], r.deleted || [], r.moved || []);
      const entry = { tool: ME, at: Date.now(), dry: !!settings.dryRun, auto: !!autoBusy, text, done: doneArr.length, errors: (r.errors || []).length,
        items: doneArr.slice(0, 40).map((x) => (x && x.name) || String(x)), errorItems: (r.errors || []).slice(0, 10).map((x) => ((x && x.name) || '') + (x && x.reason ? ' — ' + x.reason : '')) };
      chrome.storage.local.get({ pa_history: [] }, (o) => { void chrome.runtime.lastError; chrome.storage.local.set({ pa_history: [entry].concat((o && o.pa_history) || []).slice(0, 150) }); });
      const p = getPort();
      if (p && !entry.dry && (entry.done || entry.errors)) p.postMessage({ t: 'notify', entry });
    } catch (e) { /* depolama yok (eski kurulum) */ }
  }
  const remember = (kind, arr) => {
    for (const t of arr) if (t && !S.seen[kind].includes(t)) S.seen[kind].push(t);
    S.seen[kind] = S.seen[kind].slice(-12);
  };

  // ---------------------------------------------------------------- sayfa okuma
  /** Kategori başlıkları (div.service-table__category#category-NNN); Not Assigned işaretli. */
  function catHeaders() {
    const list = [...document.querySelectorAll('.service-table__category, [id^="category-"]')].filter(visible).map((el) => {
      const ne = el.querySelector('[class*="category-name"]');
      const name = cleanName(textWithAlt(ne || el));
      return { el, name, na: isNA(name) };
    });
    if (!list.some((h) => h.na)) {
      // Not Assigned başlığı farklı işaretlenmiş olabilir: yazısı tam "Not Assigned" olan yaprak öğe
      const box = getBox();
      const scope = box === document.scrollingElement ? document.body : box;
      const leaf = [...scope.querySelectorAll('span, div, td, b, strong, a, p')].find((e) => e.children.length === 0 && visible(e) && isNA(e.textContent)
        && !e.closest('.service-table__row-service, .service-table__body, .dropdown-menu, [role=menu], [role=dialog], .modal'));
      if (leaf) list.push({ el: leaf.closest('.service-table__category, [style*="position: absolute"], [class*="category"]') || leaf.parentElement, name: 'Not Assigned', na: true });
    }
    return list;
  }

  /** Servis satırları: .service-table__body#service-NNNN > .service-table__row-service[.service-table__row-disabled]. */
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
      // Durum: Status sütunundaki "Enabled"/"Disabled" yazısı + satırın service-table__row-disabled sınıfı; ikisi uyuşmalı.
      const leaf = [...row.querySelectorAll('*')].find((e) => e.children.length === 0 && /^(enabled|disabled)$/i.test(norm(e.textContent)) && !e.closest('.dropdown-menu, [role=menu]'));
      const st = leaf ? lower(leaf.textContent) : null;
      const clsOff = /(^|\s)service-table__row-disabled(\s|$)/.test(typeof row.className === 'string' ? row.className : '');
      let state = 'unknown';
      if (st === 'enabled' && !clsOff) state = 'on';
      else if (st === 'disabled' || (clsOff && st !== 'enabled')) state = 'off';
      else if (!st && !clsOff) state = 'unknown';
      const btn = [...row.querySelectorAll('button, a')].find((x) => /^actions\b/i.test(btnText(x)));
      const cb = row.querySelector('.service-table__checker input[type=checkbox]') || row.querySelector('input[type=checkbox]');
      return { el: b, row, sid, name, state, st, clsOff, btn, cb };
    }).filter((r) => r.sid);
  }
  const findSvc = (sid) => serviceRows().find((r) => r.sid === sid) || null;

  /** Listenin kaydırma kutusu (iç kutu ya da pencere). */
  function getBox() {
    const r = document.querySelector('.service-table__category, .service-table__body, .service-table__row-service');
    for (let el = r && r.parentElement; el && el !== document.body; el = el.parentElement) {
      if (/(auto|scroll)/.test(getComputedStyle(el).overflowY) && el.scrollHeight > el.clientHeight + 40) return el;
    }
    const c = document.querySelector('.service-table__container');
    if (c && c.scrollHeight > c.clientHeight + 40) return c;
    return document.scrollingElement;
  }
  /** Öğenin liste içindeki konumu (kaydırmadan bağımsız). */
  function offsetOf(el) {
    const box = getBox();
    const bt = box === document.scrollingElement ? 0 : box.getBoundingClientRect().top;
    return Math.round(el.getBoundingClientRect().top - bt + box.scrollTop);
  }
  /** Kaydır ve scroll olayını hemen tetikle (arka plan sekmesinde Chrome geciktirir; sanallaştırılmış liste bu olayla çizer). */
  function setScroll(box, top) {
    box.scrollTop = top;
    try { (box === document.scrollingElement ? window : box).dispatchEvent(new Event('scroll')); } catch (e) { /* yok */ }
  }
  function nudgeScroll() { const b = getBox(); try { (b === document.scrollingElement ? window : b).dispatchEvent(new Event('scroll')); } catch (e) { /* yok */ } }

  /** Listeyi gez (from: başlangıç konumu; yoksa en üstten). onStep() true dönerse durur. */
  async function walk(onStep, from) {
    const box = getBox();
    setScroll(box, Math.max(0, from || 0));
    await sleep(350);
    if (await onStep()) return true;
    let stable = 0;
    for (let i = 0; i < 5000 && !S.stop; i++) {
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

  /** Tüm kategorileri kapat ("yalnızca kategoriler"): Not Assigned'ı hızlı bulmak için. */
  async function ensureCategoryView() {
    if (!serviceRows().length) return true;
    const t = [...document.querySelectorAll(TOGGLE)].find(visible);
    const globalOpen = t && /fa-compress/.test(typeof t.className === 'string' ? t.className : '');
    if (!globalOpen) {
      const opened = [...document.querySelectorAll('[class*="category-collapse"].show-category')].filter(visible);
      for (const o of opened) (o.querySelector('[class*="collapse-name"], a') || o).click();
      if (opened.length && await waitFor(() => !serviceRows().length, 3000, 100)) return true;
    }
    if (!t || !globalOpen) return !serviceRows().length; // "tümü" kapalıyken düğmeye basmak her şeyi AÇAR: basma
    t.click();
    return !!(await waitFor(() => !serviceRows().length && catHeaders().length > 0, 6000, 100));
  }

  const naHead = () => { const h = catHeaders().find((x) => x.na); if (h) S.naOff = offsetOf(h.el); return h || null; };
  const naCount = (el) => { const m = norm(el.textContent).match(/(?:expand|collapse)\s*\(\s*(\d+)\s*\)/i); return m ? parseInt(m[1], 10) : null; };
  /** Not Assigned başlığının hemen altında servis satırı var mı (açık mı)? */
  const naOpen = () => { if (!naHead()) return false; return serviceRows().some((r) => { const d = offsetOf(r.el) - S.naOff; return d > 0 && d < 400; }); };

  /** Not Assigned başlığını bul ve servislerini aç (önce kendi Expand'i, olmazsa tümünü aç). */
  async function openNA() {
    let h = naHead();
    if (!h && S.naOff != null) { // bilinen konum: oraya kaydır
      setScroll(getBox(), Math.max(0, S.naOff - 60)); await sleep(350);
      h = await waitFor(naHead, 2000, 100);
    }
    if (!h) { await walk(() => !!naHead()); h = naHead(); } // hiçbir düğmeye basmadan baştan gez
    if (!h && await ensureCategoryView()) { await walk(() => !!naHead()); h = naHead(); }
    if (!h) return { error: '"Not Assigned" başlığı bulunamadı (listenin sonuna kadar bakıldı)' };
    const count = naCount(h.el);
    h.el.scrollIntoView({ block: 'start' }); nudgeScroll(); await sleep(250);
    if (!naOpen()) {
      const hh = naHead();
      const ce = hh && hh.el.querySelector('[class*="category-collapse"]');
      if (ce && !/show-category/.test(ce.className) && /expand/i.test(norm(ce.textContent))) {
        (ce.querySelector('[class*="collapse-name"]') || ce).click();
        await waitFor(naOpen, 5000, 100);
      }
    }
    if (!naOpen()) {
      const t = [...document.querySelectorAll(TOGGLE)].find(visible);
      if (t && !/fa-compress/.test(typeof t.className === 'string' ? t.className : '')) { t.click(); await waitFor(() => serviceRows().length > 0, 6000, 100); }
      if (!naHead()) await walk(() => !!naHead());
      const hh = naHead();
      if (hh) { hh.el.scrollIntoView({ block: 'start' }); nudgeScroll(); await sleep(250); }
      await waitFor(naOpen, 3000, 100);
    }
    if (!naOpen()) return { error: count === 0 ? 'Not Assigned boş' : 'Not Assigned servisleri açılamadı' };
    return { count };
  }

  /** Şu an: servis hâlâ Not Assigned'da mı (üstündeki en yakın başlık), hâlâ aktif mi? */
  function checkSvc(sid) {
    const r = findSvc(sid);
    if (!r) return { ok: false, reason: 'satır bulunamadı' };
    const off = offsetOf(r.el);
    const above = catHeaders().map((h) => ({ h, o: offsetOf(h.el) })).filter((x) => x.o < off).sort((a, b) => b.o - a.o)[0];
    if (above && !above.h.na) return { ok: false, reason: 'başka kategoride: ' + above.h.name.slice(0, 50) };
    if (!above) {
      naHead();
      if (S.naOff == null || off <= S.naOff) return { ok: false, reason: 'Not Assigned üyeliği doğrulanamadı' };
    }
    if (r.state === 'off') return { ok: false, reason: 'zaten pasif' };
    if (r.state !== 'on') return { ok: false, reason: 'durumu okunamadı ("' + (r.st || '-') + '", sınıf ' + (r.clsOff ? 'pasif' : 'aktif') + ')' };
    return { ok: true, r };
  }

  // ---------------------------------------------------------------- menü / pencere
  function itemsByText(re) {
    const all = [...document.querySelectorAll('a, button, li, span, div, p, [role=menuitem]')].filter((e) => visible(e) && re.test(norm(e.textContent)));
    return all.filter((e) => !all.some((o) => o !== e && e.contains(o)));
  }
  const MENU_ANY = /^(enable all|disable all|edit rates|assign category|delete all|hide all|make all visible|edit service|edit description|disable service|enable service|delete custom rates|duplicate|hide service|delete service|edit category)$/i;
  const click = (el) => (el.closest('a, button, li, [role=menuitem]') || el).click();
  function pressEscape() {
    for (const t of [document.activeElement, document.body, document]) {
      if (t) t.dispatchEvent(new KeyboardEvent('keydown', { key: 'Escape', code: 'Escape', keyCode: 27, bubbles: true }));
    }
  }
  async function closeMenu() {
    if (!itemsByText(MENU_ANY).length) return;
    pressEscape();
    await sleep(150);
    if (itemsByText(MENU_ANY).length) document.body.click();
    await sleep(150);
  }
  const anyDialog = () => [...document.querySelectorAll(DIALOG)].some(visible);
  function readToasts() {
    const sel = '[role=alert], [role=status], [class*=toast], [class*=Toast], [class*=notif], [class*=snack], [class*=alert], [class*=flash]';
    return [...document.querySelectorAll(sel)].filter(visible).map((e) => norm(e.textContent)).filter((t) => t && t.length < 300);
  }

  /** Servisin Actions menüsü: "Disable service" (aktif) ya da "Enable service" (zaten pasif) öğesini bul. */
  const STATUS_RE = /^(disable|enable)\s+service$/i;
  /** Satırın kendi Actions menüsündeki "Disable/Enable service" öğesi (gerçek panelde menü kapalıyken de satırın içinde durur). */
  function rowItem(sid) {
    const r = findSvc(sid);
    if (!r) return null;
    const all = [...r.row.querySelectorAll('a, button, li, [role=menuitem]')].filter((e) => STATUS_RE.test(norm(e.textContent)));
    const deep = all.filter((e) => !all.some((o) => o !== e && e.contains(o)));
    return { r, item: deep[0] || null };
  }
  /**
   * Actions'a bas, menünün açılmasını bekle. Açılmasa da (liste kaydı / satır yeniden çizildi) satırın içindeki öğe kullanılır;
   * satır dışında (portal) yeni çıkan öğe de kabul edilir. Sonuç her zaman "Disabled" olmasıyla doğrulanır.
   */
  async function openSvcMenu(sid) {
    const r0 = findSvc(sid);
    if (!r0 || !r0.btn) return { item: null, status: null };
    const before = new Set(itemsByText(STATUS_RE));
    r0.btn.click();
    const vis = await waitFor(() => {
      const x = rowItem(sid);
      if (x && x.item && visible(x.item)) return x;
      const g = itemsByText(STATUS_RE).filter((e) => !before.has(e) && !e.closest('.service-table__body, .service-table__row-service'));
      return g.length === 1 ? { r: x && x.r, item: g[0], portal: true } : null;
    }, 2000, 80);
    const x = vis || rowItem(sid);
    if (!x || !x.item) return { item: null, status: null };
    remember('menu', [norm((x.item.closest('ul, [role=menu], .dropdown-menu') || x.item.parentElement).textContent).slice(0, 200) + (vis ? '' : ' [menü görünmedi; satırdaki öğe]')]);
    const status = lower(x.item.textContent).split(' ')[0];
    return { item: status === 'disable' ? x.item : null, status, opened: !!vis, portal: !!(vis && vis.portal) };
  }

  /** "Disable service" sonrası onay penceresi (varsa): Disable/Yes/Confirm/OK; içinde "Delete" geçen pencereye dokunulmaz. */
  function findConfirm() {
    for (const d of [...document.querySelectorAll(DIALOG)].filter(visible)) {
      const t = norm(d.innerText || d.textContent);
      const ok = [...d.querySelectorAll('button, a, input[type=submit]')].find((b) => visible(b) && /^(disable|disable service|yes|confirm|ok)$/i.test(norm(b.value || btnText(b))));
      const cancel = [...d.querySelectorAll('button, a')].find((b) => visible(b) && /^(cancel|close|no)$/i.test(btnText(b))) || d.querySelector('.close, [aria-label=close], [aria-label=Close]');
      return { dlg: d, text: t, ok, cancel, safe: /disable/i.test(t) && !/delete/i.test(t) };
    }
    return null;
  }
  async function closeConfirm() {
    const c = findConfirm();
    if (!c) return true;
    if (c.cancel) c.cancel.click(); else pressEscape();
    return !!(await waitFor(() => !findConfirm(), 4000, 100));
  }

  // ---------------------------------------------------------------- tarama
  function fail(msg) { S.phase = 'idle'; S.diagNeeded = true; setStatus('⚠ ' + msg); }

  async function scan() {
    if (S.running) return;
    S.running = true; S.stop = false; S.phase = 'scanning'; busy(true);
    S.targets = []; S.excluded = new Set(); S.stats = null; S.notes = []; S.diagNeeded = false;
    S.res = { done: [], dry: [], skipped: [], errors: [], pending: [] };
    try {
      if (!onServicesList()) return fail('Bu özellik yalnızca /admin/services sayfasında çalışır.');
      if (anyDialog()) return fail('Açık bir pencere var; önce kapat.');
      setStatus('Not Assigned aranıyor…');
      const o = await openNA();
      if (S.stop) { S.phase = 'idle'; return setStatus('Durduruldu.'); }
      if (o.error) return fail(o.error);
      setStatus('Not Assigned servisleri okunuyor…');
      const col = new Map(); const heads = new Set(); let foreign = 0;
      const collect = () => {
        const hs = catHeaders().map((h) => ({ h, o: offsetOf(h.el) }));
        const na = hs.find((x) => x.h.na);
        if (na) S.naOff = na.o;
        for (const x of hs) if (!x.h.na && S.naOff != null && x.o > S.naOff) heads.add(x.o);
        for (const r of serviceRows()) {
          const off = offsetOf(r.el);
          if (S.naOff == null || off <= S.naOff) continue;
          if ([...heads].some((h) => h < off)) { foreign++; continue; } // Not Assigned'dan sonra başka kategori başladıysa
          if (!col.has(r.sid)) col.set(r.sid, { sid: r.sid, name: r.name, state: r.state, off });
        }
        S.status = 'Okunuyor… ' + col.size + ' servis'; render();
        return false;
      };
      await walk(collect, Math.max(0, (S.naOff || 0) - 60));
      if (S.stop) { S.phase = 'idle'; return setStatus('Durduruldu; liste eksik olabilir, tarama iptal.'); }
      const list = [...col.values()].sort((a, b) => a.off - b.off);
      const on = list.filter((x) => x.state === 'on');
      const off = list.filter((x) => x.state === 'off');
      const unk = list.filter((x) => x.state === 'unknown');
      S.targets = on;
      S.stats = { naSayi: o.count, okunan: list.length, aktif: on.length, pasif: off.length, belirsiz: unk.length };
      if (o.count != null && list.length !== o.count) S.notes.push('Not Assigned "Expand (' + o.count + ')" diyor, ' + list.length + ' servis okundu.');
      if (unk.length) S.notes.push(unk.length + ' servisin durumu okunamadı; bunlara dokunulmaz.');
      if (foreign) S.notes.push('Not Assigned\'dan sonra başka kategori satırları görüldü; onlara dokunulmaz.');
      setScroll(getBox(), Math.max(0, (S.naOff || 0) - 60));
      S.phase = 'ready';
      setStatus(on.length ? on.length + ' aktif servis bulundu (Not Assigned). Henüz hiçbir şey değişmedi.' : 'Not Assigned\'da aktif servis yok.');
    } catch (e) {
      log(String(e && e.stack).slice(0, 400));
      fail('Tarama hatası: ' + (e && e.message));
    } finally {
      S.running = false; busy(false);
      render();
    }
  }

  // ---------------------------------------------------------------- pasife alma
  const result = (kind, reason, extra) => Object.assign({ kind, reason }, extra || {});

  async function locateSvc(sid, off) {
    let r = findSvc(sid);
    if (r) return r;
    const box = getBox();
    if (off != null) { // taramadaki konum (yalnızca durum değiştiği için düzen aynı kalır)
      setScroll(box, Math.max(0, off - Math.floor(box.clientHeight / 2))); await sleep(350);
      r = await waitFor(() => findSvc(sid), 1500, 100);
      if (r) return r;
    }
    if (S.naOff != null) { await walk(() => !!findSvc(sid), Math.max(0, S.naOff - 60)); r = findSvc(sid); if (r) return r; }
    const o = await openNA();
    if (o.error) return { error: o.error };
    await walk(() => !!findSvc(sid), Math.max(0, (S.naOff || 0) - 60));
    return findSvc(sid);
  }

  async function processOne(t) {
    const l = await locateSvc(t.sid, t.off);
    if (l && l.error) return result('error', l.error);
    if (!l) return result('skip', 'satır bulunamadı (taşınmış ya da silinmiş olabilir)');
    l.el.scrollIntoView({ block: 'center' });
    nudgeScroll();
    await waitFor(() => findSvc(t.sid), 3000, 100);
    await sleep(500); // sanallaştırılmış liste kaydırmadan sonra otursun

    const c1 = checkSvc(t.sid);
    if (!c1.ok) return result('skip', c1.reason);
    let m = await openSvcMenu(t.sid);
    if (!m.item && m.status !== 'enable') {
      // menü görünmedi ve satır ekrandan çıktı (liste kaydı): Actions'a YENİDEN basma (yine kaydırır); satırı bul, içindeki öğeyi al
      await closeMenu(); await sleep(400);
      const again = await locateSvc(t.sid, t.off);
      if (again && !again.error) {
        again.el.scrollIntoView({ block: 'center' }); nudgeScroll(); await sleep(500);
        const x = rowItem(t.sid);
        if (x && x.item) {
          const st = lower(x.item.textContent).split(' ')[0];
          remember('menu', [norm((x.item.closest('ul, [role=menu], .dropdown-menu') || x.item.parentElement).textContent).slice(0, 200) + ' [satırdaki öğe, menü açılmadan]']);
          m = { item: st === 'disable' ? x.item : null, status: st, opened: false };
        }
      }
    }
    if (m.status === 'enable') { await closeMenu(); return result('skip', 'menü "Enable service" diyor (zaten pasif)'); }
    if (!m.item) { await closeMenu(); S.diagNeeded = true; return result('error', '"Disable service" menüde bulunamadı'); }
    const c2 = checkSvc(t.sid);
    if (!c2.ok) { await closeMenu(); return result('skip', 'son anda değişti: ' + c2.reason); }
    if (S.stop) { await closeMenu(); return result('skip', 'durduruldu'); }
    if (settings.dryRun) { await closeMenu(); return result('dry', '"Disable service" bulundu' + (m.opened ? '' : ' (menü görünmedi, satırda)') + ', basılmadı'); }

    // basmadan hemen önce öğeyi satırdan yeniden al (React yeniden çizmiş olabilir)
    const fresh = m.portal ? m : rowItem(t.sid);
    if (!fresh || !fresh.item || lower(fresh.item.textContent) !== 'disable service') { await closeMenu(); return result('error', '"Disable service" son anda kayboldu'); }
    click(fresh.item);
    const seen = [];
    let out = await waitFor(() => {
      seen.push(...readToasts());
      if (findConfirm()) return 'confirm';
      const x = findSvc(t.sid);
      return x && x.state === 'off' ? 'off' : null;
    }, 10000, 120);
    if (out === 'confirm') {
      const d = findConfirm();
      remember('dialog', [d.text.slice(0, 200)]);
      if (!d.safe || !d.ok) { await closeConfirm(); S.diagNeeded = true; return result('error', 'beklenmeyen onay penceresi: "' + d.text.slice(0, 80) + '"'); }
      d.ok.click();
      out = await waitFor(() => { seen.push(...readToasts()); const x = findSvc(t.sid); return x && x.state === 'off' && !findConfirm() ? 'off' : null; }, 12000, 120);
    }
    remember('toast', [...new Set(seen)]);
    const errToast = [...new Set(seen)].find((x) => ERR_WORDS.test(x));
    if (out !== 'off') {
      if (findConfirm()) await closeConfirm();
      const x = await locateSvc(t.sid, t.off); // liste yeniden çizilmiş olabilir
      if (!x || x.error || x.state !== 'off') return result('error', 'pasife geçmedi' + (errToast ? ' — panel: "' + errToast.slice(0, 100) + '"' : ''));
    }
    return result('done', 'pasife alındı' + (errToast ? ' (panel: ' + errToast.slice(0, 60) + ')' : ''));
  }

  const workList = () => S.targets.filter((t) => !S.excluded.has(t.sid));

  // ---------------------------------------------------------------- toplu yöntem: kutucuklar → Actions → Disable all
  const SEL_RE = /(\d+)\s+services?\s+selected/i;
  /** Başlıktaki "N services selected" çubuğu: sayı, toplu Actions düğmesi, kutucuk. */
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
  async function clearSelection() {
    let x = selection();
    if (!x) return true;
    for (let k = 0; k < 2 && x && x.cb; k++) {
      x.cb.click();
      if (await waitFor(() => !selection(), 3000, 100)) return true;
      x = selection(); // tıklama "hepsini seç" gibi çalıştıysa bir kez daha bas
    }
    for (const r of serviceRows()) if (r.cb && r.cb.checked) r.cb.click();
    return !!(await waitFor(() => !selection(), 3000, 100));
  }
  /** "Disable all" sonrası onay penceresi (varsa): metinde "disable" olmalı, "delete" olmamalı; sayı varsa = seçilen. */
  function massConfirm() {
    for (const d of [...document.querySelectorAll(DIALOG)].filter(visible)) {
      const t = norm(d.innerText || d.textContent);
      const ok = [...d.querySelectorAll('button, a, input[type=submit]')].find((b) => visible(b) && /^(disable|disable all|yes|confirm|ok)$/i.test(norm(b.value || btnText(b))));
      const cancel = [...d.querySelectorAll('button, a')].find((b) => visible(b) && /^(cancel|close|no)$/i.test(btnText(b))) || d.querySelector('.close, [aria-label=close], [aria-label=Close]');
      const m = t.match(/(\d+)\s*services?/i);
      return { dlg: d, text: t, ok, cancel, n: m ? parseInt(m[1], 10) : null, safe: /disable/i.test(t) && !/delete/i.test(t) };
    }
    return null;
  }
  async function closeMassConfirm() {
    const c = massConfirm();
    if (!c) return true;
    if (c.cancel) c.cancel.click(); else pressEscape();
    return !!(await waitFor(() => !massConfirm(), 4000, 100));
  }

  /** Bir grup: aktif servislerin kutucuklarını işaretle (Not Assigned içinde) → toplu Actions → yalnızca tam "Disable all". */
  async function processMass(chunk) {
    const out = { done: [], dry: [], skipped: [], errors: [], fatal: false };
    const errAll = (why, list) => { for (const t of list) out.errors.push({ t, why }); };
    if (!(await clearSelection())) { out.fatal = true; errAll('önceki seçim temizlenemedi', chunk); return out; }
    const o = await openNA();
    if (o.error) { errAll(o.error, chunk); return out; }
    const want = new Map(chunk.map((t) => [t.sid, t]));
    const decided = new Set(); const picked = [];
    let abort = '';
    setStatus((settings.dryRun ? '[DENEME] ' : '') + chunk.length + ' servisin kutucuğu işaretleniyor…');
    await walk(async () => {
      naHead();
      for (const r of serviceRows()) {
        const t = want.get(r.sid);
        if (!t || decided.has(r.sid)) continue;
        decided.add(r.sid);
        const c = checkSvc(r.sid);
        if (!c.ok) { out.skipped.push({ t, why: c.reason }); continue; }
        if (!r.cb) { out.skipped.push({ t, why: 'kutucuk yok' }); continue; }
        if (r.cb.checked) { abort = 'servis zaten işaretliydi (' + r.sid + ')'; return true; }
        const n1 = picked.length + 1;
        r.cb.click();
        const ok = await waitFor(() => { const x = selection(); const y = findSvc(r.sid); return x && x.n === n1 && y && y.cb && y.cb.checked; }, 3000, 80);
        if (!ok) { const x = selection(); abort = 'işaretleme sonrası seçim ' + (x ? x.n : 0) + ' ≠ ' + n1; return true; }
        picked.push(t);
        if (S.stop) { abort = 'durduruldu'; return true; }
      }
      return decided.size === want.size;
    }, Math.max(0, (S.naOff || 0) - 60));
    if (!abort) for (const t of chunk) if (!decided.has(t.sid)) out.skipped.push({ t, why: 'listede bulunamadı (taşınmış/silinmiş olabilir)' });
    const bail = async (why, list) => { await closeMenu(); await closeMassConfirm(); await clearSelection(); errAll(why, list); return out; };
    if (abort === 'durduruldu') { await clearSelection(); for (const t of picked) out.skipped.push({ t, why: 'durduruldu' }); return out; }
    if (abort) { S.diagNeeded = true; return bail(abort, picked); }
    if (!picked.length) return out;
    const n = picked.length;
    const sel = selection();
    if (!sel || sel.n !== n) return bail('seçim sayısı (' + (sel ? sel.n : 0) + ') ≠ işaretlenen (' + n + ')', picked);
    if (!sel.btn) { S.diagNeeded = true; return bail('seçim çubuğunda Actions düğmesi bulunamadı', picked); }

    const before = new Set(itemsByText(/^disable all$/i));
    sel.btn.click();
    const item = await waitFor(() => { const v = itemsByText(/^disable all$/i).filter((x) => !before.has(x) && !x.closest('.service-table__category, .service-table__row, .service-table__body')); return v.length ? v[v.length - 1] : null; }, 2500, 60);
    if (!item) { S.diagNeeded = true; return bail('"Disable all" toplu menüde bulunamadı', picked); }
    remember('menu', [norm((item.closest('ul, [role=menu], .dropdown-menu') || item.parentElement).textContent).slice(0, 200)]);
    const sel2 = selection();
    if (!sel2 || sel2.n !== n) return bail('menü açıkken seçim değişti', picked);
    if (settings.dryRun || S.stop) {
      await closeMenu();
      if (!(await clearSelection())) { out.fatal = true; errAll('deneme: seçim temizlenemedi', picked); return out; }
      for (const t of picked) (S.stop ? out.skipped : out.dry).push({ t, why: S.stop ? 'durduruldu' : n + ' servis seçildi, "Disable all" görüldü, basılmadı' });
      return out;
    }
    click(item);
    const seen = [];
    const isOff = () => picked.every((t) => { const y = findSvc(t.sid); return !y || y.state === 'off'; }) && picked.some((t) => findSvc(t.sid));
    let r = await waitFor(() => { seen.push(...readToasts()); if (massConfirm()) return 'confirm'; return isOff() || !selection() ? 'ok' : null; }, 8000, 120);
    if (r === 'confirm') {
      const d = massConfirm();
      remember('dialog', [d.text.slice(0, 200)]);
      if (!d.safe || !d.ok) { S.diagNeeded = true; return bail('beklenmeyen onay penceresi: "' + d.text.slice(0, 80) + '"', picked); }
      if (d.n != null && d.n !== n) { S.diagNeeded = true; return bail('onay penceresindeki sayı (' + d.n + ') ≠ seçilen (' + n + ')', picked); }
      d.ok.click();
      r = await waitFor(() => { seen.push(...readToasts()); return !massConfirm() && (isOff() || !selection()) ? 'ok' : null; }, 15000, 120);
    }
    remember('toast', [...new Set(seen)]);
    if (r !== 'ok') { if (massConfirm()) await closeMassConfirm(); await clearSelection(); errAll('işlem tamamlanmadı (sunucu cevap vermedi?)', picked); return out; }
    await sleep(500);
    await clearSelection();
    const errToast = [...new Set(seen)].find((x) => ERR_WORDS.test(x));
    // doğrula: seçilenler gerçekten pasif mi (gerekirse kaydırarak)
    const left = new Set(picked.map((t) => t.sid));
    const stateOf = new Map();
    await walk(async () => { for (const y of serviceRows()) if (left.has(y.sid) && !stateOf.has(y.sid)) stateOf.set(y.sid, y.state); return stateOf.size === left.size; }, Math.max(0, (S.naOff || 0) - 60));
    for (const t of picked) {
      const st = stateOf.get(t.sid);
      if (st === 'off') out.done.push({ t, why: 'pasife alındı (Disable all, ' + n + ' servis)' });
      else out.errors.push({ t, why: (st ? 'pasife geçmedi' : 'doğrulanamadı') + (errToast ? ' — panel: "' + errToast.slice(0, 80) + '"' : '') });
    }
    return out;
  }

  async function runMass(list) {
    const K = 100;
    const lbl = (t) => t.sid + ' — ' + t.name;
    let i = 0; let bad = 0; let n = 0;
    const cap = list.slice(0, settings.max);
    if (cap.length < list.length) S.notes.push('En çok ' + settings.max + ' sınırına ulaşıldı.');
    for (; i < cap.length; i += K) {
      if (S.stop) break;
      const chunk = cap.slice(i, i + K);
      setStatus((settings.dryRun ? '[DENEME] ' : '') + 'Toplu: ' + (i + 1) + '–' + (i + chunk.length) + ' / ' + cap.length);
      let r;
      try { r = await processMass(chunk); } catch (e) { r = { done: [], dry: [], skipped: [], errors: chunk.map((t) => ({ t, why: 'beklenmeyen hata: ' + (e && e.message) })), fatal: false }; }
      for (const [k, arr] of [['done', r.done], ['dry', r.dry], ['skipped', r.skipped], ['errors', r.errors]]) for (const x of arr) S.res[k].push({ name: lbl(x.t), reason: x.why });
      n += r.done.length + r.dry.length;
      render();
      bad = r.errors.length ? bad + 1 : 0;
      if (r.fatal || bad >= 2) { S.notes.push('Toplu işlemde hata: durduruldu.'); S.diagNeeded = true; i += K; break; }
    }
    return { i, list: cap };
  }

  async function run() {
    if (S.running || S.phase !== 'ready' || !workList().length) return;
    if (anyDialog()) return fail('Açık bir pencere var; önce kapat.');
    S.running = true; S.stop = false; S.phase = 'running'; S.diagNeeded = false; busy(true);
    S.res = { done: [], dry: [], skipped: [], errors: [], pending: [] };
    let list = workList();
    let n = 0; let errs = 0; let i = 0;
    try {
      if (settings.method === 'mass') { const m = await runMass(list); i = m.i; list = m.list; }
      else for (; i < list.length; i++) {
        if (S.stop) break;
        if (n >= settings.max) { S.notes.push('En çok ' + settings.max + ' sınırına ulaşıldı.'); break; }
        const t = list[i];
        const label = t.sid + ' — ' + t.name;
        setStatus((settings.dryRun ? '[DENEME] ' : '') + '(' + (i + 1) + '/' + list.length + ') ' + label);
        let r;
        try { r = await processOne(t); } catch (e) { r = result('error', 'beklenmeyen hata: ' + (e && e.message)); }
        log(label + ' → ' + r.kind + ' (' + r.reason + ')');
        const rec = { name: label, reason: r.reason };
        if (r.kind === 'done') { S.res.done.push(rec); n++; errs = 0; }
        else if (r.kind === 'dry') { S.res.dry.push(rec); n++; errs = 0; }
        else if (r.kind === 'skip') { S.res.skipped.push(rec); errs = 0; }
        else { S.res.errors.push(rec); errs++; }
        render();
        if (errs >= 3) { S.notes.push('Üst üste hata: işlem durduruldu.'); S.diagNeeded = true; i++; break; }
      }
      S.res.pending = list.slice(i).map((t) => t.sid + ' — ' + t.name);
      const r = S.res;
      const bits = [r.done.length + ' pasife alındı', r.dry.length + ' deneme', r.skipped.length + ' atlandı', r.errors.length + ' hata'];
      if (r.pending.length) bits.push(r.pending.length + ' işlenmedi');
      S.phase = 'idle'; S.targets = [];
      setStatus((S.stop ? 'Durduruldu. ' : 'Bitti. ') + bits.join(', ') + '.');
      recordRun(S.status);
    } catch (e) {
      fail('Hata: ' + (e && e.message));
    } finally {
      S.running = false; S.stop = false; busy(false);
      render();
    }
  }

  function stop() { if (!S.running) return; S.stop = true; setStatus('Durdurma istendi; sıradaki servisten önce duracak…'); }
  const gScan = guarded(scan); const gRun = guarded(run);

  // ---------------------------------------------------------------- otomatik mod (her N dk yenile + kontrol et)
  // Durum sekmeye bağlı (sessionStorage): yalnızca açıldığı sekmede çalışır, sayfa yenilense de sürer, sekme kapanınca biter.
  const AUTO_KEY = 'sp_auto';
  function autoGet() { try { return JSON.parse(sessionStorage.getItem(AUTO_KEY) || 'null'); } catch (e) { return null; } }
  function autoSet(a) { try { sessionStorage.setItem(AUTO_KEY, JSON.stringify(a)); } catch (e) { /* yok */ } }
  function swAuto(on, every, restart) { const p = getPort(); if (p) { try { p.postMessage({ t: 'auto', on, every, restart: !!restart }); } catch (e) { port = null; } } }
  function swAutoInfo() { const p = getPort(); if (p) { try { p.postMessage({ t: 'autoInfo' }); } catch (e) { port = null; } } }

  // Kullanıcı panelde çalışıyorsa yenileme: gerçek (isTrusted) tıklama/tuş, eklentinin kendi çubuğu hariç.
  let lastUserInput = 0;
  const markUser = (e) => { if (!e.isTrusted) return; const path = e.composedPath ? e.composedPath() : []; if (path.some((n) => n && /^((kt|sp|pt)-host|pa-dock)$/.test(n.id || ''))) return; lastUserInput = Date.now(); };
  document.addEventListener('pointerdown', markUser, true);
  document.addEventListener('keydown', markUser, true);

  function autoEnable(on) {
    const prev = autoGet() || {};
    if (on) {
      const every = Math.max(1, Math.min(120, parseInt(prev.every || settings.every || 5, 10) || 5));
      autoSet({ on: true, real: !settings.dryRun, every, history: prev.history || [], bad: 0, startedAt: Date.now() });
      swAuto(true, every, true);
      autoCycle('açıldı'); // ilk kontrol hemen
    } else {
      autoSet(Object.assign(prev, { on: false, offReason: 'elle kapatıldı' }));
      swAuto(false);
    }
    render();
  }
  function autoEvery(v) {
    const every = Math.max(1, Math.min(120, parseInt(v, 10) || 5));
    settings.every = every; saveSettings();
    const a = autoGet();
    if (a) { a.every = every; autoSet(a); if (a.on) swAuto(true, every, true); }
    render();
  }

  let autoBusy = false;
  /** Bir otomatik tur: tara → aktif varsa pasife al → geçmişe yaz. 3 tur üst üste hata/uyarı → otomatik kapanır. */
  async function autoCycle(why) {
    const a0 = autoGet();
    if (!a0 || !a0.on || autoBusy || S.running) return;
    autoBusy = true;
    const rec = { at: Date.now(), neden: why, gercek: !!a0.real };
    try {
      // aynı sayfada başka araç (Kategori Temizle / Pasif Servis Taşı) çalışıyorsa bitmesini bekle
      if (!(await waitFor(lockTake, 15 * 60000, 500))) throw new Error('başka araç 15 dk içinde bitmedi');
      settings.dryRun = !a0.real;
      await scan();
      rec.bulunan = S.phase === 'ready' ? workList().length : 0;
      if (S.phase === 'ready' && workList().length) await run();
      rec.yapilan = S.res.done.length; rec.deneme = S.res.dry.length; rec.atlanan = S.res.skipped.length; rec.hata = S.res.errors.length;
      rec.durum = S.status.slice(0, 140);
    } catch (e) {
      rec.hata = (rec.hata || 0) + 1; rec.durum = 'hata: ' + (e && e.message);
    } finally {
      lockDrop();
      const a = autoGet() || a0;
      const bad = /^⚠/.test(rec.durum || '') || rec.hata > 0;
      a.history = [rec].concat(a.history || []).slice(0, 30);
      a.lastAt = rec.at;
      a.bad = bad ? (a.bad || 0) + 1 : 0;
      if (a.on && a.bad >= 3) { a.on = false; a.offReason = '3 tur üst üste hata/uyarı — otomatik kapatıldı. Teşhisi kopyala.'; swAuto(false); S.diagNeeded = true; }
      autoSet(a);
      autoBusy = false;
      swAutoInfo();
      render();
    }
  }

  /** Alarm geldi: uygunsa sayfayı yenile (yenilenen sayfa turu kendisi başlatır), değilse bu turu atla. */
  function autoTick() {
    const a = autoGet();
    if (!a || !a.on) return { skip: true, off: true };
    const skip = (why) => { a.lastSkip = { at: Date.now(), why }; autoSet(a); log('otomatik tur atlandı: ' + why); render(); return { skip: true, why }; };
    if (!onServicesList()) return skip('Services sayfasında değil');
    if (S.running || autoBusy) return skip('önceki iş sürüyor');
    if (!lockFree()) return skip('başka bir araç çalışıyor');
    if (anyDialog()) return skip('panelde açık pencere var');
    const ae = document.activeElement;
    if (ae && /^(INPUT|TEXTAREA|SELECT)$/.test(ae.tagName)) return skip('panelde yazı alanı odakta');
    if (Date.now() - lastUserInput < 60000) return skip('son 1 dakikada panelde işlem yapıldı');
    setTimeout(() => location.reload(), 150);
    return { reloading: true };
  }

  /** Sayfa yüklendi: otomatik açıksa panel çizilince turu başlat. */
  async function autoBoot() {
    const a = autoGet();
    if (!a || !a.on || !onServicesList()) { if (a && a.on) swAutoInfo(); return; }
    swAuto(true, a.every); // alarm yoksa kur (varsa takvimi bozma)
    setStatus('Otomatik: panelin yüklenmesi bekleniyor…');
    const ok = await waitFor(() => document.querySelector('.service-table__category, .service-table__body'), 90000, 300);
    if (!ok) {
      const b = autoGet() || a;
      b.history = [{ at: Date.now(), neden: 'yenileme', durum: '⚠ panel 90 sn içinde yüklenmedi', hata: 1 }].concat(b.history || []).slice(0, 30);
      b.bad = (b.bad || 0) + 1;
      if (b.bad >= 3) { b.on = false; b.offReason = '3 tur üst üste hata — otomatik kapatıldı.'; swAuto(false); }
      autoSet(b); render();
      return;
    }
    await sleep(1500);
    await autoCycle('yenileme');
  }
  const hhmm = (t) => (t ? new Date(t).toTimeString().slice(0, 5) : '--:--');

  // ---------------------------------------------------------------- teşhis
  function diag() {
    const strip = (h) => h.replace(/\s+/g, ' ').replace(/<svg[\s\S]*?<\/svg>/g, '<svg/>');
    const na = catHeaders().find((x) => x.na);
    const rows = serviceRows().slice(-6).map((r) => ({ id: r.sid, ad: r.name.slice(0, 60), durum: r.state, yazi: r.st, sinifPasif: r.clsOff, actions: !!r.btn, html: strip(r.row.outerHTML).slice(0, 500) }));
    const lastHeads = catHeaders().slice(-4).map((h) => ({ ad: h.name.slice(0, 50), na: h.na, html: strip(h.el.outerHTML).slice(0, 400) }));
    return JSON.stringify({
      surum: VERSION, adres: location.pathname, durum: S.status, otomatik: (() => { const a = autoGet(); return a ? { acik: a.on, gercek: a.real, her: a.every, son: a.lastAt && new Date(a.lastAt).toTimeString().slice(0, 8), sonraki: S.autoInfo && new Date(S.autoInfo.next).toTimeString().slice(0, 8), atlanan: a.lastSkip, kapanma: a.offReason, gecmis: (a.history || []).slice(0, 8) } : null; })(), istatistik: S.stats, notlar: S.notes, sonuc: S.res,
      notAssignedBasligi: na ? strip(na.el.outerHTML).slice(0, 600) : 'ekranda yok', naKonum: S.naOff,
      sonBasliklar: lastHeads, sonServisler: rows, menuYazilari: S.seen.menu, pencereYazilari: S.seen.dialog, bildirimler: S.seen.toast,
      adimGunlugu: S.log.slice(-40),
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
  try { const m = localStorage.getItem('sp_min'); minimized = m === '1'; } catch (e) { /* yok */ }
  // Ortak tasarım (Panel Araçları): açık/koyu tema, araç rengi --ac.
  const CSS = `
    :host{all:initial;--ac:#1f8a5b;--ac2:#176b46;--ink:#16202a;--mut:#5d6b78;--line:#e2e7ec;--bg:#ffffff;--soft:#f4f6f9;--red:#c0324a;--ok:#1f7a45;--warnc:#a34a1a}
    @media (prefers-color-scheme:dark){:host{--ink:#e8edf2;--mut:#9aa7b4;--line:#2a3441;--bg:#18202b;--soft:#121821;--red:#f2537d;--ok:#5cc58a;--warnc:#f0a46a}}
    *{box-sizing:border-box}
    .bar{position:fixed;right:12px;bottom:12px;z-index:2147483646;width:320px;max-width:calc(100vw - 24px);font:12.5px/1.45 system-ui,-apple-system,"Segoe UI",Roboto,Arial,sans-serif;color:var(--ink);background:var(--bg);
      border:1px solid var(--line);border-radius:14px;box-shadow:0 12px 32px rgba(15,23,42,.18),0 2px 6px rgba(15,23,42,.08);overflow:hidden;display:flex;flex-direction:column;max-height:calc(100vh - 24px)}
    .bar.min{width:auto}.bar.min .body{display:none}
    .hd{display:flex;align-items:center;gap:8px;padding:10px 12px;border-bottom:1px solid var(--line);background:linear-gradient(0deg,var(--bg),color-mix(in srgb,var(--ac) 7%,var(--bg)))}
    .bar.min .hd{border-bottom:0}
    .hd b{flex:1;font-size:13px;font-weight:650;display:flex;align-items:center;gap:6px}
    .hd small{font-weight:600;color:var(--warnc);background:color-mix(in srgb,var(--warnc) 12%,transparent);padding:1px 7px;border-radius:999px;font-size:10.5px}
    .hd button{all:unset;cursor:pointer;width:24px;height:24px;display:grid;place-items:center;border-radius:7px;color:var(--mut)}.hd button:hover{background:var(--soft);color:var(--ink)}
    .body{padding:10px 12px 12px;display:flex;flex-direction:column;gap:8px;flex:1 1 auto;min-height:0;overflow:auto}.body > *,.body > button.b{flex-shrink:0}.hd{flex:none}
    .row{display:flex;gap:8px;flex-wrap:wrap;align-items:center}
    button.b{all:unset;box-sizing:border-box;cursor:pointer;height:30px;padding:0 12px;display:inline-flex;align-items:center;border-radius:8px;background:var(--ac);color:#fff;font-weight:600;font-size:12.5px}
    button.b:hover{filter:brightness(1.08)}button.b:focus-visible,.tab:focus-visible,.lk:focus-visible{outline:2px solid var(--ac);outline-offset:2px}
    button.b[disabled]{opacity:.38;cursor:default;filter:none}
    button.red{background:var(--red)}
    button.b[data-a=stop],button.b[data-a=diag]{background:transparent;color:var(--ink);box-shadow:inset 0 0 0 1px var(--line)}
    button.b[data-a=stop]:hover,button.b[data-a=diag]:hover{background:var(--soft)}
    .st{white-space:pre-wrap;padding:8px 10px;border-radius:9px;background:var(--soft);color:var(--ink)}
    .st.warn,.warn{color:var(--red)}.st.warn{background:color-mix(in srgb,var(--red) 10%,var(--bg))}
    .list{max-height:150px;overflow:auto;background:var(--soft);border:1px solid var(--line);border-radius:9px;padding:4px 8px}
    .list > div{padding:3px 0;border-bottom:1px solid var(--line)}.list > div:last-child{border-bottom:0}
    .it{display:flex;gap:6px;align-items:center;padding:3px 0;border-bottom:1px solid var(--line)}.it:last-child{border-bottom:0}.it span{flex:1;min-width:0;overflow-wrap:anywhere}
    .lk{all:unset;cursor:pointer;color:var(--ac);font-size:11.5px;font-weight:600;margin-left:6px}.lk:hover{text-decoration:underline}
    label{display:flex;gap:6px;align-items:center;cursor:pointer}
    input[type=checkbox]{accent-color:var(--ac);width:14px;height:14px;margin:0}
    input[type=number]{width:62px;height:26px;background:var(--bg);color:var(--ink);border:1px solid var(--line);border-radius:7px;padding:0 6px;font:inherit}
    input[type=number]:focus{outline:2px solid var(--ac);outline-offset:0;border-color:transparent}
    small{color:var(--mut)}.sum{display:block;padding:6px 8px;border-radius:8px;background:color-mix(in srgb,var(--ac) 8%,var(--bg))}
    .tabs{display:flex;gap:3px;padding:3px;background:var(--soft);border-radius:10px}
    .tab{all:unset;cursor:pointer;flex:1;text-align:center;padding:6px;border-radius:8px;color:var(--mut);font-size:11.5px;font-weight:600}.tab.on{background:var(--bg);color:var(--ink);box-shadow:0 1px 3px rgba(15,23,42,.12)}.tab[disabled]{opacity:.5;cursor:default}
    .auto{color:var(--ok)}.ok{color:var(--ok);background:color-mix(in srgb,var(--ok) 10%,var(--bg));border-radius:9px;padding:8px 10px}
    .hd .ic{font-size:14px;width:22px;height:22px;display:grid;place-items:center;border-radius:7px;background:color-mix(in srgb,var(--ac) 14%,var(--bg));color:var(--ac);flex:none}
    .hd b::before{display:none}
    .hd button.mode{all:unset;cursor:pointer;font:700 10px/1 system-ui,sans-serif;letter-spacing:.04em;padding:5px 8px;border-radius:999px;width:auto;height:auto}.hd button.mode[disabled]{cursor:default;opacity:.7}
    .hd .mode.dry{color:var(--warnc);background:color-mix(in srgb,var(--warnc) 13%,var(--bg))}.hd .mode.real{color:#fff;background:var(--red)}.hd .mode:hover{filter:brightness(.95)}
    .blk{border:1px solid var(--line);border-radius:10px;overflow:hidden}
    .blk .list{border:0;border-radius:0;max-height:170px}
    .bh{display:flex;align-items:center;justify-content:space-between;gap:8px;padding:6px 8px 6px 10px;background:var(--soft);border-bottom:1px solid var(--line);font-size:11.5px;font-weight:650;color:var(--mut)}
    .bh em{font-style:normal;color:var(--ink);margin-left:4px}.bh .acts{display:flex;gap:2px}.bh .lk{margin:0;padding:2px 6px;border-radius:6px}.bh .lk:hover{background:var(--bg);text-decoration:none}
    .res .gh{display:flex;align-items:center;justify-content:space-between;padding:6px 0 2px;border-bottom:0}.res .gh.bad b{color:var(--red)}
    .res .gh .lk{margin:0}
    .ri{padding:3px 0;border-bottom:1px solid var(--line);overflow-wrap:anywhere}.ri small{margin-left:4px}
    .main{display:flex;gap:8px}.main [data-a=run]{flex:1;justify-content:center;height:34px}
    button.b[data-a=scan]{width:100%;justify-content:center;height:34px}
    .toast{padding:6px 10px;border-radius:9px;background:var(--ink);color:var(--bg);font-weight:600;font-size:12px}
    details.set{border:1px solid var(--line);border-radius:10px}
    details.set summary{cursor:pointer;list-style:none;padding:8px 10px;font-weight:650;font-size:12px;color:var(--mut);display:flex;align-items:center;gap:6px}
    details.set summary::before{content:"›";display:inline-block;transition:transform .15s;font-size:14px;line-height:1}
    details.set[open] summary::before{transform:rotate(90deg)}
    details.set summary::-webkit-details-marker{display:none}
    .kv{display:flex;flex-direction:column;border-top:1px solid var(--line)}
    .kr{display:flex;align-items:center;justify-content:space-between;gap:10px;padding:7px 10px;border-bottom:1px solid var(--line);cursor:pointer}
    .kr:last-child{border-bottom:0}
    .kr > span:first-child{display:flex;flex-direction:column;gap:1px;min-width:0}.kr > span:first-child small{font-size:11px;line-height:1.3}
    .kr .in{display:flex;flex-direction:row;align-items:center;gap:6px;flex:none;white-space:nowrap;color:var(--mut);font-size:11.5px}
    .ft{display:flex;gap:8px}.ft .b{flex:1;justify-content:center}
    button.gh{background:transparent;color:var(--ink);box-shadow:inset 0 0 0 1px var(--line)}button.gh:hover{background:var(--soft)}
    .autoinfo{padding:8px 10px;border-radius:9px;background:color-mix(in srgb,var(--ok) 10%,var(--bg));color:var(--ok);font-size:12px}
    .autoinfo.warn{background:color-mix(in srgb,var(--red) 10%,var(--bg));color:var(--red)}
    ::-webkit-scrollbar{width:8px;height:8px}::-webkit-scrollbar-thumb{background:var(--line);border-radius:8px}
  `;
  const esc = (s) => String(s).replace(/[&<>"]/g, (c) => ({ '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;' }[c]));
  function ensureBar() {
    if (!onServicesList()) { if (host) { host.remove(); host = null; root = null; } return; }
    if (host && host.isConnected) { placeBar(); return; }
    host = document.createElement('div');
    host.id = 'sp-host';
    root = host.attachShadow({ mode: 'open' });
    root.innerHTML = '<style>' + CSS + '</style><div class="bar" id="bar"></div>';
    root.addEventListener('click', onBarClick);
    root.addEventListener('change', onBarChange);
    root.addEventListener('toggle', (e) => { if (e.target.matches && e.target.matches('details.set')) { setOpen = e.target.open; try { localStorage.setItem(ME + '_set', setOpen ? '1' : '0'); } catch (er) { /* yok */ } } }, true);
    root.addEventListener('focusout', () => setTimeout(render, 0)); // sayı kutusundan çıkınca düğme etiketini güncelle
    document.documentElement.appendChild(host);
    render();
  }
  /** Kategori Temizle çubuğu da varsa yanına otur (üst üste binmesin). */
  /** Çubuklar sağdan sola dizilir (Kategori Temizle, Servis Pasifle, Pasif Servis Taşı): soldakilerin gerçek genişliği kadar kayar. */
  function placeBar() {
    const b = root && root.getElementById('bar');
    if (!b) return;
    let right = 12; // sağ altta, soldaki tgwatch logosuyla çakışmasın
    for (const id of ['kt-host']) {
      const h = document.getElementById(id);
      const ob = h && h.shadowRoot && h.shadowRoot.getElementById('bar');
      if (ob) right += Math.round(ob.getBoundingClientRect().width) + 8;
    }
    b.style.right = right + 'px';
  }

  document.addEventListener('panel-arac-yerles', () => setTimeout(placeBar, 0)); // soldaki çubuk küçüldü/büyüdü
  let pressed = false; let wantRender = false;
  document.addEventListener('pointerdown', (e) => { if (host && e.composedPath().includes(host)) pressed = true; }, true);
  document.addEventListener('pointerup', () => { if (!pressed) return; pressed = false; setTimeout(() => { if (wantRender) render(); }, 0); }, true);

  // ---------------------------------------------------------------- ortak arayüz parçaları (Panel Araçları tasarımı)
  /** Metindeki/öğedeki servis ya da kategori ID'si: sid, id ("category-123" → 123) ya da "62727 — Ad" biçimindeki adın başı. */
  const idOf = (x) => { const v = x && (x.sid || x.id); if (v) return String(v).replace(/\D+/g, ''); const m = String((x && x.name) || x || '').match(/^\s*(\d{2,})/); return m ? m[1] : ''; };
  const idList = (arr) => arr.map(idOf).filter(Boolean);
  let setOpen = false;
  try { setOpen = localStorage.getItem(ME + '_set') === '1'; } catch (e) { setOpen = false; } // Ayarlar başta kapalı (sade görünüm)
  let flash = '';
  function toast(t) { flash = t; render(); setTimeout(() => { if (flash === t) { flash = ''; render(); } }, 2500); }
  async function copyIds(ids, what) {
    if (!ids.length) return toast('Kopyalanacak ID yok');
    const ok = await copyText(ids.join(','));
    toast(ok ? '✓ ' + ids.length + ' ' + what + ' ID kopyalandı' : '⚠ Panoya kopyalanamadı');
  }
  /** Günlük: teşhis + adım günlüğü + sonuçlar → .txt indir. */
  function logText() {
    const r = S.res || {};
    const lines = ['Panel Araçları — ' + 'Servis Pasifle' + ' v' + VERSION, 'Zaman: ' + new Date().toLocaleString('tr-TR'), 'Adres: ' + location.href, 'Durum: ' + S.status, ''];
    for (const [k, t] of RES_GROUPS) {
      const a = r[k] || [];
      if (!a.length) continue;
      lines.push('== ' + t + ' (' + a.length + ')');
      for (const x of a) lines.push('  ' + (x.name || x) + (x.reason ? '  — ' + x.reason : ''));
      const ids = idList(a); if (ids.length) lines.push('  ID: ' + ids.join(','));
      lines.push('');
    }
    if (S.notes && S.notes.length) lines.push('== Notlar', ...S.notes.map((n) => '  ' + n), '');
    lines.push('== Adım günlüğü', ...(S.log || []).map((l) => '  ' + l), '', '== Teşhis', diag());
    return lines.join('\r\n');
  }
  function downloadLog() {
    const name = ME + '-gunluk-' + new Date().toISOString().slice(0, 16).replace(/[:T]/g, '-') + '.txt';
    const a = document.createElement('a');
    a.href = 'data:text/plain;charset=utf-8,' + encodeURIComponent('﻿' + logText());
    a.download = name;
    a.style.display = 'none';
    document.documentElement.appendChild(a); a.click(); a.remove();
    toast('⬇ ' + name + ' indirildi');
  }
  const ui = {
    head: (icon, title) => '<div class="hd"><span class="ic">' + icon + '</span><b>' + title + '</b><button class="mode ' + (settings.dryRun ? 'dry' : 'real') + '" data-a="drytoggle"' + (S.running ? ' disabled' : '') + ' title="' + (settings.dryRun ? 'Deneme modu: hiçbir şey değişmez — tıkla: gerçek moda geç' : 'Gerçek mod: değişiklik yapılır — tıkla: deneme moduna dön') + '">' + (settings.dryRun ? 'DENEME' : 'GERÇEK') + '</button><button data-a="min" title="Küçült/Büyüt" aria-label="Küçült">' + (minimized ? '▢' : '–') + '</button></div>',
    /** Seçim listesi: başlıkta seçili/toplam, hepsi/hiçbiri, ID'leri kopyala. */
    pick: (id, title, items, sel, busy) => '<div class="blk"><div class="bh"><span>' + title + ' <em>' + sel + '/' + items.length + '</em></span><span class="acts"><button class="lk" data-a="all"' + (busy ? ' disabled' : '') + '>hepsi</button><button class="lk" data-a="none"' + (busy ? ' disabled' : '') + '>hiçbiri</button><button class="lk" data-a="copyids" data-g="sel" title="Seçili olanların ID\'lerini virgülle kopyala (62727,26272,…)">⧉ ID\'ler</button></span></div><div class="list" id="' + id + '">' + items.join('') + '</div></div>',
    /** Sonuç grupları; her grubun ID'leri ayrı kopyalanır. */
    res: (id, r) => {
      const g = RES_GROUPS.filter(([k]) => (r[k] || []).length);
      if (!g.length) return '';
      return '<div class="blk"><div class="bh"><span>Sonuç</span><span class="acts"><button class="lk" data-a="copyids" data-g="done" title="Yapılanların ID\'lerini virgülle kopyala">⧉ yapılan ID\'ler</button></span></div><div class="list res" id="' + id + '">'
        + g.map(([k, t]) => '<div class="gh' + (k === 'errors' ? ' bad' : '') + '"><b>' + t + ' (' + r[k].length + ')</b>' + (idList(r[k]).length ? '<button class="lk" data-a="copyids" data-g="' + k + '">⧉</button>' : '') + '</div>'
          + r[k].map((x) => '<div class="ri">' + esc(x.name) + (x.reason ? ' <small>' + esc(x.reason) + '</small>' : '') + '</div>').join('')).join('') + '</div></div>';
    },
    status: () => '<div class="st' + (/^⚠/.test(S.status) ? ' warn' : '') + '" id="st" role="status">' + esc(S.status) + '</div>' + (flash ? '<div class="toast">' + esc(flash) + '</div>' : ''),
    settings: (rows) => '<details class="set"' + (setOpen ? ' open' : '') + '><summary>Ayarlar</summary><div class="kv">' + rows.join('') + '</div></details>',
    row: (label, ctl, hint) => '<label class="kr"><span>' + label + (hint ? '<small>' + hint + '</small>' : '') + '</span>' + ctl + '</label>',
    foot: () => '<div class="ft"><button class="b gh" data-a="diag">Teşhisi kopyala</button><button class="b gh" data-a="log" title="Teşhis + adım günlüğü + sonuçlar (.txt)">⬇ Günlük (.txt)</button></div>',
  };
  /** Ortak düğmeler: ID kopyala, günlük indir, ayarlar aç/kapa. true dönerse işlendi. */
  function uiClick(a, t) {
    if (a === 'copyids') {
      const g = t.getAttribute('data-g');
      if (g === 'sel') { copyIds(idList(selectedItems()), 'seçili'); return true; }
      const r = S.res || {};
      if (g === 'done') { copyIds(idList(DONE_KEYS.flatMap((k) => r[k] || [])), 'yapılan'); return true; }
      copyIds(idList(r[g] || []), (RES_GROUPS.find((x) => x[0] === g) || ['', ''])[1].toLowerCase()); return true;
    }
    if (a === 'log') { downloadLog(); return true; }
    if (a === 'drytoggle') { patchSettings({ dryRun: !settings.dryRun }); render(); return true; }
    return false;
  }
  const RES_GROUPS = [['done', 'Pasife alınan'], ['dry', 'Deneme'], ['skipped', 'Atlanan'], ['errors', 'Hata'], ['pending', 'İşlenmeyen']];
  const DONE_KEYS = ['done'];
  const selectedItems = () => (S.phase === 'ready' ? S.targets.filter((t) => !S.excluded.has(t.sid)) : []);
  function render() {
    if (!root) return;
    if (pressed) { wantRender = true; return; }
    wantRender = false;
    const bar = root.getElementById('bar');
    if (!bar) return;
    const focused = root.activeElement;
    if (focused && focused.tagName === 'INPUT' && focused.type === 'number') { const st = bar.querySelector('#st'); if (st) st.textContent = S.status; return; }
    const busyNow = S.running; const r = S.res;
    const work = S.phase === 'ready' ? Math.min(workList().length, settings.max) : 0;
    const mass = settings.method === 'mass';
    const p = [ui.head('⊘', 'Servis Pasifle'), '<div class="body">'];
    p.push('<div class="tabs"><button class="tab' + (mass ? ' on' : '') + '" data-a="method" data-v="mass"' + (busyNow ? ' disabled' : '') + ' title="Kutucukları işaretle → Disable all">Toplu</button><button class="tab' + (mass ? '' : ' on') + '" data-a="method" data-v="single"' + (busyNow ? ' disabled' : '') + ' title="Her servis için Actions → Disable service">Tek tek</button></div>');
    p.push('<button class="b" data-a="scan"' + (busyNow ? ' disabled' : '') + '>Not Assigned\'daki aktif servisleri bul</button>');
    if (S.stats) p.push('<small class="sum">Not Assigned: ' + S.stats.okunan + ' servis · <b>' + S.stats.aktif + ' aktif</b> · ' + S.stats.pasif + ' pasif' + (S.stats.belirsiz ? ' · ' + S.stats.belirsiz + ' belirsiz' : '') + '</small>');
    if (S.phase === 'ready' && S.targets.length) {
      const sel = S.targets.filter((t) => !S.excluded.has(t.sid)).length;
      p.push(ui.pick('spList', 'Pasife alınacak', S.targets.map((t) => '<label class="it"><input type="checkbox" data-x="' + esc(t.sid) + '"' + (S.excluded.has(t.sid) ? '' : ' checked') + (busyNow ? ' disabled' : '') + '> <span>' + esc(t.sid + ' — ' + t.name) + '</span></label>'), sel, busyNow));
    }
    if (S.phase === 'ready' && !S.targets.length && !busyNow) p.push('<div class="ok">✓ Not Assigned\'daki tüm servisler zaten pasif — yapılacak iş yok.</div>');
    const why = busyNow ? 'İş sürüyor' : S.phase !== 'ready' ? 'Önce "Not Assigned\'daki aktif servisleri bul"a bas' : !workList().length ? 'Pasife alınacak aktif servis yok' : '';
    p.push('<div class="main"><button class="b red" data-a="run" title="' + esc(why) + '"' + (why ? ' disabled' : '') + '>' + (settings.dryRun ? 'Dene' : 'Pasife al') + ' (' + work + ')</button><button class="b" data-a="stop"' + (busyNow ? '' : ' disabled title="Yalnızca iş sürerken"') + '>Durdur</button></div>');
    const a = autoGet();
    const every = (a && a.every) || settings.every || 5;
    let autoBox = '';
    if (a && a.on) {
      const h = (a.history || [])[0];
      autoBox = '<div class="autoinfo" id="spAuto">● Otomatik AÇIK (' + (a.real ? '<b>GERÇEK</b>' : 'deneme') + ') · sonraki tur ' + hhmm(S.autoInfo && S.autoInfo.next) + (h ? '<br>Son tur ' + hhmm(h.at) + ': ' + (h.bulunan || 0) + ' bulundu, ' + (a.real ? (h.yapilan || 0) + ' pasife alındı' : (h.deneme || 0) + ' deneme') + (h.hata ? ', ' + h.hata + ' hata' : '') : '') + (a.lastSkip && (!h || a.lastSkip.at > h.at) ? '<br>Son tur atlandı (' + hhmm(a.lastSkip.at) + '): ' + esc(a.lastSkip.why) : '') + '</div>';
    } else if (a && a.offReason && a.offReason !== 'elle kapatıldı') {
      autoBox = '<div class="autoinfo warn">' + esc(a.offReason) + '</div>';
    }
    const autoRows = [
      ui.row('Otomatik mod', '<span class="in"><input type="checkbox" data-s="auto"' + (a && a.on ? ' checked' : '') + '></span>', 'Sayfayı yenileyip kendiliğinden Not Assigned\'ı kontrol eder'),
      ui.row('Kaç dakikada bir', '<span class="in"><input type="number" min="1" max="120" data-s="every" value="' + every + '"> dk</span>'),
    ];
    p.push(autoBox);
    p.push(ui.status());
    p.push(ui.res('spRes', r));
    if (a && (a.history || []).length) p.push('<div class="blk"><div class="bh"><span>Otomatik turlar</span></div><div class="list" id="spHist">' + a.history.slice(0, 5).map((h) => '<div>' + hhmm(h.at) + ' · ' + (h.bulunan || 0) + ' bulundu · ' + (h.gercek ? (h.yapilan || 0) + ' pasife alındı' : (h.deneme || 0) + ' deneme') + (h.hata ? ' · ' + h.hata + ' hata' : '') + (/^⚠/.test(h.durum || '') ? ' · <small>' + esc(h.durum) + '</small>' : '') + '</div>').join('') + '</div></div>');
    if (S.notes.length) p.push('<small>' + S.notes.map(esc).join('<br>') + '</small>');
    p.push(ui.settings([
      ui.row('Deneme modu', '<span class="in"><input type="checkbox" data-s="dry"' + (settings.dryRun ? ' checked' : '') + '></span>', 'Açıkken hiçbir servis pasife alınmaz'),
      ui.row('En çok', '<span class="in"><input type="number" min="1" max="5000" data-s="max" value="' + settings.max + '"> servis</span>'),
    ].concat(autoRows)));
    p.push(ui.foot(), '</div>');
    bar.className = 'bar' + (minimized ? ' min' : '');
    bar.innerHTML = p.join('');
    placeBar();
    if (globalThis.__paDock) globalThis.__paDock.place('sp', host, bar);
  }
  function setAll(off) { for (const t of S.targets) { if (off) S.excluded.add(t.sid); else S.excluded.delete(t.sid); } }
  async function onBarClick(ev) {
    const t = ev.target.closest && ev.target.closest('[data-a]');
    if (!t || t.disabled || t.hasAttribute('disabled')) return;
    const a = t.getAttribute('data-a');
    if (uiClick(a, t)) return;
    if (a === 'min') { minimized = !minimized; try { localStorage.setItem('sp_min', minimized ? '1' : '0'); } catch (e) { /* yok */ } render(); document.dispatchEvent(new CustomEvent('panel-arac-yerles')); }
    else if (a === 'scan') gScan();
    else if (a === 'method') { patchSettings({ method: t.getAttribute('data-v') }); render(); }
    else if (a === 'run') gRun();
    else if (a === 'stop') stop();
    else if (a === 'all' || a === 'none') { setAll(a === 'none'); render(); }
    else if (a === 'diag') { const ok = await copyText(diag()); S.status = ok ? 'Teşhis panoya kopyalandı; sohbete yapıştır.' : '⚠ Panoya kopyalanamadı.'; render(); }
  }
  function onBarChange(ev) {
    const t = ev.target;
    const s = t.getAttribute && t.getAttribute('data-s');
    if (s === 'dry') patchSettings({ dryRun: t.checked });
    if (s === 'max') patchSettings({ max: t.value });
    if (s === 'auto') autoEnable(t.checked);
    if (s === 'every') autoEvery(t.value);
    const x = t.getAttribute && t.getAttribute('data-x');
    if (x) { if (t.checked) S.excluded.delete(x); else S.excluded.add(x); }
    render();
  }

  // ---------------------------------------------------------------- mini panel ile iletişim
  function publicState() {
    return {
      version: VERSION, onPage: onServicesList(), phase: S.phase, running: S.running, status: S.status, stats: S.stats, notes: S.notes, res: S.res,
      targets: S.targets.map((t) => ({ key: t.sid, name: t.sid + ' — ' + t.name, off: S.excluded.has(t.sid) })), work: S.phase === 'ready' ? workList().length : 0,
      settings: Object.assign({}, settings),
      auto: (() => { const a = autoGet(); return a ? { on: a.on, real: a.real, every: a.every, next: S.autoInfo && S.autoInfo.next, last: (a.history || [])[0] || null, skip: a.lastSkip || null, off: a.offReason || '' } : null; })(),
    };
  }
  try {
    chrome.runtime.onMessage.addListener((msg, _sender, respond) => {
      if (!msg || msg.sp !== true) return false;
      const done = () => respond(publicState());
      switch (msg.cmd) {
        case 'scan': gScan(); setTimeout(done, 50); return true;
        case 'run': gRun(); setTimeout(done, 50); return true;
        case 'stop': stop(); done(); return false;
        case 'settings': patchSettings(msg.patch); render(); done(); return false;
        case 'exclude': if (msg.all !== undefined) setAll(!!msg.all); else if (msg.on) S.excluded.add(msg.key); else S.excluded.delete(msg.key); render(); done(); return false;
        case 'diag': respond({ text: diag() }); return false;
        case 'ids': { const g = msg.g; const r = S.res || {}; respond({ ids: g === 'sel' ? idList(selectedItems()) : g === 'done' ? idList(DONE_KEYS.flatMap((k) => r[k] || [])) : idList(r[g] || []) }); return false; }
        case 'log': respond({ text: logText(), name: ME + '-gunluk-' + new Date().toISOString().slice(0, 16).replace(/[:T]/g, '-') + '.txt' }); return false;
        case 'autoTick': respond(autoTick()); return false;
        case 'auto': if (msg.every !== undefined) autoEvery(msg.every); if (msg.on !== undefined) autoEnable(!!msg.on); done(); return false;
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
  // otomatik mod: sayaç görünsün diye ara ara yenile; açıksa turu başlat
  setInterval(() => { const a = autoGet(); if (a && a.on && !S.running) { swAutoInfo(); } }, 20000);
  autoBoot();
})();
