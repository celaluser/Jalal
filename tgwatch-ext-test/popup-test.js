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
    window.__bank = [{ id: 7, kind: 'service', en: 'Facebook Followers | Almost No Drop', az: 'Facebook Takipçi | Almost Azalma olmaz', origin: 'approved' }, { id: 8, kind: 'category', en: 'Almost Free Services', az: 'Almost Pulsuz Xidmətlər', origin: 'manual' }];
    const fake = (a, q) => {
      window.__calls.push({ a, q });
      if (a === 'ping') return { ok: true, version: '1.3.1', ext: '' };
      if (a === 'word_info') return { ok: true, en: q.en, az: '', state: 'keep', bank: 4, desc: 2, samples: [{ en: 'x', az: 'Facebook Takipçi | Almost Azalma olmaz' }] };
      if (a === 'teach') { window.__last = { en: q.en, to: q.remove ? '(silinsin)' : q.keep ? '(aynen kalsın)' : q.az, rows: 6 }; return { ok: true, en: q.en, az: q.remove ? '' : q.keep ? q.en : q.az, keep: !!q.keep, remove: !!q.remove, bank: 4, desc: 2, undo: true }; }
      if (a === 'word_undo') { if (q.peek) return { ok: true, last: window.__last || null }; const l = window.__last; window.__last = null; return l ? { ok: true, en: l.en, was: '(aynen kalsın)', restored: 6, last: null } : { ok: false, error: 'Geri alınacak kelime değişikliği yok' }; }
      if (a === 'bank_search') return { ok: true, q: q.q, words: [{ en: 'Almost', az: 'Demək olar ki', state: 'phrase' }], rows: window.__bank.filter((r) => (r.en + r.az).toLowerCase().includes(q.q.toLowerCase())) };
      if (a === 'bank_save') { const r = window.__bank.find((x) => x.id === q.id); if (!q.az) return { ok: false, error: 'Azerice ad 1–500 karakter olmalı' }; r.az = q.az; return { ok: true, id: q.id, az: q.az }; }
      if (a === 'bank_del') { window.__bank = window.__bank.filter((x) => x.id !== q.id); return { ok: true, id: q.id, deleted: true }; }
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
  await p.click('#t-bank');
  ok(await p.isVisible('#word') && await p.isVisible('#bank'), '"Banka" sekmesi: kelime kutusu ve arama (panel sekmesinde olmasan da)');
  ok(await p.isHidden('#w-undo-row'), 'geri alınacak değişiklik yokken "Geri al" gizli');
  await p.fill('#w-en', 'Almost');
  await p.waitForFunction(() => /aynen kalsın/.test(document.getElementById('w-info').textContent));
  ok(/Şimdi: çevrilmiyor \(aynen kalsın\) · bankada 4 adda, 2 açıklamada geçiyor \(ör\. Facebook Takipçi \| Almost Azalma olmaz\)/.test(await p.textContent('#w-info')), 'şimdiki durum: ' + await p.textContent('#w-info'));
  await p.click('#w-save');
  ok(/Azerice karşılığı yaz/.test(await p.textContent('#w-info')), 'boş karşılık gönderilmez');
  await p.fill('#w-az', 'Demək olar ki');
  await p.click('#w-save');
  await p.waitForFunction(() => /✓/.test(document.getElementById('w-info').textContent));
  ok(/✓ “Almost” → “Demək olar ki” · bankada 4 ad, 2 açıklama düzeltildi/.test(await p.textContent('#w-info')), 'sonuç: ' + await p.textContent('#w-info'));
  const t = await p.evaluate(() => window.__calls.filter((c) => c.a === 'teach').map((c) => c.q));
  ok(JSON.stringify(t) === JSON.stringify([{ en: 'Almost', az: 'Demək olar ki' }]), 'teach isteği: ' + JSON.stringify(t));
  await p.waitForSelector('#w-undo-row:not([hidden])');
  ok(/son: “Almost” → Demək olar ki · 6 kayıt/.test(await p.textContent('#w-undo-what')), 'geri al satırı: ' + await p.textContent('#w-undo-what'));
  if (process.argv[2]) await p.screenshot({ path: process.argv[2], fullPage: true });
  await p.click('#w-undo');
  await p.waitForFunction(() => /↶ Geri alındı/.test(document.getElementById('w-info').textContent));
  ok(/“Almost” yine \(aynen kalsın\) · bankada 6 kayıt eski hâline döndü/.test(await p.textContent('#w-info')) && await p.isHidden('#w-undo-row'), 'geri alındı: ' + await p.textContent('#w-info'));
  await p.click('#w-remove');
  await p.waitForFunction(() => /artık adlarda yazılmayacak/.test(document.getElementById('w-info').textContent));
  ok((await p.evaluate(() => window.__calls.filter((c) => c.a === 'teach').pop().q)).remove === true, '"Hiç yazılmasın": teach remove');

  // bankada ara → düzelt → sil
  await p.fill('#b-q', 'a');
  await p.click('#b-form button');
  ok(/En az 2 harf/.test(await p.textContent('#b-msg')), 'tek harfle aranmaz');
  await p.fill('#b-q', 'almost');
  await p.click('#b-form button');
  await p.waitForSelector('#b-rows li');
  ok(/1 sözlük terimi, 2 banka kaydı/.test(await p.textContent('#b-msg')), 'arama: ' + await p.textContent('#b-msg'));
  await p.locator('#b-rows li').first().getByRole('button', { name: 'Düzelt' }).click();
  await p.fill('#b-rows li textarea', 'Facebook Takipçi | Təxminən Azalma olmaz');
  await p.locator('#b-rows li').first().getByRole('button', { name: 'Kaydet' }).click();
  await p.waitForFunction(() => /Kaydedildi/.test(document.getElementById('b-msg').textContent));
  ok((await p.evaluate(() => window.__bank[0].az)) === 'Facebook Takipçi | Təxminən Azalma olmaz' && /Təxminən/.test(await p.locator('#b-rows li .az').first().textContent()), 'banka kaydı düzeltildi');
  const del = p.locator('#b-rows li').nth(1).locator('button.danger');
  await del.click();
  ok(/Emin misin/.test(await del.textContent()) && (await p.evaluate(() => window.__bank.length)) === 2, 'silme iki tıkla: ilk tık yalnız sorar');
  await del.click();
  await p.waitForFunction(() => document.querySelectorAll('#b-rows li').length === 1);
  ok((await p.evaluate(() => window.__bank.map((x) => x.id))).join() === '7', 'ikinci tıkla silindi');
  await p.locator('#b-words li').getByRole('button', { name: 'Değiştir' }).click();
  ok(await p.inputValue('#w-en') === 'Almost' && await p.inputValue('#w-az') === 'Demək olar ki', 'sözlük sonucundan kelime kutusuna geçiş');
  ok(errs.length === 0, 'JS hatası yok ' + errs.join(' | '));
  await b.close();
  console.log('\n' + (fails ? '✗ ' + fails + ' başarısız' : '✓ hepsi geçti'));
  process.exit(fails ? 1 : 0);
})().catch((e) => { console.error(e); process.exit(2); });
