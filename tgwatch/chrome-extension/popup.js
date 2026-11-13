const $ = (id) => document.getElementById(id);
const get = (d) => new Promise((r) => chrome.storage.local.get(d, r));
const today = () => new Date().toISOString().slice(0, 10);
let tabId = null;
let tgBase = '';

function conn(text, kind) { const c = $('conn'); c.textContent = text; c.className = 'pill ' + kind; }

async function init() {
  const s = await get({ panel: '', api: '', key: '', dry: false, skipAz: true, autoCat: true, catTop: true, notify: true, bracketSpace: true, stripMinMax: true, stripSpeed: true, descTranslate: true, descMode: '', catLogo: true, catLogoReplace: false, stats: { day: today(), today: 0, total: 0, learned: 0 } });
  $('dry').checked = !!s.dry;
  $('skipAz').checked = s.skipAz !== false;
  $('bracketSpace').checked = s.bracketSpace !== false;
  $('stripMinMax').checked = s.stripMinMax !== false;
  $('stripSpeed').checked = s.stripSpeed !== false;
  $('descTranslate').checked = s.descTranslate !== false;
  $('descMode').value = s.descMode || '';
  $('catLogo').checked = s.catLogo !== false;
  $('catLogoReplace').checked = s.catLogoReplace === true;
  $('autoCat').checked = s.autoCat !== false;
  $('catTop').checked = s.catTop !== false;
  $('notify').checked = s.notify !== false;
  $('s-today').textContent = s.stats.day === today() ? s.stats.today : 0;
  $('s-total').textContent = s.stats.total;
  $('s-learned').textContent = s.stats.learned;
  if (s.api) {
    try { const u = new URL(s.api); tgBase = u.origin + u.pathname.replace(/api\.php$/, ''); $('tg').href = tgBase; $('tg').hidden = false; } catch (e) { /* geçersiz */ }
  }
  if (!s.panel || !s.api || !s.key) {
    conn('Ayar yok', 'bad');
    $('tab-msg').textContent = 'Önce bağlantı ayarlarını gir (aşağıda).';
    return;
  }
  showUpd((await get({ upd: null })).upd);
  chrome.runtime.sendMessage({ type: 'api', action: 'ping' }, (r) => {
    if (r && r.ok && r.ext !== undefined) {
      const mine = chrome.runtime.getManifest().version;
      showUpd({ latest: r.ext, mine, has: !!r.ext && newerV(r.ext, mine) });
    }
    const mineV = chrome.runtime.getManifest().version;
    if (r && r.ok && r.ext && newerV(mineV, r.ext)) {
      // Sunucudaki tgwatch bu eklentiden eski: yeni özellikler (ör. "Hiç yazılmasın") sunucuda yok, hata verir.
      conn('Sunucu eski · ' + r.version, 'bad');
      $('conn').title = 'tgwatch sunucusunda eklenti ' + r.ext + ', sende ' + mineV + '. Sunucuya güncel tgwatch paketini yükle.';
      $('upd-dot').hidden = false;
      $('upd-msg').textContent = 'tgwatch sunucusu eklentiden eski (sunucuda ' + r.ext + ', sende ' + mineV + '): bazı özellikler çalışmaz. Sunucuya güncel tgwatch paketini yükle.';
    } else if (r && r.ok) conn('Bağlı · ' + r.version, 'ok');
    else { conn('Bağlantı yok', 'bad'); $('conn').title = (r && r.error) || ''; }
  });
  const [tab] = await chrome.tabs.query({ active: true, currentWindow: true });
  tabId = tab && tab.id;
  const onPanel = tab && tab.url && tab.url.startsWith(s.panel);
  if (!onPanel) {
    $('tab-msg').textContent = 'Bu sekme panel değil.';
    $('tab-open').hidden = false;
    $('open-panel').onclick = () => chrome.tabs.create({ url: s.panel + '/admin/services' });
    return;
  }
  refresh();
}

