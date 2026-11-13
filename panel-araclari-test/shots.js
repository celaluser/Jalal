// Tasarım görüntüleri: node shots.js <klasör>  (açık + koyu tema, sayfadaki pencere ve mini panel)
const { chromium } = require('/home/user/Jalal/servis-tasi-test/node_modules/playwright');
const fs = require('fs'); const os = require('os'); const path = require('path');
const EXT = path.resolve(__dirname, '../panel-araclari'); const MOCK = path.resolve(__dirname, '../servis-tasi-test');
const OUT = process.argv[2] || '.';
const S = (id, on) => ({ id, name: 'Instagram Takipçi | Servis ' + id + ' [ Sürətli ]', on });
const cats = [
  { id: 1, name: 'Snapchat Xidmətləri', active: true, svcs: [S(2452, false), S(1130, true), S(1132, false), S(1133, false), S(1134, false)] },
  { id: 2, name: 'TikTok Pasif Kategori', active: false, svcs: [S(4001, false)] },
  { id: 3, name: 'YouTube Baxış', active: true, svcs: [S(5001, false), S(5002, true), S(5003, false)] },
  { id: 99, name: 'Not Assigned', active: true, svcs: [S(9001, true), S(9002, false)] },
];
(async () => {
  for (const scheme of ['light', 'dark']) {
    const ctx = await chromium.launchPersistentContext(fs.mkdtempSync(path.join(os.tmpdir(), 'shot-')), { channel: 'chromium', headless: true, viewport: { width: 1280, height: 760 }, deviceScaleFactor: 2, colorScheme: scheme,
      args: ['--disable-extensions-except=' + EXT, '--load-extension=' + EXT, '--no-sandbox'] });
    const p = await ctx.newPage();
    await p.addInitScript((c) => { window.__cfg = c; }, { cats });
    await p.addInitScript((k) => { try { localStorage.setItem('pa_protect_demo', '1'); } catch (e) {} }, 0);
    await p.route('https://panelbaku.com/**', async (route) => {
      const u = new URL(route.request().url());
      if (u.pathname === '/_react.js') return route.fulfill({ contentType: 'text/javascript', body: fs.readFileSync(path.join(MOCK, 'node_modules/react/umd/react.development.js')) });
      if (u.pathname === '/_react-dom.js') return route.fulfill({ contentType: 'text/javascript', body: fs.readFileSync(path.join(MOCK, 'node_modules/react-dom/umd/react-dom.development.js')) });
      if (u.pathname === '/admin/services') return route.fulfill({ contentType: 'text/html', body: fs.readFileSync(path.join(MOCK, 'mock.html')) });
      return route.fulfill({ contentType: 'text/html', body: '<body>x</body>' });
    });
    await p.goto('https://panelbaku.com/admin/services');
    await p.waitForSelector('#pa-dock', { state: 'attached' });
    const sw = ctx.serviceWorkers()[0] || await ctx.waitForEvent('serviceworker');
    await sw.evaluate(() => chrome.storage.local.set({ pa_protect: ['5003'] }));
    await p.waitForTimeout(500);
    if (scheme === 'light') await p.screenshot({ path: path.join(OUT, 'pill.png'), clip: { x: 980, y: 680, width: 300, height: 80 } });
    await p.evaluate(() => document.getElementById('pa-dock').shadowRoot.querySelector('.pill').click());
    await p.evaluate(() => document.getElementById('pa-dock').shadowRoot.querySelector('.t[data-k=pt]').click());
    await p.evaluate(() => document.getElementById('pt-host').shadowRoot.querySelector('[data-a=scan]').click());
    await p.waitForFunction(() => /hazır|yok|⚠/.test(document.getElementById('pt-host').shadowRoot.getElementById('st').textContent), null, { timeout: 60000 });
    await p.waitForTimeout(400);
    await p.screenshot({ path: path.join(OUT, 'win-' + scheme + '.png') });
    const extId = new URL(sw.url()).host;
    const tabId = await sw.evaluate(async () => (await chrome.tabs.query({ url: 'https://panelbaku.com/*' }))[0].id);
    const pop = await ctx.newPage();
    await pop.setViewportSize({ width: 380, height: 600 });
    await pop.goto('chrome-extension://' + extId + '/popup.html?tab=' + tabId);
    await pop.click('#t-pt'); await pop.waitForTimeout(900);
    await pop.screenshot({ path: path.join(OUT, 'pop-' + scheme + '.png') });
    await ctx.close();
  }
  console.log('ok');
})().catch((e) => { console.error(e); process.exit(2); });
