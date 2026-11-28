// Kategori Temizle — arka plan service worker'ı.
// Chrome arka plandaki sekmede zamanlayıcıları kısar (setTimeout ≥1 sn, 5 dk sonra dakikada bir).
// Bu yüzden içerik script'inin beklemeleri burada yapılır ve bitince mesajla haber verilir (service worker kısılmaz).
// İş sürerken sekmenin Bellek Tasarrufu ile otomatik kapatılması (discard) da engellenir.
self.__ticks = 0;

chrome.runtime.onConnect.addListener((port) => {
  if (port.name !== 'kt') return;
  const tabId = port.sender && port.sender.tab ? port.sender.tab.id : null;
  const timers = new Set();
  const setDiscardable = (v) => { if (tabId != null) chrome.tabs.update(tabId, { autoDiscardable: v }).catch(() => {}); };
  port.onMessage.addListener((m) => {
    if (!m) return;
    if (m.t === 'sleep') {
      const h = setTimeout(() => {
        timers.delete(h);
        self.__ticks++;
        try { port.postMessage({ t: 'wake', id: m.id }); } catch (e) { /* sekme kapandı */ }
      }, Math.max(0, Math.min(60000, m.ms | 0)));
      timers.add(h);
    } else if (m.t === 'busy') {
      setDiscardable(!m.on);
    }
  });
  port.onDisconnect.addListener(() => {
    timers.forEach(clearTimeout);
    setDiscardable(true);
  });
});
