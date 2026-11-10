// OTOMATİK ÜRETİLDİ — düzenleme: kaynak kategori-temizle/content.js (build-panel-araclari.py kopyalar).
// Kategori Temizle — Perfect Panel > Services sayfasında "pasif VE boş" kategorileri bulur ve (onayınla) siler.
// Öğeleri iç HTML'e değil görünen YAZILARA göre bulur (Actions, Delete category, Delete, Cancel).
// React yeniden çizdiği için hiçbir öğe referansı saklanmaz: her adımda kategori adından yeniden bulunur.
// Silme geri alınamaz: önce yalnızca listeler; her silmeden hemen önce (pasif mi, boş mu, ad aynı mı) yeniden kontrol eder.
(() => {
  if (window.__katTemizle) return;
  window.__katTemizle = true;

  const VERSION = '1.5.0';
  const SETTINGS_KEY = 'kt_settings';
  const DIALOG = '[role=dialog], .modal, .modal-dialog, .modal-content';
  const HANDLE = '.service-table__handle, [class*="__handle"], .handle, .drag-handle';
  const TOGGLE = '.service-table__header-hide-all, [class*="header-hide-all"]';
  const SEL_RE = /(\d+)\s+services?\s+selected/i;
  const ERR_WORDS = /error|fail|hata|cannot|can't|couldn't|unable|denied|not allowed|invalid|xəta|alınmadı|olmadı/i;

  // ---------------------------------------------------------------- yardımcılar
  const norm = (s) => (s || '').replace(/\s+/g, ' ').trim();
  const lower = (s) => norm(s).toLowerCase().replace(/i̇/g, 'i');
  // Arka plan sekmesi: sayfanın zamanlayıcıları kısılır. Her bekleme hem burada hem service worker'da başlar;
  // hangisi önce biterse o sayılır (önde: sayfa, arkada: service worker). Bağlantı koparsa sayfa zamanlayıcısı yeter.
  let port = null; let seq = 0;
  const waiters = new Map();
  function getPort() {
    if (port) return port;
    try {
      port = chrome.runtime.connect({ name: 'kt' });
      port.onMessage.addListener((m) => { const f = m && m.t === 'wake' && waiters.get(m.id); if (f) f(); });
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
  /** İş sürerken sekme otomatik kapatılmasın (Bellek Tasarrufu). */
  function busy(on) { const p = getPort(); if (p) { try { p.postMessage({ t: 'busy', on }); } catch (e) { port = null; } } }
  const btnText = (b) => norm(b.innerText !== undefined ? b.innerText : b.textContent);
  const visible = (el) => !!el && el.isConnected && !!(el.offsetWidth || el.offsetHeight || el.getClientRects().length);
  const catKey = (s) => lower(s).replace(/[^\p{L}\p{N}]+/gu, ' ').replace(/(^| )(new|yeni)(?= |$)/g, ' ').replace(/\s+/g, ' ').trim();
  const isSpecial = (name) => catKey(name) === 'not assigned';
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

  /** Metin + resim emojilerinin alt yazısı. Blok/flex çocuklar arasına boşluk konur ("NEW" rozeti ada yapışmasın). */
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
          if (cs.display === 'none' || cs.visibility === 'hidden') continue; // kapalı Actions menüsü vb.
          out += (cs.display.startsWith('inline') ? '' : ' ') + textWithAlt(n) + ' ';
        }
      }
    }
    return out;
  }

  /** Satır yazısından kategori adı: Actions, baştaki/sondaki süs işaretleri (tutamaç) ve NEW/Yeni rozeti atılır; emoji kalır. */
  function cleanName(raw) {
    let t = norm(raw.replace(/ᴺᴱᵂ/g, ' NEW ').replace(/\bActions\b\s*[▾▼⌄]?/g, '').replace(/\b(?:Expand|Collapse)\s*\(\s*\d+\s*\)/gi, ''));
    for (let i = 0; i < 3; i++) {
      t = t.replace(/^[^\p{L}\p{N}\p{Extended_Pictographic}]+/u, '').replace(/[^\p{L}\p{N}\p{Extended_Pictographic})\]]+$/u, '');
      t = t.replace(/^(?:NEW|YENİ|Yeni)\s+/, '').replace(/\s+(?:NEW|YENİ|Yeni)$/, '');
    }
    return norm(t);
  }

  // ---------------------------------------------------------------- araçlar arası kilit
  // Aynı sayfada birden çok araç (Kategori Temizle / Servis Pasifle / Pasif Servis Taşı) aynı anda listeyi kullanmasın:
  // çalışan araç <html data-panel-arac="…"> yazar. Sayfa yenilenince kendiliğinden temizlenir.
  const ME = 'kt'; const LOCK = 'data-panel-arac';
  const LOCK_NAME = { kt: 'Kategori Temizle', sp: 'Servis Pasifle', pt: 'Pasif Servis Taşı' };
  const lockHolder = () => document.documentElement.getAttribute(LOCK) || '';
  const lockFree = () => { const h = lockHolder(); return !h || h === ME; };
  function lockTake() { if (!lockFree()) return false; document.documentElement.setAttribute(LOCK, ME); return true; }
  function lockDrop() { if (lockHolder() === ME) document.documentElement.removeAttribute(LOCK); }
  /** Elle başlatılan iş: başka araç çalışıyorsa başlamaz; bitince kilidi bırakır. */
  const guarded = (fn) => async (...args) => {
    if (!lockTake()) { setStatus('⚠ Şu an "' + (LOCK_NAME[lockHolder()] || lockHolder()) + '" çalışıyor: önce o bitsin.'); return undefined; }
    if (fn === run) { S.__total = workList().length; S.__t0 = Date.now(); }
    try { return await fn(...args); } finally { if (!S.running) lockDrop(); }
  };

  // ---------------------------------------------------------------- durum
  const S = {
    phase: 'idle', // idle | scanning | ready | deleting
    status: 'Hazır. "Boş pasif kategorileri bul"a bas.',
    running: false,
    stop: false,
    candidates: [], // boş pasif: [{name, id}]
    fulls: [], // dolu pasif: [{name, id, count}]
    notAssigned: null,
    excluded: new Set(), // kullanıcının işaretini kaldırdığı kategoriler (id)
    stats: null,
    notes: [],
    res: { deleted: [], moved: [], dry: [], skipped: [], errors: [], pending: [] },
    refBest: 0,
    hasCounts: false,
    diagNeeded: false,
    log: [],
    seen: { menu: [], dialog: [], toast: [] },
  };
  // mode: 'delete' = boş pasifleri sil · 'move' = dolu pasiflerin servislerini Not Assigned'a taşı (moveDelete: sonra kategoriyi sil)
  const settings = { dryRun: true, max: 50, mode: 'delete', moveDelete: true };
  // Güvenlik: deneme modu kalıcı değil; her sayfa yüklenişinde AÇIK başlar. Yalnızca "en çok N" hatırlanır.
  try { const v = JSON.parse(localStorage.getItem(SETTINGS_KEY) || '{}'); if (v.max) settings.max = Math.max(1, Math.min(1000, parseInt(v.max, 10) || 50)); if (v.mode === 'move' || v.mode === 'delete') settings.mode = v.mode; if (typeof v.moveDelete === 'boolean') settings.moveDelete = v.moveDelete; } catch (e) { /* depolama kapalı */ }
  const saveSettings = () => { try { localStorage.setItem(SETTINGS_KEY, JSON.stringify({ max: settings.max, mode: settings.mode, moveDelete: settings.moveDelete })); } catch (e) { /* yok */ } };
  function patchSettings(p) {
    if (p && typeof p.dryRun === 'boolean') settings.dryRun = p.dryRun;
    if (p && (p.mode === 'move' || p.mode === 'delete') && !S.running) settings.mode = p.mode;
    if (p && typeof p.moveDelete === 'boolean') settings.moveDelete = p.moveDelete;
    if (p && p.max !== undefined) settings.max = Math.max(1, Math.min(1000, parseInt(p.max, 10) || 50));
    saveSettings();
  }

  function log(msg) {
    S.log.push(new Date().toTimeString().slice(0, 8) + ' ' + msg);
    if (S.log.length > 300) S.log.shift();
  }
  function setStatus(t) { S.status = t; log(t); render(); }
  /** Biten işi geçmişe yaz (mini panel → Geçmiş) ve gerçek değişiklik/hata varsa arka plana bildir (masaüstü bildirimi). */
  function recordRun(text) {
    try {
      const r = S.res || {};
      const doneArr = [].concat(r.done || [], r.deleted || [], r.moved || []);
      const entry = { tool: ME, at: Date.now(), dry: !!settings.dryRun, auto: false, text, done: doneArr.length, errors: (r.errors || []).length,
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
  /**
   * Kategori satırları: "Actions" düğmesi olan ama kutucuğu (checkbox) olmayan satırlar (servis satırlarında kutucuk var).
   * Her Actions düğmesinden, başka Actions içermeyen en geniş ataya çıkılır.
   */
  function categoryRows() {
    const isActions = (x) => /^actions\b/i.test(btnText(x));
    const btns = [...document.querySelectorAll('button, a')].filter(isActions);
    const out = [];
    for (const b of btns) {
      let row = b;
      while (row.parentElement && row.parentElement !== document.body) {
        const p = row.parentElement;
        if ([...p.querySelectorAll('button, a')].filter(isActions).length > 1) break;
        row = p;
      }
      if (row.querySelector('input[type=checkbox]')) continue; // servis satırı
      const name = cleanName(textWithAlt(row));
      if (!name) continue;
      // "Expand (N)": N = kategorideki servis sayısı. Yazı yoksa: collapse öğesi boşsa kategori boş (null);
      // öğede başka bir yazı varsa sayı okunamadı = belirsiz (NaN → asla boş sayılmaz).
      const mm = norm(row.textContent).match(/(?:expand|collapse)\s*\(\s*(\d+)\s*\)/i);
      let count;
      if (mm) count = parseInt(mm[1], 10);
      else {
        const ce = row.querySelector('[class*="category-collapse"]');
        count = ce && norm(ce.textContent) ? NaN : null;
      }
      out.push({ el: row, btn: b, name, special: isSpecial(name), count, id: row.id || '' });
    }
    return out;
  }

  /** Kategorinin ad öğesi: önce [class*=category-name]; yoksa en uzun yazılı yaprak (menü, gizli öğe, rozet, Actions, Expand hariç). */
  function nameElOf(row) {
    const named = row.el.querySelector('[class*="category-name"]:not([class*="collapse"])');
    if (named && norm(named.textContent)) {
      let leaf = named;
      while (leaf.children.length === 1) leaf = leaf.children[0];
      return leaf.children.length ? named : leaf;
    }
    let best = null;
    for (const e of row.el.querySelectorAll('*')) {
      if (e.children.length) continue;
      if (e.closest('.dropdown-menu, [role=menu], .dropdown, ul')) continue;
      if (!visible(e)) continue;
      const t = norm(e.textContent);
      if (t.length < 2 || /^(actions|new|yeni|expand|collapse)\b/i.test(t) || /^ᴺᴱᵂ$/.test(t)) continue;
      if (!best || t.length > best.t.length) best = { e, t };
    }
    return best ? best.e : null;
  }

  const lum = (color) => {
    const m = (color || '').match(/(\d+(?:\.\d+)?)/g);
    if (!m || m.length < 3) return null;
    const [r, g, b] = m.map(Number);
    return { l: (0.2126 * r + 0.7152 * g + 0.0722 * b) / 255, a: m.length > 3 ? Number(m[3]) : 1 };
  };

  /** Bir satırın pasiflik ölçümü: açık işaret (sınıf/opacity/Disabled) ya da yazı-arka plan kontrastı k. */
  function measureRow(row) {
    for (let el = row.el, i = 0; el && i < 4; el = el.parentElement, i++) {
      if (/(^|[\s_-])(disabled|inactive|is-disabled|off)($|[\s_-])/i.test(typeof el.className === 'string' ? el.className : '')) return { off: 'sınıf: ' + el.className };
      if (parseFloat(getComputedStyle(el).opacity) < 0.75) return { off: 'soluk (opacity)' };
    }
    if (/\bdisabled\b/i.test(row.name)) return { off: 'adında Disabled' };
    const ne = nameElOf(row);
    if (ne && parseFloat(getComputedStyle(ne).opacity) < 0.75) return { off: 'ad soluk (opacity)' };
    const c = ne ? lum(getComputedStyle(ne).color) : null;
    if (!c) return {};
    let bg = { l: 1, a: 1 };
    for (let el = ne; el; el = el.parentElement) {
      const b = lum(getComputedStyle(el).backgroundColor);
      if (b && b.a > 0.5) { bg = b; break; }
    }
    return { k: Math.abs(c.l - bg.l) * Math.min(1, c.a) };
  }
  /** Ölçümlerden pasif kararı: açık işaret ya da en canlı addan 0.2+ soluk. refBest: tarama sırasında görülen en canlı kontrast. */
  function classify(ms, refBest = 0) {
    const ks = ms.filter((m) => !m.off && m.k !== undefined).map((m) => m.k);
    const best = Math.max(refBest, ks.length ? Math.max(...ks) : 0);
    return { best, why: ms.map((m) => m.off || (m.k !== undefined && m.k < best - 0.2 ? 'soluk yazı (kontrast ' + m.k.toFixed(2) + ' / ' + best.toFixed(2) + ')' : '')) };
  }
  function splitActive(rows, refBest = 0) {
    const { best, why } = classify(rows.map(measureRow), refBest);
    const map = new Map();
    rows.forEach((r, i) => { if (why[i]) map.set(r, why[i]); });
    return { why: map, best };
  }

  const checks = () => [...document.querySelectorAll('input[type=checkbox]')].filter(visible);
  /** Servis satırı kutucukları (başlıktaki "hepsini seç" ve üstteki "N services selected" kutucuğu hariç). */
  const svcChecks = () => checks().filter((c) => c.closest('.service-table__row-service, .service-table__body')
    || (!c.closest('thead, .service-table__header, .service-table__header-wrapper, .mass-actions') && !c.closest('.service-table__category')
      && !SEL_RE.test(norm((c.closest('label') || c.parentElement || c).textContent))));
  /** "Yalnızca kategoriler" görünümü: ekranda hiç servis satırı yok. */
  const inCatView = () => svcChecks().length === 0;

  /** Listenin gerçek kaydırma alanı (iç kutu ya da pencere). */
  function getBox() {
    const r = categoryRows()[0];
    let b = r && (function (from) {
      for (let el = from.parentElement; el && el !== document.body; el = el.parentElement) {
        if (/(auto|scroll)/.test(getComputedStyle(el).overflowY) && el.scrollHeight > el.clientHeight + 40) return el;
      }
      return null;
    })(r.el);
    if (!b) { const c = document.querySelector('.service-table__container'); if (c && c.scrollHeight > c.clientHeight + 40) b = c; }
    return b || document.scrollingElement;
  }

  /**
   * Kaydır ve "scroll" olayını hemen tetikle: arka plandaki sekmede Chrome scroll olayını bir sonraki çizime kadar
   * geciktirir; sanallaştırılmış liste bu olayla yeni satırları çizdiği için beklemeden haber verilir.
   */
  function setScroll(box, top) {
    box.scrollTop = top;
    const target = box === document.scrollingElement ? window : box;
    try { target.dispatchEvent(new Event('scroll')); } catch (e) { /* yok */ }
  }
  function nudgeScroll() { const b = getBox(); try { (b === document.scrollingElement ? window : b).dispatchEvent(new Event('scroll')); } catch (e) { /* yok */ } }

  /**
   * "Yalnızca kategoriler" görünümü: başlıktaki düğme servisleri açıp kapatır. Servisler açıkken panel kategori satırlarını göstermez.
   * Servis satırında kutucuk var; başlıkta bir kutucuk (hepsini seç) her zaman var → >1 kutucuk = servisler açık.
   */
  async function ensureCategoryView() {
    if (inCatView()) return true;
    const t = [...document.querySelectorAll(TOGGLE)].find(visible) || [...document.querySelectorAll('.fa-compress-alt, .fa-expand-alt')].find(visible);
    const globalOpen = t && /fa-compress/.test(typeof t.className === 'string' ? t.className : '');
    // 1) Tümü kapalıyken tek tek açılmış kategoriler ("Select all category services" kategoriyi açar): kendi Collapse öğesine bas.
    if (!globalOpen) {
      const opened = [...document.querySelectorAll('[class*="category-collapse"].show-category')].filter(visible);
      for (const o of opened) (o.querySelector('[class*="collapse-name"], a') || o).click();
      if (opened.length && await waitFor(inCatView, 3000, 100)) return true;
    }
    // 2) Başlıktaki tümünü aç/kapa düğmesi; ters çalışırsa geri al.
    if (!t) { log('aç/kapa düğmesi bulunamadı'); return false; }
    const before = svcChecks().length;
    t.click();
    let ok = await waitFor(() => inCatView() && categoryRows().length > 0, 6000, 100);
    if (!ok && svcChecks().length > before) { t.click(); await sleep(400); }
    if (!ok) {
      const opened = [...document.querySelectorAll('[class*="category-collapse"].show-category')].filter(visible);
      for (const o of opened) (o.querySelector('[class*="collapse-name"], a') || o).click();
      ok = await waitFor(() => inCatView() && categoryRows().length > 0, 3000, 100);
    }
    if (!ok) log('kategori görünümüne geçilemedi');
    return !!ok;
  }

  /**
   * Listeyi baştan sona gez (panel listeyi sanallaştırabilir: her an yalnızca ekrandaki satırlar DOM'da).
   * Her adımda onStep() çağrılır; true dönerse durur. Sona gelince yeni satır yüklenmesini kısa süre bekler.
   */
  async function walk(onStep) {
    const box = getBox();
    setScroll(box, 0);
    await sleep(350);
    if (onStep()) return true;
    let stable = 0;
    for (let i = 0; i < 3000 && !S.stop; i++) {
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

  /** Kategoriyi bul: id (category-NNN) varsa yalnızca id ile; yoksa ada göre. */
  function findByName(name, id) {
    const k = catKey(name);
    const rows = categoryRows();
    return { rows, hits: id ? rows.filter((r) => r.id === id) : rows.filter((r) => catKey(r.name) === k) };
  }

  /** Kategoriyi bul; DOM'da yoksa (deep) kategori görünümüne geç ve bulana kadar listeyi gez. */
  async function locate(name, id, deep) {
    let f = findByName(name, id);
    if (f.hits.length || !deep) return f;
    await ensureCategoryView();
    await walk(() => findByName(name, id).hits.length > 0);
    return findByName(name, id);
  }

  /** Şu an ekranda: kategori hâlâ var mı, tek mi, ad aynı mı, pasif mi, boş mu? (boş = "Expand (N)" yok ya da N=0) */
  /** full=true: taşınacak dolu kategori (servis sayısı > 0 olmalı); değilse boş olmalı. */
  function check(name, id, full) {
    const { rows, hits } = findByName(name, id);
    if (!hits.length) return { ok: false, reason: 'satır bulunamadı (kimlik ' + (id || '-') + '; ekranda ' + rows.length + ' kategori; ilk: "' + (rows[0] ? rows[0].name.slice(0, 40) : '-') + '")' };
    if (hits.length > 1) return { ok: false, reason: 'aynı kimlikli birden çok satır' };
    const row = hits[0];
    if (row.special) return { ok: false, reason: 'özel satır (Not Assigned)' };
    if (catKey(row.name) !== catKey(name)) return { ok: false, reason: 'ad değişmiş: "' + row.name.slice(0, 60) + '" ≠ "' + name.slice(0, 60) + '"' };
    if (!full && !inCatView()) return { ok: false, reason: 'liste kategori görünümünde değil' };
    const { why } = splitActive(rows, S.refBest);
    if (!why.has(row)) return { ok: false, reason: 'artık pasif görünmüyor (aktif)' };
    if (Number.isNaN(row.count)) return { ok: false, reason: 'servis sayısı okunamadı' };
    if (full) return row.count > 0 ? { ok: true, row, why: why.get(row) } : { ok: false, reason: 'kategori artık dolu değil' };
    if (row.count > 0) return { ok: false, reason: 'içinde ' + row.count + ' servis var' };
    if (row.count === null && !S.hasCounts) return { ok: false, reason: 'servis sayısı (Expand) doğrulanamadı' };
    return { ok: true, row, why: why.get(row) };
  }

  // ---------------------------------------------------------------- menü / pencere / bildirim
  /** Görünür, en derin, yazısı re'ye tam uyan öğeler (menüler portal ile body sonunda ya da satır içinde olabilir). */
  function itemsByText(re) {
    const all = [...document.querySelectorAll('a, button, li, span, div, p, [role=menuitem], [role=option]')].filter((e) => visible(e) && re.test(norm(e.textContent)));
    return all.filter((e) => !all.some((o) => o !== e && e.contains(o)));
  }
  const deleteItems = () => itemsByText(/^delete\s+category$/i);
  const MENU_ANY = /^(edit category|enable category|disable category|select all category services|delete category|assign category|edit rates|delete all)$/i;
  const STATUS_RE = /^(enable|disable)\s+category$/i;
  const click = (el) => (el.closest('a, button, li, [role=menuitem], [role=option]') || el).click();

  /**
   * Kategorinin Actions menüsünü aç ve want yazılı öğeyi bul. Menüdeki "Enable/Disable category" yazısı panelin kendi
   * durum bilgisidir: pasif kategoride "Enable category" yazar. status: 'enable' | 'disable' | null.
   */
  async function openRowMenu(row, want, id) {
    const before = new Set(itemsByText(want));
    const stBefore = new Set(itemsByText(STATUS_RE));
    const bodyBefore = new Set(document.body.children);
    row.btn.click();
    const item = await waitFor(() => { const v = itemsByText(want).filter((x) => !before.has(x)); return v.length ? v[v.length - 1] : null; }, 2500, 60);
    const fresh = [...document.body.children].filter((x) => !bodyBefore.has(x) && visible(x)).map((x) => norm(x.textContent).slice(0, 200));
    const sts = itemsByText(STATUS_RE).filter((x) => !stBefore.has(x));
    // durum öğesi aynı menüde olmalı
    const menuOf = (el) => el && (el.closest('ul, [role=menu], .dropdown-menu') || el.parentElement);
    const st = item ? sts.find((x) => menuOf(x) && menuOf(x).contains(item)) || (sts.length === 1 ? sts[0] : null) : (sts[sts.length - 1] || null);
    remember('menu', fresh.length ? fresh : (item ? [norm(menuOf(item).textContent).slice(0, 200)] : []));
    const owner = item ? categoryRows().find((r) => r.el.contains(item)) : null;
    return { item, status: st ? lower(norm(st.textContent)).split(' ')[0] : null, wrongOwner: owner && id && owner.id !== id ? owner.name : null };
  }

  /** Onay penceresi: pencere içinde yalnızca "Delete" yazılı düğme. */
  function findConfirm() {
    const btns = [...document.querySelectorAll('button, a, [role=button], input[type=submit]')].filter((b) => visible(b) && lower(b.value || btnText(b)) === 'delete');
    for (const btn of btns) {
      const dlg = btn.closest(DIALOG);
      if (!dlg) continue;
      const cancel = [...dlg.querySelectorAll('button, a, [role=button]')].find((b) => visible(b) && /^(cancel|close|no|iptal|bağla)$/i.test(btnText(b)));
      return { btn, dlg, cancel, text: norm(dlg.textContent) };
    }
    return null;
  }
  const anyDialog = () => [...document.querySelectorAll(DIALOG)].some(visible);

  function readToasts() {
    const sel = '[role=alert], [role=status], [class*=toast], [class*=Toast], [class*=notif], [class*=snack], [class*=alert], [class*=flash]';
    return [...document.querySelectorAll(sel)].filter(visible).map((e) => norm(e.textContent)).filter((t) => t && t.length < 300);
  }

  function pressEscape() {
    for (const t of [document.activeElement, document.body, document]) {
      if (!t) continue;
      t.dispatchEvent(new KeyboardEvent('keydown', { key: 'Escape', code: 'Escape', keyCode: 27, bubbles: true }));
    }
  }
  async function closeMenu() {
    if (!itemsByText(MENU_ANY).length) return;
    pressEscape();
    await sleep(150);
    if (itemsByText(MENU_ANY).length) document.body.click();
    await sleep(150);
  }
  async function closeDialog(conf) {
    if (conf && conf.cancel) conf.cancel.click(); else pressEscape();
    let closed = await waitFor(() => !findConfirm(), 3000, 100);
    if (!closed) { pressEscape(); closed = await waitFor(() => !findConfirm(), 2000, 100); }
    return !!closed;
  }

  // ---------------------------------------------------------------- tarama
  function fail(msg) {
    S.phase = 'idle';
    S.diagNeeded = true;
    setStatus('⚠ ' + msg);
  }

  async function scan() {
    if (S.running) return;
    S.running = true; S.stop = false; S.phase = 'scanning'; busy(true);
    S.candidates = []; S.fulls = []; S.notAssigned = null; S.excluded = new Set(); S.stats = null; S.notes = []; S.diagNeeded = false;
    S.res = { deleted: [], moved: [], dry: [], skipped: [], errors: [], pending: [] };
    try {
      if (!onServicesList()) return fail('Bu özellik yalnızca /admin/services sayfasında çalışır.');
      if (anyDialog()) return fail('Açık bir pencere var; önce kapat.');
      setStatus('Kategori görünümüne geçiliyor…');
      if (!(await ensureCategoryView())) return fail('"Yalnızca kategoriler" görünümüne geçilemedi; hiçbir şey silinmez.');
      setStatus('Liste baştan sona geziliyor…');
      const col = new Map();
      let box = null; let boxTop = 0;
      const collect = () => {
        box = box || getBox();
        const bt = box === document.scrollingElement ? 0 : box.getBoundingClientRect().top;
        boxTop = bt;
        for (const r of categoryRows()) {
          const off = Math.round((r.el.getBoundingClientRect().top - bt + box.scrollTop) / 6);
          const key = r.id || (catKey(r.name) + '@' + off);
          if (!col.has(key)) col.set(key, { name: r.name, id: r.id, special: r.special, count: r.count, m: measureRow(r) });
        }
        S.status = 'Liste geziliyor… ' + col.size + ' kategori'; render();
        return false;
      };
      await walk(collect);
      if (S.stop) { S.phase = 'idle'; return setStatus('Durduruldu; liste eksik olabilir, tarama iptal.'); }
      if (!inCatView()) return fail('Gezinirken servisler açıldı; yeniden dene.');
      const all = [...col.values()];
      const real = all.filter((e) => !e.special);
      if (!real.length) return fail('Kategori satırı bulunamadı.');
      const { best, why: whyArr } = classify(real.map((e) => e.m), 0);
      S.refBest = best;
      const pasif = real.filter((e, i) => whyArr[i]);
      if (real.length >= 3 && pasif.length === real.length) return fail('Hepsi pasif görünüyor; renk ayrımına güvenemedim, hiçbir şey silinmez.');
      S.hasCounts = real.some((e) => e.count > 0);
      const unclear = real.filter((e) => Number.isNaN(e.count)).length;
      if (unclear) S.notes.push(unclear + ' satırda servis sayısı okunamadı; bunlar boş sayılmaz.');
      if (!S.hasCounts) return fail('Hiçbir satırda "Expand (N)" görünmedi; boşluğu doğrulayamam, hiçbir şey silinmez.');
      let dolu = 0;
      real.forEach((e, i) => {
        if (!whyArr[i]) return;
        if (e.count > 0) { dolu++; S.fulls.push({ name: e.name, id: e.id, count: e.count }); return; }
        if (Number.isNaN(e.count)) return; // sayı okunamadı: boş sayılmaz
        S.candidates.push({ name: e.name, id: e.id });
      });
      const na = all.filter((e) => e.special);
      S.notAssigned = na.length === 1 ? na[0] : null;
      // Not Assigned satırının listede görünmesi gerekmez (Actions düğmesi olmayabilir): hedef Assign penceresinde seçilir ve orada tek bir "Not Assigned" seçeneği şarttır.
      if (na.length > 1) S.notes.push('Listede birden çok "Not Assigned" satırı var.');
      S.stats = { kategori: real.length, aktif: real.length - pasif.length, pasif: pasif.length, pasifDolu: dolu, bos: S.candidates.length, servis: S.fulls.reduce((a, f) => a + f.count, 0) };
      setScroll(box, 0);
      S.phase = 'ready';
      setStatus(scanSummary());
    } catch (e) {
      log(String(e && e.stack).slice(0, 400)); fail('Tarama hatası: ' + (e && e.message) + ' [' + String((e && e.stack || '').split('\n')[1] || '').trim().slice(-60) + ']');
    } finally {
      S.running = false; busy(false);
      render();
    }
  }

  function scanSummary() {
    if (settings.mode === 'move') {
      return S.fulls.length ? S.fulls.length + ' dolu pasif kategori bulundu (' + S.stats.servis + ' servis). Henüz hiçbir şey taşınmadı.' : 'Taşınacak dolu pasif kategori yok.';
    }
    return S.candidates.length ? S.candidates.length + ' boş pasif kategori bulundu. Henüz hiçbir şey silinmedi.' : 'Silinecek boş pasif kategori yok.';
  }

  // ---------------------------------------------------------------- silme
  const result = (kind, reason) => ({ kind, reason });

  /** Kategoriyi bul + kategori görünümünde olduğundan emin ol; React yeniden çizerse birkaç kez dene. */
  async function prepare(name, id) {
    for (let a = 0; a < 3; a++) {
      if (!(await ensureCategoryView())) return result('error', 'kategori görünümüne geçilemedi');
      const f = await locate(name, id, true);
      if (!f.hits.length) return result('skip', 'bulunamadı (silinmiş ya da liste yüklenmedi)');
      if (f.hits.length > 1) return result('skip', 'aynı kimlikli birden çok satır');
      if (inCatView()) return { kind: 'ok', row: f.hits[0] };
    }
    return result('skip', 'yeniden çizimden sonra kategori bulunamadı');
  }

  async function processOne(name, id) {
    const k = catKey(name);
    const p = await prepare(name, id);
    if (p.kind !== 'ok') return p;
    p.row.el.scrollIntoView({ block: 'center' });
    nudgeScroll();
    await waitFor(() => findByName(name, id).hits.length === 1, 3000, 100);
    await sleep(300);

    // 1) silmeden önce yeniden kontrol
    const c1 = check(name, id);
    if (!c1.ok) return result('skip', c1.reason);

    // 2) Actions → (panel "Enable category" diyorsa = pasif) → Delete category
    const m = await openRowMenu(c1.row, /^delete\s+category$/i, id);
    if (m.status === 'disable') { await closeMenu(); return result('skip', 'panel menüsü kategoriyi AKTİF gösteriyor ("Disable category")'); }
    if (!m.status) { await closeMenu(); S.diagNeeded = true; return result('skip', 'menüde "Enable category" görülmedi; pasif olduğu doğrulanamadı'); }
    if (!m.item) { await closeMenu(); S.diagNeeded = true; return result('error', '"Delete category" menüde bulunamadı'); }
    if (m.wrongOwner) { await closeMenu(); return result('skip', 'menü başka kategoriye ait (' + m.wrongOwner + ')'); }
    click(m.item);

    // 3) onay penceresi
    const conf = await waitFor(findConfirm, 4000, 60);
    if (!conf) { await closeMenu(); S.diagNeeded = true; return result('error', 'onay penceresi (Delete düğmeli) açılmadı'); }
    remember('dialog', [conf.text.slice(0, 200)]);
    const dk = catKey(conf.text);
    if (!dk.includes(k)) {
      const other = findByName(name).rows.find((r) => !r.special && catKey(r.name).length >= 3 && catKey(r.name) !== k && !k.includes(catKey(r.name)) && dk.includes(catKey(r.name)));
      if (other) { await closeDialog(conf); return result('skip', 'pencerede başka kategori adı var: ' + other.name); }
      log('pencerede kategori adı görünmedi; menü bu satırın Actions düğmesinden açıldı, devam');
    }

    // 4) pencere açıkken son kontrol (arka plan hâlâ DOM'da)
    const c2 = check(name, id);
    if (!c2.ok) { await closeDialog(conf); return result('skip', 'son anda değişti: ' + c2.reason); }

    // 5) deneme modu: iptal et
    if (settings.dryRun) {
      const closed = await closeDialog(conf);
      if (!closed) { S.diagNeeded = true; return { kind: 'error', reason: 'deneme: pencere kapanmadı', fatal: true }; }
      return result('dry', 'onay penceresine kadar gidildi, iptal edildi');
    }

    // 6) gerçek silme
    const seenToasts = [];
    const poll = (fn) => () => { seenToasts.push(...readToasts()); return fn(); };
    conf.btn.click();
    const closed = await waitFor(poll(() => !findConfirm()), 12000, 100);
    if (!closed) {
      const cur = findConfirm();
      await closeDialog(cur);
      S.diagNeeded = true;
      return result('error', 'onay penceresi kapanmadı (sunucu cevap vermedi?)');
    }
    await sleep(500);
    seenToasts.push(...readToasts());
    remember('toast', [...new Set(seenToasts)]);
    const errToast = [...new Set(seenToasts)].find((t) => ERR_WORDS.test(t));
    await waitFor(() => findByName(name, id).hits.length === 0, 4000, 150);
    if (findByName(name, id).hits.length) return result('error', 'satır silinmedi' + (errToast ? ' — panel: "' + errToast.slice(0, 100) + '"' : ''));
    const g = await locate(name, id, true); // listeyi yeniden çizdiyse: tüm liste yüklenip gerçekten yok mu?
    if (g.hits.length) return result('error', 'satır hâlâ listede' + (errToast ? ' — panel: "' + errToast.slice(0, 100) + '"' : ''));
    return result('deleted', errToast ? 'silindi (panel bildirimi: ' + errToast.slice(0, 80) + ')' : 'silindi');
  }

  // ---------------------------------------------------------------- dolu pasif: servisleri Not Assigned'a taşı, sonra sil
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
    for (const c of svcChecks()) if (c.checked) c.click();
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
      const opt = [...native.options].filter((o) => isSpecial(o.textContent));
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
        const os = itemsByText(/^not assigned$/i).filter((o) => !o.closest('.service-table__category, .service-table__row, #kt-host')
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

  /** Dolu pasif kategori: tüm servislerini seç → toplu Actions → Assign category → Not Assigned → Save → doğrula → boş kategoriyi sil. */
  async function processMove(name, id) {
    const p = await prepare(name, id);
    if (p.kind !== 'ok') return p;
    p.row.el.scrollIntoView({ block: 'center' });
    nudgeScroll();
    await waitFor(() => findByName(name, id).hits.length === 1, 3000, 100);
    await sleep(300);
    const c1 = check(name, id, true);
    if (!c1.ok) return result('skip', c1.reason);
    const n = c1.row.count;
    if (!(await clearSelection())) return { kind: 'error', reason: 'önceki seçim temizlenemedi', fatal: true };

    // 1) kategori Actions: panel "Enable category" demeli; "Select all category services"
    const m = await openRowMenu(c1.row, /^select all category services$/i, id);
    if (m.status === 'disable') { await closeMenu(); return result('skip', 'panel menüsü kategoriyi AKTİF gösteriyor ("Disable category")'); }
    if (!m.status) { await closeMenu(); S.diagNeeded = true; return result('skip', 'menüde "Enable category" görülmedi; pasif olduğu doğrulanamadı'); }
    if (!m.item) { await closeMenu(); S.diagNeeded = true; return result('error', '"Select all category services" menüde bulunamadı'); }
    if (m.wrongOwner) { await closeMenu(); return result('skip', 'menü başka kategoriye ait (' + m.wrongOwner + ')'); }
    click(m.item);

    // 2) seçilen sayı = kategorideki servis sayısı
    const sel = await waitFor(() => { const x = selection(); return x && x.n > 0 ? x : null; }, 5000, 100);
    if (!sel) { await clearSelection(); S.diagNeeded = true; return result('error', '"N services selected" çubuğu görünmedi'); }
    await sleep(300);
    const sel2 = selection();
    if (!sel2 || sel2.n !== n) { await clearSelection(); await ensureCategoryView(); return result('skip', 'seçilen servis sayısı (' + (sel2 ? sel2.n : '?') + ') ≠ kategorideki (' + n + ')'); }
    if (!sel2.btn) { await clearSelection(); S.diagNeeded = true; return result('error', 'seçim çubuğunda Actions düğmesi bulunamadı'); }
    if (S.stop) { await clearSelection(); await ensureCategoryView(); return result('skip', 'durduruldu (taşımadan önce)'); }

    // 3) toplu Actions → yalnızca tam "Assign category" (asla "Delete all")
    const before = new Set(itemsByText(/^assign category$/i));
    sel2.btn.click();
    const item = await waitFor(() => { const v = itemsByText(/^assign category$/i).filter((x) => !before.has(x) && !x.closest('.service-table__category, .service-table__row')); return v.length ? v[v.length - 1] : null; }, 2500, 60);
    if (!item) { await closeMenu(); await clearSelection(); S.diagNeeded = true; return result('error', '"Assign category" toplu menüde bulunamadı'); }
    remember('menu', [norm((item.closest('ul, [role=menu], .dropdown-menu') || item.parentElement).textContent).slice(0, 200)]);
    click(item);

    // 4) pencere: Not Assigned seç
    const a = await waitFor(findAssign, 4000, 80);
    if (!a) { await closeMenu(); await clearSelection(); S.diagNeeded = true; return result('error', '"Assign category" penceresi açılmadı'); }
    remember('dialog', [a.text.slice(0, 200)]);
    const perr = await pickNotAssigned(a);
    if (perr) { await closeAssign(); await clearSelection(); await ensureCategoryView(); S.diagNeeded = true; return result('error', perr); }
    const sel3 = selection();
    if (sel3 && sel3.n !== n) { await closeAssign(); await clearSelection(); await ensureCategoryView(); return result('skip', 'pencere açıkken seçim değişti (' + sel3.n + ' ≠ ' + n + ')'); }
    const c2 = check(name, id, true);
    if (!c2.ok) { await closeAssign(); await clearSelection(); await ensureCategoryView(); return result('skip', 'son anda değişti: ' + c2.reason); }

    // 5) deneme: Close
    if (settings.dryRun || S.stop) {
      const closed = await closeAssign();
      const cleared = await clearSelection();
      await ensureCategoryView();
      if (!closed || !cleared) { S.diagNeeded = true; return { kind: 'error', reason: 'deneme: pencere kapanmadı ya da seçim temizlenemedi', fatal: true }; }
      if (S.stop) return result('skip', 'durduruldu (taşımadan önce)');
      return result('dry', n + ' servis → "Not Assigned" seçildi, Close' + (settings.moveDelete ? ' (sonra kategori silinecekti)' : ''));
    }

    // 6) Save changes → pencere kapansın → kategori boşalsın
    const seen = [];
    const fa = findAssign();
    if (!fa || !enabled(fa.save)) { await closeAssign(); await clearSelection(); return result('error', 'Save changes etkin değil'); }
    fa.save.click();
    // Save changes → panel onay penceresi açar ("Assign to category … N services … Not Assigned?") ya da doğrudan kapanır
    const next = await waitFor(() => findAssignConfirm() || (!findAssign() ? 'closed' : null), 8000, 100);
    if (!next) { await closeAssign(); await clearSelection(); S.diagNeeded = true; return result('error', 'Save changes sonrası onay penceresi gelmedi, Assign penceresi de kapanmadı'); }
    if (next !== 'closed') {
      remember('dialog', [next.text.slice(0, 200)]);
      const bad = next.n !== n ? 'onay penceresindeki sayı (' + next.n + ') ≠ ' + n : !isSpecial(next.target || '') ? 'onay penceresindeki hedef "' + (next.target || '?') + '" (Not Assigned değil)' : '';
      const c3 = check(name, id, true);
      if (bad || !c3.ok || S.stop) {
        await cancelAssignConfirm(); await closeAssign(); await clearSelection(); await ensureCategoryView();
        if (S.stop) return result('skip', 'durduruldu (son onaydan önce)');
        S.diagNeeded = !!bad;
        return result('skip', bad || ('son anda değişti: ' + c3.reason));
      }
      next.ok.click();
    }
    const closed = await waitFor(() => { seen.push(...readToasts()); return !findAssign() && !findAssignConfirm(); }, 15000, 120);
    if (!closed) { await cancelAssignConfirm(); await closeAssign(); await clearSelection(); S.diagNeeded = true; return result('error', 'Assign penceresi kapanmadı (sunucu cevap vermedi?)'); }
    await sleep(500);
    seen.push(...readToasts());
    remember('toast', [...new Set(seen)]);
    const errToast = [...new Set(seen)].find((t) => ERR_WORDS.test(t));
    const emptied = () => { const f = findByName(name, id); return f.hits.length === 1 && f.hits[0].count === null; };
    let ok = await waitFor(emptied, 6000, 200);
    if (!ok) {
      await clearSelection();
      await ensureCategoryView();
      const g = await locate(name, id, true);
      ok = g.hits.length === 1 && g.hits[0].count === null;
      if (!ok) return result('error', 'taşıma doğrulanamadı' + (g.hits[0] && g.hits[0].count > 0 ? ' (hâlâ ' + g.hits[0].count + ' servis)' : '') + (errToast ? ' — panel: "' + errToast.slice(0, 100) + '"' : ''));
    }
    S.res.moved.push({ name, reason: n + ' servis → Not Assigned' });
    render();
    await clearSelection();
    if (!settings.moveDelete) { await ensureCategoryView(); return result('moved', n + ' servis → Not Assigned (kategori bırakıldı)'); }
    if (S.stop) return result('skip', n + ' servis taşındı; silme durduruldu');

    // 7) artık boş: normal silme akışı (yeniden kontrol + Enable category kapısı + Delete)
    const r = await processOne(name, id);
    if (r.kind === 'deleted') return result('deleted', n + ' servis Not Assigned\'a taşındı, sonra silindi');
    return { kind: r.kind === 'skip' ? 'skip' : 'error', reason: 'taşındı ama silinmedi: ' + r.reason, fatal: r.fatal };
  }

  /** Çalıştırılacak liste: seçili moda göre (boş pasifler ya da dolu pasifler), işareti kaldırılanlar hariç. */
  const keyOf = (x) => x.id || x.name;
  function workList() {
    const keep = (x) => !S.excluded.has(keyOf(x)) && !isProt(x);
    if (settings.mode === 'move') return S.fulls.filter(keep).map((f) => ({ name: f.name, id: f.id, count: f.count }));
    return S.candidates.filter(keep).map((c) => ({ name: c.name, id: c.id, count: 0 }));
  }

  async function run() {
    if (S.running || S.phase !== 'ready' || !workList().length) return;
    if (anyDialog()) return fail('Açık bir pencere var; önce kapat.');
    S.running = true; S.stop = false; S.phase = 'deleting'; S.diagNeeded = false; busy(true);
    S.res = { deleted: [], moved: [], dry: [], skipped: [], errors: [], pending: [] };
    if (selection() && !(await clearSelection())) return fail('Panelde seçili servisler var ve temizlenemedi; önce seçimi kaldır.');
    const list = workList();
    const max = settings.max;
    let done = 0; let consecutiveErrors = 0; let i = 0;
    try {
      for (; i < list.length; i++) {
        if (S.stop) break;
        if (done >= max) { S.notes.push('En çok ' + max + ' sınırına ulaşıldı.'); break; }
        const name = list[i].name;
        setStatus((settings.dryRun ? '[DENEME] ' : '') + '(' + (i + 1) + '/' + list.length + ') ' + (list[i].count ? (settings.moveDelete ? 'taşı + sil: ' : 'taşı: ') : 'sil: ') + name);
        let r;
        try { r = list[i].count ? await processMove(name, list[i].id) : await processOne(name, list[i].id); } catch (e) { r = result('error', 'beklenmeyen hata: ' + (e && e.message)); }
        log(name + ' → ' + r.kind + (r.reason ? ' (' + r.reason + ')' : ''));
        if (r.kind === 'deleted') { S.res.deleted.push({ name, reason: r.reason }); done++; consecutiveErrors = 0; }
        else if (r.kind === 'dry') { S.res.dry.push({ name, reason: r.reason }); done++; consecutiveErrors = 0; }
        else if (r.kind === 'moved') { done++; consecutiveErrors = 0; }
        else if (r.kind === 'skip') { S.res.skipped.push({ name, reason: r.reason }); consecutiveErrors = 0; }
        else { S.res.errors.push({ name, reason: r.reason }); consecutiveErrors++; }
        render();
        if (r.fatal || consecutiveErrors >= 3) { S.notes.push('Üst üste hata: işlem durduruldu.'); S.diagNeeded = true; i++; break; }
      }
      S.res.pending = list.slice(i).map((c) => c.name);
      const r = S.res;
      const bits = [r.deleted.length + ' silindi', r.dry.length + ' deneme', r.skipped.length + ' atlandı', r.errors.length + ' hata'];
      if (r.moved.length) bits.splice(1, 0, r.moved.length + ' taşındı');
      if (r.pending.length) bits.push(r.pending.length + ' işlenmedi');
      S.phase = 'idle'; S.candidates = []; S.fulls = [];
      setStatus((S.stop ? 'Durduruldu. ' : 'Bitti. ') + bits.join(', ') + '.');
      recordRun(S.status);
    } catch (e) {
      fail('Silme hatası: ' + (e && e.message));
    } finally {
      S.running = false; S.stop = false; busy(false);
      render();
    }
  }

  const gScan = guarded(scan); const gRun = guarded(run);
  function stop() {
    if (!S.running) return;
    S.stop = true;
    setStatus('Durdurma istendi; sıradaki silmeden önce duracak…');
  }

  // ---------------------------------------------------------------- teşhis
  /** İlk Actions düğmelerinin ata zinciri: her basamakta etiket.sınıf, kutucuk sayısı, Actions sayısı, kısa yazı. */
  function actionsChains() {
    const isA = (x) => /^actions\b/i.test(btnText(x));
    const all = [...document.querySelectorAll('button, a, [role=button], span, div')].filter((x) => x.children.length <= 2 && /^actions\b/i.test(norm(x.textContent)) && visible(x));
    const pick = [all[0], all[1], all[all.length - 1]].filter(Boolean);
    return { actionsAday: all.length, tipler: [...new Set(all.map((x) => x.tagName))], zincirler: pick.map((b) => {
      const out = []; let el = b;
      for (let i = 0; i < 7 && el && el !== document.body; i++, el = el.parentElement) {
        out.push((el.tagName + '.' + (typeof el.className === 'string' ? el.className : '')).slice(0, 70) + ' | kutucuk=' + el.querySelectorAll('input[type=checkbox]').length + ' actions=' + [...el.querySelectorAll('button, a')].filter(isA).length + ' | ' + norm(el.textContent).slice(0, 50));
      }
      return out;
    }) };
  }

  /** Panelin satır türleri: service-table__* sınıfları, sayıları ve her türün ilk örneğinin kısa HTML'i (kutucuk sayısı, Actions sayısı). */
  function rowKinds() {
    const seen = new Map();
    for (const el of document.querySelectorAll('[class*="service-table__"]')) {
      const cls = (typeof el.className === 'string' ? el.className : '').split(/\s+/).filter((c) => /^service-table__(row|body|head|header|category|group|cat)/.test(c)).sort().join(' ');
      if (!cls) continue;
      const e = seen.get(cls) || { sayi: 0, ornek: null };
      e.sayi++;
      if (!e.ornek) e.ornek = { kutucuk: el.querySelectorAll('input[type=checkbox]').length, actions: [...el.querySelectorAll('button, a')].filter((x) => /^actions\b/i.test(btnText(x))).length, yazi: norm(el.textContent).slice(0, 60), html: el.outerHTML.replace(/\s+/g, ' ').replace(/<svg[\s\S]*?<\/svg>/g, '<svg/>').slice(0, 450) };
      seen.set(cls, e);
    }
    const sc = getBox();
    return { turler: Object.fromEntries(seen), kaydirma: { pencereY: Math.round(scrollY), sayfaYuksekligi: document.documentElement.scrollHeight, icKutu: sc ? sc.tagName + '.' + (typeof sc.className === 'string' ? sc.className : '') : 'yok' }, kategoriYaziAday: [...document.querySelectorAll('.service-table__row')].filter((r) => !r.querySelector('input[type=checkbox]')).slice(0, 3).map((r) => r.outerHTML.replace(/\s+/g, ' ').slice(0, 600)) };
  }

  function diag() {
    const rows = categoryRows();
    const { why } = splitActive(rows, S.refBest);
    const tail = rows.slice(-8).map((r, i, a) => {
      const ne = nameElOf(r);
      return {
        ad: r.name.slice(0, 80), pasif: why.get(r) || '', renk: ne ? getComputedStyle(ne).color : '',
        sinif: (typeof r.el.className === 'string' ? r.el.className : '').slice(0, 80),
        servisSayisi: r.count, tutamac: !!r.el.querySelector(HANDLE),
        html: r.el.outerHTML.replace(/\s+/g, ' ').replace(/<svg[\s\S]*?<\/svg>/g, '<svg/>').slice(0, 500),
      };
    });
    return JSON.stringify({
      surum: VERSION, adres: location.pathname, tema: getComputedStyle(document.body).backgroundColor,
      kategoriSayisi: rows.length, gorunenKutucuk: checks().length, servisSayisiVar: S.hasCounts, acmaDugmesi: !![...document.querySelectorAll(TOGGLE)].find(visible),
      durum: S.status, istatistik: S.stats, aday: S.candidates.map((c) => c.name), dolu: S.fulls.map((f) => f.name + ' (' + f.count + ')'), secim: (() => { const x = selection(); return x ? { n: x.n, actions: !!x.btn, kutucuk: !!x.cb } : null; })(), sonuc: S.res, notlar: S.notes,
      actionsZinciri: actionsChains(), satirTurleri: rowKinds(), sonKategoriler: tail, menuYazilari: S.seen.menu, pencereYazilari: S.seen.dialog, bildirimler: S.seen.toast, adimGunlugu: S.log.slice(-40),
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

  // ---------------------------------------------------------------- arayüz (shadow DOM: sayfa aramalarına karışmaz)
  let host = null; let root = null; let minimized = false;
  try { const m = localStorage.getItem('kt_min'); minimized = m === '1'; } catch (e) { /* yok */ }

  // Tasarım (Panel Araçları 2): nötr yüzeyler, tek vurgu rengi (çivit), kırmızı yalnız gerçek/geri alınamaz işte.
  const CSS = `
    :host{all:initial;--bg:#ffffff;--sf:#f6f7f9;--sf2:#eef0f3;--bd:#e3e6ea;--tx:#111827;--mt:#6b7280;--ac:#4f46e5;--ac-sf:#eef2ff;--ac-tx:#3730a3;
      --on-ac:#ffffff;--dn:#dc2626;--dn-sf:#fef2f2;--ok:#047857;--ok-sf:#ecfdf5;--wr:#b45309;--wr-sf:#fffbeb;--sh:0 1px 2px rgba(16,24,40,.06),0 12px 32px rgba(16,24,40,.14)}
    @media (prefers-color-scheme:dark){:host{--bg:#14161b;--sf:#1b1e25;--sf2:#22262f;--bd:#2b303b;--tx:#e6e8ec;--mt:#9aa1ad;--ac:#818cf8;--ac-sf:#232544;--ac-tx:#c7d2fe;--on-ac:#0f1020;
      --dn:#f87171;--dn-sf:#2c1517;--ok:#34d399;--ok-sf:#0f2a22;--wr:#fbbf24;--wr-sf:#2b2210;--sh:0 1px 2px rgba(0,0,0,.4),0 16px 40px rgba(0,0,0,.5)}}
    *{box-sizing:border-box}
    .bar{position:fixed;right:12px;bottom:12px;z-index:2147483646;width:360px;max-width:calc(100vw - 24px);max-height:calc(100vh - 24px);display:flex;flex-direction:column;
      font:13px/1.45 ui-sans-serif,system-ui,-apple-system,"Segoe UI",Roboto,Arial,sans-serif;color:var(--tx);background:var(--bg);border:1px solid var(--bd);border-radius:12px;box-shadow:var(--sh);overflow:hidden}
    :host([data-embed]) .bar{position:static;width:100%;max-width:none;max-height:none;border:0;border-radius:0;box-shadow:none;background:transparent}
    :host([data-embed]) .hd .ic,:host([data-embed]) .hd [data-a=min]{display:none}:host([data-embed]) .hd{padding:10px 12px 0;border-bottom:0}:host([data-embed]) .bar.min .body{display:flex}
    .bar.min{width:auto}.bar.min .body{display:none}
    .hd{flex:none;display:flex;align-items:center;gap:8px;padding:10px 10px 10px 12px;border-bottom:1px solid var(--bd)}
    .bar.min .hd{border-bottom:0}
    .hd .ic{flex:none;width:24px;height:24px;display:grid;place-items:center;border-radius:6px;background:var(--sf2);font-size:13px}
    .hd b{flex:1;font-size:13px;font-weight:600}
    .hd button{all:unset;cursor:pointer;width:26px;height:26px;display:grid;place-items:center;border-radius:6px;color:var(--mt)}.hd button:hover{background:var(--sf);color:var(--tx)}
    .hd button.mode,.mode{width:auto;height:auto;padding:4px 8px;border-radius:6px;font:600 11px/1 ui-sans-serif,system-ui,sans-serif;letter-spacing:.02em}
    .mode.dry{color:var(--wr);background:var(--wr-sf)}.mode.real{color:var(--dn);background:var(--dn-sf)}
    .body{flex:1 1 auto;min-height:0;overflow:auto;padding:12px;display:flex;flex-direction:column;gap:10px}
    .body > *,.body > button.b{flex-shrink:0}
    .row{display:flex;gap:8px;flex-wrap:wrap;align-items:center}
    button.b{all:unset;box-sizing:border-box;cursor:pointer;display:inline-flex;align-items:center;justify-content:center;gap:6px;height:32px;padding:0 12px;border-radius:8px;
      background:var(--bg);color:var(--tx);border:1px solid var(--bd);font:600 12.5px/1 ui-sans-serif,system-ui,sans-serif;white-space:nowrap}
    button.b:hover{background:var(--sf)}
    button.b:focus-visible,.tab:focus-visible,.lk:focus-visible,input:focus-visible,summary:focus-visible{outline:2px solid var(--ac);outline-offset:2px}
    button.b[disabled]{cursor:default;opacity:1;color:var(--mt);background:var(--sf);border-color:var(--bd)}
    button.b[data-a=scan]{width:100%;height:34px;background:var(--ac);border-color:var(--ac);color:var(--on-ac)}button.b[data-a=scan]:hover{filter:brightness(1.07)}
    button.b[data-a=scan][disabled]{background:var(--sf);border-color:var(--bd);color:var(--mt);filter:none}
    .main{display:flex;gap:8px}.main [data-a=run]{flex:1;height:36px;background:var(--ac);border-color:var(--ac);color:var(--on-ac)}
    .main [data-a=run].red.real{background:var(--dn);border-color:var(--dn);color:var(--on-ac)}
    .main [data-a=run]:hover{filter:brightness(1.07)}.main [data-a=run][disabled]{background:var(--sf);border-color:var(--bd);color:var(--mt);filter:none}
    .main [data-a=stop]{height:36px}
    .tabs{display:flex;gap:2px;padding:2px;background:var(--sf2);border-radius:8px}
    .tab{all:unset;box-sizing:border-box;cursor:pointer;flex:1;text-align:center;padding:6px 8px;border-radius:6px;color:var(--mt);font:600 12px/1.2 ui-sans-serif,system-ui,sans-serif}
    .tab.on{background:var(--bg);color:var(--tx);box-shadow:0 1px 2px rgba(16,24,40,.1)}.tab[disabled]{cursor:default;opacity:.6}
    small{color:var(--mt)}
    .sum{display:block;padding:8px 10px;border-radius:8px;background:var(--sf);color:var(--mt);font-size:12px}.sum b{color:var(--tx);font-weight:600}
    .blk{border:1px solid var(--bd);border-radius:10px;overflow:hidden;background:var(--bg)}
    .bh{display:flex;align-items:center;justify-content:space-between;gap:8px;padding:6px 6px 6px 10px;background:var(--sf);border-bottom:1px solid var(--bd);font-size:12px;font-weight:600;color:var(--tx)}
    .bh em{font-style:normal;color:var(--mt);font-weight:500;margin-left:4px}
    .bh .acts{display:flex;gap:2px;flex-wrap:wrap;justify-content:flex-end}
    .lk{all:unset;cursor:pointer;padding:3px 6px;border-radius:5px;color:var(--ac);font:600 11.5px/1.2 ui-sans-serif,system-ui,sans-serif}.lk:hover{background:var(--ac-sf)}
    .lk[disabled]{color:var(--mt);cursor:default;background:none}
    .tools{display:flex;gap:6px;padding:6px;border-bottom:1px solid var(--bd)}
    .tools input{flex:1;min-width:0;height:28px;padding:0 8px;border:1px solid var(--bd);border-radius:6px;background:var(--bg);color:var(--tx);font:inherit;font-size:12px}
    .list{max-height:180px;overflow:auto;padding:2px 0}
    .blk .list{max-height:190px}
    .it{display:flex;gap:8px;align-items:flex-start;padding:5px 10px;cursor:pointer}.it:hover{background:var(--sf)}
    .it span{flex:1;min-width:0;overflow-wrap:anywhere}.it small{font-size:11px}
    .it.prot{opacity:.6;cursor:default}.it .sh{font-size:10.5px;color:var(--ok);font-weight:600;margin-left:4px}
    .it[hidden]{display:none}
    .list > div{padding:4px 10px}
    .res .gh{display:flex;align-items:center;justify-content:space-between;padding:8px 10px 2px;font-size:12px}.res .gh.bad b{color:var(--dn)}
    .ri{padding:3px 10px;overflow-wrap:anywhere;font-size:12px}.ri small{margin-left:4px}
    label{display:flex;gap:8px;align-items:center;cursor:pointer}
    input[type=checkbox]{accent-color:var(--ac);width:15px;height:15px;margin:1px 0 0;flex:none}
    input[type=number]{width:64px;height:28px;padding:0 8px;border:1px solid var(--bd);border-radius:6px;background:var(--bg);color:var(--tx);font:inherit;font-size:12.5px}
    .st{padding:8px 10px;border-radius:8px;background:var(--sf);white-space:pre-wrap;font-size:12.5px}
    .st.warn,.warn{color:var(--dn)}.st.warn{background:var(--dn-sf)}
    .ok{padding:8px 10px;border-radius:8px;background:var(--ok-sf);color:var(--ok);font-size:12.5px}
    .auto{color:var(--ok)}
    .autoinfo{padding:8px 10px;border-radius:8px;background:var(--ok-sf);color:var(--ok);font-size:12px}.autoinfo.warn{background:var(--dn-sf);color:var(--dn)}
    .toast{padding:8px 10px;border-radius:8px;background:var(--tx);color:var(--bg);font-weight:600;font-size:12px}
    .prog{display:flex;flex-direction:column;gap:4px}.prog .pb{height:6px;border-radius:999px;background:var(--sf2);overflow:hidden}
    .prog .pb i{display:block;height:100%;background:var(--ac);border-radius:999px;transition:width .3s}
    .prog .pb.ind i{width:35%;animation:pa-ind 1.2s ease-in-out infinite}
    @keyframes pa-ind{0%{margin-left:-35%}100%{margin-left:100%}}
    .prog small{display:flex;justify-content:space-between;font-size:11.5px}
    details.set{border:1px solid var(--bd);border-radius:10px;background:var(--bg)}
    details.set summary{cursor:pointer;list-style:none;padding:9px 10px;font-weight:600;font-size:12px;display:flex;align-items:center;gap:6px}
    details.set summary::-webkit-details-marker{display:none}
    details.set summary::before{content:"";width:6px;height:6px;border-right:1.5px solid var(--mt);border-bottom:1.5px solid var(--mt);transform:rotate(-45deg);transition:transform .15s;margin-right:2px}
    details.set[open] summary::before{transform:rotate(45deg)}
    details.set summary .mt{margin-left:auto;font-weight:500;color:var(--mt);font-size:11.5px}
    .kv{display:flex;flex-direction:column;border-top:1px solid var(--bd)}
    .kr{display:flex;align-items:center;justify-content:space-between;gap:12px;padding:8px 10px;border-bottom:1px solid var(--bd)}.kr:last-child{border-bottom:0}
    .kr > span:first-child{display:flex;flex-direction:column;min-width:0}.kr > span:first-child small{font-size:11px;line-height:1.3}
    .kr .in{display:flex;align-items:center;gap:6px;flex:none;white-space:nowrap;color:var(--mt);font-size:12px}
    .ft{display:flex;gap:8px}.ft .b{flex:1}
    ::-webkit-scrollbar{width:8px;height:8px}::-webkit-scrollbar-thumb{background:var(--bd);border-radius:8px}
  `;

  function esc(s) { return String(s).replace(/[&<>"]/g, (c) => ({ '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;' }[c])); }

  function ensureBar() {
    const want = onServicesList();
    if (!want) { if (host) { host.remove(); host = null; root = null; } return; }
    if (host && host.isConnected) return;
    host = document.createElement('div');
    host.id = 'kt-host';
    root = host.attachShadow({ mode: 'open' });
    root.innerHTML = '<style>' + CSS + '</style><div class="bar" id="bar"></div>';
    root.addEventListener('click', onBarClick);
    root.addEventListener('change', onBarChange);
    root.addEventListener('input', (e) => { if (e.target.classList && e.target.classList.contains('flt')) { filterText = e.target.value; applyFilter(); } });
    root.addEventListener('keydown', (e) => { if (e.key === 'Enter' && e.target.closest && e.target.closest('.paste')) { e.preventDefault(); uiClick('applyids', e.target); } }, true);
    root.addEventListener('toggle', (e) => { if (e.target.matches && e.target.matches('details.set')) { setOpen = e.target.open; try { localStorage.setItem(ME + '_set', setOpen ? '1' : '0'); } catch (er) { /* yok */ } } }, true);
    root.addEventListener('focusout', () => setTimeout(render, 0)); // sayı kutusundan çıkınca düğme etiketini güncelle
    document.documentElement.appendChild(host);
    render();
  }

  // Fare basılıyken çubuğu yeniden çizme: kutudan düğmeye tıklayınca "change" yeniden çizer ve tıklama kaybolurdu.
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
    const lines = ['Panel Araçları — ' + 'Kategori Temizle' + ' v' + VERSION, 'Zaman: ' + new Date().toLocaleString('tr-TR'), 'Adres: ' + location.href, 'Durum: ' + S.status, ''];
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
  // ---- koruma listesi: bu ID'lere hiçbir araç dokunmaz (mini panel → Koruma) ----
  let PROTECT = new Set();
  try {
    chrome.storage.local.get({ pa_protect: [] }, (o) => { void chrome.runtime.lastError; PROTECT = new Set(((o && o.pa_protect) || []).map(String)); render(); });
    chrome.storage.onChanged.addListener((ch, area) => { if (area === 'local' && ch.pa_protect) { PROTECT = new Set((ch.pa_protect.newValue || []).map(String)); render(); } });
  } catch (e) { /* depolama yok */ }
  const isProt = (x) => { const id = idOf(x); return !!id && PROTECT.has(id); };
  // ---- liste araçları: ara, ID yapıştırarak seç, tersine çevir ----
  let filterText = '';
  let pasteOpen = false;
  function applyFilter() {
    if (!root) return;
    const q = filterText.trim().toLowerCase();
    for (const it of root.querySelectorAll('.blk.pick .it')) it.hidden = !!q && !it.textContent.toLowerCase().includes(q);
    const c = root.querySelector('.blk.pick .fc');
    if (c) c.textContent = q ? root.querySelectorAll('.blk.pick .it:not([hidden])').length + ' gösteriliyor' : '';
  }
  const visibleKeys = () => [...root.querySelectorAll('.blk.pick .it:not([hidden]) input[data-x]')].filter((c) => !c.disabled).map((c) => c.getAttribute('data-x'));
  const ui = {
    head: (icon, title) => '<div class="hd"><span class="ic">' + icon + '</span><b>' + title + '</b><button class="mode ' + (settings.dryRun ? 'dry' : 'real') + '" data-a="drytoggle"' + (S.running ? ' disabled' : '') + ' title="' + (settings.dryRun ? 'Deneme modu: hiçbir şey değişmez — tıkla: gerçek moda geç' : 'Gerçek mod: değişiklik yapılır — tıkla: deneme moduna dön') + '">' + (settings.dryRun ? 'DENEME' : 'GERÇEK') + '</button><button data-a="min" title="Küçült/Büyüt" aria-label="Küçült">' + (minimized ? '▢' : '–') + '</button></div>',
    /** Seçim listesi: ara, ID yapıştır, hepsi/hiçbiri/ters, ID'leri kopyala. Korunan öğe işaretlenemez. */
    pick: (id, title, items, sel, busy) => '<div class="blk pick"><div class="bh"><span>' + title + ' <em>' + sel + ' / ' + items.length + ' seçili</em></span><span class="acts"><button class="lk" data-a="copyids" data-g="sel" title="Seçili olanların ID\'lerini virgülle kopyala (62727,26272,…)">ID kopyala</button></span></div>'
      + '<div class="tools"><input type="search" class="flt" placeholder="Ara: ad ya da ID" value="' + esc(filterText) + '" aria-label="Listede ara"><button class="lk" data-a="all"' + (busy ? ' disabled' : '') + '>Hepsi</button><button class="lk" data-a="none"' + (busy ? ' disabled' : '') + '>Hiçbiri</button><button class="lk" data-a="invert"' + (busy ? ' disabled' : '') + ' title="Seçimi tersine çevir">Ters</button><button class="lk" data-a="pasteids"' + (busy ? ' disabled' : '') + ' title="ID listesi yapıştır: yalnız onlar seçilsin">ID yapıştır</button></div>'
      + (pasteOpen ? '<div class="tools paste"><input type="text" placeholder="62727,26272,2627 …" aria-label="Seçilecek ID\'ler"><button class="lk" data-a="applyids">Seç</button></div>' : '')
      + '<small class="fc" style="padding:0 10px"></small><div class="list" id="' + id + '">' + items.join('') + '</div></div>',
    /** Sonuç grupları; her grubun ID'leri ayrı kopyalanır. */
    res: (id, r) => {
      const g = RES_GROUPS.filter(([k]) => (r[k] || []).length);
      if (!g.length) return '';
      return '<div class="blk"><div class="bh"><span>Sonuç</span><span class="acts"><button class="lk" data-a="copyids" data-g="done" title="Yapılanların ID\'lerini virgülle kopyala">Yapılan ID\'leri kopyala</button></span></div><div class="list res" id="' + id + '">'
        + g.map(([k, t]) => '<div class="gh' + (k === 'errors' ? ' bad' : '') + '"><b>' + t + ' (' + r[k].length + ')</b>' + (idList(r[k]).length ? '<button class="lk" data-a="copyids" data-g="' + k + '">ID kopyala</button>' : '') + '</div>'
          + r[k].map((x) => '<div class="ri">' + esc(x.name) + (x.reason ? ' <small>' + esc(x.reason) + '</small>' : '') + '</div>').join('')).join('') + '</div></div>';
    },
    progress: () => {
      if (!S.running) return '';
      const r = S.res || {};
      const done = ['deleted', 'moved', 'done', 'dry', 'skipped', 'errors'].reduce((n, k) => n + ((r[k] || []).length), 0);
      const tot = S.__total || 0;
      const pct = tot ? Math.min(100, Math.round(done / tot * 100)) : 0;
      const eta = tot && done && S.__t0 ? Math.round((Date.now() - S.__t0) / done * (tot - done) / 1000) : 0;
      return '<div class="prog"><div class="pb' + (tot ? '' : ' ind') + '"><i style="width:' + pct + '%"></i></div><small><span>' + (tot ? done + ' / ' + tot : 'çalışıyor…') + '</span><span>' + (eta ? '~' + (eta > 90 ? Math.round(eta / 60) + ' dk' : eta + ' sn') + ' kaldı' : '') + '</span></small></div>';
    },
    status: () => '<div class="st' + (/^⚠/.test(S.status) ? ' warn' : '') + '" id="st" role="status">' + esc(S.status) + '</div>' + (flash ? '<div class="toast">' + esc(flash) + '</div>' : ''),
    settings: (rows, note) => '<details class="set"' + (setOpen ? ' open' : '') + '><summary>Ayarlar' + (note ? '<span class="mt">' + note + '</span>' : '') + '</summary><div class="kv">' + rows.join('') + '</div></details>',
    row: (label, ctl, hint) => '<label class="kr"><span>' + label + (hint ? '<small>' + hint + '</small>' : '') + '</span>' + ctl + '</label>',
    foot: () => '<div class="ft"><button class="b" data-a="diag">Teşhisi kopyala</button><button class="b" data-a="log" title="Teşhis + adım günlüğü + sonuçlar (.txt)">Günlüğü indir (.txt)</button></div>',
  };
  /** Ortak düğmeler: ID kopyala, günlük indir, ayarlar aç/kapa. true dönerse işlendi. */
  function uiClick(a, t) {
    if ((a === 'all' || a === 'none') && filterText.trim()) { for (const k of visibleKeys()) { if (a === 'none') S.excluded.add(k); else S.excluded.delete(k); } render(); return true; }
    if (a === 'invert') { for (const k of visibleKeys()) { if (S.excluded.has(k)) S.excluded.delete(k); else S.excluded.add(k); } render(); return true; }
    if (a === 'pasteids') { pasteOpen = !pasteOpen; render(); const i = root.querySelector('.paste input'); if (i) i.focus(); return true; }
    if (a === 'applyids') {
      const inp = root.querySelector('.paste input');
      const want = new Set(((inp && inp.value) || '').match(/\d{2,}/g) || []);
      if (!want.size) { toast('ID bulunamadı: virgülle ya da boşlukla ayır'); return true; }
      let hit = 0;
      for (const c of root.querySelectorAll('.blk.pick .it input[data-x]')) { if (c.disabled) continue; const k = c.getAttribute('data-x'); const id = String(k).replace(/\D+/g, '') || (c.closest('.it').textContent.match(/\d{2,}/) || [''])[0]; if (want.has(id)) { S.excluded.delete(k); hit++; } else S.excluded.add(k); }
      pasteOpen = false; if (inp) inp.blur(); render(); toast(hit + ' / ' + want.size + ' ID listede bulundu ve seçildi; diğerleri bırakıldı'); return true;
    }
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
  const RES_GROUPS = [['moved', 'Taşınan'], ['deleted', 'Silinen'], ['dry', 'Deneme'], ['skipped', 'Atlanan'], ['errors', 'Hata'], ['pending', 'İşlenmeyen']];
  const DONE_KEYS = ['moved', 'deleted'];
  const selectedItems = () => (S.phase === 'ready' ? (settings.mode === 'move' ? S.fulls : S.candidates).filter((x) => !S.excluded.has(keyOf(x))) : []);
  function render() {
    if (!root) return;
    if (pressed) { wantRender = true; return; }
    wantRender = false;
    const bar = root.getElementById('bar');
    if (!bar) return;
    const focused = root.activeElement;
    if (focused && focused.tagName === 'INPUT' && (focused.type === 'number' || focused.type === 'search' || focused.closest('.paste'))) { const st = bar.querySelector('#st'); if (st) st.textContent = S.status; return; }
    const busy = S.running;
    const r = S.res;
    const mv = settings.mode === 'move';
    const work = S.phase === 'ready' ? Math.min(workList().length, settings.max) : 0;
    const item = (x, extra) => { const pr = isProt(x); return '<label class="it' + (pr ? ' prot' : '') + '"' + (pr ? ' title="Koruma listesinde: dokunulmaz"' : '') + '><input type="checkbox" data-x="' + esc(keyOf(x)) + '"' + (S.excluded.has(keyOf(x)) || pr ? '' : ' checked') + (busy || pr ? ' disabled' : '') + '> <span>' + esc(x.name) + (pr ? '<span class="sh">KORUMALI</span>' : '') + '</span>' + (extra || '') + '</label>'; };
    const p = [ui.head('🗑', 'Kategori Temizle'), '<div class="body">'];
    p.push('<div class="tabs"><button class="tab' + (mv ? '' : ' on') + '" data-a="mode" data-v="delete"' + (busy ? ' disabled' : '') + '>Boş kategori sil</button><button class="tab' + (mv ? ' on' : '') + '" data-a="mode" data-v="move"' + (busy ? ' disabled' : '') + '>Pasif → Not Assigned</button></div>');
    p.push('<button class="b" data-a="scan"' + (busy ? ' disabled' : '') + '>' + (mv ? 'Dolu pasif kategorileri bul' : 'Boş pasif kategorileri bul') + '</button>');
    if (S.stats) p.push('<small class="sum">' + S.stats.kategori + ' kategori · ' + S.stats.aktif + ' aktif · ' + S.stats.pasif + ' pasif · <b>' + (mv ? S.stats.pasifDolu + ' dolu pasif (' + S.stats.servis + ' servis)' : S.stats.bos + ' boş pasif') + '</b></small>');
    const lst = mv ? S.fulls : S.candidates;
    if (lst.length && S.phase === 'ready') {
      const sel = lst.filter((x) => !S.excluded.has(keyOf(x)) && !isProt(x)).length;
      p.push(ui.pick(mv ? 'ktFull' : 'ktEmpty', mv ? 'Taşınacak' + (settings.moveDelete ? ' + silinecek' : '') : 'Silinecek', lst.map((x) => item(x, mv ? ' <small>(' + x.count + ')</small>' : '')), sel, busy));
    }
    const verb = settings.dryRun ? 'Dene' : mv ? (settings.moveDelete ? 'Taşı + sil' : 'Taşı') : 'Sil';
    p.push('<div class="main"><button class="b red' + (settings.dryRun ? '' : ' real') + '" data-a="run"' + (busy || S.phase !== 'ready' || !workList().length ? ' disabled' : '') + '>' + verb + ' (' + work + ')</button><button class="b" data-a="stop"' + (busy ? '' : ' disabled') + '>Durdur</button></div>');
    p.push(ui.progress());
    p.push(ui.status());
    p.push(ui.res('ktRes', r));
    if (S.notes.length) p.push('<small>' + S.notes.map(esc).join('<br>') + '</small>');
    const rows = [
      ui.row('Deneme modu', '<span class="in"><input type="checkbox" data-s="dry"' + (settings.dryRun ? ' checked' : '') + '></span>', 'Açıkken hiçbir şey silinmez/taşınmaz'),
      ui.row('En çok', '<span class="in"><input type="number" min="1" max="1000" data-s="max" value="' + settings.max + '"> kategori</span>'),
    ];
    if (mv) rows.push(ui.row('Taşıdıktan sonra sil', '<span class="in"><input type="checkbox" data-s="moveDelete"' + (settings.moveDelete ? ' checked' : '') + (busy ? ' disabled' : '') + '></span>', 'Boşalan pasif kategoriyi de sil'));
    p.push(ui.settings(rows, (settings.dryRun ? 'deneme' : 'gerçek') + ' · en çok ' + settings.max));
    p.push(ui.foot(), '</div>');
    bar.className = 'bar' + (minimized ? ' min' : '');
    bar.innerHTML = p.join('');
    applyFilter();
    if (globalThis.__paDock) globalThis.__paDock.place('kt', host, bar);
  }
  function renderLite(bar) {
    const st = bar.querySelector('#st');
    if (st) st.textContent = S.status;
  }

  async function onBarClick(ev) {
    const t = ev.target.closest && ev.target.closest('[data-a]');
    if (!t || t.disabled || t.hasAttribute('disabled')) return;
    const a = t.getAttribute('data-a');
    if (uiClick(a, t)) return;
    if (a === 'min') { minimized = !minimized; try { localStorage.setItem('kt_min', minimized ? '1' : '0'); } catch (e) { /* yok */ } render(); document.dispatchEvent(new CustomEvent('panel-arac-yerles')); }
    else if (a === 'scan') gScan();
    else if (a === 'mode') { patchSettings({ mode: t.getAttribute('data-v') }); if (S.phase === 'ready') S.status = scanSummary(); render(); }
    else if (a === 'all' || a === 'none') { setAllExcluded(a === 'none'); render(); }
    else if (a === 'run') gRun();
    else if (a === 'stop') stop();
    else if (a === 'diag') { const ok = await copyText(diag()); S.status = ok ? 'Teşhis panoya kopyalandı; sohbete yapıştır.' : '⚠ Panoya kopyalanamadı (izin yok).'; render(); }
  }
  function setAllExcluded(on) {
    const lst = settings.mode === 'move' ? S.fulls : S.candidates;
    for (const x of lst) { if (on) S.excluded.add(keyOf(x)); else S.excluded.delete(keyOf(x)); }
  }
  function onBarChange(ev) {
    const t = ev.target;
    const s = t.getAttribute && t.getAttribute('data-s');
    if (s === 'dry') patchSettings({ dryRun: t.checked });
    if (s === 'moveDelete') patchSettings({ moveDelete: t.checked });
    const x = t.getAttribute && t.getAttribute('data-x');
    if (x) { if (t.checked) S.excluded.delete(x); else S.excluded.add(x); }
    if (s === 'max') patchSettings({ max: t.value });
    render();
  }

  // ---------------------------------------------------------------- mini panel (eklenti simgesi) ile iletişim
  function publicState() {
    return {
      version: VERSION, onPage: onServicesList(), phase: S.phase, running: S.running, status: S.status,
      candidates: S.candidates.map((c) => ({ name: c.name, key: keyOf(c), off: S.excluded.has(keyOf(c)) || isProt(c), prot: isProt(c) })), fulls: S.fulls.map((f) => ({ name: f.name, count: f.count, key: keyOf(f), off: S.excluded.has(keyOf(f)) || isProt(f), prot: isProt(f) })), work: S.phase === 'ready' ? workList().length : 0, summary: S.phase === 'ready' ? scanSummary() : '', notAssigned: !!S.notAssigned, stats: S.stats, notes: S.notes, res: S.res,
      settings: Object.assign({}, settings), diagNeeded: S.diagNeeded || S.res.errors.length > 0,
    };
  }
  try {
    chrome.runtime.onMessage.addListener((msg, _sender, respond) => {
      if (!msg || msg.kt !== true) return false;
      const done = () => respond(publicState());
      switch (msg.cmd) {
        case 'scan': gScan(); setTimeout(done, 50); return true;
        case 'run': gRun(); setTimeout(done, 50); return true;
        case 'stop': stop(); done(); return false;
        case 'settings': patchSettings(msg.patch); if (S.phase === 'ready' && msg.patch && msg.patch.mode) S.status = scanSummary(); render(); done(); return false;
        case 'exclude': if (msg.all !== undefined) setAllExcluded(!!msg.all); else if (msg.on) S.excluded.add(msg.key); else S.excluded.delete(msg.key); render(); done(); return false;
        case 'diag': respond({ text: diag() }); return false;
        case 'ids': { const g = msg.g; const r = S.res || {}; respond({ ids: g === 'sel' ? idList(selectedItems()) : g === 'done' ? idList(DONE_KEYS.flatMap((k) => r[k] || [])) : idList(r[g] || []) }); return false; }
        case 'log': respond({ text: logText(), name: ME + '-gunluk-' + new Date().toISOString().slice(0, 16).replace(/[:T]/g, '-') + '.txt' }); return false;
        default: done(); return false;
      }
    });
  } catch (e) { /* eklenti dışı ortam */ }

  // ---------------------------------------------------------------- sayfayı izle (SPA geçişleri, React yeniden çizimi)
  let throttle = null;
  const mo = new MutationObserver(() => {
    if (throttle) return;
    throttle = setTimeout(() => { throttle = null; ensureBar(); }, 800);
  });
  mo.observe(document.documentElement, { childList: true, subtree: true });
  ensureBar();
})();
