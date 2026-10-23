<?php
declare(strict_types=1);

namespace TgWatch\Web;

use TgWatch\Flow;

/** Panelin görsel parçaları: sayfa iskeleti, CSS, ikonlar, küçük bileşenler. */
final class Ui
{
    public function __construct(public string $csrf, public string $nonce)
    {
    }

    public static function e(string $s): string
    {
        return htmlspecialchars($s, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
    }

    public function csrfField(): string
    {
        return '<input type="hidden" name="csrf" value="' . self::e($this->csrf) . '">';
    }

    /**
     * Tek butonluk POST formu.
     * @param string $variant primary | quiet | danger | ghost
     */
    public function action(string $p, string $label, array $fields = [], string $variant = 'quiet', ?string $icon = null, ?string $confirm = null): string
    {
        $h = '<form method="post" action="?p=' . self::e($p) . '" class="inline"' . ($confirm ? ' data-confirm="' . self::e($confirm) . '"' : '') . '>' . $this->csrfField();
        foreach ($fields as $k => $v) {
            $h .= '<input type="hidden" name="' . self::e((string)$k) . '" value="' . self::e((string)$v) . '">';
        }
        return $h . '<button class="btn ' . $variant . '">' . ($icon ? self::icon($icon) : '') . '<span>' . self::e($label) . '</span></button></form>';
    }

    public static function link(string $href, string $label, string $variant = 'quiet', ?string $icon = null): string
    {
        return '<a class="btn ' . $variant . '" href="' . self::e($href) . '">' . ($icon ? self::icon($icon) : '') . '<span>' . self::e($label) . '</span></a>';
    }

    public static function badge(string $status): string
    {
        return '<span class="badge s-' . self::e($status) . '">' . self::e(Flow::LABELS[$status] ?? $status) . '</span>';
    }

    /** Kopyala butonu (yalnızca JavaScript açıkken görünür). */
    public static function copy(string $targetId, string $label = 'Kopyala'): string
    {
        return '<button type="button" class="btn ghost sm js-only" data-copy="' . self::e($targetId) . '">' . self::icon('copy') . '<span>' . self::e($label) . '</span></button>';
    }

    public static function ago(?int $ts): string
    {
        if (!$ts) {
            return '<span class="muted">—</span>';
        }
        $d = time() - $ts;
        $txt = match (true) {
            $d < 60 => 'az önce',
            $d < 3600 => intdiv($d, 60) . ' dk önce',
            $d < 86400 => intdiv($d, 3600) . ' sa önce',
            $d < 86400 * 7 => intdiv($d, 86400) . ' gün önce',
            default => date('d.m.Y', $ts),
        };
        return '<time title="' . date('d.m.Y H:i:s', $ts) . '">' . $txt . '</time>';
    }

    public static function empty(string $title, string $text, string $action = ''): string
    {
        return '<div class="empty"><p class="empty-title">' . self::e($title) . '</p><p class="muted">' . $text . '</p>' . ($action !== '' ? '<div class="row">' . $action . '</div>' : '') . '</div>';
    }

    public static function dot(string $state): string
    {
        return '<span class="dot ' . self::e($state) . '" aria-hidden="true"></span>';
    }

    /** Basit çizgi ikonlar (24×24, stroke). */
    public static function icon(string $name, int $size = 18): string
    {
        $p = [
            'queue' => '<path d="M4 6h16M4 12h10M4 18h7"/><circle cx="18" cy="16" r="3"/>',
            'jobs' => '<rect x="4" y="4" width="16" height="16" rx="2"/><path d="M8 9h8M8 13h8M8 17h5"/>',
            'channels' => '<path d="M4 11a8 8 0 0 1 16 0"/><path d="M7.5 11a4.5 4.5 0 0 1 9 0"/><circle cx="12" cy="13" r="1.5"/><path d="M12 15v5"/>',
            'dict' => '<path d="M5 4h11a3 3 0 0 1 3 3v13H8a3 3 0 0 1-3-3z"/><path d="M5 17a3 3 0 0 1 3-3h11"/><path d="M9 8h6"/>',
            'logs' => '<path d="M3 12h4l2-6 4 12 2-6h6"/>',
            'health' => '<path d="M12 20s-7-4.5-7-10a4 4 0 0 1 7-2.6A4 4 0 0 1 19 10c0 5.5-7 10-7 10z"/>',
            'logout' => '<path d="M15 4h3a2 2 0 0 1 2 2v12a2 2 0 0 1-2 2h-3"/><path d="M10 16l-4-4 4-4M6 12h10"/>',
            'check' => '<path d="M5 12.5l4.5 4.5L19 7.5"/>',
            'x' => '<path d="M6 6l12 12M18 6L6 18"/>',
            'refresh' => '<path d="M20 11a8 8 0 0 0-14.5-4.5L4 8"/><path d="M4 4v4h4"/><path d="M4 13a8 8 0 0 0 14.5 4.5L20 16"/><path d="M20 20v-4h-4"/>',
            'edit' => '<path d="M4 20h4L19 9l-4-4L4 16z"/><path d="M13.5 6.5l4 4"/>',
            'copy' => '<rect x="8" y="8" width="12" height="12" rx="2"/><path d="M16 8V6a2 2 0 0 0-2-2H6a2 2 0 0 0-2 2v8a2 2 0 0 0 2 2h2"/>',
            'download' => '<path d="M12 4v11M7 10l5 5 5-5"/><path d="M5 20h14"/>',
            'play' => '<path d="M8 5v14l11-7z"/>',
            'plus' => '<path d="M12 5v14M5 12h14"/>',
            'search' => '<circle cx="11" cy="11" r="6.5"/><path d="M16 16l4 4"/>',
            'trash' => '<path d="M5 7h14M10 7V5h4v2M7 7l1 13h8l1-13"/>',
            'globe' => '<circle cx="12" cy="12" r="8"/><path d="M4 12h16M12 4c2.5 2.5 2.5 13.5 0 16M12 4c-2.5 2.5-2.5 13.5 0 16"/>',
            'send' => '<path d="M4 12l16-8-6 16-2.5-6.5z"/><path d="M11.5 13.5L20 4"/>',
            'alert' => '<path d="M12 4l9 16H3z"/><path d="M12 10v4M12 17.5v.5"/>',
            'sun' => '<circle cx="12" cy="12" r="4"/><path d="M12 2v2M12 20v2M2 12h2M20 12h2M4.9 4.9l1.4 1.4M17.7 17.7l1.4 1.4M4.9 19.1l1.4-1.4M17.7 6.3l1.4-1.4"/>',
            'moon' => '<path d="M20 14.5A8 8 0 0 1 9.5 4 8 8 0 1 0 20 14.5z"/>',
            'link' => '<path d="M10 14a4 4 0 0 0 5.7 0l3-3a4 4 0 0 0-5.7-5.7l-1 1"/><path d="M14 10a4 4 0 0 0-5.7 0l-3 3a4 4 0 0 0 5.7 5.7l1-1"/>',
            'pause' => '<path d="M9 5v14M15 5v14"/>',
        ][$name] ?? '';
        return '<svg class="ic" width="' . $size . '" height="' . $size . '" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">' . $p . '</svg>';
    }

    /**
     * Sayfa iskeleti.
     * @param array{nav?:bool, active?:string, actions?:string, pending?:int, cron?:array, theme?:string, flash?:list<array{0:string,1:string}>, sub?:string} $o
     */
    public function page(string $title, string $body, array $o = []): string
    {
        $nav = $o['nav'] ?? true;
        $theme = $o['theme'] ?? 'auto';
        $flash = '';
        foreach ($o['flash'] ?? [] as [$t, $m]) {
            $flash .= '<div class="flash ' . self::e($t) . '" role="status">' . self::icon($t === 'bad' ? 'alert' : 'check') . '<span>' . self::e($m) . '</span></div>';
        }
        $side = '';
        if ($nav) {
            $items = [
                'dash' => ['Onay kuyruğu', 'queue'], 'jobs' => ['İşler', 'jobs'], 'channels' => ['Kanallar', 'channels'],
                'dict' => ['Sözlük', 'dict'], 'logs' => ['Kayıtlar', 'logs'], 'health' => ['Sağlık', 'health'],
            ];
            $links = '';
            foreach ($items as $k => [$label, $ic]) {
                $count = $k === 'dash' && !empty($o['pending']) ? '<span class="count">' . (int)$o['pending'] . '</span>' : '';
                $links .= '<a href="?p=' . $k . '"' . (($o['active'] ?? '') === $k ? ' aria-current="page"' : '') . '>' . self::icon($ic) . '<span>' . $label . '</span>' . $count . '</a>';
            }
            $cron = $o['cron'] ?? null;
            $cronLine = $cron ? '<div class="side-status">' . self::dot($cron['state']) . '<span>Cron ' . $cron['text'] . '</span></div>' : '';
            $next = $theme === 'dark' ? 'light' : ($theme === 'light' ? 'auto' : 'dark');
            $themeLabel = ['dark' => 'Koyu tema', 'light' => 'Açık tema', 'auto' => 'Sistem teması'][$theme];
            $side = '<aside class="side"><div class="flag" aria-hidden="true"><i></i><i></i><i></i></div>'
                . '<div class="brand">tgwatch<small>servis çeviri masası</small></div>'
                . '<nav class="nav">' . $links . '</nav>'
                . '<div class="side-foot">' . $cronLine
                . '<a class="side-link" href="?p=theme&amp;t=' . $next . '">' . self::icon($theme === 'dark' ? 'moon' : 'sun', 16) . '<span>' . $themeLabel . '</span></a>'
                . '<form method="post" action="?p=logout">' . $this->csrfField() . '<button class="side-link">' . self::icon('logout', 16) . '<span>Çıkış</span></button></form>'
                . '</div></aside>';
        }
        $head = '<header class="head"><div><h1>' . self::e($title) . '</h1>' . (!empty($o['sub']) ? '<p class="sub">' . $o['sub'] . '</p>' : '') . '</div>'
            . (!empty($o['actions']) ? '<div class="row">' . $o['actions'] . '</div>' : '') . '</header>';

        return '<!doctype html><html lang="tr" data-theme="' . self::e($theme) . '"><head><meta charset="utf-8">'
            . '<meta name="viewport" content="width=device-width,initial-scale=1"><meta name="robots" content="noindex,nofollow">'
            . '<title>' . self::e($title) . ' · tgwatch</title>'
            . '<link rel="preconnect" href="https://fonts.googleapis.com"><link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>'
            . '<link rel="stylesheet" href="https://fonts.googleapis.com/css2?family=IBM+Plex+Sans:wght@400;500;600&amp;display=swap">'
            . '<style nonce="' . $this->nonce . '">' . self::CSS . '</style></head>'
            . '<body class="' . ($nav ? 'has-side' : 'bare') . '">' . $side
            . '<main class="main">' . $head . $flash . $body . '</main>'
            . '<script nonce="' . $this->nonce . '">' . self::JS . '</script></body></html>';
    }

    private const JS = <<<'JS'
document.documentElement.classList.add('js');
document.addEventListener('click', function (e) {
  var b = e.target.closest('[data-copy]');
  if (!b) return;
  var el = document.getElementById(b.getAttribute('data-copy'));
  if (!el || !navigator.clipboard) return;
  navigator.clipboard.writeText(el.value !== undefined ? el.value : el.innerText).then(function () {
    b.classList.add('done'); setTimeout(function () { b.classList.remove('done'); }, 1400);
  });
});
document.addEventListener('submit', function (e) {
  var f = e.target, s = e.submitter, msg = (s && s.getAttribute('data-confirm')) || f.getAttribute('data-confirm');
  if (msg && !window.confirm(msg)) e.preventDefault();
});
document.addEventListener('change', function (e) {
  if (!e.target.matches('[data-checkall]')) return;
  document.querySelectorAll('input[name="ids[]"]').forEach(function (x) { x.checked = e.target.checked; });
});
JS;

    private const CSS = <<<'CSS'
:root{
  --caspian:#12324A;--caspian-2:#1B4562;--bg:#EDF1F4;--surface:#FFFFFF;--sunk:#F5F8FA;--line:#D5DEE5;--line-2:#E4EAEF;
  --text:#14222E;--muted:#5B6C7A;--blue:#0092BC;--blue-soft:#E1F3F9;--red:#D7002A;--red-soft:#FCE6EA;
  --green:#00A15E;--green-soft:#E0F5EC;--amber:#A86B12;--amber-soft:#FBF0DD;--focus:#0092BC;--on-accent:#FFFFFF;
  --font:"IBM Plex Sans",system-ui,-apple-system,"Segoe UI",Roboto,sans-serif;
  color-scheme:light;
}
@media (prefers-color-scheme:dark){:root:not([data-theme="light"]){
  --caspian:#0A1824;--caspian-2:#13293A;--bg:#0F1E2A;--surface:#152B3B;--sunk:#122533;--line:#24405A;--line-2:#1D3548;
  --text:#E2EAF0;--muted:#8DA2B2;--blue:#2FB4DD;--blue-soft:#123A4C;--red:#FF5A72;--red-soft:#43202A;
  --green:#2FCB86;--green-soft:#123B30;--amber:#E2A74B;--amber-soft:#3B2E17;--on-accent:#06202C;color-scheme:dark}}
:root[data-theme="dark"]{
  --caspian:#0A1824;--caspian-2:#13293A;--bg:#0F1E2A;--surface:#152B3B;--sunk:#122533;--line:#24405A;--line-2:#1D3548;
  --text:#E2EAF0;--muted:#8DA2B2;--blue:#2FB4DD;--blue-soft:#123A4C;--red:#FF5A72;--red-soft:#43202A;
  --green:#2FCB86;--green-soft:#123B30;--amber:#E2A74B;--amber-soft:#3B2E17;--on-accent:#06202C;color-scheme:dark}
*{box-sizing:border-box}
html{-webkit-text-size-adjust:100%}
body{margin:0;background:var(--bg);color:var(--text);font:15px/1.55 var(--font);font-feature-settings:"tnum" 1}
a{color:var(--blue);text-underline-offset:2px}
:focus-visible{outline:2px solid var(--focus);outline-offset:2px;border-radius:4px}
.ic{flex:none;display:block}
.muted{color:var(--muted)}
.js-only{display:none!important}.js .js-only{display:inline-flex!important}

/* yerleşim */
.has-side{display:grid;grid-template-columns:232px minmax(0,1fr);min-height:100vh}
.side{background:var(--caspian);color:#C9D8E3;display:flex;flex-direction:column;position:sticky;top:0;height:100vh;padding:0 0 16px}
.flag{display:flex;height:4px}.flag i{flex:1}.flag i:nth-child(1){background:#0092BC}.flag i:nth-child(2){background:#E4002B}.flag i:nth-child(3){background:#00AF66}
.brand{padding:20px 20px 18px;color:#fff;font-weight:600;font-size:18px;letter-spacing:-.01em}
.brand small{display:block;font-weight:400;font-size:12.5px;color:#8FA9BC;letter-spacing:0}
.nav{display:flex;flex-direction:column;gap:2px;padding:0 10px}
.nav a{display:flex;align-items:center;gap:11px;padding:9px 12px;border-radius:8px;color:#C9D8E3;text-decoration:none;font-weight:500}
.nav a:hover{background:var(--caspian-2);color:#fff}
.nav a[aria-current="page"]{background:#fff;color:var(--caspian)}
.nav .count{margin-left:auto;background:#0092BC;color:#fff;font-size:12px;font-weight:600;padding:1px 8px;border-radius:999px}
.nav a[aria-current="page"] .count{background:var(--caspian);color:#fff}
.side-foot{margin-top:auto;padding:0 10px;display:flex;flex-direction:column;gap:2px}
.side-status{display:flex;align-items:center;gap:9px;padding:8px 12px;font-size:13px;color:#A9BDCB}
.side-link{display:flex;align-items:center;gap:10px;width:100%;padding:8px 12px;border:0;background:none;color:#A9BDCB;font:inherit;font-size:13.5px;text-decoration:none;border-radius:8px;cursor:pointer;text-align:left}
.side-link:hover{background:var(--caspian-2);color:#fff}
.side form{margin:0}
.main{padding:28px 32px 64px;max-width:1180px;width:100%}
.bare .main{max-width:440px;margin:8vh auto 0;padding:24px 16px}
.head{display:flex;align-items:flex-end;justify-content:space-between;gap:16px;flex-wrap:wrap;margin:0 0 20px}
h1{font-size:26px;line-height:1.2;font-weight:600;letter-spacing:-.02em;margin:0}
h2{font-size:17px;font-weight:600;margin:32px 0 10px;letter-spacing:-.01em}
.sub{margin:4px 0 0;color:var(--muted)}
.row{display:flex;gap:8px;flex-wrap:wrap;align-items:center}
.stack{display:grid;gap:12px}

/* yüzeyler */
.panel{background:var(--surface);border:1px solid var(--line);border-radius:12px}
.panel-pad{padding:16px 18px}
.panel+.panel{margin-top:12px}
.empty{background:var(--surface);border:1px dashed var(--line);border-radius:12px;padding:28px 22px;text-align:left}
.empty-title{font-weight:600;font-size:16px;margin:0 0 4px}
.empty .row{margin-top:14px}

/* butonlar ve form */
.btn{display:inline-flex;align-items:center;gap:7px;font:inherit;font-weight:500;font-size:14px;line-height:1;padding:9px 13px;border-radius:7px;border:1px solid var(--line);background:var(--surface);color:var(--text);cursor:pointer;text-decoration:none;white-space:nowrap}
.btn:hover{border-color:var(--muted)}
.btn.primary{background:var(--blue);border-color:var(--blue);color:var(--on-accent)}
.btn.primary:hover{filter:brightness(1.07)}
.btn.success{background:var(--green);border-color:var(--green);color:var(--on-accent)}
.btn.danger{color:var(--red);border-color:color-mix(in srgb,var(--red) 35%,var(--line))}
.btn.danger:hover{background:var(--red-soft)}
.btn.ghost{border-color:transparent;background:transparent;color:var(--muted)}
.btn.ghost:hover{color:var(--text);background:var(--sunk)}
.btn.sm{padding:6px 9px;font-size:13px}
.btn.done{color:var(--green)}
form.inline{display:inline}
input[type=text],input[type=password],input[type=number],input[type=search],select,textarea{font:inherit;font-size:14.5px;color:var(--text);background:var(--surface);border:1px solid var(--line);border-radius:7px;padding:8px 10px;max-width:100%}
input:focus,select:focus,textarea:focus{outline:2px solid color-mix(in srgb,var(--blue) 45%,transparent);outline-offset:0;border-color:var(--blue)}
textarea{width:100%;font-size:13.5px;line-height:1.5}
label{display:grid;gap:5px;font-weight:500;font-size:14px}
.check{display:inline-flex;align-items:center;gap:8px;font-weight:400}
input[type=checkbox]{width:16px;height:16px;accent-color:var(--blue)}
.field-row{display:flex;gap:8px;flex-wrap:wrap;align-items:end}
.grow{flex:1 1 220px}
.grow input,.grow select{width:100%}
.nowrap{flex-wrap:nowrap}.row.nowrap{gap:8px}
.hint{font-weight:400;color:var(--muted)}
.lead{margin:-6px 0 14px}.note{flex:1 1 260px;padding-bottom:8px}
h2.first{margin-top:0}.gap-top{margin-top:14px}
.steps{margin:0;padding-left:18px}.narrow{width:90px}
td .src+.src{margin-top:2px}.edit-table .src{margin-top:4px}

/* rozetler */
.badge{display:inline-block;font-size:12.5px;font-weight:500;padding:2px 9px;border-radius:999px;background:var(--sunk);color:var(--muted);white-space:nowrap}
.s-new,.s-awaiting,.s-editing{background:var(--blue-soft);color:var(--blue)}
.s-approved{background:var(--amber-soft);color:var(--amber)}
.s-published{background:var(--green-soft);color:var(--green)}
.s-failed{background:var(--red-soft);color:var(--red)}
.dot{width:8px;height:8px;border-radius:50%;background:var(--muted);flex:none;display:inline-block}
.dot.ok{background:var(--green)}.dot.warn{background:var(--amber)}.dot.bad{background:var(--red)}

/* bildirim */
.flash{display:flex;gap:10px;align-items:flex-start;padding:11px 14px;border-radius:9px;margin:0 0 14px;background:var(--green-soft);color:var(--text);border:1px solid color-mix(in srgb,var(--green) 30%,transparent)}
.flash .ic{color:var(--green);margin-top:2px}
.flash.bad{background:var(--red-soft);border-color:color-mix(in srgb,var(--red) 30%,transparent)}.flash.bad .ic{color:var(--red)}

/* durum şeridi */
.strip{display:grid;grid-template-columns:repeat(auto-fit,minmax(190px,1fr));background:var(--surface);border:1px solid var(--line);border-radius:12px;margin:0 0 24px}
.strip>div{padding:12px 16px;border-right:1px solid var(--line-2)}
.strip>div:last-child{border-right:0}
.strip .k{font-size:13px;color:var(--muted);display:flex;align-items:center;gap:7px}
.strip .v{font-weight:600;font-size:15px;margin-top:2px}

/* kuyruk: çeviri satırları */
.queue{display:grid;gap:10px}
.qcard{background:var(--surface);border:1px solid var(--line);border-left:4px solid var(--blue);border-radius:10px;padding:14px 16px}
.qcard.st-failed{border-left-color:var(--red)}.qcard.st-approved{border-left-color:var(--amber)}.qcard.st-published{border-left-color:var(--green)}.qcard.st-rejected{border-left-color:var(--line)}
.qhead{display:flex;align-items:center;gap:10px;flex-wrap:wrap;margin:0 0 8px}
.qhead .id{font-weight:600;text-decoration:none;color:var(--text)}
.qhead .meta{color:var(--muted);font-size:13.5px}
.qhead .row{margin-left:auto}
.pair{display:grid;grid-template-columns:72px minmax(0,1fr);gap:2px 12px;padding:7px 0;border-top:1px solid var(--line-2)}
.pair:first-of-type{border-top:0}
.pair .sid{color:var(--muted);font-size:13.5px;padding-top:2px}
.pair .src{color:var(--muted);font-size:13.5px}
.pair .az{font-weight:600;font-size:15.5px}
.pair .warn{color:var(--amber);font-size:13px}
.more{color:var(--muted);font-size:13.5px;padding-top:6px}

/* tablolar */
.table-wrap{overflow-x:auto;background:var(--surface);border:1px solid var(--line);border-radius:12px}
table{width:100%;border-collapse:collapse;font-size:14.5px}
th,td{padding:10px 14px;text-align:left;vertical-align:top;border-bottom:1px solid var(--line-2)}
tr:last-child td{border-bottom:0}
th{font-size:13px;font-weight:500;color:var(--muted);background:var(--sunk);white-space:nowrap}
td.num,th.num{text-align:right}
td .src{color:var(--muted);font-size:13px}
td.tight{width:1%;white-space:nowrap}
td.tight .row{flex-wrap:nowrap;gap:4px}
td .lvl-warn{color:var(--amber)}td .lvl-error{color:var(--red)}
tbody tr:hover td{background:color-mix(in srgb,var(--sunk) 60%,transparent)}
.edit-table input[type=text]{width:100%;font-weight:600}
.edit-table td{vertical-align:middle}

/* çubuklar */
.toolbar{display:flex;gap:8px;flex-wrap:wrap;align-items:end;margin:0 0 14px}
.toolbar .grow{flex:1 1 240px}
.bulk{display:flex;gap:8px;align-items:center;flex-wrap:wrap;padding:10px 14px;border-top:1px solid var(--line-2);background:var(--sunk);border-radius:0 0 12px 12px}
.actionbar{position:sticky;bottom:0;z-index:2;display:flex;gap:8px;flex-wrap:wrap;align-items:center;padding:12px 14px;margin:18px 0 0;background:color-mix(in srgb,var(--surface) 92%,transparent);backdrop-filter:blur(6px);border:1px solid var(--line);border-radius:12px}
.actionbar .spacer{flex:1}
.pager{display:flex;gap:6px;align-items:center;margin-top:14px;color:var(--muted);font-size:14px}
.chips{display:flex;gap:6px;flex-wrap:wrap}
.chip{display:inline-flex;align-items:center;gap:6px;padding:3px 10px;border-radius:999px;background:var(--sunk);border:1px solid var(--line-2);font-size:13px;color:var(--text);text-decoration:none}
.chip[aria-current="true"]{background:var(--caspian);border-color:var(--caspian);color:#fff}
.chip .n{color:var(--muted);font-size:12px}.chip[aria-current="true"] .n{color:#BFD0DC}
pre,code{font-family:inherit}
pre{white-space:pre-wrap;word-break:break-word;margin:0;font-size:14px}
.code{background:var(--sunk);border:1px solid var(--line-2);border-radius:8px;padding:10px 12px}
details>summary{cursor:pointer;color:var(--muted);font-weight:500}
details[open]>summary{margin-bottom:8px}
.split{display:grid;grid-template-columns:minmax(0,1fr) minmax(0,1fr);gap:16px;align-items:start}
.split .row{min-height:32px}
.lvl-error{color:var(--red);font-weight:500}.lvl-warn{color:var(--amber);font-weight:500}.lvl-info{color:var(--muted)}
.login-card{background:var(--surface);border:1px solid var(--line);border-radius:14px;overflow:hidden}
.login-card .flag{height:5px}
.login-card form,.login-card .body{padding:22px;display:grid;gap:14px}
.login-card h1{font-size:22px}
.login-card input{width:100%}

@media (max-width:860px){
  .has-side{display:block}
  .side{position:static;height:auto;padding:0}
  .brand{padding:12px 16px 6px;font-size:16px}.brand small{display:none}
  .nav{flex-direction:row;overflow-x:auto;padding:0 10px 10px;gap:4px}
  .nav a{padding:7px 11px;white-space:nowrap}
  .side-foot{flex-direction:row;flex-wrap:wrap;padding:0 10px 10px}
  .side-status{display:none}
  .side-link{width:auto;padding:6px 10px}
  .side-foot{gap:4px}
  .main{padding:18px 16px 56px}
  .split{grid-template-columns:1fr}
  .strip>div{border-right:0;border-bottom:1px solid var(--line-2)}
  .pair{grid-template-columns:1fr}
  .edit-table thead{display:none}
  .edit-table table,.edit-table tbody,.edit-table tr,.edit-table td{display:block;width:100%}
  .edit-table tr{padding:10px 12px;border-bottom:1px solid var(--line-2)}
  .edit-table td{padding:2px 0;border:0;text-align:left}
  .edit-table td.num::before{content:"Fiyat ";color:var(--muted)}
  .actionbar{position:static}
  h1{font-size:22px}
}
@media (prefers-reduced-motion:reduce){*{transition:none!important;animation:none!important}}
CSS;
}
