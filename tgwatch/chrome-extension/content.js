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
  const lower = (s) => norm(s).toLowerCase(); // dil bağımsız: tr yerelinde "Import" → "ımport" olurdu
  const sleep = (ms) => new Promise((r) => setTimeout(r, ms));
  const btnText = (b) => norm(b.innerText !== undefined ? b.innerText : b.textContent);
  const visible = (el) => !!el && el.isConnected && !!(el.offsetWidth || el.offsetHeight || el.getClientRects().length);
  const ours = (el) => !!(el.closest && el.closest('.tgw-bar, .tgw-badge, .tgw-orig, .tgw-cbar, .tgw-side, .tgw-word, .tgw-pop'));

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
      const d = { delay: 400, dry: false, skipAz: true, autoCat: true, catTop: true };
      try { chrome.storage.local.get(d, (s) => resolve(s || d)); } catch (e) { resolve(d); }
    });
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
      o.title = 'Orijinal ad — tıkla, kopyala';
      o.addEventListener('click', (e) => {
        e.preventDefault();
        e.stopPropagation();
        navigator.clipboard && navigator.clipboard.writeText(o.dataset.src).then(() => {
          o.classList.add('tgw-copied');
          setTimeout(() => o.classList.remove('tgw-copied'), 900);
        });
      });
      host.insertBefore(o, host.firstChild);
    }
    o.dataset.src = st.src;
    o.textContent = st.src;
  }

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

  function openTeach(id, word, anchor) {
    closeTeach();
    pop = h('div', 'tgw-pop');
    pop.setAttribute('role', 'dialog');
    pop.setAttribute('aria-label', 'Kelimeyi öğret');
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
    const ok = h('button', 'tgw-btn', 'Öğret ve düzelt');
    ok.type = 'submit';
    const keep = h('button', 'tgw-btn tgw-ghost', 'Aynen kalsın');
    keep.type = 'button';
    keep.title = 'Marka/özel ad: çevrilmesin, bir daha bilinmeyen sayılmasın';
    const x = h('button', 'tgw-btn tgw-ghost tgw-pop-x', '×');
    x.type = 'button';
    x.title = 'Kapat (Esc)';
    acts.append(ok, keep, x);
    f.append(l1, l2, acts, msg);
    pop.appendChild(f);
    for (const ev of ['click', 'mousedown', 'keydown', 'keyup', 'keypress', 'input']) pop.addEventListener(ev, (e) => e.stopPropagation());
    pop.addEventListener('keydown', (e) => { if (e.key === 'Escape') closeTeach(); });
    x.addEventListener('click', closeTeach);
    const go = async (asKeep) => {
      const src = en.value.trim();
      const dst = az.value.trim();
      if (!src || (!asKeep && !dst)) { msg.textContent = asKeep ? 'Kaynak kelimeyi yaz' : 'Azerice karşılığı yaz'; (asKeep ? en : az).focus(); return; }
      ok.disabled = keep.disabled = true;
      msg.textContent = 'tgwatch\'a öğretiliyor…';
      const res = await api('teach', asKeep ? { en: src, keep: true } : { en: src, az: dst });
      if (!res.ok) { msg.textContent = res.error || 'tgwatch hatası'; ok.disabled = keep.disabled = false; return; }
      send({ type: 'stat', learned: 1 });
      msg.className = 'tgw-pop-msg tgw-pop-ok';
      msg.textContent = asKeep ? '✓ tgwatch sözlüğüne eklendi: “' + res.en + '” çevrilmeyecek' : '✓ tgwatch sözlüğüne eklendi: “' + res.en + '” → “' + res.az + '”';
      status(msg.textContent.slice(2) + ' · satırlar düzeltiliyor…', 'ok');
      await sleep(900);
      closeTeach();
      await retranslate(id, src, asKeep ? src : res.az);
    };
    f.addEventListener('submit', (e) => { e.preventDefault(); go(false); });
    keep.addEventListener('click', () => go(true));
    // Kutu import penceresinin İÇİNE konur: panel penceresi odağı ve tıklamaları kendi içinde tutuyor (dışarıdaki kutuya yazılamaz).
    const host = anchor.closest('.modal') || anchor.closest('[role=dialog]') || anchor.closest('.modal-content') || document.body;
    host.appendChild(pop);
    const r = anchor.getBoundingClientRect();
    const w = 320;
    const want = { x: Math.max(8, Math.min(innerWidth - w - 8, r.left)), y: r.bottom + 6 + 190 > innerHeight ? Math.max(8, r.top - 196) : r.bottom + 6 };
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
  async function retranslate(firstId, word, az) {
    if (running) return;
    const parts = lower(word).split(' ');
    const ids = [...state.entries()].filter(([rid, st]) => st.src && (rid === firstId || (st.unknown || []).some((u) => parts.includes(lower(u))))).map(([rid]) => rid);
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
      status('✓ Sözlüğe eklendi: “' + word + '”' + (az && az !== word ? ' → “' + az + '”' : ' (çevrilmeyecek)') + ' · ' + fixed + ' satır düzeltildi. tgwatch → Çeviri → Sözlük\'te görünür.', 'ok');
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
      for (const r of all) {
        const st = state.get(r.id);
        if (st && st.filled && lower(r.header.name) === lower(st.filled)) { skipped++; continue; }
        if (o.skipAz && AZ_ONLY.test(r.header.name)) {
          skipped++;
          if (!marks.has(r.id)) mark(r, 'done', 'Ad zaten Azerice, dokunulmadı');
          continue;
        }
        rows.push(r);
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
          if (!o.dry) await saveRow(id);
          const r = findRow(id) || row;
          mark(r, 'done', 'Ad zaten Azerice, dokunulmadı');
          continue;
        }
        if (!tr || !tr.az || tr.via === 'none') {
          bad++;
          if (!o.dry) await saveRow(id);
          const r = findRow(id) || row;
          remember(id, { unknown: tr ? (tr.unknown || []) : [] });
          mark(r, 'bad', tr ? 'Bankada da kurallarda da karşılığı yok: ' + src : 'tgwatch cevap vermedi');
          continue;
        }
        setValue(input, tr.az);
        remember(id, { filled: tr.az, final: tr.az, edited: false, unknown: tr.via === 'bank' || tr.via === 'claude' ? [] : (tr.unknown || []) });
        if (!o.dry && !(await saveRow(id))) {
          bad++;
          const r = findRow(id);
          if (r) mark(r, 'bad', 'Save sonrası satır kapanmadı');
          continue;
        }
        const r = findRow(id) || row;
        const why = 'Orijinal: ' + src + (tr.unknown && tr.unknown.length ? '\nBilinmeyen: ' + tr.unknown.join(', ') : '');
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
        if (!o.dry) await saveRow(id);
        remember(id, { filled: undefined, final: st.src, edited: false });
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
    return JSON.stringify({ surum: chrome.runtime.getManifest ? chrome.runtime.getManifest().version : '', adres: location.pathname, kategori: rows.length, kutucuk: visibleChecks(), son: tail,
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
    let stable = 0;
    let lastCount = -1;
    for (let i = 0; i < 40 && stable < 3; i++) {
      const rows = categoryRows();
      const box = scroller(rows.length ? rows[rows.length - 1].el : null);
      // Yalnızca en alta in (bir satıra kaydırmak listeyi yukarı çekip panelin "sonraki sayfayı yükle"sini engeller). Kayma gözle görülsün.
      if (box) box.scrollTo({ top: box.scrollHeight, behavior: 'smooth' });
      window.scrollTo({ top: document.documentElement.scrollHeight, behavior: 'smooth' });
      await waitFor(() => {
        const atEnd = (!box || box.scrollTop + box.clientHeight >= box.scrollHeight - 4)
          && window.scrollY + window.innerHeight >= document.documentElement.scrollHeight - 4;
        return atEnd;
      }, 4000, 100);
      await sleep(500);
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
  function markImport() {
    try { sessionStorage.setItem(AFTER_KEY, JSON.stringify({ at: Date.now(), before: categoryRows().map((r) => r.name) })); } catch (e) { /* depolama kapalı */ }
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
      autoTimer = setTimeout(async () => {
        autoTimer = null;
        const p = pendingImport();
        if (!p || catRunning || anyDialog() || !onServicesList()) return;
        try { sessionStorage.removeItem(AFTER_KEY); } catch (e) { /* yok */ }
        const o = await opts();
        if (o.autoCat === false) return;
        catFlow({ auto: true, before: p.before || [] });
      }, 2000);
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
    if (AZ_ONLY.test(dlg.input.value)) return { ok: false, already: true, name: dlg.input.value, note: 'ad zaten Azerice' };
    catStatus('Çevriliyor: ' + src);
    const res = await api('translate', { items: [{ id: 'cat', name: src, kind: 'category' }] });
    const tr = res.ok ? res.items[0] : null;
    if (!tr || !tr.az || tr.via === 'none' || tr.via === 'already') {
      return { ok: false, note: res.ok ? 'ad çevrilemedi: ' + src + ' — elle yaz, Save\'e bas' : (res.error || 'tgwatch hatası') };
    }
    setValue(dlg.input, tr.az);
    catFilled = tr.az;
    let o = dlg.input.parentElement.querySelector(':scope > .tgw-orig');
    if (!o) { o = h('span', 'tgw-orig'); dlg.input.insertAdjacentElement('afterend', o); }
    o.textContent = src;
    o.title = 'Orijinal kategori adı';
    const how = tr.via === 'bank' ? 'bankadan' : tr.via === 'claude' ? 'Claude ile' : 'kurallarla';
    if (!save) return { ok: true, saved: false, name: tr.az, src, how };
    await sleep(250);
    const d2 = categoryDialog();
    if (!d2 || !d2.save) return { ok: false, note: 'Save düğmesi bulunamadı' };
    rememberCategory(d2);
    d2.save.click();
    const closed = await waitFor(() => !categoryDialog(), 8000, 100);
    if (!closed) return { ok: false, note: 'Save sonrası pencere kapanmadı — paneli kontrol et' };
    send({ type: 'stat', translated: 1 });
    return { ok: true, saved: true, name: tr.az, src, how };
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
        if (box && box.scrollTop > 0) box.scrollTop = Math.max(0, box.scrollTop - step);
        else window.scrollTo(0, Math.max(0, window.scrollY - step));
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

  async function moveToTop(name) {
    await collapseAll();
    let i = indexOf(name);
    if (i < 0) { await scrollToEnd(); i = indexOf(name); }
    if (i < 0) return { ok: false, note: 'kategori listede bulunamadı: ' + name };
    // Önce tek hamle (tut → yukarı kaydır → bırak); olmazsa adım adım.
    for (const w of (dragWay && dragWay !== 'keys' ? [dragWay] : ['mouse', 'html5'])) {
      if (i <= 0) break;
      catStatus('En üste taşınıyor… (tek hamle)');
      await holdToTop(name, w);
      const ni = await waitFor(() => { const n = indexOf(name); return n >= 0 ? n + 1 : null; }, 1500, 100);
      const n = ni ? ni - 1 : -1;
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
        const plan = await dragPlan(name);
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
      if (i < 0) i = indexOf(name);
      if (i < 0) return { ok: false, note: 'taşırken kategori listede kayboldu' };
    }
    const box = scroller(categoryRows()[0] && categoryRows()[0].el);
    if (box) box.scrollTo({ top: 0, behavior: 'smooth' });
    window.scrollTo({ top: 0, behavior: 'smooth' });
    const first = categoryRows()[0];
    if (first) { first.el.classList.remove('tgw-flash'); void first.el.offsetWidth; first.el.classList.add('tgw-flash'); }
    return i === 0 ? { ok: true } : { ok: false, note: 'en üste çıkmadı (' + i + '. sırada kaldı)' };
  }

  /**
   * Kategori akışı. Açık pencere varsa: o kategori. Yoksa: listeyi daralt → sona in → son aktif kategori.
   * Ad çevrilir → Save → kategori en üste sürüklenir. Deneme modunda yalnızca ad yazılır.
   * auto: import sonrası; yalnızca import'tan önce listede OLMAYAN (yeni) kategorilere dokunulur.
   */
  async function catFlow({ auto = false, before = null } = {}) {
    if (catRunning) return;
    catRunning = true;
    if (cbar) { delete cbar.dataset.closed; delete cbar.dataset.done; }
    ensureCatBar();
    cbar.classList.add('tgw-running');
    const done = [];
    const end = (t, kind) => { catStatus(t, kind); if (cbar) cbar.dataset.done = '1'; };
    try {
      const o = await opts();
      const save = !o.dry;
      const top = save && o.catTop !== false;
      if (categoryDialog()) {
        const r = await translateDialog(save);
        if (!r.ok) return end(r.note, 'warn');
        if (!top) return end(r.name + ' · ' + r.how + (save ? ' · kaydedildi' : ' · deneme: Save\'e basılmadı'), 'ok');
        const m = await moveToTop(r.name);
        return end(m.ok ? r.name + ' · kaydedildi, en üstte' : r.name + ' · kaydedildi, ama ' + m.note, m.ok ? 'ok' : 'warn');
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
        if (top) {
          const m = await moveToTop(r.name);
          if (!m.ok) return end(r.name + ' · kaydedildi, ama ' + m.note, 'warn');
        }
        done.push(r.name);
      }
      const more = left > 0 ? ' · sonda ' + left + ' yeni kategori daha olabilir: düğmeye tekrar bas' : '';
      end((done.length === 1 ? done[0] + ' · kaydedildi' : done.length + ' kategori kaydedildi') + (top ? ', en üstte' : '') + more, more ? 'warn' : 'ok');
    } finally {
      catRunning = false;
      cbar && cbar.classList.remove('tgw-running');
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
    if (b && SAVE_WORDS.includes(lower(b.value || btnText(b)))) rememberCategory(dlg);
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
    setTimeout(() => { scheduled = false; ensureBar(); ensureCat(); }, 150);
  });
  mo.observe(document.documentElement, { childList: true, subtree: true });
  ensureBar();
  ensureCat();
})();
