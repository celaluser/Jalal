// Panel Araçları — arka plan service worker'ı (üç aracın ortak arka planı).
// 1) Chrome arka plandaki sekmede zamanlayıcıları kısar; içerik betiklerinin beklemeleri burada yapılır (service worker kısılmaz).
// 2) İş sürerken ya da otomatik mod açıkken sekmenin Bellek Tasarrufu ile kapatılması engellenir.
// 3) Otomatik mod (Servis Pasifle ve Pasif Servis Taşı): her N dakikada (chrome.alarms) panel sekmesi yenilenir; yenilenen
//    sayfada içerik betikleri işini yapar. Sekme Services sayfasında değilse, iş sürüyorsa ya da kullanıcı panelde
//    çalışıyorsa o tur atlanır (karar içerik betiğinde). İki aracın alarmı art arda gelirse sayfa bir kez yenilenir.
self.__ticks = 0;
const TOOLS = { kt: null, sp: 'sp-auto:', pt: 'pt-auto:' }; // bağlantı adı → alarm öneki (null: otomatik modu yok)
const PREFIXES = Object.values(TOOLS).filter(Boolean);
const SERVICES_URL = /^https:\/\/panelbaku\.com\/admin\/services\/?([?#].*)?$/;
const lastReload = new Map(); // tabId → zaman (iki alarm art arda gelince çift yenilemeyi önler)

async function autoInfo(name) {
  const al = await chrome.alarms.get(name);
  return al ? { next: al.scheduledTime, every: al.periodInMinutes } : null;
}
/** Sekmede herhangi bir aracın otomatik modu açık mı. */
async function anyAuto(tabId) {
  for (const p of PREFIXES) if (await chrome.alarms.get(p + tabId)) return true;
  return false;
}
async function setDiscardable(tabId, v) {
  if (tabId == null) return;
  if (v && await anyAuto(tabId).catch(() => false)) return; // otomatik mod açıkken korunmaya devam
  chrome.tabs.update(tabId, { autoDiscardable: v }).catch(() => {});
}

chrome.runtime.onConnect.addListener((port) => {
  if (!(port.name in TOOLS)) return;
  const prefix = TOOLS[port.name];
  const tabId = port.sender && port.sender.tab ? port.sender.tab.id : null;
  const timers = new Set();
  const post = (m) => { try { port.postMessage(m); } catch (e) { /* sekme kapandı */ } };
  port.onMessage.addListener(async (m) => {
    if (!m) return;
    if (m.t === 'sleep') {
      const h = setTimeout(() => { timers.delete(h); self.__ticks++; post({ t: 'wake', id: m.id }); }, Math.max(0, Math.min(60000, m.ms | 0)));
      timers.add(h);
    } else if (m.t === 'busy') {
      setDiscardable(tabId, !m.on);
    } else if (m.t === 'auto' && tabId != null && prefix) {
      const name = prefix + tabId;
      if (m.on) {
        const every = Math.max(1, Math.min(120, Number(m.every) || 5));
        const al = await chrome.alarms.get(name);
        if (!al || al.periodInMinutes !== every || m.restart) await chrome.alarms.create(name, { delayInMinutes: every, periodInMinutes: every });
        chrome.tabs.update(tabId, { autoDiscardable: false }).catch(() => {});
      } else {
        await chrome.alarms.clear(name);
        setDiscardable(tabId, true);
      }
      post({ t: 'autoInfo', info: await autoInfo(name) });
    } else if (m.t === 'autoInfo' && tabId != null && prefix) {
      post({ t: 'autoInfo', info: await autoInfo(prefix + tabId) });
    }
  });
  port.onDisconnect.addListener(() => {
    timers.forEach(clearTimeout);
    setDiscardable(tabId, true);
  });
});

/** Alarm: sekme hâlâ panelin Services sayfasındaysa o aracın içerik betiğine sor; uygunsa yenilenir. Dönüş: test için sonuç. */
async function onAutoAlarm(name) {
  const prefix = PREFIXES.find((p) => name && name.startsWith(p));
  if (!prefix) return 'yok';
  const tool = Object.keys(TOOLS).find((k) => TOOLS[k] === prefix);
  const tabId = Number(name.slice(prefix.length));
  let tab;
  try { tab = await chrome.tabs.get(tabId); } catch (e) { await chrome.alarms.clear(name); return 'sekme-yok'; }
  if (!tab.url || !SERVICES_URL.test(tab.url)) return 'atla-adres'; // başka sayfadaysa yenileme
  // Diğer aracın alarmı az önce yeniledi: yenilenen sayfada iki araç da turunu yapar, ikinci yenileme onları yarıda keserdi.
  if (tab.status === 'loading' || Date.now() - (lastReload.get(tabId) || 0) < 45000) return 'atla-yeni-yenilendi';
  let r = null;
  try { r = await chrome.tabs.sendMessage(tabId, { [tool]: true, cmd: 'autoTick' }); } catch (e) { r = null; }
  if (r && r.off) { await chrome.alarms.clear(name); return 'kapali'; }
  if (r && r.skip) return 'atla-icerik';
  lastReload.set(tabId, Date.now());
  if (r && r.reloading) return 'yenilendi';
  // içerik betiği cevap vermedi (sayfa takıldı/yüklenmedi): yenile
  await chrome.tabs.reload(tabId).catch(() => {});
  return 'yenilendi-zorla';
}
self.__autoTick = onAutoAlarm; // test kancası
self.__forgetReload = (tabId) => lastReload.delete(tabId); // test kancası
chrome.alarms.onAlarm.addListener((al) => { onAutoAlarm(al.name); });
