// tgwatch eklentisi — DestekBot profil/link yoxlaması.
// DestekBot növbəyə link qoyur; eklenti onu bu brauzerdə arxa planda yeni tabda açır, sayı və vəziyyəti oxuyur,
// tabı bağlayır, nəticəni DestekBot-a göndərir və növbətiyə keçir. İnstagram/TikTok sessiyası brauzerin özününküdür.

const PROF_ALARM = 'tgw-prof';
let profBusy = false;

async function profSettings() {
  return chrome.storage.local.get({ dbApi: '', dbKey: '', profileOn: false });
}

/** DestekBot API: ?r=/yol forması hər hostinqdə işləyir. */
async function dbCall(path, body) {
  const s = await profSettings();
  if (!s.dbApi || !s.dbKey) return { ok: false, error: 'DestekBot API ünvanı və açarı yazılmayıb (eklenti ayarları).' };
  try {
    const r = await fetch(s.dbApi + (s.dbApi.includes('?') ? '&' : '?') + 'r=' + encodeURIComponent(path), {
      method: body === undefined ? 'GET' : 'POST',
      headers: { 'Authorization': 'Bearer ' + s.dbKey, 'Content-Type': 'application/json', 'Accept': 'application/json' },
      body: body === undefined ? undefined : JSON.stringify(body), credentials: 'omit', cache: 'no-store',
    });
    const j = await r.json().catch(() => null);
    if (!j) return { ok: false, error: 'DestekBot cavab vermədi (HTTP ' + r.status + ')' };
    if (!j.ok) return { ok: false, error: (j.error && j.error.message) || ('HTTP ' + r.status) };
    return { ok: true, data: j.data };
  } catch (e) {
    return { ok: false, error: 'DestekBot-a qoşulmaq olmadı: ' + (e && e.message ? e.message : e) };
  }
}

async function profStatus(text, add) {
  const { profStat } = await chrome.storage.local.get({ profStat: null });
  await chrome.storage.local.set({ profStat: { text, at: Date.now(), done: ((profStat && profStat.done) || 0) + (add || 0) } });
}

function waitTab(tabId, timeout) {
  return new Promise((resolve) => {
    let done = false;
    const finish = (ok) => { if (done) return; done = true; chrome.tabs.onUpdated.removeListener(onUpd); clearTimeout(t); resolve(ok); };
    const onUpd = (id, info) => { if (id === tabId && info.status === 'complete') finish(true); };
    const t = setTimeout(() => finish(false), timeout);
    chrome.tabs.onUpdated.addListener(onUpd);
    chrome.tabs.get(tabId).then((tab) => { if (tab && tab.status === 'complete') finish(true); }).catch(() => finish(false));
  });
}

/**
 * Səhifənin içində işləyir (chrome.scripting): platformaya görə sayı və vəziyyəti oxuyur.
 * Bu funksiya özünə qapalı olmalıdır (xarici dəyişən işlətmir).
 */