// ---- kelimenin çevirisini değiştir: sözlük + bankadaki eski kayıtlar (tgwatch "teach"); geri al; bankada ara/düzelt/sil ----
const callApi = (action, payload) => new Promise((res) => chrome.runtime.sendMessage({ type: 'api', action, payload }, (r) => res(chrome.runtime.lastError || !r ? { ok: false, error: 'tgwatch cevap vermedi' } : r)));
const OLD = (r) => /Bilinmeyen işlem/.test((r && r.error) || '');
const W_HINT = 'Sözlükteki karşılığı ve bankadaki eski kayıtları (adlar + açıklamalar) birlikte değiştirir.';
const wInfo = (text, kind) => { const e = $('w-info'); e.textContent = text; e.className = 'muted small' + (kind ? ' ' + kind : ''); };
let wSeq = 0;
let wTimer = null;
const stateText = (r) => (r.state === 'phrase' ? 'Şimdi: “' + r.az + '”' : r.state === 'keep' ? 'Şimdi: çevrilmiyor (aynen kalsın)' : r.state === 'remove' ? 'Şimdi: adlarda hiç yazılmıyor' : 'Sözlükte yok');
async function wordInfo() {
  const en = $('w-en').value.trim();
  if (!en) { wInfo(W_HINT); return; }
  const my = ++wSeq;
  const r = await callApi('word_info', { en });
  if (my !== wSeq) return;
  if (!r.ok) { wInfo(OLD(r) ? 'tgwatch sunucusu eski: şimdiki karşılık gösterilemiyor.' : (r.error || 'tgwatch hatası'), 'bad'); return; }
  if (r.state === 'phrase' && !$('w-az').value.trim()) $('w-az').value = r.az;
  wInfo(stateText(r) + (r.bank ? ' · bankada ' + r.bank + ' adda' + (r.desc ? ', ' + r.desc + ' açıklamada' : '') + ' geçiyor' + (r.samples && r.samples[0] ? ' (ör. ' + r.samples[0].az + ')' : '')
    : r.desc ? ' · ' + r.desc + ' açıklamada geçiyor' : ' · bankada geçmiyor'));
}
$('w-en').addEventListener('input', () => { clearTimeout(wTimer); $('w-az').value = ''; wTimer = setTimeout(wordInfo, 450); });
const fixNote = (r) => (r.bank === undefined ? ' · sunucu eski: bankadaki eski kayıtlar düzeltilmedi'
  : (r.bank || r.desc) ? ' · bankada ' + (r.bank || 0) + ' ad' + (r.desc ? ', ' + r.desc + ' açıklama' : '') + ' düzeltildi' : ' · bankada düzeltilecek kayıt yoktu');
/** mode: '' (karşılık yaz) | 'keep' (aynen kalsın) | 'remove' (hiç yazılmasın) */
async function wordSave(mode) {
  const en = $('w-en').value.trim();
  const az = $('w-az').value.trim();
  if (!en) { wInfo('Kaynak kelimeyi yaz', 'bad'); $('w-en').focus(); return; }
  if (!mode && !az) { wInfo('Azerice karşılığı yaz', 'bad'); $('w-az').focus(); return; }
  const btns = ['w-save', 'w-keep', 'w-remove', 'w-undo'].map($);
  btns.forEach((b) => { b.disabled = true; });
  wSeq++;
  wInfo('tgwatch\'a gönderiliyor…');
  const r = await callApi('teach', mode === 'keep' ? { en, keep: true } : mode === 'remove' ? { en, remove: true } : { en, az });
  btns.forEach((b) => { b.disabled = false; });
  if (!r.ok) { wInfo(r.error || 'tgwatch hatası', 'bad'); return; }
  if (mode === 'remove' && !r.remove) { wInfo('tgwatch sunucusu eski: "hiç yazılmasın" desteklenmiyor — sunucuyu güncelle.', 'bad'); return; }
  wInfo('✓ “' + r.en + '”' + (r.remove ? ' artık adlarda yazılmayacak' : r.keep ? ' çevrilmeyecek' : ' → “' + r.az + '”') + fixNote(r), 'ok');
  undoPeek();
}
$('w-save').onclick = () => wordSave('');
$('w-keep').onclick = () => wordSave('keep');
$('w-remove').onclick = () => wordSave('remove');
$('w-az').addEventListener('keydown', (e) => { if (e.key === 'Enter') wordSave(''); });
function showUndo(last) {
  $('w-undo-row').hidden = !last;
  if (last) $('w-undo-what').textContent = 'son: “' + last.en + '” → ' + last.to + (last.rows ? ' · ' + last.rows + ' kayıt' : '');
}
async function undoPeek() { const r = await callApi('word_undo', { peek: true }); showUndo(r.ok ? r.last : null); }
$('w-undo').onclick = async () => {
  $('w-undo').disabled = true;
  const r = await callApi('word_undo', {});
  $('w-undo').disabled = false;
  if (!r.ok) { wInfo(r.error || 'tgwatch hatası', 'bad'); return; }
  wInfo('↶ Geri alındı: “' + r.en + '” yine ' + r.was + (r.restored ? ' · bankada ' + r.restored + ' kayıt eski hâline döndü' : ''), 'ok');
  showUndo(r.last);
};

