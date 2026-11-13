// tgwatch — Logo tablosu sayfası (logos.html): sabitleme, kararsız platformda seçim, otomatiğe dönüş, özel kural.
// Çalıştır: node logos-test.js [ekran-goruntusu.png]
const { chromium } = require('/home/user/Jalal/servis-tasi-test/node_modules/playwright');
const path = require('path');
let fails = 0;
const ok = (c, m) => { console.log((c ? '  ✓ ' : '  ✗ ') + m); if (!c) fails++; };
const eq = (a, b, m) => { const A = JSON.stringify(a), B = JSON.stringify(b); ok(A === B, m + (A === B ? '' : '\n      beklenen ' + B + '\n      gelen    ' + A)); };
const C = 'https://storage.perfectcdn.com/fsvl4a/';
const PNG = (hex) => { // tek renk 8x8 PNG yerine SVG: sayfada farklı renkli logolar görünsün
  return Buffer.from('<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 8 8"><rect width="8" height="8" rx="2" fill="#' + hex + '"/></svg>');
};
const COL = { 'tt-a.gif': '111111', 'tt-b.png': 'ee1d52', 'ig.png': 'c13584', 'yt.png': 'ff0000', 'fb.gif': '1877f2', 'tg.png': '26a5e4', 'other.png': '888888', 'eski.png': 'bbbbbb' };
(async () => {
  const b = await chromium.launch();
  const p = await b.newPage({ viewport: { width: 900, height: 900 }, deviceScaleFactor: 1 });
  const errs = []; p.on('pageerror', (e) => errs.push(String(e)));
  await p.route(/^https:\/\/storage\.perfectcdn\.com\//, (r) => r.fulfill({ contentType: 'image/svg+xml', body: PNG(COL[r.request().url().split('/').pop()] || '999999') }));
  await p.addInitScript((C) => {
    const all = {}; for (const f of ['ig.png', 'yt.png', 'tt-b.png', 'fb.gif', 'tt-a.gif', 'tg.png', 'other.png']) all[f] = { u: C + f, up: 1 };
    all['eski.png'] = { u: C + 'eski.png', up: 0 };
    const store = window.__store = {
      logoPlatforms: [['tiktok', 'TikTok'], ['instagram', 'Instagram'], ['youtube', 'YouTube'], ['facebook', 'Facebook'], ['telegram', 'Telegram'], ['spotify', 'Spotify']],
      logoStat: { tiktok: [{ url: C + 'tt-a.gif', n: 12 }, { url: C + 'tt-b.png', n: 2 }], instagram: [{ url: C + 'ig.png', n: 30 }], youtube: [{ url: C + 'yt.png', n: 9 }], facebook: [{ url: C + 'eski.png', n: 4 }] },
      logoPick: { youtube: C + 'yt.png' }, logoAll: all, logoRules: [],
    };
    const ls = [];
    window.chrome = { storage: {
      local: { get: (d, cb) => { const o = {}; for (const k of Object.keys(d)) o[k] = k in store ? JSON.parse(JSON.stringify(store[k])) : d[k]; cb(o); },
        set: (o, cb) => { const ch = {}; for (const k of Object.keys(o)) { ch[k] = {}; store[k] = JSON.parse(JSON.stringify(o[k])); } if (cb) cb(); for (const f of ls) f(ch, 'local'); } },
      onChanged: { addListener: (f) => ls.push(f) } } };
  }, C);
  await p.goto('file://' + path.resolve(__dirname, '../tgwatch/chrome-extension/logos.html'));
  await p.waitForSelector('#used li');
  const row = (k) => p.locator('#used li[data-key="' + k + '"]');
  const state = async (k) => p.locator('li[data-key="' + k + '"]').getAttribute('data-state');
  const store = () => p.evaluate(() => window.__store);

  console.log('T1 tablo: kararsız platform en üstte, sabit/otomatik/yok durumları, Uploaded\'da olmayan logo uyarısı');
  eq(await p.locator('#used li').evaluateAll((l) => l.map((x) => x.dataset.key + ':' + x.dataset.state)), ['tiktok:unsure', 'instagram:auto', 'facebook:auto', 'youtube:fixed'], 'sıra ve durumlar');
  ok(/2 farklı logo/.test(await row('tiktok').textContent()), 'TikTok: 2 farklı logo uyarısı');
  ok(/Uploaded'da görülmedi/.test(await row('facebook').textContent()), 'Facebook: listedeki logo Uploaded\'da yok uyarısı');
  ok(/1 platform sabit · 2 otomatik · 1 platformda karar bekliyor/.test(await p.textContent('#sum-text')), 'özet: ' + await p.textContent('#sum-text'));
  eq([await p.textContent('#other-n'), await state('spotify')], ['2', 'none'], 'logosu olmayan platformlar ayrı bölümde');
  if (process.argv[2]) await p.screenshot({ path: process.argv[2], fullPage: true });

  console.log('T2 kararsız platformda küçük resme tıkla → sabitlenir; "Otomatiğe dön" kaldırır');
  await row('tiktok').locator('.alts .th').nth(1).click();
  await p.waitForFunction(() => document.querySelector('li[data-key=tiktok]').dataset.state === 'fixed');
  eq((await store()).logoPick.tiktok, C + 'tt-b.png', 'ikinci logo sabitlendi');
  await row('tiktok').getByRole('button', { name: 'Otomatiğe dön' }).click();
  await p.waitForFunction(() => document.querySelector('li[data-key=tiktok]').dataset.state === 'unsure');
  ok(!('tiktok' in (await store()).logoPick), 'sabit seçim kaldırıldı');

  console.log('T3 "Değiştir" → seçici: Uploaded resimleri önce; seçilen sabitlenir. Toplu sabitleme yalnız tek logolulara');
  await row('facebook').getByRole('button', { name: 'Değiştir' }).click();
  await p.waitForSelector('#chooser[open]');
  ok(/Uploaded sekmesinde \(7\)/.test(await p.textContent('#ch-grid')) && /Yalnız listede görülen \(1\)/.test(await p.textContent('#ch-grid')), 'seçici iki grup gösterir');
  await p.locator('#ch-grid .th[title="fb.gif"]').click();
  await p.waitForFunction(() => document.querySelector('li[data-key=facebook]').dataset.state === 'fixed');
  eq((await store()).logoPick.facebook, C + 'fb.gif', 'Facebook: Uploaded\'daki resim sabitlendi');
  ok(!/Uploaded'da görülmedi/.test(await row('facebook').textContent()), 'uyarı kalktı');
  await p.click('#fix-all');
  await p.waitForFunction(() => document.querySelector('li[data-key=instagram]').dataset.state === 'fixed');
  const pk = (await store()).logoPick;
  ok(pk.instagram === C + 'ig.png' && !('tiktok' in pk), 'toplu sabitleme: Instagram sabit, kararsız TikTok\'a dokunulmadı');

  console.log('T4 logosu olmayan platforma logo seç; özel kural ekle, değiştir, sil');
  await p.click('#other-wrap summary');
  await p.locator('li[data-key=spotify]').getByRole('button', { name: 'Logo seç' }).click();
  await p.locator('#ch-grid .th[title="other.png"]').click();
  await p.waitForFunction(() => { const l = document.querySelector('li[data-key=spotify]'); return l && l.dataset.state === 'fixed' && l.closest('#used'); });
  ok(true, 'Spotify logosu seçildi ve "Platformlar"a taşındı');
  await p.click('#rule-add button');
  ok(/Önce kelimeyi yaz/.test(await p.textContent('#rule-msg')), 'boş kelimeyle kural eklenmez');
  await p.fill('#rule-word', '  Bigo   Live ');
  await p.click('#rule-add button');
  await p.fill('#ch-url-in', 'https://storage.perfectcdn.com/fsvl4a/bigo12345678.png');
  await p.click('#ch-url button');
  await p.waitForSelector('#rules li');
  eq((await store()).logoRules, [{ word: 'Bigo Live', url: C + 'bigo12345678.png' }], 'kural eklendi (yapıştırılan adresle)');
  await p.locator('#rules li').getByRole('button', { name: 'Sil' }).click();
  await p.waitForFunction(() => !document.querySelector('#rules li'));
  eq((await store()).logoRules, [], 'kural silindi');
  ok(errs.length === 0, 'JS hatası yok ' + errs.join(' | '));
  await b.close();
  console.log('\n' + (fails ? '✗ ' + fails + ' başarısız' : '✓ hepsi geçti'));
  process.exit(fails ? 1 : 0);
})().catch((e) => { console.error(e); process.exit(2); });
