// Servis Pasifle — gerçek eklenti + React taklit paneli ile uçtan uca testler.
// Çalıştır: node run-tests.js        (tek test: node run-tests.js S3)
// Arka plan sekmesi (gerçek pencere): HEADED=1 xvfb-run -a node run-tests.js S12
const { chromium } = require('playwright');
const fs = require('fs');
const os = require('os');
const path = require('path');
const crypto = require('crypto');

const EXT = path.resolve(__dirname, '../servis-pasifle');
const URL = 'https://panelbaku.com/admin/services';
let fails = 0;
const want = (t) => !process.argv[2] || process.argv[2] === t;
const ok = (c, msg) => { console.log((c ? '  ✓ ' : '  ✗ ') + msg); if (!c) fails++; };
const eq = (a, b, msg) => { const A = JSON.stringify(a), B = JSON.stringify(b); ok(A === B, msg + (A === B ? '' : '\n      beklenen ' + B + '\n      gelen    ' + A)); };

// --- veri: Not Assigned en sonda; başka kategorilerde de aktif servis var (dokunulmamalı)
function cats() {
  const S = (id, on) => ({ id, name: 'Servis ' + id + ' | Test', on });
  const list = [
    { id: 1, name: 'Instagram Followers', active: true, svcs: [S(101, true), S(102, true), S(103, false)] },
    { id: 2, name: 'TikTok Likes', active: false, svcs: [S(201, true), S(202, false)] }, // pasif kategoride AKTİF servis: dokunulmaz
  ];
  for (let i = 3; i <= 14; i++) list.push({ id: i, name: 'Kategori ' + i, active: i % 2 === 0, svcs: [S(i * 100 + 1, i % 3 === 0), S(i * 100 + 2, false)] });
  const na = [];
  for (let k = 1; k <= 12; k++) na.push(S(9000 + k, [1, 3, 4, 7, 10, 12].includes(k)));
  list.push({ id: 99, name: 'Not Assigned', active: true, svcs: na });
  return list;
}
const ON = [9001, 9003, 9004, 9007, 9010, 9012];
const OTHER_ON = () => cats().filter((c) => c.name !== 'Not Assigned').flatMap((c) => c.svcs.filter((s) => s.on).map((s) => s.id));

async function newPage(ctx, cfg) {
  const page = await ctx.newPage();
  await page.addInitScript((c) => { window.__cfg = c; }, Object.assign({ cats: cats() }, cfg));
  await page.addInitScript(() => { try { localStorage.clear(); } catch (e) { /* yok */ } });
  await page.route('https://panelbaku.com/**', async (route) => {
    const u = new globalThis.URL(route.request().url());
    if (u.pathname === '/_react.js') return route.fulfill({ contentType: 'text/javascript', body: fs.readFileSync(path.join(__dirname, 'node_modules/react/umd/react.development.js')) });
    if (u.pathname === '/_react-dom.js') return route.fulfill({ contentType: 'text/javascript', body: fs.readFileSync(path.join(__dirname, 'node_modules/react-dom/umd/react-dom.development.js')) });
    if (u.pathname === '/admin/services') return route.fulfill({ contentType: 'text/html', body: fs.readFileSync(path.join(__dirname, 'mock.html')) });
    return route.fulfill({ contentType: 'text/html', body: '<body>x</body>' });
  });
  const errors = [];
  page.on('pageerror', (er) => errors.push(String(er)));
  page.__errors = errors;
  await page.goto(URL);
  await page.waitForSelector('#sp-host', { state: 'attached' });
  return page;
}
const bar = (page, sel) => page.locator('#sp-host >> ' + sel);
const status = (page) => bar(page, '#st').innerText();
const stText = () => document.querySelector('#sp-host').shadowRoot.querySelector('#st').textContent;
async function scan(page) {
  await bar(page, '[data-a=scan]').click();
  await page.waitForFunction((f) => /bulundu|yok|⚠/.test(eval('(' + f + ')')()), stText.toString(), { timeout: 90000, polling: 200 });
}
const done = (page) => page.waitForFunction((f) => /Bitti|Durduruldu\./.test(eval('(' + f + ')')()), stText.toString(), { timeout: 180000, polling: 200 })
  .catch(async (e) => { console.log('--- ZAMAN AŞIMI:\n' + await bar(page, '#bar').innerText().catch(() => '?')); throw e; });
