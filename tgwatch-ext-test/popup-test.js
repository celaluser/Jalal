// tgwatch mini paneli: "Kelimenin çevirisini değiştir" kutusu (chrome.* taklit).
// Çalıştır: node popup-test.js
const { chromium } = require('/home/user/Jalal/servis-tasi-test/node_modules/playwright');
const path = require('path');
let fails = 0;
const ok = (c, m) => { console.log((c ? '  ✓ ' : '  ✗ ') + m); if (!c) fails++; };
(async () => {
  const b = await chromium.launch();
  const p = await b.newPage({ viewport: { width: 380, height: 640 } });
  const errs = []; p.on('pageerror', (e) => errs.push(String(e)));
  await p.addInitScript(() => {
    const store = { panel: 'https://panelbaku.com', api: 'https://tgwatch.test/api.php', key: 'k' };
    window.__calls = [];
    const fake = (a, q) => {
      window.__calls.push({ a, q });
      if (a === 'ping') return { ok: true, version: '1.3.1', ext: '' };
      if (a === 'word_info') return { ok: true, en: q.en, az: '', state: 'keep', bank: 4, samples: [{ en: 'x', az: 'Facebook Takipçi | Almost Azalma olmaz' }] };
      if (a === 'teach') return { ok: true, en: q.en, az: q.keep ? q.en : q.az, keep: !!q.keep, bank: 4 };
      return { ok: true, items: [], totals: [], at: 1 };
    };
    window.chrome = {
      runtime: { lastError: null, getManifest: () => ({ version: '1.17.0' }), sendMessage: (m, cb) => setTimeout(() => cb && cb(m.type === 'api' ? fake(m.action, m.payload || {}) : { ok: true }), 5), openOptionsPage() {}, getURL: (x) => x },
      storage: { local: { get: (d, cb) => { const o = {}; for (const k of Object.keys(d)) o[k] = k in store ? store[k] : d[k]; const r = Promise.resolve(o); if (cb) cb(o); return r; }, set: (o) => Object.assign(store, o) }, onChanged: { addListener() {} } },
      tabs: { query: async () => [{ id: 1, url: 'https://example.com/' }], create() {}, sendMessage: (i, m, cb) => cb && cb(null) },
    };
  });
  await p.goto('file://' + path.resolve(__dirname, '../tgwatch/chrome-extension/popup.html'));
  await p.waitForTimeout(400);
  ok(await p.isVisible('#word'), 'kutu Panel sekmesinde görünür (panel sekmesinde olmasan da)');
  await p.fill('#w-en', 'Almost');
  await p.waitForFunction(() => /aynen kalsın/.test(document.getElementById('w-info').textContent));
  ok(/Şimdi: çevrilmiyor \(aynen kalsın\) · bankada 4 adda geçiyor \(ör\. Facebook Takipçi \| Almost Azalma olmaz\)/.test(await p.textContent('#w-info')), 'şimdiki durum: ' + await p.textContent('#w-info'));
  await p.click('#w-save');
  ok(/Azerice karşılığı yaz/.test(await p.textContent('#w-info')), 'boş karşılık gönderilmez');
  await p.fill('#w-az', 'Demək olar ki');
  await p.click('#w-save');
  await p.waitForFunction(() => /✓/.test(document.getElementById('w-info').textContent));
  ok(/✓ “Almost” → “Demək olar ki” · bankada 4 kayıt düzeltildi/.test(await p.textContent('#w-info')), 'sonuç: ' + await p.textContent('#w-info'));
  const t = await p.evaluate(() => window.__calls.filter((c) => c.a === 'teach').map((c) => c.q));
  ok(JSON.stringify(t) === JSON.stringify([{ en: 'Almost', az: 'Demək olar ki' }]), 'teach isteği: ' + JSON.stringify(t));
  await p.screenshot({ path: process.argv[2] || '/dev/null' }).catch(() => {});
  ok(errs.length === 0, 'JS hatası yok ' + errs.join(' | '));
  await b.close();
  console.log('\n' + (fails ? '✗ ' + fails + ' başarısız' : '✓ hepsi geçti'));
  process.exit(fails ? 1 : 0);
})().catch((e) => { console.error(e); process.exit(2); });
