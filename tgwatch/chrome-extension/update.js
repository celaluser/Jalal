// tgwatch eklentisi — kendini güncelleme sayfası.
// Chrome, eklentinin kendi dosyalarını değiştirmesine izin vermez; ama kullanıcı eklenti klasörünü seçip yazma izni verirse
// (File System Access API) yeni dosyalar oraya yazılır, sonra eklenti yeniden yüklenir. Klasör tanıtıcısı IndexedDB'de hatırlanır.
const $ = (id) => document.getElementById(id);
const ALLOWED = /^[A-Za-z0-9_\-/]+\.(js|css|html|json|png|svg)$/;
const mine = chrome.runtime.getManifest().version;
let dir = null;

function log(text, kind) {
  const li = document.createElement('li');
  li.textContent = text;
  if (kind) li.className = kind;
  $('log').appendChild(li);
  li.scrollIntoView({ block: 'nearest' });
}

const newer = (a, b) => {
  const x = String(a || '').split('.').map(Number);
  const y = String(b || '').split('.').map(Number);
  for (let i = 0; i < Math.max(x.length, y.length); i++) if ((x[i] || 0) !== (y[i] || 0)) return (x[i] || 0) > (y[i] || 0);
  return false;
};

// ---- klasör tanıtıcısını hatırla (IndexedDB) ----
function idb() {
  return new Promise((res, rej) => {
    const r = indexedDB.open('tgw-update', 1);
    r.onupgradeneeded = () => r.result.createObjectStore('kv');
    r.onsuccess = () => res(r.result);
    r.onerror = () => rej(r.error);
  });
}
async function kv(key, val) {
  const db = await idb();
  return new Promise((res, rej) => {
    const tx = db.transaction('kv', val === undefined ? 'readonly' : 'readwrite');
    const st = tx.objectStore('kv');
    const r = val === undefined ? st.get(key) : val === null ? st.delete(key) : st.put(val, key);
    r.onsuccess = () => res(r.result);
    r.onerror = () => rej(r.error);
  });
}

async function readManifest(handle) {
  try {
    const f = await (await handle.getFileHandle('manifest.json')).getFile();
    return JSON.parse(await f.text());
  } catch (e) {
    return null;
  }
}

/** Seçilen klasör gerçekten bu eklentinin klasörü mü? */
async function checkDir(handle) {
  const m = await readManifest(handle);
  if (!m || !/tgwatch/i.test((m.short_name || '') + ' ' + (m.name || ''))) {
    return { ok: false, msg: 'Bu klasörde tgwatch eklentisi yok (manifest.json bulunamadı). Chrome\'a yüklediğin klasörü seç.' };
  }
  if (m.version !== mine) {
    return { ok: true, warn: true, msg: 'Klasördeki sürüm ' + m.version + ', çalışan sürüm ' + mine + '. Klasör yeni güncellendiyse "Eklentiyi yeniden yükle"ye bas; değilse Chrome\'un kullandığı klasör bu olmayabilir.' };
  }
  return { ok: true, msg: 'Klasör: ' + handle.name + ' (sürüm ' + m.version + ') ✓' };
}

async function permitted(handle, ask) {
  const o = { mode: 'readwrite' };
  if ((await handle.queryPermission(o)) === 'granted') return true;
  return ask ? (await handle.requestPermission(o)) === 'granted' : false;
}

function setReady(on) {
  $('from-server').disabled = !on;
  $('zip').disabled = !on;
  $('zip-l').setAttribute('aria-disabled', on ? 'false' : 'true');
}

async function useDir(handle, ask) {
  const c = await checkDir(handle);
  $('dir-msg').textContent = c.msg;
  $('dir-msg').className = 'note ' + (c.ok ? (c.warn ? 'warn' : 'ok') : 'bad');
  if (!c.ok) { setReady(false); return false; }
  dir = handle;
  await kv('dir', handle);
  $('pick').textContent = 'Klasör seçili';
  $('forget').hidden = false;
  setReady(true);
  if (ask === false) return true;
  return true;
}

