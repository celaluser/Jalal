// tgwatch eklentisi: kategori logosu — taklit "Edit category" penceresi + Icons/Emoji/Uploaded seçici.
const { chromium } = require('/home/user/Jalal/servis-tasi-test/node_modules/playwright');
const fs = require('fs'); const path = require('path');
const CONTENT = fs.readFileSync(path.resolve(__dirname, '../tgwatch/chrome-extension/content.js'), 'utf8');
let fails = 0;
const ok = (c, m) => { console.log((c ? '  ✓ ' : '  ✗ ') + m); if (!c) fails++; };
const eq = (a, b, m) => { const A = JSON.stringify(a), B = JSON.stringify(b); ok(A === B, m + (A === B ? '' : '\n      beklenen ' + B + '\n      gelen    ' + A)); };
const C = 'https://storage.perfectcdn.com/fsvl4a/';
const zlib = require('zlib');
/** Tek renkli 16x16 PNG. */
function PNG(hex) {
  const [r, g, b] = [0, 2, 4].map((i) => parseInt(hex.slice(i, i + 2), 16));
  const raw = Buffer.alloc(16 * (1 + 16 * 3));
  for (let y = 0; y < 16; y++) { raw[y * 49] = 0; for (let x = 0; x < 16; x++) { const o = y * 49 + 1 + x * 3; raw[o] = r; raw[o + 1] = g; raw[o + 2] = b; } }
  const crcT = []; for (let n = 0; n < 256; n++) { let c = n; for (let k = 0; k < 8; k++) c = c & 1 ? 0xedb88320 ^ (c >>> 1) : c >>> 1; crcT[n] = c >>> 0; }
  const crc = (buf) => { let c = 0xffffffff; for (const x of buf) c = crcT[(c ^ x) & 0xff] ^ (c >>> 8); return (c ^ 0xffffffff) >>> 0; };
  const chunk = (type, data) => { const len = Buffer.alloc(4); len.writeUInt32BE(data.length); const td = Buffer.concat([Buffer.from(type), data]); const cr = Buffer.alloc(4); cr.writeUInt32BE(crc(td)); return Buffer.concat([len, td, cr]); };
  const ihdr = Buffer.alloc(13); ihdr.writeUInt32BE(16, 0); ihdr.writeUInt32BE(16, 4); ihdr[8] = 8; ihdr[9] = 2;
  return Buffer.concat([Buffer.from([137, 80, 78, 71, 13, 10, 26, 10]), chunk('IHDR', ihdr), chunk('IDAT', zlib.deflateSync(raw)), chunk('IEND', Buffer.alloc(0))]);
}
function stub(storage) {
  return `(() => {
    const store = Object.assign({ panel: 'https://panelbaku.com', api: 'x', key: 'k', delay: 0, dry: false, catTop: false }, ${JSON.stringify(storage)});
    const listeners = []; const _st = window.setTimeout.bind(window);
    window.__calls = [];
    const fakeApi = (a, p) => { window.__calls.push({ a, p }); if (a === 'translate') return { ok: true, items: p.items.map((it) => ({ id: it.id, az: 'AZ ' + it.name, via: 'bank', unknown: [] })) }; return { ok: true }; };
    window.chrome = { runtime: { lastError: null, getManifest: () => ({ version: 't' }), sendMessage: (m, cb) => _st(() => cb && cb(m.type === 'api' ? fakeApi(m.action, m.payload || {}) : { ok: true }), 5), onMessage: { addListener: (f) => listeners.push(f) } },
      storage: { local: { get: (d, cb) => { const o = {}; for (const k of Object.keys(d)) o[k] = k in store ? JSON.parse(JSON.stringify(store[k])) : d[k]; _st(() => cb(o), 0); }, set: (o) => Object.assign(store, JSON.parse(JSON.stringify(o))) } } };
    window.__store = store;
    window.__send = (msg) => new Promise((r) => { for (const f of listeners) f(msg, {}, r); });
  })();`;
}
async function page(b, catCfg, storage, file) {
  const p = await b.newPage({ viewport: { width: 1000, height: 700 } });
  const errs = []; p.on('pageerror', (e) => errs.push(String(e))); p.__errs = errs;
  await p.addInitScript((c) => { window.__catCfg = c; }, catCfg || {});
  const GIF = Buffer.from('R0lGODlhAQABAIAAAP///wAAACH5BAEAAAAALAAAAAABAAEAAAICRAEAOw==', 'base64');
  await p.route(/^https:\/\/(storage\.perfectcdn\.com|cdn\.example\.com)\//, (r) => {
    const m = r.request().url().match(/c-([0-9a-f]{6})-/);
    const cors = { 'Access-Control-Allow-Origin': storage && storage.__noCors ? 'https://baska-site.example' : '*' }; // Playwright izni kendiliğinden ekler: "izin yok" için başka siteye ver
    if (m) return r.fulfill({ contentType: 'image/png', headers: cors, body: PNG(m[1]) });
    return r.fulfill({ contentType: 'image/gif', headers: cors, body: GIF });
  });
  await p.goto('file://' + path.join(__dirname, file || 'mock-cat.html'));
  await p.addScriptTag({ content: stub(storage || {}) });
  await p.addScriptTag({ content: CONTENT });
  await p.waitForTimeout(300);
  return p;
}
async function runCat(p) {
  await p.evaluate(() => { window.__lastStatus = ''; window.__tick = setInterval(() => { const s = document.querySelector('.tgw-cbar .tgw-status'); if (s && s.textContent) window.__lastStatus = s.textContent; }, 30); });
  await p.evaluate(() => window.__send({ type: 'tgw-run' }));
  await p.waitForFunction(() => /kaydedildi|deneme|Save|bulunamadı|çevrilemedi/i.test(window.__lastStatus) && !document.querySelector('.tgw-cbar.tgw-running'), null, { timeout: 30000, polling: 100 })
    .catch(async (e) => { console.log('--- ZAMAN AŞIMI:', JSON.stringify(await p.evaluate(() => ({ st: window.__lastStatus, tab: window.__tab, saved: window.__savedCat })))); throw e; });
  await p.waitForTimeout(150);
  return p.evaluate(() => window.__lastStatus);
}
const saved = (p) => p.evaluate(() => window.__savedCat || null);

