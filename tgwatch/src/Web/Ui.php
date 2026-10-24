<?php
declare(strict_types=1);

namespace TgWatch\Web;

use TgWatch\Flow;

/**
 * Panelin görsel katmanı: uygulama iskeleti (cam kenar menü + cam araç çubuğu + mobil sekme çubuğu),
 * bileşenler, ikonlar, CSS ve küçük JavaScript (komut paleti, ipucu, kopyala, onay, kenar menüyü gizle).
 * Görünüm sistemin açık/koyu ayarını izler; uygulamaya özel tema düğmesi yoktur (Apple HIG › Dark Mode).
 */
final class Ui
{
    /** Gezinme: grup => [sayfa => [ad, ikon]] (kenar menü en fazla iki seviye: HIG › Sidebars) */
    public const NAV = [
        'Masa' => ['dash' => ['Genel bakış', 'gauge'], 'queue' => ['Onay kuyruğu', 'queue'], 'jobs' => ['İşler', 'jobs'], 'changes' => ['Servis değişiklikleri', 'change']],
        'Kaynaklar' => ['channels' => ['Kanallar', 'channels']],
        'Çeviri' => ['dict' => ['Sözlük', 'dict'], 'bank' => ['Çeviri bankası', 'bank'], 'ai' => ['Çeviri ayarları', 'sliders']],
        'Sistem' => ['alerts' => ['Uyarılar ve güvenlik', 'bell'], 'logs' => ['Kayıtlar', 'logs'], 'health' => ['Sağlık', 'health']],
    ];
    /** Mobil sekme çubuğu: en sık kullanılan dört bölüm + menü */
    private const TABS = ['dash' => ['Genel', 'gauge'], 'queue' => ['Kuyruk', 'queue'], 'jobs' => ['İşler', 'jobs'], 'channels' => ['Kanallar', 'channels']];

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
     * @param string $variant primary | quiet | danger | ghost | success (+ " sm")
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
        return '<button type="button" class="btn ghost sm js-only" data-copy="' . self::e($targetId) . '">' . self::icon('copy', 16) . '<span>' . self::e($label) . '</span></button>';
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
        return '<time datetime="' . date('c', $ts) . '" title="' . date('d.m.Y H:i:s', $ts) . '">' . $txt . '</time>';
    }

    public static function empty(string $title, string $text, string $action = ''): string
    {
        return '<div class="empty"><div class="empty-ic">' . self::icon('check', 22) . '</div><p class="empty-title">' . self::e($title) . '</p><p class="muted">' . $text . '</p>'
            . ($action !== '' ? '<div class="row center">' . $action . '</div>' : '') . '</div>';
    }

    public static function via(?string $via): string
    {
        if ($via === null || $via === '') {
            return '';
        }
        return '<span class="via via-' . self::e($via) . '">' . self::e(\TgWatch\Engine::VIA_LABELS[$via] ?? $via) . '</span>';
    }

    public static function dot(string $state, string $label = ''): string
    {
        // Renk tek başına anlam taşımasın: ekran okuyucu için durum metni (HIG › Accessibility).
        $sr = $label !== '' ? $label : ['ok' => 'iyi', 'warn' => 'dikkat', 'bad' => 'sorun'][$state] ?? '';
        return '<span class="dot ' . self::e($state) . '" aria-hidden="true"></span>' . ($sr !== '' ? '<span class="sr">' . self::e($sr) . '</span>' : '');
    }

    /** Çizgi ikonlar (24×24, 1.8 stroke) — tek görsel dil. */
    public static function icon(string $name, int $size = 18): string
    {
        $p = [
            'gauge' => '<path d="M4 15a8 8 0 1 1 16 0"/><path d="M12 15l3.5-4.5"/><circle cx="12" cy="15" r="1.2"/><path d="M4 19h16"/>',
            'queue' => '<path d="M4 6h16M4 12h10M4 18h7"/><circle cx="18" cy="16" r="3"/>',
            'jobs' => '<rect x="4" y="4" width="16" height="16" rx="3"/><path d="M8 9h8M8 13h8M8 17h5"/>',
            'channels' => '<path d="M4 11a8 8 0 0 1 16 0"/><path d="M7.5 11a4.5 4.5 0 0 1 9 0"/><circle cx="12" cy="13" r="1.5"/><path d="M12 15v5"/>',
            'dict' => '<path d="M5 4h11a3 3 0 0 1 3 3v13H8a3 3 0 0 1-3-3z"/><path d="M5 17a3 3 0 0 1 3-3h11"/><path d="M9 8h6"/>',
            'bank' => '<path d="M4 7.5L12 4l8 3.5"/><path d="M5 10h14M6.5 10v7M10 10v7M14 10v7M17.5 10v7M4 20h16"/>',
            'sliders' => '<path d="M5 6h9M18 6h1M5 12h3M12 12h7M5 18h11M20 18h-1"/><circle cx="16" cy="6" r="2"/><circle cx="10" cy="12" r="2"/><circle cx="18" cy="18" r="2"/>',
            'logs' => '<path d="M3 12h4l2-6 4 12 2-6h6"/>',
            'health' => '<path d="M12 20s-7-4.5-7-10a4 4 0 0 1 7-2.6A4 4 0 0 1 19 10c0 5.5-7 10-7 10z"/>',
            'logout' => '<path d="M15 4h3a2 2 0 0 1 2 2v12a2 2 0 0 1-2 2h-3"/><path d="M10 16l-4-4 4-4M6 12h10"/>',
            'check' => '<path d="M5 12.5l4.5 4.5L19 7.5"/>',
            'x' => '<path d="M6 6l12 12M18 6L6 18"/>',
            'refresh' => '<path d="M20 11a8 8 0 0 0-14.5-4.5L4 8"/><path d="M4 4v4h4"/><path d="M4 13a8 8 0 0 0 14.5 4.5L20 16"/><path d="M20 20v-4h-4"/>',
            'edit' => '<path d="M4 20h4L19 9l-4-4L4 16z"/><path d="M13.5 6.5l4 4"/>',
            'copy' => '<rect x="8" y="8" width="12" height="12" rx="2.5"/><path d="M16 8V6a2 2 0 0 0-2-2H6a2 2 0 0 0-2 2v8a2 2 0 0 0 2 2h2"/>',
            'download' => '<path d="M12 4v11M7 10l5 5 5-5"/><path d="M5 20h14"/>',
            'play' => '<path d="M8 5.5v13l10.5-6.5z"/>',
            'plus' => '<path d="M12 5v14M5 12h14"/>',
            'search' => '<circle cx="11" cy="11" r="6.5"/><path d="M16 16l4 4"/>',
            'trash' => '<path d="M5 7h14M10 7V5h4v2M7 7l1 13h8l1-13"/>',
            'globe' => '<circle cx="12" cy="12" r="8"/><path d="M4 12h16M12 4c2.5 2.5 2.5 13.5 0 16M12 4c-2.5 2.5-2.5 13.5 0 16"/>',
            'send' => '<path d="M4 12l16-8-6 16-2.5-6.5z"/><path d="M11.5 13.5L20 4"/>',
            'alert' => '<path d="M12 4l9 16H3z"/><path d="M12 10v4M12 17.5v.5"/>',
            'link' => '<path d="M10 14a4 4 0 0 0 5.7 0l3-3a4 4 0 0 0-5.7-5.7l-1 1"/><path d="M14 10a4 4 0 0 0-5.7 0l-3 3a4 4 0 0 0 5.7 5.7l1-1"/>',
            'pause' => '<path d="M9 5v14M15 5v14"/>',
            'spark' => '<path d="M12 3l1.8 5.2L19 10l-5.2 1.8L12 17l-1.8-5.2L5 10l5.2-1.8z"/>',
            'sidebar' => '<rect x="3.5" y="5" width="17" height="14" rx="3"/><path d="M9.5 5v14"/>',
            'menu' => '<path d="M4 7h16M4 12h16M4 17h16"/>',
            'chevron' => '<path d="M9 6l6 6-6 6"/>',
            'clock' => '<circle cx="12" cy="12" r="8"/><path d="M12 8v4l3 2"/>',
            'arrow' => '<path d="M5 12h14M13 6l6 6-6 6"/>',
            'change' => '<path d="M7 4v14M3.5 14.5L7 18l3.5-3.5"/><path d="M17 20V6M13.5 9.5L17 6l3.5 3.5"/>',
            'bell' => '<path d="M6 17V11a6 6 0 0 1 12 0v6l1.5 2h-15z"/><path d="M10 21a2 2 0 0 0 4 0"/>',
        ][$name] ?? '';
        return '<svg class="ic" width="' . $size . '" height="' . $size . '" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true" focusable="false">' . $p . '</svg>';
    }

    /**
     * Sayfa iskeleti.
     * @param array{nav?:bool, active?:string, actions?:string, pending?:int, cron?:array{state:string,text:string}, flash?:list<array{0:string,1:string}>, sub?:string, hero?:bool} $o
     */
    public function page(string $title, string $body, array $o = []): string
    {
        $nav = $o['nav'] ?? true;
        $active = $o['active'] ?? '';
        $flash = '';
        foreach ($o['flash'] ?? [] as [$t, $m]) {
            $flash .= '<div class="flash ' . self::e($t) . '" role="status">' . self::icon($t === 'bad' ? 'alert' : 'check') . '<span>' . self::e($m) . '</span></div>';
        }
        $head = '<header class="page-head' . (!empty($o['hero']) ? ' with-aurora' : '') . '"><div class="page-title"><h1>' . self::e($title) . '</h1>'
            . (!empty($o['sub']) ? '<p class="sub">' . $o['sub'] . '</p>' : '') . '</div>'
            . (!empty($o['actions']) ? '<div class="row page-actions">' . $o['actions'] . '</div>' : '') . '</header>';

        $doc = '<!doctype html><html lang="tr"><head><meta charset="utf-8">'
            . '<meta name="viewport" content="width=device-width,initial-scale=1,viewport-fit=cover"><meta name="robots" content="noindex,nofollow">'
            . '<meta name="color-scheme" content="light dark"><meta name="theme-color" media="(prefers-color-scheme: light)" content="#f5f5f7"><meta name="theme-color" media="(prefers-color-scheme: dark)" content="#000000">'
            . '<title>' . self::e($title) . ' · tgwatch</title>'
            . '<link rel="icon" href="data:image/svg+xml,' . rawurlencode('<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 32 32"><rect width="32" height="32" rx="8" fill="#0a63ce"/><circle cx="10" cy="16" r="3" fill="#fff"/><circle cx="16" cy="16" r="3" fill="#fff" opacity=".75"/><circle cx="22" cy="16" r="3" fill="#fff" opacity=".5"/></svg>') . '">'
            . '<style nonce="' . $this->nonce . '">' . self::CSS . '</style></head>';

        if (!$nav) {
            return $doc . '<body class="bare"><main class="bare-main" id="main">' . $flash . $body . '</main></body></html>';
        }

        $links = '';
        $palette = '';
        foreach (self::NAV as $group => $items) {
            $links .= '<div class="nav-group"><p class="nav-label">' . self::e($group) . '</p>';
            foreach ($items as $k => [$label, $ic]) {
                $cnt = (int)($o['counts'][$k] ?? 0);
                $count = $cnt > 0 ? '<span class="count" aria-label="' . $cnt . ' bekleyen">' . $cnt . '</span>' : '';
                $links .= '<a href="?p=' . $k . '"' . ($active === $k ? ' aria-current="page"' : '') . '>' . self::icon($ic) . '<span>' . $label . '</span>' . $count . '</a>';
                $palette .= '<li><a href="?p=' . $k . '" data-cmd="' . self::e(mb_strtolower($label . ' ' . $group)) . '">' . self::icon($ic, 16) . '<span>' . $label . '</span><small>' . self::e($group) . '</small></a></li>';
            }
            $links .= '</div>';
        }
        $cron = $o['cron'] ?? null;
        $side = '<aside class="side glass" id="menu" aria-label="Ana menü">'
            . '<div class="brand"><span class="mark" aria-hidden="true"><i></i><i></i><i></i></span><span>tgwatch<small>servis çeviri masası</small></span>'
            . '<a class="close-menu" href="#" aria-label="Menüyü kapat">' . self::icon('x') . '</a></div>'
            . '<nav class="nav">' . $links . '</nav>'
            . '<div class="side-foot">' . ($cron ? '<a class="side-status" href="?p=health">' . self::dot($cron['state']) . '<span>Kanal okuma ' . $cron['text'] . '</span></a>' : '')
            . '<form method="post" action="?p=logout">' . $this->csrfField() . '<button class="side-link">' . self::icon('logout', 16) . '<span>Çıkış yap</span></button></form></div></aside>';

        $tabs = '';
        foreach (self::TABS as $k => [$label, $ic]) {
            $badge = $k === 'queue' && !empty($o['pending']) ? '<span class="tab-badge">' . (int)$o['pending'] . '</span>' : '';
            $tabs .= '<a href="?p=' . $k . '"' . ($active === $k ? ' aria-current="page"' : '') . '>' . self::icon($ic, 22) . $badge . '<span>' . $label . '</span></a>';
        }
        $tabs .= '<a href="#menu" data-menu>' . self::icon('menu', 22) . '<span>Menü</span></a>';

        $toolbar = '<div class="toolbar glass" role="banner">'
            . '<button type="button" class="icon-btn js-only" data-toggle-side aria-label="Kenar menüyü gizle veya göster" aria-controls="menu">' . self::icon('sidebar') . '</button>'
            . '<span class="tb-title">' . self::e($title) . '</span>'
            . '<form method="get" class="search" role="search"><input type="hidden" name="p" value="jobs">' . self::icon('search', 16)
            . '<input type="search" name="q" placeholder="Servis ID, iş ya da kelime ara" aria-label="İşlerde ara" autocomplete="off"><kbd class="js-only" data-open-cmd title="Komut paleti">⌘K</kbd></form>'
            . '</div>';

        $cmd = '<dialog class="cmd glass" id="cmd" aria-label="Komut paleti"><form method="dialog" class="cmd-in">' . self::icon('search')
            . '<input type="text" id="cmd-q" placeholder="Sayfaya git ya da servis ID / iş ara…" autocomplete="off" aria-label="Komut ara"><kbd>esc</kbd></form>'
            . '<ul class="cmd-list" id="cmd-list">' . $palette . '<li data-search><a href="?p=jobs&amp;q=" id="cmd-search">' . self::icon('search', 16) . '<span>İşlerde ara: <b id="cmd-term"></b></span><small>Arama</small></a></li></ul></dialog>';

        return $doc . '<body class="app"><a class="skip" href="#main">İçeriğe geç</a>' . $side
            . '<div class="shell">' . $toolbar . '<main class="main" id="main">' . $head . $flash . $body . '</main></div>'
            . '<nav class="tabbar glass" aria-label="Sekmeler">' . $tabs . '</nav>'
            . $cmd . '<div class="tip" id="tip" role="tooltip" hidden></div>'
            . '<script nonce="' . $this->nonce . '">' . self::JS . '</script></body></html>';
    }

    private const JS = <<<'JS'
