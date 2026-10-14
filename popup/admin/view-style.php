<?php if (!defined('IGFP_ADMIN')) { http_response_code(403); exit('Forbidden'); } ?>
<style>
:root{
  --bg:#0a0a11; --surface:#13131c; --surface2:#1a1a25; --surface3:#22222f;
  --line:rgba(255,255,255,.07); --line2:rgba(255,255,255,.12);
  --tx:#f2f0f8; --muted:#9a96ad; --faint:#6b6880;
  --acc:#ec4899; --acc2:#8b5cf6; --acc3:#3b82f6;
  --ok:#22c55e; --warn:#f59e0b; --err:#ef4444;
  --r:14px; --r2:10px;
  --grad:linear-gradient(135deg,var(--acc),var(--acc2));
}
*{box-sizing:border-box}
html,body{margin:0;padding:0}
body{background:var(--bg);color:var(--tx);
  font:14px/1.55 -apple-system,BlinkMacSystemFont,"Segoe UI",Roboto,Helvetica,Arial,sans-serif;
  -webkit-font-smoothing:antialiased}
a{color:inherit;text-decoration:none}
::selection{background:rgba(236,72,153,.3)}

/* ---------- iskelet ---------- */
.app{display:grid;grid-template-columns:238px minmax(0,1fr);min-height:100vh}
.side{background:var(--surface);border-right:1px solid var(--line);
  display:flex;flex-direction:column;position:sticky;top:0;height:100vh;overflow-y:auto}
.main{min-width:0;display:flex;flex-direction:column}
.inner{padding:22px 26px 90px;max-width:1400px;width:100%;margin:0 auto;flex:1}

/* ---------- sidebar ---------- */
.logo{display:flex;align-items:center;gap:11px;padding:20px 18px;border-bottom:1px solid var(--line);
  font-weight:700;font-size:15.5px;letter-spacing:-.01em}
.logo i{width:30px;height:30px;border-radius:9px;background:var(--grad);flex:none;
  display:flex;align-items:center;justify-content:center}
