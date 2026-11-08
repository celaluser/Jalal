// tgwatch eklentisi: açıklama çevirisi — content.js taklit import ekranında, chrome.runtime/storage taklit edilerek.
// Çalıştır: node run-tests.js
const { chromium } = require('/home/user/Jalal/servis-tasi-test/node_modules/playwright');
const fs = require('fs');
const path = require('path');
const CONTENT = fs.readFileSync(path.resolve(__dirname, '../tgwatch/chrome-extension/content.js'), 'utf8');
let fails = 0;
const ok = (c, m) => { console.log((c ? '  ✓ ' : '  ✗ ') + m); if (!c) fails++; };
const eq = (a, b, m) => { const A = JSON.stringify(a), B = JSON.stringify(b); ok(A === B, m + (A === B ? '' : '\n      beklenen ' + B + '\n      gelen    ' + A)); };

/** chrome.* taklidi: api çağrıları window.__calls'a yazılır, cevaplar sahte tgwatch'tan. */
function stub(storage) {
  return `(() => {
    const store = Object.assign({ panel: 'https://panelbaku.com', api: 'https://tgwatch.test/api', key: 'k', delay: 0 }, ${JSON.stringify(storage)});
    const listeners = [];
    const _st = window.setTimeout.bind(window); // gerçek mesajlaşma zamanlayıcıya bağlı değil: kısıtlama taklidinden etkilenmesin
    window.__calls = [];
    const fakeApi = (action, p) => {
      window.__calls.push({ action, p });
      if (action === 'translate') return { ok: true, items: p.items.map((it) => /ə/.test(it.name) ? { id: it.id, az: '', via: 'already', unknown: [] } : { id: it.id, az: 'AZ ' + it.name, via: 'bank', unknown: [] }) };
      if (action === 'translate_desc') return { ok: true, items: p.items.map((it) => ({ id: it.id, az: it.text.split('\\n').map((l) => 'AZ: ' + l).join('\\n'), via: 'mix', note: '' })), plan: {} };
      if (action === 'learn_desc' || action === 'learn') return { ok: true, learned: (p.items || []).length };
      return { ok: true };
    };
    window.chrome = {
      runtime: {
        lastError: null,
        getManifest: () => ({ version: 'test' }),
        sendMessage: (msg, cb) => { _st(() => cb && cb(msg.type === 'api' ? fakeApi(msg.action, msg.payload || {}) : { ok: true }), 5); },
        onMessage: { addListener: (f) => listeners.push(f) },
      },
      storage: { local: { get: (d, cb) => { const o = {}; for (const k of Object.keys(d)) o[k] = k in store ? store[k] : d[k]; _st(() => cb(o), 0); }, set: (o) => Object.assign(store, o) } },
    };
    window.__send = (msg) => new Promise((r) => { for (const f of listeners) f(msg, {}, r); });
  })();`;
}
async function page(b, storage) {
  const p = await b.newPage();
  const errs = []; p.on('pageerror', (e) => errs.push(String(e)));
  p.__errs = errs;
  await p.goto('file://' + path.join(__dirname, 'mock.html'));
  await p.addScriptTag({ content: stub(storage || {}) });
  await p.addScriptTag({ content: CONTENT });
  await p.waitForTimeout(300);
  return p;
}
const rowsOf = (p) => p.evaluate(() => window.__rows.map((r) => ({ id: r.id, name: r.name, desc: r.desc })));
const calls = (p, a) => p.evaluate((x) => window.__calls.filter((c) => c.action === x), a);
async function runImport(p) {
  await p.evaluate(() => window.__send({ type: 'tgw-run' }));
  await p.waitForFunction(() => !window.__rows.some((r) => r.open) && window.__calls.some((c) => c.action === 'translate'), null, { timeout: 30000 });
  await p.waitForTimeout(800);
}

