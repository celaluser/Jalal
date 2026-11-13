// tgwatch eklentisi — Perfect Panel "Import services" ekranı.
// Satırları düğme ve etiket YAZILARINDAN bulur (Edit, Save, Name, Import services), panelin iç HTML'ine bağlı değildir.
// Akış: Çevir → adlar tek istekte tgwatch'a → her satır: Edit → Name kutusuna Azerice ad → Save → sıradaki.
// Zaten çevrilmiş ve zaten Azerice olan satırlar atlanır. Geri al: orijinal adlar geri yazılır.
// Import'a basılınca son adlar (elle düzeltilenler dahil) tgwatch bankasına öğretilir. Import kararı her zaman kullanıcıda.
(() => {
  if (window.__tgwImport) return;
  window.__tgwImport = true;

  const ROW_ID = /^\s*(\d{1,10})\s*[-–—]\s*(.+)$/s;
  const AZ_ONLY = /[əƏ]/; // Azericeye özgü harf (Türkçede yok): ad zaten Azerice
  const MARK = ['M3.2 28.5V20C3.2 15 6.11 12 8.1 9c1.7 3.2 3.08 6 2.82 11V28.5z', 'M12.4 28.5V13.6C12.4 8.6 15.4 5.6 17.6 2.6c1.76 3.2 2.96 6 2.64 11V28.5z', 'M21.6 28.5V23.5C21.6 18.5 24.42 15.5 26.2 12.5c1.64 3.2 3.2 6 3 11V28.5z'];
  const KIND_TEXT = { ok: 'bankadan', warn: 'kontrol et', bad: 'çevrilmedi', done: 'Azerice' };

  const norm = (s) => (s || '').replace(/\s+/g, ' ').trim();
  const lower = (s) => norm(s).toLowerCase().replace(/i\u0307/g, 'i'); // dil bağımsız ("Import" → "ımport" olmasın); "İ" → i + nokta düzeltmesi
  // Arka plan sekmesi: sayfanın zamanlayıcıları kısılır. Her bekleme hem burada hem service worker'da başlar; önce biten sayılır
  // (önde: sayfa, arkada: service worker). Bağlantı koparsa (eklenti yenilendi) sayfa zamanlayıcısı yeter.
  let port = null; let seq = 0;
  const waiters = new Map();
  function getPort() {
    if (port) return port;
    try {
      port = chrome.runtime.connect({ name: 'tgw' });
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
  let busyN = 0;
  function busy(on) {
    busyN = Math.max(0, busyN + (on ? 1 : -1));
    const p = getPort();
    if (p) { try { p.postMessage({ t: 'busy', on: busyN > 0 }); } catch (e) { port = null; } }
  }
  /** Kaydır; arka plandaki sekmede yumuşak kaydırma ilerlemez ve scroll olayı gecikir: anında kaydır + olayı hemen tetikle. */
  function scrollBoxTo(box, top) {
    const hidden = document.hidden;
    if (box) {
      if (hidden) { box.scrollTop = top; try { box.dispatchEvent(new Event('scroll')); } catch (e) { /* yok */ } }
      else box.scrollTo({ top, behavior: 'smooth' });
    }
    const wt = top === 0 ? 0 : document.documentElement.scrollHeight;
    if (hidden) { window.scrollTo(0, wt); try { window.dispatchEvent(new Event('scroll')); } catch (e) { /* yok */ } }
    else window.scrollTo({ top: wt, behavior: 'smooth' });
  }
  const nudge = (el) => { try { (el || window).dispatchEvent(new Event('scroll')); } catch (e) { /* yok */ } };
  const btnText = (b) => norm(b.innerText !== undefined ? b.innerText : b.textContent);
  const visible = (el) => !!el && el.isConnected && !!(el.offsetWidth || el.offsetHeight || el.getClientRects().length);
  const ours = (el) => !!(el.closest && el.closest('.tgw-bar, .tgw-badge, .tgw-orig, .tgw-cbar, .tgw-side, .tgw-word, .tgw-pop, .tgw-descbar'));

  function h(tag, cls, text) {
    const el = document.createElement(tag);
    if (cls) el.className = cls;
    if (text !== undefined) el.textContent = text;
    return el;
  }

  function logo(size) {
    const ns = 'http://www.w3.org/2000/svg';
    const svg = document.createElementNS(ns, 'svg');
    svg.setAttribute('viewBox', '0 0 32 32');
    svg.setAttribute('width', size);
    svg.setAttribute('height', size);
    svg.setAttribute('aria-hidden', 'true');
    MARK.forEach((d, i) => {
      const p = document.createElementNS(ns, 'path');
      p.setAttribute('d', d);
      p.setAttribute('class', 'tgw-m' + (i + 1));
      svg.appendChild(p);
    });
    return svg;
  }

  /** Metin + resim emojilerinin alt yazısı (input, textarea, düğme ve eklentinin kendi öğeleri hariç). */
  function textWithAlt(el) {
    let out = '';
    for (const n of el.childNodes) {
      if (n.nodeType === 3) out += n.nodeValue;
      else if (n.nodeType === 1) {
        const tag = n.tagName;
        if (tag === 'IMG') out += n.getAttribute('alt') || '';
        else if (tag === 'INPUT' || tag === 'TEXTAREA' || tag === 'BUTTON' || tag === 'SELECT' || tag === 'SCRIPT' || tag === 'STYLE') continue;
        else if (ours(n)) continue;
        else out += textWithAlt(n);
      }
    }
    return out;
  }

  /** İnceleme ekranı: "Import services" düğmesi + Edit/Save düğmeli satırlar. */
  const DIALOG = '[role=dialog], .modal, .modal-dialog, .modal-content';
  function findModal() {
    // Sayfanın üst araç çubuğunda da "Import services" düğmesi var (modal'ı açan); asıl olan modal'ın içindeki.
    const all = [...document.querySelectorAll('button, a, input[type=submit]')]
      .filter((b) => visible(b) && !ours(b) && lower(b.value || btnText(b)) === 'import services');
    const importBtn = all.filter((b) => b.closest(DIALOG)).pop();
    if (!importBtn) return null;
    return { box: importBtn.closest(DIALOG), importBtn };
  }

  function actionButtons(box) {
    return [...box.querySelectorAll('button, a')].filter((b) => {
      const t = lower(btnText(b));
      return (t === 'edit' || t === 'save') && !ours(b);
    });
  }

  /** Bir satırın kimliği ve başlıktaki ad: "15341 - S106 - Instagram ..." */
  function rowHeader(row) {
    let best = null;
    const walker = document.createTreeWalker(row, NodeFilter.SHOW_ELEMENT);
    for (let el = walker.currentNode; el; el = walker.nextNode()) {
      if (ours(el)) continue;
      const t = norm(textWithAlt(el));
      const m = ROW_ID.exec(t);
      if (!m) continue;
      if (!best || t.length < best.text.length) best = { el, text: t, id: m[1], name: norm(m[2]) };
    }
    if (best) best.name = best.name.replace(/\s*(Min|Max)\s*:\s*[\d.,]+.*$/i, '').trim();
    return best;
  }

  /** Satırlar: her Edit/Save düğmesinin, başka Edit/Save içermeyen en geniş atası. */
  function scanRows() {
    const m = findModal();
    if (!m) return [];
    const rows = [];
    const seen = new Set();
    for (const b of actionButtons(m.box)) {
      let row = b;
      while (row.parentElement && row.parentElement !== m.box) {
        const p = row.parentElement;
        if (actionButtons(p).length > 1) break;
        row = p;
      }
      const hd = rowHeader(row);
      if (!hd || seen.has(hd.id)) continue;
      seen.add(hd.id);
      rows.push({ id: hd.id, el: row, header: hd });
    }
    return rows;
  }

  const findRow = (id) => scanRows().find((r) => r.id === id) || null;
  const rowButton = (row, label) => actionButtons(row.el).find((b) => lower(btnText(b)) === label && visible(b)) || null;

  /** Name kutusu: "Name" yazısından sonraki ilk metin kutusu; yoksa sayı olmayan en uzun metin kutusu. */
  function nameInput(row) {
    const inputs = [...row.el.querySelectorAll('input[type=text], input:not([type]), textarea')]
      .filter((i) => visible(i) && !i.readOnly && !i.disabled && !ours(i));
    if (!inputs.length) return null;
    const label = [...row.el.querySelectorAll('label, div, span, strong, b, p, h5, h6, td, th')]
      .find((el) => el.children.length === 0 && lower(el.textContent) === 'name');
    if (label) {
      const after = inputs.find((i) => label.compareDocumentPosition(i) & Node.DOCUMENT_POSITION_FOLLOWING);
      if (after && after.tagName === 'INPUT') return after;
    }
    const texty = inputs.filter((i) => i.tagName === 'INPUT' && !/^\s*[\d.,]*\s*$/.test(i.value));
    texty.sort((a, b) => b.value.length - a.value.length);
    return texty[0] || null;
  }

  /** Çerçeve (React/Vue/Angular) değişikliği görsün diye yerel setter + olaylar. */
  function setValue(input, value) {
    const proto = input.tagName === 'TEXTAREA' ? HTMLTextAreaElement.prototype : HTMLInputElement.prototype;
    Object.getOwnPropertyDescriptor(proto, 'value').set.call(input, value);
    input.dispatchEvent(new Event('input', { bubbles: true }));
    input.dispatchEvent(new Event('change', { bubbles: true }));
    input.dispatchEvent(new Event('blur', { bubbles: true }));
  }

  async function waitFor(fn, timeout = 8000, step = 80) {
    const t0 = Date.now();
    for (;;) {
      const v = fn();
      if (v) return v;
      if (Date.now() - t0 > timeout) return null;
      await sleep(step);
    }
  }

  function send(msg) {
    return new Promise((resolve) => {
      try {
        chrome.runtime.sendMessage(msg, (res) => {
          if (chrome.runtime.lastError) resolve({ ok: false, error: chrome.runtime.lastError.message });
          else resolve(res || { ok: false, error: 'Cevap yok' });
        });
      } catch (e) {
        resolve({ ok: false, error: 'Eklenti güncellendi; sayfayı yenileyin.' });
      }
    });
  }
  const api = (action, payload) => send({ type: 'api', action, payload });

  function opts() {
    return new Promise((resolve) => {
      const d = { delay: 400, dry: false, skipAz: true, autoCat: true, catTop: true, notify: true, bracketSpace: true, stripMinMax: true, stripSpeed: true, descTranslate: true, descMode: '', catLogo: true, catLogoReplace: false };
      try { chrome.storage.local.get(d, (s) => resolve(s || d)); } catch (e) { resolve(d); }
    });
  }

  /**
   * Parantez boşluğu: "[Metin]" → "[ Metin ]", "(Metin)" → "( Metin )". İçerideki fazla boşluk teke iner;
   * yalnızca eşleşen çiftlere dokunulur (tek başına ")" ya da ":)" gibi işaretler değişmez), boş "[]" / "()" olduğu gibi kalır.
   */
  function spaceBrackets(s) {
    let out = String(s == null ? '' : s);
    for (let i = 0; i < 4; i++) { // iç içe parantezler için birkaç tur
      const before = out;
      out = out.replace(/\[[ \t]*([^\[\]]*?)[ \t]*\]/g, (m, x) => (x ? '[ ' + x + ' ]' : m))
        .replace(/\([ \t]*([^()]*?)[ \t]*\)/g, (m, x) => (x ? '( ' + x + ' )' : m));
      if (out === before) break;
    }
    return out;
  }
  /**
   * Min/Max değerleri: "Max 500K", "Maks 10M", "Min 100", "Maksimum: 1M", "Ən çox 50K" … addan çıkarılır.
   * Yalnızca bunlardan oluşan parantez ([ Max 1M ], ( Min 100 - Max 10M )) tamamen kalkar; geride kalan çift ayırıcı ("| |") teke iner.
   * Sayısız kelimeler ("Minecraft", "Max Kalite") ve hız gibi diğer değerler ("Gündə 500K") değişmez.
   */
  const MINMAX_SRC = '(?:\\b(?:min(?:imum)?|max(?:imum)?|maks(?:imum)?)|ən\\s+az|ən\\s+çox)\\.?\\s*[:=]?\\s*\\d[\\d.,]*\\s*(?:k|m|b)?(?![\\p{L}\\p{N}])';
  /**
   * Günlük hız: "500K/D", "10K / Day", "Gündə 500K", "Gün 50K", "Günlük 1M", "Daily 20K", "Speed 10K", "50K per day" …
   * Yalnızca bir MİKTAR varsa silinir: "30 Gün Qarantili", "365 Gün" gibi süreler değişmez.
   */
  const VAL_SRC = '\\d[\\d.,]*\\s*(?:k|m|b)?';
  const SPEED_SRC = '(?:\\b(?:gündə|gunde|günlük|gunluk|gün|gun|daily|per\\s+day|day|speed|sürət|surat)\\s*[:=]?\\s*' + VAL_SRC + '(?:\\s*/\\s*(?:d|day|gün|gun))?'
    + '|\\b' + VAL_SRC + '\\s*(?:/\\s*(?:d|day|days|gün|gun|günlük|daily)|\\s+(?:per\\s+day|a\\s+day|daily|gündə|günlük)))(?![\\p{L}\\p{N}])';
  const stripMinMax = (s) => stripTokens(s, MINMAX_SRC);
  const stripSpeed = (s) => stripTokens(s, SPEED_SRC);
  function stripTokens(s, SRC) {
    let out = String(s == null ? '' : s);
    const has = (t) => new RegExp(SRC, 'iu').test(t);
    if (!has(out)) return out;
    const all = new RegExp(SRC, 'giu');
    const SEP = '[|/,;–-]';
    const tidy = (t) => t.replace(all, ' ').replace(/\s+/g, ' ')
      .replace(new RegExp('(' + SEP + ')(\\s*' + SEP + ')+', 'g'), '$1')
      .replace(new RegExp('^\\s*' + SEP + '\\s*'), '').replace(new RegExp('\\s*' + SEP + '\\s*$'), '')
      .replace(/\s+/g, ' ').trim();
    // parantez içleri: yalnız min/max ise parantez tamamen kalkar
    out = out.replace(/\[([^\[\]]*)\]/g, (m, x) => { if (!has(x)) return m; const c = tidy(x); return c ? '[' + c + ']' : ''; })
      .replace(/\(([^()]*)\)/g, (m, x) => { if (!has(x)) return m; const c = tidy(x); return c ? '(' + c + ')' : ''; });
    return tidy(out);
  }
  /** Ayarlara göre adı biçimlendir (çevrilen her servis/kategori adı bu yoldan yazılır): min/max, günlük hız, sonra parantez boşluğu. */
  const fmtName = (name, o) => {
    let t = name;
    if (!o || o.stripMinMax !== false) t = stripMinMax(t);
    if (!o || o.stripSpeed !== false) t = stripSpeed(t);
    if (!o || o.bracketSpace !== false) t = spaceBrackets(t);
    return t;
  };

  // ---- açıklama çevirisi (tgwatch: banka + Google + DeepSeek) ----
  const DESC_LABEL = /^(description|açıklama|açıqlama|təsvir)\s*:?$/;
  /** Açıklama alanı: "Description" yazısından sonraki ilk textarea/metin kutusu/zengin editör; yoksa satırdaki textarea. */
  function descField(scope, exclude) {
    const cands = [...scope.querySelectorAll('textarea, input[type=text], input:not([type]), [contenteditable=""], [contenteditable="true"]')]
      .filter((i) => visible(i) && !ours(i) && i !== exclude && !i.readOnly && !i.disabled);
    if (!cands.length) return null;
    const label = [...scope.querySelectorAll('label, div, span, strong, b, p, h4, h5, h6, td, th')]
      .find((el) => el.children.length === 0 && DESC_LABEL.test(lower(el.textContent)));
    if (label) {
      const after = cands.find((i) => label.compareDocumentPosition(i) & Node.DOCUMENT_POSITION_FOLLOWING);
      if (after) return after;
    }
    return cands.find((i) => i.tagName === 'TEXTAREA' || i.isContentEditable) || null;
  }
  const readField = (el) => (el.isContentEditable ? el.innerText : el.value);
  function writeField(el, text) {
    if (!el.isContentEditable) return setValue(el, text);
    el.focus();
    el.innerText = text;
    el.dispatchEvent(new InputEvent('input', { bubbles: true, inputType: 'insertText' }));
    el.dispatchEvent(new Event('change', { bubbles: true }));
    el.dispatchEvent(new Event('blur', { bubbles: true }));
  }
  const DESC_VIA = { bank: 'bankadan', mix: 'banka + Google + DeepSeek', deepseek: 'DeepSeek', google: 'Google' };
  /** Alandaki açıklamayı tgwatch ile çevirip yazar; dönüş: {ok, note, src}. Ad kuralları (min/max, parantez) açıklamaya uygulanmaz. */
  async function translateDescField(el, o) {
    const src = readField(el);
    if (!norm(src)) return { ok: false, note: '' };
    if (AZ_ONLY.test(src)) return { ok: false, note: 'açıklama zaten Azerice' };
    const res = await api('translate_desc', { mode: o.descMode || undefined, items: [{ id: 'd', text: src }] });
    if (!res.ok) return { ok: false, note: 'açıklama: ' + (res.error || 'tgwatch hatası') };
    const it = res.items && res.items[0];
    if (!it || !it.az) return { ok: false, note: 'açıklama çevrilmedi' + (it && it.note ? ' (' + it.note + ')' : '') };
    writeField(el, it.az);
    if (it.via !== 'bank') api('learn_desc', { items: [{ en: src, az: it.az }] });
    return { ok: true, src, note: 'açıklama ' + (DESC_VIA[it.via] || it.via) + (it.note ? ' (' + it.note + ')' : '') };
  }
  /** İmport satırında açıklama (ayar açıksa): satır açıkken çağrılır. */
  async function rowDesc(id, o) {
    if (!o.descTranslate) return '';
    const row = findRow(id);
    if (!row) return '';
    const el = descField(row.el, nameInput(row));
    if (!el) return '\nAçıklama alanı bulunamadı';
    const r = await translateDescField(el, o);
    if (r.ok && !(state.get(id) || {}).descSrc) remember(id, { descSrc: r.src });
    return r.note ? '\n' + r.note.charAt(0).toUpperCase() + r.note.slice(1) : '';
  }

  // ---- "Edit description" penceresi (Services sayfası): "tgwatch: çevir" düğmesi ----
  function descDialogs() {
    return [...document.querySelectorAll(DIALOG)].filter((d) => visible(d) && !ours(d) && /description|açıklama/i.test(norm(d.textContent).slice(0, 400)) && !d.querySelector('.tgw-descbtn'));
  }
  function addDescButtons() {
    for (const d of descDialogs()) {
      const el = descField(d, null);
      if (!el) continue;
      const box = d.matches('.modal-content') ? d : d.querySelector('.modal-content') || d;
      if (box.querySelector('.tgw-descbtn')) continue;
      const b = h('button', 'tgw-descbtn', 'tgwatch: açıklamayı çevir');
      b.type = 'button';
      b.style.cssText = 'margin:6px 0;padding:4px 10px;border-radius:6px;border:1px solid #2f6f4f;background:#e9f7ef;color:#1d4d35;cursor:pointer;font-size:12px';
      const note = h('span', 'tgw-descnote');
      note.style.cssText = 'margin-left:8px;font-size:12px;color:#555';
      b.addEventListener('click', async (ev) => {
        ev.preventDefault(); ev.stopPropagation();
        const field = descField(d, null);
        if (!field) { note.textContent = 'açıklama alanı bulunamadı'; return; }
        b.disabled = true; note.textContent = 'çevriliyor…';
        const r = await translateDescField(field, await opts());
        b.disabled = false;
        note.textContent = r.ok ? '✓ ' + r.note + ' — kontrol edip kaydet' : (r.note || 'çevrilecek yazı yok');
      });
      const wrap = h('div', 'tgw-descbar');
      wrap.append(b, note);
      el.insertAdjacentElement('beforebegin', wrap);
    }
  }

  // ---- satır durumu ----
  const state = new Map(); // id => {src, filled, final, edited}
  const remember = (id, patch) => state.set(id, Object.assign(state.get(id) || {}, patch));
  const marks = new Map(); // id => {kind, title} — panel satırları yeniden çizerse yeniden uygulanır

  function mark(row, kind, title) {
    marks.set(row.id, { kind, title: title || '' });
    paint(row);
    renderCounts();
  }

  function unmark(row) {
    marks.delete(row.id);
    row.el.classList.remove('tgw-row-ok', 'tgw-row-warn', 'tgw-row-bad', 'tgw-row-done');
    const b = row.header.el.querySelector(':scope > .tgw-badge');
    if (b) b.remove();
    const o = row.header.el.querySelector(':scope > .tgw-orig');
    if (o) o.remove();
    renderCounts();
  }

  /** Çevrilen adın üstünde gölge gibi orijinal ad; tıklayınca kopyalanır. */
  function shadow(row) {
    const st = state.get(row.id);
    const host = row.header.el;
    let o = host.querySelector(':scope > .tgw-orig');
    if (!st || !st.src || lower(st.src) === lower(row.header.name)) {
      if (o) o.remove();
      return;
    }
    if (o && o.dataset.src === st.src) return;
    if (!o) {
      o = h('span', 'tgw-orig');
      o.title = 'Orijinal ad — bir kelimeye tıkla: çevirisini değiştir · boşluğa tıkla: kopyala';
      o.addEventListener('click', (e) => {
        e.preventDefault();
        e.stopPropagation();
        // kelimeye tıklandıysa: o kelimenin bankadaki/sözlükteki karşılığını değiştir
        const w = e.target.closest && e.target.closest('.tgw-ow');
        if (w) { openTeach(o.__rid, w.textContent, w, true); return; }
        navigator.clipboard && navigator.clipboard.writeText(o.dataset.src).then(() => {
          o.classList.add('tgw-copied');
          setTimeout(() => o.classList.remove('tgw-copied'), 900);
        });
      });
      host.insertBefore(o, host.firstChild);
    }
    o.dataset.src = st.src;
    o.__rid = row.id;
    fillWords(o, st.src);
  }
  /** Metni kelime kelime yaz: her kelime tıklanabilir (.tgw-ow), aradaki işaretler düz metin. */
  function fillWords(el, text) {
    el.textContent = '';
    let last = 0;
    for (const m of String(text).matchAll(/\p{L}[\p{L}'’-]*\p{L}|\p{L}/gu)) {
      if (m.index > last) el.appendChild(document.createTextNode(text.slice(last, m.index)));
      const w = h('span', 'tgw-ow', m[0]);
      w.title = '“' + m[0] + '” — tıkla: bu kelimenin çevirisini değiştir (sözlük + banka)';
      el.appendChild(w);
      last = m.index + m[0].length;
    }
    if (last < text.length) el.appendChild(document.createTextNode(text.slice(last)));
  }
  /** Tam kelime/ifade kalıbı (sunucudaki Memory::wordRegex ile aynı). */
  const wordRe = (w) => new RegExp('(?<![\\p{L}\\p{N}])' + norm(w).split(' ').map((x) => x.replace(/[.*+?^${}()|[\]\\]/g, '\\$&')).join('\\s+') + '(?![\\p{L}\\p{N}])', 'iu');

  function paint(row) {
    const mk = marks.get(row.id);
    if (!mk) return;
    shadow(row);
    row.el.classList.toggle('tgw-hide', !!(bar && bar.classList.contains('tgw-only-issues')) && (mk.kind === 'ok' || mk.kind === 'done'));
    const host = row.header.el;
    let badge = host.querySelector(':scope > .tgw-badge');
    const st0 = state.get(row.id) || {};
    const words = (mk.kind === 'warn' || mk.kind === 'bad') ? (st0.unknown || []).slice(0, 5) : [];
    const sig = mk.kind + '|' + words.join('|') + '|' + (st0.filled ? 1 : 0);
    if (row.el.classList.contains('tgw-row-' + mk.kind) && badge && badge.dataset.sig === sig) return;
    row.el.classList.remove('tgw-row-ok', 'tgw-row-warn', 'tgw-row-bad', 'tgw-row-done');
    row.el.classList.add('tgw-row-' + mk.kind);
    if (!badge) {
      badge = h('span', 'tgw-badge');
      host.appendChild(badge);
    }
    badge.textContent = '';
    badge.dataset.kind = mk.kind;
    badge.dataset.sig = sig;
    badge.className = 'tgw-badge tgw-badge-' + mk.kind;
    badge.title = mk.title;
    badge.appendChild(h('span', '', KIND_TEXT[mk.kind]));
    const st = state.get(row.id);
    if (st && st.src && st.filled) {
      const undo = h('button', 'tgw-undo1', '↺');
      undo.type = 'button';
      undo.title = 'Bu satırı orijinal ada döndür: ' + st.src;
      undo.addEventListener('click', (e) => { e.preventDefault(); e.stopPropagation(); restore([row.id]); });
      badge.appendChild(undo);
    }
    for (const w of words) {
      const c = h('button', 'tgw-word', w);
      c.type = 'button';
      c.title = '“' + w + '” bilinmiyor — tıkla, Azericesini yaz: satır düzelir, kelime tgwatch sözlüğüne girer';
      c.addEventListener('click', (e) => { e.preventDefault(); e.stopPropagation(); openTeach(row.id, w, c); });
      host.appendChild(c);
    }
    host.querySelectorAll(':scope > .tgw-word').forEach((c) => { if (!words.includes(c.textContent)) c.remove(); });
  }

  // ---- bilinmeyen kelimeyi satırda düzelt: kelime sözlüğe girer, aynı kelimeli satırlar yeniden çevrilir ----
  let pop = null;
  function closeTeach() { if (pop) { pop.remove(); pop = null; } }

  /** edit = true: bilinen kelimenin karşılığını değiştir (şimdiki karşılık tgwatch'tan sorulup kutuya yazılır). */
  function openTeach(id, word, anchor, edit) {
    closeTeach();
    pop = h('div', 'tgw-pop');
    pop.setAttribute('role', 'dialog');
    pop.setAttribute('aria-label', edit ? 'Kelimenin çevirisini değiştir' : 'Kelimeyi öğret');
    const f = h('form', 'tgw-pop-f');
    const l1 = h('label', '', 'Kaynak');
    const en = h('input');
    en.value = word;
    en.title = 'Gerekirse ifadeyi uzat: “Low” yerine “Low Quality”';
    l1.appendChild(en);
    const l2 = h('label', '', 'Azerice');
    const az = h('input');
    az.placeholder = 'Azerice karşılığı';
    l2.appendChild(az);
    const msg = h('div', 'tgw-pop-msg');
    const acts = h('div', 'tgw-pop-a');
    const ok = h('button', 'tgw-btn', edit ? 'Değiştir ve düzelt' : 'Öğret ve düzelt');
    ok.type = 'submit';
    const keep = h('button', 'tgw-btn tgw-ghost', 'Aynen kalsın');
    keep.type = 'button';
    keep.title = 'Marka/özel ad: çevrilmesin, bir daha bilinmeyen sayılmasın';
    const x = h('button', 'tgw-btn tgw-ghost tgw-pop-x', '×');
    x.type = 'button';
    x.title = 'Kapat (Esc)';
    acts.append(ok, keep, x);
    // ikinci satır: "hiç yazılmasın" (kelimeyi adlardan sil) ve son değişikliği geri al
    const acts2 = h('div', 'tgw-pop-a tgw-pop-a2');
    const rm = h('button', 'tgw-btn tgw-ghost', 'Hiç yazılmasın');
    rm.type = 'button';
    rm.title = 'Bu kelime adlarda hiç yazılmasın: çeviriden önce silinir, bankadaki adlardan da çıkarılır';
    const undo = h('button', 'tgw-btn tgw-ghost', '↶ Geri al');
    undo.type = 'button';
    undo.hidden = true;
    acts2.append(rm, undo);
    f.append(l1, l2, acts, acts2, msg);
    pop.appendChild(f);
    for (const ev of ['click', 'mousedown', 'keydown', 'keyup', 'keypress', 'input']) pop.addEventListener(ev, (e) => e.stopPropagation());
    pop.addEventListener('keydown', (e) => { if (e.key === 'Escape') closeTeach(); });
    x.addEventListener('click', closeTeach);
    const lock = (v) => { ok.disabled = keep.disabled = rm.disabled = undo.disabled = v; };
    /** mode: true/'keep' (aynen kalsın) | 'remove' (hiç yazılmasın) | false (karşılık yaz) */
    const go = async (mode) => {
      const asKeep = mode === true || mode === 'keep';
      const asRemove = mode === 'remove';
      const src = en.value.trim();
      const dst = az.value.trim();
      if (!src || (!asKeep && !asRemove && !dst)) { msg.className = 'tgw-pop-msg'; msg.textContent = !src ? 'Kaynak kelimeyi yaz' : 'Azerice karşılığı yaz'; (!src ? en : az).focus(); return; }
      lock(true);
      msg.className = 'tgw-pop-msg';
      msg.textContent = 'tgwatch\'a öğretiliyor…';
      const res = await api('teach', asKeep ? { en: src, keep: true } : asRemove ? { en: src, remove: true } : { en: src, az: dst });
      if (!res.ok) {
        // Eski sunucu "hiç yazılmasın"ı tanımaz: isteği boş karşılık sanıp reddeder. Kullanıcıya gerçek nedeni söyle.
        const oldServer = asRemove && /Azerice karşılık|Bilinmeyen işlem/.test(res.error || '');
        msg.textContent = oldServer ? 'tgwatch sunucusu eklentiden eski: "Hiç yazılmasın" desteklenmiyor. Sunucuya güncel tgwatch paketini yükle.' : (res.error || 'tgwatch hatası');
        lock(false);
        return;
      }
      if (asRemove && !res.remove) { msg.textContent = 'tgwatch sunucusu eski: "hiç yazılmasın" desteklenmiyor — sunucuyu güncelle.'; lock(false); return; }
      send({ type: 'stat', learned: 1 });
      // Banka sözlükten önce gelir: sunucu eski kayıtları da düzeltir (res.bank adlar, res.desc açıklamalar). Eski sunucu bu alanları göndermez.
      const bankNote = res.bank === undefined ? ' · tgwatch sunucusu eski: bankadaki eski kayıtlar düzeltilmedi'
        : (res.bank || res.desc ? ' · bankada ' + (res.bank || 0) + ' kayıt' + (res.desc ? ', ' + res.desc + ' açıklama' : '') + ' düzeltildi' : '');
      msg.className = 'tgw-pop-msg tgw-pop-ok';
      msg.textContent = (asRemove ? '✓ “' + res.en + '” artık adlarda yazılmayacak' : asKeep ? '✓ tgwatch sözlüğüne eklendi: “' + res.en + '” çevrilmeyecek' : '✓ tgwatch sözlüğüne eklendi: “' + res.en + '” → “' + res.az + '”') + bankNote;
      status(msg.textContent.slice(2) + ' · satırlar düzeltiliyor…', 'ok');
      await sleep(900);
      closeTeach();
      await retranslate(id, src, asRemove ? '' : asKeep ? src : res.az, bankNote, asRemove ? 'remove' : '');
    };
    rm.addEventListener('click', () => go('remove'));
    // son kelime değişikliğini geri al (sözlük + banka), sonra o kelimenin geçtiği satırları yeniden çevir
    api('word_undo', { peek: true }).then((r) => {
      if (!pop || !r || !r.ok || !r.last) return;
      undo.hidden = false;
      undo.title = 'Son değişikliği geri al: “' + r.last.en + '” → ' + r.last.to + (r.last.rows ? ' (' + r.last.rows + ' banka kaydı)' : '');
    });
    undo.addEventListener('click', async () => {
      lock(true);
      msg.className = 'tgw-pop-msg';
      msg.textContent = 'Geri alınıyor…';
      const r = await api('word_undo', {});
      if (!r.ok) { msg.textContent = r.error || 'tgwatch hatası'; lock(false); return; }
      msg.className = 'tgw-pop-msg tgw-pop-ok';
      msg.textContent = '↶ Geri alındı: “' + r.en + '” yine ' + r.was;
      await sleep(900);
      closeTeach();
      await retranslate(id, r.en, '', ' · bankada ' + (r.restored || 0) + ' kayıt eski hâline döndü', 'undo');
    });
    if (edit) {
      // şimdiki karşılığı göster (kullanıcı kaynağı uzatırsa/değiştirirse yeniden sorulur)
      let seq = 0;
      const info = async () => {
        const src = en.value.trim();
        if (!src) return;
        const my = ++seq;
        const r = await api('word_info', { en: src });
        if (my !== seq || !pop || ok.disabled) return;
        if (!r.ok) { if (/Bilinmeyen işlem/.test(r.error || '')) { msg.className = 'tgw-pop-msg tgw-pop-info'; msg.textContent = 'tgwatch sunucusu eski: şimdiki karşılık gösterilemiyor (yine de değiştirebilirsin).'; } return; }
        if (r.state === 'phrase' && !az.value.trim()) az.value = r.az;
        msg.className = 'tgw-pop-msg tgw-pop-info';
        msg.textContent = (r.state === 'phrase' ? 'Şimdi: “' + r.az + '”' : r.state === 'keep' ? 'Şimdi: çevrilmiyor (aynen kalsın)' : r.state === 'remove' ? 'Şimdi: adlarda hiç yazılmıyor' : 'Sözlükte yok')
          + (r.bank ? ' · bankada ' + r.bank + ' adda' + (r.desc ? ', ' + r.desc + ' açıklamada' : '') + ' geçiyor' : r.desc ? ' · ' + r.desc + ' açıklamada geçiyor' : '');
      };
      en.addEventListener('change', info);
      info();
    }
    f.addEventListener('submit', (e) => { e.preventDefault(); go(false); });
    keep.addEventListener('click', () => go(true));
    // Kutu import penceresinin İÇİNE konur: panel penceresi odağı ve tıklamaları kendi içinde tutuyor (dışarıdaki kutuya yazılamaz).
    const host = anchor.closest('.modal') || anchor.closest('[role=dialog]') || anchor.closest('.modal-content') || document.body;
    host.appendChild(pop);
    const r = anchor.getBoundingClientRect();
    const w = 320;
    const want = { x: Math.max(8, Math.min(innerWidth - w - 8, r.left)), y: r.bottom + 6 + 226 > innerHeight ? Math.max(8, r.top - 232) : r.bottom + 6 };
    pop.style.left = want.x + 'px';
    pop.style.top = want.y + 'px';
    // Pencerede transform varsa "fixed" konum pencereye göre olur: farkı düzelt.
    const got = pop.getBoundingClientRect();
    pop.style.left = (want.x + (want.x - got.left)) + 'px';
    pop.style.top = (want.y + (want.y - got.top)) + 'px';
    setTimeout(() => az.focus(), 0);
    setTimeout(() => { if (pop && document.activeElement !== az && document.activeElement !== en) az.focus(); }, 120);
  }

  /** Öğretilen kelimeyi içeren (bilinmeyen listesinde olan) tüm satırları yeniden çevirip yaz. */
  /** how: '' (öğretildi/değişti) | 'remove' (kelime silindi) | 'undo' (değişiklik geri alındı) */
  async function retranslate(firstId, word, az, note, how) {
    if (running) return;
    const parts = lower(word).split(' ');
    const wre = wordRe(word);
    // tıklanan satır + kelimenin bilinmeyen olduğu satırlar + orijinal adında bu kelime geçen satırlar (elle düzelttiklerin hariç)
    const ids = [...state.entries()].filter(([rid, st]) => st.src && (rid === firstId
      || (st.unknown || []).some((u) => parts.includes(lower(u))) || (!st.edited && wre.test(st.src)))).map(([rid]) => rid);
    ids.sort((a, b) => (a === firstId ? -1 : b === firstId ? 1 : 0));
    setRunning(true);
    const o = await opts();
    let fixed = 0;
    try {
      const res = await api('translate', { items: ids.map((rid) => ({ id: rid, name: state.get(rid).src })) });
      if (!res.ok) { status(res.error || 'tgwatch hatası', 'bad'); return; }
      for (let k = 0; k < ids.length; k++) {
        const rid = ids[k];
        const tr = res.items[k];
        if (!tr || !tr.az || tr.via === 'none' || tr.via === 'already') continue;
        tr.az = fmtName(tr.az, o);
        if (rid !== firstId && tr.az === state.get(rid).filled) continue; // bu satırda değişen bir şey yok
        status('Düzeltiliyor · #' + rid);
        const row = await openRow(rid);
        if (!row) continue;
        setValue(nameInput(row), tr.az);
        remember(rid, { filled: tr.az, final: tr.az, edited: false, unknown: tr.via === 'bank' || tr.via === 'claude' ? [] : (tr.unknown || []) });
        if (!o.dry) await saveRow(rid);
        const r = findRow(rid) || row;
        const left = state.get(rid).unknown;
        mark(r, tr.via === 'bank' || !left.length ? 'ok' : 'warn', 'Orijinal: ' + state.get(rid).src + (left.length ? '\nBilinmeyen: ' + left.join(', ') : '\nKelime öğretildi'));
        fixed++;
      }
      status((how === 'undo' ? '↶ Geri alındı: “' + word + '”' : how === 'remove' ? '✓ “' + word + '” artık adlarda yazılmayacak' : '✓ Sözlüğe eklendi: “' + word + '”' + (az && az !== word ? ' → “' + az + '”' : ' (çevrilmeyecek)')) + (note || '') + ' · ' + fixed + ' satır düzeltildi. tgwatch → Çeviri → Sözlük\'te görünür.', 'ok');
    } finally {
      setRunning(false);
    }
  }

  // ---- araç çubuğu (import penceresinin içinde, başlığın altında) ----
  let bar = null;
  let running = false;
  let stopFlag = false;
  const jump = { ok: 0, warn: 0, bad: 0 };

  function buildBar() {
    bar = h('div', 'tgw-bar');
    const brand = h('div', 'tgw-brand');
    brand.appendChild(logo(26));
    const txt = h('div', 'tgw-text');
    txt.appendChild(h('div', 'tgw-title', 'tgwatch çeviri'));
    txt.appendChild(h('div', 'tgw-status'));
    brand.appendChild(txt);
    const chips = h('div', 'tgw-chips');
    for (const k of ['ok', 'warn', 'bad']) {
      const c = h('button', 'tgw-chip tgw-chip-' + k);
      c.type = 'button';
      c.dataset.kind = k;
      c.title = { ok: 'Bankadan gelenler', warn: 'Kontrol etmen gerekenler — tıkla, sıradakine git', bad: 'Çevrilemeyenler — tıkla, sıradakine git' }[k];
      c.appendChild(h('i'));
      c.appendChild(h('b', '', '0'));
      c.appendChild(h('span', '', KIND_TEXT[k]));
      c.addEventListener('click', () => goNext(k));
      chips.appendChild(c);
    }
    const acts = h('div', 'tgw-actions');
    const go = h('button', 'tgw-btn tgw-go', 'Çevir');
    go.type = 'button';
    go.title = 'Seçtiğin servislerin adlarını tgwatch ile çevir (Alt+Shift+T)';
    go.addEventListener('click', () => runAll());
    const stop = h('button', 'tgw-btn tgw-stop', 'Durdur');
    stop.type = 'button';
    stop.hidden = true;
    stop.addEventListener('click', () => { stopFlag = true; status('Durduruluyor…'); });
    const undo = h('button', 'tgw-btn tgw-ghost tgw-undo', 'Geri al');
    undo.type = 'button';
    undo.title = 'Eklentinin değiştirdiği tüm adları orijinaline döndür';
    undo.hidden = true;
    undo.addEventListener('click', () => restore(null));
    const filter = h('button', 'tgw-btn tgw-ghost tgw-filter', 'Sadece sorunlular');
    filter.type = 'button';
    filter.title = 'Yeşil satırları gizle, yalnızca kontrol edilecekleri ve çevrilmeyenleri göster (import edilmeleri etkilenmez)';
    filter.addEventListener('click', () => {
      const on = bar.classList.toggle('tgw-only-issues');
      filter.textContent = on ? 'Hepsini göster' : 'Sadece sorunlular';
      for (const r of scanRows()) paint(r);
    });
    acts.append(go, stop, filter, undo);
    const prog = h('div', 'tgw-progress');
    prog.appendChild(h('i'));
    bar.append(brand, chips, acts, prog);
  }

  function placeBar(m) {
    const title = [...m.box.querySelectorAll('h1, h2, h3, h4, h5, .modal-title, div, span')]
      .find((el) => el.children.length === 0 && lower(el.textContent) === 'import services' && !ours(el));
    const header = title && (title.closest('.modal-header') || title.parentElement);
    if (header && header.parentElement && m.box.contains(header)) {
      if (bar.previousElementSibling !== header || !bar.isConnected) header.insertAdjacentElement('afterend', bar);
      bar.classList.remove('tgw-floating');
    } else if (!bar.isConnected) {
      document.body.appendChild(bar);
      bar.classList.add('tgw-floating');
    }
  }

  let sideBtn = null;
  /** Panelin "Import services" düğmesinin hemen soluna "tgwatch ile çevir". */
  function placeSideButton(m) {
    if (!sideBtn) {
      sideBtn = h('button', 'tgw-side');
      sideBtn.type = 'button';
      sideBtn.appendChild(logo(16));
      sideBtn.appendChild(h('span', '', 'tgwatch ile çevir'));
      sideBtn.title = 'Seçili servislerin adlarını tgwatch bankasıyla Azericeye çevir (Alt+Shift+T)';
      sideBtn.addEventListener('click', (e) => { e.preventDefault(); e.stopPropagation(); runAll(); });
    }
    if (sideBtn.nextElementSibling !== m.importBtn) m.importBtn.insertAdjacentElement('beforebegin', sideBtn);
    sideBtn.disabled = running;
  }

  function ensureBar() {
    const m = findModal();
    const rows = m ? scanRows() : [];
    if (!m || !rows.length) {
      if (bar && bar.isConnected) bar.remove();
      if (sideBtn && sideBtn.isConnected) sideBtn.remove();
      return;
    }
    if (!bar) buildBar();
    placeBar(m);
    placeSideButton(m);
    for (const r of rows) paint(r);
    renderCounts(rows);
    if (!running && !marks.size) status(rows.length + ' servis seçili · Çevir\'e bas');
  }

  function status(t, kind) {
    if (!bar) return;
    const s = bar.querySelector('.tgw-status');
    s.textContent = t;
    s.title = t;
    s.className = 'tgw-status' + (kind ? ' tgw-status-' + kind : '');
  }

  function progress(done, total) {
    if (!bar) return;
    const p = bar.querySelector('.tgw-progress');
    p.classList.toggle('tgw-on', total > 0 && done < total);
    p.firstChild.style.width = total ? Math.round((done / total) * 100) + '%' : '0';
  }

  function counts() {
    const c = { ok: 0, warn: 0, bad: 0, done: 0 };
    for (const mk of marks.values()) c[mk.kind]++;
    return c;
  }

  function renderCounts() {
    if (!bar) return;
    const c = counts();
    for (const k of ['ok', 'warn', 'bad']) {
      const chip = bar.querySelector('.tgw-chip-' + k);
      chip.querySelector('b').textContent = String(c[k] + (k === 'ok' ? c.done : 0));
      chip.classList.toggle('tgw-zero', !(c[k] + (k === 'ok' ? c.done : 0)));
    }
    bar.querySelector('.tgw-undo').hidden = running || ![...state.values()].some((s) => s.filled && s.src);
    bar.querySelector('.tgw-filter').hidden = !(c.ok + c.done) || !(c.warn + c.bad);
  }

  function goNext(kind) {
    const rows = scanRows().filter((r) => {
      const mk = marks.get(r.id);
      return mk && (mk.kind === kind || (kind === 'ok' && mk.kind === 'done'));
    });
    if (!rows.length) return;
    const r = rows[jump[kind]++ % rows.length];
    r.el.scrollIntoView({ behavior: 'smooth', block: 'center' });
    r.el.classList.remove('tgw-flash');
    void r.el.offsetWidth;
    r.el.classList.add('tgw-flash');
  }

  function setRunning(on) {
    if (on !== running) busy(on);
    running = on;
    if (!bar) return;
    bar.classList.toggle('tgw-running', on);
    bar.querySelector('.tgw-go').disabled = on;
    if (sideBtn) {
      sideBtn.disabled = on;
      sideBtn.querySelector('span').textContent = on ? 'Çevriliyor…' : 'tgwatch ile çevir';
    }
    bar.querySelector('.tgw-stop').hidden = !on;
    renderCounts();
  }

  async function openRow(id) {
    let row = findRow(id);
    if (!row) return null;
    if (!rowButton(row, 'save')) {
      const edit = rowButton(row, 'edit');
      if (!edit) return null;
      edit.click();
      row = await waitFor(() => {
        const r = findRow(id);
        return r && rowButton(r, 'save') && nameInput(r) ? r : null;
      });
    }
    return row && nameInput(row) ? row : null;
  }

  async function saveRow(id) {
    const row = findRow(id);
    const save = row && rowButton(row, 'save');
    if (!save) return false;
    save.click();
    return !!(await waitFor(() => {
      const r = findRow(id);
      return r && !rowButton(r, 'save') ? r : null;
    }, 8000));
  }

  async function runAll() {
    if (running) return { ok: false, error: 'Zaten çalışıyor' };
    setRunning(true);
    stopFlag = false;
    const o = await opts();
    let ok = 0, warn = 0, bad = 0, skipped = 0, i = 0;
    try {
      const all = scanRows();
      if (!all.length) {
        status('Satır bulunamadı', 'bad');
        return { ok: false, error: 'Satır bulunamadı' };
      }
      // Zaten çevrilmiş (bu oturumda) ya da zaten Azerice olan satırlar yeniden çevrilmez.
      const rows = [];
      const descOnly = []; // adı atlanan ama açıklaması çevrilecek satırlar
      for (const r of all) {
        const st = state.get(r.id);
        if (st && st.filled && lower(r.header.name) === lower(st.filled)) { skipped++; continue; }
        if (o.skipAz && AZ_ONLY.test(r.header.name)) {
          skipped++;
          if (o.descTranslate && !(st && st.descDone)) descOnly.push(r);
          else if (!marks.has(r.id)) mark(r, 'done', 'Ad zaten Azerice, dokunulmadı');
          continue;
        }
        rows.push(r);
      }
      // Adı Azerice olan satırlar: ada dokunmadan yalnız açıklama (ayar açıksa)
      for (const r of descOnly) {
        if (stopFlag) break;
        status((o.dry ? 'Deneme · ' : '') + 'Açıklama · #' + r.id);
        const row = await openRow(r.id);
        if (!row) continue;
        const dn = await rowDesc(r.id, o);
        remember(r.id, { descDone: true });
        if (!o.dry) await saveRow(r.id);
        const rr = findRow(r.id) || row;
        mark(rr, 'done', 'Ad zaten Azerice, dokunulmadı' + dn);
      }
      if (!rows.length) {
        status('Hepsi zaten çevrilmiş (' + skipped + ' satır). Gözden geçirip Import\'a bas.', 'ok');
        return { ok: true };
      }
      status('tgwatch çeviriyor… (' + rows.length + ' ad)');
      progress(0, rows.length);
      const res = await api('translate', { items: rows.map((r) => ({ id: r.id, name: r.header.name })) });
      if (!res.ok) {
        status(res.error || 'tgwatch hatası', 'bad');
        return res;
      }
      const byName = new Map(res.items.map((it, n) => [lower(rows[n].header.name), it]));
      for (const { id } of rows) {
        if (stopFlag) break;
        i++;
        status((o.dry ? 'Deneme · ' : '') + i + '/' + rows.length + ' · #' + id);
        progress(i - 1, rows.length);
        const row = await openRow(id);
        if (!row) {
          bad++;
          const r = findRow(id);
          if (r) mark(r, 'bad', 'Satır açılamadı (Edit/Name bulunamadı)');
          continue;
        }
        const input = nameInput(row);
        const src = input.value;
        if (!(state.get(id) || {}).src) remember(id, { src });
        let tr = byName.get(lower(src));
        if (!tr) {
          const one = await api('translate', { items: [{ id, name: src }] });
          tr = one.ok ? one.items[0] : null;
        }
        if (tr && tr.via === 'already') {
          skipped++;
          const dn = await rowDesc(id, o);
          if (!o.dry) await saveRow(id);
          const r = findRow(id) || row;
          mark(r, 'done', 'Ad zaten Azerice, dokunulmadı' + dn);
          continue;
        }
        if (!tr || !tr.az || tr.via === 'none') {
          bad++;
          const dn = await rowDesc(id, o);
          if (!o.dry) await saveRow(id);
          const r = findRow(id) || row;
          remember(id, { unknown: tr ? (tr.unknown || []) : [] });
          mark(r, 'bad', (tr ? 'Bankada da kurallarda da karşılığı yok: ' + src : 'tgwatch cevap vermedi') + dn);
          continue;
        }
        tr.az = fmtName(tr.az, o);
        setValue(input, tr.az);
        remember(id, { filled: tr.az, final: tr.az, edited: false, unknown: tr.via === 'bank' || tr.via === 'claude' ? [] : (tr.unknown || []) });
        const dn = await rowDesc(id, o);
        if (!o.dry && !(await saveRow(id))) {
          bad++;
          const r = findRow(id);
          if (r) mark(r, 'bad', 'Save sonrası satır kapanmadı');
          continue;
        }
        const r = findRow(id) || row;
        const why = 'Orijinal: ' + src + (tr.unknown && tr.unknown.length ? '\nBilinmeyen: ' + tr.unknown.join(', ') : '') + dn;
        if (tr.via === 'bank') { ok++; mark(r, 'ok', 'Bankadan birebir\n' + why); }
        else { warn++; mark(r, 'warn', (tr.via === 'mixed' ? 'Kısmen bankadan' : tr.via === 'claude' ? 'Claude ile (tgwatch denetiminden geçti)' : 'Kurallarla') + '\n' + why); }
        await sleep(Math.max(0, Number(o.delay) || 0));
      }
      progress(rows.length, rows.length);
      if (ok + warn) send({ type: 'stat', translated: ok + warn });
      const c = counts();
      const parts = [(c.ok + c.done) + ' hazır', c.warn + ' kontrol et', c.bad + ' çevrilmedi'];
      status((stopFlag ? 'Durduruldu · ' : 'Bitti · ') + parts.join(', ') + (c.warn || c.bad ? ' — sarı/kırmızıya bak, sonra Import' : ' — Import\'a bas'), c.warn || c.bad ? 'warn' : 'ok');
      return { ok: true, translated: ok + warn, bad, skipped };
    } finally {
      setRunning(false);
      progress(0, 0);
    }
  }

  /** Orijinal adlara dön: ids null ise eklentinin değiştirdiği tüm satırlar. */
  async function restore(ids) {
    if (running) return;
    setRunning(true);
    const o = await opts();
    let n = 0;
    try {
      const list = ids || [...state.entries()].filter(([, s]) => s.filled && s.src).map(([id]) => id);
      for (const id of list) {
        const st = state.get(id);
        if (!st || !st.src) continue;
        status('Geri alınıyor · #' + id);
        const row = await openRow(id);
        if (!row) continue;
        setValue(nameInput(row), st.src);
        if (st.descSrc) { const d = descField(row.el, nameInput(row)); if (d) writeField(d, st.descSrc); }
        if (!o.dry) await saveRow(id);
        remember(id, { filled: undefined, final: st.src, edited: false, descSrc: undefined });
        const r = findRow(id);
        if (r) unmark(r);
        n++;
      }
      status(n + ' satır orijinal adına döndü', 'ok');
    } finally {
      setRunning(false);
    }
  }

  // ---- elle yapılan düzenlemeleri ve Import'u yakala ----
  document.addEventListener('click', (e) => {
    if (running) return;
    const b = e.target.closest && e.target.closest('button, a, input[type=submit]');
    if (!b || ours(b)) return;
    const t = lower(b.value || btnText(b));
    if (t === 'edit') {
      const row = scanRows().find((r) => r.el.contains(b));
      if (!row) return;
      const id = row.id;
      waitFor(() => { const r = findRow(id); return r && nameInput(r); }, 4000).then((input) => {
        if (input && !(state.get(id) || {}).src) remember(id, { src: input.value });
      });
    } else if (t === 'save') {
      const row = scanRows().find((r) => r.el.contains(b));
      const input = row && nameInput(row);
      if (!row || !input) return;
      const st = state.get(row.id) || {};
      remember(row.id, { final: input.value, edited: st.filled !== undefined ? lower(input.value) !== lower(st.filled) : true });
      if (!st.src) remember(row.id, { src: row.header.name });
    } else if (t === 'import services' && b.closest(DIALOG) && scanRows().length) {
      markImport(); // import bitince yeni kategori kendiliğinden çevrilip en üste taşınır
      const items = [];
      for (const [id, st] of state) {
        if (st.src && st.final && lower(st.src) !== lower(st.final)) items.push({ id, src: st.src, az: st.final, edited: !!st.edited });
      }
      if (items.length) send({ type: 'api', action: 'learn', payload: { items } });
      state.clear();
      marks.clear();
      if (bar) bar.classList.remove('tgw-only-issues');
    }
  }, true);

  document.addEventListener('keydown', (e) => {
    if (e.altKey && e.shiftKey && (e.key === 'D' || e.key === 'd') && typeof showDiag === 'function') { e.preventDefault(); showDiag(); return; }
    if (e.altKey && e.shiftKey && (e.key === 'T' || e.key === 't') && bar && bar.isConnected) {
      e.preventDefault();
      runAll();
    }
  });


  // =====================================================================
  // KATEGORİ: Services listesinde en sondaki aktif kategoriyi ya da açık "Edit category" penceresinin adını çevir.
  // Logo ve Save kullanıcıda; Save'e basılınca ad bankaya öğretilir.
  // =====================================================================
  const SAVE_WORDS = ['save', 'save changes', 'update', 'kaydet', 'yadda saxla'];
  function categoryDialog() {
    const boxes = [...document.querySelectorAll('[role=dialog], .modal, .modal-dialog, .modal-content')].filter((b) => visible(b) && !ours(b));
    for (const box of boxes) {
      const title = [...box.querySelectorAll('h1, h2, h3, h4, h5, .modal-title')].find((el) => /(edit|add)\s+category|kateqoriya|kategori/i.test(norm(el.textContent)));
      if (!title) continue;
      const inputs = [...box.querySelectorAll('input[type=text], input:not([type])')].filter((i) => visible(i) && !ours(i));
      if (!inputs.length) continue;
      const ownText = (el) => norm([...el.childNodes].filter((n) => n.nodeType === 3).map((n) => n.nodeValue).join(' '));
      const label = [...box.querySelectorAll('label, div, span, strong, b, p, h5, h6')].find((el) => !ours(el) && /^(category name|name|kateqoriya adı|ad)$/i.test(ownText(el)));
      const input = (label && inputs.find((i) => label.compareDocumentPosition(i) & Node.DOCUMENT_POSITION_FOLLOWING)) || inputs[0];
      const save = [...box.querySelectorAll('button, a, input[type=submit]')].find((b) => visible(b) && !ours(b) && SAVE_WORDS.includes(lower(b.value || btnText(b))));
      return { box: box.closest('[role=dialog], .modal') || box, title, input, save };
    }
    return null;
  }


  // ---- kategori logosu: adına uygun logoyu "Uploaded" sekmesinden seç ----
  // Uploaded'daki resimlerin adı yok (dosya adları rastgele). Bu yüzden logo, mevcut kategorilerinden öğrenilir:
  // adında "TikTok" geçen kategorilerde en çok hangi resim kullanılıyorsa yeni TikTok kategorisine o seçilir.
  // Her platform: [anahtar, kelime kalıbı (boşluklu metinde), bitişik yazım kalıbı (harf dışı her şey atılmış metinde)].
  // Bitişik kalıp "I.n.s.t.a.g.r.a.m", "You Tube", "Tik-Tok", "Face_book" gibi bölünmüş yazımları yakalar;
  // yalnız uzun/ayırt edici adlar orada aranır (kısa "ig", "yt", "x" orada yanlış eşleşirdi).
  const PLATFORMS = [
    ['tiktok', /tik\s*[-_.]?\s*tok|tic\s*tok|тик\s*[-]?\s*ток/, /tiktok|tictok|ticktock|тикток/],
    ['instagram', /insta(?!nt|ll)|\big\b|инста/, /instagram|instaqram|instagramm|инстаграм/],
    ['youtube', /you\s*tube|\byt\b|ютуб|ютьюб|\byutub|\byoutub/, /youtube|yutube|yutub|ютуб|ютьюб/],
    ['facebook', /face\s*book|\bfb\b|фейсбук|фэйсбук|feysbuk|fesbuk/, /facebook|facebok|feysbuk|fesbuk|фейсбук|фэйсбук/],
    ['telegram', /telegram|teleqram|\btg\b|телеграм|телега/, /telegram|teleqram|телеграм/],
    ['twitter', /twitter|tvitter|twiter|твиттер|твитер|\bx\.com\b|\btweet|ретвит|^\W*x\s+(?=[a-zа-яəçğıöşü])|\(\s*x\s*\)|\bx\s*\/\s*twitter/, /twitter|tvitter|твиттер/],
    ['threads', /\bthreads\b|тредс/, null],
    ['whatsapp', /whats\s*app|w?vatsap|watsap|ватсап|вотсап|вацап/, /whatsapp|vatsap|watsap|ватсап|вотсап/],
    ['spotify', /spotify|спотифай/, /spotify|спотифай/],
    ['twitch', /twitch|твич/, /twitch|твич/],
    ['discord', /discord|дискорд/, /discord|дискорд/],
    ['snapchat', /snap\s*chat|снэпчат|снапчат|\bsnap\b/, /snapchat|снэпчат|снапчат/],
    ['linkedin', /linked\s*in|линкедин/, /linkedin|линкедин/],
    ['pinterest', /pinterest|пинтерест/, /pinterest|пинтерест/],
    ['reddit', /reddit|реддит|редит/, /reddit|реддит/],
    ['kick', /\bkick\b|(?<![а-яё])кик(?![а-яё])/, null],
    ['kwai', /\bkwai\b|(?<![а-яё])квай(?![а-яё])/, null],
    ['vk', /\bvk\b|vkontakte|(?<![а-яё])вк(?![а-яё])|вконтакте/, /vkontakte|вконтакте/],
    ['odnoklassniki', /odnoklassniki|\bok\.ru\b|одноклассники/, /odnoklassniki|одноклассники/],
    ['soundcloud', /sound\s*cloud|саундклауд/, /soundcloud|саундклауд/],
    ['netflix', /netflix|нетфликс/, /netflix|нетфликс/],
    ['canva', /\bcanva\b/, null],
    ['capcut', /cap\s*cut|капкут/, /capcut|капкут/],
    ['onlyfans', /only\s*fans|онлифанс/, /onlyfans|онлифанс/],
    ['pubg', /\bpubg\b|пабг|пубг/, null],
    ['likee', /\blikee\b/, null],
    ['google', /google|гугл/, /google/],
    ['trovo', /trovo/, null], ['rumble', /rumble/, null], ['dailymotion', /daily\s*motion/, /dailymotion/], ['vimeo', /vimeo/, null],
    ['steam', /\bsteam\b|(?<![а-яё])стим(?![а-яё])/, null], ['apple', /apple\s*music|itunes/, /applemusic/], ['deezer', /deezer/, null],
    ['shazam', /shazam|шазам/, /shazam|шазам/], ['tumblr', /tumblr/, /tumblr/], ['quora', /quora/, null],
    ['bigo', /\bbigo\b/, /bigolive/], ['clubhouse', /club\s*house/, /clubhouse/], ['wechat', /we\s*chat/, /wechat/], ['viber', /\bviber\b|вайбер/, null],
    ['signal', /\bsignal\b/, null], ['imo', /\bimo\b/, null], ['zalo', /\bzalo\b/, null], ['weibo', /weibo/, null], ['bilibili', /bilibili/, /bilibili/],
    ['douyin', /douyin/, null], ['medium', /\bmedium\.com\b|\bmedium\s+(claps|followers)/, null], ['github', /git\s*hub/, /github/], ['behance', /behance/, null],
    ['dribbble', /dribb+le/, null], ['patreon', /patreon/, null], ['mixcloud', /mix\s*cloud/, /mixcloud/], ['audiomack', /audio\s*mack/, /audiomack/],
    ['boomplay', /boom\s*play/, /boomplay/], ['tidal', /\btidal\b/, null], ['anghami', /anghami/, null], ['yandex', /yandex|яндекс|\bdzen\b|дзен/, /yandex|яндекс/],
    ['rutube', /rutube|рутуб/, /rutube|рутуб/], ['coub', /\bcoub\b/, null], ['tripadvisor', /trip\s*advisor/, /tripadvisor/], ['trustpilot', /trust\s*pilot/, /trustpilot/],
    ['opensea', /open\s*sea/, /opensea/], ['coinmarketcap', /coin\s*market\s*cap|\bcmc\b/, /coinmarketcap/], ['binance', /binance/, null], ['amazon', /amazon/, null],
    ['line', /\bline\s+(friends|followers|voom)/, null], ['bluesky', /blue\s*sky|\bbsky\b/, /bluesky/], ['mastodon', /mastodon/, null], ['lemon8', /lemon\s*8/, null],
    ['xiaohongshu', /xiaohongshu|red\s*note/, /xiaohongshu|rednote/], ['podcast', /podcast|подкаст/, null], ['iptv', /\biptv\b/, null],
    ['website', /web\s*site\s+traffic|\btraffic\b|\btrafik\b|трафик/, /websitetraffic/],
  ];
  /** Görünen ad (logo tablosu ve durum yazıları). */
  const PLATFORM_LABEL = { tiktok: 'TikTok', youtube: 'YouTube', linkedin: 'LinkedIn', whatsapp: 'WhatsApp', soundcloud: 'SoundCloud', onlyfans: 'OnlyFans', capcut: 'CapCut',
    pubg: 'PUBG', vk: 'VK', dailymotion: 'Dailymotion', twitter: 'Twitter / X', odnoklassniki: 'Odnoklassniki (OK)', apple: 'Apple Music', wechat: 'WeChat', github: 'GitHub',
    tripadvisor: 'TripAdvisor', opensea: 'OpenSea', coinmarketcap: 'CoinMarketCap', iptv: 'IPTV', website: 'Website Traffic', bigo: 'Bigo Live', xiaohongshu: 'Xiaohongshu (RedNote)',
    imo: 'imo', lemon8: 'Lemon8', mixcloud: 'Mixcloud', bluesky: 'Bluesky' };
  const platformLabel = (k) => PLATFORM_LABEL[k] || (k.charAt(0).toUpperCase() + k.slice(1));
  // Latin harfe benzeyen harfler (Kiril/Yunan "а е о р с у х і…", küçük büyük harf "ɪɴsᴛᴀɢʀᴀᴍ") → Latin.
  // Yalnız ikinci okumada kullanılır; ilk okuma Rusça yazımlar ("Инстаграм") için harfleri olduğu gibi bırakır.
  const LOOKALIKE = {
    'а': 'a', 'в': 'b', 'е': 'e', 'ё': 'e', 'к': 'k', 'м': 'm', 'н': 'h', 'о': 'o', 'р': 'p', 'с': 'c', 'т': 't', 'у': 'y', 'х': 'x',
    'і': 'i', 'ї': 'i', 'ј': 'j', 'ѕ': 's', 'һ': 'h', 'ԁ': 'd', 'ԛ': 'q', 'ԝ': 'w', 'ɡ': 'g', 'ɑ': 'a', 'ı': 'i',
    'α': 'a', 'β': 'b', 'ε': 'e', 'η': 'n', 'ι': 'i', 'κ': 'k', 'ν': 'v', 'ο': 'o', 'ρ': 'p', 'τ': 't', 'υ': 'u', 'χ': 'x', 'γ': 'y',
    'ᴀ': 'a', 'ʙ': 'b', 'ᴄ': 'c', 'ᴅ': 'd', 'ᴇ': 'e', 'ꜰ': 'f', 'ɢ': 'g', 'ʜ': 'h', 'ɪ': 'i', 'ᴊ': 'j', 'ᴋ': 'k', 'ʟ': 'l', 'ᴍ': 'm',
    'ɴ': 'n', 'ᴏ': 'o', 'ᴘ': 'p', 'ǫ': 'q', 'ʀ': 'r', 'ꜱ': 's', 'ᴛ': 't', 'ᴜ': 'u', 'ᴠ': 'v', 'ᴡ': 'w', 'ʏ': 'y', 'ᴢ': 'z',
    '0': 'o', '@': 'a', '$': 's',
  };
  /**
   * Adı platform aramasına hazırla. Dönüş: [olduğu gibi, benzer harfler Latin'e çevrilmiş] — ikisi aynı uzunlukta (konumlar aynı).
   * NFKD + işaretleri at: "𝐈𝐧𝐬𝐭𝐚𝐠𝐫𝐚𝐦", "Ｉｎｓｔａ", "Ⓘⓝⓢⓣⓐ", "İnstagram" (büyük noktalı İ) → "instagram".
   * Görünmez harfler (sıfır genişlikli boşluk vb.) atılır.
   */
  function platformText(name) {
    // "й" NFKD'de "и" + işaret olur; işaret atılınca "Фейсбук" bozulurdu → önce saklanır
    const t = String(name || '').replace(/[йЙ]/g, '\ue000').normalize('NFKD').replace(/[\u0300-\u036f\u200b-\u200f\u2060\u00ad\ufeff\ufe0f]/g, '').replace(/\ue000/g, 'й').toLowerCase();
    const map = (c) => {
      if (c === 'ı') return 'i';
      if (/[a-z]/.test(c)) return c;
      return LOOKALIKE[c] || c;
    };
    const plain = [...t].map((c) => (c === 'ı' ? 'i' : c)).join('');
    // "0", "@", "$" yalnız harflerin arasında harf sayılır ("Inst@gram", "Y0uTube"); "1000" gibi sayılara dokunulmaz
    const look = [...t].map((c, i, a) => {
      if (/[0@$]/.test(c)) return /\p{L}/u.test(a[i - 1] || '') && /\p{L}/u.test(a[i + 1] || '') ? LOOKALIKE[c] : c;
      return map(c);
    }).join('');
    return [plain, look];
  }
  /** Addaki ilk platform (yazım biçimi önemsiz: süslü/Kiril benzeri harfler, araya nokta/boşluk, Azerice/Rusça yazılış). */
  function platformOf(name) {
    const [plain, look] = platformText(name);
    let best = null;
    const hit = (k, i) => { if (!best || i < best.i) best = { k, i }; };
    // bitişik metin: yalnız harfler (hangi alfabeden olursa), her harfin asıl konumu tutulur
    const idx = []; let compact = '';
    for (let i = 0; i < look.length; i++) if (/\p{L}/u.test(look[i])) { compact += look[i]; idx.push(i); }
    for (const [k, re, glued] of PLATFORMS) {
      for (const t of [plain, look]) { const m = re.exec(t); if (m) hit(k, m.index); }
      if (glued) { const m = glued.exec(compact); if (m) hit(k, idx[m.index]); }
    }
    return best ? best.k : null;
  }
  const urlOf = (el) => {
    if (!el) return '';
    const img = el.tagName === 'IMG' ? el : el.querySelector('img');
    if (img) {
      const lazy = img.getAttribute('data-src') || img.getAttribute('data-original') || img.getAttribute('data-lazy');
      const src = img.getAttribute('src') || '';
      if (lazy && (!src || /^data:|placeholder|blank|spacer|loading/i.test(src))) return new URL(lazy, location.href).href;
      if (src && !/^data:image\/(gif|svg)/i.test(src)) return img.src;
      if (img.srcset) return new URL(img.srcset.split(',')[0].trim().split(/\s+/)[0], location.href).href;
    }
    const bg = getComputedStyle(el).backgroundImage || '';
    const m = bg.match(/url\(["']?([^"')]+)["']?\)/);
    return m ? m[1] : '';
  };
  /** Karşılaştırma anahtarı: adresin dosya adı (sorgu/alan farkı önemsiz). */
  const fileKey = (u) => { try { return new URL(u, location.href).pathname.split('/').pop().toLowerCase(); } catch (e) { return ''; } };
  /** Aynı resim mi: dosya adı aynı; ya da uzantısız ad (≥8 harf) diğerinin içinde (önizleme "_thumb" vb. ekleri). */
  const sameImg = (a, b) => {
    const x = fileKey(a); const y = fileKey(b);
    if (!x || !y) return false;
    if (x === y) return true;
    const bx = x.replace(/\.[a-z0-9]+$/, ''); const by = y.replace(/\.[a-z0-9]+$/, '');
    return (bx.length >= 8 && y.includes(bx)) || (by.length >= 8 && x.includes(by));
  };
  function rowLogo(row) {
    const el = row.el.querySelector('[class*="category-image"], [class*="category-icon"], img');
    return el ? urlOf(el) : '';
  }
  const stGet = (d) => new Promise((res) => { try { chrome.storage.local.get(d, (r) => res(r || d)); } catch (e) { res(d); } });
  const stSet = (o) => { try { chrome.storage.local.set(o); } catch (e) { /* yok */ } };
  let walkSeen = null; // "Logoları şimdi öğren" sürerken: bu gezintide görülenler (bitince eski/silinmiş kategoriler atılır)
  const STAT_V = 2; // özet biçimi/platform listesi değişince artır: logo tablosu yeniden hesaplanır
  /**
   * Ekrandaki kategori satırlarından öğren (chrome.storage.local):
   *  - logoSeen: platform adlı kategori → logo (en çok 3000),
   *  - logoAll: görülen bütün resimler (logo tablosundaki seçim ızgarası için; up=1: Uploaded sekmesinde görüldü),
   *  - logoStat: platform → [{url, n}] özeti (logo tablosu sayfası bunu okur).
   */
  async function learnLogos() {
    const all = categoryRows().map((r) => ({ name: r.name, url: rowLogo(r) })).filter((x) => x.url);
    const { logoSeen, logoAll, logoStatV } = await stGet({ logoSeen: {}, logoAll: {}, logoStatV: 0 });
    let n = 0; let m = 0;
    for (const x of all) {
      const k = fileKey(x.url);
      if (k && !logoAll[k]) { logoAll[k] = { u: x.url, up: 0 }; m++; }
      if (!platformOf(x.name)) continue;
      if (walkSeen) walkSeen[x.name] = x.url;
      if (logoSeen[x.name] !== x.url) { logoSeen[x.name] = x.url; n++; }
    }
    if (n) {
      const keys = Object.keys(logoSeen);
      for (const k of keys.slice(0, Math.max(0, keys.length - 3000))) delete logoSeen[k];
    }
    if (n || m || logoStatV !== STAT_V) stSet(Object.assign({ logoSeen, logoStatV: STAT_V, logoStat: logoStatOf(logoSeen), logoPlatforms: PLATFORMS.map(([k]) => [k, platformLabel(k)]) }, m ? { logoAll } : {}));
    return n;
  }
  /** platform → kullanılan logolar, çoktan aza. */
  function logoStatOf(logoSeen) {
    const by = {};
    for (const [name, url] of Object.entries(logoSeen)) {
      const p = platformOf(name);
      if (!p) continue;
      const k = fileKey(url);
      const e = (by[p] = by[p] || {})[k] || (by[p][k] = { url, n: 0 });
      e.n++;
    }
    const out = {};
    for (const [p, m] of Object.entries(by)) out[p] = Object.values(m).sort((a, b) => b.n - a.n);
    return out;
  }
  /** Seçicide (Uploaded) görünen resimleri "seçilebilir" olarak kaydet. */
  async function collectPicker() {
    const p = picker();
    if (!p || !p.box) return 0;
    const urls = pickerImages(p.box).map(urlOf).filter(Boolean);
    if (!urls.length) return 0;
    const { logoAll } = await stGet({ logoAll: {} });
    let n = 0;
    for (const u of urls) { const k = fileKey(u); if (k && (!logoAll[k] || !logoAll[k].up)) { logoAll[k] = { u, up: 1 }; n++; } }
    if (n) stSet({ logoAll });
    return n;
  }
  /** Addaki özel kural (logo tablosunda "adında şu kelime geçerse → bu logo"): en önde geçen kazanır. */
  function ruleOf(name, rules) {
    const [plain, look] = platformText(name);
    let best = null;
    for (const r of rules || []) {
      if (!r || !r.word || !r.url) continue;
      const re = wordRe(platformText(r.word)[0]);
      for (const t of [plain, look]) { const m = re.exec(t); if (m && (!best || m.index < best.i)) best = { r, i: m.index }; }
    }
    return best ? best.r : null;
  }
  /**
   * Bu ad için hangi logo: { key, label, cands, fixed }.
   * Sıra: 1) özel kural, 2) tabloda sabitlenen platform logosu, 3) listede o platformda en çok kullanılanlar.
   */
  async function logoTarget(name, alt) {
    const { logoSeen, logoPick, logoRules } = await stGet({ logoSeen: {}, logoPick: {}, logoRules: [] });
    const rule = ruleOf(name, logoRules) || (alt ? ruleOf(alt, logoRules) : null);
    if (rule) return { key: 'kural:' + rule.word, label: rule.word, cands: [rule.url], fixed: true };
    const plat = platformOf(name) || (alt ? platformOf(alt) : null);
    if (!plat) return null;
    const cands = [];
    if (logoPick[plat]) cands.push(logoPick[plat]);
    for (const e of logoStatOf(logoSeen)[plat] || []) if (!cands.some((u) => sameImg(u, e.url))) cands.push(e.url);
    return { key: plat, label: platformLabel(plat), cands, fixed: !!logoPick[plat] };
  }
  /** "Add icon" (logo yokken seçiciyi açan bağlantı) ya da "Change/Edit icon". */
  function iconLink(dlg) {
    const all = [...dlg.box.querySelectorAll('a, button, span, div, label, p')].filter((e) => visible(e) && !ours(e) && e.children.length <= 3
      && /^(\+\s*)?(add|change|edit|select|choose|upload)\s+(an?\s+)?(icon|image|logo)$/i.test(norm(e.textContent)));
    const deep = all.filter((e) => !all.some((x) => x !== e && e.contains(x)));
    const el = deep[0];
    return el ? { el: el.closest('a, button, [role=button]') || el, add: /^(\+\s*)?add/i.test(norm(el.textContent)) } : null;
  }
  /** Pencerede hâlâ "Add icon" var mı (logo seçilmemiş). */
  const iconLinkAdd = (dlg) => { const l = iconLink(dlg); return !!(l && l.add); };
  /** Gerçek fare tıklaması gibi: üstüne gel, bas, bırak, tıkla (React/kütüphaneler mousedown'da da seçebilir). */
  function realClick(el) {
    const r = el.getBoundingClientRect();
    const x = r.left + r.width / 2; const y = r.top + r.height / 2;
    const base = { bubbles: true, cancelable: true, composed: true, view: window, clientX: x, clientY: y, button: 0 };
    const P = window.PointerEvent;
    const ev = (type, extra) => el.dispatchEvent(type.startsWith('pointer') && P ? new P(type, Object.assign({ pointerId: 1, pointerType: 'mouse', isPrimary: true }, base, extra)) : new MouseEvent(type, Object.assign({}, base, extra)));
    ev('pointerover'); ev('mouseover'); ev('pointerenter'); ev('mouseenter');
    ev('pointerdown', { buttons: 1 }); ev('mousedown', { buttons: 1 });
    ev('pointerup', { buttons: 0 }); ev('mouseup', { buttons: 0 });
    ev('click', { buttons: 0 });
  }
  /** Penceredeki logo kutusu: ad kutusunun solundaki/yakınındaki resimli ya da "icon/image/logo" sınıflı kare öğe. */
  function logoBox(dlg) {
    const ir = dlg.input.getBoundingClientRect();
    const pk = picker();
    const cands = [...dlg.box.querySelectorAll('div, span, a, button, img, label')].filter((e) => {
      if (!visible(e) || ours(e) || e.contains(dlg.input) || e.closest('.modal-footer, .modal-header') || (pk && pk.box && pk.box.contains(e))) return false;
      const r = e.getBoundingClientRect();
      if (r.width < 24 || r.height < 24 || r.width > 160 || r.height > 160 || Math.abs(r.width - r.height) > 40) return false;
      return !!urlOf(e) || /icon|image|logo|avatar|picture|photo/i.test(typeof e.className === 'string' ? e.className : '');
    });
    if (!cands.length) {
      // sınıf adı tutmadıysa: ad kutusunun SOLUNDA, onunla aynı yükseklikte duran kare öğe (gerçek panelde logo kutusu orada)
      for (const e of dlg.box.querySelectorAll('div, span, a, button, img, label')) {
        if (!visible(e) || ours(e) || e.contains(dlg.input) || e.closest('.modal-footer, .modal-header') || (pk && pk.box && pk.box.contains(e))) continue;
        const r = e.getBoundingClientRect();
        if (r.width < 30 || r.height < 30 || r.width > 140 || r.height > 140 || Math.abs(r.width - r.height) > 30) continue;
        if (r.right > ir.left + 4 || r.bottom < ir.top - 60 || r.top > ir.bottom + 20) continue;
        cands.push(e);
      }
    }
    if (!cands.length) return null;
    const d = (e) => { const r = e.getBoundingClientRect(); return Math.hypot((r.left + r.right) / 2 - ir.left, (r.top + r.bottom) / 2 - (ir.top + ir.bottom) / 2); };
    cands.sort((a, b) => d(a) - d(b));
    // en yakın adayın en dış tıklanabilir hâli (içindeki img yerine kutunun kendisi)
    let el = cands[0];
    while (el.parentElement && el.parentElement !== dlg.box && cands.includes(el.parentElement)) el = el.parentElement;
    return el;
  }
  /** Seçicinin "Uploaded" sekmesi (ilk açılışta Icons sekmesi seçili olur; orada resim / "Upload file" olmayabilir). */
  function uploadedTab() {
    const all = [...document.querySelectorAll('a, button, li, span, div, [role=tab]')].filter((e) => visible(e) && !ours(e) && e.children.length <= 3 && /^uploaded$/i.test(norm(e.textContent)));
    const deep = all.filter((e) => !all.some((x) => x !== e && e.contains(x)));
    const t = deep[0];
    return t ? (t.closest('a, button, li, [role=tab]') || t) : null;
  }
  /** Açık seçici: "Uploaded" sekmesinin, içinde resim ya da "Upload file" olan en yakın atası. */
  function picker() {
    const tab = uploadedTab();
    if (!tab) return null;
    for (let box = tab.parentElement; box && box !== document.body; box = box.parentElement) {
      const upl = [...box.querySelectorAll('button, a, label')].some((x) => /^upload\s*file$/i.test(norm(x.textContent)));
      if (upl || pickerImages(box).length >= 2) return { box, tab };
    }
    return { box: null, tab };
  }
  const pickerImages = (box) => [...box.querySelectorAll('img, [style*="background-image"]')].filter((e) => visible(e) && !ours(e) && urlOf(e));
  /**
   * Kategori adına göre logoyu seç (pencere açıkken, Save'den önce). Dönüş: not (ör. "logo: TikTok") ya da ''.
   * Mevcut logo varsa yalnızca "Mevcut logoyu değiştir" açıkken değişir.
   */
  async function pickCategoryLogo(name, o) {
    logoDiag = { ad: name, zaman: new Date().toTimeString().slice(0, 8) };
    const note = await pickLogoInner(name, o);
    logoDiag.not = note;
    return note;
  }
  async function pickLogoInner(name, o) {
    if (o.catLogo === false) return '';
    await learnLogos(); // pencerenin arkasındaki liste satırları
    const target = await logoTarget(name, catSrc);
    const plat = target && target.key;
    logoDiag.platform = plat;
    if (!target) return 'logo: adda tanınan bir platform yok (Logo tablosu → özel kural ekleyebilirsin)';
    const cands = target.cands;
    const want = cands[0] || '';
    logoDiag.istenen = want;
    logoDiag.sabit = target.fixed;
    logoDiag.adaylar = cands.map(fileKey);
    // seçicideki resimlerden, aday sırası en önde olan (öğrenilen ilk logo Uploaded'da yoksa sonraki denenir)
    const rank = (e) => { const u = urlOf(e); const i = cands.findIndex((c) => sameImg(u, c)); return i < 0 ? 1e9 : i; };
    const bestIn = (box) => { let b = null; for (const e of pickerImages(box)) { const r = rank(e); if (r < 1e9 && (!b || r < b.r)) b = { e, r }; } return b; };
    const P = target.label;
    if (!want) return 'logo: ' + P + ' için logo belli değil — mini panel → Logo tablosu\'ndan seç';
    let dlg = categoryDialog();
    if (!dlg) return 'kategori penceresi kayboldu';
    logoDiag.pencere = shortHtml(dlg.input.closest('.modal-body') || dlg.box, 1500);
    // Logo yoksa panel "Add icon" gösterir (seçici yalnız ona tıklayınca açılır); logo varsa resmin kendisine tıklanır.
    const link = iconLink(dlg);
    const box = logoBox(dlg);
    logoDiag.addIcon = link ? shortHtml(link.el, 200) : null;
    logoDiag.kutu = box ? shortHtml(box, 400) : null;
    const cur = link && link.add ? '' : (box ? urlOf(box) || urlOf(box.querySelector('*')) : '');
    logoDiag.mevcut = cur;
    if (cur && cands.some((c) => sameImg(cur, c))) return 'logo zaten ' + P;
    if (cur && !o.catLogoReplace) return 'logo zaten var, değiştirilmedi';
    const trigger = (link && link.el) || (cur && box) || box;
    if (!trigger) { catDiagNote = 'logo kutusu bulunamadı'; return 'logo kutusu ("Add icon") bulunamadı'; }
    trigger.click();
    const pk = await waitFor(uploadedTab, 4000, 80) && picker();
    if (!pk) {
      catDiagNote = 'logo seçici açılmadı';
      logoDiag.uploadedYazisi = [...document.querySelectorAll('a, button, li, span, div')].filter((e) => visible(e) && /uploaded/i.test(norm(e.textContent)) && norm(e.textContent).length < 40).slice(0, 4).map((e) => shortHtml(e, 200));
      return 'logo seçici açılmadı';
    }
    pk.tab.click();
    logoDiag.uploadedTiklandi = true;
    await waitFor(() => { const p2 = picker(); return p2 && p2.box && pickerImages(p2.box).length; }, 3000, 100);
    await collectPicker(); // Uploaded'daki resimler logo tablosunda seçilebilir olsun
    // ilk aday görünürse hemen; değilse 3 sn içinde görünen en iyi aday
    let firstHit = null;
    await waitFor(() => { const p2 = picker(); const b = p2 && p2.box && bestIn(p2.box); if (b) firstHit = b; return b && b.r === 0; }, 3000, 100);
    let img = firstHit && firstHit.e;
    if (!img || firstHit.r > 0) { const s2 = await scrollPickerFor((e) => rank(e) === 0); if (s2) img = s2; } // ilk aday aşağıda olabilir
    if (!img) img = await scrollPickerFor((e) => rank(e) < 1e9); // gecikmeli yüklenen resimler
    if (!img) { img = await visualMatch(want); if (img) logoDiag.gorselEslesme = true; } // adres farklıysa: resim içeriğine göre
    const p4 = picker();
    logoDiag.secici = p4 && p4.box ? shortHtml(p4.box, 500) : 'kutu bulunamadı';
    if (!img) {
      const p3 = picker();
      logoDiag.uploadedResimler = p3 && p3.box ? pickerImages(p3.box).slice(0, 12).map((e) => fileKey(urlOf(e))) : 'seçici kayboldu';
      closePicker();
      return 'logo: ' + P + ' logosu Uploaded\'da bulunamadı — mini panel → Logo tablosu\'ndan Uploaded\'daki bir resmi seç';
    }
    const chosen = urlOf(img); // görsel eşleşmede listedeki adresten farklı olabilir
    logoDiag.secilenResim = fileKey(chosen);
    // Panelin resim seçicisi seçimi gerçek fare basışıyla (mousedown) alabilir: tam tıklama dizisi gönder.
    // Kutuda görünmezse aynı dizi resmin bir-iki üst kabına da denenir (tıklama dinleyicisi orada olabilir).
    const shown = () => { const d2 = categoryDialog(); if (!d2 || iconLinkAdd(d2)) return false; const b2 = logoBox(d2); const u = b2 && (urlOf(b2) || urlOf(b2.querySelector('*'))); return !!u && (sameImg(u, chosen) || cands.some((c) => sameImg(u, c))); };
    let okSel = false;
    const targets = [img, img.parentElement, img.parentElement && img.parentElement.parentElement].filter((x, i, a) => x && a.indexOf(x) === i && !x.matches('.ReactVirtualized__Grid__innerScrollContainer, [role=rowgroup], [role=grid]'));
    for (const tgt of targets) {
      if (!tgt.isConnected) break;
      realClick(tgt);
      logoDiag.tiklanan = (logoDiag.tiklanan || []).concat(tgt.tagName + '.' + String(tgt.className || '').slice(0, 40));
      okSel = !!(await waitFor(shown, 2000, 80));
      if (okSel || !picker()) break; // seçildi ya da seçici kapandı (seçim olmuş olabilir)
    }
    if (!okSel) okSel = !!(await waitFor(shown, 1500, 80));
    if (picker()) closePicker();
    await waitFor(() => !picker(), 1500, 80);
    logoDiag.secildi = okSel;
    return okSel ? 'logo: ' + P + (target.fixed ? '' : ' (otomatik)') : 'logo: ' + P + ' resmine tıklandı ama kutuda görünmedi';
  }
  /** Seçicinin kaydırılabilir alanını aşağı kaydırarak ara (resimler görününce yüklenir). */
  async function scrollPickerFor(test) {
    const p = picker();
    if (!p || !p.box) return null;
    const sc = [p.box, ...p.box.querySelectorAll('*')].find((e) => e.scrollHeight > e.clientHeight + 20 && /(auto|scroll)/.test(getComputedStyle(e).overflowY));
    if (!sc) return null;
    for (let y = 0; y <= sc.scrollHeight; y += Math.max(80, sc.clientHeight - 40)) {
      sc.scrollTop = y; nudge(sc);
      await sleep(250);
      await collectPicker();
      const p2 = picker();
      const hit = p2 && p2.box && pickerImages(p2.box).find(test);
      if (hit) { hit.scrollIntoView({ block: 'nearest' }); return hit; }
    }
    sc.scrollTop = 0;
    return null;
  }
  /** Küçük gri parmak izi (16×16): resim sunucusu CORS izni vermezse null (o zaman görsel eşleşme yapılmaz). */
  function imgHash(url) {
    return new Promise((resolve) => {
      const im = new Image();
      im.crossOrigin = 'anonymous';
      const t = setTimeout(() => resolve(null), 4000);
      im.onload = () => {
        clearTimeout(t);
        try {
          const c = document.createElement('canvas'); c.width = 16; c.height = 16;
          const x = c.getContext('2d'); x.drawImage(im, 0, 0, 16, 16);
          const d = x.getImageData(0, 0, 16, 16).data;
          const v = []; for (let i = 0; i < d.length; i += 4) v.push(d[i + 3] < 30 ? 255 : (d[i] * 0.3 + d[i + 1] * 0.59 + d[i + 2] * 0.11));
          resolve(v);
        } catch (e) { resolve(null); }
      };
      im.onerror = () => { clearTimeout(t); resolve(null); };
      im.src = url;
    });
  }
  /** Adres tutmazsa: istenen logoya en çok benzeyen Uploaded resmi (ortalama piksel farkı küçükse). */
  async function visualMatch(want) {
    const h0 = await imgHash(want);
    if (!h0) { logoDiag.gorsel = 'resim sunucusu karşılaştırmaya izin vermedi (CORS)'; return null; }
    const p = picker();
    if (!p || !p.box) return null;
    let best = null;
    for (const e of pickerImages(p.box).slice(0, 200)) {
      const h = await imgHash(urlOf(e));
      if (!h) continue;
      let diff = 0; for (let i = 0; i < h.length; i++) diff += Math.abs(h[i] - h0[i]);
      diff /= h.length;
      if (!best || diff < best.diff) best = { e, diff };
    }
    logoDiag.gorsel = best ? 'en yakın fark ' + best.diff.toFixed(1) : 'karşılaştırılacak resim yok';
    return best && best.diff < 12 ? best.e : null;
  }

  // Kullanıcı seçicide bir resme tıklayıp Save'e basarsa: "bu platform = bu resim" öğrenilir — ama yalnız
  //  - o platform tabloda henüz sabitlenmemişse (sabit seçim kendiliğinden değişmez),
  //  - Save anında penceredeki logo gerçekten o resimse ve tıklama aynı pencerede, az önce olduysa.
  // (Eskiden tıklanıp vazgeçilen bir resim, sonra kaydedilen başka kategorinin platformuna yazılabiliyordu.)
  let pickedByUser = null;
  // Panel seçimi fareye basınca (mousedown) yapıp seçiciyi kapatabilir; bu yüzden basış anı da dinlenir.
  const onUserPick = (e) => {
    if (!e.isTrusted || catRunning) return;
    const p = picker();
    if (!p || !p.box || !p.box.contains(e.target)) return;
    collectPicker();
    const el = e.target.closest('img, [style*="background-image"]') || e.target.querySelector && e.target.querySelector('img');
    const u = el && urlOf(el);
    const d = categoryDialog();
    if (u) pickedByUser = { u, at: Date.now(), dlg: d ? d.box : null };
  };
  for (const t of ['pointerdown', 'mousedown', 'click']) document.addEventListener(t, onUserPick, true);
  async function rememberLogoPick(dlg) {
    const pick = pickedByUser; pickedByUser = null;
    if (!pick || !dlg || pick.dlg !== dlg.box || Date.now() - pick.at > 10 * 60000) return;
    const b = logoBox(dlg);
    const shown = b && (urlOf(b) || urlOf(b.querySelector('*')));
    if (!shown || !sameImg(shown, pick.u)) return; // kullanıcı sonra başka resim seçti ya da logoyu kaldırdı
    const plat = platformOf(dlg.input ? dlg.input.value : '') || platformOf(catSrc);
    if (!plat) return;
    const { logoPick } = await stGet({ logoPick: {} });
    if (logoPick[plat]) return;
    logoPick[plat] = pick.u;
    stSet({ logoPick });
  }

  /** Kategori penceresini KAYDETMEDEN kapat (Cancel / Close / ×). Save'e asla basılmaz. */
  async function dismissCategoryDialog() {
    const d = categoryDialog();
    if (!d) return true;
    const btns = [...d.box.querySelectorAll('button, a, [role=button]')].filter((b) => visible(b) && !ours(b) && b !== d.save && !SAVE_WORDS.includes(lower(b.value || btnText(b))));
    const b = btns.find((x) => /^(cancel|close|kapat|iptal|bağla|ləğv et)$/i.test(norm(x.value || btnText(x))))
      || btns.find((x) => x.matches('.close, .btn-close, [data-dismiss=modal], [data-bs-dismiss=modal], [aria-label=Close], [aria-label=close]') || /^[×✕x]$/i.test(norm(x.textContent)));
    if (!b) return false;
    b.click();
    return !!(await waitFor(() => !categoryDialog(), 4000, 100));
  }
  /**
   * Uploaded sekmesini kendiliğinden tara: bir kategorinin Edit penceresi açılır → logo / "Add icon" → Uploaded →
   * oradaki bütün resimler (kaydırarak) "seçilebilir" diye kaydedilir → seçici ve pencere Save'e BASILMADAN kapatılır.
   * Pencere zaten açıksa o kullanılır ve açık bırakılır.
   */
  async function scanUploaded() {
    let dlg = categoryDialog();
    let opened = false;
    if (!dlg) {
      const row = categoryRows()[0];
      if (!row) return { ok: false, error: 'Actions düğmesi olan kategori yok' };
      const r = await openCategoryEditor(row);
      row.el.classList.remove('tgw-flash');
      if (r.error) return { ok: false, error: r.error };
      dlg = r.dlg; opened = true;
    }
    const done = async (res) => {
      if (picker()) { closePicker(); await waitFor(() => !picker(), 1500, 80); }
      if (opened) { const closed = await dismissCategoryDialog(); catSrc = null; catFilled = null; if (!closed) res.note = 'Edit category penceresi kapatılamadı: kendin kapat (Save\'e basma)'; }
      return res;
    };
    const link = iconLink(dlg);
    const trigger = (link && link.el) || logoBox(dlg);
    if (!trigger) return done({ ok: false, error: 'logo kutusu ("Add icon") bulunamadı' });
    trigger.click();
    const pk = await waitFor(uploadedTab, 4000, 80) && picker();
    if (!pk) return done({ ok: false, error: 'logo seçici açılmadı' });
    pk.tab.click();
    await waitFor(() => { const p2 = picker(); return p2 && p2.box && pickerImages(p2.box).length; }, 3000, 100);
    await collectPicker();
    await scrollPickerFor(() => false); // sanallaştırılmış ızgara: sona kadar kaydır, her adımda topla
    const { logoAll } = await stGet({ logoAll: {} });
    return done({ ok: true, uploaded: Object.values(logoAll).filter((x) => x && x.up).length });
  }

  /** Seçiciyi kapat: pencere başlığına tıkla (Escape pencerenin kendisini kapatabilir). */
  function closePicker() {
    const d = categoryDialog();
    if (d && d.title) d.title.click();
  }
  let catDiagNote = '';
  let logoDiag = null; // son logo denemesinin ayrıntıları (Teşhisi kopyala)
  const shortHtml = (el, n) => (el ? el.outerHTML.replace(/\s+/g, ' ').replace(/<svg[\s\S]*?<\/svg>/g, '<svg/>').replace(/data:[^"')]{40,}/g, 'data:…').slice(0, n || 600) : '');

  /** Listeyi baştan sona adım adım gez (sanallaştırılmış liste) ve tüm kategorilerin logosunu öğren. */
  async function walkLearnLogos() {
    if (catRunning || running) return { ok: false, error: 'Başka bir iş sürüyor' };
    catRunning = true; busy(true);
    try {
      ensureCatBar();
      catStatus('Logolar öğreniliyor: liste kategorilere daraltılıyor…');
      await collapseAll();
      const first = categoryRows()[0];
      if (!first) return { ok: false, error: 'Kategori satırı yok (Services sayfasında değil misin?)' };
      const box = scroller(first.el);
      const el = box || document.scrollingElement;
      el.scrollTop = 0; nudge(box); nudge();
      await sleep(400);
      walkSeen = {};
      let learned = await learnLogos();
      let stable = 0;
      for (let i = 0; i < 3000 && stable < 2; i++) {
        const before = el.scrollTop;
        el.scrollTop = before + Math.max(150, Math.floor(el.clientHeight * 0.7));
        nudge(box); nudge();
        await sleep(200);
        learned += await learnLogos();
        catStatus('Logolar öğreniliyor… ' + learned + ' yeni');
        const atEnd = el.scrollTop + el.clientHeight >= el.scrollHeight - 4 || el.scrollTop === before;
        if (!atEnd) { stable = 0; continue; }
        const hgt = el.scrollHeight; await sleep(700);
        learned += await learnLogos();
        stable = el.scrollHeight > hgt ? 0 : stable + 1;
      }
      // Liste baştan sona gezildi: kayıt yalnız şimdi var olan kategorilerden oluşsun (adı değişen/silinenler yanlış oy vermesin).
      let { logoSeen } = await stGet({ logoSeen: {} });
      if (walkSeen && Object.keys(walkSeen).length) {
        logoSeen = walkSeen;
        stSet({ logoSeen, logoStatV: STAT_V, logoStat: logoStatOf(logoSeen), logoPlatforms: PLATFORMS.map(([k]) => [k, platformLabel(k)]) });
      }
      const stat = logoStatOf(logoSeen);
      const plats = Object.keys(stat);
      const unsure = plats.filter((k) => stat[k].length > 1).map(platformLabel);
      // Uploaded sekmesi de taransın: logo tablosundaki seçim ızgarası ve "Uploaded'da görülmedi" uyarısı buna dayanır.
      catStatus('Uploaded sekmesi taranıyor (bir kategorinin Edit penceresi açılıp kaydedilmeden kapatılır)…');
      el.scrollTop = 0; nudge(box); nudge();
      await sleep(400);
      let up = null;
      try { up = await scanUploaded(); } catch (e) { up = { ok: false, error: String(e && e.message || e) }; }
      const upNote = up.ok ? ' · Uploaded: ' + up.uploaded + ' resim' + (up.note ? ' · ' + up.note : '') : ' · Uploaded taranamadı: ' + up.error;
      catStatus('Logolar öğrenildi: ' + Object.keys(logoSeen).length + ' kategori, ' + plats.length + ' platform' + upNote + (unsure.length ? ' · birden fazla logo kullanılan: ' + unsure.join(', ') + ' (Logo tablosundan sabitle)' : ''), up.ok && !up.note ? 'ok' : 'warn');
      return { ok: true, categories: Object.keys(logoSeen).length, platforms: plats, unsure, uploaded: up.ok ? up.uploaded : null, uploadedError: up.ok ? (up.note || '') : up.error };
    } finally {
      walkSeen = null;
      catRunning = false; busy(false);
    }
  }

  let cbar = null;
  let catRunning = false;
  let catSrc = null;   // pencere açıldığındaki orijinal kategori adı
  let catFilled = null; // eklentinin yazdığı ad (elle değiştirildi mi anlamak için)

  function catStatus(t, kind) {
    if (!cbar) return;
    const s = cbar.querySelector('.tgw-status');
    s.textContent = t;
    s.title = t;
    if (kind === 'bad' || kind === 'warn') setCatMin(false);
    s.className = 'tgw-status' + (kind ? ' tgw-status-' + kind : '');
  }

  function buildCatBar() {
    cbar = h('div', 'tgw-bar tgw-cbar tgw-floating');
    const brand = h('div', 'tgw-brand');
    brand.appendChild(logo(26));
    const txt = h('div', 'tgw-text');
    txt.appendChild(h('div', 'tgw-title', 'tgwatch kategori'));
    txt.appendChild(h('div', 'tgw-status'));
    brand.appendChild(txt);
    const acts = h('div', 'tgw-actions');
    const go = h('button', 'tgw-btn tgw-cgo', 'Çevir');
    go.type = 'button';
    go.addEventListener('click', () => catFlow());
    const close = h('button', 'tgw-btn tgw-ghost tgw-cclose', '–');
    close.type = 'button';
    close.title = 'Küçült (logoya tıklayınca açılır)';
    close.addEventListener('click', () => setCatMin(true));
    brand.addEventListener('click', () => { if (cbar.classList.contains('tgw-min')) setCatMin(false); });
    const diag = h('button', 'tgw-btn tgw-ghost tgw-diag', 'Teşhisi kopyala');
    diag.type = 'button';
    diag.hidden = true;
    diag.title = 'Kategori satırlarının yapısını panoya kopyalar (kategori adları ve sayfa yapısı; şifre/anahtar yok). Sohbete yapıştır.';
    diag.addEventListener('click', async () => {
      const txt = catDiag();
      try { await navigator.clipboard.writeText(txt); } catch (e) {
        const ta = document.createElement('textarea'); ta.value = txt; document.body.appendChild(ta); ta.select(); document.execCommand('copy'); ta.remove();
      }
      diag.textContent = 'Kopyalandı ✓';
      setTimeout(() => { diag.textContent = 'Teşhisi kopyala'; }, 1500);
    });
    acts.append(go, diag, close);
    const prog = h('div', 'tgw-progress');
    prog.appendChild(h('i'));
    cbar.append(brand, acts, prog);
    try { if (localStorage.getItem('tgw_cat_min')) setCatMin(true); } catch (e) { /* yok */ }
  }

  function setCatMin(on) {
    if (!cbar) return;
    cbar.classList.toggle('tgw-min', on);
    cbar.title = on ? 'tgwatch kategori — aç' : '';
    try { localStorage.setItem('tgw_cat_min', on ? '1' : ''); } catch (e) { /* depolama kapalı */ }
  }

  function showDiag() {
    ensureCatBar();
    const d = cbar.querySelector('.tgw-diag');
    if (d) d.hidden = false;
  }

  function editCategoryLinks() {
    return [...document.querySelectorAll('a, button, li, span, div')].filter((el) => el.children.length === 0 && !ours(el) && /^edit\s+category$/i.test(norm(el.textContent)));
  }

  /**
   * Kategori satırları: "Actions" düğmesi olan ama kutucuğu (checkbox) olmayan satırlar (servis satırlarında kutucuk var).
   * Her Actions düğmesinden, başka Actions içermeyen en geniş ataya çıkılır.
   */
  function categoryRows() {
    const btns = [...document.querySelectorAll('button, a')].filter((b) => !ours(b) && /^actions\b/i.test(btnText(b)));
    const out = [];
    for (const b of btns) {
      let row = b;
      while (row.parentElement && row.parentElement !== document.body) {
        const p = row.parentElement;
        if ([...p.querySelectorAll('button, a')].filter((x) => /^actions\b/i.test(btnText(x))).length > 1) break;
        row = p;
      }
      if (row.querySelector('input[type=checkbox]')) continue; // servis satırı
      // "NEW"/"Yeni" rozeti adın başında ya da sonunda olabilir: addan sayılmaz.
      const name = norm(norm(textWithAlt(row).replace(/\bActions\b\s*[▾▼⌄]?/g, '')).replace(/^(?:NEW|YENİ|Yeni)\s+/, '').replace(/\s+(?:NEW|YENİ|Yeni)$/, ''));
      if (!name) continue;
      out.push({ el: row, btn: b, name });
    }
    return out;
  }

  /** Kategorinin ad öğesi: en uzun yazılı yaprak öğe ("NEW" rozeti, Actions, simgeler değil). */
  function nameElOf(row) {
    let best = null;
    for (const e of row.el.querySelectorAll('*')) {
      if (e.children.length || ours(e)) continue;
      const t = norm(e.textContent);
      if (t.length < 2 || /^(actions|new|yeni)\b/i.test(t)) continue;
      if (!best || t.length > best.t.length) best = { e, t };
    }
    return best ? best.e : null;
  }

  const lum = (color) => {
    const m = (color || '').match(/(\d+(?:\.\d+)?)/g);
    if (!m || m.length < 3) return null;
    const [r, g, b] = m.map(Number);
    const a = m.length > 3 ? Number(m[3]) : 1;
    return { l: (0.2126 * r + 0.7152 * g + 0.0722 * b) / 255, a };
  };

  /**
   * Kapalı kategoriler: soluk satır (opacity), "disabled/inactive" sınıfı, "Disabled" yazısı ya da adı arka plana göre belirgin SOLUK.
   * Soluk = yazı/arka plan kontrastı en canlı addan 0.2+ düşük. Açık ve koyu temada aynı çalışır (koyu temada aktifler parlak).
   * @return {{active: Array, why: Map}}
   */
  function splitActive(rows) {
    const why = new Map();
    const info = rows.map((row) => {
      for (let el = row.el, i = 0; el && i < 4; el = el.parentElement, i++) {
        if (/(^|[\s_-])(disabled|inactive|is-disabled|off)($|[\s_-])/i.test(typeof el.className === 'string' ? el.className : '')) return { row, off: 'sınıf: ' + el.className };
        if (parseFloat(getComputedStyle(el).opacity) < 0.75) return { row, off: 'soluk (opacity)' };
      }
      if (/\bdisabled\b/i.test(row.name)) return { row, off: 'adında Disabled' };
      const ne = nameElOf(row);
      if (ne && parseFloat(getComputedStyle(ne).opacity) < 0.75) return { row, off: 'ad soluk (opacity)' };
      const c = ne ? lum(getComputedStyle(ne).color) : null;
      if (!c) return { row };
      // Yazı ile arka plan arasındaki fark (kontrast): açık temada kapalı = gri yazı, koyu temada kapalı = soluk/koyu yazı.
      let bg = { l: 1, a: 1 };
      for (let el = ne; el; el = el.parentElement) {
        const b = lum(getComputedStyle(el).backgroundColor);
        if (b && b.a > 0.5) { bg = b; break; }
      }
      return { row, k: Math.abs(c.l - bg.l) * Math.min(1, c.a) };
    });
    const ks = info.filter((x) => !x.off && x.k !== undefined).map((x) => x.k);
    const best = ks.length ? Math.max(...ks) : 0;
    for (const x of info) {
      if (!x.off && x.k !== undefined && x.k < best - 0.2) x.off = 'soluk yazı (kontrast ' + x.k.toFixed(2) + ' / ' + best.toFixed(2) + ')';
      if (x.off) why.set(x.row, x.off);
    }
    return { active: info.filter((x) => !x.off).map((x) => x.row), why };
  }

  /** Teşhis: son kategorilerin durumu (panoya kopyalanır, destek için). */
  function catDiag() {
    const rows = categoryRows();
    const { why } = splitActive(rows);
    const tail = rows.slice(-8).map((r) => {
      const ne = nameElOf(r);
      return { ad: r.name.slice(0, 80), kapali: why.get(r) || '', renk: ne ? getComputedStyle(ne).color : '', sinif: (typeof r.el.className === 'string' ? r.el.className : '').slice(0, 80), tutamac: !!rowHandle(r) };
    });
    const last = rows[rows.length - 1];
    const html = last ? last.el.outerHTML.replace(/\s+/g, ' ').replace(/<svg[\s\S]*?<\/svg>/g, '<svg/>').slice(0, 1500) : '';
    const hd = last && rowHandle(last);
    const hcs = hd && getComputedStyle(hd);
    const box = last && last.el.parentElement;
    const lib = {
      jquiSortable: !!document.querySelector('.ui-sortable'), sortableAttr: !!document.querySelector('[class*="sortable"]'),
      draggableAttr: !!document.querySelector('[draggable=true]'), rbd: !!document.querySelector('[data-rbd-drag-handle-draggable-id], [data-rfd-drag-handle-draggable-id]'),
      dndkit: !!document.querySelector('[aria-roledescription="sortable"]'), vue: !!document.querySelector('[data-v-app], [data-v-]') || !!window.__VUE__, react: !!document.querySelector('[data-reactroot]') || !!(document.getElementById('root') || {})._reactRootContainer,
      angular: !!document.querySelector('[ng-app], [ng-version], .ng-scope'),
    };
    return JSON.stringify({ surum: chrome.runtime.getManifest ? chrome.runtime.getManifest().version : '', adres: location.pathname, kategori: rows.length, kutucuk: visibleChecks(), logo: logoDiag, son: tail,
      tutamac: hd ? { boyut: Math.round(hd.getBoundingClientRect().width) + 'x' + Math.round(hd.getBoundingClientRect().height), display: hcs.display, visibility: hcs.visibility, opacity: hcs.opacity, cursor: hcs.cursor } : 'yok',
      liste: box ? (box.tagName + '.' + (typeof box.className === 'string' ? box.className : '')).slice(0, 120) : '', kutuphane: lib, surukleme: dragLog, satirHtml: html }, null, 1);
  }

  /** Görünen servis satırı sayısı (servis satırlarında kutucuk var; başlıktaki "hepsini seç" de bir tane). */
  const visibleChecks = () => [...document.querySelectorAll('input[type=checkbox]')].filter((c) => visible(c) && !ours(c)).length;

  /**
   * Listeyi yalnızca kategori adlarına daralt: tablo başlığındaki (Status, Min, Max satırı) en sağdaki simge düğmesi.
   * Servisler zaten kapalıysa dokunulmaz; basınca servisler açılırsa geri basılır.
   */
  async function collapseAll() {
    if (visibleChecks() <= 1) return true;
    // Perfect Panel: <span class="far service-table__header-hide-all fa-expand-alt"></span>
    const exact = [...document.querySelectorAll('.service-table__header-hide-all, [class*="header-hide-all"]')].find((el) => visible(el));
    if (exact) {
      const before = visibleChecks();
      exact.click();
      const ok = await waitFor(() => visibleChecks() <= 1, 3000, 100);
      if (!ok && visibleChecks() > before) exact.click(); // ters çalıştı: geri al
      if (ok) return true;
    }
    const status = [...document.querySelectorAll('button, a, th, div, span')].find((el) => !ours(el) && el.children.length <= 2 && /^status\b/i.test(btnText(el)));
    let head = status;
    while (head && head !== document.body && !/\bmax\b/i.test(head.textContent || '')) head = head.parentElement;
    if (!head || head === document.body) return false;
    const icons = [...head.querySelectorAll('a, button, [role=button], span, i, div')].filter((el) => {
      if (!visible(el) || ours(el) || /[a-z]/i.test(el.textContent || '')) return false;
      if (el.querySelector('input')) return false;
      const r = el.getBoundingClientRect();
      return r.width > 0 && r.width <= 48 && r.height <= 48;
    });
    if (!icons.length) return false;
    icons.sort((a, b) => b.getBoundingClientRect().right - a.getBoundingClientRect().right);
    const toggle = icons[0].closest('a, button, [role=button]') || icons[0];
    const before = visibleChecks();
    toggle.click();
    const ok = await waitFor(() => visibleChecks() <= 1, 3000, 100);
    if (!ok && visibleChecks() > before) toggle.click(); // ters çalıştı: geri al
    return !!ok;
  }

  /** Listenin gerçek kaydırma alanı (pencere ya da iç kutu). */
  function scroller(from) {
    for (let el = from && from.parentElement; el && el !== document.body; el = el.parentElement) {
      const st = getComputedStyle(el);
      if (/(auto|scroll)/.test(st.overflowY) && el.scrollHeight > el.clientHeight + 40) return el;
    }
    return null;
  }

  /** Sonuna kadar kaydır; yeni satır yüklenmesi durana kadar tekrarla. */
  async function scrollToEnd() {
    learnLogos();
    let stable = 0;
    let lastCount = -1;
    for (let i = 0; i < 40 && stable < 3; i++) {
      const rows = categoryRows();
      const box = scroller(rows.length ? rows[rows.length - 1].el : null);
      // Yalnızca en alta in (bir satıra kaydırmak listeyi yukarı çekip panelin "sonraki sayfayı yükle"sini engeller). Kayma gözle görülsün.
      scrollBoxTo(box, box ? box.scrollHeight : document.documentElement.scrollHeight);
      await waitFor(() => {
        const atEnd = (!box || box.scrollTop + box.clientHeight >= box.scrollHeight - 4)
          && window.scrollY + window.innerHeight >= document.documentElement.scrollHeight - 4;
        return atEnd;
      }, 4000, 100);
      await sleep(500);
      learnLogos();
      const n = categoryRows().length + document.documentElement.scrollHeight / 1e6;
      stable = n === lastCount ? stable + 1 : 0;
      lastCount = n;
    }
  }

  const onServicesList = () => /\/admin\/services\/?$/.test(location.pathname);
  const anyDialog = () => [...document.querySelectorAll(DIALOG)].some((m) => visible(m) && !ours(m));

  // ---- import sonrası otomatik kategori: Import'a basılınca işaret bırakılır (sayfa yenilense de sekmede kalır) ----
  const AFTER_KEY = 'tgw_after_import';
  function pendingImport() {
    try {
      const v = JSON.parse(sessionStorage.getItem(AFTER_KEY) || 'null');
      if (!v) return null;
      if (Date.now() - v.at > 10 * 60 * 1000) { sessionStorage.removeItem(AFTER_KEY); return null; }
      return v;
    } catch (e) { return null; }
  }
  /** Satırdaki fiyat kutusu: sayı içeren ilk metin/sayı kutusu (ad kutusu değil). */
  function rateOf(row) {
    for (const i of row.el.querySelectorAll('input')) {
      if (ours(i) || i.type === 'checkbox' || i.type === 'hidden') continue;
      if (/^\s*\d+(?:[.,]\d+)?\s*$/.test(i.value) && /[.,]/.test(i.value)) return i.value.replace(',', '.');
    }
    return null;
  }
  function markImport() {
    let services = [];
    try { services = scanRows().map((r) => ({ name: r.header.name, rate: rateOf(r) })); } catch (e) { /* yok */ }
    const info = { at: Date.now(), before: categoryRows().map((r) => r.name), counts: counts(), services };
    try { sessionStorage.setItem(AFTER_KEY, JSON.stringify(info)); } catch (e) { /* depolama kapalı */ }
  }
  let autoTimer = null;

  function ensureCat() {
    if (cbar && cbar.dataset.closed && !catRunning) return;
    const importOpen = !!(findModal() && scanRows().length);
    const dlg = !importOpen && categoryDialog();
    if (!dlg && !catRunning) { catSrc = null; catFilled = null; } // pencere kapandı: hatırlanan orijinal ad bir sonraki kategoriye taşınmasın
    const onList = !importOpen && !dlg && !anyDialog() && onServicesList(); // ağır tarama yalnızca düğmeye basınca
    if (onList && !catRunning && !autoTimer && pendingImport()) {
      // Import bitti, liste geldi: biraz bekle (panel listeyi yeniden çizer), sonra kendiliğinden çalış.
      autoTimer = true;
      sleep(2000).then(async () => {
        autoTimer = null;
        const p = pendingImport();
        if (!p || catRunning || anyDialog() || !onServicesList()) return;
        try { sessionStorage.removeItem(AFTER_KEY); } catch (e) { /* yok */ }
        const o = await opts();
        if (o.autoCat === false) return;
        catFlow({ auto: true, before: p.before || [], info: p });
      });
    }
    if (!dlg && !onList && !catRunning) {
      if (cbar && cbar.isConnected) cbar.remove();
      return;
    }
    if (!cbar) buildCatBar();
    // Pencere açıkken çubuk pencerenin içinde (başlığın altında); listede sol altta küçük şerit (sağdaki Actions menülerini kapatmasın).
    const head = dlg && (dlg.title.closest('.modal-header') || dlg.title.parentElement);
    if (head && dlg.box.contains(head)) {
      if (cbar.previousElementSibling !== head) head.insertAdjacentElement('afterend', cbar);
      cbar.classList.remove('tgw-floating');
    } else {
      if (!cbar.classList.contains('tgw-floating') || !cbar.isConnected || cbar.parentElement !== document.body) document.body.appendChild(cbar);
      cbar.classList.add('tgw-floating');
    }
    const go = cbar.querySelector('.tgw-cgo');
    go.disabled = catRunning;
    if (dlg) {
      if (catSrc === null && dlg.input) catSrc = dlg.input.value;
      go.textContent = 'Bu kategoriyi çevir';
      if (!catRunning) catStatus('Kategori: ' + (dlg.input ? dlg.input.value : ''));
    } else {
      go.textContent = 'Son kategoriyi çevir';
      if (!catRunning && !cbar.dataset.done) catStatus('Son aktif kategori: çevir → Save → en üste');
    }
  }

  /** Kategorinin Actions menüsünden "Edit category" penceresini aç. */
  async function openCategoryEditor(row) {
    row.el.scrollIntoView({ block: 'center' });
    nudge(scroller(row.el)); nudge();
    row.el.classList.add('tgw-flash');
    await sleep(300);
    const before = new Set(editCategoryLinks().filter(visible));
    row.btn.click();
    const item = await waitFor(() => {
      const vis = editCategoryLinks().filter(visible);
      return vis.find((e) => row.el.contains(e)) || vis.find((e) => !before.has(e)) || (vis.length === 1 ? vis[0] : null);
    }, 2500) || [...row.el.querySelectorAll('a, button, li, span')].find((e) => /^edit\s+category$/i.test(norm(e.textContent)));
    if (!item) return { error: '"Edit category" menüde bulunamadı' };
    item.click();
    const dlg = await waitFor(categoryDialog, 5000);
    if (!dlg || !dlg.input) return { error: 'Edit category penceresi açılmadı' };
    catSrc = dlg.input.value;
    catFilled = null;
    return { dlg };
  }

  /** Açık pencerede adı çevir; save ise Save'e bas ve pencerenin kapanmasını bekle. */
  async function translateDialog(save) {
    const dlg = categoryDialog();
    if (!dlg || !dlg.input) return { ok: false, note: 'Kategori penceresi bulunamadı' };
    // Güvenlik: penceredeki ad hatırlanan addan farklıysa ve Azerice değilse başka bir kategori açılmıştır → penceredeki ad esas.
    if (catSrc === null || (lower(dlg.input.value) !== lower(catSrc) && !AZ_ONLY.test(dlg.input.value))) catSrc = dlg.input.value;
    const src = catSrc;
    if (AZ_ONLY.test(dlg.input.value)) {
      // Ad zaten Azerice: ada dokunma, ama logo yine seçilsin (ve logo değiştiyse kaydet)
      const nm = dlg.input.value;
      catStatus('Ad zaten Azerice · logo seçiliyor…');
      const lg = await pickCategoryLogo(nm, await opts());
      const changed = !!(logoDiag && logoDiag.secildi); // logo gerçekten seçildi (kutuda göründü)
      if (!changed || !save) return { ok: false, already: true, name: nm, note: nm + ' · ad zaten Azerice' + (lg ? ' · ' + lg : '') + (changed && !save ? ' · deneme: Save\'e basılmadı' : '') };
      await sleep(250);
      const d3 = categoryDialog();
      if (!d3 || !d3.save) return { ok: false, already: true, name: nm, note: 'Save düğmesi bulunamadı' };
      d3.save.click();
      const closed3 = await waitFor(() => !categoryDialog(), 8000, 100);
      return { ok: false, already: true, name: nm, note: nm + ' · ad zaten Azerice · ' + lg + (closed3 ? ' · kaydedildi' : ' · Save sonrası pencere kapanmadı') };
    }
    catStatus('Çevriliyor: ' + src);
    const res = await api('translate', { items: [{ id: 'cat', name: src, kind: 'category' }] });
    const tr = res.ok ? res.items[0] : null;
    if (!tr || !tr.az || tr.via === 'none' || tr.via === 'already') {
      return { ok: false, note: res.ok ? 'ad çevrilemedi: ' + src + ' — elle yaz, Save\'e bas' : (res.error || 'tgwatch hatası') };
    }
    tr.az = fmtName(tr.az, await opts());
    setValue(dlg.input, tr.az);
    catFilled = tr.az;
    let o = dlg.input.parentElement.querySelector(':scope > .tgw-orig');
    if (!o) { o = h('span', 'tgw-orig'); dlg.input.insertAdjacentElement('afterend', o); }
    o.textContent = src;
    o.title = 'Orijinal kategori adı';
    let how = tr.via === 'bank' ? 'bankadan' : tr.via === 'claude' ? 'Claude ile' : 'kurallarla';
    catStatus('Logo seçiliyor…');
    const logo = await pickCategoryLogo(tr.az, await opts());
    if (logo) how += ' · ' + logo;
    if (!save) return { ok: true, saved: false, name: tr.az, src, how, logo };
    await sleep(250);
    const d2 = categoryDialog();
    if (!d2 || !d2.save) return { ok: false, note: 'Save düğmesi bulunamadı' };
    rememberCategory(d2);
    d2.save.click();
    const closed = await waitFor(() => !categoryDialog(), 8000, 100);
    if (!closed) return { ok: false, note: 'Save sonrası pencere kapanmadı — paneli kontrol et' };
    send({ type: 'stat', translated: 1 });
    return { ok: true, saved: true, name: tr.az, src, how, logo };
  }

  // ---- sürükle-bırak: kategoriyi tutamaçından (service-table__handle) tutup listenin en üstüne bırak ----
  const HANDLE = '.service-table__handle, [class*="__handle"], .handle, .drag-handle, [class*="drag-handle"], .fa-grip-vertical, .fa-grip-lines, .fa-arrows-alt, .fa-bars';
  const rowHandle = (row) => row.el.querySelector(HANDLE) || null;
  /** Ad karşılaştırması: büyük/küçük harf, boşluk, emoji ve rozet farkı sayılmaz; tam eşleşme yoksa adı içeren tek satır. */
  const catKey = (s) => lower(s).replace(/[^\p{L}\p{N}]+/gu, ' ').replace(/(^| )(new|yeni)(?= |$)/g, ' ').replace(/\s+/g, ' ').trim();
  function findCat(rows, name) {
    const k = catKey(name);
    const i = rows.findIndex((r) => catKey(r.name) === k);
    if (i >= 0 || !k) return i;
    const hits = rows.map((r, n) => (catKey(r.name).includes(k) ? n : -1)).filter((n) => n >= 0);
    return hits.length === 1 ? hits[0] : -1;
  }
  const indexOf = (name) => findCat(categoryRows(), name);

  function fire(el, type, x, y) {
    const pointer = type.startsWith('pointer');
    const init = { bubbles: true, cancelable: true, composed: true, view: window, clientX: x, clientY: y, screenX: x, screenY: y, button: 0, buttons: /up$/.test(type) ? 0 : 1 };
    const ev = pointer && window.PointerEvent
      ? new PointerEvent(type, Object.assign(init, { pointerId: 1, pointerType: 'mouse', isPrimary: true }))
      : new MouseEvent(type, init);
    el.dispatchEvent(ev);
  }
  const at = (x, y) => document.elementFromPoint(x, y) || document.body;
  const center = (el) => { const r = el.getBoundingClientRect(); return { x: r.left + r.width / 2, y: r.top + r.height / 2 }; };
  let dragLog = []; // teşhis: hangi yol denendi, ne oldu

  /** Görünür alan: listenin kaydırma kutusu ile pencerenin kesişimi. */
  function viewport(box) {
    const r = box ? box.getBoundingClientRect() : { top: 0, bottom: innerHeight };
    return { top: Math.max(0, r.top), bottom: Math.min(innerHeight, r.bottom) };
  }

  /** Taşınacak satır görünür alanın altına gelsin; hedef = ondan yukarıdaki, görünen en üstteki kategori. */
  async function dragPlan(name) {
    const rows = categoryRows();
    const i = findCat(rows, name);
    if (i <= 0) return { i };
    const me = rows[i];
    const box = scroller(me.el);
    let v = viewport(box);
    const dy = me.el.getBoundingClientRect().bottom - (v.bottom - 60);
    if (box) box.scrollTop += dy; else window.scrollBy(0, dy);
    if (box) { const r = me.el.getBoundingClientRect(); if (r.bottom > innerHeight - 40 || r.top < 0) window.scrollBy(0, r.bottom - (innerHeight - 60)); }
    await sleep(250);
    v = viewport(box);
    let j = -1;
    for (let k = 0; k < i; k++) {
      const r = rows[k].el.getBoundingClientRect();
      const p = { x: r.left + Math.min(r.width / 2, 200), y: r.top + Math.min(6, r.height / 4) };
      if (r.top >= v.top + (k === 0 ? 0 : 50) && r.bottom <= v.bottom && rows[k].el.contains(at(p.x, p.y))) { j = k; break; }
    }
    if (j < 0) j = i - 1;
    return { i, j, me, target: rows[j] };
  }

  async function dragMouse(plan) {
    const handle = rowHandle(plan.me) || plan.me.el;
    const from = center(handle);
    const tr = plan.target.el.getBoundingClientRect();
    const to = { x: from.x, y: tr.top + Math.max(2, Math.min(6, tr.height / 4)) };
    fire(handle, 'pointerover', from.x, from.y);
    fire(handle, 'mouseover', from.x, from.y);
    fire(handle, 'pointerdown', from.x, from.y);
    fire(handle, 'mousedown', from.x, from.y);
    await sleep(80);
    const steps = 16;
    for (let k = 1; k <= steps; k++) {
      const x = from.x + ((to.x - from.x) * k) / steps;
      const y = from.y + ((to.y - from.y) * k) / steps;
      const t = at(x, y);
      fire(t, 'pointermove', x, y);
      fire(t, 'mousemove', x, y);
      await sleep(30);
    }
    await sleep(150);
    const t = at(to.x, to.y);
    fire(t, 'pointermove', to.x, to.y);
    fire(t, 'mousemove', to.x, to.y);
    await sleep(80);
    fire(t, 'pointerup', to.x, to.y);
    fire(t, 'mouseup', to.x, to.y);
  }

  async function dragHtml5(plan) {
    const handle = rowHandle(plan.me) || plan.me.el;
    const src = handle.closest('[draggable=true]') || plan.me.el.closest('[draggable=true]') || plan.me.el.querySelector('[draggable=true]') || handle;
    const from = center(handle);
    const tr = plan.target.el.getBoundingClientRect();
    const to = { x: from.x, y: tr.top + Math.max(2, Math.min(6, tr.height / 4)) };
    const dt = new DataTransfer();
    const dfire = (el, type, x, y) => el.dispatchEvent(new DragEvent(type, { bubbles: true, cancelable: true, composed: true, view: window, clientX: x, clientY: y, screenX: x, screenY: y, dataTransfer: dt }));
    fire(handle, 'pointerdown', from.x, from.y);
    fire(handle, 'mousedown', from.x, from.y);
    await sleep(30);
    dfire(src, 'dragstart', from.x, from.y);
    await sleep(60);
    for (let k = 1; k <= 8; k++) {
      const y = from.y + ((to.y - from.y) * k) / 8;
      const t = at(to.x, y);
      dfire(t, 'dragenter', to.x, y);
      dfire(t, 'dragover', to.x, y);
      await sleep(40);
    }
    await sleep(120);
    const t = at(to.x, to.y);
    dfire(t, 'dragover', to.x, to.y);
    dfire(t, 'drop', to.x, to.y);
    dfire(src, 'dragend', to.x, to.y);
    fire(t, 'pointerup', to.x, to.y);
    fire(t, 'mouseup', to.x, to.y);
  }

  async function dragKeys(plan) {
    const handle = rowHandle(plan.me) || plan.me.el;
    const key = (type, k, code) => {
      const ev = new KeyboardEvent(type, { key: k, code: k === ' ' ? 'Space' : k, bubbles: true, cancelable: true });
      Object.defineProperty(ev, 'keyCode', { get: () => code });
      Object.defineProperty(ev, 'which', { get: () => code });
      handle.dispatchEvent(ev);
    };
    if (handle.focus) handle.focus();
    key('keydown', ' ', 32); key('keyup', ' ', 32);
    await sleep(150);
    for (let k = 0; k < plan.i; k++) { key('keydown', 'ArrowUp', 38); key('keyup', 'ArrowUp', 38); await sleep(90); }
    await sleep(150);
    key('keydown', ' ', 32); key('keyup', ' ', 32);
  }

  /** Tutamaç yalnızca fare üstündeyken görünüyorsa (CSS :hover) sürükleme süresince görünür yap; sonra eski haline döndür. */
  async function withHandle(plan, fn) {
    const hd = rowHandle(plan.me);
    const fixes = [];
    if (hd) {
      for (let el = hd; el && el !== plan.me.el.parentElement; el = el.parentElement) {
        const r = el.getBoundingClientRect();
        const cs = getComputedStyle(el);
        if (r.width === 0 || r.height === 0 || cs.visibility === 'hidden' || parseFloat(cs.opacity) < 0.1) {
          fixes.push([el, el.getAttribute('style')]);
          if (cs.display === 'none') el.style.setProperty('display', el === hd ? 'inline-block' : 'block', 'important');
          el.style.setProperty('visibility', 'visible', 'important');
          el.style.setProperty('opacity', '1', 'important');
        }
        if (el === plan.me.el) break;
      }
      for (const t of ['pointerenter', 'mouseenter', 'pointerover', 'mouseover']) {
        const c = center(hd.getBoundingClientRect().width ? hd : plan.me.el);
        fire(hd, t, c.x, c.y);
      }
    }
    try {
      await fn(plan);
    } finally {
      for (const [el, st] of fixes.reverse()) { if (st === null) el.removeAttribute('style'); else el.setAttribute('style', st); }
    }
  }

  const DRAGS = { mouse: (p) => withHandle(p, dragMouse), html5: (p) => withHandle(p, dragHtml5), keys: (p) => withHandle(p, dragKeys) };
  let dragWay = null; // bu panelde işe yarayan yol (sonraki adımlarda önce o denenir)

  /**
   * Tek hamlede: tutamaçtan tut, basılı tutarken listeyi yukarı kaydır (imleç listenin üst kenarında, kütüphanenin
   * kendi kaydırması da çalışsın), en üste varınca ilk kategorinin üstüne gel ve bırak.
   */
  async function holdToTop(name, way) {
    const rows = categoryRows();
    const i = findCat(rows, name);
    if (i <= 0) return;
    const me = rows[i];
    me.el.scrollIntoView({ block: 'center' });
    nudge(scroller(me.el)); nudge();
    await sleep(200);
    const box = scroller(me.el);
    const atTop = () => (box ? box.scrollTop <= 0 : true) && window.scrollY <= 0;
    await withHandle({ me }, async () => {
      const hd = rowHandle(me) || me.el;
      const from = center(hd);
      const x = from.x;
      const html5 = way === 'html5';
      const dt = html5 ? new DataTransfer() : null;
      const src = html5 ? (hd.closest('[draggable=true]') || me.el.closest('[draggable=true]') || me.el.querySelector('[draggable=true]') || hd) : null;
      const dfire = (el, type, px, py) => el.dispatchEvent(new DragEvent(type, { bubbles: true, cancelable: true, composed: true, view: window, clientX: px, clientY: py, screenX: px, screenY: py, dataTransfer: dt }));
      const move = (px, py) => {
        const t = at(px, py);
        if (html5) { dfire(t, 'dragenter', px, py); dfire(t, 'dragover', px, py); } else { fire(t, 'pointermove', px, py); fire(t, 'mousemove', px, py); }
      };
      fire(hd, 'pointerdown', from.x, from.y);
      fire(hd, 'mousedown', from.x, from.y);
      await sleep(60);
      if (html5) { dfire(src, 'dragstart', from.x, from.y); await sleep(40); }
      for (let k = 1; k <= 4; k++) { move(x, from.y - k * 6); await sleep(25); } // sürüklemeyi başlat (eşik)
      const v0 = viewport(box);
      const edge = v0.top + 8;
      for (let k = 1; k <= 6; k++) { move(x, from.y + ((edge - from.y) * k) / 6); await sleep(20); }
      // Basılı tutarken yukarı kaydır
      for (let n = 0; n < 600 && !atTop(); n++) {
        const step = Math.max(240, (box ? box.clientHeight : innerHeight) * 0.6);
        if (box && box.scrollTop > 0) { box.scrollTop = Math.max(0, box.scrollTop - step); nudge(box); }
        else { window.scrollTo(0, Math.max(0, window.scrollY - step)); nudge(); }
        move(x, edge);
        await sleep(16);
        if (n % 10 === 9) catStatus('En üste taşınıyor… (basılı)');
      }
      move(x, edge);
      await sleep(150);
      // İlk kategori (taşınan hariç) üstüne gel, bırak
      // Son yaklaşma aşağıdan yukarı: önce ikinci kategorinin ortasına, sonra ilkinin üst kenarına (yukarıdan gelince kütüphane "altına" koyar).
      const others = categoryRows().filter((r) => r.el !== me.el && !r.el.contains(me.el) && !me.el.contains(r.el));
      const fr = others[0] ? others[0].el.getBoundingClientRect() : { top: edge, height: 20, bottom: edge + 20 };
      const sr = others[1] ? others[1].el.getBoundingClientRect() : { top: fr.bottom, height: fr.height || 20 };
      const via = { x, y: Math.min(viewport(box).bottom - 4, sr.top + (sr.height || 20) / 2) };
      const to = { x, y: Math.max(viewport(box).top + 2, fr.top + Math.max(2, Math.min(6, fr.height / 4))) };
      for (let k = 1; k <= 4; k++) { move(x, edge + ((via.y - edge) * k) / 4); await sleep(30); }
      await sleep(80);
      for (let k = 1; k <= 5; k++) { move(x, via.y + ((to.y - via.y) * k) / 5); await sleep(30); }
      // Bazı kütüphaneler aynı satırda ikinci bir hareket görmeden yer değiştirmez: üst kenarda küçük kıpırdatma.
      for (const dy of [3, 0, 2, 0]) { move(x, to.y + dy); await sleep(40); }
      await sleep(150);
      const t = at(to.x, to.y);
      if (html5) { dfire(t, 'dragover', to.x, to.y); dfire(t, 'drop', to.x, to.y); dfire(src, 'dragend', to.x, to.y); }
      fire(t, 'pointerup', to.x, to.y);
      fire(t, 'mouseup', to.x, to.y);
    });
  }

  /**
   * Kategoriyi yeniden bul. Panel bırakmadan sonra listeyi yeniden çizebilir (servisler açılır, sayfa başa döner):
   * bekle → yeniden daralt → en üste bak → gerekirse sona kadar in.
   */
  async function refind(name) {
    let i = indexOf(name);
    if (i >= 0) return i;
    await sleep(800);
    if ((i = indexOf(name)) >= 0) return i;
    await collapseAll();
    await sleep(300);
    if ((i = indexOf(name)) >= 0) return i;
    const rows = categoryRows();
    const box = scroller(rows[0] && rows[0].el);
    if (box) box.scrollTop = 0;
    window.scrollTo(0, 0);
    nudge(box); nudge();
    await sleep(600);
    if ((i = indexOf(name)) >= 0) return i;
    await scrollToEnd();
    return indexOf(name);
  }

  async function moveToTop(name) {
    await collapseAll();
    let i = await refind(name);
    if (i < 0) return { ok: false, note: 'kategori listede bulunamadı: ' + name };
    // Önce tek hamle (tut → yukarı kaydır → bırak); olmazsa adım adım.
    for (const w of (dragWay && dragWay !== 'keys' ? [dragWay] : ['mouse', 'html5'])) {
      if (i <= 0) break;
      catStatus('En üste taşınıyor… (tek hamle)');
      await holdToTop(name, w);
      const ni = await waitFor(() => { const n = indexOf(name); return n >= 0 ? n + 1 : null; }, 1500, 100);
      const n = ni ? ni - 1 : await refind(name);
      dragLog.push({ yol: 'tek-hamle/' + w, sira: i, sonra: n });
      dragLog = dragLog.slice(-12);
      if (n === 0) { dragWay = w; i = 0; break; }
      if (n > 0 && n < i) { dragWay = w; i = n; } // kısmen çıktı: kalanı adım adım
      await sleep(200);
    }
    for (let step = 0; step < 120 && i > 0; step++) {
      catStatus('En üste taşınıyor… (' + i + ' sıra kaldı)');
      const ways = dragWay ? [dragWay] : Object.keys(DRAGS);
      let moved = false;
      for (const w of ways) {
        let plan = await dragPlan(name);
        if (plan.i < 0) { await refind(name); plan = await dragPlan(name); }
        if (!(plan.i > 0)) { i = plan.i; moved = true; break; }
        const hd = rowHandle(plan.me);
        const hr = hd ? hd.getBoundingClientRect() : null;
        await DRAGS[w](plan);
        dragLog.push({ yol: w, sira: plan.i, hedef: plan.j, tutamac: hd ? Math.round(hr.width) + 'x' + Math.round(hr.height) : 'yok', sonra: indexOf(name) });
        dragLog = dragLog.slice(-12);
        const ni = await waitFor(() => { const n = indexOf(name); return n >= 0 && n < plan.i ? n + 1 : null; }, 1500, 100);
        if (ni) { i = ni - 1; dragWay = w; moved = true; break; }
      }
      if (!moved) {
        if (dragWay) { dragWay = null; continue; } // bildiğimiz yol bu kez olmadı: hepsini bir daha dene
        showDiag();
        return { ok: false, note: 'sürükleme bu panelde çalışmadı — kategoriyi elle taşı; "Teşhisi kopyala"ya basıp bana gönder' };
      }
      if (i < 0) i = await refind(name);
      if (i < 0) return { ok: false, note: 'en üste taşındı mı doğrulanamadı (panel listeyi yeniledi); listeye bir bak' };
    }
    const box = scroller(categoryRows()[0] && categoryRows()[0].el);
    scrollBoxTo(box, 0);
    const first = categoryRows()[0];
    if (first) { first.el.classList.remove('tgw-flash'); void first.el.offsetWidth; first.el.classList.add('tgw-flash'); }
    return i === 0 ? { ok: true } : { ok: false, note: 'en üste çıkmadı (' + i + '. sırada kaldı)' };
  }

  /**
   * Kategori akışı. Açık pencere varsa: o kategori. Yoksa: listeyi daralt → sona in → son aktif kategori.
   * Ad çevrilir → Save → kategori en üste sürüklenir. Deneme modunda yalnızca ad yazılır.
   * auto: import sonrası; yalnızca import'tan önce listede OLMAYAN (yeni) kategorilere dokunulur.
   */
  async function catFlow({ auto = false, before = null, info = null } = {}) {
    if (catRunning) return;
    catRunning = true;
    busy(true);
    if (cbar) { delete cbar.dataset.closed; delete cbar.dataset.done; }
    ensureCatBar();
    cbar.classList.add('tgw-running');
    const done = [];
    const report = []; // tgwatch'a: {name, src, moved}
    let last = { t: '', kind: '' };
    const end = (t, kind) => { last = { t, kind }; catStatus(t, kind); if (cbar) cbar.dataset.done = '1'; };
    try {
      const o = await opts();
      const save = !o.dry;
      const top = save && o.catTop !== false;
      if (categoryDialog()) {
        const r = await translateDialog(save);
        if (!r.ok) return end(r.note, 'warn');
        if (!top) return end(r.name + ' · ' + r.how + (save ? ' · kaydedildi' : ' · deneme: Save\'e basılmadı'), 'ok');
        const m = await moveToTop(r.name);
        const lg = r.logo ? ' · ' + r.logo : '';
        return end(m.ok ? r.name + ' · kaydedildi, en üstte' + lg : r.name + ' · kaydedildi' + lg + ', ama ' + m.note, m.ok ? 'ok' : 'warn');
      }
      let max = 1;
      let left = 0;
      for (let n = 0; n < max; n++) {
        catStatus('Liste yalnızca kategori adlarına daraltılıyor…');
        await collapseAll();
        catStatus('Listenin sonuna gidiliyor…');
        await scrollToEnd();
        const all = categoryRows();
        const { active } = splitActive(all);
        let pick = active[active.length - 1];
        if (!pick) {
          showDiag();
          return end(all.length ? all.length + ' kategori bulundu ama hepsi kapalı görünüyor — "Teşhisi kopyala"ya basıp bana gönder' : 'Kategori satırı bulunamadı — "Teşhisi kopyala"ya basıp bana gönder', 'bad');
        }
        if (auto) {
          // Sondan geriye: import'tan önce olmayan, Azerice olmayan ardışık kategoriler = yeni import edilenler.
          const seen = new Set((before || []).map(lower));
          const fresh = [];
          for (let j = active.length - 1; j >= 0; j--) {
            if (seen.has(lower(active[j].name)) || AZ_ONLY.test(active[j].name)) break;
            fresh.unshift(active[j]);
          }
          if (!fresh.length) {
            return end(n ? done.length + ' yeni kategori hazır' : 'Yeni kategori yok (servisler mevcut kategoriye eklenmiş) — dokunulmadı', 'ok');
          }
          // Önceki liste tam sayılabildiyse (önce + yeni = şimdi) hepsi; değilse yalnızca en sondaki.
          if (n === 0) max = before && before.length && before.length + fresh.length === all.length ? Math.min(fresh.length, 5) : 1;
          if (n === 0) left = fresh.length - max;
          pick = fresh[fresh.length - 1];
        } else if (AZ_ONLY.test(pick.name)) {
          return end('Son aktif kategori zaten Azerice: ' + pick.name + ' — dokunulmadı', 'ok');
        }
        catStatus((auto ? 'Import edilen kategori: ' : 'Son aktif kategori: ') + pick.name);
        const op = await openCategoryEditor(pick);
        if (op.error) return end(op.error, 'bad');
        const r = await translateDialog(save);
        if (!r.ok) return end(r.note, 'warn');
        if (!save) return end(r.name + ' · ' + r.how + ' · deneme: Save\'e basılmadı', 'ok');
        const rep = { name: r.name, src: r.src || pick.name, moved: false };
        report.push(rep);
        if (top) {
          const m = await moveToTop(r.name);
          if (!m.ok) return end(r.name + ' · kaydedildi' + (r.logo ? ' · ' + r.logo : '') + ', ama ' + m.note, 'warn');
          rep.moved = true;
        }
        done.push(r.name + (r.logo ? ' · ' + r.logo : ''));
      }
      const more = left > 0 ? ' · sonda ' + left + ' yeni kategori daha olabilir: düğmeye tekrar bas' : '';
      end((done.length === 1 ? done[0] + ' · kaydedildi' : done.length + ' kategori kaydedildi') + (top ? ', en üstte' : '') + more, more ? 'warn' : 'ok');
    } finally {
      catRunning = false;
      busy(false);
      cbar && cbar.classList.remove('tgw-running');
      if (auto) reportImport(report, last, info);
    }
  }

  /** Import sonrası tgwatch'a haber: bota özet, istenirse kanalda paylaşım (Ayarlar → Telegram botu). */
  async function reportImport(cats, last, info) {
    const o = await opts();
    if (o.dry || o.notify === false) return;
    const res = await api('imported', { categories: cats, note: last.t, kind: last.kind, counts: (info && info.counts) || {}, services: (info && info.services) || [] });
    if (!cbar) return;
    if (res.ok) {
      const ch = res.status === 'posted' ? ' · kanalda paylaşıldı' : res.status === 'pending' ? ' · Telegram\'da "Kanalda paylaş"a bas' : res.notified ? ' · Telegram\'a bildirildi' : '';
      if (ch) catStatus(last.t + ch, last.kind || 'ok');
    } else if (!/Bilinmeyen işlem/.test(res.error || '')) {
      catStatus(last.t + ' · Telegram bildirimi gitmedi: ' + (res.error || 'tgwatch hatası'), 'warn');
    }
  }

  function ensureCatBar() {
    if (!cbar) buildCatBar();
    if (!cbar.isConnected) { document.body.appendChild(cbar); cbar.classList.add('tgw-floating'); }
  }

  /** Save anında: değişen adı bankaya öğret. */
  function rememberCategory(dlg) {
    const name = dlg.input ? dlg.input.value : '';
    if (catSrc && name && lower(catSrc) !== lower(name) && !AZ_ONLY.test(catSrc)) {
      send({ type: 'api', action: 'learn', payload: { items: [{ id: 'cat', src: catSrc, az: name, kind: 'category', edited: !catFilled || lower(name) !== lower(catFilled) }] } });
    }
  }

  // Kullanıcı kategori penceresinde Save'e basınca adı öğren.
  document.addEventListener('click', (e) => {
    if (catRunning) return;
    const dlg = categoryDialog();
    if (!dlg || ours(e.target) || !dlg.box.contains(e.target)) return;
    const b = e.target.closest && e.target.closest('button, a, input[type=submit]');
    if (b && SAVE_WORDS.includes(lower(b.value || btnText(b)))) { rememberCategory(dlg); rememberLogoPick(dlg); }
  }, true);

  // ---- mini panelden gelen istekler ----
  chrome.runtime.onMessage.addListener((msg, sender, sendResponse) => {
    if (!msg || typeof msg !== 'object') return false;
    if (msg.type === 'tgw-status') {
      const rows = findModal() ? scanRows() : [];
      const ctx = rows.length ? 'import' : categoryDialog() ? 'category' : (/\/admin\/services\/?$/.test(location.pathname) ? 'list' : '');
      sendResponse({ screen: rows.length > 0, ctx, rows: rows.length, running: running || catRunning, counts: counts(), changed: [...state.values()].filter((s) => s.filled).length });
      return false;
    }
    if (msg.type === 'tgw-learn-logos') {
      walkLearnLogos().then(sendResponse, (e) => sendResponse({ ok: false, error: String(e) }));
      return true;
    }
    if (msg.type === 'tgw-run') {
      if (!(findModal() && scanRows().length)) {
        if (categoryDialog() || categoryRows().length) { catFlow(); sendResponse({ ok: true }); return false; }
        sendResponse({ ok: false, error: 'Import ekranında satır yok' });
        return false;
      }
      runAll();
      sendResponse({ ok: true });
      return false;
    }
    if (msg.type === 'tgw-undo') {
      restore(null);
      sendResponse({ ok: true });
      return false;
    }
    return false;
  });

  // Panel satırları sık yeniden çizebilir: en geç 150 ms'de bir yeniden boya (beklemeden, sürekli değişimde de).
  let scheduled = false;
  const mo = new MutationObserver(() => {
    if (scheduled) return;
    scheduled = true;
    setTimeout(() => { scheduled = false; ensureBar(); ensureCat(); descButtons(); }, 150);
  });
  mo.observe(document.documentElement, { childList: true, subtree: true });
  /** "Edit description" penceresine düğme: yalnızca ayar açıkken (ayar her seferinde okunur, önbellek 3 sn). */
  let descOn = null; let descAt = 0;
  async function descButtons() {
    if (Date.now() - descAt > 3000) { descAt = Date.now(); descOn = (await opts()).descTranslate !== false; }
    if (descOn) addDescButtons();
  }
  ensureBar();
  ensureCat();
  descButtons();
})();