(function () {
  var d = document, html = d.documentElement;
  html.classList.add('js');
  try { if (localStorage.getItem('tgw-side') === 'hidden') d.body.classList.add('side-hidden'); } catch (e) {}

  // Kopyala
  d.addEventListener('click', function (e) {
    var b = e.target.closest('[data-copy]');
    if (b) {
      var el = d.getElementById(b.getAttribute('data-copy'));
      if (el && navigator.clipboard) navigator.clipboard.writeText(el.value !== undefined ? el.value : el.innerText).then(function () {
        b.classList.add('done'); var s = b.querySelector('span'), t = s ? s.textContent : '';
        if (s) s.textContent = 'Kopyalandı'; setTimeout(function () { b.classList.remove('done'); if (s) s.textContent = t; }, 1400);
      });
    }
    var t = e.target.closest('[data-toggle-side]');
    if (t) {
      var hidden = d.body.classList.toggle('side-hidden');
      try { localStorage.setItem('tgw-side', hidden ? 'hidden' : 'shown'); } catch (e2) {}
    }
    if (e.target.closest('[data-open-cmd]')) { e.preventDefault(); openCmd(); }
  });

  // Onay sorusu (geri alınamayan işlemler)
  d.addEventListener('submit', function (e) {
    var f = e.target, s = e.submitter, msg = (s && s.getAttribute('data-confirm')) || f.getAttribute('data-confirm');
    if (msg && !window.confirm(msg)) e.preventDefault();
  });

  // Tümünü seç
  d.addEventListener('change', function (e) {
    if (!e.target.matches('[data-checkall]')) return;
    d.querySelectorAll('input[name="ids[]"]').forEach(function (x) { x.checked = e.target.checked; });
  });

  // İpucu: grafik işaretleri ve [data-tip] öğeleri (fare ve klavye odağı)
  var tip = d.getElementById('tip');
  function showTip(el) {
    if (!tip) return;
    var v = el.getAttribute('data-tip-value') || '', l = el.getAttribute('data-tip') || '';
    tip.textContent = '';
    if (v) { var b = d.createElement('strong'); b.textContent = v; tip.appendChild(b); }
    var s = d.createElement('span'); s.textContent = l; tip.appendChild(s);
    tip.hidden = false;
    var r = el.getBoundingClientRect(), tr = tip.getBoundingClientRect();
    var x = Math.min(Math.max(8, r.left + r.width / 2 - tr.width / 2), window.innerWidth - tr.width - 8);
    var y = r.top - tr.height - 10; if (y < 8) y = r.bottom + 10;
    tip.style.left = x + 'px'; tip.style.top = y + 'px';
  }
  function hideTip() { if (tip) tip.hidden = true; }
  ['pointerover', 'focusin'].forEach(function (ev) { d.addEventListener(ev, function (e) { var el = e.target.closest && e.target.closest('[data-tip]'); if (el) showTip(el); }); });
  ['pointerout', 'focusout'].forEach(function (ev) { d.addEventListener(ev, function (e) { if (e.target.closest && e.target.closest('[data-tip]')) hideTip(); }); });
  window.addEventListener('scroll', hideTip, { passive: true });

  // Komut paleti: ⌘K / Ctrl+K, "/" arama
  var cmd = d.getElementById('cmd'), q = d.getElementById('cmd-q'), list = d.getElementById('cmd-list');
  function items() { return Array.prototype.slice.call(list.querySelectorAll('li')).filter(function (li) { return !li.hidden; }); }
  function select(i) { var it = items(); it.forEach(function (li) { li.classList.remove('sel'); }); if (it[i]) { it[i].classList.add('sel'); it[i].scrollIntoView({ block: 'nearest' }); } return it[i]; }
  var sel = 0;
  function filter() {
    var v = q.value.trim().toLowerCase();
    list.querySelectorAll('li').forEach(function (li) {
      if (li.hasAttribute('data-search')) { li.hidden = v === ''; return; }
      var a = li.querySelector('a'); li.hidden = v !== '' && a.getAttribute('data-cmd').indexOf(v) === -1;
    });
    d.getElementById('cmd-term').textContent = q.value.trim();
    d.getElementById('cmd-search').href = '?p=jobs&q=' + encodeURIComponent(q.value.trim());
    sel = 0; select(0);
  }
  function openCmd() { if (!cmd || !cmd.showModal) return; q.value = ''; filter(); cmd.showModal(); q.focus(); }
  if (q) {
    q.addEventListener('input', filter);
    q.addEventListener('keydown', function (e) {
      var it = items();
      if (e.key === 'ArrowDown') { e.preventDefault(); sel = Math.min(sel + 1, it.length - 1); select(sel); }
      if (e.key === 'ArrowUp') { e.preventDefault(); sel = Math.max(sel - 1, 0); select(sel); }
      if (e.key === 'Enter') { e.preventDefault(); var li = select(sel); if (li) location.href = li.querySelector('a').href; }
    });
    cmd.addEventListener('click', function (e) { if (e.target === cmd) cmd.close(); });
  }
  d.addEventListener('keydown', function (e) {
    if ((e.metaKey || e.ctrlKey) && e.key.toLowerCase() === 'k') { e.preventDefault(); openCmd(); }
    var tag = (e.target.tagName || '').toLowerCase();
    if (e.key === '/' && tag !== 'input' && tag !== 'textarea' && tag !== 'select') {
      var s = d.querySelector('.search input[type=search]'); if (s) { e.preventDefault(); s.focus(); }
    }
  });
  if (!/Mac|iPhone|iPad/.test(navigator.platform || navigator.userAgent)) d.querySelectorAll('kbd[data-open-cmd]').forEach(function (k) { k.textContent = 'Ctrl K'; });
})();
JS;

    private const CSS = <<<'CSS'
