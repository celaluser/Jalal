// Pasif Servis Taşı — gerçek eklenti + React taklit paneli ile uçtan uca testler.
// Çalıştır: node run-tests.js        (tek test: node run-tests.js P4)
// Arka plan sekmesi (gerçek pencere): HEADED=1 xvfb-run -a node run-tests.js P12
const { chromium } = require('playwright');
const fs = require('fs');
const os = require('os');
const path = require('path');
const crypto = require('crypto');

const EXT = path.resolve(__dirname, '../servis-tasi');
const URL = 'https://panelbaku.com/admin/services';
let fails = 0;
const want = (t) => !process.argv[2] || process.argv[2] === t;
const ok = (c, msg) => { console.log((c ? '  ✓ ' : '  ✗ ') + msg); if (!c) fails++; };
const eq = (a, b, msg) => { const A = JSON.stringify(a), B = JSON.stringify(b); ok(A === B, msg + (A === B ? '' : '\n      beklenen ' + B + '\n      gelen    ' + A)); };
const sorted = (a) => [...a].sort((x, y) => x - y);

// --- veri: ekran görüntüsündeki gibi aktif kategoride pasif servisler; pasif kategori; Not Assigned en sonda
function cats() {
  const S = (id, on) => ({ id, name: 'Servis ' + id, on });
  const list = [
    { id: 1, name: 'Linkedin Xidmətləri', active: true, svcs: [S(2830, true), S(2829, true), S(2828, true)] },
    { id: 2, name: 'Snapchat Xidmətləri', active: true, svcs: [S(2452, false), S(1130, true), S(1132, false), S(2451, false), S(1131, true), S(1136, true), S(1137, true)] },
    { id: 3, name: 'PanelBaku - Kwai Xidmətləri', active: true, svcs: [S(3001, true), S(3002, false)] },
    { id: 4, name: 'TikTok Pasif Kategori', active: false, svcs: [S(4001, false), S(4002, false), S(4003, true)] },
  ];
  for (let i = 5; i <= 14; i++) list.push({ id: i, name: 'Kategori ' + i, active: i % 3 !== 0, svcs: [S(i * 100 + 1, true), S(i * 100 + 2, i % 2 !== 0), S(i * 100 + 3, true)] });
  list.push({ id: 99, name: 'Not Assigned', active: true, svcs: [S(9001, true), S(9002, false)] });
  return list;
}
const offIn = (pred) => cats().filter((c) => c.name !== 'Not Assigned' && pred(c)).flatMap((c) => c.svcs.filter((s) => !s.on).map((s) => s.id));
const EXP = offIn((c) => c.active);            // aktif kategorilerdeki pasif servisler (liste sırasıyla)
const EXP_ALL = offIn(() => true);             // + pasif kategorilerdekiler

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
  await page.waitForSelector('#pt-host', { state: 'attached' });
  return page;
}
const bar = (page, sel) => page.locator('#pt-host >> ' + sel);
const status = (page) => bar(page, '#st').innerText();
const stText = () => document.querySelector('#pt-host').shadowRoot.querySelector('#st').textContent;
async function scan(page) {
  await bar(page, '[data-a=scan]').click();
  await page.waitForFunction((f) => /hazır|yok|⚠/.test(eval('(' + f + ')')()), stText.toString(), { timeout: 120000, polling: 200 });
}
const done = (page) => page.waitForFunction((f) => /Bitti|Durduruldu\./.test(eval('(' + f + ')')()), stText.toString(), { timeout: 240000, polling: 200 })
  .catch(async (e) => { console.log('--- ZAMAN AŞIMI:\n' + await bar(page, '#bar').innerText().catch(() => '?')); throw e; });
