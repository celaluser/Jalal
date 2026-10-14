/* =============================================================
   ÇOKLU POPUP - SABLON DOSYASI
   popup-cron.php her calistiginda bu sablonu alir, POPUPS ve
   SETTINGS satirlarini gercek verilerle degistirir ve embed.js
   dosyasini uretir.

   Sitenize yapistiracaginiz tek satir:
   <script src="https://uplow.us/popup/embed.js" async><\/script>

   Birden cok popup ayni anda "aktif" olabilir; hepsi zamanlama ve
   gosterim kurallarina gore suzulur, uygun olanlar KUYRUKTA sirayla
   gosterilir (biri kapaninca digeri acilir).
   ============================================================= */

(function () {
  "use strict";

  /* ======================= CONFIG ======================= */
  var CONFIG = {
    footer:       "Click anywhere to close",
    delay:        1200,
    openInNewTab: false,
    enabled:      true,
    tzOffset:     240,   // dakika (Baku UTC+4) — zamanlama bu saate gore
    maxPerLoad:   2,     // bir sayfa acilisinda en fazla kac popup
    queueGapMs:   700,   // siradaki popup arasi bekleme
    maxAgeHours:  0,     // 0 = kapali
    forceTheme:   "",
    pages: { mode: "exclude", patterns: [] },
    stats: { enabled: false, url: "" },
    rules: {
      perDay:            3,
      perSession:        1,
      minGapMinutes:    60,
      maxTotal:          0,
      clickCooldownDays: 7
    },
    // panel kullanici verisini hangi JS degiskeninden okuyalim (segmentasyon)
    segment: { varName: "PANEL_USER" },
    // acil durdurma kontrolu (embed.js cache'ini asar)
    killCheck: { enabled: false, url: "" },
    botFilter: true,
    coupon: { url: "" }
  };
  /* ===================== /CONFIG ======================== */

  /* Bu iki satiri cron her calistiginda gercek degerlerle degistirir. */
  var POPUPS = null;
  var SETTINGS = null;
  var SKINS = null;

  /* Admin panelden gelen global ayarlar CONFIG'i ezer */
  (function () {
    if (!SETTINGS || typeof SETTINGS !== "object") return;
    for (var k in SETTINGS) {
      if (!Object.prototype.hasOwnProperty.call(SETTINGS, k)) continue;
      var v = SETTINGS[k];
      if (v && typeof v === "object" && !(v instanceof Array) && CONFIG[k] && typeof CONFIG[k] === "object") {
        for (var j in v) { if (Object.prototype.hasOwnProperty.call(v, j)) CONFIG[k][j] = v[j]; }
      } else {
        CONFIG[k] = v;
      }
    }
  })();

  var LIST = (POPUPS instanceof Array) ? POPUPS : [];

  var ov = null, styled = false, onClose = null;

  /* ---------- gösterim durumu (popup basina) ---------- */
  var LS_KEY = "igfp_rules_v2", SS_KEY = "igfp_sess_v2";

  function readAll() {
    try { var o = JSON.parse(localStorage.getItem(LS_KEY) || "null"); return (o && typeof o === "object") ? o : {}; }
    catch (e) { return {}; }
  }
  function writeAll(o) { try { localStorage.setItem(LS_KEY, JSON.stringify(o)); } catch (e) {} }

  function readState(pid) {
    var s = { day: "", dayCount: 0, total: 0, last: 0, clicked: 0 };
    var all = readAll(), o = all[pid];
    if (o && typeof o === "object") { for (var k in s) { if (o[k] != null) s[k] = o[k]; } }
    var today = new Date().toDateString();
    if (s.day !== today) { s.day = today; s.dayCount = 0; }
    return s;
  }
  function writeState(pid, s) { var all = readAll(); all[pid] = s; writeAll(all); }

  function sessAll() {
    try { var o = JSON.parse(sessionStorage.getItem(SS_KEY) || "null"); return (o && typeof o === "object") ? o : {}; }
    catch (e) { return {}; }
  }
  function sessCount(pid) { var o = sessAll(); return parseInt(o[pid] || 0, 10) || 0; }
  function sessInc(pid)   { try { var o = sessAll(); o[pid] = sessCount(pid) + 1; sessionStorage.setItem(SS_KEY, JSON.stringify(o)); } catch (e) {} }

  /* ---------- zamanlama (isletme saatine gore) ---------- */
  function bizDate() {
    var off = parseInt(CONFIG.tzOffset, 10); if (isNaN(off)) off = 0;
    return new Date(Date.now() + off * 60000);   // UTC getter'lari isletme saatini verir
  }
  function pad2(n) { return (n < 10 ? "0" : "") + n; }
  function hm(str) {
    var m = /^(\d{1,2}):(\d{2})$/.exec(String(str || ""));
    if (!m) return null;
    return (parseInt(m[1], 10) % 24) * 60 + parseInt(m[2], 10);
  }
  function scheduleActive(sc) {
    if (!sc || typeof sc !== "object") return true;
    var d = bizDate();
    var ymd = d.getUTCFullYear() + "-" + pad2(d.getUTCMonth() + 1) + "-" + pad2(d.getUTCDate());
    if (sc.dateFrom && ymd < sc.dateFrom) return false;
    if (sc.dateTo   && ymd > sc.dateTo)   return false;
    if (sc.days && sc.days.length && sc.days.indexOf(d.getUTCDay()) === -1) return false;
    var h = sc.hours || {};
    var f = hm(h.from), t = hm(h.to);
    if (f != null && t != null) {
      var cur = d.getUTCHours() * 60 + d.getUTCMinutes();
      if (f <= t) { if (cur < f || cur > t) return false; }     // ayni gun araligi
      else        { if (cur < f && cur > t) return false; }     // gece yarisini asan aralik
    }
    return true;
  }

  /* ---- sayfa kurali ---- */
  function globToRe(g) {
    var esc = String(g).replace(/[.+^${}()|[\]\\]/g, "\\$&").replace(/\*/g, ".*").replace(/\?/g, ".");
    return new RegExp("^" + esc + "$", "i");
  }
  function pageAllowed(pages) {
    var p = pages || CONFIG.pages || {}, list = p.patterns || [];
    if (!list.length) return true;
    var url = location.pathname + location.search;
    var hit = false;
    for (var i = 0; i < list.length; i++) {
      var pat = String(list[i] || "").trim();
      if (!pat) continue;
      if (pat.indexOf("*") === -1 && pat.indexOf("?") === -1) {
        if (url.toLowerCase().indexOf(pat.toLowerCase()) === 0) { hit = true; break; }
      } else if (globToRe(pat).test(url)) { hit = true; break; }
    }
    return p.mode === "include" ? hit : !hit;
  }

  /* ---- istatistik ---- */
  /* ---- istatistik kirilim anahtarlari ---- */
  function deviceKey() { return isMobile() ? "m" : "d"; }

  function sourceKey() {
    try {
      var m = /[?&]utm_source=([^&]+)/i.exec(location.search || "");
      if (m) return decodeURIComponent(m[1]).toLowerCase().slice(0, 40);
      var r = document.referrer || "";
      if (!r) return "direct";
      var h = r.replace(/^https?:\/\//i, "").split("/")[0].toLowerCase();
      if (h && location.hostname && h.indexOf(location.hostname) !== -1) return "internal";
      return h.slice(0, 40) || "direct";
    } catch (e) { return "direct"; }
  }

  function pageKey() { try { return (location.pathname || "/").slice(0, 60); } catch (e) { return "/"; } }

  /* gunde bir kez 1 doner -> tekil ziyaretci sayimi */
  function uniqueFlag() {
    try {
      var k = "igfp_uniq_v1", today = new Date().toDateString();
      if (localStorage.getItem(k) === today) return 0;
      localStorage.setItem(k, today);
      return 1;
    } catch (e) { return 0; }
  }

  function track(ev, pid, sid, ver, extra) {
    var st = CONFIG.stats || {};
    if (!st.enabled || !st.url) return;
    var body = "e=" + encodeURIComponent(ev)
             + (pid ? "&p=" + encodeURIComponent(pid) : "")
             + (sid ? "&s=" + encodeURIComponent(sid) : "")
             + (ver ? "&v=" + encodeURIComponent(ver) : "")
             + "&d="  + encodeURIComponent(deviceKey())
             + "&sr=" + encodeURIComponent(sourceKey())
             + "&pg=" + encodeURIComponent(pageKey());
    if (extra && extra.u) body += "&u=1";
    if (extra && extra.ms != null) body += "&ms=" + encodeURIComponent(extra.ms);
    try {
      if (navigator.sendBeacon) {
        navigator.sendBeacon(st.url, new Blob([body], { type: "text/plain;charset=UTF-8" }));
      } else if (typeof fetch === "function") {
        fetch(st.url, { method: "POST", body: body, keepalive: true, mode: "no-cors" });
      }
    } catch (e) {}
  }

  function rulesFor(p) { return (p.rules && typeof p.rules === "object") ? p.rules : (CONFIG.rules || {}); }

  function tooOld(p) {
    if (!CONFIG.maxAgeHours || !p || !p.updated) return false;
    return (Date.now() / 1000 - p.updated) > CONFIG.maxAgeHours * 3600;
  }

  /* ---- cihaz + kaynak hedefleme ---- */
  function isMobile() {
    try {
      if (window.matchMedia && window.matchMedia("(pointer: coarse)").matches) return true;
    } catch (e) {}
    return (window.innerWidth || 1024) < 768;
  }
  function deviceOK(p) {
    var t = p.targeting || {}, d = t.devices || [];
    if (!d.length) return true;
    var want = isMobile() ? "mobile" : "desktop";
    return d.indexOf(want) !== -1;
  }
  function sourceOK(p) {
    var t = p.targeting || {}, list = t.sources || [];
    if (!list.length) return true;
    var hay = ((document.referrer || "") + " " + (location.search || "")).toLowerCase();
    for (var i = 0; i < list.length; i++) {
      var k = String(list[i] || "").trim().toLowerCase();
      if (k && hay.indexOf(k) !== -1) return true;
    }
    return false;
  }

  /* ---- geri sayim (isletme saatine gore hedef) ---- */
  function countdownTarget(p) {
    if (!p.countdown) return 0;
    var off = parseInt(CONFIG.tzOffset, 10); if (isNaN(off)) off = 0;
    var t = Date.parse(p.countdown + ":00Z");           // isletme duvar saati
    if (isNaN(t)) return 0;
    return t - off * 60000;                             // gercek UTC epoch
  }
  function countdownExpired(p) {
    if (!p.countdown) return false;
    var tg = countdownTarget(p);
    return tg > 0 && (tg - Date.now()) <= 0;
  }

  /* ---- ziyaret sayaci (segmentasyon icin) ---- */
  var VS_KEY = "igfp_visit_v1";
  function visitState() {
    var v = { first: 0, visits: 0 };
    try {
      var o = JSON.parse(localStorage.getItem(VS_KEY) || "null");
      if (o && typeof o === "object") { v.first = o.first || 0; v.visits = o.visits || 0; }
    } catch (e) {}
    return v;
  }
  function visitBump() {                       // her yeni oturumda 1 artar
    var v = visitState(), fresh = false;
    try {
      if (!sessionStorage.getItem("igfp_vsess")) { sessionStorage.setItem("igfp_vsess", "1"); fresh = true; }
    } catch (e) { fresh = true; }
    if (fresh) {
      if (!v.first) v.first = Date.now();
      v.visits = (v.visits || 0) + 1;
      try { localStorage.setItem(VS_KEY, JSON.stringify(v)); } catch (e) {}
    }
    return v;
  }

  /* ---- panelin sayfaya verdigi kullanici bilgisi ---- */
  function panelUser() {
    var name = (CONFIG.segment && CONFIG.segment.varName) || "PANEL_USER";
    var u = null;
    try { if (window[name] && typeof window[name] === "object") u = window[name]; } catch (e) {}
    if (!u) { try { if (window.IGFP_USER && typeof window.IGFP_USER === "object") u = window.IGFP_USER; } catch (e) {} }
    if (!u) {
      try {
        var b = document.body && document.body.getAttribute("data-igfp-user");
        if (b) u = JSON.parse(b);
      } catch (e) {}
    }
    if (!u || typeof u !== "object") return null;
    var loggedIn = !!(u.loggedIn || u.logged_in || u.isLoggedIn || u.user_id || u.userId || u.uid);
    var bal = (u.balance != null) ? parseFloat(u.balance) : ((u.funds != null) ? parseFloat(u.funds) : null);
    var ord = (u.orders != null) ? parseInt(u.orders, 10) : ((u.order_count != null) ? parseInt(u.order_count, 10) : null);
    var ctry = String(u.country || u.country_code || "").toUpperCase();
    var nm = String(u.name || u.username || u.first_name || u.firstname || u.login || u.user_name || "").trim();
    return {
      name: nm,
      loggedIn: loggedIn,
      balance: (bal == null || isNaN(bal)) ? null : bal,
      orders:  (ord == null || isNaN(ord)) ? null : ord,
      country: ctry
    };
  }

  function detectCountry(u) {
    if (u && u.country) return u.country;
    try {
      var m = /-([A-Za-z]{2})$/.exec(navigator.language || navigator.userLanguage || "");
      if (m) return m[1].toUpperCase();
    } catch (e) {}
    return "";
  }

  /* Segment kurallari. Veri yoksa ve kural varsa -> gosterme (guvenli taraf). */
  function segmentOK(p) {
    var s = p.segment;
    if (!s) return true;
    var u = panelUser();

    if (s.login === "in"  && !(u && u.loggedIn)) return false;
    if (s.login === "out" &&  (u && u.loggedIn)) return false;

    if (s.balanceMin !== "" && s.balanceMin != null) { if (!u || u.balance == null || u.balance < +s.balanceMin) return false; }
    if (s.balanceMax !== "" && s.balanceMax != null) { if (!u || u.balance == null || u.balance > +s.balanceMax) return false; }
    if (s.ordersMin  !== "" && s.ordersMin  != null) { if (!u || u.orders  == null || u.orders  < +s.ordersMin)  return false; }
    if (s.ordersMax  !== "" && s.ordersMax  != null) { if (!u || u.orders  == null || u.orders  > +s.ordersMax)  return false; }

    var v = visitState();
    if (s.visitor === "new"       && v.visits > 1)  return false;
    if (s.visitor === "returning" && v.visits <= 1) return false;
    if (s.visitMin && v.visits < s.visitMin) return false;

    if (s.countries && s.countries.length) {
      var c = detectCountry(u);
      if (!c || s.countries.indexOf(c) === -1) return false;
    }
    return true;
  }

  /* Bir popup su an gosterilebilir mi? (kurallar dahil) */
  function canShow(p) {
    if (CONFIG.enabled === false) return false;
    if (!p || tooOld(p)) return false;
    if (!deviceOK(p)) return false;
    if (!sourceOK(p)) return false;
    if (!segmentOK(p)) return false;
    if (countdownExpired(p)) return false;
    if (!pageAllowed(p.pages)) return false;
    if (!scheduleActive(p.schedule)) return false;
    var r = rulesFor(p), s = readState(p.pid), now = Date.now();
    if (r.perDay            && s.dayCount     >= r.perDay)     return false;
    if (r.maxTotal          && s.total        >= r.maxTotal)   return false;
    if (r.perSession        && sessCount(p.pid) >= r.perSession) return false;
    if (r.minGapMinutes     && s.last    && (now - s.last)    < r.minGapMinutes * 60000)     return false;
    if (r.clickCooldownDays && s.clicked && (now - s.clicked) < r.clickCooldownDays * 86400000) return false;
    return true;
  }
  function recordShow(pid) {
    var s = readState(pid);
    s.dayCount++; s.total++; s.last = Date.now();
    writeState(pid, s);
    sessInc(pid);
  }
  function recordClick(pid) { var s = readState(pid); s.clicked = Date.now(); writeState(pid, s); }

  /* ---- kategori adindan platform temasini bul ---- */
  var THEMES = [
    { name: "instagram", re: /instagram|insta(?!ll)|\big\b/i },
    { name: "tiktok",    re: /tik\s*-?\s*tok/i },
    { name: "whatsapp",  re: /whats\s*-?\s*app|\bwp\b/i },
    { name: "telegram",  re: /telegram|\btg\b/i },
    { name: "youtube",   re: /you\s*-?\s*tube|\byt\b/i }
  ];
  function pickTheme(p) {
    if (p.theme) return p.theme;
    if (CONFIG.forceTheme) return CONFIG.forceTheme;
    var hay = [p.category, p.title, p.badge].join(" ");
    for (var i = 0; i < THEMES.length; i++) { if (THEMES[i].re.test(hay)) return THEMES[i].name; }
    return "default";
  }


  /* ---- GORUNUM: sablon (skin) + yerlesim (layout) + ozel renkler -> CSS ---- */
  var NONMODAL = { topbar: 1, stickybar: 1, fab: 1 };             // sayfayi karartmaz / kilitlemez
  var KEEP     = { topbar: 1, stickybar: 1, fab: 1, wheel: 1, scratch: 1, steps: 1 }; // sadece X ile kapanir
  function pk(p) { return "k-" + String(p.pid || "x").replace(/[^0-9A-Za-z_-]/g, ""); }
  function effSkin(p) {
    var base = SKINS && (SKINS[p.skin] || SKINS.sk01); if (!base) return null;
    var v = {}, k, st = p.style || {};
    for (k in base) { if (Object.prototype.hasOwnProperty.call(base, k)) v[k] = base[k]; }
    for (k in st) { if (Object.prototype.hasOwnProperty.call(st, k)) v[k] = st[k]; }
    return v;
  }
  function popCss(p) {
    var v = effSkin(p); if (!v) return "";
    var l = p.layout || "", c = ".igfp-ov." + pk(p) + " ", on = ".igfp-ov." + pk(p) + ".igfp-on ";
    var fg = v.fg || "#fff", r = v.r || "20px", br = v.br || "12px", acc = String(v.btn).indexOf("gradient") < 0 ? v.btn : "#6366f1";
    var css = c + ".igfp-frame{max-width:380px;padding:0;background:none;animation:none;border-radius:" + r + ";box-shadow:none}"
      + c + ".igfp-box{background:" + v.bg + ";color:" + fg + ";border-radius:" + r + ";padding:28px 24px 22px;text-align:center;"
      +   "box-shadow:0 24px 60px rgba(0,0,0,.45);" + (v.x || "") + "}"
      + c + ".igfp-box:before{display:none}"
      + c + ".igfp-h{color:" + fg + ";font-size:23px;line-height:1.15;margin:0 0 8px}"
      + c + ".igfp-sub{color:" + fg + ";opacity:.85;font-size:13.5px;line-height:1.45;margin:0 auto 16px}"
      + c + ".igfp-badge{background:" + v.bb + ";color:" + v.bf + ";border:0;font-size:11px;font-weight:700;padding:4px 10px;letter-spacing:.06em;margin-bottom:12px}"
      + c + ".igfp-dot{display:none}"
      + c + ".igfp-ic,"+ c + ".igfp-it{color:" + fg + "}"
      + c + ".igfp-btn{background:" + v.btn + ";color:" + v.btnf + "!important;border-radius:" + br + ";font-size:14px;font-weight:700;padding:12px;box-shadow:none}"
      + c + ".igfp-cpn-b{border:2px dashed currentColor;color:" + fg + ";border-radius:10px}"
      + c + ".igfp-cpn-l,"+ c + ".igfp-cpn-code{color:" + fg + "}"
      + c + ".igfp-cpn-code{background:transparent;font-size:15px;letter-spacing:.1em}"
      + c + ".igfp-cpn-btn{background:" + v.btn + ";color:" + v.btnf + "}"
      + c + ".igfp-cd-c{background:rgba(0,0,0,.16);border:0;border-radius:10px;padding:8px 0}"
      + c + ".igfp-cd-c b{color:" + fg + ";font-size:20px}" + c + ".igfp-cd-c span{color:" + fg + ";opacity:.7}"
      + c + ".igfp-media{border-radius:12px}"
      + c + ".igfp-x{color:" + fg + ";background:transparent;top:8px;right:10px;width:30px;height:30px;opacity:.6}"
      + c + ".igfp-foot{color:" + fg + ";opacity:.45;font-size:11.5px;margin-top:14px}"
      + c + ".igfp-opt{background:rgba(128,128,128,.14);color:" + fg + ";border:2px solid rgba(128,128,128,.3)}"
      + c + ".igfp-opt:hover," + c + ".igfp-opt.sel{border-color:" + acc + "}"
      + c + ".igfp-dots i{background:rgba(128,128,128,.35)}" + c + ".igfp-dots i.on{background:" + acc + "}"
      + c + ".igfp-wp{border-top-color:" + fg + "}" + c + ".igfp-wc{color:" + acc + "}"
      + c + ".igfp-vis{background:" + v.btn + ";color:" + v.btnf + "}"
      + c + ".igfp-fab{background:" + v.btn + ";color:" + v.btnf + "}";
    if (v.special === "banner" && !l) css += c + ".igfp-box{padding-top:0}" + c + ".igfp-box:before{display:block;position:static;width:auto;height:110px;border-radius:0;margin:0 -24px 18px;background:linear-gradient(135deg,#6366f1,#ec4899);filter:none}";
    if (v.special === "corner" && !l) css += ".igfp-ov." + pk(p) + "{align-items:flex-end;justify-content:flex-end;background:none;-webkit-backdrop-filter:none;backdrop-filter:none;pointer-events:none}"
      + c + ".igfp-frame{pointer-events:auto;margin:0}" + c + ".igfp-foot{display:none}";
    if (NONMODAL[l]) css += ".igfp-ov." + pk(p) + "{background:none;-webkit-backdrop-filter:none;backdrop-filter:none;pointer-events:none;padding:0;overflow:visible}"
      + c + ".igfp-frame{pointer-events:auto}" + c + ".igfp-foot{display:none}";
    if (KEEP[l]) css += c + ".igfp-foot{display:none}";
    var tr = ".igfp-frame{transition:transform .34s cubic-bezier(.2,.9,.3,1),opacity .34s ease}";
    switch (l) {
      case "sheet":
        css += ".igfp-ov." + pk(p) + "{align-items:flex-end;padding:0}"
          + c + ".igfp-frame{max-width:560px;margin:0 auto;transform:translateY(100%);opacity:1}" + on + ".igfp-frame{transform:none}"
          + c + ".igfp-box{border-radius:" + r + " " + r + " 0 0;text-align:left;padding:14px 22px 24px}"
          + c + ".igfp-box:before{display:block;position:static;width:42px;height:5px;border-radius:3px;background:rgba(128,128,128,.4);margin:0 auto 16px;filter:none}";
        break;
      case "drawer":
        css += ".igfp-ov." + pk(p) + "{justify-content:flex-end;padding:0}"
          + c + ".igfp-frame{max-width:380px;width:100%;height:100%;margin:0;transform:translateX(100%);opacity:1}" + on + ".igfp-frame{transform:none}"
          + c + ".igfp-box{border-radius:0;min-height:100%;text-align:left;padding:54px 22px 24px;overflow-y:auto}";
        break;
      case "topbar": case "stickybar":
        css += ".igfp-ov." + pk(p) + "{align-items:" + (l === "topbar" ? "flex-start" : "flex-end") + "}"
          + c + ".igfp-frame{max-width:none;width:100%;margin:0;transform:translateY(" + (l === "topbar" ? "-100%" : "100%") + ");opacity:1;pointer-events:auto}" + on + ".igfp-frame{transform:none}"
          + c + ".igfp-box{border-radius:0;padding:10px 44px 10px 16px;text-align:left}"
          + c + ".igfp-in{display:flex;align-items:center;gap:12px;flex-wrap:wrap}"
          + c + ".igfp-ic{font-size:26px;margin:0}" + c + ".igfp-badge{display:none}"
          + c + ".igfp-h{font-size:15px;margin:0}" + c + ".igfp-sub{margin:0;font-size:12.5px;flex:1 1 200px}"
          + c + ".igfp-btn{width:auto;flex:none;padding:8px 18px;margin:0}"
          + c + ".igfp-items,"+ c + ".igfp-media,"+ c + ".igfp-cd{display:none}" + c + ".igfp-cpn{margin:0}" + c + ".igfp-cpn-l{display:none}"
          + c + ".igfp-cpn-code{padding:6px 12px;font-size:13px}" + c + ".igfp-cpn-btn{padding:0 12px;font-size:12px}"
          + c + ".igfp-x{top:50%;margin-top:-15px}";
        break;
      case "full":
        css += ".igfp-ov." + pk(p) + "{padding:0}"
          + c + ".igfp-frame{max-width:none;height:100%;margin:0}"
          + c + ".igfp-box{border-radius:0;min-height:100%;display:flex;flex-direction:column;align-items:center;justify-content:center;padding:40px 24px}"
          + c + ".igfp-in{max-width:520px;width:100%}" + c + ".igfp-ic{font-size:72px}"
          + c + ".igfp-h{font-size:34px}" + c + ".igfp-sub{font-size:16px}" + c + ".igfp-btn{font-size:16px;padding:16px;border-radius:999px}"
          + c + ".igfp-x{top:16px;right:18px;font-size:20px}";
        break;
      case "split":
        css += c + ".igfp-frame{max-width:680px}"
          + c + ".igfp-box{display:flex;padding:0;text-align:left;overflow:hidden}"
          + c + ".igfp-vis{flex:0 0 42%;display:flex;align-items:center;justify-content:center;font-size:84px;background-size:cover;background-position:center}"
          + c + ".igfp-in{flex:1;padding:30px 24px 22px}"
          + "@media(max-width:560px){.igfp-ov." + pk(p) + " .igfp-box{flex-direction:column}.igfp-ov." + pk(p) + " .igfp-vis{flex:none;height:140px}}";
        break;
      case "fab":
        css += ".igfp-ov." + pk(p) + "{align-items:flex-end;justify-content:flex-end;padding:16px}"
          + c + ".igfp-frame{max-width:300px;margin:0 0 70px;transform-origin:bottom right;pointer-events:auto}"
          + c + ".igfp-box{text-align:left;padding:20px 18px 16px;border-radius:" + r + " " + r + " 4px " + r + "}"
          + c + ".igfp-fab{position:absolute;right:16px;bottom:16px;width:54px;height:54px;border-radius:50%;border:0;cursor:pointer;font-size:26px;pointer-events:auto;"
          +   "box-shadow:0 8px 24px rgba(0,0,0,.35);display:flex;align-items:center;justify-content:center}"
          + ".igfp-ov." + pk(p) + ".igfp-fab-closed .igfp-frame{transform:scale(.6);opacity:0;pointer-events:none}";
        break;
      case "wheel": case "scratch": case "steps":
        css += c + ".igfp-frame{max-width:400px}";
        break;
    }
    /* ---- kullanicinin ozel tasarim ayarlari (panel > Stil) ---- */
    var u = p.style || {}, fixed = (l === "topbar" || l === "stickybar" || l === "full");
    if (u.w && !fixed) css += c + ".igfp-frame{max-width:" + u.w + "px}";
    if (u.pad != null) css += (l === "split" ? c + ".igfp-in" : c + ".igfp-box") + "{padding:" + u.pad + "px}";
    if (u.tsz) css += c + ".igfp-h{font-size:" + u.tsz + "px}";
    if (u.ssz) css += c + ".igfp-sub{font-size:" + u.ssz + "px}";
    if (u.bsz) css += c + ".igfp-btn{font-size:" + u.bsz + "px}";
    if (u.br != null) css += c + ".igfp-btn{border-radius:" + u.br + "px}";
    if (u.bw != null) css += c + ".igfp-box{border:" + u.bw + "px solid " + (u.bc || "rgba(128,128,128,.4)") + "}";
    else if (u.bc) css += c + ".igfp-box{border-color:" + u.bc + "}";
    if (u.sh) css += c + ".igfp-box{box-shadow:" + (u.sh === "none" ? "none" : u.sh === "soft" ? "0 10px 30px rgba(0,0,0,.25)" : "0 30px 80px rgba(0,0,0,.65)") + "}";
    if (u.al) css += c + ".igfp-box,"+ c + ".igfp-in{text-align:" + u.al + "}" + (u.al === "left" ? c + ".igfp-sub{margin-left:0}" : u.al === "right" ? c + ".igfp-sub{margin-left:auto;margin-right:0}" : "");
    if (u.ov != null && !NONMODAL[l]) css += ".igfp-ov." + pk(p) + "{background:rgba(8,6,16," + (u.ov / 100) + ")}";
    if (u.anim && { "": 1, split: 1, wheel: 1, scratch: 1, steps: 1 }[l]) {
      var from = u.anim === "fade" ? "none" : u.anim === "slide" ? "translateY(50px)" : u.anim === "zoom" ? "scale(.8)" : "scale(.6)";
      css += c + ".igfp-frame{transform:" + from + ";opacity:0;transition:transform .45s " + (u.anim === "bounce" ? "cubic-bezier(.34,1.56,.64,1)" : "cubic-bezier(.2,.9,.3,1)") + ",opacity .35s ease}" + on + ".igfp-frame{transform:none;opacity:1}";
    }
    if (u.css) css += u.css;
    return css;
  }
  var skinDone = {};
  function ensureSkin(p) {
    var k = pk(p); if (skinDone[k] && skinDone[k] === p.v) return;
    var old = document.getElementById("igfp-s-" + k); if (old) old.remove();
    skinDone[k] = p.v || 1;
    var css = popCss(p); if (!css) return;
    var st = document.createElement("style"); st.id = "igfp-s-" + k; st.textContent = css; document.head.appendChild(st);
  }

  /* ---- Etkilesimli parcalar: carx / kazima / adimlar ---- */
  var WCOL = ["#f43f5e", "#fbbf24", "#22c55e", "#3b82f6", "#a855f7", "#f97316", "#14b8a6", "#ec4899"];
  function lsGet(k) { try { return localStorage.getItem(k); } catch (e) { return null; } }
  function lsSet(k, v) { try { localStorage.setItem(k, v); } catch (e) {} }
  function copyBtn(code) {
    return '<div class="igfp-cpn"><div class="igfp-cpn-b"><code class="igfp-cpn-code">' + esc(code) + '</code>'
         + '<button type="button" class="igfp-cpn-btn" data-copy="' + esc(code) + '">Kopyala</button></div></div>';
  }
  function ctaHtml(p, href) {
    if (!(p.button && p.button.url)) return "";
    return '<a class="igfp-btn" href="' + esc(href || p.button.url) + '"' + (CONFIG.openInNewTab ? ' target="_blank" rel="noopener"' : "") + ' data-cta="1">' + esc(p.button.text || "Aç") + "</a>";
  }
  function lsJson(k) { var v = lsGet(k); if (!v) return null; try { return JSON.parse(v); } catch (e) { return null; } }
  function renderWheel(p) {
    var pr = p.prizes || []; if (pr.length < 2) pr = [["5%", "SPIN5"], ["10%", "SPIN10"]];
    var n = pr.length, seg = 360 / n, g = [], lab = "", i;
    for (i = 0; i < n; i++) g.push(WCOL[i % WCOL.length] + " " + (i * seg) + "deg " + ((i + 1) * seg) + "deg");
    for (i = 0; i < n; i++) lab += '<div style="position:absolute;left:50%;top:50%;transform:rotate(' + (i * seg + seg / 2) + 'deg)"><span class="igfp-wl">' + esc(pr[i][0]) + "</span></div>";
    var res = null;
    if (p.srv) { var o = lsJson("igfp_ws_" + p.pid); if (o && o.l != null) res = [o.l, o.c || ""]; }
    else { var done = lsGet("igfp_w_" + p.pid); if (done !== null && pr[parseInt(done, 10)]) res = pr[parseInt(done, 10)]; }
    return '<div class="igfp-wh" data-n="' + n + '"><div class="igfp-wp"></div>'
         + '<div class="igfp-wr" style="background:conic-gradient(' + g.join(",") + ')">' + lab + '<b class="igfp-wc">SPIN</b></div></div>'
         + '<div class="igfp-wres">' + (res ? wheelResult(p, res, true) : '<button type="button" class="igfp-btn igfp-spin">Fırlat</button>') + "</div>";
  }
  function wheelResult(p, r, again) {
    return '<div class="igfp-sub" style="margin-bottom:10px">' + (again ? "Əvvəl qazandığınız: " : "Təbriklər! Qazandınız: ") + "<b>" + esc(r[0]) + "</b></div>"
         + (r[1] ? copyBtn(r[1]) : "") + ctaHtml(p);
  }
  function initWheel(p) {
    var btn = ov.querySelector(".igfp-spin"); if (!btn) return;
    var pr = p.prizes && p.prizes.length > 1 ? p.prizes : [["5%", "SPIN5"], ["10%", "SPIN10"]];
    function spinTo(idx, code, label) {
      var n = pr.length, seg = 360 / n;
      var jit = (Math.random() - .5) * seg * .6, ang = 360 * 5 + (360 - (idx * seg + seg / 2)) + jit;
      var w = ov.querySelector(".igfp-wr");
      w.style.transition = "transform 4.2s cubic-bezier(.17,.67,.12,1)"; w.style.transform = "rotate(" + ang + "deg)";
      setTimeout(function () {
        var box = ov && ov.querySelector(".igfp-wres"); if (!box) return;
        box.innerHTML = wheelResult(p, [label, code], false); track("click", p.pid, "spin", p.v);
      }, 4300);
    }
    btn.addEventListener("click", function () {
      btn.disabled = true; btn.style.opacity = ".6";
      if (p.srv) {                                   // sonucu SUNUCU belirler (stok + kisi basi limit)
        claimCoupon(p, function (r) {
          if (!ov) return;
          if (!r.ok) { var bx = ov.querySelector(".igfp-wres"); if (bx) bx.innerHTML = '<div class="igfp-sub">' + esc(claimMsg(r)) + "</div>"; return; }
          var idx = Math.max(0, Math.min(pr.length - 1, r.idx | 0));
          lsSet("igfp_ws_" + p.pid, JSON.stringify({ l: r.label, c: r.code }));
          spinTo(idx, r.code || "", r.label || pr[idx][0]);
        });
        return;
      }
      var idx = Math.floor(Math.random() * pr.length);
      lsSet("igfp_w_" + p.pid, String(idx));
      spinTo(idx, pr[idx][1], pr[idx][0]);
    });
  }
  function scratchCode(p) { return (p.coupon && p.coupon.code) || (p.prizes && p.prizes[0] && p.prizes[0][1]) || "SUPER"; }
  function renderScratch(p) {
    var saved = p.srv ? lsJson("igfp_ss_" + p.pid) : null;
    var seen = p.srv ? !!(saved && saved.c) : lsGet("igfp_s_" + p.pid) === "1";
    var code = p.srv ? (saved && saved.c) || "••••••" : scratchCode(p);
    return '<div class="igfp-sc' + (seen ? " done" : "") + '"><div class="igfp-sp2">' + esc(code) + '</div><canvas class="igfp-cv" width="300" height="100"></canvas></div>'
         + '<div class="igfp-sres">' + (seen ? copyBtn(code) + ctaHtml(p) : '<div class="igfp-sub" style="margin:0">Kartı barmağınla və ya siçanla sil</div>') + "</div>";
  }
  function initScratch(p) {
    var cv = ov.querySelector(".igfp-cv"), wrap = ov.querySelector(".igfp-sc"); if (!cv || wrap.classList.contains("done")) return;
    var cx = cv.getContext("2d"), W = cv.width, H = cv.height, down = false, last = 0, code = p.srv ? "" : scratchCode(p), got = !p.srv, failed = null;
    cx.fillStyle = "#b9bdcc"; cx.fillRect(0, 0, W, H);
    cx.fillStyle = "#a3a8ba"; for (var i = -H; i < W; i += 16) { cx.beginPath(); cx.moveTo(i, H); cx.lineTo(i + H, 0); cx.lineTo(i + H + 8, 0); cx.lineTo(i + 8, H); cx.fill(); }
    cx.fillStyle = "#6b7080"; cx.font = "800 17px sans-serif"; cx.textAlign = "center"; cx.fillText("✋ qaşı", W / 2, H / 2 + 6);
    cx.globalCompositeOperation = "destination-out";
    function pos(e) { var r = cv.getBoundingClientRect(); return [(e.clientX - r.left) * W / r.width, (e.clientY - r.top) * H / r.height]; }
    function dot(e) { var q = pos(e); cx.beginPath(); cx.arc(q[0], q[1], 16, 0, 7); cx.fill(); }
    function cover() {
      try { var d = cx.getImageData(0, 0, W, H).data, c = 0, t = 0; for (var i = 3; i < d.length; i += 64) { t++; if (d[i] < 40) c++; } return c / t; } catch (e) { return 1; }
    }
    function startClaim() {
      if (got || failed !== null || code) return; failed = false;
      claimCoupon(p, function (r) {
        if (!ov) return;
        if (r.ok && r.code) { code = r.code; got = true; lsSet("igfp_ss_" + p.pid, JSON.stringify({ c: code })); var sp = ov.querySelector(".igfp-sp2"); if (sp) sp.textContent = code; }
        else { failed = r; var sp2 = ov.querySelector(".igfp-sp2"); if (sp2) { sp2.textContent = r.reason === "out" ? "BİTİB" : "XƏTA"; sp2.style.fontSize = "20px"; } }
      });
    }
    function reveal() {
      if (p.srv && !got && !failed) return;                       // sunucu yaniti gelmeden acma
      wrap.classList.add("done"); track("click", p.pid, "scratch", p.v);
      if (!p.srv) lsSet("igfp_s_" + p.pid, "1");
      ov.querySelector(".igfp-sres").innerHTML = failed ? '<div class="igfp-sub" style="margin:0">' + esc(claimMsg(failed)) + "</div>" : copyBtn(code) + ctaHtml(p);
    }
    cv.addEventListener("pointerdown", function (e) { down = true; if (p.srv) startClaim(); dot(e); try { cv.setPointerCapture(e.pointerId); } catch (x) {} });
    cv.addEventListener("pointermove", function (e) { if (!down) return; dot(e); var t = Date.now(); if (t - last > 120) { last = t; if (cover() > .45) reveal(); } });
    cv.addEventListener("pointerup", function () { down = false; if (cover() > .45) reveal(); });
  }
  function renderSteps(p, finalHtml) {
    var st = p.steps || [], i, h = '<div class="igfp-dots">';
    for (i = 0; i <= st.length; i++) h += "<i" + (i === 0 ? ' class="on"' : "") + "></i>";
    h += "</div>";
    for (i = 0; i < st.length; i++) {
      h += '<div class="igfp-stp" data-i="' + i + '"' + (i ? " hidden" : "") + '><h2 class="igfp-h">' + esc(st[i].q) + '</h2><div class="igfp-opts">'
        + st[i].o.map(function (o) { return '<button type="button" class="igfp-opt" data-o="' + esc(o) + '">' + esc(o) + "</button>"; }).join("") + "</div></div>";
    }
    return h + '<div class="igfp-stp" data-i="' + st.length + '"' + (st.length ? " hidden" : "") + ">" + finalHtml + "</div>";
  }
  function initSteps(p) {
    var st = p.steps || [], picks = [], cur = 0, root = ov.querySelector(".igfp-in");
    function go(n) {
      cur = n;
      root.querySelectorAll(".igfp-stp").forEach(function (el) { el.hidden = (parseInt(el.getAttribute("data-i"), 10) !== n); });
      root.querySelectorAll(".igfp-dots i").forEach(function (d, i) { d.classList.toggle("on", i <= n); });
      if (n === st.length) {
        var a = root.querySelector("[data-cta]");
        if (a && picks.length) { var u = a.getAttribute("href"); a.setAttribute("href", u + (u.indexOf("?") < 0 ? "?" : "&") + "popup_choice=" + encodeURIComponent(picks.join("|"))); }
      }
    }
    root.addEventListener("click", function (e) {
      var o = e.target.closest(".igfp-opt"); if (!o) return;
      o.classList.add("sel"); picks.push(o.getAttribute("data-o"));
      setTimeout(function () { go(cur + 1); }, 220);
    });
  }

  /* ---- bot / otomasyon filtresi ---- */
  function isBot() {
    try {
      var ua = navigator.userAgent || "";
      if (/bot|crawl|spider|slurp|bingpreview|facebookexternalhit|headless|lighthouse|pagespeed|gtmetrix|phantom|selenium|puppeteer|playwright|python-requests|curl\/|wget|httpclient|monitor|uptime|archive|preview/i.test(ua)) return true;
      if (navigator.webdriver === true) return true;
      if (!navigator.languages || !navigator.languages.length) return true;
    } catch (e) {}
    return false;
  }

  /* ---- kisiye ozel metin: {ad} {bakiye} {siparis}  (yedek: {ad|Dostum}) ---- */
  function pz(str) {
    if (!str || String(str).indexOf("{") < 0) return str;
    var u = null; try { u = panelUser(); } catch (e) {}
    return String(str).replace(/\{(ad|bakiye|siparis)(?:\|([^}]*))?\}/gi, function (m, k, fb) {
      var v = "";
      k = k.toLowerCase();
      if (u) {
        if (k === "ad") v = u.name || "";
        else if (k === "bakiye") v = u.balance != null ? (Math.round(u.balance * 100) / 100) + "" : "";
        else if (k === "siparis") v = u.orders != null ? String(u.orders) : "";
      }
      return v !== "" ? v : (fb != null ? fb : "");
    }).replace(/\s+([,.!?:])/g, "$1").replace(/^[\s,]+/, "");
  }
  function pzPop(p) {
    var q = {}, k;
    for (k in p) { if (Object.prototype.hasOwnProperty.call(p, k)) q[k] = p[k]; }
    q.badge = pz(p.badge); q.title = pz(p.title); q.subtitle = pz(p.subtitle);
    if (p.button) q.button = { text: pz(p.button.text), url: p.button.url };
    if (p.coupon) q.coupon = { code: p.coupon.code, label: pz(p.coupon.label), gated: p.coupon.gated };
    return q;
  }

  /* ---- sunucu taraflı kupon (limitli): kodlar sadece buradan gelir ---- */
  function visitorId() {
    var v = null;
    try { v = localStorage.getItem("igfp_vid"); } catch (e) {}
    if (!v || v.length < 8) {
      v = ""; for (var i = 0; i < 4; i++) v += Math.random().toString(36).slice(2, 8);
      try { localStorage.setItem("igfp_vid", v); } catch (e) {}
    }
    return v;
  }
  function claimCoupon(p, cb) {
    var cu = CONFIG.coupon && CONFIG.coupon.url, done = false;
    function fin(r) { if (done) return; done = true; cb(r); }
    if (!cu || typeof fetch !== "function") { fin({ ok: false, reason: "net" }); return; }
    setTimeout(function () { fin({ ok: false, reason: "net" }); }, 8000);
    try {
      fetch(cu, { method: "POST", body: "a=claim&p=" + encodeURIComponent(p.pid) + "&k=" + encodeURIComponent(visitorId()),
                  headers: { "Content-Type": "application/x-www-form-urlencoded" }, credentials: "omit" })
        .then(function (r) { return r.json(); })
        .then(function (j) { fin(j && typeof j === "object" ? j : { ok: false, reason: "net" }); })
        .catch(function () { fin({ ok: false, reason: "net" }); });
    } catch (e) { fin({ ok: false, reason: "net" }); }
  }
  function claimMsg(r) {
    return r && r.reason === "out" ? "Təəssüf, kuponlar bitib." : "Bağlantı xətası. Bir az sonra yenidən cəhd edin.";
  }

  function esc(s) {
    return String(s == null ? "" : s).replace(/[&<>"']/g, function (c) {
      return { "&": "&amp;", "<": "&lt;", ">": "&gt;", '"': "&quot;", "'": "&#39;" }[c];
    });
  }

  var CSS = ''
  + '@import url("https://fonts.googleapis.com/css2?family=Poppins:wght@500;600;800&display=swap");'
  + '@property --igfp-a{syntax:"<angle>";initial-value:0deg;inherits:false}'
  + '.igfp-ov{'
  +   '--g:#f59e0b,#ec4899,#d946ef,#a855f7,#6366f1,#3b82f6,#f59e0b;'
  +   '--bg:#131221;--glow:rgba(168,85,247,.34);--dot:#ec4899;'
  +   '--card:#1c1b2c;--cardh:#232236;--cardb:rgba(236,72,153,.34);'
  +   '--pill:rgba(236,72,153,.16);--pillfg:#f472b6}'
  + '.igfp-ov.igfp-t-tiktok{--g:#00f2ea,#25f4ee,#f1f1f2,#ff0050,#ee1d52,#00f2ea;--bg:#0f1014;'
  +   '--glow:rgba(0,242,234,.22);--dot:#00f2ea;--card:#191a20;--cardh:#20212a;'
  +   '--cardb:rgba(0,242,234,.38);--pill:rgba(255,0,80,.18);--pillfg:#ff4d7d}'
  + '.igfp-ov.igfp-t-whatsapp{--g:#25d366,#4ae08a,#128c7e,#075e54,#25d366;--bg:#0e1a16;'
  +   '--glow:rgba(37,211,102,.24);--dot:#25d366;--card:#16241f;--cardh:#1c2d27;'
  +   '--cardb:rgba(37,211,102,.36);--pill:rgba(37,211,102,.16);--pillfg:#4ae08a}'
  + '.igfp-ov.igfp-t-telegram{--g:#37bbfe,#2aabee,#229ed9,#1c93e3,#37bbfe;--bg:#0d1622;'
  +   '--glow:rgba(42,171,238,.24);--dot:#2aabee;--card:#151f2c;--cardh:#1b2735;'
  +   '--cardb:rgba(42,171,238,.38);--pill:rgba(42,171,238,.18);--pillfg:#6ecaff}'
  + '.igfp-ov.igfp-t-youtube{--g:#ff0000,#ff4e45,#f1f1f1,#cc0000,#ff0000;--bg:#16100f;'
  +   '--glow:rgba(255,0,0,.20);--dot:#ff0000;--card:#211817;--cardh:#2a1e1d;'
  +   '--cardb:rgba(255,0,0,.35);--pill:rgba(255,0,0,.16);--pillfg:#ff6b6b}'
  + '.igfp-ov.igfp-t-default{--g:#6366f1,#8b5cf6,#a855f7,#3b82f6,#6366f1;--bg:#12131c;'
  +   '--glow:rgba(139,92,246,.28);--dot:#8b5cf6;--card:#1b1d29;--cardh:#222533;'
  +   '--cardb:rgba(139,92,246,.34);--pill:rgba(139,92,246,.18);--pillfg:#a78bfa}'
  + '.igfp-ov{position:fixed;inset:0;z-index:2147483000;display:flex;align-items:flex-start;justify-content:center;'
  + 'padding:20px;overflow-y:auto;-webkit-overflow-scrolling:touch;'
  + 'background:rgba(8,6,16,.62);-webkit-backdrop-filter:blur(14px) saturate(120%);backdrop-filter:blur(14px) saturate(120%);'
  + 'opacity:0;transition:opacity .28s ease;font-family:Poppins,-apple-system,BlinkMacSystemFont,"Segoe UI",Roboto,Helvetica,Arial,sans-serif}'
  + '.igfp-ov.igfp-on{opacity:1}'
  + '.igfp-frame{position:relative;width:100%;max-width:830px;margin:auto;padding:2px;border-radius:30px;'
  + 'background:conic-gradient(from var(--igfp-a),var(--g));'
  + 'animation:igfp-spin 9s linear infinite;box-shadow:0 40px 90px -20px rgba(0,0,0,.75);'
  + 'transform:translateY(14px) scale(.97);opacity:0;transition:transform .34s cubic-bezier(.2,.9,.3,1),opacity .34s ease}'
  + '.igfp-ov.igfp-on .igfp-frame{transform:none;opacity:1}'
  + '@keyframes igfp-spin{to{--igfp-a:360deg}}'
  + '@supports not (background:conic-gradient(from 0deg,#000,#fff)){.igfp-frame{background:linear-gradient(135deg,var(--g));animation:none}}'
  + '.igfp-box{position:relative;overflow:hidden;border-radius:28px;background:var(--bg);padding:38px 42px 30px}'
  + '.igfp-box:before{content:"";position:absolute;top:-160px;right:-120px;width:420px;height:420px;border-radius:50%;'
  + 'background:radial-gradient(circle,var(--glow),transparent 68%);pointer-events:none}'
  + '.igfp-in{position:relative;z-index:1}'
  + '.igfp-badge{display:inline-flex;align-items:center;gap:9px;padding:10px 18px;border-radius:999px;'
  + 'background:rgba(255,255,255,.055);border:1px solid rgba(255,255,255,.09);color:#efeaf5;'
  + 'font-size:12.5px;font-weight:600;letter-spacing:.13em;text-transform:uppercase}'
  + '.igfp-badge:empty{display:none}'
  + '.igfp-dot{width:8px;height:8px;border-radius:50%;background:var(--dot);box-shadow:0 0 10px var(--dot);flex:none}'
  + '.igfp-h{margin:20px 0 10px;font-size:34px;line-height:1.1;font-weight:800;color:#fff;letter-spacing:-.01em}'
  + '.igfp-h:empty{display:none}'
  + '.igfp-sub{margin:0 0 26px;max-width:610px;font-size:16px;line-height:1.5;font-weight:500;color:#a5a2b6}'
  + '.igfp-sub:empty{display:none}'
  + '.igfp-list{display:flex;flex-direction:column;gap:14px}'
  + '.igfp-ov *,.igfp-ov *:before,.igfp-ov *:after{box-sizing:border-box}'
  + '.igfp-ov a,.igfp-ov a:hover,.igfp-ov a:focus,.igfp-ov a:active,.igfp-ov a:visited{text-decoration:none!important}'
  + '.igfp-card{display:flex;align-items:center;gap:20px;padding:18px 24px;border-radius:18px;'
  + 'background:var(--card);border:1px solid rgba(255,255,255,.045);text-decoration:none!important;'
  + 'box-shadow:none;outline:none;'
  + 'transition:background .18s ease,border-color .18s ease,transform .18s ease}'
  + '.igfp-card:hover,.igfp-card:focus{background:var(--cardh);border-color:var(--cardb);'
  + 'transform:translateY(-2px);text-decoration:none!important}'
  + '.igfp-cl{flex:1;min-width:0}'
  + '.igfp-id{display:inline-block;padding:5px 11px;border-radius:8px;background:var(--pill);'
  + 'color:var(--pillfg);font-size:12.5px;font-weight:600;letter-spacing:.02em}'
  + '.igfp-name{margin-top:11px;font-size:15.5px;font-weight:600;color:#f4f2f8;line-height:1.35}'
  + '.igfp-price{flex:none;font-size:27px;font-weight:800;color:#fff;letter-spacing:-.01em}'
  /* ---- MANUEL (serbest icerik) popup ---- */
  + '.igfp-media{margin:0 0 22px;border-radius:18px;overflow:hidden;border:1px solid rgba(255,255,255,.07);line-height:0}'
  + '.igfp-media img{width:100%;height:auto;display:block}'
  + '.igfp-btn{display:flex;align-items:center;justify-content:center;gap:10px;width:100%;'
  + 'padding:18px 24px;border-radius:16px;background:linear-gradient(135deg,var(--g));'
  + 'color:#fff!important;font-size:17px;font-weight:800;letter-spacing:.01em;text-decoration:none!important;'
  + 'box-shadow:0 14px 30px -10px var(--glow);transition:transform .18s ease,filter .18s ease}'
  + '.igfp-btn:hover{transform:translateY(-2px);filter:brightness(1.08)}'
  /* ---- geri sayim ---- */
  + '.igfp-cd{display:flex;gap:10px;margin:0 0 22px}'
  + '.igfp-cd-c{flex:1;background:var(--card);border:1px solid rgba(255,255,255,.06);border-radius:14px;'
  + 'padding:14px 6px;text-align:center}'
  + '.igfp-cd-c b{display:block;font-size:30px;font-weight:800;color:#fff;line-height:1;letter-spacing:-.02em;'
  + 'font-variant-numeric:tabular-nums}'
  + '.igfp-cd-c span{display:block;margin-top:6px;font-size:11px;font-weight:600;color:var(--pillfg);'
  + 'text-transform:uppercase;letter-spacing:.08em}'
  /* ---- kupon ---- */
  + '.igfp-ic{font-size:44px;line-height:1.1;margin:0 0 8px}.igfp-ic:empty{display:none}'
  + '.igfp-items{margin:0 0 16px}.igfp-it{display:flex;justify-content:space-between;gap:12px;padding:9px 12px;border-radius:10px;background:rgba(128,128,128,.16);margin-bottom:7px;font-size:13px;font-weight:600;color:#f4f2f8}'
  + '.igfp-wh{position:relative;width:190px;height:190px;margin:6px auto 14px}'
  + '.igfp-wr{position:absolute;inset:0;border-radius:50%;border:6px solid #fff;box-shadow:0 0 0 3px rgba(124,58,237,.9),0 10px 30px rgba(0,0,0,.4)}'
  + '.igfp-wl{position:absolute;left:-26px;top:-84px;width:52px;text-align:center;font-size:10.5px;font-weight:800;color:#fff;text-shadow:0 1px 2px rgba(0,0,0,.5);overflow:hidden;white-space:nowrap;text-overflow:ellipsis}'
  + '.igfp-wc{position:absolute;inset:70px;border-radius:50%;background:#fff;display:flex;align-items:center;justify-content:center;font-size:11px}'
  + '.igfp-wp{position:absolute;z-index:3;top:-10px;left:50%;margin-left:-12px;border:12px solid transparent;border-top:20px solid #fff;border-bottom:0}'
  + '.igfp-spin{border:0;cursor:pointer;font-family:inherit}.igfp-spin:disabled{cursor:default}'
  + '.igfp-sc{position:relative;height:100px;border-radius:14px;overflow:hidden;margin:0 auto 14px;max-width:300px;background:#fff}'
  + '.igfp-sp2{position:absolute;inset:0;display:flex;align-items:center;justify-content:center;font-size:26px;font-weight:900;letter-spacing:.1em;color:#e11d48}'
  + '.igfp-cv{position:absolute;inset:0;width:100%;height:100%;touch-action:none;cursor:grab;transition:opacity .4s}.igfp-sc.done .igfp-cv{opacity:0;pointer-events:none}'
  + '.igfp-dots{display:flex;gap:6px;margin:0 0 18px}.igfp-dots i{flex:1;height:5px;border-radius:3px}'
  + '.igfp-opts{display:grid;grid-template-columns:1fr 1fr;gap:8px;margin:12px 0 4px}'
  + '.igfp-opt{font:inherit;font-weight:700;font-size:13px;padding:12px 8px;border-radius:12px;cursor:pointer;transition:border-color .15s}'
  + '.igfp-stp[hidden]{display:none}'
  + '.igfp-vis{display:none}.igfp-fab{display:none}'
  + '.igfp-cpn{margin:0 0 22px}'
  + '.igfp-cpn-l{display:block;margin-bottom:8px;font-size:12.5px;font-weight:600;color:#a5a2b6}'
  + '.igfp-cpn-b{display:flex;align-items:stretch;gap:0;border:1px dashed var(--cardb);border-radius:14px;overflow:hidden}'
  + '.igfp-cpn-code{flex:1;display:flex;align-items:center;justify-content:center;padding:15px 16px;'
  + 'background:var(--card);color:#fff;font-size:19px;font-weight:800;letter-spacing:.14em;'
  + 'font-family:ui-monospace,Menlo,Consolas,monospace}'
  + '.igfp-cpn-btn{flex:none;border:0;cursor:pointer;padding:0 22px;font:inherit;font-weight:700;font-size:14px;'
  + 'background:linear-gradient(135deg,var(--g));color:#fff;transition:filter .15s ease}'
  + '.igfp-cpn-btn:hover{filter:brightness(1.1)}'
  + '.igfp-cpn-btn.ok{background:#16a34a}'
  + '.igfp-foot{margin-top:26px;text-align:center;font-size:13.5px;font-weight:500;color:#6f6c82}'
  + '.igfp-x{position:absolute;top:24px;right:26px;z-index:2;width:42px;height:42px;border:0;border-radius:50%;cursor:pointer;'
  + 'display:flex;align-items:center;justify-content:center;background:rgba(255,255,255,.075);color:#fff;'
  + 'font-size:19px;line-height:1;transition:background .18s ease,transform .18s ease}'
  + '.igfp-x:hover{background:rgba(255,255,255,.16);transform:rotate(90deg)}'
  + '@media(max-width:640px){'
  + '.igfp-box{padding:30px 20px 22px}.igfp-h{font-size:26px}.igfp-sub{font-size:14.5px;margin-bottom:20px}'
  + '.igfp-card{padding:16px;gap:12px}.igfp-price{font-size:22px}.igfp-name{font-size:14px}'
  + '.igfp-btn{font-size:15.5px;padding:16px}'
  + '.igfp-badge{font-size:11px;padding:8px 14px}.igfp-x{top:16px;right:16px;width:36px;height:36px}}'
  + '@media(prefers-reduced-motion:reduce){.igfp-frame{animation:none}}';

  function renderServices(p) {
    return p.services.map(function (s) {
      var tag = s.url ? "a" : "div";
      var attrs = s.url ? ' href="' + esc(s.url) + '"' + (CONFIG.openInNewTab ? ' target="_blank" rel="noopener"' : "") : "";
      return "<" + tag + ' class="igfp-card"' + attrs + ' data-id="' + esc(s.id) + '">'
           +   '<div class="igfp-cl">'
           +     (s.id ? '<span class="igfp-id">ID ' + esc(s.id) + "</span>" : "")
           +     '<div class="igfp-name">' + esc(s.name) + "</div>"
           +   "</div>"
           +   '<div class="igfp-price">' + esc(s.price) + "</div>"
           + "</" + tag + ">";
    }).join("");
  }

  function renderContent(p) {
    var html = "";
    if (p.image) html += '<div class="igfp-media"><img src="' + esc(p.image) + '" alt="" loading="lazy"></div>';
    if (p.items && p.items.length) {
      html += '<div class="igfp-items">' + p.items.map(function (r) { return '<div class="igfp-it"><span>' + esc(r[0]) + '</span><b>' + esc(r[1]) + '</b></div>'; }).join('') + '</div>';
    }
    if (p.countdown) {
      var lbls = ["gün", "saat", "dəq", "san"];
      var cells = "";
      for (var i = 0; i < 4; i++) cells += '<div class="igfp-cd-c"><b>00</b><span>' + lbls[i] + "</span></div>";
      html += '<div class="igfp-cd" data-cd="' + countdownTarget(p) + '">' + cells + "</div>";
    }
    if (p.coupon && p.coupon.gated) {                 // limitli kupon: kod claim ile gelir
      var sv = lsJson("igfp_cs_" + p.pid);
      html += '<div class="igfp-cpn-slot">' + (sv && sv.c ? copyBtn(sv.c)
            : (p.coupon.label ? '<span class="igfp-cpn-l" style="display:block;margin-bottom:8px">' + esc(p.coupon.label) + "</span>" : "")
              + '<button type="button" class="igfp-btn igfp-claim" data-claim="1" style="margin:0 0 14px">Kuponu al</button>') + "</div>";
    } else if (p.coupon && p.coupon.code) {
      html += '<div class="igfp-cpn">'
            + (p.coupon.label ? '<span class="igfp-cpn-l">' + esc(p.coupon.label) + "</span>" : "")
            + '<div class="igfp-cpn-b"><code class="igfp-cpn-code">' + esc(p.coupon.code) + "</code>"
            + '<button type="button" class="igfp-cpn-btn" data-copy="' + esc(p.coupon.code) + '">Kopyala</button></div></div>';
    }
    if (p.button && p.button.url) {
      html += '<a class="igfp-btn" href="' + esc(p.button.url) + '"'
            + (CONFIG.openInNewTab ? ' target="_blank" rel="noopener"' : "")
            + ' data-cta="1">' + esc(p.button.text || "Aç") + "</a>";
    }
    return html;
  }

  /* canli geri sayim: cells = [gun,saat,dk,sn] */
  var cdTimer = null;
  function startCountdown(p) {
    if (!ov || !p.countdown) return;
    var box = ov.querySelector(".igfp-cd");
    if (!box) return;
    var cells = box.querySelectorAll("b");
    var target = countdownTarget(p);
    function tick() {
      var rem = Math.max(0, target - Date.now());
      var s = Math.floor(rem / 1000);
      var d = Math.floor(s / 86400); s -= d * 86400;
      var h = Math.floor(s / 3600);  s -= h * 3600;
      var m = Math.floor(s / 60);    s -= m * 60;
      var v = [d, h, m, s];
      for (var i = 0; i < cells.length && i < 4; i++) cells[i].textContent = (v[i] < 10 ? "0" : "") + v[i];
      if (rem <= 0) { clearInterval(cdTimer); cdTimer = null; close(); }  // kampanya bitti
    }
    tick();
    cdTimer = setInterval(tick, 1000);
  }

  function render(p) {
    p = pzPop(p);
    if (!styled) {
      var st = document.createElement("style");
      st.textContent = CSS;
      document.head.appendChild(st);
      styled = true;
    }

    var inner = (p.kind === "content") ? renderContent(p) : '<div class="igfp-list">' + renderServices(p) + "</div>";
    var lay = p.layout || "";
    if (lay === "wheel")   { inner = renderWheel(p); }
    if (lay === "scratch") { inner = renderScratch(p); }
    var main =
        '<div class="igfp-badge"><span class="igfp-dot"></span>' + esc(p.badge || "") + "</div>"
      + '<h2 class="igfp-h">' + esc(p.title || "") + "</h2>"
      + '<p class="igfp-sub">' + esc(p.subtitle || "") + "</p>" + inner;
    if (lay === "steps" && p.steps && p.steps.length) {
      main = '<div class="igfp-badge"><span class="igfp-dot"></span>' + esc(p.badge || "") + "</div>"
           + renderSteps(p, '<h2 class="igfp-h">' + esc(p.title || "") + '</h2><p class="igfp-sub">' + esc(p.subtitle || "") + "</p>" + inner);
    }

    ov = document.createElement("div");
    var styled2 = !!(SKINS && (p.skin || lay || p.style));
    ov.className = "igfp-ov igfp-t-" + pickTheme(p) + (styled2 ? " " + pk(p) + (lay ? " igfp-l-" + lay : "") : "");
    if (styled2) ensureSkin(p);
    ov.setAttribute("role", "dialog");
    ov.setAttribute("aria-modal", "true");
    ov.setAttribute("aria-label", p.title || p.badge || "");
    var vis = "";
    if (lay === "split") vis = '<div class="igfp-vis"' + (p.image ? ' style="background-image:url(&quot;' + esc(p.image) + '&quot;)"' : "") + ">" + (p.image ? "" : esc(p.icon || "")) + "</div>";
    if (lay === "split" && p.image && p.kind === "content") inner = inner.replace(/<div class="igfp-media">.*?<\/div>/, "");
    ov.innerHTML =
        '<div class="igfp-frame"><div class="igfp-box">'
      +   '<button class="igfp-x" type="button" aria-label="Close">&#10005;</button>' + vis
      +   '<div class="igfp-in">'
      +     (p.icon && lay !== "split" ? '<div class="igfp-ic">' + esc(p.icon) + "</div>" : "")
      +     main
      +     '<div class="igfp-foot">' + esc(CONFIG.footer) + "</div>"
      +   "</div>"
      + "</div></div>"
      + (lay === "fab" ? '<button class="igfp-fab" type="button" aria-label="Open">' + esc(p.icon || "💬") + "</button>" : "");

    document.body.appendChild(ov);
    if (p.countdown && lay !== "wheel" && lay !== "scratch") startCountdown(p);
    if (lay === "wheel") initWheel(p);
    if (lay === "scratch") initScratch(p);
    if (lay === "steps" && p.steps && p.steps.length) initSteps(p);
    ov.addEventListener("click", function (e) {
      var cp = e.target.closest("[data-copy]");
      if (cp) {                                   // kupon kodunu kopyala
        var code = cp.getAttribute("data-copy");
        try {
          if (navigator.clipboard && navigator.clipboard.writeText) navigator.clipboard.writeText(code);
          else {
            var ta = document.createElement("textarea"); ta.value = code;
            document.body.appendChild(ta); ta.select(); try { document.execCommand("copy"); } catch (x) {} ta.remove();
          }
        } catch (x) {}
        var old = cp.textContent; cp.textContent = "Kopyalandı ✓"; cp.classList.add("ok");
        setTimeout(function () { cp.textContent = old; cp.classList.remove("ok"); }, 1600);
        recordClick(p.pid); track("click", p.pid, "coupon", p.v);
        return;
      }
      var cl = e.target.closest("[data-claim]");
      if (cl) {
        cl.disabled = true; cl.textContent = "...";
        claimCoupon(p, function (r) {
          var slot = ov && ov.querySelector(".igfp-cpn-slot"); if (!slot) return;
          if (r.ok && r.code) { lsSet("igfp_cs_" + p.pid, JSON.stringify({ c: r.code })); slot.innerHTML = copyBtn(r.code); track("click", p.pid, "claim", p.v); }
          else slot.innerHTML = '<div class="igfp-sub">' + esc(claimMsg(r)) + "</div>";
        });
        return;
      }
      var fb = e.target.closest(".igfp-fab");
      if (fb) { ov.classList.toggle("igfp-fab-closed"); return; }
      var card = e.target.closest(".igfp-card");
      if (card) { recordClick(p.pid); track("click", p.pid, card.getAttribute("data-id"), p.v); return; }
      var cta = e.target.closest("[data-cta]");
      if (cta) { recordClick(p.pid); track("click", p.pid, "", p.v); return; }  // link kendisi gider
      if (KEEP[lay] && !e.target.closest(".igfp-x") && (e.target.closest(".igfp-box") || NONMODAL[lay])) return;
      close();
    });
  }

  var shownAt = 0, shownPid = "", shownV = "";
  function show(p, auto) {
    if (!NONMODAL[p.layout || ""]) document.body.style.overflow = "hidden";
    requestAnimationFrame(function () { ov && ov.classList.add("igfp-on"); });
    if (auto) {
      recordShow(p.pid);
      shownAt = Date.now(); shownPid = p.pid; shownV = p.v || "";
      track("view", p.pid, "", p.v, { u: uniqueFlag() });
    }
  }

  function openPopup(p, auto, cb) {
    onClose = cb || null;
    if (ov) { show(p, auto); return; }
    render(p);
    show(p, auto);
  }

  function close() {
    if (!ov) return;
    if (cdTimer) { clearInterval(cdTimer); cdTimer = null; }
    if (shownAt && shownPid) {                       // ne kadar acik kaldi (hizli kapatma olcumu)
      track("close", shownPid, "", shownV, { ms: Date.now() - shownAt });
      shownAt = 0; shownPid = ""; shownV = "";
    }
    ov.classList.remove("igfp-on");
    document.body.style.overflow = "";
    var el = ov; ov = null;
    var cb = onClose; onClose = null;
    setTimeout(function () { el.remove(); if (cb) cb(); }, 320);
  }

  document.addEventListener("keydown", function (e) { if (e.key === "Escape") close(); });
  document.addEventListener("click", function (e) {
    var t = e.target.closest && e.target.closest("[data-igfp-open]");
    if (t) {
      e.preventDefault();
      var want = t.getAttribute("data-igfp-open");
      var p = null;
      for (var i = 0; i < LIST.length; i++) { if (!want || want === "1" || LIST[i].pid === want) { p = LIST[i]; break; } }
      if (p) openPopup(p, false, null);
    }
  });

  /* ---- GÖSTERİM YÖNETİCİSİ: kuyruk + tetikleyiciler ---- */
  var busy = false, shownCount = 0;
  function maxLoad() { var m = parseInt(CONFIG.maxPerLoad, 10); return (isNaN(m) || m < 1) ? 1 : m; }
  function byPri(a, b) { return ((a.priority || 0) - (b.priority || 0)) || (LIST.indexOf(a) - LIST.indexOf(b)); }

  function present(p) {                       // gercekten goster (sayac + kilit)
    busy = true; shownCount++;
    openPopup(p, true, function () {          // kapaninca: kilidi ac, yuk kuyrugunu ilerlet
      busy = false;
      setTimeout(pumpLoad, parseInt(CONFIG.queueGapMs, 10) || 0);
    });
  }
  function tryPresent(p) {                     // uygunsa goster; degilse false
    if (busy || shownCount >= maxLoad() || !canShow(p)) return false;
    present(p); return true;
  }
  function fireFirst(list) { for (var i = 0; i < list.length; i++) { if (tryPresent(list[i])) return true; } return false; }

  var loadQ = [];
  function pumpLoad() {                         // 'load' tetikli popuplari sirayla dener
    while (loadQ.length) { if (tryPresent(loadQ.shift())) return; }
  }

  function armTriggers(pops) {                  // exit-intent + scroll
    var exitP   = pops.filter(function (p) { return p.trigger && p.trigger.type === "exit"; }).sort(byPri);
    var scrollP = pops.filter(function (p) { return p.trigger && p.trigger.type === "scroll"; }).sort(byPri);

    if (exitP.length) {                          // masaustu: imlec ustten cikinca
      document.addEventListener("mouseout", function (e) {
        if (e.clientY <= 0 && !e.relatedTarget) fireFirst(exitP);
      });
    }
    if (scrollP.length || exitP.length) {
      var lastY = window.pageYOffset || 0, ticking = false;
      window.addEventListener("scroll", function () {
        if (ticking) return; ticking = true;
        (window.requestAnimationFrame || function (f) { setTimeout(f, 16); })(function () {
          ticking = false;
          var y = window.pageYOffset || 0;
          var docH = Math.max(1, (document.documentElement.scrollHeight || 0) - (window.innerHeight || 0));
          var pct = Math.round(y / docH * 100);
          for (var i = 0; i < scrollP.length; i++) {
            if (pct >= (scrollP[i].trigger.scrollPercent || 50)) { if (tryPresent(scrollP[i])) break; }
          }
          if (exitP.length && isMobile() && (lastY - y) > 60 && y < 300) fireFirst(exitP);  // mobil exit vekili
          lastY = y;
        });
      }, false);
    }
  }

  /* Dışarıdan kontrol. open() elle çağrıldığında kurallar işlemez, sayaç artmaz. */
  window.IGFollowersPopup = {
    open:  function (pid) {
      var p = null;
      for (var i = 0; i < LIST.length; i++) { if (!pid || LIST[i].pid === pid) { p = LIST[i]; break; } }
      if (p) openPopup(p, false, null);
    },
    close: close,
    list:  function () { return LIST; },
    canShow: canShow,
    state: readState,
    reset: function () {
      try { localStorage.removeItem(LS_KEY); sessionStorage.removeItem(SS_KEY); } catch (e) {}
    }
  };

  /* ---- ACİL DURDURMA: cache'i asan kucuk kontrol ---- */
  function killCheck(cb) {
    var k = CONFIG.killCheck || {};
    if (!k.enabled || !k.url || typeof fetch !== "function") { cb(false); return; }
    var done = false;
    var t = setTimeout(function () { if (!done) { done = true; cb(false); } }, 2000);  // hata olursa ACIK kal
    try {
      fetch(k.url, { cache: "no-store", credentials: "omit" })
        .then(function (r) { return r.json(); })
        .then(function (j) { if (done) return; done = true; clearTimeout(t); cb(!!(j && j.off)); })
        .catch(function () { if (done) return; done = true; clearTimeout(t); cb(false); });
    } catch (e) { if (!done) { done = true; clearTimeout(t); cb(false); } }
  }

  function begin() {
    var loadPops = [], eventPops = [];
    for (var j = 0; j < LIST.length; j++) {
      var t = (LIST[j].trigger && LIST[j].trigger.type) || "load";
      if (t === "exit" || t === "scroll") eventPops.push(LIST[j]); else loadPops.push(LIST[j]);
    }
    loadQ = loadPops.slice().sort(byPri);
    armTriggers(eventPops);
    setTimeout(pumpLoad, CONFIG.delay);          // 'load' popuplari gecikmeyle
  }

  function start() {
    if (!LIST.length) return;
    if (window.__IGFP_PREVIEW) {                 // admin panel önizlemesi: kurallari/tetigi atla
      var idx = 0;
      if (window.__IGFP_PREVIEW_PID) {
        for (var i = 0; i < LIST.length; i++) { if (LIST[i].pid === window.__IGFP_PREVIEW_PID) { idx = i; break; } }
      }
      openPopup(LIST[idx], false, null);
      return;
    }
    if (CONFIG.botFilter !== false && isBot()) return;   // bot / otomasyon: gosterme, sayma
    visitBump();                                 // ziyaret sayacini isle (segmentasyon)
    killCheck(function (off) { if (!off) begin(); });
  }
  if (document.readyState === "loading") document.addEventListener("DOMContentLoaded", start);
  else start();
})();
