// tgwatch eklentisi: bilinen kelimenin çevirisini import ekranından değiştirme (orijinal satırdaki kelimeye tıkla).
// Çalıştır: node word-tests.js
const { chromium } = require('/home/user/Jalal/servis-tasi-test/node_modules/playwright');
const fs = require('fs');
const path = require('path');
const CONTENT = fs.readFileSync(path.resolve(__dirname, '../tgwatch/chrome-extension/content.js'), 'utf8');
let fails = 0;
const ok = (c, m) => { console.log((c ? '  ✓ ' : '  ✗ ') + m); if (!c) fails++; };
const eq = (a, b, m) => { const A = JSON.stringify(a), B = JSON.stringify(b); ok(A === B, m + (A === B ? '' : '\n      beklenen ' + B + '\n      gelen    ' + A)); };

/** Sahte tgwatch: çeviri bir sözlükle yapılır; teach sözlüğü değiştirir (gerçek sunucudaki gibi sonraki çeviriler değişir). */
function stub(oldServer) {
  return `(() => {
    const store = { panel: 'https://panelbaku.com', api: 'https://tgwatch.test/api', key: 'k', delay: 0, descTranslate: false };
    const listeners = [];
    const _st = window.setTimeout.bind(window);
    window.__calls = [];
    window.__dict = { followers: 'Takipçi', 'no drop': 'Azalma olmaz', likes: 'Bəyəni', fast: 'Sürətli' };
    window.__keep = ['almost'];
    window.__undo = [];
    const tr = (name) => { let s = name; for (const [k, v] of Object.entries(window.__dict)) s = s.replace(new RegExp('\\\\b' + k + '\\\\b', 'gi'), v); return s.replace(/ {2,}/g, ' ').trim(); };
    const fakeApi = (action, p) => {
      window.__calls.push({ action, p });
      if (action === 'translate') return { ok: true, items: p.items.map((it) => ({ id: it.id, az: tr(it.name), via: 'bank', unknown: [] })) };
      if (action === 'learn') return { ok: true, learned: (p.items || []).length };
      if (action === 'word_info') {
        if (${oldServer ? 'true' : 'false'}) return { ok: false, error: 'Bilinmeyen işlem' };
        const k = p.en.toLowerCase();
        return { ok: true, en: p.en, az: window.__dict[k] || '', state: window.__dict[k] ? 'phrase' : window.__keep.includes(k) ? 'keep' : 'none', bank: 3, samples: [] };
      }
      if (action === 'teach') {
        const k = p.en.toLowerCase();
        if (${oldServer ? 'true' : 'false'}) { // eski sunucu: remove/undo bilmez, bank alanı yok
          if (p.keep) { delete window.__dict[k]; window.__keep.push(k); } else window.__dict[k] = p.az || p.en;
          return { ok: true, en: p.en, az: p.keep ? p.en : p.az, keep: !!p.keep };
        }
        window.__undo.push({ en: p.en, dict: Object.assign({}, window.__dict), keep: window.__keep.slice(), to: p.remove ? '(silinsin)' : p.keep ? '(aynen kalsın)' : p.az });
        window.__keep = window.__keep.filter((x) => x !== k);
        if (p.keep) { delete window.__dict[k]; window.__keep.push(k); } else window.__dict[k] = p.remove ? '' : p.az;
        return { ok: true, en: p.en, az: p.remove ? '' : p.keep ? p.en : p.az, keep: !!p.keep, remove: !!p.remove, bank: 3, desc: 1, undo: true };
      }
      if (action === 'word_undo') {
        if (${oldServer ? 'true' : 'false'}) return { ok: false, error: 'Bilinmeyen işlem' };
        const last = window.__undo[window.__undo.length - 1];
        if (p.peek) return { ok: true, last: last ? { en: last.en, to: last.to, rows: 3 } : null };
        if (!last) return { ok: false, error: 'Geri alınacak kelime değişikliği yok' };
        window.__undo.pop(); window.__dict = last.dict; window.__keep = last.keep;
        return { ok: true, en: last.en, was: '(aynen kalsın)', restored: 3, last: null };
      }
      return { ok: true };
    };
    window.chrome = {
      runtime: { lastError: null, getManifest: () => ({ version: 'test' }),
        sendMessage: (msg, cb) => { _st(() => cb && cb(msg.type === 'api' ? fakeApi(msg.action, msg.payload || {}) : { ok: true }), 5); },
        onMessage: { addListener: (f) => listeners.push(f) } },
      storage: { local: { get: (d, cb) => { const o = {}; for (const k of Object.keys(d)) o[k] = k in store ? store[k] : d[k]; _st(() => cb(o), 0); }, set: (o) => Object.assign(store, o) } },
    };
    window.__send = (msg) => new Promise((r) => { for (const f of listeners) f(msg, {}, r); });
  })();`;
}
async function page(b, oldServer) {
  const p = await b.newPage({ viewport: { width: 1200, height: 800 } });
  const errs = []; p.on('pageerror', (e) => errs.push(String(e))); p.__errs = errs;
  await p.goto('file://' + path.join(__dirname, 'mock.html'));
  await p.evaluate(() => {
    const n = ['Facebook Followers | Almost No Drop | Fast', 'Facebook Likes | Fast', 'Facebook Followers | Almost No Drop | 30 Days', 'YouTube Views'];
    window.__rows.forEach((r, i) => { r.name = n[i]; r.desc = ''; });
    render();
  });
  await p.addScriptTag({ content: stub(oldServer) });
  await p.addScriptTag({ content: CONTENT });
  await p.waitForTimeout(300);
  await p.evaluate(() => window.__send({ type: 'tgw-run' }));
  await p.waitForFunction(() => !window.__rows.some((r) => r.open) && window.__calls.some((c) => c.action === 'translate'), null, { timeout: 30000 });
  await p.waitForTimeout(800);
  return p;
}
const names = (p) => p.evaluate(() => window.__rows.map((r) => r.name));
const calls = (p, a) => p.evaluate((x) => window.__calls.filter((c) => c.action === x), a);