async function extractProfile(platform) {
  const out = { final_url: location.href };
  const toNum = (s) => {
    if (s === null || s === undefined) return null;
    if (typeof s === 'number') return Math.round(s);
    let t = String(s).trim().replace(/\s| /g, '');
    const m = t.match(/^([\d.,]+)([KkMmBb]|тыс|млн)?$/);
    if (!m) return null;
    let n = m[1];
    if (m[2]) {
      const v = parseFloat(n.replace(',', '.'));
      const mul = /[Kk]|тыс/.test(m[2]) ? 1e3 : /[Mm]|млн/.test(m[2]) ? 1e6 : 1e9;
      return Math.round(v * mul);
    }
    return parseInt(n.replace(/[.,]/g, ''), 10);
  };
  const meta = (sel) => { const el = document.querySelector(sel); return el ? el.getAttribute('content') || '' : ''; };
  const bodyText = () => (document.body ? document.body.innerText : '').slice(0, 4000);
  const sleep = (ms) => new Promise((r) => setTimeout(r, ms));

  if (platform === 'instagram') {
    if (/\/accounts\/login/.test(location.pathname)) return { ...out, logged_in: false, error: 'Bu brauzerdə Instagram-a giriş edilməyib' };
    const parts = location.pathname.split('/').filter(Boolean);
    if (['p', 'reel', 'reels', 'tv'].includes(parts[0])) {
      out.kind = 'post';
      for (let i = 0; i < 10 && !meta('meta[property="og:description"]') && !meta('meta[name="description"]'); i++) await sleep(500);
      const d = meta('meta[property="og:description"]') || meta('meta[name="description"]');
      if (!d && /isn.t available|mövcud deyil|kullanılamıyor|недоступна/i.test(bodyText())) return { ...out, exists: false };
      const likes = d.match(/([\d.,]+\s?[KkMm]?)\s+(likes?|bəyən|beğen|отмет)/i);
      const comments = d.match(/([\d.,]+\s?[KkMm]?)\s+(comments?|şərh|yorum|коммент)/i);
      const user = d.match(/-\s+([A-Za-z0-9._]+)\s+(on|tarihinde|в)\s/);
      return { ...out, exists: !!d, likes: likes ? toNum(likes[1]) : null, comments: comments ? toNum(comments[1]) : null, username: user ? user[1] : null };
    }
    const username = parts[0];
    out.kind = 'profile';
    try {
      const r = await fetch('/api/v1/users/web_profile_info/?username=' + encodeURIComponent(username), { headers: { 'x-ig-app-id': '936619743392459' }, credentials: 'include' });
      if (r.status === 404) return { ...out, exists: false };
      const j = await r.json();
      const u = j && j.data && j.data.user;
      if (!u) return { ...out, exists: false };
      return { ...out, exists: true, private: !!u.is_private, username: u.username,
        followers: u.edge_followed_by ? u.edge_followed_by.count : null, following: u.edge_follow ? u.edge_follow.count : null,
        posts: u.edge_owner_to_timeline_media ? u.edge_owner_to_timeline_media.count : null };
    } catch (e) {
      // ehtiyat: səhifənin təsviri "1,234 Followers, 56 Following, 7 Posts"
      for (let i = 0; i < 10 && !meta('meta[property="og:description"]'); i++) await sleep(500);
      const d = meta('meta[property="og:description"]') || meta('meta[name="description"]');
      if (!d) return /isn.t available|mövcud deyil|kullanılamıyor|недоступна/i.test(bodyText()) ? { ...out, exists: false } : { ...out, error: 'Profil oxunmadı' };
      const f = d.match(/([\d.,]+\s?[KkMm]?)\s+(Followers|izləyici|takipçi|подписчик)/i);
      return { ...out, exists: true, followers: f ? toNum(f[1]) : null, private: /private|gizli/i.test(bodyText()) || null, username };
    }
  }

  if (platform === 'tiktok') {
    let scope = null;
    for (let i = 0; i < 16 && !scope; i++) {
      const el = document.getElementById('__UNIVERSAL_DATA_FOR_REHYDRATION__');
      try { scope = el ? JSON.parse(el.textContent).__DEFAULT_SCOPE__ : null; } catch (e) { scope = null; }
      if (!scope) await sleep(500);
    }
    const txt = bodyText();
    if (scope && scope['webapp.video-detail']) {
      const vd = scope['webapp.video-detail'];
      out.kind = 'video';
      if (vd.statusCode && vd.statusCode !== 0) return { ...out, exists: false };
      const it = vd.itemInfo && vd.itemInfo.itemStruct;
      const st = (it && (it.statsV2 || it.stats)) || {};
      return { ...out, exists: !!it, username: it && it.author ? it.author.uniqueId : null, likes: toNum(st.diggCount), views: toNum(st.playCount), comments: toNum(st.commentCount) };
    }
    if (scope && scope['webapp.user-detail']) {
      const ud = scope['webapp.user-detail'];
      out.kind = 'profile';
      if (ud.statusCode && ud.statusCode !== 0) return { ...out, exists: false };
      const ui = ud.userInfo || {};
      const st = ui.statsV2 || ui.stats || {};
      return { ...out, exists: !!ui.user, private: !!(ui.user && ui.user.privateAccount), username: ui.user ? ui.user.uniqueId : null,
        followers: toNum(st.followerCount), likes: toNum(st.heartCount || st.heart) };
    }
    if (/couldn.t find this account|hesab tapılmadı|hesap bulunamadı|не удалось найти/i.test(txt)) return { ...out, exists: false };
    if (/video currently unavailable|video is unavailable|video kullanılamıyor/i.test(txt)) return { ...out, kind: 'video', exists: false };
    return { ...out, error: 'TikTok səhifəsi oxunmadı (bot qorunması və ya giriş tələbi ola bilər)' };
  }

  if (platform === 'telegram') {
    const title = document.querySelector('.tgme_page_title');
    const extra = document.querySelector('.tgme_page_extra');
    out.kind = 'channel';
    if (!title) return { ...out, exists: false };
    const m = extra ? extra.textContent.replace(/\s| /g, '').match(/([\d.,]+)(subscribers?|members?|abunə|üzv|подписчик|участник)/i) : null;
    return { ...out, exists: true, username: (location.pathname.split('/').filter(Boolean)[0] || null), members: m ? parseInt(m[1].replace(/[.,]/g, ''), 10) : null };
  }
  return { ...out, error: 'Dəstəklənməyən platforma' };
}

