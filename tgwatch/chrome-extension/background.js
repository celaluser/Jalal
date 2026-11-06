// tgwatch eklentisi — arka plan: tgwatch API çağrıları ve panel sayfasına content script kaydı.
const SCRIPT_ID = 'tgw-import';

async function settings() {
  return chrome.storage.local.get({ panel: '', api: '', key: '', delay: 400, dry: false });
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
chrome.action.onClicked.addListener(() => chrome.runtime.openOptionsPage());

chrome.runtime.onMessage.addListener((msg, sender, sendResponse) => {
  if (!msg || typeof msg !== 'object') return false;
  if (msg.type === 'api' && typeof msg.action === 'string') {
    callApi(msg.action, msg.payload).then(sendResponse);
    return true;
  }
  if (msg.type === 'register') {
    register().then(sendResponse, (e) => sendResponse({ ok: false, error: String(e) }));
    return true;
  }
  return false;
});
