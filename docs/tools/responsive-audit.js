// Opens every page at a phone width in a real browser and reports sideways scrolling and tiny touch targets.
// Usage: npm i playwright && BASE=http://127.0.0.1:8000 W=360 TOKEN=<order token> node docs/tools/responsive-audit.js [all|public|admin|panel]
// Expects the demo data (php artisan db:seed --class=DemoSeeder): admin@demo.test and owner@bella-italia.demo.
const { chromium } = require('playwright');
const BASE = process.env.BASE || 'http://127.0.0.1:8771';
const W = parseInt(process.env.W || '360');
const PASS = 'demo-password';
const probe = () => {
  const vw = document.documentElement.clientWidth;
  const over = [];
  for (const el of document.querySelectorAll('body *')) {
    const r = el.getBoundingClientRect();
    if (r.width === 0 || r.height === 0) continue;
    const cs = getComputedStyle(el);
    if (cs.position === 'fixed' || cs.visibility === 'hidden' || cs.display === 'none') continue;
    // ignore elements inside horizontally scrollable containers
    let p = el.parentElement, scrolls = false;
    while (p && p !== document.body) { const o = getComputedStyle(p).overflowX; if ((o === 'auto' || o === 'scroll' || o === 'hidden') && p.scrollWidth > p.clientWidth) { scrolls = true; break; } if (o === 'hidden' || o === 'auto' || o === 'scroll') { scrolls = true; break; } p = p.parentElement; }
    if (scrolls) continue;
    if (r.right > vw + 1 || r.left < -1) over.push((el.tagName + '.' + String(el.className).split(' ').slice(0, 3).join('.') + (el.id ? '#' + el.id : '')).slice(0, 90) + ' ' + Math.round(r.left) + '→' + Math.round(r.right));
  }
  const small = [...document.querySelectorAll('a[href],button,input:not([type=hidden]),select')].filter((e) => { const r = e.getBoundingClientRect(); const cs = getComputedStyle(e); return r.width > 0 && cs.visibility !== 'hidden' && (r.height < 32 || r.width < 32) && !e.closest('.sr-only') && !(e.tagName === 'INPUT' && (e.type === 'checkbox' || e.type === 'radio')) && e.getAttribute('aria-hidden') !== 'true' && cs.position !== 'absolute'; }).length;
  return { overflow: document.documentElement.scrollWidth > vw + 1, sw: document.documentElement.scrollWidth, vw, over: over.slice(0, 4), small };
};
async function login(browser, email) {
  const ctx = await browser.newContext({ viewport: { width: W, height: 740 }, isMobile: true, hasTouch: true });
  const page = await ctx.newPage();
  await page.goto(BASE + '/login', { waitUntil: 'networkidle' });
  await page.fill('input[name=email]', email); await page.fill('input[name=password]', PASS);
  await Promise.all([page.waitForNavigation({ waitUntil: 'networkidle' }).catch(() => {}), page.click('button[type=submit]')]);
  return { ctx, page };
}
async function run(page, urls, label) {
  for (const u of urls) {
    try {
      const r = await page.goto(u.startsWith('http') ? u : BASE + u, { waitUntil: 'networkidle', timeout: 30000 });
      await page.waitForTimeout(300);
      const res = await page.evaluate(probe);
      const bad = res.overflow || r.status() >= 400;
      console.log(`${bad ? 'XX' : 'ok'} ${label} ${r.status()} ${u} sw=${res.sw}/${res.vw} small=${res.small}${res.over.length ? ' | ' + res.over.join(' ; ') : ''}`);
    } catch (e) { console.log('ERR', label, u, e.message.split('\n')[0]); }
  }
}
(async () => {
  const browser = await chromium.launch();
  const which = process.argv[2] || 'all';
  const T = process.env.TOKEN || '';
  if (which === 'all' || which === 'public') {
    const ctx = await browser.newContext({ viewport: { width: W, height: 740 }, isMobile: true, hasTouch: true });
    const page = await ctx.newPage();
    await run(page, ['/', '/blog', '/login', '/register', '/forgot-password', '/r/bella-italia', '/r/bella-italia/reserve', '/r/bella-italia/about', '/r/bella-italia/links', '/r/bella-italia/account', '/r/bella-italia?kiosk=1', '/r/sushi-zen', '/r/bella-italia?lang=ar', '/r/does-not-exist', T ? `/r/bella-italia/order/${T}` : '/'], 'public');
    await ctx.close();
  }
  if (which === 'all' || which === 'admin') {
    const s = await login(browser, 'admin@demo.test');
    await run(s.page, ['', '/restaurants', '/plans', '/plans/create', '/subscriptions', '/invoices', '/coupons', '/settings/billing', '/settings/payments', '/settings/guest-payments', '/messaging', '/email-templates', '/tickets', '/announcements', '/landing', '/posts', '/pages', '/languages', '/currencies', '/translations', '/themes', '/themes/create', '/system', '/system/logs', '/system/backups', '/updates', '/addons', '/settings/general', '/settings/ai', '/settings/mail', '/settings/security'].map((u) => '/admin' + u), 'admin');
    await s.ctx.close();
  }
  if (which === 'all' || which === 'panel') {
    const s = await login(browser, 'owner@bella-italia.demo');
    await run(s.page, ['/dashboard', '/orders', '/orders/kitchen-display', '/orders/new', '/orders/history', '/orders/batch', '/menu', '/menu/categories/create', '/menu/products/create', '/menu/option-groups', '/menu/menus', '/menu/stock', '/menu/import', '/inventory', '/inventory/recipes', '/tables', '/tables/qr', '/tables/map', '/appearance', '/branches', '/reservations', '/reservations/settings', '/customers', '/reviews', '/marketing/promos', '/marketing/campaigns', '/marketing/campaigns/create', '/marketing/banners', '/marketing/pricing', '/marketing/gift-cards', '/marketing/loyalty', '/website', '/reports', '/reports/menu', '/reports/operations', '/reports/visits', '/shifts', '/team', '/team/roles', '/delivery/zones', '/settings/restaurant', '/settings/ordering', '/settings/payments', '/settings/domains', '/integrations', '/subscription', '/activity', '/referrals', '/support', '/profile/two-factor'], 'panel');
    await s.ctx.close();
  }
  await browser.close();
})();
