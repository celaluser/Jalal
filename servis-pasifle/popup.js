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
    chrome.tabs.sendMessage(tabId, Object.assign({ sp: true }, msg), (r) => { void chrome.runtime.lastError; resolve(r || null); });
  });
}
function paint(s) {
  $('off').hidden = !!(s && s.onPage);
  $('ui').hidden = !(s && s.onPage);
  if (!s || !s.onPage) return;
  $('ver').textContent = 'v' + s.version;
  if (document.activeElement !== $('max')) $('max').value = s.settings.max;
  $('dry').checked = s.settings.dryRun;
  $('scan').disabled = s.running;
  const n = s.phase === 'ready' ? Math.min(s.work, s.settings.max) : 0;
  $('run').textContent = (s.settings.dryRun ? 'Dene' : 'Pasife al') + ' (' + n + ')';
  $('run').disabled = s.running || s.phase !== 'ready' || !s.work;
  $('stop').disabled = !s.running;
  $('stats').textContent = s.stats ? `Not Assigned: ${s.stats.okunan} servis · ${s.stats.aktif} aktif · ${s.stats.pasif} pasif` + (s.stats.belirsiz ? ` · ${s.stats.belirsiz} belirsiz` : '') : '';
  const show = s.phase === 'ready' && s.targets.length;
  $('list').hidden = !show; $('selrow').hidden = !show;
  const html = s.targets.map((x) => '<label><input type="checkbox" data-k="' + esc(x.key) + '"' + (x.off ? '' : ' checked') + (s.running ? ' disabled' : '') + '> <span>' + esc(x.name) + '</span></label>').join('');
  if (html !== last) { $('list').innerHTML = html; last = html; }
  const a = s.auto;
  const hm = (t) => (t ? new Date(t).toTimeString().slice(0, 5) : '--:--');
  $('auto').checked = !!(a && a.on);
  if (document.activeElement !== $('every')) $('every').value = (a && a.every) || s.settings.every || 5;
  $('autoInfo').className = a && !a.on && a.off && a.off !== 'elle kapatıldı' ? 'warn' : 'auto';
  $('autoInfo').textContent = a && a.on
    ? '● Otomatik AÇIK (' + (a.real ? 'GERÇEK' : 'DENEME') + ') · sonraki: ' + hm(a.next) + (a.last ? ' · son ' + hm(a.last.at) + ': ' + (a.last.bulunan || 0) + ' bulundu, ' + (a.real ? (a.last.yapilan || 0) + ' pasife alındı' : (a.last.deneme || 0) + ' deneme') : '') + (a.skip && (!a.last || a.skip.at > a.last.at) ? ' · son tur atlandı: ' + a.skip.why : '')
    : (a && a.off && a.off !== 'elle kapatıldı' ? a.off : '');
  $('st').textContent = s.status;
  $('st').className = /^⚠/.test(s.status) ? 'warn' : '';
  const r = s.res;
  const sec = (a, t) => a.length ? '<div><b>' + t + ' (' + a.length + ')</b></div>' + a.map((x) => '<div>' + esc(x.name) + ' <small>' + esc(x.reason || '') + '</small></div>').join('') : '';
  const rh = sec(r.done, 'Pasife alınan') + sec(r.dry, 'Deneme') + sec(r.skipped, 'Atlanan') + sec(r.errors, 'Hata');
  $('res').hidden = !rh; $('res').innerHTML = rh;
}
async function tick() { paint(await send({ cmd: 'state' })); }
(async () => {
  tabId = await findTab();
  $('scan').onclick = async () => paint(await send({ cmd: 'scan' }));
  $('run').onclick = async () => paint(await send({ cmd: 'run' }));
  $('stop').onclick = async () => paint(await send({ cmd: 'stop' }));
  $('all').onclick = async () => paint(await send({ cmd: 'exclude', all: false }));
  $('none').onclick = async () => paint(await send({ cmd: 'exclude', all: true }));
  $('list').onchange = async (ev) => { const k = ev.target.getAttribute('data-k'); if (k) paint(await send({ cmd: 'exclude', key: k, on: !ev.target.checked })); };
  $('dry').onchange = async () => paint(await send({ cmd: 'settings', patch: { dryRun: $('dry').checked } }));
  $('max').onchange = async () => paint(await send({ cmd: 'settings', patch: { max: $('max').value } }));
  $('auto').onchange = async () => paint(await send({ cmd: 'auto', on: $('auto').checked, every: $('every').value }));
  $('every').onchange = async () => paint(await send({ cmd: 'auto', every: $('every').value }));
  $('diag').onclick = async () => {
    const r = await send({ cmd: 'diag' });
    let ok = false;
    try { await navigator.clipboard.writeText(r.text); ok = true; } catch (e) { /* yok */ }
    $('st').textContent = ok ? 'Teşhis panoya kopyalandı; sohbete yapıştır.' : '⚠ Panoya kopyalanamadı.';
  };
  await tick();
  setInterval(tick, 700);
})();
