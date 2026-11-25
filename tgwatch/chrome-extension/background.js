// tgwatch eklentisi — arka plan: tgwatch API çağrıları ve panel sayfasına content script kaydı.
importScripts('profile.js'); // DestekBot profil/link yoxlaması
const SCRIPT_ID = 'tgw-import';

async function settings() {
  return chrome.storage.local.get({ panel: '', api: '', key: '', delay: 400, dry: false, skipAz: true });
}

async function callApi(action, payload) {
  const s = await settings();
  if (!s.api || !s.key) {
    return { ok: false, error: 'Eklenti ayarları eksik: API adresi ve anahtar girin (eklenti simgesi → ayarlar).' };
  }
  try {
    const r = await fetch(s.api, {
      method: 'POST',
      headers: { 'Content-Type': 'application/json', 'X-Tgwatch-Key': s.key, 'X-Tgwatch-Ext': chrome.runtime.getManifest().version },
      body: JSON.stringify(Object.assign({ action }, payload || {})),
      credentials: 'omit',
      cache: 'no-store',
    });
    const j = await r.json().catch(() => null);
    return j || { ok: false, error: 'tgwatch cevap vermedi (HTTP ' + r.status + ')' };
  } catch (e) {
    return { ok: false, error: 'tgwatch\'a ulaşılamadı: ' + (e && e.message ? e.message : e) };
  }
}

/** Mini paneldeki sayaçlar: bugün / toplam çevrilen, bankaya öğretilen. */
async function bump(add) {
  const today = new Date().toISOString().slice(0, 10);
  const { stats } = await chrome.storage.local.get({ stats: { day: today, today: 0, total: 0, learned: 0, last: 0 } });
  if (stats.day !== today) { stats.day = today; stats.today = 0; }
  if (add.translated) { stats.today += add.translated; stats.total += add.translated; stats.last = Date.now(); }
  if (add.learned) stats.learned += add.learned;
  await chrome.storage.local.set({ stats });
}

async function register() {
  const s = await settings();
  try { await chrome.scripting.unregisterContentScripts({ ids: [SCRIPT_ID] }); } catch (e) { /* yoktu */ }
  if (!s.panel) return { ok: false, error: 'Panel adresi yok' };
  let origin;
  try { origin = new URL(s.panel).origin; } catch (e) { return { ok: false, error: 'Panel adresi geçersiz' }; }
  const has = await chrome.permissions.contains({ origins: [origin + '/*'] });
  if (!has) return { ok: false, error: 'Panel sitesine izin verilmedi' };
  await chrome.scripting.registerContentScripts([{
    id: SCRIPT_ID, matches: [origin + '/*'], js: ['content.js'], css: ['content.css'], runAt: 'document_idle', allFrames: false,
  }]);
  return { ok: true };
}

// ---- yeni sürüm kontrolü: tgwatch sunucusundaki chrome-extension sürümü bundan yeniyse rozet + mini panelde "İndir" ----
const newer = (a, b) => {
  const x = String(a || '').split('.').map(Number);
  const y = String(b || '').split('.').map(Number);
  for (let i = 0; i < Math.max(x.length, y.length); i++) {
    if ((x[i] || 0) !== (y[i] || 0)) return (x[i] || 0) > (y[i] || 0);
  }
  return false;
};
/** Rozet: yeni sürüm varsa "YENİ", yoksa panele yazılmayı bekleyen açıklama sayısı. */
async function badge() {
  const { upd, descq } = await chrome.storage.local.get({ upd: null, descq: null });
  const n = descq ? (descq.count || 0) + (descq.fresh || 0) : 0;
  try {
    await chrome.action.setBadgeText({ text: upd && upd.has ? 'YENİ' : n ? String(n > 999 ? '999+' : n) : '' });
    await chrome.action.setBadgeBackgroundColor({ color: upd && upd.has ? '#b3362f' : '#243b6b' });
  } catch (e) { /* rozet yok */ }
}