const server = (page) => page.evaluate(() => JSON.parse(JSON.stringify(window.__server)));
const setDry = async (page, v) => { const cb = bar(page, '[data-s=dry]'); if ((await cb.isChecked()) !== v) await cb.click(); };
const setNum = async (page, k, v) => { await bar(page, '[data-s=' + k + ']').fill(String(v)); await bar(page, '[data-s=' + k + ']').press('Tab'); };
const listed = async (page) => (await bar(page, '#ptList .it').allInnerTexts()).map((x) => parseInt(x.trim(), 10));
const resText = (page) => bar(page, '#ptRes').innerText().catch(() => '');
const naIds = (s) => s.cats.find((c) => c.name === 'Not Assigned').svcs.map((x) => x.id);

(async () => {
  const dir = fs.mkdtempSync(path.join(os.tmpdir(), 'pt-'));
  const ctx = await chromium.launchPersistentContext(dir, {
    channel: 'chromium', headless: !process.env.HEADED, viewport: { width: 1100, height: 560 },
    args: ['--disable-extensions-except=' + EXT, '--load-extension=' + EXT, '--no-sandbox'],
    ignoreDefaultArgs: process.env.HEADED ? ['--disable-background-timer-throttling', '--disable-backgrounding-occluded-windows', '--disable-renderer-backgrounding'] : [],
  });
  const t0 = Date.now();

  console.log('P1 tarama: aktif kategorilerdeki pasif servisler listelenir (Not Assigned ve pasif kategoriler hariç); hiçbir şey seçilmez');
  if (want('P1')) {
    const p = await newPage(ctx, {});
    await scan(p);
    eq(await listed(p), EXP, 'aday listesi (liste sırasıyla)');
    ok((await listed(p)).includes(2452) && (await listed(p)).includes(1132) && (await listed(p)).includes(2451), 'Snapchat\'teki 2452, 1132, 2451 listede');
    ok(!(await listed(p)).includes(9002), 'Not Assigned\'daki pasif servis listede yok');
    const s = await server(p);
    eq(s.assignOpened, 0, 'Assign açılmadı'); eq(await p.evaluate(() => window.__selCount()), 0, 'hiçbir şey seçilmedi');
    ok(new RegExp('Dene \\(' + EXP.length + '\\)').test(await bar(p, '[data-a=run]').innerText()), 'düğme: Dene (' + EXP.length + ')');
    ok(p.__errors.length === 0, 'JS hatası yok ' + p.__errors.join('|'));
    await p.close();
  }

  console.log('P2 "Pasif kategorilerdekileri de dahil et": liste anında genişler');
  if (want('P2')) {
    const p = await newPage(ctx, {});
    await scan(p);
    await bar(p, '[data-s=includePasif]').check();
    eq(await listed(p), EXP_ALL, 'pasif kategorilerin pasif servisleri de listede');
    ok(new RegExp('\\(' + EXP_ALL.length + '\\)').test(await bar(p, '[data-a=run]').innerText()), 'düğme sayısı güncellendi');
    await p.close();
  }

  console.log('P3 deneme modu: kutucuklar işaretlenir, Assign penceresinde Not Assigned seçilir, Close; hiçbir şey taşınmaz');
  if (want('P3')) {
    const p = await newPage(ctx, {});
    await scan(p); await setNum(p, 'batch', 3);
    await bar(p, '[data-a=run]').click(); await done(p);
    const s = await server(p);
    const groups = Math.ceil(EXP.length / 3);
    eq(s.moves, [], 'taşıma yok'); eq([s.assignOpened, s.assignClosed, s.assignOk], [groups, groups, 0], groups + ' grup: Assign açıldı/Close, hiç Assign onayı yok');
    eq(await p.evaluate(() => window.__selCount()), 0, 'seçim temizlendi');
    eq([s.massDeleteAll, s.massOther.length], [0, 0], '"Delete all" / "Disable all" vb. yok');
    ok(new RegExp(EXP.length + ' deneme').test(await status(p)), 'özet: ' + await status(p));
    await p.close();
  }

  console.log('P4 gerçek: hepsi Not Assigned\'a taşınır, sonda doğrulanır; aktif servislere dokunulmaz');
  if (want('P4')) {
    const p = await newPage(ctx, {});
    await scan(p); await setDry(p, false);
    ok(new RegExp('^Taşı \\(' + EXP.length + '\\)').test(await bar(p, '[data-a=run]').innerText()), 'düğme: Taşı (' + EXP.length + ')');
    await bar(p, '[data-a=run]').click(); await done(p);
    const s = await server(p);
    eq(sorted(s.moves.map((m) => m.id)), sorted(EXP), 'taşınanlar = adaylar');
    ok(s.moves.every((m) => m.to === 'Not Assigned'), 'hepsi Not Assigned\'a');
    eq(s.violations, [], 'ihlal yok (aktif servis / Not Assigned\'dan / başka hedefe taşıma yok)');
    eq([s.massDeleteAll, s.massOther.length, s.deletes], [0, 0, 0], '"Delete all"/diğer toplu işlemler/servis silme yok');
    ok(EXP.every((id) => naIds(s).includes(id)), 'Not Assigned\'da görünüyorlar');
    ok(new RegExp('Bitti\\. ' + EXP.length + ' taşındı, 0 deneme, 0 atlandı, 0 hata').test(await status(p)), 'özet: ' + await status(p));
    ok(/✓/.test(await resText(p)), 'sonda doğrulandı (✓)');
    eq(await p.evaluate(() => window.__selCount()), 0, 'seçim kalmadı');
    ok(p.__errors.length === 0, 'JS hatası yok');
    await p.close();
  }

  console.log('P5 grup boyutu 2: her grupta ayrı Assign; hepsi taşınır');
  if (want('P5')) {
    const p = await newPage(ctx, {});
    await scan(p); await setDry(p, false); await setNum(p, 'batch', 2);
    await bar(p, '[data-a=run]').click(); await done(p);
    const s = await server(p);
    eq(s.assignOk, Math.ceil(EXP.length / 2), Math.ceil(EXP.length / 2) + ' Assign onayı');
    eq(sorted(s.moves.map((m) => m.id)), sorted(EXP), 'hepsi taşındı'); eq(s.violations, [], 'ihlal yok');
    await p.close();
  }

  console.log('P6 onay penceresi farklı sayı gösterirse (99) → Assign yok, Cancel; üst üste 2 hatalı grupta durur');
  if (want('P6')) {
    const p = await newPage(ctx, { confirmCount: 99 });
    await scan(p); await setDry(p, false); await setNum(p, 'batch', 2);
    await bar(p, '[data-a=run]').click(); await done(p);
    const s = await server(p);
    eq(s.moves, [], 'taşıma yok'); eq(s.assignOk, 0, 'Assign\'a basılmadı'); eq(s.confirmCancels, 2, '2 grupta Cancel, sonra durdu');
    ok(/onay penceresindeki sayı \(99\)/.test(await resText(p)), 'neden yazıldı');
    eq(await p.evaluate(() => window.__selCount()), 0, 'seçim temizlendi');
    await p.close();
  }

  console.log('P7 işaretlemeden hemen önce yeniden kontrol: sonradan aktif olan / Not Assigned\'a taşınmış olan atlanır');
  if (want('P7')) {
    const p = await newPage(ctx, {});
    await scan(p); await setDry(p, false);
    await p.evaluate(() => { window.__mutate(2452, { on: true }); window.__moveSvc(1132, 'Not Assigned'); });
    await bar(p, '[data-a=run]').click(); await done(p);
    const s = await server(p);
    eq(sorted(s.moves.map((m) => m.id)), sorted(EXP.filter((x) => x !== 2452 && x !== 1132)), 'yalnızca hâlâ uygun olanlar taşındı');
    eq(s.violations, [], 'ihlal yok');
    const rt = await resText(p);
    ok(/2452[^\n]*artık aktif/.test(rt), '2452: artık aktif');
    ok(/1132[^\n]*zaten Not Assigned/.test(rt), '1132: zaten Not Assigned\'da');
    await p.close();
  }

  console.log('P8 kutucuk tıklanınca fazladan servis seçilirse (sayı tutmaz) → o grup iptal, taşıma yok');
  if (want('P8')) {
    const p = await newPage(ctx, { badCheckbox: 1132 });
    await scan(p); await setDry(p, false); await setNum(p, 'batch', 50);
    await bar(p, '[data-a=run]').click(); await done(p);
    const s = await server(p);
    eq(s.moves, [], 'taşıma yok'); eq(s.assignOpened, 0, 'Assign açılmadı');
    ok(/seçim 3 ≠ 2/.test(await resText(p)), 'neden: seçim sayısı tutmadı');
    eq(await p.evaluate(() => window.__selCount()), 0, 'seçim temizlendi');
    await p.close();
  }

  console.log('P9 tümü açıkken kapalı kalmış kategori ("Snapchat") gezinti sırasında açılır ve okunur');
  if (want('P9')) {
    const p = await newPage(ctx, { someCollapsed: ['Snapchat Xidmətləri'] });
    await scan(p);
    eq(await listed(p), EXP, 'Snapchat\'in pasif servisleri de bulundu');
    await p.close();
  }

  console.log('P10 Durdur: o anki grup bitince durur');
  if (want('P10')) {
    const p = await newPage(ctx, { delay: 1500 });
    await scan(p); await setDry(p, false); await setNum(p, 'batch', 2);
    await bar(p, '[data-a=run]').click();
    await p.waitForFunction(() => window.__server.assignOk >= 1, null, { timeout: 60000, polling: 100 });
    await bar(p, '[data-a=stop]').click(); await done(p);
    const n = (await server(p)).moves.length;
    ok(n >= 2 && n <= 4, 'bir-iki grup taşındı (' + n + ')');
    ok(/Durduruldu/.test(await status(p)), 'durum: ' + await status(p));
    eq(await p.evaluate(() => window.__selCount()), 0, 'seçim kalmadı');
    await p.close();
  }

  console.log('P11 işareti kaldırılan atlanır + en çok N');
  if (want('P11')) {
    const p = await newPage(ctx, {});
    await scan(p); await setDry(p, false);
    await bar(p, '#ptList .it:has-text("2452") input').uncheck();
    await setNum(p, 'max', 3);
    ok(/Taşı \(3\)/.test(await bar(p, '[data-a=run]').innerText()), 'düğme: Taşı (3)');
    await bar(p, '[data-a=run]').click(); await done(p);
    const ids = (await server(p)).moves.map((m) => m.id);
    eq(sorted(ids), sorted(EXP.filter((x) => x !== 2452).slice(0, 3)), '2452 atlandı, en çok 3');
    await p.close();
  }

  console.log('P15 sunucu hatası (taşıma olmadı) → hata, seçim temizlenir, 2 hatalı grupta durur');
  if (want('P15')) {
    const p = await newPage(ctx, { moveFail: true });
    await scan(p); await setDry(p, false); await setNum(p, 'batch', 2);
    await bar(p, '[data-a=run]').click(); await done(p);
    const s = await server(p);
    eq(s.moves, [], 'taşıma yok');
    ok(/Hata/.test(await resText(p)), 'hata listesi var');
    ok(/Üst üste 2 grupta hata/.test(await bar(p, '#bar').innerText()), 'durdu: üst üste 2 grup');
    eq(await p.evaluate(() => window.__selCount()), 0, 'seçim temizlendi');
    await p.close();
  }

  console.log('P13 mini panel (eklenti simgesi): bul → seçenek → dene');
  if (want('P13')) {
    const p = await newPage(ctx, {});
    const extId = [...crypto.createHash('sha256').update(EXT).digest('hex').slice(0, 32)].map((c) => String.fromCharCode(97 + parseInt(c, 16))).join('');
    const pop = await ctx.newPage();
    await pop.goto('chrome-extension://' + extId + '/popup.html');
    const tabId = await pop.evaluate(async () => (await chrome.tabs.query({ url: 'https://panelbaku.com/*' }))[0].id);
    await pop.goto('chrome-extension://' + extId + '/popup.html?tab=' + tabId);
    await pop.waitForFunction(() => !document.getElementById('ui').hidden);
    await pop.click('#scan');
    await pop.waitForFunction(() => /hazır/.test(document.getElementById('st').textContent), null, { timeout: 90000 });
    eq((await pop.locator('#list label').allInnerTexts()).map((x) => parseInt(x.trim(), 10)), EXP, 'mini panelde liste');
    await pop.click('#inc');
    await pop.waitForFunction((n) => document.querySelectorAll('#list label').length === n, EXP_ALL.length);
    ok(true, 'seçenek: pasif kategoriler eklendi (' + EXP_ALL.length + ')');
    await pop.click('#inc');
    await pop.waitForFunction((n) => document.querySelectorAll('#list label').length === n, EXP.length);
    await pop.click('#run');
    await pop.waitForFunction(() => /Bitti/.test(document.getElementById('st').textContent), null, { timeout: 120000 });
    const s = await server(p);
    eq(s.moves, [], 'deneme: taşıma yok'); ok(s.assignOpened >= 1 && s.assignOpened === s.assignClosed, 'Assign açıldı ve kapandı');
    const d = await pop.evaluate(async (tab) => new Promise((r) => chrome.tabs.sendMessage(tab, { pt: true, cmd: 'diag' }, r)), tabId);
    let dj = null; try { dj = JSON.parse(d.text); } catch (e) { /* yok */ }
    ok(dj && dj.surum === '1.0.0' && Array.isArray(dj.sonServisler), 'teşhis metni hatasız üretildi');
    await pop.close(); await p.close();
  }

  console.log('P12 sekme arka plandayken (başka sekme önde) taşıma sürer; beklemeler service worker ile');
  if (want('P12')) {
    const sw = ctx.serviceWorkers()[0] || await ctx.waitForEvent('serviceworker');
    const t0sw = await sw.evaluate(() => self.__ticks);
    const p = await newPage(ctx, { delay: 500 });
    const otherP = ctx.waitForEvent('page');
    await sw.evaluate(async () => { const [t] = await chrome.tabs.query({ url: 'https://panelbaku.com/*' }); await chrome.tabs.create({ url: 'about:blank', active: true, windowId: t.windowId }); });
    const other = await otherP;
    const sr = (sel) => p.evaluate((q) => document.querySelector('#pt-host').shadowRoot.querySelector(q).click(), sel);
    await sr('[data-a=scan]');
    await p.waitForFunction((f) => /hazır|yok|⚠/.test(eval('(' + f + ')')()), stText.toString(), { timeout: 120000, polling: 300 });
    await sr('[data-s=dry]'); await sr('[data-a=run]');
    await done(p);
    const s = await server(p);
    eq(sorted(s.moves.map((m) => m.id)), sorted(EXP), 'arka planda hepsi taşındı'); eq(s.violations, [], 'ihlal yok');
    const ticks = (await sw.evaluate(() => self.__ticks)) - t0sw;
    ok(ticks > 20, 'beklemeler service worker üzerinden (' + ticks + ' uyanış)');
    await other.close(); await p.close();
  }

  console.log('P14 Services dışı sayfada çubuk yok');
  if (want('P14')) {
    const p = await ctx.newPage();
    await p.route('https://panelbaku.com/**', (r) => r.fulfill({ contentType: 'text/html', body: '<body>x</body>' }));
    await p.goto('https://panelbaku.com/admin/orders');
    await p.waitForTimeout(1200);
    eq(await p.locator('#pt-host').count(), 0, 'çubuk eklenmedi');
    await p.close();
  }

  await ctx.close();
  console.log('\n' + (fails ? '✗ ' + fails + ' başarısız' : '✓ hepsi geçti') + '  (' + ((Date.now() - t0) / 1000).toFixed(0) + ' sn)');
  process.exit(fails ? 1 : 0);
})().catch((e) => { console.error(e); process.exit(2); });