// bankada ara: sözlük eşleşmeleri (tıkla → yukarıdaki kutuya gelir) + banka kayıtları (düzelt / sil)
const bMsg = (t, kind) => { const e = $('b-msg'); e.textContent = t; e.className = 'muted small' + (kind ? ' ' + kind : ''); };
const mk = (tag, cls, text) => { const e = document.createElement(tag); if (cls) e.className = cls; if (text !== undefined) e.textContent = text; return e; };
function bankRow(x) {
  const li = mk('li');
  const en = mk('span', 'en'); en.append(mk('span', 'tag', x.kind === 'category' ? 'kategori' : 'servis'), x.en);
  const az = mk('span', 'az', x.az);
  const acts = mk('div', 'acts');
  const edit = mk('button', 'mini', 'Düzelt'); edit.type = 'button';
  const del = mk('button', 'mini danger', 'Sil'); del.type = 'button';
  acts.append(edit, del);
  li.append(en, az, acts);
  edit.onclick = () => {
    const ta = mk('textarea'); ta.value = x.az; ta.setAttribute('aria-label', 'Azerice ad');
    const save = mk('button', 'mini', 'Kaydet'); save.type = 'button';
    const cancel = mk('button', 'mini', 'Vazgeç'); cancel.type = 'button';
    az.replaceWith(ta); acts.replaceChildren(save, cancel); ta.focus();
    cancel.onclick = () => li.replaceWith(bankRow(x));
    save.onclick = async () => {
      save.disabled = true;
      const r = await callApi('bank_save', { id: x.id, az: ta.value.trim() });
      if (!r.ok) { save.disabled = false; bMsg(r.error || 'tgwatch hatası', 'bad'); return; }
      x.az = r.az; li.replaceWith(bankRow(x)); bMsg('✓ Kaydedildi: bu ad bundan sonra böyle çevrilir.', 'ok');
    };
  };
  let armed = false;
  del.onclick = async () => {
    if (!armed) { armed = true; del.textContent = 'Emin misin? Sil'; setTimeout(() => { armed = false; del.textContent = 'Sil'; }, 4000); return; }
    del.disabled = true;
    const r = await callApi('bank_del', { id: x.id });
    if (!r.ok) { del.disabled = false; bMsg(r.error || 'tgwatch hatası', 'bad'); return; }
    li.remove(); bMsg('Bankadan silindi: ' + x.en, 'ok');
  };
  return li;
}
function wordRow(w) {
  const li = mk('li');
  const en = mk('span', 'en'); en.append(mk('span', 'tag', 'sözlük'), w.en);
  li.append(en, mk('span', 'az', w.state === 'keep' ? '(aynen kalsın)' : w.az));
  const acts = mk('div', 'acts');
  const b = mk('button', 'mini', 'Değiştir'); b.type = 'button';
  b.onclick = () => { $('w-en').value = w.en; $('w-az').value = w.state === 'keep' ? '' : w.az; wordInfo(); $('w-az').focus(); $('word').scrollIntoView({ block: 'start' }); };
  acts.append(b); li.append(acts);
  return li;
}
$('b-form').addEventListener('submit', async (e) => {
  e.preventDefault();
  const q = $('b-q').value.trim();
  if (q.length < 2) { bMsg('En az 2 harf yaz.', 'bad'); return; }
  bMsg('Aranıyor…');
  const r = await callApi('bank_search', { q });
  if (!r.ok) { bMsg(OLD(r) ? 'tgwatch sunucusu eski: banka araması yok — sunucuyu güncelle.' : (r.error || 'tgwatch hatası'), 'bad'); return; }
  $('b-words').replaceChildren(...r.words.map(wordRow));
  $('b-rows').replaceChildren(...r.rows.map(bankRow));
  bMsg(!r.words.length && !r.rows.length ? 'Sonuç yok.' : r.words.length + ' sözlük terimi, ' + r.rows.length + ' banka kaydı' + (r.rows.length >= 30 ? ' (ilk 30; aramayı daralt)' : ''));
});

