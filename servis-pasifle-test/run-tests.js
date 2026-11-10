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
const sorted = (a) => [...a].sort((x, y) => x - y);
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
  await page.addInitScript(() => { try { localStorage.clear(); localStorage.setItem('sp_set', '1'); } catch (e) { /* yok */ } });
  await page.addInitScript((m) => { try { localStorage.setItem('sp_settings', JSON.stringify({ method: m })); } catch (e) { /* yok */ } }, (cfg && cfg.method) || 'single');
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
    ok(dj && dj.surum === '1.3.0' && Array.isArray(dj.sonServisler), 'teşhis metni hatasız üretildi');
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

  const autoState = (page) => page.evaluate(() => JSON.parse(sessionStorage.getItem('sp_auto') || 'null'));
const loads = (page) => page.evaluate(() => +sessionStorage.getItem('__loads') || 0);
const waitHist = (page, n) => page.waitForFunction((k) => { const a = JSON.parse(sessionStorage.getItem('sp_auto') || 'null'); return a && (a.history || []).length >= k; }, n, { timeout: 120000, polling: 250 });
async function getSw(ctx) { return ctx.serviceWorkers()[0] || ctx.waitForEvent('serviceworker'); }
async function tabIdOf(sw) { return sw.evaluate(async () => { const t = await chrome.tabs.query({ url: 'https://panelbaku.com/admin/services*' }); return t.length ? t[t.length - 1].id : null; }); }

