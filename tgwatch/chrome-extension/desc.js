// tgwatch eklentisi — açıklama önerileri sayfası: oku, düzelt, onayla; panele yazılacakları gör, vazgeç; son rapor.
const $ = (id) => document.getElementById(id);
const api = (action, payload) => new Promise((res) => chrome.runtime.sendMessage({ type: 'api', action, payload }, (r) => res(chrome.runtime.lastError || !r ? { ok: false, error: 'tgwatch cevap vermedi' } : r)));
const el = (tag, cls, text) => { const e = document.createElement(tag); if (cls) e.className = cls; if (text !== undefined) e.textContent = text; return e; };
let msgTimer = null;
function say(t) { $('msg').textContent = t; clearTimeout(msgTimer); msgTimer = setTimeout(() => { $('msg').textContent = ''; }, 6000); }
function ago(t) { const m = Math.round((Date.now() - t) / 60000); return m < 1 ? 'az önce' : m < 60 ? m + ' dk önce' : m < 1440 ? Math.round(m / 60) + ' sa önce' : Math.round(m / 1440) + ' gün önce'; }
const oldServer = (r) => /Bilinmeyen işlem/.test((r && r.error) || '');

// ---- sekmeler ----
function view(v) {
  for (const b of document.querySelectorAll('.seg button')) b.setAttribute('aria-selected', b.dataset.v === v ? 'true' : 'false');
  for (const k of ['props', 'queue', 'report']) $('p-' + k).hidden = k !== v;
  history.replaceState(null, '', '#' + v);
  if (v === 'props') loadProps();
  if (v === 'queue') loadQueue();
  if (v === 'report') loadReport();
}
for (const b of document.querySelectorAll('.seg button')) b.onclick = () => view(b.dataset.v);

