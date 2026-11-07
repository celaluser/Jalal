// Kategori Temizle — gerçek eklenti + React taklit paneli ile uçtan uca testler.
const { chromium } = require('playwright');
const fs = require('fs');
const os = require('os');
const path = require('path');
const crypto = require('crypto');

const EXT = path.resolve(__dirname, '../kategori-temizle');
const URL = 'https://panelbaku.com/admin/services';
let fails = 0;
const want = (t) => !process.argv[2] || process.argv[2] === t;
const ok = (c, msg) => { console.log((c ? '  ✓ ' : '  ✗ ') + msg); if (!c) fails++; };
const eq = (a, b, msg) => { const A = JSON.stringify(a), B = JSON.stringify(b); ok(A === B, msg + (A === B ? '' : '\n      beklenen ' + B + '\n      gelen    ' + A)); };
const sorted = (a) => [...a].sort();

// --- veri: aktif/pasif, dolu/boş, NEW rozeti, 2. sayfa, özel satır
let id = 0;
const C = (name, o = {}) => Object.assign({ id: ++id, name, active: false, services: 0 }, o);
function cats() {
  id = 0;
  return [
    C('Instagram Followers', { active: true, services: 4 }),
    C('TikTok Likes', { services: 3 }),                               // pasif DOLU → kalır
    C('Old Telegram', { badgeBefore: true }),                         // NEW rozeti üst simge harflerle (ᴺᴱᵂ)                         // SİL
    C('Youtube Views', { active: true }),                             // aktif boş → kalır
    C('Facebook Page', { badgeAfter: true }),                         // SİL
    C('Twitter Soft', { active: true, color: '#cfd6e4' }),            // aktif ama biraz soluk → kalır
    C('Spotify Plays'),                                               // SİL (hata testinde başarısız)
    C('Discord Members', { cls: 'is-disabled' }),                     // SİL (sınıfla pasif)
    C('Snapchat', { active: true, services: 2 }),
    C('🔥 Kick Viewers'),                                             // SİL
    C('Twitch', { active: true, services: 1 }),
    C('Pinterest', { services: 1 }),                                  // pasif dolu → kalır
    C('Reddit', { active: true }),
    C('LinkedIn', { active: true, services: 2 }),
    C('Quora', { active: true }),
    // ---- 2. sayfa (kaydırınca yüklenir)
    C('Late Empty Inactive'),                                         // SİL
    C('Late Full Inactive', { services: 2 }),
    C('Late Active', { active: true, services: 1 }),
    C('Late Active Empty', { active: true }),
    C('Not Assigned'),                                                // özel → kalır
  ];
}
const EXPECT = ['Old Telegram', 'Facebook Page', 'Spotify Plays', 'Discord Members', '🔥 Kick Viewers', 'Late Empty Inactive'];