console.log('S14 otomatik mod: hemen bir tur + her 5 dk alarm; alarmda sayfa yenilenir, yeni aktif servis pasife alınır');
  if (want('S14')) {
    const sw = await getSw(ctx);
    const p = await newPage(ctx, {});
    await setDry(p, false);
    await bar(p, '[data-s=auto]').check();
    await waitHist(p, 1);
    let s = await server(p);
    eq(s.disabled, ON, 'ilk tur: 6 aktif servis pasife alındı');
    const tabId = await tabIdOf(sw);
    const al = await sw.evaluate(async () => (await chrome.alarms.getAll()).map((a) => ({ name: a.name, p: a.periodInMinutes })));
    ok(al.some((a) => a.name === 'sp-auto:' + tabId && a.p === 5), 'alarm kuruldu: her 5 dk (' + JSON.stringify(al) + ')');
    ok(/Otomatik AÇIK \(GERÇEK\)/.test(await bar(p, '#spAuto').innerText()), 'çubuk: Otomatik AÇIK (GERÇEK)');
    // panelde yeniden aktif servis belirsin, sonra alarm çalsın
    await p.evaluate(() => { window.__mutate(9002, { on: true }); window.__mutate(9005, { on: true }); });
    const l0 = await loads(p);
    eq(await sw.evaluate((n) => self.__autoTick(n), 'sp-auto:' + tabId), 'yenilendi', 'alarm: sayfa yenilendi');
    await p.waitForFunction((l) => (+sessionStorage.getItem('__loads') || 0) > l, l0, { timeout: 20000, polling: 200 });
    await p.waitForSelector('#sp-host', { state: 'attached' });
    await waitHist(p, 2);
    s = await server(p);
    ok(s.disabled.includes(9002) && s.disabled.includes(9005), 'yenilemeden sonra 9002 ve 9005 pasife alındı');
    eq(s.violations, [], 'ihlal yok'); eq([s.deletes, s.enables, s.hides], [0, 0, 0], 'Delete/Enable/Hide yok');
    let a = await autoState(p);
    eq([a.on, a.history[0].bulunan, a.history[0].yapilan], [true, 2, 2], 'geçmiş: 2 bulundu, 2 pasife alındı; otomatik hâlâ açık');
    // aktif yokken tur: hiçbir şeye basılmaz
    const l1 = await loads(p);
    eq(await sw.evaluate((n) => self.__autoTick(n), 'sp-auto:' + tabId), 'yenilendi', 'alarm 3');
    await p.waitForFunction((l) => (+sessionStorage.getItem('__loads') || 0) > l, l1, { timeout: 20000, polling: 200 });
    await waitHist(p, 3);
    a = await autoState(p);
    eq([a.history[0].bulunan, a.history[0].hata || 0], [0, 0], 'aktif yok: 0 bulundu, hata yok');
    // kullanıcı panelde çalışıyorsa (son 1 dk tıklama) yenileme yapılmaz
    await p.mouse.click(600, 300);
    const l2 = await loads(p);
    eq(await sw.evaluate((n) => self.__autoTick(n), 'sp-auto:' + tabId), 'atla-icerik', 'kullanıcı tıkladı → tur atlandı');
    await p.waitForTimeout(800);
    eq(await loads(p), l2, 'sayfa yenilenmedi');
    ok(/son 1 dakikada/.test((await autoState(p)).lastSkip.why), 'neden yazıldı');
    // otomatiği kapat → alarm silinir
    await bar(p, '[data-s=auto]').uncheck();
    await p.waitForTimeout(500);
    const al2 = await sw.evaluate(async () => (await chrome.alarms.getAll()).map((x) => x.name));
    ok(!al2.includes('sp-auto:' + tabId), 'kapatınca alarm silindi');
    eq(await sw.evaluate((n) => self.__autoTick(n), 'sp-auto:' + tabId), 'kapali', 'eski alarm gelirse: kapalı, yenileme yok');
    await p.close();
  }

  console.log('S15 otomatik: tur sürerken alarm gelirse atlanır; başka sayfadaysa yenilenmez');
  if (want('S15')) {
    const sw = await getSw(ctx);
    const p = await newPage(ctx, { delay: 1500 });
    await setDry(p, false);
    await bar(p, '[data-s=auto]').check();
    await p.waitForFunction(() => window.__server.disabled.length >= 1, null, { timeout: 60000, polling: 100 });
    const tabId = await tabIdOf(sw);
    const l0 = await loads(p);
    eq(await sw.evaluate((n) => self.__autoTick(n), 'sp-auto:' + tabId), 'atla-icerik', 'iş sürerken alarm → atlandı');
    await waitHist(p, 1);
    eq(await loads(p), l0, 'yenileme yok (iş yarıda kesilmedi)');
    eq((await server(p)).disabled, ON, 'tur tamamlandı: 6 pasif');
    await p.goto('https://panelbaku.com/admin/orders');
    eq(await sw.evaluate((n) => self.__autoTick(n), 'sp-auto:' + tabId), 'atla-adres', 'Orders sayfasındayken alarm → yenileme yok');
    ok(/\/admin\/orders$/.test(p.url()), 'sekme Orders\'ta kaldı');
    await p.close();
    eq(await sw.evaluate((n) => self.__autoTick(n), 'sp-auto:' + tabId), 'sekme-yok', 'sekme kapandıysa alarm silinir');
  }

  console.log('S16 otomatik: 3 tur üst üste hata → otomatik kendini kapatır');
  if (want('S16')) {
    const sw = await getSw(ctx);
    const p = await newPage(ctx, { cats: cats().filter((c) => c.name !== 'Not Assigned') });
    await bar(p, '[data-s=auto]').check();
    await waitHist(p, 1);
    const tabId = await tabIdOf(sw);
    for (let k = 2; k <= 3; k++) {
      const l = await loads(p);
      await sw.evaluate((n) => self.__autoTick(n), 'sp-auto:' + tabId);
      await p.waitForFunction((x) => (+sessionStorage.getItem('__loads') || 0) > x, l, { timeout: 20000, polling: 200 });
      await waitHist(p, k);
    }
    const a = await autoState(p);
    eq(a.on, false, 'otomatik kapandı');
    ok(/3 tur üst üste/.test(a.offReason), 'neden: ' + a.offReason);
    ok(/Not Assigned/.test(a.history[0].durum), 'tur hatası: ' + a.history[0].durum);
    const al = await sw.evaluate(async () => (await chrome.alarms.getAll()).map((x) => x.name));
    ok(!al.includes('sp-auto:' + tabId), 'alarm silindi');
    await p.close();
  }

  console.log('S17 gerçek alarm (kanca yok): her 1 dk → ~1 dk sonra sayfa kendiliğinden yenilenir ve yeni aktif servis pasife alınır');
  if (want('S17')) {
    const p = await newPage(ctx, {});
    await setDry(p, false);
    await bar(p, '[data-s=every]').fill('1'); await bar(p, '[data-s=every]').press('Tab');
    await bar(p, '[data-s=auto]').check();
    await waitHist(p, 1);
    await p.evaluate(() => window.__mutate(9006, { on: true }));
    const l0 = await loads(p);
    const t1 = Date.now();
    await p.waitForFunction((l) => (+sessionStorage.getItem('__loads') || 0) > l, l0, { timeout: 100000, polling: 500 });
    const sec = Math.round((Date.now() - t1) / 1000);
    ok(sec >= 40 && sec <= 95, 'alarm ~1 dk sonra kendiliğinden çaldı (' + sec + ' sn)');
    await waitHist(p, 2);
    ok((await server(p)).disabled.includes(9006), '9006 pasife alındı');
    await bar(p, '[data-s=auto]').uncheck();
    await p.close();
  }

  console.log('S18 otomatik mod, panel sekmesi arka plandayken: alarm → yenile → tur tamamlanır');
  if (want('S18')) {
    const sw = await getSw(ctx);
    const p = await newPage(ctx, { delay: 400 });
    await setDry(p, false);
    await p.evaluate(() => window.__mutate(9001, { on: false })); // ilk turda 5 aktif
    const otherP = ctx.waitForEvent('page');
    await sw.evaluate(async () => { const [t] = await chrome.tabs.query({ url: 'https://panelbaku.com/admin/services*' }); await chrome.tabs.create({ url: 'about:blank', active: true, windowId: t.windowId }); });
    const other = await otherP;
    await p.evaluate(() => document.querySelector('#sp-host').shadowRoot.querySelector('[data-s=auto]').click());
    await waitHist(p, 1);
    eq((await server(p)).disabled.length, 5, 'arka planda ilk tur: 5 pasif');
    await p.evaluate(() => window.__mutate(9008, { on: true }));
    const tabId = await tabIdOf(sw);
    const l0 = await loads(p);
    eq(await sw.evaluate((n) => self.__autoTick(n), 'sp-auto:' + tabId), 'yenilendi', 'arka planda alarm → yenilendi');
    await p.waitForFunction((l) => (+sessionStorage.getItem('__loads') || 0) > l, l0, { timeout: 30000, polling: 300 });
    await waitHist(p, 2);
    ok((await server(p)).disabled.includes(9008), 'arka planda yenilenen sayfada 9008 pasife alındı');
    eq((await server(p)).violations, [], 'ihlal yok');
    await p.evaluate(() => document.querySelector('#sp-host').shadowRoot.querySelector('[data-s=auto]').click());
    await other.close(); await p.close();
  }

  console.log('S19 gerçek panelde görülen: Not Assigned kalabalık (başlık çok yukarıda), menü açılmıyor, tıklayınca liste başa kayıyor');
  if (want('S19')) {
    const base = cats();
    const na = base.find((c) => c.name === 'Not Assigned');
    na.svcs = Array.from({ length: 60 }, (_, k) => ({ id: 9001 + k, name: 'Servis ' + (9001 + k) + ' | Test', on: k % 10 === 3 }));
    const want19 = na.svcs.filter((x) => x.on).map((x) => x.id);
    const p = await newPage(ctx, { cats: base, menuNoOpen: true, jumpOnMenu: true });
    await scan(p);
    eq(await targets(p), want19, 'aday listesi (' + want19.length + ')');
    await setDry(p, false);
    await bar(p, '[data-a=run]').click(); await done(p);
    const s = await server(p);
    eq(s.disabled, want19, 'hepsi pasife alındı (menü görünmese de satırdaki öğeyle)');
    eq(s.violations, [], 'ihlal yok'); eq([s.deletes, s.enables, s.hides], [0, 0, 0], 'Delete/Enable/Hide yok');
    eq(s.toggles || 0, 0, '"tümünü aç/kapa" düğmesine hiç basılmadı');
    const st = await onState(p);
    ok(OTHER_ON().every((id) => st[id] === true), 'diğer kategorilerdeki aktifler duruyor');
    await p.close();
  }

  console.log('M1 toplu yöntem (varsayılan): aktiflerin kutucuğu işaretlenir → Actions → Disable all; hepsi pasif, başka yere dokunulmaz');
  if (want('M1')) {
    const p = await newPage(ctx, { method: 'mass' });
    ok(/on/.test(await bar(p, '[data-a=method][data-v=mass]').getAttribute('class')), 'Toplu sekmesi seçili');
    await scan(p); await setDry(p, false);
    await bar(p, '[data-a=run]').click(); await done(p);
    const s = await server(p);
    eq(sorted(s.massDisabled || []), sorted(ON), 'Disable all yalnızca seçilen 6 aktif servise');
    eq(s.massDisableClicks, 1, 'tek bir "Disable all"');
    eq([s.massDeleteAll || 0, s.massEnableAll || 0, s.deletes, s.enables], [0, 0, 0, 0], 'Delete all / Enable all / servis silme yok');
    eq(s.menuOpens, [], 'servis menüsü açılmadı');
    eq(s.violations, [], 'ihlal yok (Not Assigned dışı seçim yok)');
    const st = await onState(p);
    ok(OTHER_ON().every((id) => st[id] === true), 'diğer kategorilerdeki aktifler duruyor');
    eq(await p.evaluate(() => window.__onCount('Not Assigned')), 0, 'Not Assigned\'da aktif kalmadı');
    eq(await p.evaluate(() => window.__selCount()), 0, 'seçim kalmadı');
    ok(/6 pasife alındı, 0 deneme, 0 atlandı, 0 hata/.test(await status(p)), 'özet: ' + await status(p));
    await p.close();
  }

  console.log('M2 toplu deneme: kutucuklar işaretlenir, "Disable all" görülür ama basılmaz; seçim temizlenir');
  if (want('M2')) {
    const p = await newPage(ctx, { method: 'mass' });
    await scan(p);
    await bar(p, '[data-a=run]').click(); await done(p);
    const s = await server(p);
    eq([s.massDisableClicks || 0, (s.massDisabled || []).length], [0, 0], 'Disable all\'a basılmadı');
    eq(await p.evaluate(() => window.__selCount()), 0, 'seçim temizlendi');
    ok(/6 deneme/.test(await status(p)), 'özet: ' + await status(p));
    await p.close();
  }

  console.log('M3 toplu: onay penceresi (sayı doğru) → Disable; sayı yanlışsa Cancel');
  if (want('M3')) {
    let p = await newPage(ctx, { method: 'mass', massConfirm: true });
    await scan(p); await setDry(p, false);
    await bar(p, '[data-a=run]').click(); await done(p);
    let s = await server(p);
    eq(sorted(s.massDisabled || []), sorted(ON), 'onaylandı, 6 pasif');
    await p.close();
    p = await newPage(ctx, { method: 'mass', massConfirm: true, massConfirmCount: 99 });
    await scan(p); await setDry(p, false);
    await bar(p, '[data-a=run]').click(); await done(p);
    s = await server(p);
    eq((s.massDisabled || []).length, 0, 'yanlış sayı: hiçbir şey pasif olmadı'); eq(s.massCancels, 1, 'Cancel');
    ok(/onay penceresindeki sayı \(99\)/.test(await resText(p)), 'neden yazıldı');
    eq(await p.evaluate(() => window.__selCount()), 0, 'seçim temizlendi');
    await p.close();
  }

  console.log('M4 toplu: kutucuk fazladan servis seçerse grup iptal; kalabalık Not Assigned + yeniden çizim');
  if (want('M4')) {
    let p = await newPage(ctx, { method: 'mass', badCheckbox: 9003 });
    await scan(p); await setDry(p, false);
    await bar(p, '[data-a=run]').click(); await done(p);
    let s = await server(p);
    eq((s.massDisabled || []).length, 0, 'iptal: hiçbir şey pasif olmadı'); eq(s.massDisableClicks || 0, 0, 'Disable all\'a basılmadı');
    eq(await p.evaluate(() => window.__selCount()), 0, 'seçim temizlendi');
    await p.close();
    const base = cats();
    const na = base.find((c) => c.name === 'Not Assigned');
    na.svcs = Array.from({ length: 60 }, (_, k) => ({ id: 9001 + k, name: 'Servis ' + (9001 + k) + ' | Test', on: k % 10 === 3 }));
    const w = na.svcs.filter((x) => x.on).map((x) => x.id);
    p = await newPage(ctx, { method: 'mass', cats: base, resetAfter: true });
    await scan(p); await setDry(p, false);
    await bar(p, '[data-a=run]').click(); await done(p);
    s = await server(p);
    eq(sorted(s.massDisabled || []), sorted(w), 'kalabalık Not Assigned: ' + w.length + ' aktif pasife alındı');
    eq(s.violations, [], 'ihlal yok'); eq(s.toggles || 0, 0, 'tümünü aç/kapa düğmesine basılmadı');
    await p.close();
  }

  console.log('M5 otomatik mod + toplu yöntem: alarm → yenile → Disable all');
  if (want('M5')) {
    const sw = await getSw(ctx);
    const p = await newPage(ctx, { method: 'mass' });
    await setDry(p, false);
    await bar(p, '[data-s=auto]').check();
    await waitHist(p, 1);
    eq(sorted((await server(p)).massDisabled || []), sorted(ON), 'ilk tur: Disable all ile 6 pasif');
    await p.evaluate(() => window.__mutate(9002, { on: true }));
    const tabId = await tabIdOf(sw);
    const l0 = await loads(p);
    eq(await sw.evaluate((n) => self.__autoTick(n), 'sp-auto:' + tabId), 'yenilendi', 'alarm → yenilendi');
    await p.waitForFunction((l) => (+sessionStorage.getItem('__loads') || 0) > l, l0, { timeout: 20000, polling: 200 });
    await waitHist(p, 2);
    ok(((await server(p)).massDisabled || []).includes(9002), 'yenilemeden sonra 9002 de Disable all ile pasif');
    await bar(p, '[data-s=auto]').uncheck();
    await p.close();
  }

  console.log('M6 arayüz: Toplu / Tek tek sekmelerine fareyle tıklama yöntemi değiştirir ve hatırlanır');
  if (want('M6')) {
    const p = await newPage(ctx, { method: 'mass' });
    const cls = (v) => bar(p, '[data-a=method][data-v=' + v + ']').getAttribute('class');
    ok(/on/.test(await cls('mass')), 'başta Toplu seçili');
    await bar(p, '[data-a=method][data-v=single]').click();
    ok(/on/.test(await cls('single')) && !/on/.test(await cls('mass')), 'Tek tek\'e tıklayınca Tek tek seçili');
    await bar(p, '[data-a=method][data-v=mass]').click();
    ok(/on/.test(await cls('mass')) && !/on/.test(await cls('single')), 'Toplu\'ya tıklayınca Toplu seçili');
    eq(await p.evaluate(() => JSON.parse(localStorage.getItem('sp_settings')).method), 'mass', 'seçim kaydedildi');
    await p.close();
    const base = cats(); base.find((c) => c.name === 'Not Assigned').svcs.forEach((x) => { x.on = false; });
    const q = await newPage(ctx, { method: 'mass', cats: base });
    await scan(q);
    ok(/yapılacak iş yok/.test(await bar(q, '#bar').innerText()), 'aktif yoksa açık mesaj: "yapılacak iş yok"');
    eq(await bar(q, '[data-a=run]').getAttribute('title'), 'Pasife alınacak aktif servis yok', 'kapalı düğmede neden yazıyor');
    await q.close();
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