// ---- sağlayıcı bakiyeleri: 10 dakikadan eskiyse kendiliğinden yenilenir, "Yenile" ile hemen ----
const money = (v) => {
  const [i, d] = (v !== 0 && Math.abs(v) < 1 ? v.toFixed(4) : v.toFixed(2)).split('.');
  return i.replace(/\B(?=(\d{3})+(?!\d))/g, ' ') + '.' + d;
};
function ago(t) {
  const m = Math.round((Date.now() - t) / 60000);
  return m < 1 ? 'az önce' : m < 60 ? m + ' dk önce' : Math.round(m / 60) + ' sa önce';
}
function drawBal(b) {
  const list = $('bal-list');
  list.textContent = '';
  if (!b.items.length) {
    $('bal-msg').textContent = 'tgwatch\'ta sağlayıcı yok (Ayarlar → Kaynaklar).';
    $('bal-tot').hidden = true;
    return;
  }
  for (const it of b.items) {
    const li = document.createElement('li');
    const n = document.createElement('span');
    n.className = 'n';
    n.textContent = it.name;
    const v = document.createElement('span');
    v.className = 'v';
    if (it.error) {
      li.className = 'err';
      v.textContent = '⚠ ' + it.error;
      v.title = it.error;
    } else {
      if (it.balance <= 0) li.className = 'zero';
      v.textContent = money(it.balance) + (it.currency ? ' ' + it.currency : '');
    }
    li.append(n, v);
    list.appendChild(li);
  }
  const tot = Object.entries(b.totals || {});
  const okN = b.items.filter((i) => !i.error).length;
  $('bal-tot').hidden = okN < 2;
  $('bal-tot').innerHTML = '';
  if (okN >= 2) {
    $('bal-tot').append('Toplam: ');
    tot.forEach(([c, v], i) => { const e = document.createElement('b'); e.textContent = money(v) + (c ? ' ' + c : ''); if (i) $('bal-tot').append(' · '); $('bal-tot').append(e); });
  }
  $('bal-msg').textContent = 'Son kontrol: ' + ago(b.at * 1000) + ' · her dakika kendiliğinden güncellenir';
}
async function balances(force) {
  const { bal, balErr } = await get({ bal: null, balErr: '' });
  if (bal && bal.items) drawBal(bal);
  if (balErr && !force) $('bal-msg').textContent = balErr;
  // arka plan her dakika çeker; yalnız hiç kayıt yoksa ya da "Yenile"ye basıldıysa burada sorulur
  if (!force && bal && bal.items) return;
  $('bal-ref').disabled = true;
  $('bal-msg').textContent = 'Bakiyeler soruluyor…';
  chrome.runtime.sendMessage({ type: 'api', action: 'balance', payload: force ? { force: 1 } : {} }, (r) => {
    $('bal-ref').disabled = false;
    if (chrome.runtime.lastError || !r || !r.ok) {
      $('bal-msg').textContent = (r && r.error) || 'tgwatch cevap vermedi';
      if (r && /Bilinmeyen işlem/.test(r.error || '')) $('bal-msg').textContent = 'tgwatch eski sürüm: sunucudaki src klasörünü güncelle.';
      return;
    }
    drawBal({ items: r.items, totals: r.totals, at: r.at });
  });
}
// arka plan yeni bakiye getirince açık mini panel de kendiliğinden güncellenir
chrome.storage.onChanged.addListener((ch, area) => { if (area === 'local' && ch.bal && ch.bal.newValue && ch.bal.newValue.items) drawBal(ch.bal.newValue); });
setInterval(() => { const b = $('bal-msg'); if (b && /Son kontrol/.test(b.textContent)) get({ bal: null }).then(({ bal }) => { if (bal && bal.items) drawBal(bal); }); }, 30000);
$('bal-ref').onclick = () => balances(true);