// ---- öneriler ----
function selected() { return [...document.querySelectorAll('#props input[type=checkbox]:checked')].map((c) => Number(c.value)); }
function syncSel() {
  const n = selected().length;
  const all = document.querySelectorAll('#props input[type=checkbox]').length;
  $('ok-sel').disabled = !n;
  $('ok-sel').textContent = n ? 'Seçilenleri onayla (' + n + ')' : 'Seçilenleri onayla';
  $('regen-sel').disabled = !n;
  $('del-sel').disabled = !n;
  $('all').checked = n > 0 && n === all;
  $('all').indeterminate = n > 0 && n < all;
}
async function loadProps() {
  const box = $('props');
  box.textContent = '';
  box.appendChild(el('p', 'empty', 'Yükleniyor…'));
  const r = await api('desc_props', {});
  box.textContent = '';
  if (!r.ok) { box.appendChild(el('p', 'empty', oldServer(r) ? 'tgwatch sunucusu eski: güncel tgwatch paketini yükle.' : (r.error || 'tgwatch cevap vermedi'))); return; }
  $('n-props').textContent = r.items.length;
  if (!r.items.length) { box.appendChild(el('p', 'empty', 'Onay bekleyen öneri yok. Yeni servis gelince tgwatch kendiliğinden üretir; ya da tgwatch → Açıklamalar\'da kategori seçip üret.')); syncSel(); return; }
  let cat = null;
  for (const it of r.items) {
    if (it.category !== cat) { cat = it.category; box.appendChild(el('h2', 'cat', cat)); }
    const card = el('article', 'card' + (it.new ? ' fresh' : ''));
    const cb = document.createElement('input');
    cb.type = 'checkbox'; cb.value = it.sid; cb.setAttribute('aria-label', 'Seç ' + it.sid);
    cb.onchange = syncSel;
    const h = el('h3');
    h.append(el('small', '', it.sid + ' · '), document.createTextNode(it.name));
    if (it.auto) h.appendChild(el('span', 'tag', it.new ? 'yeni servis' : 'otomatik'));
    const ta = document.createElement('textarea');
    ta.value = it.text;
    ta.rows = Math.min(16, Math.max(4, it.text.split('\n').length + 1));
    ta.dataset.sid = it.sid;
    const row = el('div', 'row');
    const ok = el('button', 'btn sm', 'Onayla');
    ok.onclick = async () => { ok.disabled = true; const x = await api('desc_approve', { items: [{ sid: it.sid, text: ta.value }] }); if (x.ok && x.approved) { card.remove(); say('Onaylandı: ' + it.sid + '. Panele yazılacaklar listesinde.'); counts(); } else { ok.disabled = false; say(x.error || 'Onaylanamadı'); } syncSel(); };
    const lines = el('span', 'muted', it.text.split('\n').length + ' satır');
    ta.oninput = () => { lines.textContent = ta.value.split('\n').filter((l) => l.trim()).length + ' satır · düzeltildi'; };
    const re = el('button', 'btn ghost sm', 'Yeniden üret');
    re.title = 'Bu öneriyi güncel kurallarla yeniden yaz';
    re.onclick = async () => {
      re.disabled = true;
      const x = await api('desc_regen', { sids: [it.sid] });
      if (!x.ok) { re.disabled = false; say(x.error || 'Üretilemedi'); return; }
      const fresh = (await api('desc_props', { peek: 1 })).items || [];
      const n = fresh.find((f) => f.sid === it.sid);
      if (n) { ta.value = n.text; ta.rows = Math.min(16, Math.max(4, n.text.split('\n').length + 1)); lines.textContent = n.text.split('\n').length + ' satır · yeniden üretildi'; }
      re.disabled = false;
      say('Yeniden üretildi: ' + it.sid);
    };
    const del = el('button', 'btn ghost sm danger', 'Sil');
    del.title = 'Öneriyi sil (panele yazılmaz; istersen sonra yeniden üretilir)';
    del.onclick = async () => {
      del.disabled = true;
      const x = await api('desc_del', { sids: [it.sid] });
      if (x.ok && x.deleted) { card.remove(); say('Silindi: ' + it.sid); counts(); syncSel(); } else { del.disabled = false; say(x.error || 'Silinemedi'); }
    };
    row.append(ok, re, del, lines);
    card.append(cb, h, el('span'), ta, el('span'), row);
    box.appendChild(card);
  }
  syncSel();
}
$('all').onchange = () => { for (const c of document.querySelectorAll('#props input[type=checkbox]')) c.checked = $('all').checked; syncSel(); };
$('del-sel').onclick = async () => {
  const sids = selected();
  if (!sids.length || !confirm(sids.length + ' öneri silinsin mi? Panele yazılmazlar; istersen sonra yeniden üretilir.')) return;
  const x = await api('desc_del', { sids });
  say(x.ok ? x.deleted + ' öneri silindi.' : (x.error || 'Silinemedi'));
  await loadProps(); counts();
};
$('regen-sel').onclick = async () => {
  const sids = selected();
  if (!sids.length) return;
  $('regen-sel').disabled = true;
  const x = await api('desc_regen', { sids });
  say(x.ok ? x.made + ' öneri yeni kurallarla yeniden üretildi.' : (x.error || 'Üretilemedi'));
  await loadProps(); counts();
};
$('ok-sel').onclick = async () => {
  const sids = selected();
  const items = sids.map((sid) => ({ sid, text: document.querySelector('#props textarea[data-sid="' + sid + '"]').value }));
  $('ok-sel').disabled = true;
  const x = await api('desc_approve', { items });
  if (!x.ok) { say(x.error || 'Onaylanamadı'); syncSel(); return; }
  say(x.approved + ' açıklama onaylandı. “Panele yazılacak” sekmesinde → Panele ekle.');
  await loadProps();
  counts();
};

