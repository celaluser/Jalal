// Servis Pasifle — Perfect Panel > Services: "Not Assigned" kategorisindeki AKTİF servisleri tek tek pasife alır.
// Akış: Bul → kategori görünümü → listeyi sona kadar gez → Not Assigned başlığını bul ve aç → altındaki servisleri oku →
//   aktif olanları listele. Pasife al → her servis: satırı kimliğinden (service-NNNN) bul → hâlâ Not Assigned'da ve
//   "Enabled" mı? → Actions → yalnızca tam "Disable service" → (onay penceresi çıkarsa onayla) → "Disabled" oldu mu?
// "Delete service", "Enable service" gibi diğer menü öğelerine asla basılmaz. Deneme modu: menüyü açar, kapatır.
// React yeniden çizdiği için öğe referansı saklanmaz; liste sanallaştırılmış olduğu için satırlar kaydırarak aranır.
(() => {
  if (window.__servisPasifle) return;
  window.__servisPasifle = true;

  const VERSION = '1.1.0';
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
  const settings = { dryRun: true, max: 100, every: 5 };
  try { const v = JSON.parse(localStorage.getItem(SETTINGS_KEY) || '{}'); if (v.max) settings.max = Math.max(1, Math.min(5000, parseInt(v.max, 10) || 100)); if (v.every) settings.every = Math.max(1, Math.min(120, parseInt(v.every, 10) || 5)); } catch (e) { /* yok */ }
  const saveSettings = () => { try { localStorage.setItem(SETTINGS_KEY, JSON.stringify({ max: settings.max, every: settings.every })); } catch (e) { /* yok */ } };
  function patchSettings(p) {
    if (p && typeof p.dryRun === 'boolean') { settings.dryRun = p.dryRun; const a = autoGet(); if (a && a.on) { a.real = !p.dryRun; autoSet(a); } }
    if (p && p.max !== undefined) settings.max = Math.max(1, Math.min(5000, parseInt(p.max, 10) || 100));
    saveSettings();
  }
  function log(msg) { S.log.push(new Date().toTimeString().slice(0, 8) + ' ' + msg); if (S.log.length > 300) S.log.shift(); }
  function setStatus(t) { S.status = t; log(t); render(); }
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
      return { el: b, row, sid, name, state, st, clsOff, btn };
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
    if (onStep()) return true;
    let stable = 0;
    for (let i = 0; i < 5000 && !S.stop; i++) {
      const before = box.scrollTop;
      setScroll(box, before + Math.max(150, Math.floor(box.clientHeight * 0.7)));
      await sleep(170);
      if (onStep()) return true;
      const atEnd = box.scrollTop + box.clientHeight >= box.scrollHeight - 4 || box.scrollTop === before;
      if (!atEnd) { stable = 0; continue; }
      const h = box.scrollHeight;
      await sleep(700);
      if (onStep()) return true;
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
    if (!t) return false;
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
    if (!h) {
      if (!(await ensureCategoryView())) return { error: 'kategori görünümüne geçilemedi' };
      await walk(() => !!naHead());
      h = naHead();
      if (!h) return { error: '"Not Assigned" başlığı bulunamadı (listenin sonuna kadar bakıldı)' };
    }
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
  const MENU_ANY = /^(edit service|edit description|disable service|enable service|delete custom rates|duplicate|hide service|delete service|edit category)$/i;
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
  async function openSvcMenu(r) {
    const re = /^(disable|enable)\s+service$/i;
    const before = new Set(itemsByText(re));
    r.btn.click();
    const item = await waitFor(() => { const v = itemsByText(re).filter((x) => !before.has(x)); return v.length ? v[v.length - 1] : null; }, 2500, 60);
    if (!item) return { item: null, status: null };
    remember('menu', [norm((item.closest('ul, [role=menu], .dropdown-menu') || item.parentElement).textContent).slice(0, 200)]);
    const status = lower(item.textContent).split(' ')[0];
    const inRow = item.closest('.service-table__body, .service-table__row-service');
    return { item: status === 'disable' ? item : null, status, foreign: !!(inRow && !r.el.contains(item)) };
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

  async function locateSvc(sid) {
    let r = findSvc(sid);
    if (r) return r;
    const o = await openNA();
    if (o.error) return { error: o.error };
    await walk(() => !!findSvc(sid), Math.max(0, (S.naOff || 0) - 60));
    return findSvc(sid);
  }

  async function processOne(t) {
    const l = await locateSvc(t.sid);
    if (l && l.error) return result('error', l.error);
    if (!l) return result('skip', 'satır bulunamadı (taşınmış ya da silinmiş olabilir)');
    l.el.scrollIntoView({ block: 'center' });
    nudgeScroll();
    await waitFor(() => findSvc(t.sid), 3000, 100);
    await sleep(250);

    const c1 = checkSvc(t.sid);
    if (!c1.ok) return result('skip', c1.reason);
    const m = await openSvcMenu(c1.r);
    if (m.status === 'enable') { await closeMenu(); return result('skip', 'menü "Enable service" diyor (zaten pasif)'); }
    if (!m.item) { await closeMenu(); S.diagNeeded = true; return result('error', '"Disable service" menüde bulunamadı'); }
    if (m.foreign) { await closeMenu(); return result('skip', 'menü başka satıra ait'); }
    const c2 = checkSvc(t.sid);
    if (!c2.ok) { await closeMenu(); return result('skip', 'son anda değişti: ' + c2.reason); }
    if (S.stop) { await closeMenu(); return result('skip', 'durduruldu'); }
    if (settings.dryRun) { await closeMenu(); return result('dry', '"Disable service" görüldü, basılmadı'); }

    click(m.item);
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
      const x = await locateSvc(t.sid); // liste yeniden çizilmiş olabilir
      if (!x || x.error || x.state !== 'off') return result('error', 'pasife geçmedi' + (errToast ? ' — panel: "' + errToast.slice(0, 100) + '"' : ''));
    }
    return result('done', 'pasife alındı' + (errToast ? ' (panel: ' + errToast.slice(0, 60) + ')' : ''));
  }

  const workList = () => S.targets.filter((t) => !S.excluded.has(t.sid));

  async function run() {
    if (S.running || S.phase !== 'ready' || !workList().length) return;
    if (anyDialog()) return fail('Açık bir pencere var; önce kapat.');
    S.running = true; S.stop = false; S.phase = 'running'; S.diagNeeded = false; busy(true);
    S.res = { done: [], dry: [], skipped: [], errors: [], pending: [] };
    const list = workList();
    let n = 0; let errs = 0; let i = 0;
    try {
      for (; i < list.length; i++) {
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
    } catch (e) {
      fail('Hata: ' + (e && e.message));
    } finally {
      S.running = false; S.stop = false; busy(false);
      render();
    }
  }

  function stop() { if (!S.running) return; S.stop = true; setStatus('Durdurma istendi; sıradaki servisten önce duracak…'); }

  // ---------------------------------------------------------------- otomatik mod (her N dk yenile + kontrol et)
  // Durum sekmeye bağlı (sessionStorage): yalnızca açıldığı sekmede çalışır, sayfa yenilense de sürer, sekme kapanınca biter.
  const AUTO_KEY = 'sp_auto';
  function autoGet() { try { return JSON.parse(sessionStorage.getItem(AUTO_KEY) || 'null'); } catch (e) { return null; } }
  function autoSet(a) { try { sessionStorage.setItem(AUTO_KEY, JSON.stringify(a)); } catch (e) { /* yok */ } }
  function swAuto(on, every, restart) { const p = getPort(); if (p) { try { p.postMessage({ t: 'auto', on, every, restart: !!restart }); } catch (e) { port = null; } } }
  function swAutoInfo() { const p = getPort(); if (p) { try { p.postMessage({ t: 'autoInfo' }); } catch (e) { port = null; } } }

  // Kullanıcı panelde çalışıyorsa yenileme: gerçek (isTrusted) tıklama/tuş, eklentinin kendi çubuğu hariç.
  let lastUserInput = 0;
  const markUser = (e) => { if (!e.isTrusted) return; if (host && e.composedPath && e.composedPath().includes(host)) return; lastUserInput = Date.now(); };
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
      settings.dryRun = !a0.real;
      await scan();
      rec.bulunan = S.phase === 'ready' ? workList().length : 0;
      if (S.phase === 'ready' && workList().length) await run();
      rec.yapilan = S.res.done.length; rec.deneme = S.res.dry.length; rec.atlanan = S.res.skipped.length; rec.hata = S.res.errors.length;
      rec.durum = S.status.slice(0, 140);
    } catch (e) {
      rec.hata = (rec.hata || 0) + 1; rec.durum = 'hata: ' + (e && e.message);
    } finally {
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
  try { minimized = localStorage.getItem('sp_min') === '1'; } catch (e) { /* yok */ }
  const CSS = `
    :host{all:initial}
    .bar{position:fixed;bottom:12px;z-index:2147483646;width:300px;font:12px/1.4 system-ui,sans-serif;color:#e8ecf4;background:#1e2a24;border:1px solid #3c5a4a;border-radius:10px;box-shadow:0 6px 20px rgba(0,0,0,.45)}
    .bar.min{width:auto}.bar.min .body{display:none}
    .hd{display:flex;align-items:center;gap:6px;padding:6px 8px}.hd b{flex:1}
    .hd button{all:unset;cursor:pointer;padding:0 6px;border-radius:4px;color:#aab4cc}.hd button:hover{background:#2c3f35}
    .body{padding:0 8px 8px;display:flex;flex-direction:column;gap:6px}
    .row{display:flex;gap:6px;flex-wrap:wrap;align-items:center}
    button.b{all:unset;cursor:pointer;padding:5px 9px;border-radius:6px;background:#2d4a3c;color:#fff}
    button.b:hover{background:#3a604d}button.b[disabled]{opacity:.4;cursor:default}
    button.red{background:#9b2c3a}button.red:hover{background:#b73646}
    .st{white-space:pre-wrap;color:#c9d8d0}.warn{color:#ffb4a8}
    .list{max-height:140px;overflow:auto;background:#121a16;border-radius:6px;padding:4px 6px}
    .list > div{padding:1px 0;border-bottom:1px solid #20302a}
    .it{display:flex;gap:4px;align-items:center;padding:1px 0;border-bottom:1px solid #20302a}.it span{flex:1}
    .lk{all:unset;cursor:pointer;color:#8fd0ff;font-size:11px;margin-left:6px}
    label{display:flex;gap:4px;align-items:center}
    input[type=number]{width:56px;background:#121a16;color:#fff;border:1px solid #3c5a4a;border-radius:4px;padding:2px 4px}
    small{color:#9fb3a8}
    .auto{color:#8fe0b0}.warn{color:#ffb4a8}
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
    root.addEventListener('focusout', () => setTimeout(render, 0)); // sayı kutusundan çıkınca düğme etiketini güncelle
    document.documentElement.appendChild(host);
    render();
  }
  /** Kategori Temizle çubuğu da varsa yanına otur (üst üste binmesin). */
  function placeBar() { const b = root && root.getElementById('bar'); if (b) b.style.left = document.getElementById('kt-host') ? '316px' : '12px'; }

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
    const work = S.phase === 'ready' ? Math.min(workList().length, settings.max) : 0;
    const p = [];
    p.push('<div class="hd"><b>⏻ Servis Pasifle' + (settings.dryRun ? ' <small>(deneme)</small>' : '') + '</b><button data-a="min" title="Küçült/Büyüt">' + (minimized ? '▢' : '–') + '</button></div><div class="body">');
    p.push('<div class="row"><button class="b" data-a="scan"' + (busyNow ? ' disabled' : '') + '>Not Assigned\'daki aktif servisleri bul</button></div>');
    if (S.stats) p.push('<small class="sum">Not Assigned: ' + S.stats.okunan + ' servis · <b>' + S.stats.aktif + ' aktif</b> · ' + S.stats.pasif + ' pasif' + (S.stats.belirsiz ? ' · ' + S.stats.belirsiz + ' belirsiz' : '') + '</small>');
    if (S.phase === 'ready' && S.targets.length) {
      p.push('<div class="row"><small>Pasife alınacak (işareti kaldırılan atlanır):</small><button class="lk" data-a="all">hepsi</button><button class="lk" data-a="none">hiçbiri</button></div>');
      p.push('<div class="list" id="spList">' + S.targets.map((t) => '<label class="it"><input type="checkbox" data-x="' + esc(t.sid) + '"' + (S.excluded.has(t.sid) ? '' : ' checked') + '> <span>' + esc(t.sid + ' — ' + t.name) + '</span></label>').join('') + '</div>');
    }
    p.push('<div class="row"><button class="b red" data-a="run"' + (busyNow || S.phase !== 'ready' || !workList().length ? ' disabled' : '') + '>' + (settings.dryRun ? 'Dene' : 'Pasife al') + ' (' + work + ')</button><button class="b" data-a="stop"' + (busyNow ? '' : ' disabled') + '>Durdur</button></div>');
    p.push('<div class="row"><label><input type="checkbox" data-s="dry"' + (settings.dryRun ? ' checked' : '') + '> Deneme modu</label><label>en çok <input type="number" min="1" max="5000" data-s="max" value="' + settings.max + '"></label></div>');
    const a = autoGet();
    const every = (a && a.every) || settings.every || 5;
    p.push('<div class="row"><label><input type="checkbox" data-s="auto"' + (a && a.on ? ' checked' : '') + '> Otomatik: her</label><input type="number" min="1" max="120" data-s="every" value="' + every + '"><label>dk yenile + kontrol et</label></div>');
    if (a && a.on) {
      const h = (a.history || [])[0];
      p.push('<small class="auto" id="spAuto">● Otomatik AÇIK (' + (a.real ? '<b>GERÇEK</b>' : 'DENEME') + ') · sonraki: ' + hhmm(S.autoInfo && S.autoInfo.next) + (h ? ' · son ' + hhmm(h.at) + ': ' + (h.bulunan || 0) + ' bulundu, ' + (a.real ? (h.yapilan || 0) + ' pasife alındı' : (h.deneme || 0) + ' deneme') + (h.hata ? ', ' + h.hata + ' hata' : '') : '') + (a.lastSkip && (!h || a.lastSkip.at > h.at) ? '<br>son tur atlandı (' + hhmm(a.lastSkip.at) + '): ' + esc(a.lastSkip.why) : '') + '</small>');
    } else if (a && a.offReason && a.offReason !== 'elle kapatıldı') {
      p.push('<small class="warn">' + esc(a.offReason) + '</small>');
    }
    if (a && (a.history || []).length) p.push('<div class="list" id="spHist">' + a.history.slice(0, 5).map((h) => '<div>' + hhmm(h.at) + ' · ' + (h.bulunan || 0) + ' bulundu · ' + (h.gercek ? (h.yapilan || 0) + ' pasife alındı' : (h.deneme || 0) + ' deneme') + (h.hata ? ' · ' + h.hata + ' hata' : '') + (/^⚠/.test(h.durum || '') ? ' · <small>' + esc(h.durum) + '</small>' : '') + '</div>').join('') + '</div>');
    p.push('<div class="st' + (/^⚠/.test(S.status) ? ' warn' : '') + '" id="st">' + esc(S.status) + '</div>');
    if (r.done.length || r.dry.length || r.skipped.length || r.errors.length) {
      const sec = (a, t) => a.length ? '<div><b>' + t + ' (' + a.length + ')</b></div>' + a.map((x) => '<div>' + esc(x.name) + ' <small>' + esc(x.reason || '') + '</small></div>').join('') : '';
      p.push('<div class="list" id="spRes">' + sec(r.done, 'Pasife alınan') + sec(r.dry, 'Deneme') + sec(r.skipped, 'Atlanan') + sec(r.errors, 'Hata') + '</div>');
    }
    if (S.notes.length) p.push('<small>' + S.notes.map(esc).join('<br>') + '</small>');
    p.push('<div class="row"><button class="b" data-a="diag">Teşhisi kopyala</button></div></div>');
    bar.className = 'bar' + (minimized ? ' min' : '');
    bar.innerHTML = p.join('');
    placeBar();
  }
  function setAll(off) { for (const t of S.targets) { if (off) S.excluded.add(t.sid); else S.excluded.delete(t.sid); } }
  async function onBarClick(ev) {
    const t = ev.target.closest && ev.target.closest('[data-a]');
    if (!t || t.disabled || t.hasAttribute('disabled')) return;
    const a = t.getAttribute('data-a');
    if (a === 'min') { minimized = !minimized; try { localStorage.setItem('sp_min', minimized ? '1' : '0'); } catch (e) { /* yok */ } render(); }
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
        case 'scan': scan(); setTimeout(done, 50); return true;
        case 'run': run(); setTimeout(done, 50); return true;
        case 'stop': stop(); done(); return false;
        case 'settings': patchSettings(msg.patch); render(); done(); return false;
        case 'exclude': if (msg.all !== undefined) setAll(!!msg.all); else if (msg.on) S.excluded.add(msg.key); else S.excluded.delete(msg.key); render(); done(); return false;
        case 'diag': respond({ text: diag() }); return false;
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