$('pick').onclick = async () => {
  try {
    if (dir && (await permitted(dir, true))) { await useDir(dir); return; }
    const h = await window.showDirectoryPicker({ id: 'tgw-ext', mode: 'readwrite' });
    await useDir(h);
  } catch (e) {
    if (e && e.name !== 'AbortError') log('Klasör seçilemedi: ' + (e.message || e), 'bad');
  }
};
$('forget').onclick = async () => { dir = null; await kv('dir', null); $('pick').textContent = 'Klasörü seç'; $('forget').hidden = true; $('dir-msg').textContent = ''; setReady(false); $('pick').click(); };

// ---- zip okuma (stored + deflate) ----
async function unzip(buf) {
  if (buf.byteLength > 20 * 1024 * 1024) throw new Error('Zip çok büyük');
  const dv = new DataView(buf);
  const u8 = new Uint8Array(buf);
  let e = -1;
  for (let i = buf.byteLength - 22; i >= Math.max(0, buf.byteLength - 65557); i--) {
    if (dv.getUint32(i, true) === 0x06054b50) { e = i; break; }
  }
  if (e < 0) throw new Error('Bu dosya zip değil');
  const n = dv.getUint16(e + 10, true);
  let off = dv.getUint32(e + 16, true);
  const out = new Map();
  for (let k = 0; k < n; k++) {
    if (dv.getUint32(off, true) !== 0x02014b50) throw new Error('Zip bozuk');
    const method = dv.getUint16(off + 10, true);
    const csize = dv.getUint32(off + 20, true);
    const nl = dv.getUint16(off + 28, true);
    const xl = dv.getUint16(off + 30, true);
    const cl = dv.getUint16(off + 32, true);
    const lho = dv.getUint32(off + 42, true);
    const name = new TextDecoder().decode(u8.subarray(off + 46, off + 46 + nl)).replace(/\\/g, '/');
    off += 46 + nl + xl + cl;
    if (name.endsWith('/')) continue;
    const start = lho + 30 + dv.getUint16(lho + 26, true) + dv.getUint16(lho + 28, true);
    const data = u8.subarray(start, start + csize);
    let bytes;
    if (method === 0) bytes = data;
    else if (method === 8) bytes = new Uint8Array(await new Response(new Blob([data]).stream().pipeThrough(new DecompressionStream('deflate-raw'))).arrayBuffer());
    else continue;
    out.set(name, bytes);
  }
  return out;
}

/** Zip'in içindeki eklenti: tgwatch manifest.json'u olan en kısa yol (ör. tgwatch-eklenti/ ya da chrome-extension/). */
function pickExtension(files) {
  let best = null;
  for (const [p, b] of files) {
    if (!/(^|\/)manifest\.json$/.test(p)) continue;
    try {
      const m = JSON.parse(new TextDecoder().decode(b));
      if (!/tgwatch/i.test((m.short_name || '') + ' ' + (m.name || ''))) continue;
      const prefix = p.slice(0, -'manifest.json'.length);
      if (!best || prefix.length < best.prefix.length) best = { prefix, version: m.version };
    } catch (e) { /* bozuk json */ }
  }
  if (!best) throw new Error('Zip\'te tgwatch eklentisi yok (manifest.json bulunamadı)');
  const set = new Map();
  for (const [p, b] of files) if (p.startsWith(best.prefix)) set.set(p.slice(best.prefix.length), b);
  return { version: best.version, files: set };
}

function validate(pkg) {
  const files = new Map([...pkg.files].filter(([p]) => ALLOWED.test(p) && !p.split('/').includes('..')));
  for (const need of ['manifest.json', 'content.js', 'background.js', 'popup.html']) {
    if (!files.has(need)) throw new Error('Pakette ' + need + ' yok, kurulmadı');
  }
  return { version: pkg.version, files };
}