(async () => {
  const b = await chromium.launch();

  console.log('W1 orijinal satırdaki "Almost" kelimesine tıkla → şimdiki durum görünür → yeni karşılık yazılır → kelimenin geçtiği satırlar düzelir');
  {
    const p = await page(b, false);
    eq(await names(p), ['Facebook Takipçi | Almost Azalma olmaz | Sürətli', 'Facebook Bəyəni | Sürətli', 'Facebook Takipçi | Almost Azalma olmaz | 30 Days', 'YouTube Views'], 'önce: "Almost" çevrilmeden kalmış');
    const w = p.locator('.tgw-orig .tgw-ow', { hasText: /^Almost$/ }).first();
    ok(await w.count() === 1, 'orijinal satırda kelimeler tıklanabilir');
    await w.click();
    await p.waitForSelector('.tgw-pop');
    await p.waitForFunction(() => /aynen kalsın/.test(document.querySelector('.tgw-pop-msg').textContent));
    const info = await p.textContent('.tgw-pop-msg');
    ok(/Şimdi: çevrilmiyor \(aynen kalsın\) · bankada 3 adda geçiyor/.test(info), 'şimdiki durum: ' + info);
    eq(await p.inputValue('.tgw-pop input >> nth=0'), 'Almost', 'kaynak kutusu dolu');
    await p.fill('.tgw-pop input >> nth=1', 'Demək olar ki');
    await p.click('.tgw-pop button[type=submit]');
    await p.waitForFunction(() => window.__rows[2].name.includes('Demək olar ki') && !window.__rows.some((r) => r.open), null, { timeout: 30000 });
    await p.waitForTimeout(600);
    eq(await names(p), ['Facebook Takipçi | Demək olar ki Azalma olmaz | Sürətli', 'Facebook Bəyəni | Sürətli', 'Facebook Takipçi | Demək olar ki Azalma olmaz | 30 Days', 'YouTube Views'], 'kelimenin geçtiği iki satır düzeldi, diğerleri aynı');
    eq((await calls(p, 'teach')).map((c) => c.p), [{ en: 'Almost', az: 'Demək olar ki' }], 'tgwatch\'a tek teach isteği');
    const st = await p.textContent('.tgw-status');
    ok(/bankada 3 kayıt, 1 açıklama düzeltildi/.test(st) && /2 satır düzeltildi/.test(st), 'durum: ' + st);
    ok(p.__errs.length === 0, 'JS hatası yok ' + p.__errs.join(' | '));

    // çevrilmiş kelimeyi değiştir: kutu şimdiki karşılıkla dolu gelir
    await p.locator('.tgw-orig .tgw-ow', { hasText: /^Likes$/ }).first().click();
    await p.waitForFunction(() => { const i = document.querySelectorAll('.tgw-pop input')[1]; return i && i.value === 'Bəyəni'; });
    ok(/Şimdi: “Bəyəni”/.test(await p.textContent('.tgw-pop-msg')), 'çevrilmiş kelimede şimdiki karşılık kutuya yazılır');
    await p.fill('.tgw-pop input >> nth=1', 'Bəyənmə');
    await p.click('.tgw-pop button[type=submit]');
    await p.waitForFunction(() => window.__rows[1].name === 'Facebook Bəyənmə | Sürətli' && !window.__rows.some((r) => r.open), null, { timeout: 30000 });
    ok(true, '"Likes" → "Bəyənmə": satır düzeldi');
    // boşluğa tıklayınca eskisi gibi kopyalar (kelime kutusu açılmaz)
    await p.waitForTimeout(1200);
    await p.evaluate(() => { const o = document.querySelector('.tgw-orig'); o.dispatchEvent(new MouseEvent('click', { bubbles: true })); });
    ok(await p.locator('.tgw-pop').count() === 0, 'kelime dışına tıklamak kutu açmaz');
    await p.close();
  }

  console.log('W3 "Hiç yazılmasın": kelime adlardan silinir; "↶ Geri al": sözlük eski hâline döner ve satırlar yeniden çevrilir');
  {
    const p = await page(b, false);
    await p.locator('.tgw-orig .tgw-ow', { hasText: /^Almost$/ }).first().click();
    await p.waitForSelector('.tgw-pop');
    ok(await p.locator('.tgw-pop button', { hasText: '↶ Geri al' }).isHidden(), 'geri alınacak bir şey yokken düğme gizli');
    await p.locator('.tgw-pop button', { hasText: 'Hiç yazılmasın' }).click();
    await p.waitForFunction(() => window.__rows[2].name === 'Facebook Takipçi | Azalma olmaz | 30 Days' && !window.__rows.some((r) => r.open), null, { timeout: 30000 });
    await p.waitForTimeout(600);
    eq(await names(p), ['Facebook Takipçi | Azalma olmaz | Sürətli', 'Facebook Bəyəni | Sürətli', 'Facebook Takipçi | Azalma olmaz | 30 Days', 'YouTube Views'], '"Almost" iki satırdan silindi');
    eq((await calls(p, 'teach')).map((c) => c.p), [{ en: 'Almost', remove: true }], 'teach: remove');
    ok(/“Almost” artık adlarda yazılmayacak/.test(await p.textContent('.tgw-status')), 'durum: ' + await p.textContent('.tgw-status'));
    // geri al
    await p.locator('.tgw-orig .tgw-ow', { hasText: /^Almost$/ }).first().click();
    await p.waitForFunction(() => /adlarda hiç yazılmıyor|aynen|Sözlükte/.test((document.querySelector('.tgw-pop-msg') || {}).textContent || ''));
    const u = p.locator('.tgw-pop button', { hasText: '↶ Geri al' });
    await u.waitFor({ state: 'visible' });
    ok(/“Almost” → \(silinsin\)/.test(await u.getAttribute('title')), 'düğme neyin geri alınacağını söyler: ' + await u.getAttribute('title'));
    await u.click();
    await p.waitForFunction(() => window.__rows[2].name === 'Facebook Takipçi | Almost Azalma olmaz | 30 Days' && !window.__rows.some((r) => r.open), null, { timeout: 30000 });
    await p.waitForTimeout(600);
    eq((await names(p))[0], 'Facebook Takipçi | Almost Azalma olmaz | Sürətli', 'geri alındı: kelime yine yazılıyor');
    ok(/↶ Geri alındı: “Almost”.*bankada 3 kayıt eski hâline döndü/.test(await p.textContent('.tgw-status')), 'durum: ' + await p.textContent('.tgw-status'));
    ok(p.__errs.length === 0, 'JS hatası yok ' + p.__errs.join(' | '));
    await p.close();
  }

  console.log('W2 eski sunucu (word_info yok, teach "bank" göndermiyor): değiştirme yine çalışır, uyarı gösterilir');
  {
    const p = await page(b, true);
    await p.locator('.tgw-orig .tgw-ow', { hasText: /^Almost$/ }).first().click();
    await p.waitForFunction(() => /sunucusu eski/.test((document.querySelector('.tgw-pop-msg') || {}).textContent || ''));
    await p.fill('.tgw-pop input >> nth=1', 'Təxminən');
    await p.click('.tgw-pop button[type=submit]');
    await p.waitForFunction(() => window.__rows[0].name.includes('Təxminən') && !window.__rows.some((r) => r.open), null, { timeout: 30000 });
    await p.waitForTimeout(600);
    ok(/sunucusu eski: bankadaki eski kayıtlar düzeltilmedi/.test(await p.textContent('.tgw-status')), 'durumda "sunucu eski" uyarısı');
    await p.locator('.tgw-orig .tgw-ow', { hasText: /^Likes$/ }).first().click();
    await p.waitForSelector('.tgw-pop');
    await p.locator('.tgw-pop button', { hasText: 'Hiç yazılmasın' }).click();
    await p.waitForFunction(() => /"hiç yazılmasın" desteklenmiyor/.test((document.querySelector('.tgw-pop-msg') || {}).textContent || ''));
    ok(true, 'eski sunucuda "hiç yazılmasın": açık uyarı, satırlara dokunulmaz');
    ok(p.__errs.length === 0, 'JS hatası yok');
    await p.close();
  }

  await b.close();
  console.log('\n' + (fails ? '✗ ' + fails + ' başarısız' : '✓ hepsi geçti'));
  process.exit(fails ? 1 : 0);
})().catch((e) => { console.error(e); process.exit(2); });
