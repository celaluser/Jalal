// Pasif Servis Taşı — arka plan service worker'ı.
// 1) Chrome arka plandaki sekmede zamanlayıcıları kısar; içerik script'inin beklemeleri burada yapılır (service worker kısılmaz).
// 2) İş sürerken sekmenin Bellek Tasarrufu ile otomatik kapatılması engellenir.
// 3) Otomatik mod: her N dakikada (chrome.alarms) panel sekmesini yeniler; yenilenen sayfada içerik script'i pasif servisleri
//    bulur ve Not Assigned'a taşır. Sekme Services sayfasında değilse, iş sürüyorsa ya da kullanıcı panelde çalışıyorsa
//    o tur atlanır (karar içerik script'inde).
self.__ticks = 0;
const ALARM = 'pt-auto:';
const SERVICES_URL = /^https:\/\/panelbaku\.com\/admin\/services\/?([?#].*)?$/;

async function autoInfo(tabId) {
  const al = await chrome.alarms.get(ALARM + tabId);
  return al ? { next: al.scheduledTime, every: al.periodInMinutes } : null;
}

chrome.runtime.onConnect.addListener((port) => {
  if (port.name !== 'pt') return;
  const tabId = port.sender && port.sender.tab ? port.sender.tab.id : null;
  const timers = new Set();
  const setDiscardable = (v) => { if (tabId != null) chrome.tabs.update(tabId, { autoDiscardable: v }).catch(() => {}); };
  const post = (m) => { try { port.postMessage(m); } catch (e) { /* sekme kapandı */ } };
  port.onMessage.addListener(async (m) => {
    if (!m) return;
    if (m.t === 'sleep') {
      const h = setTimeout(() => { timers.delete(h); self.__ticks++; post({ t: 'wake', id: m.id }); }, Math.max(0, Math.min(60000, m.ms | 0)));
      timers.add(h);
    } else if (m.t === 'busy') {
      setDiscardable(!m.on);
    } else if (m.t === 'auto' && tabId != null) {
      const name = ALARM + tabId;
      if (m.on) {
        const every = Math.max(1, Math.min(120, Number(m.every) || 5));
        const al = await chrome.alarms.get(name);
        if (!al || al.periodInMinutes !== every || m.restart) await chrome.alarms.create(name, { delayInMinutes: every, periodInMinutes: every });
        // otomatik mod açıkken sekme Bellek Tasarrufu ile kapatılmasın
        chrome.tabs.update(tabId, { autoDiscardable: false }).catch(() => {});
      } else {
        await chrome.alarms.clear(name);
        chrome.tabs.update(tabId, { autoDiscardable: true }).catch(() => {});
      }
      post({ t: 'autoInfo', info: await autoInfo(tabId) });
    } else if (m.t === 'autoInfo' && tabId != null) {
      post({ t: 'autoInfo', info: await autoInfo(tabId) });
    }
  });
  port.onDisconnect.addListener(() => {
    timers.forEach(clearTimeout);
    // otomatik mod açıksa sekme kapatılmaya karşı korunmaya devam etsin; değilse serbest bırak
    if (tabId != null) chrome.alarms.get(ALARM + tabId).then((al) => { if (!al) setDiscardable(true); }).catch(() => {});
  });
});

/** Alarm: sekme hâlâ panelin Services sayfasındaysa içerik script'ine sor; uygunsa yenilenir. Dönüş: test için sonuç. */
async function onAutoAlarm(name) {
  if (!name || !name.startsWith(ALARM)) return 'yok';
  const tabId = Number(name.slice(ALARM.length));
  let tab;
  try { tab = await chrome.tabs.get(tabId); } catch (e) { await chrome.alarms.clear(name); return 'sekme-yok'; }
  if (!tab.url || !SERVICES_URL.test(tab.url)) return 'atla-adres'; // başka sayfadaysa yenileme
  let r = null;
  try { r = await chrome.tabs.sendMessage(tabId, { pt: true, cmd: 'autoTick' }); } catch (e) { r = null; }
  if (r && r.off) { await chrome.alarms.clear(name); return 'kapali'; }
  if (r && r.skip) return 'atla-icerik';
  if (r && r.reloading) return 'yenilendi';
  // içerik script'i cevap vermedi (sayfa takıldı/yüklenmedi): yenile
  await chrome.tabs.reload(tabId).catch(() => {});
  return 'yenilendi-zorla';
}
self.__autoTick = onAutoAlarm; // test kancası
chrome.alarms.onAlarm.addListener((al) => { onAutoAlarm(al.name); });