async function newPage(ctx, cfg) {
  const page = await ctx.newPage();
  await page.addInitScript((c) => { window.__cfg = c; }, Object.assign({ cats: cats() }, cfg));
  await page.addInitScript(() => { try { localStorage.clear(); } catch (e) { /* yok */ } });
  await page.route('https://panelbaku.com/**', async (route) => {
    const u = new globalThis.URL(route.request().url());
    if (u.pathname === '/_react.js') return route.fulfill({ contentType: 'text/javascript', body: fs.readFileSync(path.join(__dirname,'node_modules/react/umd/react.development.js')) });
    if (u.pathname === '/_react-dom.js') return route.fulfill({ contentType: 'text/javascript', body: fs.readFileSync(path.join(__dirname,'node_modules/react-dom/umd/react-dom.development.js')) });
    if (u.pathname === '/admin/services') return route.fulfill({ contentType: 'text/html', body: fs.readFileSync(path.join(__dirname, 'mock.html')) });
    return route.fulfill({ status: 404, body: 'x' });
  });
  const errors = [];
  page.on('pageerror', (er) => errors.push(String(er)));
  page.__errors = errors;
  await page.goto(URL);
  await page.waitForSelector('#kt-host', { state: 'attached' });
  return page;
}
const bar = (page, sel) => page.locator('#kt-host >> ' + sel);
const status = (page) => bar(page, '#st').innerText();
async function scan(page) {
  await bar(page, '[data-a=scan]').click();
  await page.waitForFunction(() => { const s = document.querySelector('#kt-host').shadowRoot.querySelector('#st'); return s && /bulundu|yok|⚠/.test(s.textContent); }, null, { timeout: 60000, polling: 200 });
}
const done = (page) => page.waitForFunction(() => /Bitti|Durduruldu\./.test(document.querySelector('#kt-host').shadowRoot.querySelector('#st').textContent), null, { timeout: 120000, polling: 200 }).catch(async (e) => { console.log('--- ZAMAN AŞIMI, çubuk:\n' + await page.locator('#kt-host >> #bar').innerText().catch(() => '?') + '\nsunucu: ' + JSON.stringify(await page.evaluate(() => ({ d: window.__server.deleted, a: window.__server.attempts, o: window.__server.dialogsOpened }))) ); throw e; });
const server = (page) => page.evaluate(() => JSON.parse(JSON.stringify(window.__server)));
const setDry = async (page, v) => { const cb = bar(page, '[data-s=dry]'); if ((await cb.isChecked()) !== v) await cb.click(); };
const setMove = async (page, v) => { const cb = bar(page, '[data-s=move]'); if ((await cb.isChecked()) !== v) await cb.click(); };
const FULL = ['TikTok Likes', 'Pinterest', 'Late Full Inactive']; // pasif + dolu
const resText = (page) => bar(page, '#ktRes').innerText().catch(() => '');
const lists = (page) => bar(page, '.list').first().innerText();

