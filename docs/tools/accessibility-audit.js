// Runs axe-core (WCAG 2 A/AA and best practices) on the main pages and prints what it finds. Needs: npm i playwright axe-core, a demo install (DemoSeeder).
// Usage: BASE=http://127.0.0.1:8000 TOKEN_FILE=order-token.txt node docs/tools/accessibility-audit.js
const { chromium } = require('playwright');
const fs = require('fs');
const AXE = fs.readFileSync(require.resolve('axe-core/axe.min.js'), 'utf8');
const BASE = process.env.BASE || 'http://127.0.0.1:8771';
const TOKEN = fs.readFileSync(''+(process.env.TOKEN_FILE||'token.txt')+'', 'utf8').trim();
(async () => {
  const b = await chromium.launch();
  const run = async (p, urls, label) => {
    for (const u of urls) {
      try {
        await p.goto(BASE + u, { waitUntil: 'networkidle' });
        await p.locator('button:has-text("Decline")').first().click({ timeout: 300 }).catch(() => {});
        await p.evaluate(AXE);
        const res = await p.evaluate(async () => (await axe.run(document, { resultTypes: ['violations'], runOnly: { type: 'tag', values: ['wcag2a', 'wcag2aa', 'wcag21aa', 'best-practice'] } })).violations.map((v) => ({ id: v.id, impact: v.impact, n: v.nodes.length, help: v.help, sample: v.nodes[0].target.join(' ').slice(0, 90) })));
        for (const v of res) console.log(`${label}\t${u}\t${v.impact}\t${v.id}\t${v.n}\t${v.sample}`);
        if (!res.length) console.log(`${label}\t${u}\tclean`);
      } catch (e) { console.log('ERR', u, e.message.split('\n')[0]); }
    }
  };
  const anon = await (await b.newContext({ viewport: { width: 1280, height: 800 } })).newPage();
  await run(anon, ['/', '/login', '/register', '/blog', '/r/bella-italia', '/r/bella-italia/reserve', '/r/bella-italia/about', `/r/bella-italia/order/${TOKEN}`], 'public');
  const login = async (email) => { const p = await (await b.newContext({ viewport: { width: 1280, height: 800 } })).newPage(); await p.goto(BASE + '/login'); await p.fill('input[name=email]', email); await p.fill('input[name=password]', 'demo-password'); await Promise.all([p.waitForNavigation({ waitUntil: 'networkidle' }).catch(() => {}), p.click('button[type=submit]')]); return p; };
  const o = await login('owner@bella-italia.demo');
  await run(o, ['/dashboard', '/orders', '/menu', '/menu/products/create', '/tables', '/appearance', '/reports', '/customers', '/marketing/campaigns/create', '/inventory', '/store', '/settings/restaurant', '/subscription'], 'panel');
  const a = await login('admin@demo.test');
  await run(a, ['/admin', '/admin/plans', '/admin/store', '/admin/themes', '/admin/settings/general', '/admin/system'], 'admin');
  await b.close();
})();