// ---- yeni sürüm ----
function newerV(a, b) {
  const x = String(a || '').split('.').map(Number);
  const y = String(b || '').split('.').map(Number);
  for (let i = 0; i < Math.max(x.length, y.length); i++) if ((x[i] || 0) !== (y[i] || 0)) return (x[i] || 0) > (y[i] || 0);
  return false;
}
function showUpd(u) {
  const mine = chrome.runtime.getManifest().version;
  $('v-mine').textContent = mine;
  if (u && u.latest !== undefined) $('v-server').textContent = u.latest || 'bilinmiyor';
  const has = !!(u && u.has && newerV(u.latest, mine));
  $('upd-dot').hidden = !has;
  $('v-server').className = has ? 'new' : '';
  const older = !!(u && u.latest && newerV(mine, u.latest));
  if (!older) $('upd-msg').textContent = has ? 'Yeni sürüm hazır: ' + u.latest + '.' : (u && u.latest ? 'Eklenti güncel.' : '');
}
$('upd-go').onclick = () => { chrome.tabs.create({ url: chrome.runtime.getURL('update.html') }); window.close(); };
$('upd-dl').onclick = (e) => {
  e.preventDefault();
  if (!tgBase) { $('upd-msg').textContent = 'tgwatch adresi bilinmiyor: Bağlantı ayarlarını kontrol et.'; return; }
  chrome.tabs.create({ url: tgBase + '?p=ext_zip' });
};
$('upd-reload').onclick = (e) => { e.preventDefault(); chrome.runtime.sendMessage({ type: 'reload' }, () => { void chrome.runtime.lastError; window.close(); }); };

$('v-mine').textContent = chrome.runtime.getManifest().version;

// ---- sekmeler (son açılan hatırlanır) ----
let balLoaded = false;
function showTab(name) {
  for (const b of document.querySelectorAll('.tabs [role=tab]')) {
    const on = b.dataset.tab === name;
    b.setAttribute('aria-selected', on ? 'true' : 'false');
    b.tabIndex = on ? 0 : -1;
    $(b.getAttribute('aria-controls')).hidden = !on;
  }
  if (name === 'bal' && !balLoaded) { balLoaded = true; balances(false); }
  if (name === 'bank') undoPeek();
  try { chrome.storage.local.set({ popTab: name }); } catch (e) { /* yok */ }
}
for (const b of document.querySelectorAll('.tabs [role=tab]')) {
  b.addEventListener('click', () => showTab(b.dataset.tab));
  b.addEventListener('keydown', (e) => {
    const all = [...document.querySelectorAll('.tabs [role=tab]')];
    const i = all.indexOf(b);
    const j = e.key === 'ArrowRight' ? (i + 1) % all.length : e.key === 'ArrowLeft' ? (i + all.length - 1) % all.length : -1;
    if (j >= 0) { e.preventDefault(); all[j].focus(); showTab(all[j].dataset.tab); }
  });
}
get({ popTab: 'panel' }).then((s) => showTab(['panel', 'bal', 'set', 'upd'].includes(s.popTab) ? s.popTab : 'panel'));