/* ===== Tokenlar (açık) ===== */
:root{
  --bg:#f5f5f7;--surface:#ffffff;--surface-2:#f5f5f7;--fill:#e8e8ed;--fill-2:#f0f0f3;
  --sep:#d2d2d7;--sep-2:#e5e5ea;--text:#1d1d1f;--text-2:#6e6e73;--text-3:#8e8e93;
  --accent:#0a63ce;--accent-text:#0a63ce;--on-accent:#ffffff;--accent-soft:#e6f0fc;
  --good:#1b7f3b;--good-soft:#e5f4ea;--warn:#8f5a00;--warn-soft:#fbf1df;--bad:#c4242b;--bad-soft:#fdebec;
  --s1:#2a78d6;--s2:#eb6834;--s3:#1baf7a;--s4:#eda100;--s5:#e87ba4;
  --glass:rgba(250,250,252,.72);--glass-line:rgba(255,255,255,.7);--glass-edge:rgba(0,0,0,.08);
  --shadow:0 1px 2px rgba(0,0,0,.04),0 8px 24px rgba(0,0,0,.05);--shadow-pop:0 24px 64px rgba(0,0,0,.18),0 2px 8px rgba(0,0,0,.08);
  --r-xl:20px;--r-l:16px;--r-m:12px;--r-s:9px;
  --font:-apple-system,BlinkMacSystemFont,"SF Pro Text","SF Pro Display","Segoe UI Variable Text","Segoe UI",system-ui,Roboto,"Helvetica Neue",Arial,sans-serif;
  --mono:ui-monospace,"SF Mono",SFMono-Regular,Menlo,Consolas,monospace;
  color-scheme:light;
}
/* ===== Tokenlar (koyu) — yalnızca sistem ayarı ===== */
@media (prefers-color-scheme:dark){:root{
  --bg:#000000;--surface:#1c1c1e;--surface-2:#2c2c2e;--fill:#3a3a3c;--fill-2:#2c2c2e;
  --sep:#38383a;--sep-2:#2c2c2e;--text:#f5f5f7;--text-2:#a1a1a6;--text-3:#8e8e93;
  --accent:#0a6ce0;--accent-text:#5aa9ff;--on-accent:#ffffff;--accent-soft:#0d2a4d;
  --good:#34c77b;--good-soft:#0f2e1e;--warn:#f2b24c;--warn-soft:#33260f;--bad:#ff6b6b;--bad-soft:#3a1517;
  --s1:#3987e5;--s2:#d95926;--s3:#199e70;--s4:#c98500;--s5:#d55181;
  --glass:rgba(30,30,32,.68);--glass-line:rgba(255,255,255,.08);--glass-edge:rgba(255,255,255,.06);
  --shadow:0 1px 0 rgba(255,255,255,.04) inset;--shadow-pop:0 24px 64px rgba(0,0,0,.6);
  color-scheme:dark}}
