// tgwatch eklentisi: arka plan service worker'ı — bekleme (sleep/wake) ve "meşgul" iken sekmeyi kapatılmaya karşı koruma.
const { chromium } = require('/home/user/Jalal/servis-tasi-test/node_modules/playwright');
const path = require('path'); const fs = require('fs'); const os = require('os'); const crypto = require('crypto');
let fails = 0;
const ok = (c, m) => { console.log((c ? '  ✓ ' : '  ✗ ') + m); if (!c) fails++; };
(async () => {
  const EXT = path.resolve(__dirname, '../tgwatch/chrome-extension');
  const ctx = await chromium.launchPersistentContext(fs.mkdtempSync(path.join(os.tmpdir(), 'tgwsw-')), { channel: 'chromium', headless: true, args: ['--disable-extensions-except=' + EXT, '--load-extension=' + EXT, '--no-sandbox'] });
  const sw = ctx.serviceWorkers()[0] || await ctx.waitForEvent('serviceworker');
  const id = [...crypto.createHash('sha256').update(EXT).digest('hex').slice(0, 32)].map((c) => String.fromCharCode(97 + parseInt(c, 16))).join('');
  const p = await ctx.newPage();
  await p.goto('chrome-extension://' + id + '/options.html');
  const r = await p.evaluate(() => new Promise((resolve) => {
    const port = chrome.runtime.connect({ name: 'tgw' });
    const t0 = Date.now(); const got = [];
    port.onMessage.addListener((m) => { got.push(m); if (got.length === 2) resolve({ got, ms: Date.now() - t0 }); });
    port.postMessage({ t: 'sleep', id: 1, ms: 300 });
    port.postMessage({ t: 'sleep', id: 2, ms: 600 });
    window.__port = port;
  }));
  ok(r.got.map((m) => m.t + m.id).join(',') === 'wake1,wake2', 'service worker uyandırdı: ' + JSON.stringify(r.got));
  ok(r.ms >= 550 && r.ms < 3000, 'süre doğru (' + r.ms + ' ms)');
  ok((await sw.evaluate(() => self.__ticks)) >= 2, 'sayaç arttı');
  const tabId = await p.evaluate(() => new Promise((res) => chrome.tabs.getCurrent((t) => res(t.id))));
  await p.evaluate(() => window.__port.postMessage({ t: 'busy', on: true }));
  await p.waitForTimeout(400);
  ok((await sw.evaluate((t) => chrome.tabs.get(t).then((x) => x.autoDiscardable), tabId)) === false, 'meşgulken sekme otomatik kapatılamaz (autoDiscardable=false)');
  await p.evaluate(() => window.__port.postMessage({ t: 'busy', on: false }));
  await p.waitForTimeout(400);
  ok((await sw.evaluate((t) => chrome.tabs.get(t).then((x) => x.autoDiscardable), tabId)) === true, 'iş bitince serbest');
  const old = await p.evaluate(() => new Promise((res) => chrome.runtime.sendMessage({ type: 'api', action: 'ping' }, res)));
  ok(old && old.ok === false && /ayarları eksik/.test(old.error), 'eski mesajlar (api) çalışmaya devam ediyor');
  await ctx.close();
  console.log(fails ? '✗ ' + fails + ' başarısız' : '✓ hepsi geçti');
  process.exit(fails ? 1 : 0);
})();
