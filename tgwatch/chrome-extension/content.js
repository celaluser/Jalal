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
  const ours = (el) => !!(el.closest && el.closest('.tgw-bar, .tgw-badge, .tgw-orig, .tgw-cbar, .tgw-side'));

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
      const d = { delay: 400, dry: false, skipAz: true };
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
    if (row.el.classList.contains('tgw-row-' + mk.kind) && badge && badge.dataset.kind === mk.kind) return;
    row.el.classList.remove('tgw-row-ok', 'tgw-row-warn', 'tgw-row-bad', 'tgw-row-done');
    row.el.classList.add('tgw-row-' + mk.kind);
    if (!badge) {
      badge = h('span', 'tgw-badge');
      host.appendChild(badge);
    }
    badge.textContent = '';
    badge.dataset.kind = mk.kind;
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
          mark(r, 'bad', tr ? 'Bankada da kurallarda da karşılığı yok: ' + src : 'tgwatch cevap vermedi');
          continue;
        }
        setValue(input, tr.az);
        remember(id, { filled: tr.az, final: tr.az, edited: false });
        if (!o.dry && !(await saveRow(id))) {
          bad++;
          const r = findRow(id);
          if (r) mark(r, 'bad', 'Save sonrası satır kapanmadı');
          continue;
        }
        const r = findRow(id) || row;
        const why = 'Orijinal: ' + src + (tr.unknown && tr.unknown.length ? '\nBilinmeyen: ' + tr.unknown.join(', ') : '');
        if (tr.via === 'bank') { ok++; mark(r, 'ok', 'Bankadan birebir\n' + why); }
        else { warn++; mark(r, 'warn', (tr.via === 'mixed' ? 'Kısmen bankadan' : 'Kurallarla') + '\n' + why); }
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
    } else if (t === 'import services' && state.size) {
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
    go.addEventListener('click', () => (categoryDialog() ? runCategory() : runLastCategory()));
    const close = h('button', 'tgw-btn tgw-ghost tgw-cclose', '×');
    close.type = 'button';
    close.title = 'Gizle (sayfa yenilenince geri gelir)';
    close.addEventListener('click', () => { cbar.dataset.closed = '1'; cbar.remove(); });
    acts.append(go, close);
    const prog = h('div', 'tgw-progress');
    prog.appendChild(h('i'));
    cbar.append(brand, acts, prog);
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
      const name = norm(textWithAlt(row).replace(/\bActions\b\s*[▾▼⌄]?/g, '')).replace(/\s+NEW$/i, '');
      if (!name) continue;
      out.push({ el: row, btn: b, name });
    }
    return out;
  }

  /** Devre dışı kategori: soluk (opacity), "disabled/inactive" sınıfı ya da "Disabled" yazısı. */
  function isDisabled(row) {
    for (let el = row.el, i = 0; el && i < 4; el = el.parentElement, i++) {
      if (/(^|[\s_-])(disabled|inactive|is-disabled|off)($|[\s_-])/i.test(el.className || '')) return true;
      if (parseFloat(getComputedStyle(el).opacity) < 0.75) return true;
    }
    const nameEl = [...row.el.querySelectorAll('*')].find((e) => e.children.length === 0 && norm(e.textContent) && !/^actions\b/i.test(norm(e.textContent)));
    if (nameEl && parseFloat(getComputedStyle(nameEl).opacity) < 0.75) return true;
    if (nameEl) { // gri yazı: kapalı kategori (aktifler koyu)
      const m = getComputedStyle(nameEl).color.match(/(\d+(?:\.\d+)?)/g);
      if (m && m.length >= 3) {
        const [r, g, b] = m.map(Number);
        const a = m.length > 3 ? Number(m[3]) : 1;
        if (a < 0.75 || (0.2126 * r + 0.7152 * g + 0.0722 * b) / 255 > 0.55) return true;
      }
    }
    return /\bdisabled\b/i.test(row.name);
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

  function ensureCat() {
    if (cbar && cbar.dataset.closed) return;
    const importOpen = !!(findModal() && scanRows().length);
    const dlg = !importOpen && categoryDialog();
    if (!dlg && !catRunning) { catSrc = null; catFilled = null; } // pencere kapandı: hatırlanan orijinal ad bir sonraki kategoriye taşınmasın
    const anyModal = [...document.querySelectorAll(DIALOG)].some((m) => visible(m) && !ours(m));
    const onList = !importOpen && !dlg && !anyModal && /\/admin\/services\/?$/.test(location.pathname); // ağır tarama yalnızca düğmeye basınca
    if (!dlg && !onList) {
      if (cbar && cbar.isConnected && !catRunning) cbar.remove();
      if (!dlg) catSrc = null;
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
    if (dlg) {
      if (catSrc === null && dlg.input) catSrc = dlg.input.value;
      go.textContent = 'Bu kategorinin adını çevir';
      if (!catRunning) catStatus('Kategori: ' + (dlg.input ? dlg.input.value : ''));
    } else {
      go.textContent = 'Son kategorinin adını çevir';
      if (!catRunning) catStatus('En sondaki aktif kategoriyi açıp adını çevirir; logoyu sen seçersin');
    }
  }

  async function runLastCategory() {
    if (catRunning) return;
    catRunning = true;
    try {
      catStatus('Listenin sonuna gidiliyor…');
      await scrollToEnd();
      const rows = categoryRows().filter((r) => !isDisabled(r));
      const last = rows[rows.length - 1];
      if (!last) { catStatus('Aktif kategori bulunamadı', 'bad'); return; }
      last.el.scrollIntoView({ block: 'center' });
      last.el.classList.add('tgw-flash');
      catStatus('Son aktif kategori: ' + last.name);
      await sleep(300);
      // Actions menüsünü aç, "Edit category"ye tıkla (menü sayfanın başka yerine açılabilir: görünen olanı al)
      const before = new Set(editCategoryLinks().filter(visible));
      last.btn.click();
      const item = await waitFor(() => {
        const vis = editCategoryLinks().filter(visible);
        return vis.find((e) => last.el.contains(e)) || vis.find((e) => !before.has(e)) || (vis.length === 1 ? vis[0] : null);
      }, 2500) || [...last.el.querySelectorAll('a, button, li, span')].find((e) => /^edit\s+category$/i.test(norm(e.textContent)));
      if (!item) { catStatus('"Edit category" menüde bulunamadı', 'bad'); return; }
      item.click();
      const dlg = await waitFor(categoryDialog, 5000);
      if (!dlg) { catStatus('Edit category penceresi açılmadı', 'bad'); return; }
      catSrc = dlg.input.value;
    } finally {
      catRunning = false;
    }
    await runCategory();
  }

  async function runCategory() {
    if (catRunning) return;
    catRunning = true;
    cbar && cbar.classList.add('tgw-running');
    try {
      const dlg = categoryDialog();
      if (!dlg || !dlg.input) { catStatus('Kategori penceresi bulunamadı', 'bad'); return; }
      // Güvenlik: penceredeki ad hatırlanan addan farklıysa ve Azerice değilse başka bir kategori açılmıştır → penceredeki ad esas.
      if (catSrc === null || (lower(dlg.input.value) !== lower(catSrc) && !AZ_ONLY.test(dlg.input.value))) catSrc = dlg.input.value;
      const src = catSrc;
      let az = '';
      let note = '';
      if (AZ_ONLY.test(dlg.input.value)) {
        note = 'ad zaten Azerice';
      } else {
        catStatus('Çevriliyor…');
        const res = await api('translate', { items: [{ id: 'cat', name: src, kind: 'category' }] });
        const tr = res.ok ? res.items[0] : null;
        if (tr && tr.az && tr.via !== 'none' && tr.via !== 'already') {
          az = tr.az;
          setValue(dlg.input, az);
          catFilled = az;
          let o = dlg.input.parentElement.querySelector(':scope > .tgw-orig');
          if (!o) { o = h('span', 'tgw-orig'); dlg.input.insertAdjacentElement('afterend', o); }
          o.textContent = src;
          o.title = 'Orijinal kategori adı';
          note = tr.via === 'bank' ? 'ad bankadan' : 'ad kurallarla — kontrol et';
        } else {
          note = res.ok ? 'ad çevrilemedi' : (res.error || 'tgwatch hatası');
        }
      }
      catStatus(note + (az ? ' · logoyu seç, Save\'e bas' : ''), /çevrilemedi|hata/.test(note) ? 'warn' : 'ok');
    } finally {
      catRunning = false;
      cbar && cbar.classList.remove('tgw-running');
    }
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
        if (categoryDialog()) { runCategory(); sendResponse({ ok: true }); return false; }
        if (categoryRows().length) { runLastCategory(); sendResponse({ ok: true }); return false; }
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
