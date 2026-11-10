// Panel Araçları (birleşik eklenti) — üç aracın birlikte yüklendiği gerçek eklentiyle duman testi.
// Çalıştır: node run-tests.js   (önce: python3 ../build-panel-araclari.py)
const { chromium } = require('/home/user/Jalal/servis-tasi-test/node_modules/playwright');
const fs = require('fs');
const os = require('os');
const path = require('path');
const crypto = require('crypto');

const EXT = path.resolve(__dirname, '../panel-araclari');
const MOCK = path.resolve(__dirname, '../servis-tasi-test');
let fails = 0;
const ok = (c, m) => { console.log((c ? '  ✓ ' : '  ✗ ') + m); if (!c) fails++; };
const eq = (a, b, m) => { const A = JSON.stringify(a), B = JSON.stringify(b); ok(A === B, m + (A === B ? '' : '\n      beklenen ' + B + '\n      gelen    ' + A)); };
function cats() {
  const S = (id, on) => ({ id, name: 'Servis ' + id, on });
  return [
    { id: 1, name: 'Snapchat Xidmətləri', active: true, svcs: [S(2452, false), S(1130, true), S(1132, false)] },
    { id: 2, name: 'TikTok Pasif Kategori', active: false, svcs: [S(4001, false)] },
    { id: 99, name: 'Not Assigned', active: true, svcs: [S(9001, true), S(9002, false)] },
  ];
}
(async () => {
  const t0 = Date.now();
  const dir = fs.mkdtempSync(path.join(os.tmpdir(), 'pa-'));
  const ctx = await chromium.launchPersistentContext(dir, {
    channel: 'chromium', headless: true, viewport: { width: 1300, height: 700 },
    args: ['--disable-extensions-except=' + EXT, '--load-extension=' + EXT, '--no-sandbox'],
  });
  const p = await ctx.newPage();
  const errors = []; p.on('pageerror', (e) => errors.push(String(e)));
  await p.addInitScript((c) => { window.__cfg = c; }, { cats: cats() });
  await p.addInitScript(() => { try { localStorage.clear(); } catch (e) { /* yok */ } });
  await p.route('https://panelbaku.com/**', async (route) => {
    const u = new URL(route.request().url());
    if (u.pathname === '/_react.js') return route.fulfill({ contentType: 'text/javascript', body: fs.readFileSync(path.join(MOCK, 'node_modules/react/umd/react.development.js')) });
    if (u.pathname === '/_react-dom.js') return route.fulfill({ contentType: 'text/javascript', body: fs.readFileSync(path.join(MOCK, 'node_modules/react-dom/umd/react-dom.development.js')) });
    if (u.pathname === '/admin/services') return route.fulfill({ contentType: 'text/html', body: fs.readFileSync(path.join(MOCK, 'mock.html')) });
    return route.fulfill({ contentType: 'text/html', body: '<body>x</body>' });
  });
  await p.goto('https://panelbaku.com/admin/services');

  console.log('A1 tek dok: sağ altta "Panel Araçları"; başta hiçbir çubuk görünmez; tıklanan araç açılır, diğerleri gizli');
  for (const id of ['kt-host', 'sp-host', 'pt-host', 'pa-dock']) await p.waitForSelector('#' + id, { state: 'attached', timeout: 20000 });
  await p.waitForTimeout(800);
  const vis = () => p.evaluate(() => ['kt-host', 'sp-host', 'pt-host'].filter((id) => getComputedStyle(document.getElementById(id)).display !== 'none'));
  const dockBtn = (k) => p.evaluate((x) => document.getElementById('pa-dock').shadowRoot.querySelector('button[data-k=' + x + ']').click(), k);
  eq(await vis(), [], 'başta üç çubuk da gizli');
  const dr = await p.evaluate(() => { const r = document.getElementById('pa-dock').shadowRoot.querySelector('.dock').getBoundingClientRect(); return { right: Math.round(innerWidth - r.right), bottom: Math.round(innerHeight - r.bottom), left: Math.round(r.left) }; });
  ok(dr.right === 12 && dr.bottom === 12 && dr.left > 400, 'dok sağ altta (soldaki tgwatch logosuyla çakışmaz): ' + JSON.stringify(dr));
  await dockBtn('pt');
  await p.waitForTimeout(200);
  eq(await vis(), ['pt-host'], 'Taşı seçildi: yalnız onun çubuğu');
  const br = await p.evaluate(() => { const b = document.getElementById('pt-host').shadowRoot.getElementById('bar').getBoundingClientRect(); const d = document.getElementById('pa-dock').shadowRoot.querySelector('.dock').getBoundingClientRect(); return { barBottom: Math.round(b.bottom), dockTop: Math.round(d.top), right: Math.round(innerWidth - b.right) }; });
  ok(br.barBottom <= br.dockTop && br.right === 12, 'çubuk dokun üstünde, sağda: ' + JSON.stringify(br));
  await dockBtn('kt');
  await p.waitForTimeout(200);
  eq(await vis(), ['kt-host'], 'Kategori seçildi: Taşı gizlendi');
  await dockBtn('kt');
  await p.waitForTimeout(200);
  eq(await vis(), [], 'aynı düğmeye tekrar: kapandı');
  await dockBtn('pt');
  await p.waitForTimeout(200);

  console.log('A2 araçlar arası kilit: biri çalışırken diğeri başlamaz');
  await p.evaluate(() => document.documentElement.setAttribute('data-panel-arac', 'kt'));
  await p.evaluate(() => document.getElementById('pt-host').shadowRoot.querySelector('[data-a=scan]').click());
  await p.waitForFunction(() => /Kategori Temizle.*çalışıyor/.test(document.getElementById('pt-host').shadowRoot.getElementById('st').textContent), null, { timeout: 8000 });
  ok(true, 'Pasif Servis Taşı: "Kategori Temizle çalışıyor: önce o bitsin"');
  await p.evaluate(() => document.documentElement.removeAttribute('data-panel-arac'));
  await p.evaluate(() => document.getElementById('pt-host').shadowRoot.querySelector('[data-a=scan]').click());
  await p.waitForFunction(() => /hazır|yok|⚠/.test(document.getElementById('pt-host').shadowRoot.getElementById('st').textContent), null, { timeout: 60000 });
  ok(/2 pasif servis/.test(await p.evaluate(() => document.getElementById('pt-host').shadowRoot.getElementById('st').textContent)), 'kilit kalkınca tarama çalıştı');
  eq(await p.evaluate(() => document.documentElement.getAttribute('data-panel-arac')), null, 'tarama bitince kilit bırakıldı');
  await p.evaluate(() => document.documentElement.setAttribute('data-panel-arac', 'sp'));
  await p.waitForTimeout(1200);
  ok(await p.evaluate(() => { const d = document.getElementById('pa-dock').shadowRoot.querySelector('button[data-k=sp] .d'); return !d.hidden && d.classList.contains('run'); }), 'gizli araç çalışırken dokta sarı nokta');
  await p.evaluate(() => document.documentElement.removeAttribute('data-panel-arac'));

  console.log('A3 mini panel: üç sekme, her biri kendi aracına bağlanır');
  const sw = ctx.serviceWorkers()[0] || await ctx.waitForEvent('serviceworker');
  const extId = new URL(sw.url()).host;
  const tabId = await sw.evaluate(async () => (await chrome.tabs.query({ url: 'https://panelbaku.com/*' }))[0].id);
  const pop = await ctx.newPage();
  await pop.goto('chrome-extension://' + extId + '/popup.html?tab=' + tabId);
  for (const [t, title] of [['kt', 'Kategori Temizle'], ['sp', 'Servis Pasifle'], ['pt', 'Pasif Servis Taşı']]) {
    await pop.click('#t-' + t);
    const f = pop.frameLocator('#f-' + t);
    await f.locator('#ver').filter({ hasText: /^v\d/ }).waitFor({ timeout: 10000 });
    ok(new RegExp(title).test(await f.locator('h1').innerText()), title + ' sekmesi bağlandı (' + await f.locator('#ver').innerText() + ')');
  }
  ok(await pop.frameLocator('#f-pt').locator('#auto').count() === 1, 'Pasif Servis Taşı sekmesinde otomatik mod var');
  await pop.close();

  console.log('A4 ortak arka plan: iki aracın alarmı art arda gelirse sayfa bir kez yenilenir');
  await sw.evaluate(async (id) => { await chrome.alarms.create('sp-auto:' + id, { delayInMinutes: 60 }); await chrome.alarms.create('pt-auto:' + id, { delayInMinutes: 60 }); }, tabId);
  await p.evaluate(() => {
    sessionStorage.setItem('sp_auto', JSON.stringify({ on: true, real: false, every: 5, history: [] }));
    sessionStorage.setItem('pt_auto', JSON.stringify({ on: true, real: false, every: 5, history: [] }));
  });
  await sw.evaluate((id) => self.__forgetReload(id), tabId);
  const r1 = await sw.evaluate((n) => self.__autoTick(n), 'sp-auto:' + tabId);
  const r2 = await sw.evaluate((n) => self.__autoTick(n), 'pt-auto:' + tabId);
  eq([r1, r2], ['yenilendi', 'atla-yeni-yenilendi'], 'ilki yeniledi, ikincisi atladı');
  await p.waitForTimeout(1500);
  await p.evaluate(() => { sessionStorage.removeItem('sp_auto'); sessionStorage.removeItem('pt_auto'); });
  await sw.evaluate(async () => chrome.alarms.clearAll());

  console.log('A5 birleşik eklentide gerçek iş: Pasif Servis Taşı aktif kategorideki 2 pasif servisi Not Assigned\'a taşır');
  await p.evaluate(() => { const r = document.getElementById('pt-host').shadowRoot; const d = r.querySelector('[data-s=dry]'); if (d.checked) d.click(); });
  await p.waitForTimeout(200);
  await p.evaluate(() => document.getElementById('pt-host').shadowRoot.querySelector('[data-a=scan]').click());
  await p.waitForFunction(() => /hazır|yok|⚠/.test(document.getElementById('pt-host').shadowRoot.getElementById('st').textContent), null, { timeout: 60000 });
  await p.evaluate(() => document.getElementById('pt-host').shadowRoot.querySelector('[data-a=run]').click());
  await p.waitForFunction(() => /Bitti|Durduruldu\.|⚠/.test(document.getElementById('pt-host').shadowRoot.getElementById('st').textContent), null, { timeout: 120000 });
  const srv = await p.evaluate(() => JSON.parse(JSON.stringify(window.__server)));
  eq(srv.moves.map((m) => m.id).sort(), [1132, 2452], 'taşınan: 2452, 1132'); eq(srv.violations, [], 'ihlal yok');
  await p.waitForTimeout(1300);
  eq(await p.evaluate(() => [document.documentElement.getAttribute('data-panel-arac'), document.getElementById('pa-dock').shadowRoot.querySelector('button[data-k=pt] .d').hidden]), [null, true], 'iş bitti: kilit bırakıldı, dokta nokta söndü');
  await p.evaluate(() => document.getElementById('pa-dock').shadowRoot.querySelector('button[data-k=pt]').click());
  await p.waitForTimeout(300);
  if (process.argv[2]) await p.screenshot({ path: process.argv[2] });
  eq(errors, [], 'JS hatası yok');
  await ctx.close();
  console.log('\n' + (fails ? '✗ ' + fails + ' başarısız' : '✓ hepsi geçti') + '  (' + ((Date.now() - t0) / 1000).toFixed(0) + ' sn)');
  process.exit(fails ? 1 : 0);
})().catch((e) => { console.error(e); process.exit(2); });
