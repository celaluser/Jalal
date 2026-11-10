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
    channel: 'chromium', headless: true, viewport: { width: 1300, height: 700 }, acceptDownloads: true,
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

  console.log('A1 tek pencere: başta sağ altta küçük hap; tıklayınca pencere açılır; sekmeyle araç değişir; araçların çubukları pencerenin içinde');
  for (const id of ['kt-host', 'sp-host', 'pt-host', 'pa-dock']) await p.waitForSelector('#' + id, { state: 'attached', timeout: 20000 });
  await p.waitForTimeout(800);
  const W = (fn) => p.evaluate(fn);
  const vis = () => W(() => { const r = document.getElementById('pa-dock').shadowRoot; if (r.querySelector('.w').hidden) return []; return ['kt-host', 'sp-host', 'pt-host'].filter((id) => getComputedStyle(document.getElementById(id)).display !== 'none'); });
  const dockBtn = (k) => W(new Function('return document.getElementById("pa-dock").shadowRoot.querySelector("button[data-k=' + k + ']").click()'));
  eq(await W(() => ['kt-host', 'sp-host', 'pt-host'].map((id) => document.getElementById(id).parentElement.id)), ['pa-dock', 'pa-dock', 'pa-dock'], 'üç araç pencerenin içinde');
  eq(await vis(), [], 'başta yalnız hap (pencere kapalı)');
  const pill = await W(() => { const r = document.getElementById('pa-dock').shadowRoot.querySelector('.pill').getBoundingClientRect(); return { right: Math.round(innerWidth - r.right), bottom: Math.round(innerHeight - r.bottom), left: Math.round(r.left) }; });
  ok(pill.right === 12 && pill.bottom === 12 && pill.left > 400, 'hap sağ altta (soldaki tgwatch logosuyla çakışmaz): ' + JSON.stringify(pill));
  await W(() => document.getElementById('pa-dock').shadowRoot.querySelector('.pill').click());
  await p.waitForTimeout(200);
  eq(await vis(), ['kt-host'], 'pencere açıldı: Kategori sekmesi');
  await dockBtn('pt');
  await p.waitForTimeout(200);
  eq(await vis(), ['pt-host'], 'Taşı sekmesi: yalnız onun içeriği');
  const geo = await W(() => { const r = document.getElementById('pa-dock').shadowRoot.querySelector('.w').getBoundingClientRect(); const b = document.getElementById('pt-host').shadowRoot.getElementById('bar').getBoundingClientRect(); return { w: Math.round(r.width), inside: b.left >= r.left - 1 && b.right <= r.right + 1, right: Math.round(innerWidth - r.right), bottom: Math.round(innerHeight - r.bottom) }; });
  ok(geo.inside && geo.right === 12 && geo.bottom === 12, 'araç içeriği pencerenin içinde, pencere sağ altta: ' + JSON.stringify(geo));

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
  ok(await p.evaluate(() => { const d = document.getElementById('pa-dock').shadowRoot.querySelector('.t[data-k=sp] .d'); return !d.hidden && d.classList.contains('run'); }), 'gizli araç çalışırken dokta sarı nokta');
  await p.evaluate(() => document.documentElement.removeAttribute('data-panel-arac'));

  console.log('A9 liste araçları: ara, ters, ID yapıştır; koruma listesi (KORUMALI öğe seçilemez, işlenmez)');
  {
    const R = (fn, arg) => p.evaluate(fn, arg);
    const sr = "document.getElementById('pt-host').shadowRoot";
    const selected = () => R(new Function('return [...' + sr + '.querySelectorAll("#ptList input[data-x]")].filter((c) => c.checked).map((c) => c.getAttribute("data-x")).sort()'));
    const click = (q) => R(new Function('return ' + sr + '.querySelector(' + JSON.stringify(q) + ').click()'));
    const swA = ctx.serviceWorkers()[0] || await ctx.waitForEvent('serviceworker');
    eq(await selected(), ['1132', '2452'], 'başta ikisi seçili');
    await R(new Function('const i = ' + sr + '.querySelector(".flt"); i.value = "2452"; i.dispatchEvent(new Event("input", { bubbles: true, composed: true }));'));
    eq(await R(new Function('return [...' + sr + '.querySelectorAll("#ptList .it")].filter((x) => !x.hidden).length')), 1, 'ara "2452": tek satır görünür');
    await click('[data-a=none]');
    eq(await selected(), ['1132'], 'arama açıkken "Hiçbiri" yalnız görünenlere uygulandı');
    await R(new Function('const i = ' + sr + '.querySelector(".flt"); i.value = ""; i.dispatchEvent(new Event("input", { bubbles: true, composed: true }));'));
    await click('[data-a=invert]');
    eq(await selected(), ['2452'], '"Ters": seçim tersine döndü');
    await click('[data-a=pasteids]');
    await R(new Function(sr + '.querySelector(".paste input").value = "1132, 99999";'));
    await click('[data-a=applyids]');
    eq(await selected(), ['1132'], 'ID yapıştır: yalnız 1132 seçildi');
    ok(/1 \/ 2 ID listede bulundu/.test(await R(new Function('return ' + sr + '.querySelector(".toast").textContent'))), 'bildirim: 1 / 2 bulundu');
    await click('[data-a=all]');
    await swA.evaluate(() => chrome.storage.local.set({ pa_protect: ['2452'] }));
    await p.waitForTimeout(400);
    eq(await R(new Function('const c = ' + sr + '.querySelector("#ptList input[data-x=\'2452\']"); return [c.disabled, c.checked, /KORUMALI/.test(c.closest(".it").textContent)]')), [true, false, true], 'korunan 2452: işaretsiz, kilitli, KORUMALI');
    ok(/\(1\)/.test(await R(new Function('return ' + sr + '.querySelector("[data-a=run]").textContent'))), 'çalıştır düğmesi yalnız 1 servis sayıyor');
    await swA.evaluate(() => chrome.storage.local.set({ pa_protect: [] }));
    await p.waitForTimeout(300);
  }

  console.log('A3 mini panel: üç sekme, her biri kendi aracına bağlanır');
  const sw = ctx.serviceWorkers()[0] || await ctx.waitForEvent('serviceworker');
  const extId = new URL(sw.url()).host;
  const tabId = await sw.evaluate(async () => (await chrome.tabs.query({ url: 'https://panelbaku.com/*' }))[0].id);
  const pop = await ctx.newPage();
  await pop.goto('chrome-extension://' + extId + '/popup.html?tab=' + tabId);
  for (const [t, title] of [['kt', 'Kategori Temizle'], ['sp', 'Servis Pasifle'], ['pt', 'Pasif Servis Taşı']]) {
    await pop.click('#t-' + t);
    const f = pop.frameLocator('#f-' + t);
    await f.locator('#ver').filter({ hasText: /^v\d/ }).waitFor({ state: 'attached', timeout: 10000 });
    ok(new RegExp(title).test(await f.locator('h1').textContent()), title + ' sekmesi bağlandı (' + await f.locator('#ver').textContent() + ')');
  }
  ok(await pop.frameLocator('#f-pt').locator('#auto').count() === 1, 'Pasif Servis Taşı sekmesinde otomatik mod var');
  if (process.argv[3]) { await pop.click('#t-pt'); await pop.waitForTimeout(700); await pop.setViewportSize({ width: 372, height: 600 }); await pop.screenshot({ path: process.argv[3] }); }
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
  eq(await p.evaluate(() => [document.documentElement.getAttribute('data-panel-arac'), document.getElementById('pa-dock').shadowRoot.querySelector('.t[data-k=pt] .d').hidden]), [null, true], 'iş bitti: kilit bırakıldı, dokta nokta söndü');
  await p.waitForTimeout(300);
  if (process.argv[2]) await p.screenshot({ path: process.argv[2] });
  console.log('A6 Geçmiş: biten iş mini panelde görünür; gerçek değişiklikte masaüstü bildirimi');
  const hist = await sw.evaluate(async () => (await chrome.storage.local.get({ pa_history: [] })).pa_history);
  ok(hist.length >= 1 && hist[0].tool === 'pt' && hist[0].dry === false && hist[0].done === 2 && /Bitti\. 2 taşındı/.test(hist[0].text), 'geçmişe yazıldı: ' + JSON.stringify(hist[0]).slice(0, 160));
  const notes = await sw.evaluate(async () => Object.keys(await chrome.notifications.getAll()));
  ok(notes.some((n) => n.startsWith('pa-pt-')), 'bildirim gösterildi: ' + JSON.stringify(notes));
  const pop2 = await ctx.newPage();
  await pop2.goto('chrome-extension://' + extId + '/popup.html?tab=' + tabId);
  console.log('A8 ID\'leri virgülle kopyala ve günlük (.txt): içerik betiği doğru veriyi verir; sayfadaki düğmeler var');
  const idsDone = await pop2.evaluate((t) => new Promise((r) => chrome.tabs.sendMessage(t, { pt: true, cmd: 'ids', g: 'done' }, r)), tabId);
  eq(idsDone.ids.slice().sort(), ['1132', '2452'], 'yapılan ID\'ler');
  const lg = await pop2.evaluate((t) => new Promise((r) => chrome.tabs.sendMessage(t, { pt: true, cmd: 'log' }, r)), tabId);
  ok(/^pt-gunluk-.*\.txt$/.test(lg.name) && /== Taşınan \(2\)/.test(lg.text) && /ID: (2452,1132|1132,2452)/.test(lg.text) && /== Adım günlüğü/.test(lg.text) && /== Teşhis/.test(lg.text), 'günlük metni: ' + lg.name);
  ok(await p.evaluate(() => { const r = document.getElementById('pt-host').shadowRoot; return !!r.querySelector('[data-a=copyids][data-g=done]') && !!r.querySelector('[data-a=log]'); }), 'çubukta "⧉ yapılan ID\'ler" ve "⬇ Günlük (.txt)" düğmeleri');
  const dl = p.waitForEvent('download', { timeout: 10000 });
  await p.evaluate(() => document.getElementById('pt-host').shadowRoot.querySelector('[data-a=log]').click());
  const d = await dl;
  ok(/^pt-gunluk-.*\.txt$/.test(d.suggestedFilename()), 'sayfadan indirildi: ' + d.suggestedFilename());
  await pop2.click('#t-hist');
  await pop2.waitForSelector('#h-list li');
  ok(/Pasif Servis Taşı[\s\S]*gerçek[\s\S]*2 taşındı/.test(await pop2.locator('#h-list li').first().innerText()), 'Geçmiş sekmesinde: ' + (await pop2.locator('#h-list li').first().innerText()).replace(/\s+/g, ' '));
  await pop2.selectOption('#h-tool', 'kt');
  ok(await pop2.locator('#h-list li').count() === 0 && /filtreye uyan/.test(await pop2.textContent('#h-empty')), 'araç filtresi');
  await pop2.selectOption('#h-tool', '');
  if (process.argv[4]) { await pop2.setViewportSize({ width: 372, height: 560 }); await pop2.screenshot({ path: process.argv[4] }); }
  await pop2.click('#h-clear'); await pop2.click('#h-clear');
  await pop2.waitForFunction(() => !document.querySelector('#h-list li'));
  ok(true, 'temizle (iki tık)');
  await pop2.close();

  console.log('A7 pencere başlığından sürüklenir, yeri kalır; çift tık köşeye döndürür; "–" hapa küçültür');
  await W(() => { const r = document.getElementById('pa-dock').shadowRoot; if (r.querySelector('.w').hidden) r.querySelector('.pill').click(); });
  await p.waitForTimeout(200);
  const hd = await W(() => { const r = document.getElementById('pa-dock').shadowRoot.querySelector('.hd b').getBoundingClientRect(); return { x: r.x + r.width / 2, y: r.y + r.height / 2 }; });
  await p.mouse.move(hd.x, hd.y); await p.mouse.down(); await p.mouse.move(hd.x - 400, hd.y + 0, { steps: 8 }); await p.mouse.up();
  await p.waitForTimeout(300);
  const mv = await W(() => { const r = document.getElementById('pa-dock').shadowRoot.querySelector('.w').getBoundingClientRect(); return { right: Math.round(innerWidth - r.right), saved: localStorage.getItem('pa_pos') }; });
  ok(mv.right > 300 && mv.saved, 'pencere sola taşındı ve yeri kaydedildi: ' + JSON.stringify(mv));
  await W(() => document.getElementById('pa-dock').shadowRoot.querySelector('.hd').dispatchEvent(new MouseEvent('dblclick', { bubbles: true })));
  await p.waitForTimeout(200);
  ok(await W(() => { const r = document.getElementById('pa-dock').shadowRoot.querySelector('.w').getBoundingClientRect(); return Math.round(innerWidth - r.right) === 12 && !localStorage.getItem('pa_pos'); }), 'çift tık: köşeye döndü');
  await W(() => document.getElementById('pa-dock').shadowRoot.querySelector('[data-x=min]').click());
  await p.waitForTimeout(200);
  eq(await vis(), [], '"–": pencere kapandı, hap kaldı');

  eq(errors, [], 'JS hatası yok');
  await ctx.close();
  console.log('\n' + (fails ? '✗ ' + fails + ' başarısız' : '✓ hepsi geçti') + '  (' + ((Date.now() - t0) / 1000).toFixed(0) + ' sn)');
  process.exit(fails ? 1 : 0);
})().catch((e) => { console.error(e); process.exit(2); });