function refresh() {
  if (!tabId) return;
  chrome.tabs.sendMessage(tabId, { type: 'tgw-status' }, (r) => {
    if (chrome.runtime.lastError || !r) {
      $('tab-msg').textContent = 'Eklenti bu sayfada henüz çalışmıyor: sayfayı yenile.';
      return;
    }
    if (!r.screen) {
      $('tab-counts').hidden = true;
      $('undo').hidden = true;
      if (r.ctx === 'category' || r.ctx === 'list') {
        $('tab-msg').textContent = r.ctx === 'category' ? 'Edit category penceresi açık.' : 'Services listesi: en sondaki aktif kategori çevrilir, kaydedilir, en üste taşınır.';
        $('run').textContent = r.ctx === 'category' ? 'Bu kategorinin adını çevir' : 'Son kategorinin adını çevir';
        $('tab-actions').hidden = false;
        $('run').disabled = r.running;
      } else {
        $('tab-msg').textContent = 'Services → Import services → servisleri seç → inceleme ekranına geç.';
        $('tab-actions').hidden = true;
      }
      return;
    }
    $('run').textContent = 'Servisleri çevir';
    $('tab-msg').textContent = r.running ? 'Çeviriyor…' : r.rows + ' servis seçili.';
    $('tab-counts').hidden = !(r.counts.ok + r.counts.warn + r.counts.bad + r.counts.done);
    $('c-ok').textContent = r.counts.ok + r.counts.done;
    $('c-warn').textContent = r.counts.warn;
    $('c-bad').textContent = r.counts.bad;
    $('tab-actions').hidden = false;
    $('run').disabled = r.running;
    $('undo').hidden = !r.changed || r.running;
  });
}

$('run').onclick = () => chrome.tabs.sendMessage(tabId, { type: 'tgw-run' }, () => { void chrome.runtime.lastError; window.close(); });
$('undo').onclick = () => chrome.tabs.sendMessage(tabId, { type: 'tgw-undo' }, () => { void chrome.runtime.lastError; window.close(); });
$('dry').onchange = (e) => chrome.storage.local.set({ dry: e.target.checked });
$('skipAz').onchange = (e) => chrome.storage.local.set({ skipAz: e.target.checked });
$('autoCat').onchange = (e) => chrome.storage.local.set({ autoCat: e.target.checked });
$('catTop').onchange = (e) => chrome.storage.local.set({ catTop: e.target.checked });
$('notify').onchange = (e) => chrome.storage.local.set({ notify: e.target.checked });
$('bracketSpace').onchange = (e) => chrome.storage.local.set({ bracketSpace: e.target.checked });
$('stripMinMax').onchange = (e) => chrome.storage.local.set({ stripMinMax: e.target.checked });
$('stripSpeed').onchange = (e) => chrome.storage.local.set({ stripSpeed: e.target.checked });
$('descTranslate').onchange = (e) => chrome.storage.local.set({ descTranslate: e.target.checked });
$('descMode').onchange = (e) => chrome.storage.local.set({ descMode: e.target.value });
$('catLogo').onchange = (e) => chrome.storage.local.set({ catLogo: e.target.checked });
$('catLogoReplace').onchange = (e) => chrome.storage.local.set({ catLogoReplace: e.target.checked });
$('learnLogos').onclick = () => {
  $('learnMsg').textContent = 'Services sayfasında liste geziliyor…';
  chrome.tabs.sendMessage(tabId, { type: 'tgw-learn-logos' }, (r) => {
    void chrome.runtime.lastError;
    $('learnMsg').textContent = !r ? 'Bu sekmede panelin Services sayfası açık değil' : r.ok
      ? r.categories + ' kategori, ' + r.platforms.length + ' platform öğrenildi'
        + (r.uploaded != null ? ' · Uploaded: ' + r.uploaded + ' resim' : '') + (r.uploadedError ? ' · Uploaded: ' + r.uploadedError : '')
        + (r.unsure && r.unsure.length ? ' · kontrol et: ' + r.unsure.join(', ') + ' → Logo tablosu' : '') : r.error;
  });
};
$('logoTable').onclick = () => chrome.tabs.create({ url: chrome.runtime.getURL('logos.html') });
$('opt').onclick = (e) => { e.preventDefault(); chrome.runtime.openOptionsPage(); };
$('tg').onclick = (e) => { e.preventDefault(); chrome.tabs.create({ url: $('tg').href }); };
init();