// ---- panele yazılacaklar ----
async function loadQueue() {
  const ul = $('queue');
  ul.textContent = '';
  const r = await api('desc_queue', {});
  if (!r.ok) { ul.appendChild(el('li', '', oldServer(r) ? 'tgwatch sunucusu eski.' : (r.error || 'tgwatch cevap vermedi'))); return; }
  $('n-queue').textContent = r.count || 0;
  $('go').disabled = !r.count;
  $('cancel-all').disabled = !r.count;
  const { descRun } = await chrome.storage.local.get({ descRun: null });
  $('run-note').hidden = !descRun;
  if (descRun) {
    $('run-note').textContent = 'Yarıda kalan iş: ' + descRun.i + '/' + descRun.items.length + ' bitti' + (descRun.paused ? ' (' + descRun.paused + ')' : '') + '. “Panele ekle” kaldığı yerden sürdürür.';
    $('go').textContent = 'Kaldığı yerden devam et';
    $('go').disabled = false;
  }
  if (!r.items || !r.items.length) { ul.appendChild(el('li', 'empty', 'Panele yazılacak açıklama yok.')); return; }
  for (const it of r.items) {
    const li = el('li');
    const t = el('div', 't');
    t.append(el('b', '', it.sid + ' · ' + it.name), el('span', 'muted', it.category));
    const pre = el('pre', '', it.text);
    pre.hidden = true;
    t.appendChild(pre);
    const see = el('button', 'btn ghost sm', 'Metin');
    see.onclick = () => { pre.hidden = !pre.hidden; };
    const no = el('button', 'btn ghost sm', 'Vazgeç');
    no.title = 'Panele yazılmasın (öneri olarak geri döner)';
    no.onclick = async () => { no.disabled = true; const x = await api('desc_cancel', { sids: [it.sid] }); if (x.ok) { li.remove(); say('Vazgeçildi: ' + it.sid + ' (Öneriler\'e döndü).'); counts(); } else { no.disabled = false; say(x.error || 'olmadı'); } };
    li.append(t, see, no);
    ul.appendChild(li);
  }
}
$('go').onclick = () => chrome.runtime.sendMessage({ type: 'desc-open' }, () => { void chrome.runtime.lastError; say('Services sayfası açılıyor: eklenti yazmaya başlıyor.'); });
$('cancel-all').onclick = async () => {
  if (!confirm('Panele yazılacak bütün açıklamalardan vazgeçilsin mi? Silinmezler, Öneriler\'e dönerler.')) return;
  const x = await api('desc_cancel', { all: 1 });
  say(x.ok ? x.cancelled + ' açıklamadan vazgeçildi.' : (x.error || 'olmadı'));
  loadQueue(); counts();
};

// ---- son rapor ----
async function loadReport() {
  const ul = $('report');
  ul.textContent = '';
  const { descReport: r } = await chrome.storage.local.get({ descReport: null });
  if (!r || !r.results) { $('rep-sum').textContent = 'Henüz rapor yok: panele yazma işi bitince burada servis servis görünür.'; return; }
  const ok = r.results.filter((x) => x.ok).length;
  $('rep-sum').textContent = ago(r.at) + ': ' + ok + ' servise eklendi, ' + (r.results.length - ok) + ' eklenmedi' + (r.stopped ? ' (yarıda bitirildi)' : '') + '.';
  for (const x of r.results) {
    const li = el('li', x.ok ? 'ok' : 'bad');
    const t = el('div', 't');
    t.append(el('b', '', x.sid + ' · ' + (x.name || '')), el('span', 'st', x.ok ? (x.verified ? '✓ Eklendi, panelden doğrulandı' : '✓ Eklendi') : '✗ Eklenmedi: ' + x.error));
    li.appendChild(t);
    ul.appendChild(li);
  }
}

async function counts() {
  const r = await api('desc_queue', { peek: 1 });
  if (r.ok) { $('n-queue').textContent = r.count || 0; if (r.proposed !== undefined) $('n-props').textContent = r.proposed; }
  chrome.runtime.sendMessage({ type: 'desc-refresh' }, () => void chrome.runtime.lastError);
}

counts();
view(['props', 'queue', 'report'].includes(location.hash.slice(1)) ? location.hash.slice(1) : 'props');