/** Bir işi icra et: tab aç → oxu → bağla. */
async function profRun(job) {
  let host;
  try { host = new URL(job.link).origin; } catch (e) { return { error: 'Link düzgün deyil' }; }
  const has = await chrome.permissions.contains({ origins: [host + '/*'] }).catch(() => false);
  if (!has) return { error: 'Eklentiyə ' + host + ' üçün icazə verilməyib (eklenti ayarlarında "Saxla, icazə ver" basın)' };
  let tab;
  try {
    tab = await chrome.tabs.create({ url: job.link, active: false });
    const loaded = await waitTab(tab.id, 30000);
    if (!loaded) return { error: 'Səhifə 30 saniyədə açılmadı' };
    await new Promise((r) => setTimeout(r, 1500));   // SPA hissələri yüklənsin
    const fresh = await chrome.tabs.get(tab.id);
    const fhost = fresh.url ? new URL(fresh.url).origin : host;
    if (!(await chrome.permissions.contains({ origins: [fhost + '/*'] }).catch(() => false))) return { error: 'Link başqa sayta yönləndi: ' + fhost };
    const [res] = await chrome.scripting.executeScript({ target: { tabId: tab.id }, func: extractProfile, args: [job.platform] });
    return (res && res.result) || { error: 'Səhifədən nəticə alınmadı' };
  } catch (e) {
    return { error: 'Yoxlama xətası: ' + (e && e.message ? e.message : e) };
  } finally {
    if (tab && tab.id) chrome.tabs.remove(tab.id).catch(() => {});
  }
}

/** Növbəni boşalana qədər işlə (bir dövrədə ən çox 10). */
async function profTick() {
  if (profBusy) return;
  const s = await profSettings();
  if (!s.profileOn || !s.dbApi || !s.dbKey) return;
  profBusy = true;
  try {
    for (let i = 0; i < 10; i++) {
      const c = await dbCall('/profile-checks/claim', {});
      if (!c.ok) { await profStatus('Xəta: ' + c.error); break; }
      const job = c.data && c.data.job;
      if (!job) { if (i === 0) await profStatus(c.data && c.data.disabled ? 'DestekBot-da profil yoxlaması söndürülüb' : 'Növbə boşdur'); break; }
      await profStatus('Yoxlanılır: ' + job.platform + ' · #' + job.order_id);
      const result = await profRun(job);
      const p = await dbCall('/profile-checks/' + job.id + '/result', { result });
      await profStatus(p.ok ? ('#' + job.order_id + ': ' + (result.error ? 'alınmadı — ' + result.error : 'yoxlanıldı')) : 'Nəticə göndərilmədi: ' + p.error, 1);
    }
  } finally {
    profBusy = false;
  }
}

function profSchedule() {
  try { chrome.alarms.create(PROF_ALARM, { periodInMinutes: 0.5 }); } catch (e) { /* yox */ }
}
chrome.alarms && chrome.alarms.onAlarm.addListener((a) => { if (a.name === PROF_ALARM) profTick(); });
chrome.alarms && chrome.alarms.get(PROF_ALARM, (a) => { if (!a) profSchedule(); });
chrome.runtime.onInstalled.addListener(profSchedule);
chrome.runtime.onStartup.addListener(profSchedule);
chrome.runtime.onMessage.addListener((msg, sender, sendResponse) => {
  if (!msg || msg.type !== 'prof-test') return false;
  dbCall('/status').then(async (r) => {
    const c = r.ok ? await dbCall('/profile-checks?limit=1') : r;
    sendResponse(r.ok ? { ok: true, disabled: false, checks: c.ok } : r);
    profSchedule();
    profTick();
  });
  return true;
});
self.__profTick = profTick; self.__extractProfile = extractProfile; // test kancası
