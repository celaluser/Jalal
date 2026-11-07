// Mini panel: komutları açık sekmedeki içerik script'ine gönderir; durumu 0,7 sn'de bir okur.
const $ = (id) => document.getElementById(id);
const esc = (s) => String(s).replace(/[&<>"]/g, (c) => ({ '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;' }[c]));
let tabId = null;

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
  $('ver').textContent = 'v' + s.version;
  const dry = s.settings.dryRun;
  if (document.activeElement !== $('max')) $('max').value = s.settings.max;
  $('dry').checked = dry;
  $('scan').disabled = s.running;
  const n = s.phase === 'ready' ? Math.min(s.candidates.length, s.settings.max) : 0;
  $('run').textContent = (dry ? 'Dene' : 'Sil') + ' (' + n + ')';
  $('run').disabled = s.running || s.phase !== 'ready' || !s.candidates.length;
  $('stop').disabled = !s.running;
  $('stats').textContent = s.stats ? `${s.stats.kategori} kategori · ${s.stats.aktif} aktif · ${s.stats.pasif} pasif (${s.stats.pasifDolu} dolu) · ${s.stats.bos} boş pasif` : '';
  $('list').hidden = !s.candidates.length;
  $('list').innerHTML = s.candidates.map((c) => '<div>' + esc(c) + '</div>').join('');
  $('st').textContent = s.status;
  $('st').className = /^⚠/.test(s.status) ? 'warn' : '';
  const r = s.res;
  const sec = (a, t) => a.length ? '<div><b>' + t + ' (' + a.length + ')</b></div>' + a.map((x) => '<div>' + esc(x.name) + ' <small>' + esc(x.reason || '') + '</small></div>').join('') : '';
  const html = sec(r.deleted, 'Silinen') + sec(r.dry, 'Deneme') + sec(r.skipped, 'Atlanan') + sec(r.errors, 'Hata');
  $('res').hidden = !html; $('res').innerHTML = html;
  $('diag').hidden = !s.diagNeeded;
}

async function tick() { paint(await send({ cmd: 'state' })); }

(async () => {
  tabId = await findTab();
  $('scan').onclick = async () => paint(await send({ cmd: 'scan' }));
  $('run').onclick = async () => paint(await send({ cmd: 'run' }));
  $('stop').onclick = async () => paint(await send({ cmd: 'stop' }));
  $('dry').onchange = async () => paint(await send({ cmd: 'settings', patch: { dryRun: $('dry').checked } }));
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
