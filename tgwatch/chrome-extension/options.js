const $ = (id) => document.getElementById(id);
const msg = (t, ok) => { const m = $('msg'); m.textContent = t; m.className = ok ? 'ok' : 'bad'; };

chrome.storage.local.get({ panel: '', api: '', key: '', delay: 400, dry: false }, (s) => {
  $('panel').value = s.panel; $('api').value = s.api; $('key').value = s.key; $('delay').value = s.delay; $('dry').checked = !!s.dry;
});

function origin(u) {
  const x = new URL(u);
  if (x.protocol !== 'https:') throw new Error(u + ' https:// ile başlamalı');
  return x.origin;
}

$('f').addEventListener('submit', async (e) => {
  e.preventDefault();
  let panelOrigin, apiOrigin;
  try {
    panelOrigin = origin($('panel').value.trim());
    apiOrigin = origin($('api').value.trim());
  } catch (err) {
    msg(err.message, false);
    return;
  }
  // İzin, düğmeye basma anında istenmeli (Chrome kuralı).
  const granted = await chrome.permissions.request({ origins: [panelOrigin + '/*', apiOrigin + '/*'] });
  if (!granted) {
    msg('İzin verilmedi. Eklenti panel sitesinde çalışamaz ve tgwatch\'a bağlanamaz.', false);
    return;
  }
  await chrome.storage.local.set({
    panel: panelOrigin, api: $('api').value.trim(), key: $('key').value.trim(),
    delay: Math.max(0, Math.min(5000, Number($('delay').value) || 0)), dry: $('dry').checked,
  });
  const reg = await chrome.runtime.sendMessage({ type: 'register' });
  if (!reg || !reg.ok) {
    msg('Kaydedildi ama panel sayfasına bağlanamadı: ' + (reg && reg.error), false);
    return;
  }
  const ping = await chrome.runtime.sendMessage({ type: 'api', action: 'ping' });
  if (ping && ping.ok) msg('Bağlandı: ' + ping.panel + ' (tgwatch ' + ping.version + '). Panel sekmesini yenile, Import services ekranında sol altta tgwatch çubuğu çıkar.', true);
  else msg('Kaydedildi ama tgwatch cevap vermedi: ' + ((ping && ping.error) || 'bilinmeyen hata'), false);
});