const server = (page) => page.evaluate(() => JSON.parse(JSON.stringify(window.__server)));
const setDry = async (page, v) => { const cb = bar(page, '[data-s=dry]'); if ((await cb.isChecked()) !== v) await cb.click(); };
const targets = async (page) => (await bar(page, '#spList .it').allInnerTexts()).map((x) => parseInt(x.trim(), 10));
const resText = (page) => bar(page, '#spRes').innerText().catch(() => '');
const onState = (page) => page.evaluate(() => Object.fromEntries(window.__server.cats.flatMap((c) => c.svcs.map((s) => [s.id, s.on]))));

(async () => {
  const dir = fs.mkdtempSync(path.join(os.tmpdir(), 'sp-'));
  const ctx = await chromium.launchPersistentContext(dir, {
    channel: 'chromium', headless: !process.env.HEADED, viewport: { width: 1100, height: 560 },
    args: ['--disable-extensions-except=' + EXT, '--load-extension=' + EXT, '--no-sandbox'],
    ignoreDefaultArgs: process.env.HEADED ? ['--disable-background-timer-throttling', '--disable-backgrounding-occluded-windows', '--disable-renderer-backgrounding'] : [],
  });
  const t0 = Date.now();

  console.log('S1 tarama: yalnızca Not Assigned\'daki AKTİF servisler listelenir; hiçbir şeye basılmaz');
  if (want('S1')) {
    const p = await newPage(ctx, {});
    await scan(p);
    eq(await targets(p), ON, 'aday listesi (sırayla)');
    ok(/12 servis · 6 aktif · 6 pasif/.test(await bar(p, 'small.sum').innerText()), 'özet: ' + await bar(p, 'small.sum').innerText());
    const s = await server(p);
    eq(s.menuOpens, [], 'servis menüsü açılmadı'); eq(s.disabled, [], 'pasife alınan yok');
    ok(/Pasife al|Dene/.test(await bar(p, '[data-a=run]').innerText()) && /\(6\)/.test(await bar(p, '[data-a=run]').innerText()), 'düğme: Dene (6)');
    ok(p.__errors.length === 0, 'sayfada JS hatası yok ' + p.__errors.join('|'));
    await p.close();
  }

  console.log('S2 deneme modu (varsayılan): her servisin menüsü açılır, "Disable service"e basılmaz');
  if (want('S2')) {
    const p = await newPage(ctx, {});
    ok(await bar(p, '[data-s=dry]').isChecked(), 'deneme modu varsayılan açık');
    await scan(p);
    await bar(p, '[data-a=run]').click(); await done(p);
    const s = await server(p);
    eq(s.menuOpens, ON, 'yalnızca hedeflerin menüsü açıldı');
    eq(s.disabled, [], 'pasife alınan yok'); eq([s.deletes, s.enables, s.hides], [0, 0, 0], 'Delete/Enable/Hide yok');
    ok(/6 deneme/.test(await status(p)), 'özet: ' + await status(p));
    await p.close();
  }

  console.log('S3 gerçek: Not Assigned\'daki aktiflerin hepsi pasif; başka kategorilerdeki aktiflere dokunulmaz');
  if (want('S3')) {
    const p = await newPage(ctx, {});
    await scan(p); await setDry(p, false);
    ok(/^Pasife al \(6\)/.test(await bar(p, '[data-a=run]').innerText()), 'düğme: Pasife al (6)');
    await bar(p, '[data-a=run]').click(); await done(p);
    const s = await server(p);
    eq(s.disabled, ON, 'pasife alınanlar (sırayla)');
    eq(s.violations, [], 'ihlal yok (Not Assigned dışı / zaten pasif)');
    eq([s.deletes, s.enables, s.hides], [0, 0, 0], '"Delete service" / "Enable service" / "Hide service" hiç basılmadı');
    eq(await p.evaluate(() => window.__onCount('Not Assigned')), 0, 'Not Assigned\'da aktif kalmadı');
    const st = await onState(p);
    ok(OTHER_ON().every((id) => st[id] === true), 'diğer kategorilerdeki aktif servisler aktif kaldı (' + OTHER_ON().join(',') + ')');
    ok(/6 pasife alındı, 0 deneme, 0 atlandı, 0 hata/.test(await status(p)), 'özet: ' + await status(p));
    ok(p.__errors.length === 0, 'JS hatası yok');
    await p.close();
  }

  console.log('S4 sunucu hatası: o servis "hata", sıradakine geçilir');
  if (want('S4')) {
    const p = await newPage(ctx, { disableFail: [9004] });
    await scan(p); await setDry(p, false);
    await bar(p, '[data-a=run]').click(); await done(p);
    const s = await server(p);
    eq(s.disabled, ON.filter((x) => x !== 9004), 'diğerleri pasife alındı');
    ok(/Hata[\s\S]*9004/.test(await resText(p)), 'hata listesinde 9004');
    ok(/5 pasife alındı, 0 deneme, 0 atlandı, 1 hata/.test(await status(p)), 'özet: ' + await status(p));
    await p.close();
  }

  console.log('S5 onay penceresi varsa ("Disable service — Are you sure…") Disable\'a basılır');
  if (want('S5')) {
    const p = await newPage(ctx, { disableConfirm: true });
    await scan(p); await setDry(p, false);
    await bar(p, '[data-a=run]').click(); await done(p);
    const s = await server(p);
    eq(s.confirmOpens, 6, '6 onay penceresi'); eq(s.disabled, ON, 'hepsi pasif'); eq(s.violations, [], 'ihlal yok');
    await p.close();
  }

  console.log('S6 her işlemden sonra liste baştan çizilir + Not Assigned\'ın kendi Expand\'i yok (tümünü aç yolu)');
  if (want('S6')) {
    const p = await newPage(ctx, { resetAfter: true, naNoCollapse: true });
    await scan(p); eq(await targets(p), ON, 'aday listesi');
    await setDry(p, false);
    await bar(p, '[data-a=run]').click(); await done(p);
    const s = await server(p);
    eq(s.disabled, ON, 'hepsi pasif'); eq(s.violations, [], 'ihlal yok');
    const st = await onState(p);
    ok(OTHER_ON().every((id) => st[id] === true), 'diğer kategorilerdeki aktifler duruyor (tümü açıkken bile)');
    await p.close();
  }

  console.log('S7 Not Assigned başlığı kategori sınıfı taşımıyor (yalnızca yazı)');
  if (want('S7')) {
    const p = await newPage(ctx, { naPlain: true, naNoCollapse: true });
    await scan(p); eq(await targets(p), ON, 'aday listesi');
    await setDry(p, false);
    await bar(p, '[data-a=run]').click(); await done(p);
    const s = await server(p);
    eq(s.disabled, ON, 'hepsi pasif'); eq(s.violations, [], 'ihlal yok');
    await p.close();
  }

  console.log('S8 işlemden hemen önce yeniden kontrol: sonradan pasif olan / başka kategoriye taşınan atlanır');
  if (want('S8')) {
    const p = await newPage(ctx, {});
    await scan(p); await setDry(p, false);
    await p.evaluate(() => { window.__mutate(9003, { on: false }); window.__moveSvc(9007, 'Instagram Followers'); });
    await bar(p, '[data-a=run]').click(); await done(p);
    const s = await server(p);
    eq(s.disabled, [9001, 9004, 9010, 9012], 'yalnızca hâlâ uygun olanlar');
    eq(s.violations, [], 'ihlal yok');
    const rt = await resText(p);
    ok(/9003[^\n]*zaten pasif/.test(rt), '9003: zaten pasif');
    ok(/9007[^\n]*(başka kategoride|bulunamadı)/.test(rt), '9007: başka kategoride → atlandı');
    eq((await onState(p))[9007], true, '9007 aktif kaldı (artık Instagram Followers\'ta)');
    await p.close();
  }

  console.log('S9 Durdur: sıradaki servisten önce durur');
  if (want('S9')) {
    const p = await newPage(ctx, { delay: 800 });
    await scan(p); await setDry(p, false);
    await bar(p, '[data-a=run]').click();
    await p.waitForFunction(() => window.__server.disabled.length >= 1, null, { timeout: 30000, polling: 100 });
    await bar(p, '[data-a=stop]').click(); await done(p);
    const n = (await server(p)).disabled.length;
    ok(n >= 1 && n <= 2, 'bir-iki servis işlendi (' + n + ')');
    ok(/Durduruldu/.test(await status(p)), 'durum: ' + await status(p));
    await p.waitForTimeout(1500);
    eq((await server(p)).disabled.length, n, 'durduktan sonra yeni işlem yok');
    await p.close();
  }

  console.log('S10 işareti kaldırılan atlanır + en çok N');
  if (want('S10')) {
    const p = await newPage(ctx, {});
    await scan(p); await setDry(p, false);
    await bar(p, '#spList .it:has-text("9001") input').uncheck();
    await bar(p, '[data-s=max]').fill('2'); await bar(p, '[data-s=max]').press('Tab');
    ok(/Pasife al \(2\)/.test(await bar(p, '[data-a=run]').innerText()), 'düğme: Pasife al (2)');
    await bar(p, '[data-a=run]').click(); await done(p);
    eq((await server(p)).disabled, [9003, 9004], '9001 atlandı, en çok 2');
    await p.close();
  }

  console.log('S11 mini panel (eklenti simgesi): bul → dene');
  if (want('S11')) {
    const p = await newPage(ctx, {});
    const extId = [...crypto.createHash('sha256').update(EXT).digest('hex').slice(0, 32)].map((c) => String.fromCharCode(97 + parseInt(c, 16))).join('');
    const pop = await ctx.newPage();
    await pop.goto('chrome-extension://' + extId + '/popup.html');
    const tabId = await pop.evaluate(async () => (await chrome.tabs.query({ url: 'https://panelbaku.com/*' }))[0].id);
    await pop.goto('chrome-extension://' + extId + '/popup.html?tab=' + tabId);
    await pop.waitForFunction(() => !document.getElementById('ui').hidden);
    await pop.click('#scan');
    await pop.waitForFunction(() => /bulundu/.test(document.getElementById('st').textContent), null, { timeout: 60000 });
    eq((await pop.locator('#list label').allInnerTexts()).map((x) => parseInt(x.trim(), 10)), ON, 'mini panelde liste');
    ok(/Dene \(6\)/.test(await pop.locator('#run').innerText()), 'düğme: Dene (6)');
    await pop.click('#run');
    await pop.waitForFunction(() => /Bitti/.test(document.getElementById('st').textContent), null, { timeout: 60000 });
    const s = await server(p);
    eq(s.disabled, [], 'deneme: pasife alınan yok'); eq(s.menuOpens.length, 6, '6 menü açıldı');
    const d = await pop.evaluate(async (tab) => new Promise((r) => chrome.tabs.sendMessage(tab, { sp: true, cmd: 'diag' }, r)), tabId);
    let dj = null; try { dj = JSON.parse(d.text); } catch (e) { /* yok */ }
    ok(dj && dj.surum === '1.0.0' && Array.isArray(dj.sonServisler), 'teşhis metni hatasız üretildi');
    await pop.close(); await p.close();
  }

  console.log('S12 sekme arka plandayken (başka sekme önde) de çalışır; beklemeler service worker ile');
  if (want('S12')) {
    const sw = ctx.serviceWorkers()[0] || await ctx.waitForEvent('serviceworker');
    const t0sw = await sw.evaluate(() => self.__ticks);
    const p = await newPage(ctx, { delay: 500 });
    const otherP = ctx.waitForEvent('page');
    await sw.evaluate(async () => { const [t] = await chrome.tabs.query({ url: 'https://panelbaku.com/*' }); await chrome.tabs.create({ url: 'about:blank', active: true, windowId: t.windowId }); });
    const other = await otherP;
    const sr = (sel) => p.evaluate((q) => document.querySelector('#sp-host').shadowRoot.querySelector(q).click(), sel);
    await sr('[data-a=scan]');
    await p.waitForFunction((f) => /bulundu|yok|⚠/.test(eval('(' + f + ')')()), stText.toString(), { timeout: 120000, polling: 300 });
    await sr('[data-s=dry]'); await sr('[data-a=run]');
    await done(p);
    const s = await server(p);
    eq(s.disabled, ON, 'arka planda hepsi pasif'); eq(s.violations, [], 'ihlal yok');
    const ticks = (await sw.evaluate(() => self.__ticks)) - t0sw;
    ok(ticks > 20, 'beklemeler service worker üzerinden (' + ticks + ' uyanış)');
    await other.close(); await p.close();
  }

  console.log('S13 Services dışı sayfada çubuk yok');
  if (want('S13')) {
    const p = await ctx.newPage();
    await p.route('https://panelbaku.com/**', (r) => r.fulfill({ contentType: 'text/html', body: '<body>x</body>' }));
    await p.goto('https://panelbaku.com/admin/orders');
    await p.waitForTimeout(1200);
    eq(await p.locator('#sp-host').count(), 0, 'çubuk eklenmedi');
    await p.close();
  }

  await ctx.close();
  console.log('\n' + (fails ? '✗ ' + fails + ' başarısız' : '✓ hepsi geçti') + '  (' + ((Date.now() - t0) / 1000).toFixed(0) + ' sn)');
  process.exit(fails ? 1 : 0);
})().catch((e) => { console.error(e); process.exit(2); });