.logo i svg{width:16px;height:16px;stroke:#fff;fill:none;stroke-width:2.2}
nav{padding:12px 10px;display:flex;flex-direction:column;gap:2px;flex:1}
nav a{display:flex;align-items:center;gap:11px;padding:10px 12px;border-radius:var(--r2);
  color:var(--muted);font-weight:500;font-size:13.5px;transition:.15s;cursor:pointer}
nav a svg{width:17px;height:17px;stroke:currentColor;fill:none;stroke-width:1.9;flex:none;opacity:.85}
nav a:hover{background:var(--surface2);color:var(--tx)}
nav a.on{background:var(--grad);color:#fff}
nav a.on svg{opacity:1}
.side-foot{padding:14px 16px;border-top:1px solid var(--line);font-size:11.5px;color:var(--faint)}

/* ---------- topbar ---------- */
.top{position:sticky;top:0;z-index:30;display:flex;align-items:center;gap:12px;
  padding:12px 26px;background:rgba(10,10,17,.82);backdrop-filter:blur(14px);
  border-bottom:1px solid var(--line);flex-wrap:wrap}
.top h1{margin:0;font-size:16px;font-weight:650;letter-spacing:-.01em;flex:1;min-width:120px}
.burger{display:none;width:38px;height:38px;border-radius:var(--r2);border:1px solid var(--line);
  background:var(--surface2);cursor:pointer;align-items:center;justify-content:center;flex:none}
.burger svg{width:18px;height:18px;stroke:var(--tx);stroke-width:2}
.pill{display:inline-flex;align-items:center;gap:7px;padding:6px 12px;border-radius:99px;
  background:var(--surface2);border:1px solid var(--line);font-size:12.5px;font-weight:600;white-space:nowrap}
.pill b{width:7px;height:7px;border-radius:50%;display:block}
.pill.on b{background:var(--ok);box-shadow:0 0 8px var(--ok)}
.pill.off b{background:var(--err);box-shadow:0 0 8px var(--err)}

/* ---------- butonlar ---------- */
.btn{display:inline-flex;align-items:center;gap:8px;padding:9px 16px;border:0;border-radius:var(--r2);
  cursor:pointer;font:inherit;font-weight:600;font-size:13.5px;background:var(--grad);color:#fff;
  transition:.15s;white-space:nowrap}
.btn:hover{filter:brightness(1.12);transform:translateY(-1px)}
.btn:active{transform:none}
.btn.sec{background:var(--surface2);border:1px solid var(--line2);color:var(--tx)}
.btn.sec:hover{background:var(--surface3)}
.btn svg{width:15px;height:15px;stroke:currentColor;fill:none;stroke-width:2}
.btn.sm{padding:7px 12px;font-size:12.5px}

/* ---------- kartlar ---------- */
.card{background:var(--surface);border:1px solid var(--line);border-radius:var(--r);
  padding:20px;margin-bottom:16px}
.card > h2{margin:0;font-size:14.5px;font-weight:650;letter-spacing:-.01em;
  display:flex;align-items:center;gap:9px}
.card > h2 svg{width:16px;height:16px;stroke:var(--acc);fill:none;stroke-width:2}
.card > .hint{margin:6px 0 0;color:var(--muted);font-size:12.5px;line-height:1.5}
.card > .hint + .rows,.card > h2 + .rows{margin-top:16px}
.card > h2 + .body,.card > .hint + .body{margin-top:16px}

/* ---------- form satirlari ---------- */
.rows{display:flex;flex-direction:column}
.row{display:grid;grid-template-columns:minmax(180px,250px) 1fr;gap:8px 18px;
  padding:13px 0;border-top:1px solid var(--line);align-items:center}
.row:first-child{border-top:0;padding-top:0}
.row > .lbl{font-size:13px;font-weight:500}
.row > .lbl small{display:block;color:var(--faint);font-size:11.5px;font-weight:400;margin-top:3px;line-height:1.45}
@media(max-width:640px){.row{grid-template-columns:1fr;padding:14px 0}}

input[type=text],input[type=number],input[type=password],select,textarea{
  width:100%;padding:10px 12px;border-radius:var(--r2);background:var(--surface2);
  border:1px solid var(--line);color:var(--tx);font:inherit;font-size:13.5px;transition:.15s}
input:hover,select:hover,textarea:hover{border-color:var(--line2)}
input:focus,select:focus,textarea:focus{outline:none;border-color:var(--acc);
  box-shadow:0 0 0 3px rgba(236,72,153,.14)}
input::placeholder,textarea::placeholder{color:var(--faint)}
textarea{min-height:96px;resize:vertical;font-family:ui-monospace,SFMono-Regular,Menlo,Consolas,monospace;font-size:12.5px;line-height:1.7}
select{appearance:none;background-image:url("data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' viewBox='0 0 24 24' fill='none' stroke='%239a96ad' stroke-width='2'%3E%3Cpath d='M6 9l6 6 6-6'/%3E%3C/svg%3E");
  background-repeat:no-repeat;background-position:right 11px center;background-size:15px;padding-right:34px}
input[type=number]{max-width:170px}

/* ---------- switch ---------- */
.sw{display:inline-flex;align-items:center;gap:10px;cursor:pointer;user-select:none;font-size:13.5px}
.sw input{position:absolute;opacity:0;width:0;height:0}
.sw i{width:42px;height:24px;border-radius:99px;background:var(--surface3);position:relative;
  transition:.18s;flex:none;border:1px solid var(--line)}
.sw i:after{content:"";position:absolute;top:3px;left:3px;width:16px;height:16px;border-radius:50%;
  background:#8b8798;transition:.18s}
.sw input:checked + i{background:linear-gradient(135deg,#16a34a,#22c55e);border-color:transparent}
.sw input:checked + i:after{left:22px;background:#fff}
.sw input:focus-visible + i{box-shadow:0 0 0 3px rgba(236,72,153,.25)}

/* ---------- istatistik kutulari ---------- */
.tiles{display:grid;grid-template-columns:repeat(auto-fit,minmax(158px,1fr));gap:12px;margin-bottom:16px}
.tile{background:var(--surface);border:1px solid var(--line);border-radius:var(--r);padding:16px 17px;
  position:relative;overflow:hidden}
.tile:before{content:"";position:absolute;inset:0 0 auto 0;height:2px;background:var(--grad);opacity:.7}
.tile .t-lbl{color:var(--muted);font-size:11.5px;font-weight:600;text-transform:uppercase;letter-spacing:.06em}
.tile .t-val{font-size:24px;font-weight:750;letter-spacing:-.02em;margin-top:7px;line-height:1.1;
  overflow:hidden;text-overflow:ellipsis;white-space:nowrap}
.tile .t-sub{color:var(--faint);font-size:11.5px;margin-top:3px}

/* ---------- icerik gridi ---------- */
.split{display:grid;grid-template-columns:minmax(0,1fr) 400px;gap:18px;align-items:start}
.split > *{min-width:0}
@media(max-width:1180px){.split{grid-template-columns:minmax(0,1fr)}}
.preview-card{position:sticky;top:76px}
iframe{width:100%;height:540px;border:1px solid var(--line);border-radius:var(--r2);
  background:#07060c;display:block}
@media(max-width:1180px){iframe{height:440px}.preview-card{position:static}}

/* ---------- diger ---------- */
.flash{display:flex;align-items:flex-start;gap:10px;padding:13px 16px;border-radius:var(--r2);
  margin-bottom:16px;font-size:13.5px;font-weight:500;line-height:1.5}
.flash.ok{background:rgba(34,197,94,.1);border:1px solid rgba(34,197,94,.3);color:#8ef0ae}
.flash.err{background:rgba(239,68,68,.1);border:1px solid rgba(239,68,68,.3);color:#fda4a4}
.flash svg{width:17px;height:17px;stroke:currentColor;fill:none;stroke-width:2;flex:none;margin-top:1px}

.kvs{display:flex;flex-direction:column}
.kv{display:flex;justify-content:space-between;align-items:center;gap:14px;padding:11px 0;
  border-top:1px solid var(--line);font-size:13px}
.kv:first-child{border-top:0;padding-top:0}
.kv .k{color:var(--muted)}
.kv .v{font-weight:600;text-align:right;min-width:0;overflow-wrap:anywhere}

pre{background:#08080e;border:1px solid var(--line);border-radius:var(--r2);padding:13px 14px;
  overflow:auto;font-size:11.5px;line-height:1.75;color:#a8a4bb;margin:0;max-height:250px;
  font-family:ui-monospace,SFMono-Regular,Menlo,Consolas,monospace}
code{background:var(--surface2);padding:2px 7px;border-radius:6px;font-size:12px;color:#f9a8d4;
  font-family:ui-monospace,Menlo,Consolas,monospace;overflow-wrap:anywhere}

.tbl-wrap{overflow-x:auto;margin:0 -4px}
table{width:100%;border-collapse:collapse;font-size:13px;min-width:340px}
th,td{text-align:left;padding:10px 8px;border-bottom:1px solid var(--line)}
th{color:var(--muted);font-weight:600;font-size:11.5px;text-transform:uppercase;letter-spacing:.05em}
tr:last-child td{border-bottom:0}
.num{text-align:right;white-space:nowrap}

.chart{display:flex;align-items:flex-end;gap:4px;height:140px;margin:18px 0 7px}
.chart .bar{flex:1;background:linear-gradient(180deg,var(--acc),var(--acc2));border-radius:5px 5px 0 0;
  min-height:3px;position:relative;transition:.15s}
.chart .bar:hover{filter:brightness(1.25)}
.chart .bar span{position:absolute;top:-18px;left:50%;transform:translateX(-50%);
  font-size:10px;color:var(--muted);font-weight:600}
.chart-x{display:flex;gap:4px;font-size:10px;color:var(--faint)}
.chart-x div{flex:1;text-align:center}

.msvc{display:grid;grid-template-columns:100px minmax(0,1fr) 120px;gap:9px;margin-bottom:9px}
@media(max-width:640px){.msvc{grid-template-columns:1fr}}

/* ---------- popup listesi + editor ---------- */
.dim{color:var(--faint)}
.sub-h{margin:22px 0 4px;font-size:12.5px;font-weight:700;text-transform:uppercase;letter-spacing:.06em;
  color:var(--muted);display:flex;align-items:center;gap:8px;padding-top:16px;border-top:1px solid var(--line)}
.sub-h svg{width:15px;height:15px;stroke:var(--acc);fill:none;stroke-width:2}
.poprow{display:flex;align-items:center;gap:10px;flex-wrap:wrap;padding:13px 0;border-top:1px solid var(--line)}
.poprow:first-child{border-top:0;padding-top:0}
.poprow-main{flex:1;min-width:180px}
.poprow-name{font-size:14px;font-weight:650;display:flex;align-items:center;gap:9px}
.poprow-name .ptheme{font-size:10.5px;font-weight:600;color:var(--faint);text-transform:uppercase;
  letter-spacing:.05em;background:var(--surface2);padding:2px 8px;border-radius:99px}
.poprow-name .ptheme:empty{display:none}
.poprow-sub{color:var(--muted);font-size:12px;margin-top:3px;display:flex;align-items:center;gap:6px;flex-wrap:wrap}
.poprow-sub svg{width:13px;height:13px;stroke:currentColor;fill:none;stroke-width:2;opacity:.7;flex:none}
.poprow .btn svg{margin:0}
.dual{display:flex;align-items:center;gap:8px}
.dual input{max-width:150px}
.dual .dash{color:var(--faint);font-weight:600}
.days{display:flex;gap:6px;flex-wrap:wrap}
.days .day{cursor:pointer}
.days .day input{position:absolute;opacity:0;width:0;height:0}
.days .day span{display:inline-flex;align-items:center;justify-content:center;min-width:42px;padding:8px 10px;
  border-radius:var(--r2);background:var(--surface2);border:1px solid var(--line);font-size:12.5px;
  font-weight:600;color:var(--muted);transition:.15s}
.days .day input:checked + span{background:var(--grad);border-color:transparent;color:#fff}
.days .day input:focus-visible + span{box-shadow:0 0 0 3px rgba(236,72,153,.25)}

/* ---------- aralik cipleri ---------- */
.chips{display:flex;gap:7px;flex-wrap:wrap;margin-bottom:16px}
.chip{padding:7px 14px;border-radius:99px;background:var(--surface);border:1px solid var(--line);
  color:var(--muted);font-size:12.5px;font-weight:600;transition:.15s;white-space:nowrap}
.chip:hover{background:var(--surface2);color:var(--tx)}
.chip.on{background:var(--grad);color:#fff;border-color:transparent}

/* ---------- surum gecmisi ---------- */
.ver{border:1px solid var(--line);border-radius:var(--r2);margin-bottom:9px;background:var(--surface2);
  overflow:hidden}
.ver[open]{border-color:var(--line2)}
.ver summary{display:flex;align-items:center;gap:14px;flex-wrap:wrap;padding:13px 15px;cursor:pointer;
  list-style:none;transition:.15s}
.ver summary::-webkit-details-marker{display:none}
.ver summary:hover{background:var(--surface3)}
.ver-name{flex:1;min-width:150px;font-size:13.5px;font-weight:600;line-height:1.35}
.ver-name small{display:block;color:var(--faint);font-size:11.5px;font-weight:400;margin-top:3px}
.ver-name .tag{display:inline-block;margin-left:7px;padding:2px 8px;border-radius:99px;font-style:normal;
  font-size:10.5px;font-weight:700;background:rgba(34,197,94,.16);color:#6ee7a0;vertical-align:1px}
.ver-num{text-align:right;min-width:66px}
.ver-num b{display:block;font-size:15px;font-weight:700;line-height:1.2}
.ver-num small{color:var(--faint);font-size:10.5px}
.ver-body{padding:0 15px 15px;border-top:1px solid var(--line)}
.ver-body .tbl-wrap{margin-top:13px}
@media(max-width:560px){
  .ver summary{gap:10px}
  .ver-name{min-width:100%}
  .ver-num{min-width:auto;flex:1;text-align:left}
}

/* ---------- kaydet cubugu ---------- */
.savebar{position:sticky;bottom:0;z-index:20;display:flex;align-items:center;gap:14px;flex-wrap:wrap;
  margin:20px -26px -90px;padding:14px 26px;background:rgba(10,10,17,.9);
  backdrop-filter:blur(14px);border-top:1px solid var(--line)}
.savebar .note{color:var(--faint);font-size:12.5px}

/* ---------- giris ekrani ---------- */
.center{max-width:390px;margin:0 auto;padding:11vh 20px 40px}
.center .logo{justify-content:center;border:0;padding:0 0 22px}

/* ---------- mobil ---------- */
.scrim{display:none;position:fixed;inset:0;background:rgba(0,0,0,.6);z-index:39;
  backdrop-filter:blur(2px)}
@media(max-width:900px){
  .app{grid-template-columns:minmax(0,1fr)}
  .side{position:fixed;left:0;top:0;bottom:0;width:250px;z-index:40;height:100dvh;
    transform:translateX(-101%);transition:transform .22s ease;box-shadow:0 0 40px rgba(0,0,0,.5)}
  body.nav-open .side{transform:none}
  body.nav-open .scrim{display:block}
  .burger{display:flex}
  .top{padding:11px 16px}
  .inner{padding:16px 16px 90px}
  .savebar{margin:18px -16px -90px;padding:13px 16px}
  .card{padding:16px}
  .tile .t-val{font-size:21px}
}
@media(max-width:560px){
  .top .btn span{display:none}
  .top .btn{padding:9px 11px}
  .top{gap:8px}
}
@media(max-width:420px){
  .top h1{font-size:15px}
  .btn{padding:9px 13px;font-size:13px}
  .tiles{grid-template-columns:repeat(2,1fr)}
}

/* ---------- modal ---------- */
body.mdl-open{overflow:hidden}
.mdl{position:fixed;inset:0;z-index:100;display:flex;align-items:center;justify-content:center;padding:20px}
.mdl[hidden]{display:none}
.mdl-scrim{position:absolute;inset:0;background:rgba(5,5,10,.72);backdrop-filter:blur(6px)}
.mdl-box{position:relative;z-index:1;width:100%;max-width:760px;max-height:90vh;display:flex;flex-direction:column;
  background:var(--surface);border:1px solid var(--line2);border-radius:var(--r);
  box-shadow:0 30px 80px -20px rgba(0,0,0,.8);overflow:hidden;
  animation:mdl-in .18s cubic-bezier(.2,.9,.3,1)}
@keyframes mdl-in{from{transform:translateY(12px) scale(.98);opacity:0}to{transform:none;opacity:1}}
.mdl-box form{display:flex;flex-direction:column;min-height:0;max-height:90vh}
.mdl-head{display:flex;align-items:center;gap:12px;padding:16px 18px;border-bottom:1px solid var(--line);flex:none}
.mdl-head h3{margin:0;font-size:15px;font-weight:650;flex:1;min-width:0;
  overflow:hidden;text-overflow:ellipsis;white-space:nowrap}
.mdl-x{width:34px;height:34px;flex:none;border:1px solid var(--line);background:var(--surface2);
  border-radius:var(--r2);cursor:pointer;display:flex;align-items:center;justify-content:center;transition:.15s}
.mdl-x:hover{background:var(--surface3)}
.mdl-x svg{width:16px;height:16px;stroke:var(--tx);fill:none;stroke-width:2}
.mdl-tabs{display:flex;gap:4px;padding:10px 14px;border-bottom:1px solid var(--line);
  overflow-x:auto;flex:none;background:var(--bg)}
.mdl-tabs button{flex:none;padding:8px 14px;border:0;border-radius:99px;cursor:pointer;font:inherit;
  font-size:12.5px;font-weight:600;background:transparent;color:var(--muted);transition:.15s;white-space:nowrap}
.mdl-tabs button:hover{background:var(--surface2);color:var(--tx)}
.mdl-tabs button.on{background:var(--grad);color:#fff}
.mdl-body{padding:18px;overflow-y:auto;flex:1;min-height:0}
.mdl-body .row:first-child{border-top:0;padding-top:0}
.mdl-body pre{max-height:none}
.mdl-foot{display:flex;gap:10px;padding:14px 18px;border-top:1px solid var(--line);flex:none;
  background:var(--bg);flex-wrap:wrap}
@media(max-width:640px){
  .mdl{padding:0}
  .mdl-box{max-width:none;max-height:100vh;height:100vh;border-radius:0;border:0}
  .mdl-box form{max-height:100vh}
}

/* ---------- acil durdurma ---------- */
.btn.danger{background:linear-gradient(135deg,#dc2626,#ef4444)}
.kill-card.on{border-color:rgba(239,68,68,.45);background:linear-gradient(180deg,rgba(239,68,68,.07),transparent)}

/* ---------- etiketler ---------- */
.tagx{display:inline-block;padding:1px 7px;border-radius:99px;background:var(--surface3);
  color:var(--muted);font-size:10.5px;font-weight:700;letter-spacing:.03em}
.segtag{display:inline-flex;align-items:center;gap:5px;padding:1px 8px;border-radius:99px;
  background:rgba(139,92,246,.16);color:#c4b5fd;font-size:10.5px;font-weight:600}
.segtag svg{width:11px;height:11px;stroke:currentColor;fill:none;stroke-width:2}

/* ---------- iki sutunlu kart gridi ---------- */
.grid2{display:grid;grid-template-columns:repeat(auto-fit,minmax(280px,1fr));gap:16px}
.grid2 > .card{margin-bottom:0}
</style>

<style>
.tplgrid{display:grid;grid-template-columns:repeat(auto-fill,minmax(210px,1fr));gap:14px}
.tplc{border:1px solid var(--line);border-radius:14px;overflow:hidden;background:var(--card,transparent)}
.tplp{height:170px;display:flex;align-items:center;justify-content:center;padding:14px;background:linear-gradient(135deg,#2c3045,#14151c)}
.tplm{width:100%;padding:14px 12px;text-align:center;font-size:11px;box-shadow:0 10px 24px #0007}
.tplm .tplb{display:inline-block;font-size:8px;font-weight:700;padding:2px 7px;border-radius:99px;margin-bottom:6px;text-transform:uppercase}
.tplm b{display:block;font-size:12.5px;line-height:1.2;margin-bottom:9px}
.tplm i{display:block;font-style:normal;font-weight:700;font-size:10.5px;padding:7px}
.tplf{display:flex;align-items:center;justify-content:space-between;gap:8px;padding:10px 12px;font-size:12.5px}
</style>
