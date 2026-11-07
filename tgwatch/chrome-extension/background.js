// tgwatch eklentisi — arka plan: tgwatch API çağrıları ve panel sayfasına content script kaydı.
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
      headers: { 'Content-Type': 'application/json', 'X-Tgwatch-Key': s.key },
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

chrome.runtime.onInstalled.addListener(() => { register(); });
chrome.runtime.onStartup.addListener(() => { register(); });

chrome.runtime.onMessage.addListener((msg, sender, sendResponse) => {
  if (!msg || typeof msg !== 'object') return false;
  if (msg.type === 'api' && typeof msg.action === 'string') {
    callApi(msg.action, msg.payload).then((res) => {
      if (msg.action === 'learn' && res && res.ok && res.learned) bump({ learned: res.learned });
      sendResponse(res);
    });
    return true;
  }
  if (msg.type === 'stat') {
    bump({ translated: Number(msg.translated) || 0, learned: Number(msg.learned) || 0 }).then(() => sendResponse({ ok: true }));
    return true;
  }
  if (msg.type === 'register') {
    register().then(sendResponse, (e) => sendResponse({ ok: false, error: String(e) }));
    return true;
  }
  return false;
});
