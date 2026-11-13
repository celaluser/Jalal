// tgwatch eklentisi: bakiyeler arka planda her dakika çekilir (mini panel açılmasa da), panel açılınca kayıt görünür.
const { chromium } = require('/home/user/Jalal/servis-tasi-test/node_modules/playwright');
const path = require('path'); const fs = require('fs'); const os = require('os');
let fails = 0;
const ok = (c, m) => { console.log((c ? '  ✓ ' : '  ✗ ') + m); if (!c) fails++; };
(async () => {
  const EXT = path.resolve(__dirname, '../tgwatch/chrome-extension');
  const ctx = await chromium.launchPersistentContext(fs.mkdtempSync(path.join(os.tmpdir(), 'tgwbal-')), { channel: 'chromium', headless: true, args: ['--disable-extensions-except=' + EXT, '--load-extension=' + EXT, '--no-sandbox'] });
  let n = 0; let force = 0;
  await ctx.route('https://tgwatch.test/**', async (route) => {
    const b = JSON.parse(route.request().postData() || '{}');
    if (b.action === 'balance') { n++; if (b.force) force++; return route.fulfill({ contentType: 'application/json', body: JSON.stringify({ ok: true, items: [{ id: 1, name: 'fastersmm', balance: 18.71 + n, currency: 'USD', error: '' }], totals: { USD: 18.71 + n }, at: Math.floor(Date.now() / 1000), cached: !b.force }) }); }
    return route.fulfill({ contentType: 'application/json', body: JSON.stringify({ ok: true, version: '1.4.0', ext: '' }) });
  });
  const sw = ctx.serviceWorkers()[0] || await ctx.waitForEvent('serviceworker');
  const alarms = await sw.evaluate(async () => (await chrome.alarms.getAll()).map((a) => [a.name, a.periodInMinutes]));
  ok(alarms.some(([n, p]) => n === 'tgw-bal' && p === 1), 'her dakikalık bakiye alarmı kurulu: ' + JSON.stringify(alarms));
  await sw.evaluate(() => chrome.storage.local.set({ api: 'https://tgwatch.test/api.php', key: 'k', panel: 'https://panelbaku.com' }));
  await sw.evaluate(() => self.__pullBalances(false));
  await sw.evaluate(() => self.__pullBalances(false));
  const st = await sw.evaluate(() => chrome.storage.local.get({ bal: null }));
  ok(n === 2 && st.bal && st.bal.items[0].balance === 20.71, 'mini panel kapalıyken arka planda çekildi ve saklandı (' + n + ' istek, son ' + (st.bal && st.bal.items[0].balance) + ')');
  // mini panel açılınca: kayıt hemen görünür, yeni istek atılmaz; arka plan güncelleyince panel de güncellenir
  const id = new URL(sw.url()).host;
  const p = await ctx.newPage();
  await p.goto('chrome-extension://' + id + '/popup.html');
  await p.click('#t-bal');
  await p.waitForFunction(() => /20\.71/.test(document.getElementById('bal-list').textContent));
  ok(n === 2, 'panel açılınca kayıt gösterildi, yeni istek atılmadı');
  ok(/her dakika kendiliğinden güncellenir/.test(await p.textContent('#bal-msg')), 'durum: ' + await p.textContent('#bal-msg'));
  await sw.evaluate(() => self.__pullBalances(false));
  await p.waitForFunction(() => /21\.71/.test(document.getElementById('bal-list').textContent), null, { timeout: 5000 });
  ok(true, 'arka plan yeni değeri getirince açık panel kendiliğinden güncellendi');
  await p.click('#bal-ref');
  await p.waitForFunction(() => /22\.71/.test(document.getElementById('bal-list').textContent), null, { timeout: 5000 });
  ok(force === 1, '"Yenile": sunucuya force ile anında sorgu');
  await ctx.close();
  console.log(fails ? '✗ ' + fails + ' başarısız' : '✓ hepsi geçti');
  process.exit(fails ? 1 : 0);
})().catch((e) => { console.error(e); process.exit(2); });
