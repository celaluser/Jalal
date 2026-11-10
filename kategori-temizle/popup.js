// Mini panel: komutları açık sekmedeki içerik script'ine gönderir; durumu 0,7 sn'de bir okur.
const $ = (id) => document.getElementById(id);
const esc = (s) => String(s).replace(/[&<>"]/g, (c) => ({ '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;' }[c]));
let tabId = null;
let last = '';

async function findTab() {
  const q = new URLSearchParams(location.search).get('tab'); // yalnızca test için
  if (q) return Number(q);
  const [t] = await chrome.tabs.query({ active: true, currentWindow: true });
  return t ? t.id : null;
}
function send(msg) {
  return new Promise((resolve) => {
    if (tabId == null) return resolve(null);
    chrome.tabs.sendMessage(tabId, Object.assign({ kt: true }, msg), (r) => {
      void chrome.runtime.lastError; // içerik script'i yok (başka sayfa)
      resolve(r || null);
    });
  });
}

function paint(s) {
  $('off').hidden = !!(s && s.onPage);
  $('ui').hidden = !(s && s.onPage);
  if (!s || !s.onPage) return;
  const mv = s.settings.mode === 'move';
  $('ver').textContent = 'v' + s.version;
  $('mDel').className = 'tab' + (mv ? '' : ' on');
  $('mMove').className = 'tab' + (mv ? ' on' : '');
  $('mDel').disabled = $('mMove').disabled = s.running;
  if (document.activeElement !== $('max')) $('max').value = s.settings.max;
  $('dry').checked = s.settings.dryRun;
  $('moveDelete').checked = s.settings.moveDelete;
  $('mdRow').hidden = !mv;
  $('scan').textContent = mv ? 'Dolu pasif kategorileri bul' : 'Boş pasif kategorileri bul';
  $('scan').disabled = s.running;
  const n = s.phase === 'ready' ? Math.min(s.work, s.settings.max) : 0;
  $('run').textContent = (s.settings.dryRun ? 'Dene' : mv ? (s.settings.moveDelete ? 'Taşı + sil' : 'Taşı') : 'Sil') + ' (' + n + ')';
  $('run').disabled = s.running || s.phase !== 'ready' || !s.work;
  $('stop').disabled = !s.running;
  $('stats').textContent = s.stats ? `${s.stats.kategori} kategori · ${s.stats.aktif} aktif · ${s.stats.pasif} pasif · ` + (mv ? `${s.stats.pasifDolu} dolu pasif (${s.stats.servis} servis)` : `${s.stats.bos} boş pasif`) : '';
  const lst = mv ? s.fulls : s.candidates;
  const show = s.phase === 'ready' && lst.length;
  $('list').hidden = !show; $('selrow').hidden = !show;
  $('listTitle').textContent = mv ? 'Taşınacak' + (s.settings.moveDelete ? ' ve silinecek' : '') : 'Silinecek';
  const html = lst.map((x) => '<label><input type="checkbox" data-k="' + esc(x.key) + '"' + (x.off ? '' : ' checked') + (s.running || x.prot ? ' disabled' : '') + '> <span>' + esc(x.name) + (x.prot ? ' <b style="color:var(--ok);font-size:10.5px">KORUMALI</b>' : '') + '</span>' + (mv ? ' <small>(' + x.count + ')</small>' : '') + '</label>').join('');
  if (html !== last) { $('list').innerHTML = html; last = html; applyFlt(); }
  $('run').classList.toggle('real', !s.settings.dryRun);
  $('st').textContent = s.status;
  $('st').className = /^⚠/.test(s.status) ? 'warn' : '';
  const r = s.res;
  const sec = (a, t) => a.length ? '<div class="gh"><b>' + t + ' (' + a.length + ')</b></div>' + a.map((x) => '<div>' + esc(x.name) + ' <small>' + esc(x.reason || '') + '</small></div>').join('') : '';
  const rh = sec(r.moved, 'Taşınan') + sec(r.deleted, 'Silinen') + sec(r.dry, 'Deneme') + sec(r.skipped, 'Atlanan') + sec(r.errors, 'Hata');
  $('res').hidden = !rh; $('res').innerHTML = rh;
  $('resCard').hidden = !rh;
  const selN = ((typeof lst !== 'undefined' && lst) || s.targets || s.items || []).filter((x) => !x.off).length; const allN = ((typeof lst !== 'undefined' && lst) || s.targets || s.items || []).length; if ($('selCount')) $('selCount').textContent = allN ? selN + '/' + allN : '';
}

async function tick() { paint(await send({ cmd: 'state' })); }

// ---- ID kopyala ve günlük indir (içerik betiğinden alınır) ----
const flashMsg = (t) => { $('msg').textContent = t; clearTimeout(flashMsg.h); flashMsg.h = setTimeout(() => { $('msg').textContent = ''; }, 2600); };
async function copyIdsFrom(g, what) {
  const r = await send({ cmd: 'ids', g });
  const ids = (r && r.ids) || [];
  if (!ids.length) return flashMsg('Kopyalanacak ID yok');
  try { await navigator.clipboard.writeText(ids.join(',')); flashMsg('✓ ' + ids.length + ' ' + what + ' ID kopyalandı'); } catch (e) { flashMsg('⚠ Panoya kopyalanamadı'); }
}
$('cpSel').onclick = () => copyIdsFrom('sel', 'seçili');
$('cpDone').onclick = () => copyIdsFrom('done', 'yapılan');
$('cpErr').onclick = () => copyIdsFrom('errors', 'hatalı');
$('log').onclick = async () => {
  const r = await send({ cmd: 'log' });
  if (!r || !r.text) return flashMsg('⚠ Günlük alınamadı (sayfayı yenile)');
  const url = URL.createObjectURL(new Blob(['﻿' + r.text], { type: 'text/plain;charset=utf-8' }));
  const a = document.createElement('a'); a.href = url; a.download = r.name || 'gunluk.txt'; document.body.appendChild(a); a.click(); a.remove();
  setTimeout(() => URL.revokeObjectURL(url), 5000);
  flashMsg('⬇ ' + (r.name || 'gunluk.txt') + ' indirildi');
};
// ---- liste araçları: ara (yalnız görünümü süzer), ters çevir, ID yapıştırarak seç ----
function applyFlt() {
  const q = $('flt').value.trim().toLowerCase();
  for (const l of $('list').querySelectorAll('label')) l.hidden = !!q && !l.textContent.toLowerCase().includes(q);
}
$('flt').addEventListener('input', applyFlt);
const visBoxes = () => [...$('list').querySelectorAll('label:not([hidden]) input[data-k]')].filter((c) => !c.disabled);
$('inv').onclick = async () => { let st = null; for (const c of visBoxes()) st = await send({ cmd: 'exclude', key: c.getAttribute('data-k'), on: c.checked }); if (st) paint(st); };
$('pasteBtn').onclick = () => { $('pasteRow').hidden = !$('pasteRow').hidden; if (!$('pasteRow').hidden) $('pasteIn').focus(); };
async function pasteGo() {
  const want = new Set($('pasteIn').value.match(/\d{2,}/g) || []);
  if (!want.size) return flashMsg('ID bulunamadı: virgülle ya da boşlukla ayır');
  let hit = 0; let st = null;
  for (const c of $('list').querySelectorAll('input[data-k]')) {
    if (c.disabled) continue;
    const k = c.getAttribute('data-k');
    const id = String(k).replace(/\D+/g, '') || (c.closest('label').textContent.match(/\d{2,}/) || [''])[0];
    const on = want.has(id); if (on) hit++;
    st = await send({ cmd: 'exclude', key: k, on: !on });
  }
  if (st) paint(st);
  $('pasteRow').hidden = true; $('pasteIn').value = '';
  flashMsg(hit + ' / ' + want.size + ' ID listede bulundu ve seçildi');
}
$('pasteGo').onclick = pasteGo;
$('pasteIn').addEventListener('keydown', (e) => { if (e.key === 'Enter') pasteGo(); });
(async () => {
  tabId = await findTab();
  $('mDel').onclick = async () => paint(await send({ cmd: 'settings', patch: { mode: 'delete' } }));
  $('mMove').onclick = async () => paint(await send({ cmd: 'settings', patch: { mode: 'move' } }));
  $('scan').onclick = async () => paint(await send({ cmd: 'scan' }));
  $('run').onclick = async () => paint(await send({ cmd: 'run' }));
  $('stop').onclick = async () => paint(await send({ cmd: 'stop' }));
  $('all').onclick = async () => paint(await send({ cmd: 'exclude', all: false }));
  $('none').onclick = async () => paint(await send({ cmd: 'exclude', all: true }));
  $('list').onchange = async (ev) => { const k = ev.target.getAttribute('data-k'); if (k) paint(await send({ cmd: 'exclude', key: k, on: !ev.target.checked })); };
  $('dry').onchange = async () => paint(await send({ cmd: 'settings', patch: { dryRun: $('dry').checked } }));
  $('moveDelete').onchange = async () => paint(await send({ cmd: 'settings', patch: { moveDelete: $('moveDelete').checked } }));
  $('max').onchange = async () => paint(await send({ cmd: 'settings', patch: { max: $('max').value } }));
  $('diag').onclick = async () => {
    const r = await send({ cmd: 'diag' });
    let ok = false;
    try { await navigator.clipboard.writeText(r.text); ok = true; } catch (e) { /* yok */ }
    $('st').textContent = ok ? 'Teşhis panoya kopyalandı; sohbete yapıştır.' : '⚠ Panoya kopyalanamadı.';
  };
  await tick();
  setInterval(tick, 700);
})();