(async () => {
  const b = await chromium.launch();

  console.log('L1 ad çevrilir + logo: kutuya tıkla → Uploaded → en çok kullanılan TikTok logosu → Save');
  {
    const p = await page(b, { name: 'TikTok Followers' });
    const st = await runCat(p);
    eq(await saved(p), { name: 'AZ TikTok Followers', logo: C + 'tt-a.gif' }, 'kaydedilen ad ve logo (tt-a: 3 kategori, tt-b: 1)');
    eq(await p.evaluate(() => window.__tab), 'uploaded', 'Uploaded sekmesine geçildi');
    eq(await p.evaluate(() => window.__addClicks || 0), 1, 'logo yokken "Add icon"a tıklandı');
    ok(/logo: TikTok/.test(st), 'durum: ' + st);
    ok(p.__errs.length === 0, 'JS hatası yok ' + p.__errs.join(' | '));
    await p.close();
  }

  console.log('L2 kalın Unicode ad ("𝐈𝐧𝐬𝐭𝐚𝐠𝐫𝐚𝐦 ...") → Instagram logosu; "Logo seç" kapalıyken dokunulmaz');
  {
    let p = await page(b, { name: '𝐈𝐧𝐬𝐭𝐚𝐠𝐫𝐚𝐦 Followers [Real]' });
    await runCat(p);
    eq((await saved(p)).logo, C + 'ig.png', 'Instagram logosu');
    await p.close();
    p = await page(b, { name: 'TikTok Followers' }, { catLogo: false });
    await runCat(p);
    eq((await saved(p)).logo, '', 'ayar kapalı: logo yok');
    eq(await p.evaluate(() => window.__logoClicks || 0), 0, 'logo kutusuna tıklanmadı');
    await p.close();
  }

  console.log('L3 mevcut logo: "Mevcut logoyu değiştir" kapalıyken korunur, açıkken değişir');
  {
    let p = await page(b, { name: 'YouTube Views', logo: C + 'other.png' });
    let st = await runCat(p);
    eq((await saved(p)).logo, C + 'other.png', 'mevcut logo korundu');
    ok(/logo zaten var/.test(st), 'durum: ' + st);
    await p.close();
    p = await page(b, { name: 'YouTube Views', logo: C + 'other.png' }, { catLogoReplace: true });
    await runCat(p);
    eq((await saved(p)).logo, C + 'yt.png', 'değiştir açık: YouTube logosu');
    await p.close();
  }

  console.log('L4 öğrenilmemiş platform (Netflix) ve platformsuz ad: logo seçilmez, ad yine kaydedilir');
  {
    let p = await page(b, { name: 'Netflix Premium Account' });
    let st = await runCat(p);
    eq(await saved(p), { name: 'AZ Netflix Premium Account', logo: '' }, 'ad kaydedildi, logo yok');
    ok(/öğrenilmiş logo yok/.test(st), 'durum: ' + st);
    await p.close();
    p = await page(b, { name: 'Premium Paketler' });
    st = await runCat(p);
    eq((await saved(p)).logo, '', 'platformsuz: logo yok');
    await p.close();
  }

  console.log('L5 deneme modu: ad ve logo yazılır, Save\'e basılmaz');
  {
    const p = await page(b, { name: 'TikTok Followers' }, { dry: true });
    await runCat(p);
    eq(await saved(p), null, 'Save yok');
    eq(await p.evaluate(() => (document.querySelector('#logo img') || {}).src || ''), C + 'tt-a.gif', 'logo seçildi (kaydedilmedi)');
    await p.close();
  }

  console.log('L7 Uploaded\'daki resimler önizleme adıyla ("nylack51yquk11yq_thumb.gif") gelse de eşleşir');
  {
    const p = await page(b, { name: 'TikTok Followers', thumbs: true, longNames: true });
    await runCat(p);
    eq((await saved(p)).logo, C + 'nylack51yquk11yq_thumb.gif', 'önizleme adresiyle eşleşti');
    await p.close();
  }

  console.log('L8 adı zaten Azerice: ad değişmez, logo seçilip kaydedilir, en üste taşınmaz');
  {
    const p = await page(b, { name: 'YouTube Baxış [ Yeni Native ads ] Sürətli Working' });
    const st = await runCat(p);
    eq(await saved(p), { name: 'YouTube Baxış [ Yeni Native ads ] Sürətli Working', logo: C + 'yt.png' }, 'ad aynı, logo YouTube, kaydedildi');
    ok(/ad zaten Azerice · logo: YouTube · kaydedildi/.test(st), 'durum: ' + st);
    eq((await p.evaluate(() => window.__calls.filter((c) => c.a === 'translate'))).length, 0, 'çeviri isteği yok');
    await p.close();
  }

  console.log('L9 listedeki ve Uploaded\'daki adres farklı: resim içeriğine göre eşleşir; sunucu izin vermezse seçmez');
  {
    const cats9 = [['YouTube Baxış', 'c-ff0000-list.png'], ['YouTube Abunə', 'c-ff0000-list.png'], ['Instagram', 'c-800080-list.png']];
    const up9 = ['c-000000-a.png', 'c-800080-b.png', 'c-ff0000-c.png', 'c-00aa00-d.png'];
    let p = await page(b, { name: 'YouTube Views', cats: cats9, uploaded: up9 });
    let st = await runCat(p);
    eq((await saved(p)).logo, C + 'c-ff0000-c.png', 'kırmızı YouTube logosu içerikten bulundu');
    ok(/logo: YouTube/.test(st), 'durum: ' + st);
    await p.close();
    // önbellekten gelmesin diye yeni adresler
    p = await page(b, { name: 'YouTube Views', cats: cats9.map(([n, f]) => [n, f.replace('list', 'list2')]), uploaded: up9.map((f) => f.replace('.png', '2.png')) }, { __noCors: true });
    st = await runCat(p);
    eq((await saved(p)).logo, '', 'CORS yok: yanlış logo seçilmedi');
    ok(/bulunamadı/.test(st), 'durum: ' + st);
    await p.close();
  }

  console.log('L10 elle seçilen logo öğrenilir: kullanıcı Telegram\'a logo seçip Save\'e basar → sonraki Telegram kategorisinde kullanılır');
  {
    const p = await page(b, { name: 'Telegram Members' });
    let st = await runCat(p);
    ok(/öğrenilmiş logo yok/.test(st), 'önce bilinmiyor: ' + st);
    await p.evaluate(() => window.__reopen('Telegram Members'));
    await p.click('#addicon'); await p.click('a[data-t=uploaded]'); await p.click('#grid img[data-f="tg.png"]'); await p.click('#csave');
    await p.waitForTimeout(300);
    eq((await p.evaluate(() => window.__store.logoPick || {})).telegram, C + 'tg.png', 'elle seçim öğrenildi');
    await p.evaluate(() => window.__reopen('Telegram Premium Members'));
    st = await runCat(p);
    eq((await saved(p)).logo, C + 'tg.png', 'sonraki Telegram kategorisi: öğrenilen logo');
    await p.close();
  }

  console.log('L11 gecikmeli yüklenen resimler (data-src) de okunur');
  {
    const p = await page(b, { name: 'TikTok Followers', lazy: true });
    await runCat(p);
    eq((await saved(p)).logo, C + 'tt-a.gif', 'data-src ile eşleşti');
    await p.close();
  }

  console.log('L12 gerçek yapı (select-icon__popup, React-Virtualized ızgara, seçim mousedown ile): YouTube logosu seçilir');
  {
    const p = await page(b, { name: 'YouTube Views' }, {}, 'mock-cat2.html');
    const st = await runCat(p);
    eq(await saved(p), { name: 'AZ YouTube Views', logo: C + 'nylack51yquk11yq.gif' }, 'kaydedilen logo: listedeki YouTube logosu');
    eq(await p.evaluate(() => window.__tab), 'uploaded', 'Uploaded sekmesi');
    ok(/logo: YouTube/.test(st), 'durum: ' + st);
    ok(p.__errs.length === 0, 'JS hatası yok');
    await p.close();
    const q = await page(b, { name: '𝐓𝐢𝐤𝐓𝐨𝐤 Followers' }, {}, 'mock-cat2.html');
    await runCat(q);
    eq((await saved(q)).logo, C + 'ymw9y8g5ztat5h85.gif', 'TikTok logosu');
    await q.close();
  }

  console.log('L6 "Logoları şimdi öğren": liste gezilir, platform logoları kaydedilir');
  {
    const p = await page(b, { name: 'x' });
    const r = await p.evaluate(() => window.__send({ type: 'tgw-learn-logos' }));
    ok(r.ok && r.categories === 8, 'öğrenilen kategori: ' + JSON.stringify(r));
    eq([...r.platforms].sort(), ['facebook', 'instagram', 'tiktok', 'youtube'], 'platformlar');
    await p.close();
  }

  await b.close();
  console.log('\n' + (fails ? '✗ ' + fails + ' başarısız' : '✓ hepsi geçti'));
  process.exit(fails ? 1 : 0);
})().catch((e) => { console.error(e); process.exit(2); });