// ---- tgwatch'ta onaylanan açıklamalar: sayıyı çek, yeni iş varsa bildirim göster ----
async function pullDescQueue() {
  const s = await settings();
  if (!s.api || !s.key) return null;
  const r = await callApi('desc_queue', { peek: 1 });
  if (!r || !r.ok) return r; // eski sunucu ("Bilinmeyen işlem") ya da bağlantı yok: sessiz
  const { descq } = await chrome.storage.local.get({ descq: null });
  const now = { count: r.count || 0, categories: r.categories || [], at: Date.now(), told: descq ? descq.told || 0 : 0, toldAt: descq ? descq.toldAt || 0 : 0 };
  // Aynı iş için bir kez; sayı artınca ya da 3 saat sonra yine
  if (now.count && (now.count > now.told || Date.now() - now.toldAt > 3 * 3600 * 1000) && chrome.notifications) {
    const cats = now.categories.map((c) => c.name + ' (' + c.n + ')').join(', ');
    try {
      chrome.notifications.create('tgw-desc', {
        type: 'basic', iconUrl: 'icons/128.png', priority: 1, requireInteraction: true,
        title: now.count + ' açıklama panele eklenmeyi bekliyor',
        message: (cats ? cats + ' için ' : '') + 'tgwatch\'ta onayladığın açıklamalar hazır. Eklensin mi?',
        buttons: [{ title: 'Panele ekle' }, { title: 'Sonra' }],
      });
      now.told = now.count;
      now.toldAt = Date.now();
    } catch (e) { /* bildirim izni yok */ }
  }
  if (!now.count) { now.told = 0; try { chrome.notifications && chrome.notifications.clear('tgw-desc'); } catch (e) { /* yok */ } }
  // Yeni servislere kendiliğinden üretilen öneriler: "Bak" → öneriler sayfası
  now.fresh = r.fresh || 0;
  now.proposed = r.proposed || 0;
  now.toldFresh = descq ? descq.toldFresh || 0 : 0;
  if (now.fresh && now.fresh > now.toldFresh && chrome.notifications) {
    try {
      chrome.notifications.create('tgw-desc-new', {
        type: 'basic', iconUrl: 'icons/128.png', priority: 1, requireInteraction: true,
        title: now.fresh + ' yeni servis için açıklama üretildi',
        message: 'tgwatch yeni eklenen servislere açıklama yazdı. Okuyup düzeltebilir, onaylayabilirsin; onaylamadan panele yazılmaz.',
        buttons: [{ title: 'Bak' }, { title: 'Sonra' }],
      });
    } catch (e) { /* bildirim yok */ }
  }
  now.toldFresh = now.fresh ? Math.max(now.toldFresh, now.fresh) : 0;
  await chrome.storage.local.set({ descq: now });
  await badge();
  return r;
}
self.__pullDescQueue = pullDescQueue; // test kancası

/**
 * Services sayfasını aç (açıksa öne getir) ve açıklama yazmayı başlat.
 * Başlatma işareti storage'da (descStart): sayfa yönlendirme yapsa/hash'i silse de yeni yüklenen sayfa görür.
 * Açık sekmedeki içerik betiği eklenti güncellenince ölür: mesaj gitmezse sekme YENİDEN YÜKLENİR (yalnız hash değiştirmek yetmez).
 */