(async () => {
  const b = await chromium.launch();

  console.log('D1 import: ad + açıklama çevrilir (satır yapısı korunur), Save; açıklama bankaya öğretilir');
  {
    const p = await page(b, {});
    await runImport(p);
    const r = await rowsOf(p);
    eq(r[0].desc, 'AZ: Start: 0-1 Hour\nAZ: Speed: 10K per day\nAZ: No refill', '5378 açıklaması (3 satır)');
    eq(r[1].desc, 'AZ: Instant start\nAZ: Guarantee: 30 days', '5379 açıklaması');
    ok(r[0].name.startsWith('AZ '), 'ad da çevrildi: ' + r[0].name);
    eq(r[2].desc, 'AZ: Fast delivery', 'adı zaten Azerice olan satırın açıklaması da çevrildi');
    eq(r[3].desc, '', 'boş açıklamaya dokunulmadı');
    const td = await calls(p, 'translate_desc');
    eq(td.length, 3, '3 açıklama isteği (boş olan gönderilmedi)');
    eq(td[0].p.mode, undefined, 'mod boş: tgwatch ayarı kullanılır');
    eq((await calls(p, 'learn_desc')).length, 3, 'açıklamalar bankaya öğretildi');
    const tip = await p.evaluate(() => [...document.querySelectorAll('.tgw-badge, [title]')].map((e) => e.title || e.getAttribute('data-title') || '').join(' | '));
    ok(/Açıklama banka \+ Google \+ DeepSeek/.test(tip), 'satır işaretinde açıklama kaynağı yazıyor');
    ok(p.__errs.length === 0, 'JS hatası yok ' + p.__errs.join(' | '));
    await p.close();
  }

  console.log('D2 ayar kapalı: açıklamalara dokunulmaz, istek gitmez; mod seçimi isteğe eklenir');
  {
    let p = await page(b, { descTranslate: false });
    await runImport(p);
    eq((await rowsOf(p))[0].desc, 'Start: 0-1 Hour\nSpeed: 10K per day\nNo refill', 'açıklama aynı');
    eq((await calls(p, 'translate_desc')).length, 0, 'translate_desc yok');
    await p.close();
    p = await page(b, { descMode: 'deepseek' });
    await runImport(p);
    eq((await calls(p, 'translate_desc'))[0].p.mode, 'deepseek', 'mini paneldeki kaynak (deepseek) gönderildi');
    await p.close();
  }

  console.log('D3 Geri al: ad ve açıklama orijinale döner');
  {
    const p = await page(b, {});
    await runImport(p);
    await p.evaluate(() => window.__send({ type: 'tgw-undo' }));
    await p.waitForFunction(() => window.__rows[0].desc.startsWith('Start') && !window.__rows.some((r) => r.open), null, { timeout: 30000 });
    const r = await rowsOf(p);
    eq([r[0].desc, r[1].desc], ['Start: 0-1 Hour\nSpeed: 10K per day\nNo refill', 'Instant start\nGuarantee: 30 days'], 'açıklamalar geri döndü');
    ok(!r[0].name.startsWith('AZ '), 'ad geri döndü');
    await p.close();
  }

  console.log('D4 "Edit description" penceresi: "tgwatch: açıklamayı çevir" düğmesi alanı doldurur (kaydetme kullanıcıda)');
  {
    const p = await page(b, {});
    await p.evaluate(() => window.__openEdit('High quality accounts\nStart: instant'));
    await p.waitForSelector('#edlg .tgw-descbtn', { timeout: 5000 });
    ok(true, 'düğme eklendi');
    await p.click('#edlg .tgw-descbtn');
    await p.waitForFunction(() => document.getElementById('edesc').value.startsWith('AZ:'), null, { timeout: 5000 });
    eq(await p.inputValue('#edesc'), 'AZ: High quality accounts\nAZ: Start: instant', 'alan dolduruldu');
    ok(/kontrol edip kaydet/.test(await p.locator('#edlg .tgw-descnote').innerText()), 'not: kontrol edip kaydet');
    eq(await p.evaluate(() => window.__saved), undefined, 'kendisi kaydetmedi');
    eq(await p.locator('.tgw-descbtn').count(), 1, 'import penceresine düğme eklenmedi (yalnız Edit description)');
    await p.close();
    const q = await page(b, { descTranslate: false });
    await q.evaluate(() => window.__openEdit('Text'));
    await q.waitForTimeout(800);
    eq(await q.locator('.tgw-descbtn').count(), 0, 'ayar kapalıyken düğme yok');
    await q.close();
  }

  console.log('D5 arka plan taklidi: sayfa zamanlayıcıları 100 kat yavaş (Chrome kısıtlaması gibi); beklemeler service worker\'dan gelince import yine çabuk biter');
  {
    const p = await b.newPage();
    const errs = []; p.on('pageerror', (e) => errs.push(String(e)));
    await p.goto('file://' + path.join(__dirname, 'mock.html'));
    await p.addScriptTag({ content: stub({ delay: 300 }) });
    await p.addScriptTag({ content: `(() => {
      const orig = window.setTimeout.bind(window);
      window.__wakes = 0;
      // service worker taklidi: bekleme isteğine gerçek sürede cevap verir
      window.chrome.runtime.connect = () => {
        const ls = [];
        return { onMessage: { addListener: (f) => ls.push(f) }, onDisconnect: { addListener: () => {} },
          postMessage: (m) => { if (m.t === 'sleep') orig(() => { window.__wakes++; ls.forEach((f) => f({ t: 'wake', id: m.id })); }, m.ms); if (m.t === 'busy') window.__busy = m.on; } };
      };
      // sayfanın kendi zamanlayıcıları kısıldı (arka plan sekmesi gibi)
      window.setTimeout = (f, ms, ...a) => orig(f, (Number(ms) || 0) * 100 + 5000, ...a);
      window.__orig = orig;
    })();` });
    await p.addScriptTag({ content: CONTENT });
    const t0 = Date.now();
    await p.evaluate(() => window.__send({ type: 'tgw-run' }));
    await p.waitForFunction(() => { const s = document.querySelector('.tgw-status'); return s && /^Bitti/.test(s.textContent) && window.__rows.every((r) => !r.open); }, null, { timeout: 120000, polling: 200 });
    eq((await rowsOf(p)).map((r) => r.desc.startsWith('AZ:')), [true, true, true, false], 'dört satır işlendi (boş açıklama hariç)');
    const sec = (Date.now() - t0) / 1000;
    ok(sec < 20, 'import + açıklamalar ' + sec.toFixed(1) + ' sn içinde bitti (sayfa zamanlayıcılarıyla dakikalar sürerdi)');
    ok((await p.evaluate(() => window.__wakes)) >= 3, 'satırlar arası 300 ms beklemeler service worker üzerinden (' + await p.evaluate(() => window.__wakes) + ' uyanış)');
    ok(errs.length === 0, 'JS hatası yok ' + errs.join(' | '));
    await p.close();
  }

  await b.close();
  console.log('\n' + (fails ? '✗ ' + fails + ' başarısız' : '✓ hepsi geçti'));
  process.exit(fails ? 1 : 0);
})().catch((e) => { console.error(e); process.exit(2); });
