// tgwatch eklentisi — Perfect Panel "Import services" ekranı.
// Satırları düğme ve etiket YAZILARINDAN bulur (Edit, Save, Name, Import services), panelin iç HTML'ine bağlı değildir.
// Akış: Hepsini çevir → adlar tek istekte tgwatch'a → her satır: Edit → Name kutusuna Azerice ad → Save → sıradaki.
// Import'a basılınca son adlar (elle düzeltilenler dahil) tgwatch bankasına öğretilir. Import kararı her zaman kullanıcıda.
(() => {
  if (window.__tgwImport) return;
  window.__tgwImport = true;

  const ROW_ID = /^\s*(\d{1,10})\s*[-–—]\s*(.+)$/s;
  const norm = (s) => (s || '').replace(/\s+/g, ' ').trim();
  const lower = (s) => norm(s).toLowerCase(); // dil bağımsız: tr yerelinde "Import" → "ımport" olurdu
  const sleep = (ms) => new Promise((r) => setTimeout(r, ms));
  const btnText = (b) => norm(b.innerText !== undefined ? b.innerText : b.textContent);
  const visible = (el) => !!el && el.isConnected && !!(el.offsetWidth || el.offsetHeight || el.getClientRects().length);

  /** Metin + resim emojilerinin alt yazısı (input, textarea, düğme hariç). */
  function textWithAlt(el) {
    let out = '';
    for (const n of el.childNodes) {
      if (n.nodeType === 3) out += n.nodeValue;
      else if (n.nodeType === 1) {
        const tag = n.tagName;
        if (tag === 'IMG') out += n.getAttribute('alt') || '';
        else if (tag === 'INPUT' || tag === 'TEXTAREA' || tag === 'BUTTON' || tag === 'SELECT' || tag === 'SCRIPT' || tag === 'STYLE') continue;
        else if (n.classList && n.classList.contains('tgw-badge')) continue;
        else out += textWithAlt(n);
      }
    }
    return out;
  }

  /** İnceleme ekranı: "Import services" düğmesi + Edit/Save düğmeli satırlar. */
  function findModal() {
    const importBtn = [...document.querySelectorAll('button, a, input[type=submit]')]
      .find((b) => visible(b) && lower(b.value || btnText(b)) === 'import services');
    if (!importBtn) return null;
    const box = importBtn.closest('[role=dialog], .modal, .modal-dialog, .modal-content') || document.body;
    return { box, importBtn };
  }

  function actionButtons(box) {
    return [...box.querySelectorAll('button, a')].filter((b) => {
      const t = lower(btnText(b));
      return (t === 'edit' || t === 'save') && !b.closest('.tgw-bar');
    });
  }

  /** Bir satırın kimliği ve başlıktaki ad: "15341 - S106 - Instagram ..." */
  function rowHeader(row) {
    let best = null;
    const walker = document.createTreeWalker(row, NodeFilter.SHOW_ELEMENT);
    for (let el = walker.currentNode; el; el = walker.nextNode()) {
      if (el.closest('.tgw-badge')) continue;
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
    const btns = actionButtons(m.box);
    const rows = [];
    const seen = new Set();
    for (const b of btns) {
      let row = b;
      while (row.parentElement && row.parentElement !== m.box) {
        const p = row.parentElement;
        if (actionButtons(p).length > 1) break;
        row = p;
      }
      const h = rowHeader(row);
      if (!h || seen.has(h.id)) continue;
      seen.add(h.id);
      rows.push({ id: h.id, el: row, header: h, btn: b });
    }
    return rows;
  }

  function findRow(id) {
    return scanRows().find((r) => r.id === id) || null;
  }

  function rowButton(row, label) {
    return actionButtons(row.el).find((b) => lower(btnText(b)) === label && visible(b)) || null;
  }

  /** Name kutusu: "Name" yazısından sonraki ilk metin kutusu; yoksa sayı olmayan en uzun metin kutusu. */
  function nameInput(row) {
    const inputs = [...row.el.querySelectorAll('input[type=text], input:not([type]), textarea')]
      .filter((i) => visible(i) && !i.readOnly && !i.disabled && !i.closest('.tgw-bar'));
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
    const setter = Object.getOwnPropertyDescriptor(proto, 'value').set;
    input.focus();
    setter.call(input, value);
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

  function api(action, payload) {
    return new Promise((resolve) => {
      try {
        chrome.runtime.sendMessage({ type: 'api', action, payload }, (res) => {
          if (chrome.runtime.lastError) resolve({ ok: false, error: chrome.runtime.lastError.message });
          else resolve(res || { ok: false, error: 'Cevap yok' });
        });
      } catch (e) {
        resolve({ ok: false, error: 'Eklenti yeniden yüklendi; sayfayı yenileyin.' });
      }
    });
  }

  function opts() {
    return new Promise((resolve) => {
      try {
        chrome.storage.local.get({ delay: 400, dry: false }, (s) => resolve(s || { delay: 400, dry: false }));
      } catch (e) {
        resolve({ delay: 400, dry: false });
      }
    });
  }

  // ---- satır durumu (öğrenme için) ----
  const state = new Map(); // id => {src, filled, final, edited}
  function remember(id, patch) {
    state.set(id, Object.assign(state.get(id) || {}, patch));
  }

  // Panel satırları yeniden çizebilir: işaretler burada saklanır, sayfa her değiştiğinde yeniden uygulanır.
  const marks = new Map(); // id => {kind, title}
  function mark(row, kind, title) {
    marks.set(row.id, { kind, title: title || '' });
    paint(row, kind, title);
  }

  function paint(row, kind, title) {
    if (row.el.classList.contains('tgw-row-' + kind) && row.header.el.querySelector(':scope > .tgw-badge-' + kind)) return;
    row.el.classList.remove('tgw-row-ok', 'tgw-row-warn', 'tgw-row-bad');
    row.el.classList.add('tgw-row-' + kind);
    const host = row.header.el;
    let badge = host.querySelector(':scope > .tgw-badge');
    if (!badge) {
      badge = document.createElement('span');
      badge.className = 'tgw-badge';
      host.appendChild(badge);
    }
    badge.className = 'tgw-badge tgw-badge-' + kind;
    badge.textContent = { ok: 'bankadan', warn: 'kontrol et', bad: 'çevrilmedi' }[kind];
    badge.title = title || '';
  }

  // ---- araç çubuğu ----
  let bar = null;
  let running = false;
  let stopFlag = false;

  function ensureBar() {
    const m = findModal();
    const rows = m ? scanRows() : [];
    if (!m || !rows.length) {
      if (bar) bar.hidden = true;
      return;
    }
    if (!bar) {
      bar = document.createElement('div');
      bar.className = 'tgw-bar';
      bar.innerHTML = '<strong>tgwatch</strong><button type="button" class="tgw-go">Hepsini çevir</button>'
        + '<button type="button" class="tgw-stop" hidden>Durdur</button><span class="tgw-status"></span>';
      document.body.appendChild(bar);
      bar.querySelector('.tgw-go').addEventListener('click', runAll);
      bar.querySelector('.tgw-stop').addEventListener('click', () => { stopFlag = true; status('Durduruluyor…'); });
    }
    bar.hidden = false;
    for (const r of rows) {
      const mk = marks.get(r.id);
      if (mk) paint(r, mk.kind, mk.title);
    }
    if (!running && !marks.size) status(rows.length + ' servis görünüyor');
  }

  function status(t, kind) {
    if (!bar) return;
    const s = bar.querySelector('.tgw-status');
    s.textContent = t;
    s.className = 'tgw-status' + (kind ? ' tgw-status-' + kind : '');
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
    const done = await waitFor(() => {
      const r = findRow(id);
      return r && !rowButton(r, 'save') ? r : null;
    }, 8000);
    return !!done;
  }

  async function runAll() {
    if (running) return;
    running = true;
    stopFlag = false;
    bar.querySelector('.tgw-go').disabled = true;
    bar.querySelector('.tgw-stop').hidden = false;
    const o = await opts();
    try {
      const rows = scanRows();
      if (!rows.length) {
        status('Satır bulunamadı', 'bad');
        return;
      }
      status('tgwatch çeviriyor… (' + rows.length + ' ad)');
      const res = await api('translate', { items: rows.map((r) => ({ id: r.id, name: r.header.name })) });
      if (!res.ok) {
        status(res.error || 'tgwatch hatası', 'bad');
        return;
      }
      const byName = new Map(res.items.map((it, i) => [lower(rows[i].header.name), it]));
      let ok = 0, warn = 0, bad = 0, i = 0;
      for (const { id } of rows) {
        if (stopFlag) break;
        i++;
        status(i + '/' + rows.length + ' · #' + id);
        const row = await openRow(id);
        if (!row) {
          bad++;
          const r = findRow(id);
          if (r) mark(r, 'bad', 'Satır açılamadı (Edit/Name bulunamadı)');
          continue;
        }
        const input = nameInput(row);
        const src = input.value;
        remember(id, { src: state.get(id)?.src || src });
        let tr = byName.get(lower(src));
        if (!tr) {
          const one = await api('translate', { items: [{ id, name: src }] });
          tr = one.ok ? one.items[0] : null;
        }
        if (!tr || !tr.az || tr.via === 'none') {
          bad++;
          mark(row, 'bad', tr ? 'Bankada da kurallarda da karşılığı yok' : 'tgwatch cevap vermedi');
          if (!o.dry) await saveRow(id);
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
        if (tr.via === 'bank') {
          ok++;
          mark(r, 'ok', 'Bankadan birebir');
        } else {
          warn++;
          mark(r, 'warn', (tr.via === 'mixed' ? 'Kısmen bankadan' : 'Kurallarla') + (tr.unknown && tr.unknown.length ? ' · bilinmeyen: ' + tr.unknown.join(', ') : ''));
        }
        await sleep(Math.max(0, Number(o.delay) || 0));
      }
      status((stopFlag ? 'Durduruldu. ' : 'Bitti. ') + ok + ' bankadan, ' + warn + ' kontrol et, ' + bad + ' çevrilmedi. Gözden geçirip Import\'a sen bas.', bad ? 'warn' : 'ok');
    } finally {
      running = false;
      if (bar) {
        bar.querySelector('.tgw-go').disabled = false;
        bar.querySelector('.tgw-stop').hidden = true;
      }
    }
  }

  // ---- elle yapılan düzenlemeleri yakala ----
  document.addEventListener('click', (e) => {
    if (running) return;
    const b = e.target.closest && e.target.closest('button, a');
    if (!b || b.closest('.tgw-bar')) return;
    const t = lower(btnText(b));
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
      if (items.length) {
        chrome.runtime.sendMessage({ type: 'api', action: 'learn', payload: { items } }, () => void chrome.runtime.lastError);
      }
      state.clear();
      marks.clear();
    }
  }, true);

  const mo = new MutationObserver(() => {
    clearTimeout(mo.t);
    mo.t = setTimeout(ensureBar, 250);
  });
  mo.observe(document.documentElement, { childList: true, subtree: true });
  ensureBar();
})();
