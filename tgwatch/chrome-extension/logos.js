// tgwatch — Logo tablosu: platform → logo eşleştirmesi (chrome.storage.local). Sayfayı eklentinin mini paneli açar.
// Veriyi içerik betiği (content.js) yazar: logoStat (listede görülen), logoAll (bilinen resimler), logoPlatforms (ad listesi).
// Bu sayfa yalnız logoPick (sabit seçimler) ve logoRules (özel kurallar) yazar.
const $ = (id) => document.getElementById(id);
const get = (d) => new Promise((r) => chrome.storage.local.get(d, r));
const set = (o) => new Promise((r) => chrome.storage.local.set(o, r));
const el = (tag, cls, text) => { const e = document.createElement(tag); if (cls) e.className = cls; if (text !== undefined) e.textContent = text; return e; };
const fileKey = (u) => { try { return new URL(u).pathname.split('/').pop().toLowerCase(); } catch (e) { return ''; } };
const same = (a, b) => !!a && !!b && fileKey(a) === fileKey(b);
const bg = (node, url) => { node.style.backgroundImage = 'url("' + String(url).replace(/["\\\n]/g, '') + '")'; return node; };
let S = { logoStat: {}, logoPick: {}, logoAll: {}, logoRules: [], logoPlatforms: [] };

function logoBox(url) {
  const d = el('span', 'logo' + (url ? '' : ' none'));
  if (url) bg(d, url);
  d.setAttribute('role', 'img');
  d.setAttribute('aria-label', url ? 'seçili logo' : 'logo yok');
  return d;
}
function btn(text, cls, fn, title) {
  const b = el('button', 'btn' + (cls ? ' ' + cls : ''), text);
  b.type = 'button';
  if (title) b.title = title;
  b.addEventListener('click', fn);
  return b;
}
/** Uploaded sekmesinde hiç resim görüldü mü (görülmediyse "Uploaded'da yok" uyarısı anlamsız). */
const scanned = () => Object.values(S.logoAll).some((x) => x && x.up);
const inUploaded = (url) => { const x = S.logoAll[fileKey(url)]; return !!(x && x.up); };

async function setPick(key, url) {
  const { logoPick } = await get({ logoPick: {} });
  if (url) logoPick[key] = url; else delete logoPick[key];
  await set({ logoPick });
}

function platformRow(key, label) {
  const pick = S.logoPick[key] || '';
  const stat = S.logoStat[key] || [];
  const cur = pick || (stat[0] && stat[0].url) || '';
  const li = el('li');
  li.dataset.key = key;
  li.appendChild(logoBox(cur));
  const who = el('div', 'who');
  who.appendChild(el('b', '', label));
  let state;
  if (pick) { state = 'fixed'; who.appendChild(el('span', 'chip ok', 'Sabit')); }
  else if (stat.length > 1) { state = 'unsure'; who.appendChild(el('span', 'chip warn', stat.length + ' farklı logo kullanılmış — birini seç')); }
  else if (stat.length === 1) { state = 'auto'; who.appendChild(el('span', 'chip auto', 'Otomatik · ' + stat[0].n + ' kategoride')); }
  else { state = 'none'; who.appendChild(el('span', 'chip bad', 'Logo yok')); }
  if (cur && scanned() && !inUploaded(cur)) {
    const c = el('span', 'chip bad', 'Uploaded\'da görülmedi');
    c.title = 'Bu resim listede bir kategoride kullanılıyor ama Uploaded sekmesinde görülmedi: eklenti onu seçemeyebilir. Uploaded\'daki bir resmi seç.';
    who.appendChild(c);
  }
  li.dataset.state = state;
  li.appendChild(who);
  if (!pick && stat.length > 1) {
    const alts = el('div', 'alts');
    for (const s of stat.slice(0, 6)) {
      const t = bg(el('button', 'th'), s.url);
      t.type = 'button';
      t.title = s.n + ' kategoride kullanılmış — tıkla: ' + label + ' için sabitle';
      t.setAttribute('aria-label', label + ' için bu logoyu sabitle (' + s.n + ' kategoride)');
      t.appendChild(el('i', '', String(s.n)));
      t.addEventListener('click', () => setPick(key, s.url));
      alts.appendChild(t);
    }
    li.appendChild(alts);
  }
  const acts = el('div', 'acts');
  if (state === 'auto') acts.appendChild(btn('Sabitle', '', () => setPick(key, cur), 'Bu logo hep kullanılsın'));
  acts.appendChild(btn(cur ? 'Değiştir' : 'Logo seç', 'ghost', () => choose(label, (u) => setPick(key, u))));
  if (pick) acts.appendChild(btn('Otomatiğe dön', 'ghost', () => setPick(key, ''), 'Sabit seçimi kaldır: listede en çok kullanılan logo seçilir'));
  li.appendChild(acts);
  return li;
}

function ruleRow(r, i) {
  const li = el('li');
  li.appendChild(logoBox(r.url));
  const who = el('div', 'who');
  who.appendChild(el('b', '', '“' + r.word + '”'));
  who.appendChild(el('span', 'chip ok', 'Adında geçerse bu logo'));
  if (scanned() && !inUploaded(r.url)) who.appendChild(el('span', 'chip bad', 'Uploaded\'da görülmedi'));
  li.appendChild(who);
  const acts = el('div', 'acts');
  acts.appendChild(btn('Değiştir', 'ghost', () => choose('“' + r.word + '”', async (u) => { const { logoRules } = await get({ logoRules: [] }); if (logoRules[i]) { logoRules[i].url = u; await set({ logoRules }); } })));
  acts.appendChild(btn('Sil', 'ghost', async () => { const { logoRules } = await get({ logoRules: [] }); logoRules.splice(i, 1); await set({ logoRules }); }));
  li.appendChild(acts);
  return li;
}

function render() {
  const plats = S.logoPlatforms;
  const has = (k) => !!S.logoPick[k] || (S.logoStat[k] || []).length > 0;
  const used = plats.filter(([k]) => has(k));
  const other = plats.filter(([k]) => !has(k));
  // kararsız olanlar en üstte (karar bekliyor), sonra otomatik, sonra sabit
  const rank = (k) => (S.logoPick[k] ? 2 : (S.logoStat[k] || []).length > 1 ? 0 : 1);
  used.sort((a, b) => rank(a[0]) - rank(b[0]));
  $('empty').hidden = plats.length > 0 && (used.length > 0 || S.logoRules.length > 0);
  $('used-wrap').hidden = !used.length;
  $('used').replaceChildren(...used.map(([k, l]) => platformRow(k, l)));
  $('other-wrap').hidden = !other.length;
  $('other-n').textContent = other.length;
  $('other').replaceChildren(...other.map(([k, l]) => platformRow(k, l)));
  $('rules').replaceChildren(...S.logoRules.map(ruleRow));
  const fixed = used.filter(([k]) => S.logoPick[k]).length;
  const unsure = used.filter(([k]) => !S.logoPick[k] && (S.logoStat[k] || []).length > 1).length;
  const auto = used.length - fixed - unsure;
  $('sum-text').textContent = used.length
    ? fixed + ' platform sabit · ' + auto + ' otomatik' + (unsure ? ' · ' + unsure + ' platformda karar bekliyor' : '') + ' · bilinen resim: ' + Object.keys(S.logoAll).length
    : 'Henüz platform logosu yok.';
  $('fix-all').hidden = !auto;
  $('fix-all').textContent = 'Otomatik ' + auto + ' platformu sabitle';
}

// ---- resim seçici ----
let onChoose = null;
function choose(label, cb) {
  onChoose = cb;
  $('ch-title').textContent = label + ' için logo seç';
  const all = Object.values(S.logoAll).filter((x) => x && x.u);
  const up = all.filter((x) => x.up);
  const rest = all.filter((x) => !x.up);
  $('ch-note').textContent = !all.length ? 'Henüz bilinen resim yok: panelde Services sayfasında "Logoları şimdi öğren"e bas, ya da aşağıya resim adresini yapıştır.'
    : !up.length ? 'Uploaded sekmesindeki resimler henüz görülmedi. Panelde herhangi bir kategoride Edit → logoya tıkla → Uploaded sekmesini bir kez aç; eklenti oradaki resimleri kaydeder.'
      : 'Uploaded sekmesinde görülen resimler kesin seçilebilir.';
  const g = $('ch-grid');
  g.replaceChildren();
  const add = (title, items, cls) => {
    if (!items.length) return;
    g.appendChild(el('h3', '', title));
    for (const x of items) {
      const t = bg(el('button', 'th' + (cls ? ' ' + cls : '')), x.u);
      t.type = 'button';
      t.title = fileKey(x.u);
      t.setAttribute('aria-label', 'Logo ' + fileKey(x.u));
      t.addEventListener('click', () => done(x.u));
      g.appendChild(t);
    }
  };
  add('Uploaded sekmesinde (' + up.length + ')', up);
  add('Yalnız listede görülen (' + rest.length + ') — Uploaded\'da olmayabilir', rest, 'list-only');
  $('ch-url-in').value = '';
  $('chooser').showModal();
}
async function done(url) {
  const cb = onChoose; onChoose = null;
  $('chooser').close();
  if (cb) await cb(url);
}
$('ch-url').addEventListener('submit', (e) => {
  e.preventDefault();
  const u = $('ch-url-in').value.trim();
  if (/^https?:\/\/\S+$/i.test(u) && fileKey(u)) done(u);
});

$('fix-all').addEventListener('click', async () => {
  const { logoPick } = await get({ logoPick: {} });
  for (const [k] of S.logoPlatforms) { const st = S.logoStat[k] || []; if (!logoPick[k] && st.length === 1) logoPick[k] = st[0].url; }
  await set({ logoPick });
});
$('rule-add').addEventListener('submit', (e) => {
  e.preventDefault();
  const word = $('rule-word').value.replace(/\s+/g, ' ').trim();
  if (!/\p{L}/u.test(word)) { $('rule-msg').textContent = 'Önce kelimeyi yaz.'; $('rule-word').focus(); return; }
  if (S.logoRules.some((r) => r.word.toLowerCase() === word.toLowerCase())) { $('rule-msg').textContent = 'Bu kelime için kural zaten var: listeden "Değiştir".'; return; }
  $('rule-msg').textContent = '';
  choose('“' + word + '”', async (u) => {
    const { logoRules } = await get({ logoRules: [] });
    logoRules.push({ word, url: u });
    await set({ logoRules });
    $('rule-word').value = '';
  });
});

async function load() {
  S = await get({ logoStat: {}, logoPick: {}, logoAll: {}, logoRules: [], logoPlatforms: [] });
  render();
}
chrome.storage.onChanged.addListener((ch, area) => { if (area === 'local' && Object.keys(ch).some((k) => k.startsWith('logo'))) load(); });
load();