@media (prefers-contrast:more){:root{--sep:#8e8e93;--sep-2:#aeaeb2;--text-2:#3a3a3c;--glass:var(--surface)}}
@media (prefers-contrast:more) and (prefers-color-scheme:dark){:root{--text-2:#d1d1d6}}

/* ===== Temel ===== */
*,*::before,*::after{box-sizing:border-box}
html{-webkit-text-size-adjust:100%;text-size-adjust:100%}
body{margin:0;background:var(--bg);color:var(--text);font:400 15px/1.47 var(--font);letter-spacing:-.006em;-webkit-font-smoothing:antialiased}
a{color:var(--accent-text);text-decoration:none}a:hover{text-decoration:underline;text-underline-offset:2px}
:focus-visible{outline:3px solid color-mix(in srgb,var(--accent) 55%,transparent);outline-offset:2px;border-radius:8px}
.ic{flex:none;display:block}
.muted{color:var(--text-2)}
.sr{position:absolute;width:1px;height:1px;overflow:hidden;clip:rect(0 0 0 0);white-space:nowrap}
.js-only{display:none!important}.js .js-only{display:inline-flex!important}
.skip{position:absolute;left:-999px;top:8px;z-index:100;background:var(--surface);padding:8px 14px;border-radius:10px}.skip:focus{left:12px}
h1,h2,h3{margin:0;color:var(--text)}
h1{font-size:32px;line-height:1.12;font-weight:700;letter-spacing:-.022em}
h2{font-size:20px;line-height:1.25;font-weight:600;letter-spacing:-.016em;margin:36px 0 12px}
h2.first{margin-top:0}
h3{font-size:15px;font-weight:600;letter-spacing:-.01em}
pre,code{font-family:var(--mono);font-size:13px}
pre{white-space:pre-wrap;word-break:break-word;margin:0}
code{background:var(--fill-2);padding:1px 6px;border-radius:6px}
kbd{font:500 11.5px/1 var(--font);color:var(--text-2);background:var(--fill-2);border:1px solid var(--sep-2);border-radius:6px;padding:3px 6px}

/* ===== Cam (yalnızca işlevsel katman: kenar menü, araç çubuğu, sekme çubuğu, palet, işlem çubuğu) ===== */
.glass{background:var(--glass);-webkit-backdrop-filter:blur(28px) saturate(1.5);backdrop-filter:blur(28px) saturate(1.5);
  border:1px solid var(--glass-edge);box-shadow:inset 0 1px 0 var(--glass-line)}
@media (prefers-reduced-transparency:reduce){.glass{background:var(--surface);-webkit-backdrop-filter:none;backdrop-filter:none}}

/* ===== Uygulama iskeleti ===== */
.app{min-height:100vh}
.side{position:fixed;z-index:30;top:10px;bottom:10px;left:10px;width:248px;border-radius:var(--r-xl);display:flex;flex-direction:column;padding:14px 10px 12px;overflow:auto;transition:transform .28s cubic-bezier(.2,.8,.2,1),opacity .2s}
.shell{margin-left:268px;min-width:0;overflow-x:clip;transition:margin .28s cubic-bezier(.2,.8,.2,1)}
.side-hidden .side{transform:translateX(-120%);opacity:0;pointer-events:none}
.side-hidden .shell{margin-left:0}
.brand{display:flex;align-items:center;gap:11px;padding:4px 10px 16px;font-weight:700;font-size:17px;letter-spacing:-.02em}
.brand small{display:block;font-weight:400;font-size:12px;color:var(--text-2);letter-spacing:0}
.mark{width:30px;height:30px;border-radius:9px;background:linear-gradient(180deg,#1677e6,#0a52b0);display:flex;align-items:center;justify-content:center;gap:3px;box-shadow:inset 0 1px 0 rgba(255,255,255,.35);flex:none}
.mark i{width:5px;height:5px;border-radius:50%;background:#fff}.mark i:nth-child(2){opacity:.75}.mark i:nth-child(3){opacity:.5}
.close-menu{display:none;margin-left:auto;color:var(--text-2)}
.nav-group{margin:0 0 14px}
.nav-label{margin:0 0 4px;padding:0 10px;font-size:12px;font-weight:600;color:var(--text-3)}
.nav a{display:flex;align-items:center;gap:10px;min-height:34px;padding:6px 10px;border-radius:10px;color:var(--text);font-weight:500;font-size:14.5px;text-decoration:none}
.nav a .ic{color:var(--accent-text)}
.nav a:hover{background:color-mix(in srgb,var(--text) 6%,transparent)}
.nav a[aria-current="page"]{background:color-mix(in srgb,var(--accent) 14%,transparent);color:var(--text)}
.count{margin-left:auto;min-width:22px;text-align:center;background:var(--accent);color:var(--on-accent);font-size:12px;font-weight:600;padding:1px 7px;border-radius:999px}
.side-foot{margin-top:auto;padding-top:10px;border-top:1px solid var(--sep-2);display:grid;gap:2px}
.side-foot form{margin:0}
.side-status,.side-link{display:flex;align-items:center;gap:9px;padding:7px 10px;border-radius:10px;font-size:13px;color:var(--text-2);text-decoration:none;border:0;background:none;width:100%;font-family:inherit;cursor:pointer;text-align:left}
.side-status:hover,.side-link:hover{background:color-mix(in srgb,var(--text) 6%,transparent);text-decoration:none;color:var(--text)}

.toolbar{position:sticky;top:10px;z-index:20;margin:10px 10px 0;height:52px;border-radius:16px;display:flex;align-items:center;gap:10px;padding:0 10px}
.icon-btn{width:34px;height:34px;border-radius:10px;border:0;background:none;color:var(--text-2);display:inline-flex;align-items:center;justify-content:center;cursor:pointer}
.icon-btn:hover{background:color-mix(in srgb,var(--text) 7%,transparent);color:var(--text)}
.tb-title{font-weight:600;font-size:15px;opacity:0;transition:opacity .2s}
.search{margin-left:auto;display:flex;align-items:center;gap:8px;height:36px;width:min(420px,46vw);padding:0 8px 0 12px;border-radius:11px;background:color-mix(in srgb,var(--text) 6%,transparent);color:var(--text-2)}
.search input{flex:1;min-width:0;border:0!important;background:transparent!important;padding:0!important;outline:none!important;box-shadow:none!important;font:inherit;font-size:14.5px;color:var(--text);min-height:0}
.search kbd{cursor:pointer}
.main{max-width:1240px;margin:0 auto;padding:22px 28px 96px}
.bare .bare-main{max-width:440px;margin:9vh auto 0;padding:24px 16px}

/* Başlık alanı (büyük başlık). Marka rengi içerik katmanında: hafif bir aurora (HIG › Branding). */
.page-head{position:relative;display:flex;align-items:flex-end;justify-content:space-between;gap:16px;flex-wrap:wrap;margin:6px 0 22px;padding:10px 0 4px}
.page-head.with-aurora::before{content:"";position:absolute;z-index:-1;inset:-120px -40px -30px;pointer-events:none;
  background:radial-gradient(60% 70% at 18% 30%,color-mix(in srgb,#2a78d6 22%,transparent),transparent 70%),radial-gradient(40% 60% at 52% 10%,color-mix(in srgb,#1baf7a 14%,transparent),transparent 70%);filter:blur(10px)}
.sub{margin:6px 0 0;color:var(--text-2);font-size:15px}
.page-actions{gap:8px}

/* ===== Düzen yardımcıları ===== */
.row{display:flex;gap:8px;flex-wrap:wrap;align-items:center}.row.center{justify-content:center}.row.nowrap{flex-wrap:nowrap}
.stack{display:grid;gap:12px}
.grow{flex:1 1 220px}.grow input,.grow select{width:100%}
.split{display:grid;grid-template-columns:repeat(2,minmax(0,1fr));gap:16px;align-items:start}
.gap-top{margin-top:14px}.lead{margin:-8px 0 16px}.note{flex:1 1 260px;padding-bottom:9px}.narrow{width:96px}
.steps{margin:0;padding-left:20px}.steps li+li{margin-top:6px}
.hint{font-weight:400;color:var(--text-2)}

/* ===== Yüzeyler (içerik katmanı: opak) ===== */
.panel,.card{background:var(--surface);border-radius:var(--r-l);box-shadow:var(--shadow);border:1px solid var(--sep-2)}
@media (prefers-color-scheme:dark){.panel,.card{border-color:transparent}}
.panel-pad{padding:18px 20px}
.panel+.panel{margin-top:12px}
.code{background:var(--surface-2);border-radius:var(--r-m);padding:12px 14px}
.kv{display:grid;grid-template-columns:max-content 1fr;gap:8px 22px;margin:0}.kv dt{color:var(--text-2)}.kv dd{margin:0;font-weight:500}
details>summary{cursor:pointer;color:var(--accent-text);font-weight:500;list-style:none;display:flex;align-items:center;gap:6px}
details>summary::before{content:"";width:7px;height:7px;border-right:2px solid currentColor;border-bottom:2px solid currentColor;transform:rotate(-45deg);transition:transform .2s;margin-right:2px}
details[open]>summary::before{transform:rotate(45deg)}
details>summary::-webkit-details-marker{display:none}
details[open]>summary{margin-bottom:12px}

/* ===== Butonlar (kapsül) ===== */
.btn{display:inline-flex;align-items:center;justify-content:center;gap:7px;min-height:34px;padding:7px 15px;border-radius:999px;border:0;
  font:500 14.5px/1.1 var(--font);letter-spacing:-.01em;cursor:pointer;text-decoration:none!important;white-space:nowrap;
  background:color-mix(in srgb,var(--text) 7%,transparent);color:var(--text);transition:background .15s,transform .1s}
.btn:hover{background:color-mix(in srgb,var(--text) 11%,transparent)}
.btn:active{transform:scale(.98)}
.btn.primary{background:var(--accent);color:var(--on-accent)}.btn.primary:hover{background:color-mix(in srgb,var(--accent) 88%,#000)}
.btn.success{background:var(--good);color:#fff}
@media (prefers-color-scheme:dark){.btn.success{color:#06210f}}
.btn.danger{background:var(--bad-soft);color:var(--bad)}.btn.danger:hover{background:color-mix(in srgb,var(--bad) 18%,transparent)}
.btn.ghost{background:transparent;color:var(--accent-text)}.btn.ghost:hover{background:color-mix(in srgb,var(--accent) 10%,transparent)}
.btn.quiet{background:color-mix(in srgb,var(--text) 7%,transparent)}
.btn.sm{min-height:28px;padding:5px 11px;font-size:13px}
.btn.done{color:var(--good)}
form.inline{display:inline}

/* ===== Form ===== */
input[type=text],input[type=password],input[type=number],input[type=search],select,textarea{
  font:inherit;font-size:15px;color:var(--text);background:var(--surface);border:1px solid var(--sep);border-radius:var(--r-s);padding:8px 11px;max-width:100%;min-height:36px;transition:border-color .15s,box-shadow .15s}
select{appearance:none;-webkit-appearance:none;padding-right:32px;background-image:linear-gradient(45deg,transparent 50%,var(--text-2) 50%),linear-gradient(135deg,var(--text-2) 50%,transparent 50%);
  background-position:calc(100% - 15px) 52%,calc(100% - 10px) 52%;background-size:5px 5px;background-repeat:no-repeat}
input:focus,select:focus,textarea:focus{outline:none;border-color:var(--accent);box-shadow:0 0 0 4px color-mix(in srgb,var(--accent) 22%,transparent)}
textarea{width:100%;font-family:var(--mono);font-size:13px;line-height:1.55}
label{display:grid;gap:6px;font-weight:500;font-size:13.5px;color:var(--text-2)}
label>input,label>select,label>textarea{color:var(--text)}
.check{display:inline-flex!important;align-items:center;gap:9px;font-weight:400;font-size:15px;color:var(--text)}
input[type=checkbox],input[type=radio]{width:18px;height:18px;accent-color:var(--accent);margin:0}
.field-row{display:flex;gap:10px;flex-wrap:wrap;align-items:end}

/* ===== Rozet / durum ===== */
.badge{display:inline-flex;align-items:center;gap:5px;font-size:12.5px;font-weight:600;padding:3px 9px;border-radius:999px;background:var(--fill-2);color:var(--text-2);white-space:nowrap}
.s-new,.s-awaiting,.s-editing,.s-publishing{background:var(--accent-soft);color:var(--accent-text)}
.s-approved{background:var(--warn-soft);color:var(--warn)}
.s-published{background:var(--good-soft);color:var(--good)}
.s-failed{background:var(--bad-soft);color:var(--bad)}
.dot{width:8px;height:8px;border-radius:50%;background:var(--text-3);flex:none;display:inline-block}
.dot.ok{background:var(--good)}.dot.warn{background:#e3a008}.dot.bad{background:var(--bad)}
.via{display:inline-block;font-size:11.5px;font-weight:600;padding:1px 8px;border-radius:999px;background:var(--fill-2);color:var(--text-2);vertical-align:2px;margin-left:6px}
.via-bank{background:var(--good-soft);color:var(--good)}.via-claude{background:var(--accent-soft);color:var(--accent-text)}.via-manual{background:var(--warn-soft);color:var(--warn)}
.lvl-error{color:var(--bad);font-weight:500}.lvl-warn{color:var(--warn);font-weight:500}.lvl-info{color:var(--text-2)}
td .lvl-warn{color:var(--warn)}td .lvl-error{color:var(--bad)}

/* ===== Bildirim ===== */
.flash{display:flex;gap:10px;align-items:flex-start;padding:12px 16px;border-radius:var(--r-m);margin:0 0 16px;background:var(--good-soft);color:var(--text);font-weight:500}
.flash .ic{color:var(--good);margin-top:1px}
.flash.bad{background:var(--bad-soft)}.flash.bad .ic{color:var(--bad)}

/* ===== Boş durum ===== */
.empty{background:var(--surface);border-radius:var(--r-l);padding:40px 24px;text-align:center;box-shadow:var(--shadow);border:1px solid var(--sep-2)}
.empty-ic{width:46px;height:46px;border-radius:50%;margin:0 auto 12px;display:flex;align-items:center;justify-content:center;background:var(--good-soft);color:var(--good)}
.empty-title{font-weight:600;font-size:17px;margin:0 0 4px}
.empty .muted{max-width:52ch;margin:0 auto}
.empty .row{margin-top:16px}

/* ===== Tablo: gruplanmış liste ===== */
.table-wrap{overflow-x:auto;background:var(--surface);border-radius:var(--r-l);box-shadow:var(--shadow);border:1px solid var(--sep-2)}
@media (prefers-color-scheme:dark){.table-wrap,.empty{border-color:transparent}}
table{width:100%;border-collapse:collapse;font-size:14.5px}
th,td{padding:11px 16px;text-align:left;vertical-align:top}
td{border-top:1px solid var(--sep-2)}
tbody tr:first-child td{border-top:0}
thead+tbody tr:first-child td{border-top:1px solid var(--sep-2)}
th{font-size:12.5px;font-weight:600;color:var(--text-2);white-space:nowrap;padding-top:12px;padding-bottom:8px}
td.num,th.num{text-align:right;font-variant-numeric:tabular-nums}
td .src{color:var(--text-2);font-size:13px}
td.tight{width:1%;white-space:nowrap}td.tight .row{flex-wrap:nowrap;gap:4px}
tbody tr:hover td{background:color-mix(in srgb,var(--text) 2.5%,transparent)}
.edit-table input[type=text]{width:100%;font-weight:600}
.edit-table td{vertical-align:middle}.edit-table .src{margin-top:5px}

/* ===== Araç satırı, segment, sayfalama ===== */
form.toolbar{display:flex;gap:10px;flex-wrap:wrap;align-items:end;margin:0 0 14px;position:static;height:auto;background:none;border:0;padding:0;box-shadow:none;border-radius:0}
.chips{display:inline-flex;gap:2px;flex-wrap:wrap;padding:3px;border-radius:12px;background:color-mix(in srgb,var(--text) 6%,transparent)}
.chip{display:inline-flex;align-items:center;gap:6px;padding:5px 12px;border-radius:9px;font-size:13.5px;font-weight:500;color:var(--text);text-decoration:none!important}
.chip:hover{background:color-mix(in srgb,var(--text) 6%,transparent)}
.chip[aria-current="true"]{background:var(--surface);box-shadow:0 1px 3px rgba(0,0,0,.12)}
@media (prefers-color-scheme:dark){.chip[aria-current="true"]{background:var(--fill)}}
.chip .n{color:var(--text-2);font-size:12px;font-variant-numeric:tabular-nums}
.bulk{display:flex;gap:8px;align-items:center;flex-wrap:wrap;padding:12px 16px;border-top:1px solid var(--sep-2)}
.pager{display:flex;gap:8px;align-items:center;margin-top:16px;color:var(--text-2);font-size:14px}

/* İşlem çubuğu: içeriğin üzerinde yüzen kontrol katmanı → cam uygun */
.actionbar{position:sticky;bottom:14px;z-index:5;display:flex;gap:8px;flex-wrap:wrap;align-items:center;padding:10px 12px;margin:18px 0 0;border-radius:18px;
  background:var(--glass);-webkit-backdrop-filter:blur(24px) saturate(1.5);backdrop-filter:blur(24px) saturate(1.5);border:1px solid var(--glass-edge);box-shadow:var(--shadow-pop)}
@media (prefers-reduced-transparency:reduce){.actionbar{background:var(--surface);backdrop-filter:none;-webkit-backdrop-filter:none}}
.actionbar .spacer{flex:1}

/* ===== Durum şeridi (sayfa üstü özet kutucukları) ===== */
.strip{display:grid;grid-template-columns:repeat(auto-fit,minmax(180px,1fr));gap:12px;margin:0 0 28px}
.strip>div{background:var(--surface);border-radius:var(--r-l);box-shadow:var(--shadow);border:1px solid var(--sep-2);padding:14px 16px}
@media (prefers-color-scheme:dark){.strip>div{border-color:transparent}}
.strip .k{font-size:13px;font-weight:500;color:var(--text-2);display:flex;gap:7px;align-items:center}
.strip .v{font-size:24px;font-weight:700;letter-spacing:-.02em;margin-top:4px}

/* ===== Kuyruk kartları: çeviri çiftleri ===== */
.queue{display:grid;gap:12px}
.qcard{background:var(--surface);border-radius:var(--r-l);box-shadow:var(--shadow);border:1px solid var(--sep-2);padding:16px 18px 14px 22px;position:relative;overflow:hidden}
.qcard::before{content:"";position:absolute;left:0;top:14px;bottom:14px;width:3px;border-radius:0 3px 3px 0;background:var(--accent)}
.qcard.st-failed::before{background:var(--bad)}.qcard.st-approved::before{background:#e3a008}.qcard.st-published::before{background:var(--good)}.qcard.st-rejected::before{background:var(--sep)}
@media (prefers-color-scheme:dark){.qcard{border-color:transparent}}
.qhead{display:flex;align-items:center;gap:10px;flex-wrap:wrap;margin:0 0 10px}
.qhead .id{font-weight:700;font-size:16px;color:var(--text)}
.qhead .meta{color:var(--text-2);font-size:13.5px}
.qhead .row{margin-left:auto}
.pair{display:grid;grid-template-columns:76px minmax(0,1fr);gap:2px 14px;padding:9px 0;border-top:1px solid var(--sep-2)}
.pair:first-of-type{border-top:0}
.pair .sid{color:var(--text-2);font-size:13px;font-variant-numeric:tabular-nums;padding-top:2px}
.pair .az{font-weight:600;font-size:16px;letter-spacing:-.012em}
.pair .src{color:var(--text-2);font-size:13.5px}
.pair .warn{color:var(--warn);font-size:13px}
.more{color:var(--text-2);font-size:13.5px;padding-top:8px}

/* ===== Gösterge paneli ===== */
.dash{display:grid;grid-template-columns:repeat(12,minmax(0,1fr));gap:16px}
.c-3{grid-column:span 3}.c-4{grid-column:span 4}.c-5{grid-column:span 5}.c-6{grid-column:span 6}.c-7{grid-column:span 7}.c-8{grid-column:span 8}.c-12{grid-column:span 12}
.card{padding:18px 20px;min-width:0}
.card-h{display:flex;align-items:center;justify-content:space-between;gap:10px;margin:0 0 14px}
.card-h h3{font-size:15px}
.card-h a{font-size:13.5px;font-weight:500}
.hero-card{display:flex;flex-direction:column;justify-content:space-between;gap:14px;background:linear-gradient(160deg,color-mix(in srgb,var(--accent) 12%,var(--surface)),var(--surface) 62%)}
.hero-num{font-size:64px;line-height:1;font-weight:700;letter-spacing:-.04em}
.hero-label{color:var(--text-2);font-size:15px;margin-top:6px}
.tile-label{color:var(--text-2);font-size:13.5px;font-weight:500;display:flex;align-items:center;gap:7px}
.tile-value{font-size:30px;line-height:1.1;font-weight:700;letter-spacing:-.03em;margin:8px 0 2px}
.tile-foot{color:var(--text-2);font-size:13px}
.tile-value a{color:inherit}.tile-value a:hover{color:var(--accent-text)}
.chart-scroll{overflow-x:auto;margin:0 -4px;padding:0 4px}
.spark{display:block;width:100%;height:36px;margin-top:10px;overflow:visible}
.spark path{fill:none;stroke:var(--text-3);stroke-width:2;stroke-linecap:round;stroke-linejoin:round;vector-effect:non-scaling-stroke}
.spark path.last{stroke:var(--accent);stroke-width:2.5}
.chart{display:block;width:100%;height:auto;overflow:visible}
.chart .grid{stroke:var(--sep-2);stroke-width:1}
.chart .axis{fill:var(--text-2);font-size:11.5px;font-variant-numeric:tabular-nums}
.chart .bar{outline:none;cursor:default}
.chart .bar:hover rect.m,.chart .bar:focus rect.m{filter:brightness(1.12)}
.chart .hit{fill:transparent}
.chart .bar:focus-visible .hit{stroke:var(--accent);stroke-width:2}
.legend{display:flex;gap:14px;flex-wrap:wrap;margin:12px 0 0;padding:0;list-style:none;font-size:13px;color:var(--text-2)}
.legend li{display:inline-flex;align-items:center;gap:7px}.legend b{color:var(--text);font-weight:600;font-variant-numeric:tabular-nums}
.sw{width:10px;height:10px;border-radius:3px;display:inline-block;flex:none}
.sw.s1{background:var(--s1)}.sw.s2{background:var(--s2)}.sw.s3{background:var(--s3)}.sw.s4{background:var(--s4)}.sw.s5{background:var(--s5)}
.mix{display:block;width:100%;height:14px}
.mix rect{outline:none}.mix rect:hover,.mix rect:focus{filter:brightness(1.12)}
.f-s1{fill:var(--s1)}.f-s2{fill:var(--s2)}.f-s3{fill:var(--s3)}.f-s4{fill:var(--s4)}.f-s5{fill:var(--s5)}
.tiles{display:grid;grid-template-columns:repeat(2,minmax(0,1fr));gap:16px}
.meter{display:block;width:100%;height:8px;margin-top:10px}
.meter .track{fill:color-mix(in srgb,var(--accent) 16%,var(--surface))}.meter .fill{fill:var(--accent)}
.meter.warn .track{fill:var(--warn-soft)}.meter.warn .fill{fill:#e3a008}.meter.bad .track{fill:var(--bad-soft)}.meter.bad .fill{fill:var(--bad)}
.list-rows{list-style:none;margin:0;padding:0}
.list-rows li{display:flex;align-items:center;gap:10px;padding:10px 0;border-top:1px solid var(--sep-2);min-width:0}
.list-rows li:first-child{border-top:0;padding-top:0}
.list-rows .grow{min-width:0;flex:1 1 auto}.list-rows .t{font-weight:500;white-space:nowrap;overflow:hidden;text-overflow:ellipsis}
.list-rows .s{color:var(--text-2);font-size:13px;white-space:nowrap;overflow:hidden;text-overflow:ellipsis}
.list-rows .r{margin-left:auto;color:var(--text-2);font-size:13px;white-space:nowrap}
.timeline{list-style:none;margin:0;padding:0;position:relative}
.timeline li{position:relative;padding:0 0 14px 22px;font-size:14px}
.timeline li::before{content:"";position:absolute;left:4px;top:16px;bottom:-2px;width:1px;background:var(--sep-2)}
.timeline li:last-child{padding-bottom:0}.timeline li:last-child::before{display:none}
.timeline .dot{position:absolute;left:1px;top:6px}
.timeline .src{color:var(--text-2);font-size:12.5px}
.quick{display:grid;grid-template-columns:repeat(2,minmax(0,1fr));gap:8px}
.quick .btn,.quick form .btn{justify-content:flex-start;border-radius:12px;min-height:44px;padding:10px 14px;width:100%}
.quick form{display:block}
.chart-table{margin-top:12px}.chart-table table{font-size:13.5px}

/* ===== Komut paleti ve ipucu ===== */
.cmd{border-radius:18px;padding:0;width:min(600px,calc(100vw - 32px));max-height:min(520px,80vh);color:var(--text);box-shadow:var(--shadow-pop);margin-top:12vh}
.cmd::backdrop{background:rgba(0,0,0,.25)}
@media (prefers-color-scheme:dark){.cmd::backdrop{background:rgba(0,0,0,.5)}}
.cmd-in{display:flex;align-items:center;gap:10px;padding:14px 16px;border-bottom:1px solid var(--sep-2);color:var(--text-2)}
.cmd-in input{flex:1;border:0!important;background:transparent!important;font-size:17px!important;padding:0!important;box-shadow:none!important;color:var(--text)}
.cmd-list{list-style:none;margin:0;padding:6px;overflow:auto;max-height:420px}
.cmd-list a{display:flex;align-items:center;gap:11px;padding:10px 12px;border-radius:11px;color:var(--text);text-decoration:none!important}
.cmd-list small{margin-left:auto;color:var(--text-2);font-size:12px}
.cmd-list li.sel a,.cmd-list a:hover{background:var(--accent);color:var(--on-accent)}
.cmd-list li.sel small,.cmd-list a:hover small{color:color-mix(in srgb,var(--on-accent) 80%,transparent)}
.tip{position:fixed;z-index:60;pointer-events:none;display:grid;gap:1px;background:var(--surface);color:var(--text);border-radius:10px;padding:8px 11px;font-size:12.5px;box-shadow:var(--shadow-pop);max-width:260px}
.tip strong{font-size:14px;font-variant-numeric:tabular-nums}.tip span{color:var(--text-2)}
.tip[hidden]{display:none}

/* ===== Giriş ===== */
.login-card{background:var(--surface);border-radius:22px;box-shadow:var(--shadow-pop);overflow:hidden;border:1px solid var(--sep-2)}
.login-card .body{padding:30px 28px;display:grid;gap:18px}
.login-card .mark{width:46px;height:46px;border-radius:13px}.login-card .mark i{width:7px;height:7px}
.login-card h1{font-size:26px}.login-card input{width:100%}
.login-card form .btn{width:100%;min-height:42px;font-size:15px}

/* ===== Mobil: sekme çubuğu + menü sayfası ===== */
.tabbar{display:none}
@media (max-width:900px){
  .shell{margin-left:0}
  .side{inset:8px 8px 8px 8px;width:auto;transform:translateY(110%);opacity:0;pointer-events:none;z-index:50}
  .side:target{transform:none;opacity:1;pointer-events:auto}
  .side-hidden .side{transform:translateY(110%)}
  .close-menu{display:inline-flex}
  .toolbar{margin:8px 8px 0;top:8px}
  .toolbar [data-toggle-side]{display:none!important}
  .tb-title{display:none}
  .search{width:auto;flex:1;margin-left:0}.search kbd{display:none!important}
  .main{padding:16px 16px calc(100px + env(safe-area-inset-bottom))}
  h1{font-size:28px}
  .tabbar{display:flex;position:fixed;z-index:40;left:10px;right:10px;bottom:calc(8px + env(safe-area-inset-bottom));height:64px;border-radius:24px;padding:0 6px;justify-content:space-around;align-items:center}
  .tabbar a{position:relative;flex:1;display:flex;flex-direction:column;align-items:center;gap:3px;min-height:54px;justify-content:center;border-radius:18px;font-size:11px;font-weight:600;color:var(--text-2);text-decoration:none}
  .tabbar a[aria-current="page"]{color:var(--accent-text);background:color-mix(in srgb,var(--accent) 12%,transparent)}
  .tab-badge{position:absolute;top:5px;left:calc(50% + 6px);min-width:18px;height:18px;padding:0 5px;border-radius:9px;background:var(--bad);color:#fff;font-size:11px;line-height:18px;text-align:center}
  .dash{grid-template-columns:repeat(2,minmax(0,1fr));gap:12px}
  .c-3{grid-column:span 1}.c-4,.c-5,.c-6,.c-7,.c-8,.c-12{grid-column:span 2}
  .hero-num{font-size:52px}
  .chart-scroll .chart{min-width:560px}
  .split{grid-template-columns:1fr}
  .pair{grid-template-columns:1fr}
  .page-actions{width:100%}
  .edit-table thead{display:none}
  .edit-table table,.edit-table tbody,.edit-table tr,.edit-table td{display:block;width:100%}
  .edit-table tr{padding:12px 14px;border-top:1px solid var(--sep-2)}.edit-table tr:first-child{border-top:0}
  .edit-table td{padding:3px 0;border:0;text-align:left}
  .edit-table td.num::before{content:"Fiyat ";color:var(--text-2)}
  .actionbar{bottom:calc(84px + env(safe-area-inset-bottom))}
}
@media (max-width:480px){.dash .c-3{grid-column:span 2}.quick{grid-template-columns:1fr}.tile-value{font-size:26px}}
@media (prefers-reduced-motion:reduce){*,*::before,*::after{transition:none!important;animation:none!important;scroll-behavior:auto!important}}
@media print{.side,.toolbar,.tabbar,.actionbar{display:none!important}.shell{margin:0}}
CSS;
}