(async () => {
  const dir = fs.mkdtempSync(path.join(os.tmpdir(), 'kt-'));
  const ctx = await chromium.launchPersistentContext(dir, {
    channel: 'chromium', headless: !process.env.HEADED, viewport: { width: 1100, height: 520 },
    args: ['--disable-extensions-except=' + EXT, '--load-extension=' + EXT, '--no-sandbox'],
    // HEADED: Playwright'ın arka plan kısıtlamasını kapatan bayrakları kaldır (gerçek Chrome gibi kısılsın)
    ignoreDefaultArgs: process.env.HEADED ? ['--disable-background-timer-throttling', '--disable-backgrounding-occluded-windows', '--disable-renderer-backgrounding'] : [],
  });
  const t0 = Date.now();

  console.log('T1 tarama: yalnızca pasif+boş listelenir, hiçbir şey silinmez');
  if (want('T1')) {
    const p = await newPage(ctx, {});
    await scan(p);
    const names = (await bar(p, '.list').first().locator('div').allInnerTexts());
    eq(sorted(names), sorted(EXPECT), 'aday listesi');
    ok(/6 boş pasif/.test(await bar(p, 'small:has(b)').first().innerText()), 'özet: 6 boş pasif');
    const s = await server(p);
    eq(s.attempts, [], 'sunucuya silme isteği gitmedi');
    ok((await bar(p, '[data-a=run]').innerText()).includes('(6)'), 'düğme: (6)');
    ok(p.__errors.length === 0, 'sayfada JS hatası yok ' + p.__errors.join('|'));
    await p.close();
  }

  console.log('T2 deneme modu (varsayılan): menü + pencere açılır, Delete yerine iptal; hiçbir şey silinmez');
  if (want('T2')) {
    const p = await newPage(ctx, {});
    ok(await bar(p, '[data-s=dry]').isChecked(), 'deneme modu varsayılan açık');
    await scan(p);
    await bar(p, '[data-a=run]').click(); await done(p);
    const s = await server(p);
    eq(s.deleted, [], 'silinen yok'); eq(s.attempts, [], 'silme isteği yok');
    eq(sorted(s.dialogsOpened), sorted(EXPECT), 'her aday için onay penceresi açıldı');
    eq(sorted(s.dialogsCancelled), sorted(EXPECT), 'hepsi iptal edildi');
    eq(s.serviceMenuDeletes, 0, 'servis menüsüne dokunulmadı');
    await p.close();
  }

  console.log('T3 gerçek silme: yalnızca boş+pasif silinir; aktif/dolu/Not Assigned dokunulmaz');
  if (want('T3')) {
    const p = await newPage(ctx, { startExpanded: true });
    await scan(p); await setDry(p, false);
    await bar(p, '[data-a=run]').click(); await done(p);
    const s = await server(p);
    eq(sorted(s.deleted), sorted(EXPECT), 'silinenler tam beklenen küme (servisler açık başladı)');
    eq(s.violations, [], 'aktif/dolu kategoriye silme isteği gitmedi');
    eq(sorted(s.attempts), sorted(EXPECT), 'yalnızca aday olanlara istek gitti');
    ok(s.cats.some((c) => c.name === 'Not Assigned'), 'Not Assigned duruyor');
    eq(s.cats.length, 20 - 6, 'kalan kategori sayısı');
    ok(/6 silindi, 0 deneme, 0 atlandı, 0 hata/.test(await status(p)), 'özet: ' + await status(p));
    ok(p.__errors.length === 0, 'JS hatası yok');
    await p.close();
  }

  console.log('T4 sunucu hatası: o kategori "hata" sayılır, sıradakine geçilir');
  if (want('T4')) {
    const p = await newPage(ctx, { failNames: ['Spotify Plays'] });
    await scan(p); await setDry(p, false);
    await bar(p, '[data-a=run]').click(); await done(p);
    const s = await server(p);
    eq(sorted(s.deleted), sorted(EXPECT.filter((n) => n !== 'Spotify Plays')), 'diğerleri silindi');
    ok(s.cats.some((c) => c.name === 'Spotify Plays'), 'Spotify Plays duruyor');
    const st = await status(p);
    ok(/5 silindi, 0 deneme, 0 atlandı, 1 hata/.test(st), 'özet: ' + st);
    ok(/Spotify Plays/.test(await lists(p).catch(() => '')) || (await bar(p, '.list').allInnerTexts()).join().includes('Spotify Plays'), 'hata listesinde Spotify Plays');
    ok((await bar(p, '[data-a=diag]').count()) === 1, '"Teşhisi kopyala" düğmesi çıktı');
    await p.close();
  }

  console.log('T5 Durdur: sıradaki silmeden önce durur');
  if (want('T5')) {
    const p = await newPage(ctx, { delay: 700 });
    await scan(p); await setDry(p, false);
    await bar(p, '[data-a=run]').click();
    await p.waitForFunction(() => window.__server.deleted.length >= 1, null, { timeout: 30000 });
    await bar(p, '[data-a=stop]').click(); await done(p);
    const s = await server(p);
    ok(s.deleted.length >= 1 && s.deleted.length < 6, 'bir kısmı silindi, hepsi değil (' + s.deleted.length + ')');
    eq(s.violations, [], 'ihlal yok');
    ok(/Durduruldu/.test(await status(p)), 'durum: ' + await status(p));
    await p.waitForTimeout(1500);
    eq((await server(p)).deleted.length, s.deleted.length, 'durduktan sonra yeni silme yok');
    await p.close();
  }

  console.log('T6 en çok N=2 + iç kutuda kayan liste');
  if (want('T6')) {
    const p = await newPage(ctx, { virtual: true, startExpanded: true });
    await scan(p);
    eq(sorted(await bar(p, '.list').first().locator('div').allInnerTexts()), sorted(EXPECT), 'iç kaydırmalı listede de aynı adaylar');
    await setDry(p, false);
    await bar(p, '[data-s=max]').fill('2'); await bar(p, '[data-s=max]').dispatchEvent('change');
    await bar(p, '[data-a=run]').click(); await done(p);
    const s = await server(p);
    eq(s.deleted.length, 2, 'yalnızca 2 silindi');
    eq(s.violations, [], 'ihlal yok');
    await p.close();
  }

  console.log('T7 silmeden hemen önce yeniden kontrol: aday sonradan aktif/dolu olursa atlanır');
  if (want('T7')) {
    const p = await newPage(ctx, {});
    await scan(p); await setDry(p, false);
    await p.evaluate(() => { window.__mutate('Old Telegram', { active: true }); window.__mutate('Facebook Page', { services: 2 }); window.__mutate('Spotify Plays', { name: 'Spotify Renamed' }); });
    await bar(p, '[data-a=run]').click(); await done(p);
    const s = await server(p);
    eq(sorted(s.deleted), sorted(['Discord Members', '🔥 Kick Viewers', 'Late Empty Inactive']), 'yalnızca hâlâ uygun olanlar silindi');
    eq(s.violations, [], 'ihlal yok');
    const txt = (await bar(p, '.list').allInnerTexts()).join('\n');
    ok(/Old Telegram[\s\S]*aktif/.test(txt), 'Old Telegram: aktif olduğu için atlandı');
    ok(/Facebook Page[\s\S]*2 servis/.test(txt), 'Facebook Page: dolu olduğu için atlandı');
    ok(/Spotify Plays[\s\S]*ad değişmiş/.test(txt), 'Spotify Plays: ad değişti, atlandı');
    await p.close();
  }

  console.log('T8 pencerede başka kategori adı varsa silmez');
  if (want('T8')) {
    const p = await newPage(ctx, {});
    await scan(p); await setDry(p, false);
    // sahte panel: Old Telegram'ın onay penceresi başka kategoriyi gösterir
    await p.evaluate(() => {
      const mo = new MutationObserver(() => { document.querySelectorAll('.modal p').forEach((el) => { if (el.textContent.includes('Old Telegram')) el.textContent = 'Are you sure you want to delete category "Instagram Followers"?'; }); });
      mo.observe(document.body, { childList: true, subtree: true, characterData: true });
    });
    await bar(p, '[data-a=run]').click(); await done(p);
    const s = await server(p);
    ok(!s.deleted.includes('Old Telegram') && !s.attempts.includes('Old Telegram'), 'Old Telegram silinmedi');
    ok(s.deleted.length === 5, 'diğer 5 silindi');
    await p.close();
  }

  console.log('T9 pencerede ad hiç görünmezse (panel böyle olabilir) menü satıra bağlı olduğu için devam eder');
  if (want('T9')) {
    const p = await newPage(ctx, { dialogShowsName: false });
    await scan(p); await setDry(p, false);
    await bar(p, '[data-a=run]').click(); await done(p);
    const s = await server(p);
    eq(sorted(s.deleted), sorted(EXPECT), 'beklenen küme silindi');
    eq(s.violations, [], 'ihlal yok');
    await p.close();
  }

  console.log('T10 mini panel (eklenti simgesi): bul → dene → ayarlar');
  if (want('T10')) {
    const p = await newPage(ctx, {});
    const extId = (() => { const h = crypto.createHash('sha256').update(EXT).digest('hex').slice(0, 32); return [...h].map((c) => String.fromCharCode('a'.charCodeAt(0) + parseInt(c, 16))).join(''); })();
    const pop = await ctx.newPage();
    await pop.goto('chrome-extension://' + extId + '/popup.html');
    const tabId = await pop.evaluate(async () => (await chrome.tabs.query({ url: 'https://panelbaku.com/*' }))[0].id);
    await pop.goto('chrome-extension://' + extId + '/popup.html?tab=' + tabId);
    await pop.waitForFunction(() => !document.getElementById('ui').hidden);
    await pop.click('#scan');
    await pop.waitForFunction(() => /bulundu/.test(document.getElementById('st').textContent), null, { timeout: 60000 });
    eq(sorted(await pop.locator('#list div').allInnerTexts()), sorted(EXPECT), 'mini panelde aday listesi');
    ok(/Dene \(6\)/.test(await pop.locator('#run').innerText()), 'düğme: Dene (6)');
    await pop.fill('#max', '3'); await pop.dispatchEvent('#max', 'change');
    await pop.waitForFunction(() => /\(3\)/.test(document.getElementById('run').textContent));
    ok(true, 'en çok N=3 → düğme (3)');
    await pop.click('#run');
    await pop.waitForFunction(() => /Bitti/.test(document.getElementById('st').textContent), null, { timeout: 60000 });
    const s = await server(p);
    eq(s.deleted, [], 'mini panelde de deneme modu silmedi');
    eq(s.dialogsCancelled.length, 3, '3 kategoride iptal edildi');
    const dgn = await pop.evaluate(async (tab) => new Promise((r) => chrome.tabs.sendMessage(tab, { kt: true, cmd: 'diag' }, r)), tabId);
    let dj = null; try { dj = JSON.parse(dgn.text); } catch (e) { /* yok */ }
    ok(dj && dj.surum === '1.2.0' && dj.satirTurleri && dj.actionsZinciri, 'teşhis metni hatasız üretildi (JSON)');
    await pop.close(); await p.close();
  }

  console.log('T12 koyu tema + sanallaştırılmış liste: tam silme');
  if (want('T12')) {
    const p = await newPage(ctx, { dark: true, virtual: true, noDisabledClass: true });
    await scan(p); await setDry(p, false);
    eq(sorted(await bar(p, '.list').first().locator('div').allInnerTexts()), sorted(EXPECT), 'koyu temada da aynı adaylar');
    await bar(p, '[data-a=run]').click(); await done(p);
    const s = await server(p);
    eq(sorted(s.deleted), sorted(EXPECT), 'silinenler');
    eq(s.violations, [], 'ihlal yok');
    ok(s.cats.some((c) => c.name === 'Not Assigned'), 'Not Assigned duruyor');
    await p.close();
  }

  console.log('T13 servis sayısı okunamayan kategori boş sayılmaz');
  if (want('T13')) {
    const base = cats(); base.find((c) => c.name === 'Old Telegram').collapseText = 'Genişlet';
    const p = await newPage(ctx, { cats: base });
    await scan(p);
    eq(sorted(await bar(p, '.list').first().locator('div').allInnerTexts()), sorted(EXPECT.filter((n) => n !== 'Old Telegram')), 'Old Telegram aday değil');
    ok(/okunamadı/.test(await bar(p, 'small').allInnerTexts().then((a) => a.join(' '))), 'not düşüldü');
    await p.close();
  }

  console.log('T14 aynı (normalize) adlı iki boş pasif kategori kimlikle ayrı ayrı silinir; teşhis hatasız üretilir');
  if (want('T14')) {
    const base = cats(); base.splice(2, 0, { id: 901, name: 'Dup Cat [ YENİ ]', active: false, services: 0 }, { id: 902, name: 'Dup Cat [ Yeni ]', active: false, services: 0 });
    const p = await newPage(ctx, { cats: base, virtual: true });
    await scan(p); await setDry(p, false);
    ok((await bar(p, '.list').first().locator('div').allInnerTexts()).filter((x) => /Dup Cat/.test(x)).length === 2, 'iki Dup Cat da aday');
    await bar(p, '[data-a=run]').click(); await done(p);
    const s = await server(p);
    ok(s.deleted.includes('Dup Cat [ YENİ ]') && s.deleted.includes('Dup Cat [ Yeni ]'), 'ikisi de silindi');
    eq(s.violations, [], 'ihlal yok');
    await p.close();
  }

  console.log('T15 sekme arka plandayken (başka sekme önde) tarama + silme sürer; beklemeler service worker ile');
  if (want('T15')) {
    const sw = ctx.serviceWorkers()[0] || await ctx.waitForEvent('serviceworker');
    const t0sw = await sw.evaluate(() => self.__ticks);
    const p = await newPage(ctx, { virtual: true, delay: 500, moveDelay: 600 });
    // aynı pencerede yeni sekme aç ve öne getir (gerçek sekme değişimi)
    const otherP = ctx.waitForEvent('page');
    await sw.evaluate(async () => { const [t] = await chrome.tabs.query({ url: 'https://panelbaku.com/*' }); await chrome.tabs.create({ url: 'about:blank', active: true, windowId: t.windowId }); });
    const other = await otherP;
    await p.waitForFunction(() => document.visibilityState === 'hidden', null, { timeout: 5000, polling: 100 }).catch(() => {});
    console.log('    (bilgi) panel sekmesi visibilityState: ' + await p.evaluate(() => document.visibilityState) + ' — Playwright sayfaları görünür gösterir; gerçek gizlilik burada ölçülemez)');
    // düğmelere arka plandayken bas (mini panelin yaptığı gibi): tıklama yerine DOM click
    await p.evaluate(() => document.querySelector('#kt-host').shadowRoot.querySelector('[data-a=scan]').click());
    await p.waitForFunction(() => /bulundu|yok|⚠/.test(document.querySelector('#kt-host').shadowRoot.querySelector('#st').textContent), null, { timeout: 120000, polling: 300 });
    await p.evaluate(() => { const r = document.querySelector('#kt-host').shadowRoot; r.querySelector('[data-s=dry]').click(); r.querySelector('[data-s=move]').click(); });
    await p.evaluate(() => document.querySelector('#kt-host').shadowRoot.querySelector('[data-a=run]').click());
    await done(p);
    const s = await server(p);
    eq(sorted(s.deleted), sorted(EXPECT.concat(FULL)), 'arka planda da tam küme silindi (taşıma dahil)');
    eq(s.moves.length, 3, 'arka planda 3 taşıma');
    if (s.deleted.length !== EXPECT.length + FULL.length) console.log(await p.evaluate(() => document.querySelector('#kt-host').shadowRoot.querySelector('#bar').innerText));
    eq(s.violations, [], 'ihlal yok');
    const ticks = (await sw.evaluate(() => self.__ticks)) - t0sw;
    ok(ticks > 50, 'beklemeler service worker üzerinden (' + ticks + ' uyanış)');
    await other.close(); await p.close();
  }

  console.log('T16 taşıma: dolu pasiflerin servisleri Not Assigned\'a, sonra kategori silinir (sanallaştırılmış liste)');
  if (want('T16')) {
    const p = await newPage(ctx, { virtual: true });
    await scan(p);
    eq(sorted(await bar(p, '#ktFull div').allInnerTexts()), sorted(['TikTok Likes (3)', 'Pinterest (1)', 'Late Full Inactive (2)']), 'dolu pasif listesi (sayılarıyla)');
    await setMove(p, true); await setDry(p, false);
    ok((await bar(p, '[data-a=run]').innerText()).includes('(9)'), 'düğme: 6 boş + 3 dolu = (9)');
    await bar(p, '[data-a=run]').click(); await done(p);
    const s = await server(p);
    eq(sorted(s.deleted), sorted(EXPECT.concat(FULL)), 'boşlar + taşınanlar silindi');
    eq(sorted(s.moves.map((m) => m.from + '>' + m.to + ':' + m.n)), sorted(['TikTok Likes>Not Assigned:3', 'Pinterest>Not Assigned:1', 'Late Full Inactive>Not Assigned:2']), 'taşımalar doğru hedefe, doğru sayıda');
    eq(s.violations, [], 'ihlal yok (aktiften taşıma / dolu silme yok)');
    eq(s.massDeleteAll, 0, '"Delete all"a hiç basılmadı');
    eq(s.cats.find((c) => c.name === 'Not Assigned').services, 6, 'Not Assigned 6 servis aldı');
    ok(['Instagram Followers', 'Snapchat', 'Twitch', 'LinkedIn', 'Late Active'].every((n) => s.cats.find((c) => c.name === n)), 'aktif dolu kategoriler duruyor');
    eq(await p.evaluate(() => window.__selCount()), 0, 'sonda seçili servis yok');
    ok(/3 taşındı/.test(await status(p)), 'özet: ' + await status(p));
    await p.close();
  }

  console.log('T17 taşıma deneme modu: Assign penceresine kadar gider, Close; hiçbir şey taşınmaz/silinmez');
  if (want('T17')) {
    const p = await newPage(ctx, {});
    await scan(p); await setMove(p, true);
    await bar(p, '[data-a=run]').click(); await done(p);
    const s = await server(p);
    eq(s.moves, [], 'taşıma yok'); eq(s.deleted, [], 'silme yok'); eq(s.attempts, [], 'silme isteği yok');
    eq(s.assignOpened, 3, '3 kez Assign penceresi açıldı'); eq(s.assignClosed, 3, '3 kez Close');
    eq(sorted(s.selectAllClicks), sorted(FULL), '"Select all category services" yalnızca dolu pasiflere');
    eq(await p.evaluate(() => window.__selCount()), 0, 'seçim temizlendi');
    eq(s.massDeleteAll, 0, '"Delete all" yok');
    ok(/9 deneme/.test(await status(p)), 'özet: ' + await status(p));
    await p.close();
  }

  console.log('T18 seçilen sayı ≠ Expand (N) → atla, taşıma yok');
  if (want('T18')) {
    const p = await newPage(ctx, { selectShort: ['TikTok Likes'] });
    await scan(p); await setMove(p, true); await setDry(p, false);
    await bar(p, '[data-a=run]').click(); await done(p);
    const s = await server(p);
    ok(!s.moves.some((m) => m.from === 'TikTok Likes') && !s.deleted.includes('TikTok Likes'), 'TikTok Likes taşınmadı/silinmedi');
    ok(/TikTok Likes[^\n]*seçilen servis sayısı \(2\) ≠ kategorideki \(3\)/.test(await resText(p)), 'neden yazıldı');
    eq(sorted(s.moves.map((m) => m.from)), ['Late Full Inactive', 'Pinterest'], 'diğerleri taşındı');
    eq(await p.evaluate(() => window.__selCount()), 0, 'seçim temizlendi');
    eq(s.violations, [], 'ihlal yok');
    await p.close();
  }

  console.log('T19 taşıma sunucu hatası → hata, kategori silinmez, sıradakine geçilir');
  if (want('T19')) {
    const p = await newPage(ctx, { moveFail: ['Pinterest'] });
    await scan(p); await setMove(p, true); await setDry(p, false);
    await bar(p, '[data-a=run]').click(); await done(p);
    const s = await server(p);
    ok(!s.deleted.includes('Pinterest') && s.cats.find((c) => c.name === 'Pinterest').services === 1, 'Pinterest duruyor, servisi yerinde');
    ok(/Hata[\s\S]*Pinterest/.test(await resText(p)), 'hata listesinde Pinterest');
    ok(s.deleted.includes('TikTok Likes') && s.deleted.includes('Late Full Inactive'), 'diğer dolu pasifler taşınıp silindi');
    eq(s.violations, [], 'ihlal yok');
    await p.close();
  }

  console.log('T20 soluk görünse de panel menüsü "Disable category" diyorsa (aktif) dokunulmaz');
  if (want('T20')) {
    const base = cats();
    base.find((c) => c.name === 'Late Full Inactive').menuActive = true;
    base.find((c) => c.name === 'Spotify Plays').menuActive = true;
    const p = await newPage(ctx, { cats: base });
    await scan(p); await setMove(p, true); await setDry(p, false);
    await bar(p, '[data-a=run]').click(); await done(p);
    const s = await server(p);
    ok(!s.deleted.includes('Spotify Plays') && !s.deleted.includes('Late Full Inactive'), 'ikisi de silinmedi');
    ok(!s.moves.some((m) => m.from === 'Late Full Inactive'), 'Late Full Inactive taşınmadı');
    const rt = await resText(p);
    ok(/Spotify Plays[^\n]*AKTİF/.test(rt) && /Late Full Inactive[^\n]*AKTİF/.test(rt), 'neden: panel AKTİF gösteriyor');
    eq(s.violations, [], 'ihlal yok');
    await p.close();
  }

  console.log('T11 Services dışı sayfada çubuk yok');
  if (want('T11')) {
    const p = await ctx.newPage();
    await p.route('https://panelbaku.com/**', (r) => r.fulfill({ contentType: 'text/html', body: '<body>x</body>' }));
    await p.goto('https://panelbaku.com/admin/orders');
    await p.waitForTimeout(1200);
    eq(await p.locator('#kt-host').count(), 0, 'çubuk eklenmedi');
    await p.close();
  }

  await ctx.close();
  console.log('\n' + (fails ? '✗ ' + fails + ' başarısız' : '✓ hepsi geçti') + '  (' + ((Date.now() - t0) / 1000).toFixed(0) + ' sn)');
  process.exit(fails ? 1 : 0);
})().catch((e) => { console.error(e); process.exit(2); });
