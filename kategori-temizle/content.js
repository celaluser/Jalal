// Kategori Temizle — Perfect Panel > Services sayfasında "pasif VE boş" kategorileri bulur ve (onayınla) siler.
// Öğeleri iç HTML'e değil görünen YAZILARA göre bulur (Actions, Delete category, Delete, Cancel).
// React yeniden çizdiği için hiçbir öğe referansı saklanmaz: her adımda kategori adından yeniden bulunur.
// Silme geri alınamaz: önce yalnızca listeler; her silmeden hemen önce (pasif mi, boş mu, ad aynı mı) yeniden kontrol eder.
(() => {
  if (window.__katTemizle) return;
  window.__katTemizle = true;

  const VERSION = '1.1.0';
  const SETTINGS_KEY = 'kt_settings';
  const DIALOG = '[role=dialog], .modal, .modal-dialog, .modal-content';
  const HANDLE = '.service-table__handle, [class*="__handle"], .handle, .drag-handle';
  const TOGGLE = '.service-table__header-hide-all, [class*="header-hide-all"]';
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

  // ---------------------------------------------------------------- durum
  const S = {
    phase: 'idle', // idle | scanning | ready | deleting
    status: 'Hazır. "Boş pasif kategorileri bul"a bas.',
    running: false,
    stop: false,
    candidates: [], // [{name}]
    stats: null,
    notes: [],
    res: { deleted: [], dry: [], skipped: [], errors: [], pending: [] },
    refBest: 0,
    hasCounts: false,
    diagNeeded: false,
    log: [],
    seen: { menu: [], dialog: [], toast: [] },
  };
  const settings = { dryRun: true, max: 50 };
  // Güvenlik: deneme modu kalıcı değil; her sayfa yüklenişinde AÇIK başlar. Yalnızca "en çok N" hatırlanır.
  try { const m = JSON.parse(localStorage.getItem(SETTINGS_KEY) || '{}').max; if (m) settings.max = Math.max(1, Math.min(1000, parseInt(m, 10) || 50)); } catch (e) { /* depolama kapalı */ }
  const saveSettings = () => { try { localStorage.setItem(SETTINGS_KEY, JSON.stringify({ max: settings.max })); } catch (e) { /* yok */ } };
  function patchSettings(p) {
    if (p && typeof p.dryRun === 'boolean') settings.dryRun = p.dryRun;
    if (p && p.max !== undefined) settings.max = Math.max(1, Math.min(1000, parseInt(p.max, 10) || 50));
    saveSettings();
  }

  function log(msg) {
    S.log.push(new Date().toTimeString().slice(0, 8) + ' ' + msg);
    if (S.log.length > 300) S.log.shift();
  }
  function setStatus(t) { S.status = t; log(t); render(); }
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
    if (checks().length <= 1) return true;
    let t = [...document.querySelectorAll(TOGGLE)].find(visible);
    if (!t) t = [...document.querySelectorAll('.fa-compress-alt, .fa-expand-alt')].find(visible);
    if (!t) { log('aç/kapa düğmesi bulunamadı'); return false; }
    t.click();
    const ok = await waitFor(() => checks().length <= 1 && categoryRows().length > 0, 6000, 100);
    if (!ok) log('düğmeye basıldı ama kategori görünümü gelmedi');
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
  function check(name, id) {
    const { rows, hits } = findByName(name, id);
    if (!hits.length) return { ok: false, reason: 'satır bulunamadı (kimlik ' + (id || '-') + '; ekranda ' + rows.length + ' kategori; ilk: "' + (rows[0] ? rows[0].name.slice(0, 40) : '-') + '")' };
    if (hits.length > 1) return { ok: false, reason: 'aynı kimlikli birden çok satır' };
    const row = hits[0];
    if (row.special) return { ok: false, reason: 'özel satır (Not Assigned)' };
    if (catKey(row.name) !== catKey(name)) return { ok: false, reason: 'ad değişmiş: "' + row.name.slice(0, 60) + '" ≠ "' + name.slice(0, 60) + '"' };
    if (checks().length > 1) return { ok: false, reason: 'liste kategori görünümünde değil' };
    const { why } = splitActive(rows, S.refBest);
    if (!why.has(row)) return { ok: false, reason: 'artık pasif görünmüyor (aktif)' };
    if (row.count > 0) return { ok: false, reason: 'içinde ' + row.count + ' servis var' };
    if (Number.isNaN(row.count)) return { ok: false, reason: 'servis sayısı okunamadı' };
    if (row.count === null && !S.hasCounts) return { ok: false, reason: 'servis sayısı (Expand) doğrulanamadı' };
    return { ok: true, row, why: why.get(row) };
  }

  // ---------------------------------------------------------------- menü / pencere / bildirim
  /** Görünür, en derin "Delete category" yazılı öğeler (menü portal ile body sonunda olabilir). */
  function deleteItems() {
    const all = [...document.querySelectorAll('a, button, li, span, div, p, [role=menuitem]')].filter((e) => visible(e) && /^delete\s+category$/i.test(norm(e.textContent)));
    return all.filter((e) => !all.some((o) => o !== e && e.contains(o)));
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
    if (!deleteItems().length) return;
    pressEscape();
    await sleep(150);
    if (deleteItems().length) document.body.click();
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
    S.candidates = []; S.stats = null; S.notes = []; S.diagNeeded = false;
    S.res = { deleted: [], dry: [], skipped: [], errors: [], pending: [] };
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
      if (checks().length > 1) return fail('Gezinirken servisler açıldı; yeniden dene.');
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
        if (e.count > 0) { dolu++; return; }
        if (Number.isNaN(e.count)) return; // sayı okunamadı: boş sayılmaz
        S.candidates.push({ name: e.name, id: e.id });
      });
      S.stats = { kategori: real.length, aktif: real.length - pasif.length, pasif: pasif.length, pasifDolu: dolu, bos: S.candidates.length };
      setScroll(box, 0);
      S.phase = 'ready';
      setStatus(S.candidates.length
        ? S.candidates.length + ' boş pasif kategori bulundu. Henüz hiçbir şey silinmedi.'
        : 'Silinecek boş pasif kategori yok.');
    } catch (e) {
      log(String(e && e.stack).slice(0, 400)); fail('Tarama hatası: ' + (e && e.message) + ' [' + String((e && e.stack || '').split('\n')[1] || '').trim().slice(-60) + ']');
    } finally {
      S.running = false; busy(false);
      render();
    }
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
      if (checks().length <= 1) return { kind: 'ok', row: f.hits[0] };
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

    // 2) Actions → Delete category
    const before = new Set(deleteItems());
    const bodyBefore = new Set(document.body.children);
    c1.row.btn.click();
    const item = await waitFor(() => { const v = deleteItems().filter((x) => !before.has(x)); return v.length ? v[v.length - 1] : null; }, 2500, 60);
    const fresh = [...document.body.children].filter((x) => !bodyBefore.has(x) && visible(x)).map((x) => norm(x.textContent).slice(0, 200));
    remember('menu', fresh);
    if (!item) { await closeMenu(); S.diagNeeded = true; return result('error', '"Delete category" menüde bulunamadı'); }
    const owner = categoryRows().find((r) => r.el.contains(item));
    if (owner && (id ? owner.id !== id : catKey(owner.name) !== k)) { await closeMenu(); return result('skip', 'menü başka kategoriye ait (' + owner.name + ')'); }
    (item.closest('a, button, li, [role=menuitem]') || item).click();

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

  async function run() {
    if (S.running || S.phase !== 'ready' || !S.candidates.length) return;
    if (anyDialog()) return fail('Açık bir pencere var; önce kapat.');
    S.running = true; S.stop = false; S.phase = 'deleting'; S.diagNeeded = false; busy(true);
    S.res = { deleted: [], dry: [], skipped: [], errors: [], pending: [] };
    const list = S.candidates.slice();
    const max = settings.max;
    let done = 0; let consecutiveErrors = 0; let i = 0;
    try {
      for (; i < list.length; i++) {
        if (S.stop) break;
        if (done >= max) { S.notes.push('En çok ' + max + ' sınırına ulaşıldı.'); break; }
        const name = list[i].name;
        setStatus((settings.dryRun ? '[DENEME] ' : '') + '(' + (i + 1) + '/' + list.length + ') ' + name);
        let r;
        try { r = await processOne(name, list[i].id); } catch (e) { r = result('error', 'beklenmeyen hata: ' + (e && e.message)); }
        log(name + ' → ' + r.kind + (r.reason ? ' (' + r.reason + ')' : ''));
        if (r.kind === 'deleted') { S.res.deleted.push({ name, reason: r.reason }); done++; consecutiveErrors = 0; }
        else if (r.kind === 'dry') { S.res.dry.push({ name, reason: r.reason }); done++; consecutiveErrors = 0; }
        else if (r.kind === 'skip') { S.res.skipped.push({ name, reason: r.reason }); consecutiveErrors = 0; }
        else { S.res.errors.push({ name, reason: r.reason }); consecutiveErrors++; }
        render();
        if (r.fatal || consecutiveErrors >= 3) { S.notes.push('Üst üste hata: işlem durduruldu.'); S.diagNeeded = true; i++; break; }
      }
      S.res.pending = list.slice(i).map((c) => c.name);
      const r = S.res;
      const bits = [r.deleted.length + ' silindi', r.dry.length + ' deneme', r.skipped.length + ' atlandı', r.errors.length + ' hata'];
      if (r.pending.length) bits.push(r.pending.length + ' işlenmedi');
      S.phase = 'idle'; S.candidates = [];
      setStatus((S.stop ? 'Durduruldu. ' : 'Bitti. ') + bits.join(', ') + '.');
    } catch (e) {
      fail('Silme hatası: ' + (e && e.message));
    } finally {
      S.running = false; S.stop = false; busy(false);
      render();
    }
  }

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
      durum: S.status, istatistik: S.stats, aday: S.candidates.map((c) => c.name), sonuc: S.res, notlar: S.notes,
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
  try { minimized = localStorage.getItem('kt_min') === '1'; } catch (e) { /* yok */ }

  const CSS = `
    :host{all:initial}
    .bar{position:fixed;left:12px;bottom:12px;z-index:2147483646;width:290px;font:12px/1.4 system-ui,sans-serif;color:#e8ecf4;background:#1b2130;border:1px solid #3a4560;border-radius:10px;box-shadow:0 6px 20px rgba(0,0,0,.45)}
    .bar.min{width:auto}.bar.min .body{display:none}
    .hd{display:flex;align-items:center;gap:6px;padding:6px 8px;cursor:default}
    .hd b{flex:1;font-size:12px}
    .hd button{all:unset;cursor:pointer;padding:0 6px;border-radius:4px;color:#aab4cc}.hd button:hover{background:#2c3550}
    .body{padding:0 8px 8px;display:flex;flex-direction:column;gap:6px}
    .row{display:flex;gap:6px;flex-wrap:wrap;align-items:center}
    button.b{all:unset;cursor:pointer;padding:5px 9px;border-radius:6px;background:#2d3a5c;color:#fff;font-size:12px}
    button.b:hover{background:#3a4a74}button.b[disabled]{opacity:.4;cursor:default}
    button.red{background:#9b2c3a}button.red:hover{background:#b73646}
    .st{white-space:pre-wrap;color:#c9d2e8}.warn{color:#ffb4a8}
    .list{max-height:120px;overflow:auto;background:#121724;border-radius:6px;padding:4px 6px}
    .list div{padding:1px 0;border-bottom:1px solid #1f2740}
    label{display:flex;gap:4px;align-items:center}
    input[type=number]{width:52px;background:#121724;color:#fff;border:1px solid #3a4560;border-radius:4px;padding:2px 4px}
    small{color:#8f9bb8}
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
    document.documentElement.appendChild(host);
    render();
  }

  // Fare basılıyken çubuğu yeniden çizme: kutudan düğmeye tıklayınca "change" yeniden çizer ve tıklama kaybolurdu.
  let pressed = false; let wantRender = false;
  document.addEventListener('pointerdown', (e) => { if (host && e.composedPath().includes(host)) pressed = true; }, true);
  document.addEventListener('pointerup', () => { if (!pressed) return; pressed = false; setTimeout(() => { if (wantRender) render(); }, 0); }, true);

  function render() {
    if (!root) return;
    if (pressed) { wantRender = true; return; }
    wantRender = false;
    const bar = root.getElementById ? root.getElementById('bar') : root.querySelector('#bar');
    if (!bar) return;
    // Giriş kutusu odaktayken yeniden çizip yazmayı bozma
    const focused = root.activeElement;
    if (focused && focused.tagName === 'INPUT' && focused.type === 'number') return renderLite(bar);
    const busy = S.running;
    const r = S.res;
    const parts = [];
    parts.push('<div class="hd"><b>🗑 Kategori Temizle' + (settings.dryRun ? ' <small>(deneme)</small>' : '') + '</b><button data-a="min" title="Küçült/Büyüt">' + (minimized ? '▢' : '–') + '</button></div>');
    parts.push('<div class="body">');
    parts.push('<div class="row"><button class="b" data-a="scan"' + (busy ? ' disabled' : '') + '>Boş pasif kategorileri bul</button></div>');
    if (S.stats) parts.push('<small>' + S.stats.kategori + ' kategori · ' + S.stats.aktif + ' aktif · ' + S.stats.pasif + ' pasif (' + S.stats.pasifDolu + ' dolu) · <b>' + S.stats.bos + ' boş pasif</b></small>');
    if (S.candidates.length) parts.push('<div class="list">' + S.candidates.map((c) => '<div>' + esc(c.name) + '</div>').join('') + '</div>');
    parts.push('<div class="row"><button class="b red" data-a="run"' + (busy || S.phase !== 'ready' || !S.candidates.length ? ' disabled' : '') + '>' + (settings.dryRun ? 'Dene' : 'Sil') + ' (' + (S.phase === 'ready' ? Math.min(S.candidates.length, settings.max) : 0) + ')</button>'
      + '<button class="b" data-a="stop"' + (busy ? '' : ' disabled') + '>Durdur</button></div>');
    parts.push('<div class="row"><label><input type="checkbox" data-s="dry"' + (settings.dryRun ? ' checked' : '') + '> Deneme modu</label><label>en çok <input type="number" min="1" max="1000" data-s="max" value="' + settings.max + '"></label></div>');
    parts.push('<div class="st' + (/^⚠/.test(S.status) ? ' warn' : '') + '" id="st">' + esc(S.status) + '</div>');
    if (r.deleted.length || r.dry.length || r.skipped.length || r.errors.length) {
      const line = (arr, t) => arr.length ? '<div><b>' + t + ' (' + arr.length + ')</b></div>' + arr.map((x) => '<div>' + esc(x.name) + ' <small>' + esc(x.reason || '') + '</small></div>').join('') : '';
      parts.push('<div class="list">' + line(r.deleted, 'Silinen') + line(r.dry, 'Deneme') + line(r.skipped, 'Atlanan') + line(r.errors, 'Hata') + '</div>');
    }
    if (S.notes.length) parts.push('<small>' + S.notes.map(esc).join('<br>') + '</small>');
    if (true) parts.push('<div class="row"><button class="b" data-a="diag">Teşhisi kopyala</button></div>');
    parts.push('</div>');
    bar.className = 'bar' + (minimized ? ' min' : '');
    bar.innerHTML = parts.join('');
  }
  function renderLite(bar) {
    const st = bar.querySelector('#st');
    if (st) st.textContent = S.status;
  }

  async function onBarClick(ev) {
    const t = ev.target.closest && ev.target.closest('[data-a]');
    if (!t || t.disabled || t.hasAttribute('disabled')) return;
    const a = t.getAttribute('data-a');
    if (a === 'min') { minimized = !minimized; try { localStorage.setItem('kt_min', minimized ? '1' : '0'); } catch (e) { /* yok */ } render(); }
    else if (a === 'scan') scan();
    else if (a === 'run') run();
    else if (a === 'stop') stop();
    else if (a === 'diag') { const ok = await copyText(diag()); S.status = ok ? 'Teşhis panoya kopyalandı; sohbete yapıştır.' : '⚠ Panoya kopyalanamadı (izin yok).'; render(); }
  }
  function onBarChange(ev) {
    const t = ev.target;
    const s = t.getAttribute && t.getAttribute('data-s');
    if (s === 'dry') patchSettings({ dryRun: t.checked });
    if (s === 'max') patchSettings({ max: t.value });
    render();
  }

  // ---------------------------------------------------------------- mini panel (eklenti simgesi) ile iletişim
  function publicState() {
    return {
      version: VERSION, onPage: onServicesList(), phase: S.phase, running: S.running, status: S.status,
      candidates: S.candidates.map((c) => c.name), stats: S.stats, notes: S.notes, res: S.res,
      settings: Object.assign({}, settings), diagNeeded: S.diagNeeded || S.res.errors.length > 0,
    };
  }
  try {
    chrome.runtime.onMessage.addListener((msg, _sender, respond) => {
      if (!msg || msg.kt !== true) return false;
      const done = () => respond(publicState());
      switch (msg.cmd) {
        case 'scan': scan(); setTimeout(done, 50); return true;
        case 'run': run(); setTimeout(done, 50); return true;
        case 'stop': stop(); done(); return false;
        case 'settings': patchSettings(msg.patch); render(); done(); return false;
        case 'diag': respond({ text: diag() }); return false;
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