async function openDescTab() {
  const s = await settings();
  if (!s.panel) return { ok: false, error: 'Panel adresi yok' };
  const base = s.panel.replace(/\/+$/, '').replace(/\/admin(\/services)?$/i, '') + '/admin/services';
  await chrome.storage.local.set({ descStart: Date.now() });
  const tabs = await chrome.tabs.query({});
  const t = tabs.find((x) => x.url && x.url.split('#')[0].split('?')[0].replace(/\/+$/, '') === base);
  if (t) {
    await chrome.tabs.update(t.id, { active: true });
    if (t.windowId != null) chrome.windows.update(t.windowId, { focused: true }).catch(() => {});
    const ok = await new Promise((res) => chrome.tabs.sendMessage(t.id, { type: 'tgw-desc-run' }, (r) => { void chrome.runtime.lastError; res(!!(r && r.ok)); }));
    if (ok) {
      await chrome.storage.local.remove('descStart');
    } else {
      await chrome.tabs.update(t.id, { url: base });
      await chrome.tabs.reload(t.id);
    }
  } else {
    await chrome.tabs.create({ url: base });
  }
  return { ok: true };
}
self.openDescTab = openDescTab;
const openDescPage = (v) => chrome.tabs.create({ url: chrome.runtime.getURL('desc.html') + '#' + (v || 'props') });
if (chrome.notifications) {
  chrome.notifications.onClicked.addListener((id) => {
    if (id === 'tgw-desc') { chrome.notifications.clear(id); openDescTab(); }
    if (id === 'tgw-desc-new') { chrome.notifications.clear(id); openDescPage('props'); }
    if (id === 'tgw-desc-rep') { chrome.notifications.clear(id); openDescPage('report'); }
  });
  chrome.notifications.onButtonClicked.addListener((id, i) => {
    if (id !== 'tgw-desc' && id !== 'tgw-desc-new') return;
    chrome.notifications.clear(id);
    if (i === 0) { if (id === 'tgw-desc') openDescTab(); else openDescPage('props'); }
  });
}

async function checkUpdate(known) {
  const r = known || await callApi('ping');
  if (!r || !r.ok) return null;
  const mine = chrome.runtime.getManifest().version;
  const upd = { latest: r.ext || '', mine, has: !!r.ext && newer(r.ext, mine), at: Date.now() };
  await chrome.storage.local.set({ upd });
  await badge();
  return upd;
}
// ---- sağlayıcı bakiyeleri: her dakika tgwatch'tan (sunucu cron'u her dakika sorgulayıp kaydeder) ----
// Mini panel açık olmasa da çekilir ve saklanır; panel açılınca hemen güncel değer görünür.
async function pullBalances(force) {
  const s = await settings();
  if (!s.api || !s.key) return null;
  const r = await callApi('balance', force ? { force: 1 } : {});
  if (!r || !r.ok) { await chrome.storage.local.set({ balErr: (r && r.error) || 'tgwatch cevap vermedi', balErrAt: Date.now() }); return r; }
  const bal = { items: r.items, totals: r.totals, at: r.at, pulled: Date.now() };
  await chrome.storage.local.set({ bal, balErr: '' });
  return r;
}
self.__pullBalances = pullBalances; // test kancası
function schedule() {
  try {
    chrome.alarms.create('tgw-upd', { periodInMinutes: 360 });
    chrome.alarms.create('tgw-bal', { delayInMinutes: 0.1, periodInMinutes: 1 });
  } catch (e) { /* alarms yok */ }
}
chrome.alarms && chrome.alarms.onAlarm.addListener((a) => {
  if (a.name === 'tgw-upd') checkUpdate();
  if (a.name === 'tgw-bal') {
    pullBalances(false);
    // açıklama kuyruğu 3 dakikada bir (her dakika gereksiz)
    if (!(self.__descTick = ((self.__descTick || 0) + 1) % 3)) pullDescQueue();
  }
});

chrome.runtime.onInstalled.addListener(() => {
  register(); schedule(); checkUpdate(); pullDescQueue();
  // Güncelleme sayfasından kurulduysa sonucu göstermek için sayfayı yeniden aç
  chrome.storage.local.get({ updTry: null }, (x) => { if (x.updTry && Date.now() - x.updTry.at < 600000) chrome.tabs.create({ url: chrome.runtime.getURL('update.html') }); });
});
chrome.runtime.onStartup.addListener(() => { register(); schedule(); checkUpdate(); pullDescQueue(); });