async function writeAll(pkg) {
  let n = 0;
  for (const [path, bytes] of pkg.files) {
    const parts = path.split('/');
    let d = dir;
    for (const seg of parts.slice(0, -1)) d = await d.getDirectoryHandle(seg, { create: true });
    const fh = await d.getFileHandle(parts[parts.length - 1], { create: true });
    const w = await fh.createWritable();
    await w.write(bytes);
    await w.close();
    n++;
  }
  return n;
}

async function install(pkg, from) {
  try {
    if (!dir || !(await permitted(dir, true))) { log('Önce eklenti klasörünü seç ve yazma izni ver.', 'bad'); return; }
    const p = validate(pkg);
    if (!newer(p.version, mine) && !confirm('Paketteki sürüm ' + p.version + ', çalışan ' + mine + '. Yine de kurulsun mu?')) { log('Vazgeçildi.'); return; }
    log(from + ': ' + p.files.size + ' dosya, sürüm ' + p.version + '. Yazılıyor…');
    const n = await writeAll(p);
    log(n + ' dosya yazıldı. Eklenti yeniden yükleniyor…', 'ok');
    $('reload').hidden = false;
    setTimeout(() => chrome.runtime.reload(), 1500);
  } catch (e) {
    log('Kurulamadı: ' + (e.message || e), 'bad');
  }
}

$('from-server').onclick = async () => {
  // İzin isteği tıklamanın hemen içinde olmalı (kullanıcı hareketi).
  if (!dir || !(await permitted(dir, true))) { log('Önce eklenti klasörünü seç.', 'bad'); return; }
  log('tgwatch\'tan dosyalar alınıyor…');
  chrome.runtime.sendMessage({ type: 'api', action: 'ext_files' }, (r) => {
    if (chrome.runtime.lastError || !r || !r.ok) {
      log((r && r.error) || 'tgwatch cevap vermedi' + (r && /Bilinmeyen/.test(r.error || '') ? ' (sunucudaki tgwatch eski: tgwatch-guncelleme.zip\'i yükle)' : ''), 'bad');
      return;
    }
    const files = new Map(r.files.map((f) => [f.path, Uint8Array.from(atob(f.b64), (c) => c.charCodeAt(0))]));
    install({ version: r.version, files }, 'tgwatch');
  });
};

$('zip').onchange = async (e) => {
  const f = e.target.files && e.target.files[0];
  e.target.value = '';
  if (!f) return;
  try {
    const pkg = pickExtension(await unzip(await f.arrayBuffer()));
    await install(pkg, f.name);
  } catch (err) {
    log('Zip okunamadı: ' + (err.message || err), 'bad');
  }
};
$('reload').onclick = () => chrome.runtime.reload();

(async () => {
  $('mine').textContent = mine;
  if (!window.showDirectoryPicker) {
    $('dir-msg').textContent = 'Bu tarayıcı klasöre yazmayı desteklemiyor (Chrome 102+ gerekir). Elle güncelle: zip\'i aç, dosyaları eklenti klasörüne kopyala, chrome://extensions → yenile.';
    $('dir-msg').className = 'note bad';
    $('pick').disabled = true;
  } else {
    try {
      const h = await kv('dir');
      if (h) {
        dir = h;
        if (await permitted(h, false)) await useDir(h, false);
        else { $('dir-msg').textContent = 'Klasör hatırlandı (' + h.name + '): izin için "Klasörü seç"e bas.'; $('dir-msg').className = 'note'; $('pick').textContent = 'İzin ver'; }
      }
    } catch (e) { /* ilk kez */ }
  }
  chrome.runtime.sendMessage({ type: 'api', action: 'ping' }, (r) => {
    $('server').textContent = r && r.ok ? (r.ext || 'bilinmiyor') : 'bağlantı yok';
    if (r && r.ok && r.ext && newer(r.ext, mine)) $('server').className = 'new';
  });
})();