// ---- arka plan sekmesi: Chrome sayfanın zamanlayıcılarını kısar; içerik script'inin beklemeleri burada yapılır ----
// (service worker kısılmaz). İş sürerken sekmenin Bellek Tasarrufu ile kapatılması da engellenir.
self.__ticks = 0;
chrome.runtime.onConnect.addListener((port) => {
  if (port.name !== 'tgw') return;
  const tabId = port.sender && port.sender.tab ? port.sender.tab.id : null;
  const timers = new Set();
  const setDiscardable = (v) => { if (tabId != null) chrome.tabs.update(tabId, { autoDiscardable: v }).catch(() => {}); };
  port.onMessage.addListener((m) => {
    if (!m) return;
    if (m.t === 'sleep') {
      const h = setTimeout(() => {
        timers.delete(h); self.__ticks++;
        try { port.postMessage({ t: 'wake', id: m.id }); } catch (e) { /* sekme kapandı */ }
      }, Math.max(0, Math.min(60000, m.ms | 0)));
      timers.add(h);
    } else if (m.t === 'busy') {
      setDiscardable(!m.on);
    }
  });
  port.onDisconnect.addListener(() => { timers.forEach(clearTimeout); setDiscardable(true); });
});

chrome.runtime.onMessage.addListener((msg, sender, sendResponse) => {
  if (!msg || typeof msg !== 'object') return false;
  if (msg.type === 'api' && typeof msg.action === 'string') {
    callApi(msg.action, msg.payload).then((res) => {
      if (msg.action === 'learn' && res && res.ok && res.learned) bump({ learned: res.learned });
      if (msg.action === 'ping' && res && res.ok) checkUpdate(res);
      if (msg.action === 'balance' && res && res.ok) chrome.storage.local.set({ bal: { items: res.items, totals: res.totals, at: res.at, pulled: Date.now() }, balErr: '' });
      sendResponse(res);
    });
    return true;
  }
  if (msg.type === 'stat') {
    bump({ translated: Number(msg.translated) || 0, learned: Number(msg.learned) || 0 }).then(() => sendResponse({ ok: true }));
    return true;
  }
  if (msg.type === 'reload') {
    sendResponse({ ok: true });
    setTimeout(() => chrome.runtime.reload(), 100);
    return false;
  }
  if (msg.type === 'desc-page') {
    openDescPage(msg.view);
    sendResponse({ ok: true });
    return false;
  }
  if (msg.type === 'desc-open') {
    openDescTab().then(sendResponse, (e) => sendResponse({ ok: false, error: String(e) }));
    return true;
  }
  if (msg.type === 'desc-report' && msg.report) {
    const rep = msg.report;
    const ok = rep.results.filter((r) => r.ok).length;
    const bad = rep.results.length - ok;
    chrome.storage.local.set({ descReport: rep }).then(() => pullDescQueue());
    try {
      chrome.notifications && chrome.notifications.create('tgw-desc-rep', {
        type: 'basic', iconUrl: 'icons/128.png', priority: 1,
        title: 'Açıklamalar: ' + ok + ' eklendi' + (bad ? ', ' + bad + ' eklenmedi' : ''),
        message: (bad ? 'Eklenmeyenler: ' + rep.results.filter((r) => !r.ok).slice(0, 4).map((r) => r.sid + ' (' + r.error + ')').join('; ') + '. ' : '') + 'Ayrıntı: eklenti simgesi → Panel → Son rapor.',
      });
    } catch (e) { /* bildirim yok */ }
    sendResponse({ ok: true });
    return false;
  }
  if (msg.type === 'desc-refresh') {
    pullDescQueue().then(() => chrome.storage.local.get({ descq: null })).then((x) => sendResponse({ ok: true, descq: x.descq }), (e) => sendResponse({ ok: false, error: String(e) }));
    return true;
  }
  if (msg.type === 'register') {
    register().then(sendResponse, (e) => sendResponse({ ok: false, error: String(e) }));
    return true;
  }
  return false;
});

// Eklenti yeniden yüklenince/güncellenince de bakiye alarmı kurulsun (onInstalled/onStartup dışında service worker uyanışı).
chrome.alarms && chrome.alarms.get('tgw-bal', (a) => { if (!a) schedule(); });
