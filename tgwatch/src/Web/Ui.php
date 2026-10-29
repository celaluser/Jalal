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
    /**
     * Gezinme: 6 bölüm. Her bölümün alt sayfaları sayfanın üstünde segment kontrolü (sekmeler) olarak çıkar;
     * kenar menü ve mobil alt çubuk yalnızca bölümleri gösterir. [ad, ikon, [sayfa => sekme adı]]
     */
    public const SECTIONS = [
        'dash' => ['Genel bakış', 'gauge', ['dash' => 'Genel bakış']],
        'work' => ['İşler', 'queue', ['queue' => 'Onay kuyruğu', 'jobs' => 'Tüm işler', 'changes' => 'Değişiklikler', 'unparsed' => 'Okunamayanlar']],
        'channels' => ['Kanallar', 'channels', ['channels' => 'Kanallar']],
        'translate' => ['Çeviri', 'dict', ['dict' => 'Sözlük', 'bank' => 'Çeviri bankası', 'learn' => 'Öğrenme', 'ai' => 'Çeviri ayarları']],
        'insights' => ['Analiz', 'chart', ['catalog' => 'Servisler', 'audit' => 'Fiyat denetimi', 'reports' => 'Raporlar', 'rivals' => 'Rakip kıyası', 'rival_list' => 'Rakip listesi']],
        'system' => ['Ayarlar', 'gear', ['settings' => 'Ayarlar', 'profile' => 'Profil', 'alerts' => 'Uyarılar', 'botcmds' => 'Bot komutları', 'health' => 'Sağlık', 'logs' => 'Kayıtlar']],
    ];

    /** Sayfa => bölüm anahtarı. */
    public static function sectionOf(string $page): string
    {
        foreach (self::SECTIONS as $k => [, , $pages]) {
            if (isset($pages[$page])) {
                return $k;
            }
        }
        return 'dash';
    }

    public function __construct(public string $csrf, public string $nonce)
    {
    }

    public const MARK_PATHS = ['M3.2 28.5V20C3.2 15 6.11 12 8.1 9c1.7 3.2 3.08 6 2.82 11V28.5z', 'M12.4 28.5V13.6C12.4 8.6 15.4 5.6 17.6 2.6c1.76 3.2 2.96 6 2.64 11V28.5z', 'M21.6 28.5V23.5C21.6 18.5 24.42 15.5 26.2 12.5c1.64 3.2 3.2 6 3 11V28.5z'];

    /**
     * Logo: Bakü'nün Alov Qüllələri (Alev Kuleleri) silueti; üç kule = üç kanal.
     * $mono true ise tek renk (menü şeridi ve koyu zemin), değilse nila/rövənd/zəfəran boyaları.
     */
    /** Profil menüsündeki tasarım stili düğmeleri. */
    private static function styleButtons(string $cur): string
    {
        $h = '';
        foreach (self::STYLES as $k => $label) {
            $h .= '<button type="button" data-look="style" data-val="' . $k . '"' . ($cur === $k ? ' aria-pressed="true"' : '') . '>' . self::e($label) . '</button>';
        }
        return $h;
    }

    public static function mark(int $size = 32, bool $mono = false): string
    {
        $paths = self::MARK_PATHS;
        $cls = ['m1', 'm2', 'm3'];
        $svg = '';
        foreach ($paths as $i => $p) {
            $svg .= '<path class="' . $cls[$i] . '" d="' . $p . '"/>';
        }
        return '<svg class="mark-svg' . ($mono ? ' mono' : '') . '" viewBox="0 0 32 32" width="' . $size . '" height="' . $size . '" aria-hidden="true" focusable="false">' . $svg . '</svg>';
    }

    /** CSV/Excel formül enjeksiyonuna karşı: =, +, -, @, sekme ya da satır başı ile başlayan metni etkisizleştirir (dış kaynaklı adlar!). */
    public static function csvCell($v)
    {
        if (!is_string($v) || $v === '') {
            return $v;
        }
        return preg_match('/^[=+\-@\t\r]/', $v) && !is_numeric($v) ? "'" . $v : $v;
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
            'user' => '<circle cx="12" cy="8.5" r="3.5"/><path d="M5 20a7 7 0 0 1 14 0"/>',
            'chart' => '<path d="M5 20V11M12 20V5M19 20v-6"/>',
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
            'gear' => '<circle cx="12" cy="12" r="3"/><path d="M12 3v2.2M12 18.8V21M4.6 7.5l1.9 1.1M17.5 15.4l1.9 1.1M4.6 16.5l1.9-1.1M17.5 8.6l1.9-1.1"/><circle cx="12" cy="12" r="7.2"/>',
            'scale' => '<path d="M12 4v16M7 20h10M5 7h14"/><path d="M5 7l-3 7a3 3 0 0 0 6 0zM19 7l-3 7a3 3 0 0 0 6 0z"/>',
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

        $pf = $o['prefs'] ?? [];
        $name = (string)($pf['name'] ?? 'tgwatch');
        $accent = isset(self::ACCENT_SET[$pf['accent'] ?? '']) ? $pf['accent'] : 'blue';
        $theme = in_array($pf['theme'] ?? 'auto', ['light', 'dark'], true) ? $pf['theme'] : 'auto';
        $style = isset(self::STYLES[$pf['style'] ?? '']) ? (string)$pf['style'] : 'buta';
        $doc = '<!doctype html><html lang="tr" data-forced="' . $theme . '" data-style="' . $style . '" data-density="' . (($pf['density'] ?? '') === 'compact' ? 'compact' : 'comfortable') . '"><head><meta charset="utf-8">'
            . '<meta name="viewport" content="width=device-width,initial-scale=1,viewport-fit=cover"><meta name="robots" content="noindex,nofollow">'
            . '<meta name="color-scheme" content="' . ($theme === 'auto' ? 'light dark' : $theme) . '"><meta name="theme-color" content="' . ($theme === 'dark' ? '#000000' : '#f5f5f7') . '"' . ($theme === 'auto' ? ' media="(prefers-color-scheme: light)"' : '') . '>'
            . ($theme === 'auto' ? '<meta name="theme-color" media="(prefers-color-scheme: dark)" content="#000000">' : '')
            . '<title>' . self::e($title) . ' · ' . self::e($name) . '</title>'
            . '<link rel="icon" href="data:image/svg+xml,' . rawurlencode('<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 32 32"><rect width="32" height="32" rx="7" fill="#F1F2EE"/><g transform="translate(2 2) scale(.875)"><path fill="#243B6B" d="' . self::MARK_PATHS[0] . '"/><path fill="#B3362F" d="' . self::MARK_PATHS[1] . '"/><path fill="#D9A21B" d="' . self::MARK_PATHS[2] . '"/></g></svg>') . '">'
            . '<style nonce="' . $this->nonce . '">' . self::css($theme, $accent) . '</style></head>';

        if (!$nav) {
            return $doc . '<body class="bare"><main class="bare-main" id="main">' . $flash . $body . '<p class="login-ver">tgwatch v' . \TgWatch\VERSION . '</p></main></body></html>';
        }

        $links = '';
        $palette = '';
        $tabs = '';
        $sub = '';
        $curSec = self::sectionOf($active);
        $counts = $o['counts'] ?? [];
        foreach (self::SECTIONS as $sk => [$label, $ic, $pages]) {
            $first = (string)array_key_first($pages);
            $cnt = 0;
            foreach ($pages as $pk => $pl) {
                $cnt += (int)($counts[$pk] ?? 0);
            }
            $badge = $cnt > 0 ? '<span class="count" aria-label="' . $cnt . ' bekleyen">' . $cnt . '</span>' : '';
            $cur = $sk === $curSec ? ' aria-current="page"' : '';
            $links .= '<a href="?p=' . $first . '" data-sec="' . $sk . '"' . $cur . '>' . self::icon($ic) . '<span>' . self::e($label) . '</span>' . $badge . '</a>';
            $tabs .= '<a href="?p=' . $first . '" data-sec="' . $sk . '"' . $cur . '>' . self::icon($ic, 22) . ($cnt > 0 ? '<span class="tab-badge">' . $cnt . '</span>' : '') . '<span>' . self::e($sk === 'dash' ? 'Genel' : $label) . '</span></a>';
            foreach ($pages as $pk => $pl) {
                $palette .= '<li><a href="?p=' . $pk . '" data-cmd="' . self::e(mb_strtolower($pl . ' ' . $label)) . '">' . self::icon($ic, 16) . '<span>' . self::e($pl) . '</span><small>' . self::e($label) . '</small></a></li>';
            }
            if ($sk === $curSec && count($pages) > 1) {
                $sub = '<nav class="tabs subnav" aria-label="' . self::e($label) . ' bölümü">';
                foreach ($pages as $pk => $pl) {
                    $c = (int)($counts[$pk] ?? 0);
                    $sub .= '<a href="?p=' . $pk . '"' . ($active === $pk ? ' aria-current="page"' : '') . '>' . self::e($pl) . ($c > 0 ? ' <span class="n">' . $c . '</span>' : '') . '</a>';
                }
                $sub .= '</nav>';
            }
        }
        $cron = $o['cron'] ?? null;
        $side = '<aside class="side glass" id="menu" aria-label="Ana menü">'
            . '<div class="brand"><span class="mark" aria-hidden="true">' . self::mark(34, true) . '</span><span>' . self::e($name) . '<small>servis çeviri masası</small></span>'
            . '<a class="close-menu" href="#" aria-label="Menüyü kapat">' . self::icon('x') . '</a></div>'
            . '<nav class="nav">' . $links . '</nav>'
            . '<div class="side-foot">' . ($cron ? '<a class="side-status" href="?p=health">' . self::dot($cron['state']) . '<span>Kanal okuma ' . $cron['text'] . '</span></a>' : '')
            . (empty($o['loginOff']) ? '<form method="post" action="?p=logout">' . $this->csrfField() . '<button class="side-link">' . self::icon('logout', 16) . '<span>Çıkış yap</span></button></form>' : '<span class="side-link muted">Giriş kapalı</span>') . '</div></aside>';

        $adm = trim((string)($pf['admin'] ?? '')) !== '' ? trim((string)$pf['admin']) : 'Yönetici';
        $words = preg_split('/\s+/u', $adm) ?: [$adm];
        $initials = mb_strtoupper(mb_substr($words[0], 0, 1) . (isset($words[1]) ? mb_substr($words[1], 0, 1) : mb_substr($words[0], 1, 1)));
        $menu = '<details class="profile-menu"><summary class="avatar" aria-label="Profil menüsü"><span aria-hidden="true">' . self::e($initials) . '</span></summary>'
            . '<div class="pm-panel glass"><div class="pm-head"><span class="avatar big" aria-hidden="true">' . self::e($initials) . '</span><div><b>' . self::e($adm) . '</b><small>' . (empty($o['loginOff']) ? 'Yönetici' : 'Giriş kapalı') . '</small></div></div>'
            . '<a href="?p=profile">' . self::icon('user', 18) . '<span>Profilim</span></a>'
            . '<a href="?p=settings">' . self::icon('gear', 18) . '<span>Ayarlar</span></a>'
            . '<div class="pm-look js-only" role="group" aria-label="Tasarım stili"><span>Tasarım</span>' . self::styleButtons($style) . '</div>'
            . '<div class="pm-look js-only" role="group" aria-label="Renk modu"><span>Mod</span>'
            . '<button type="button" data-look="theme" data-val="light"' . ($theme === 'light' ? ' aria-pressed="true"' : '') . '>Açık</button>'
            . '<button type="button" data-look="theme" data-val="dark"' . ($theme === 'dark' ? ' aria-pressed="true"' : '') . '>Koyu</button>'
            . '<button type="button" data-look="theme" data-val="auto"' . ($theme === 'auto' ? ' aria-pressed="true"' : '') . '>Otomatik</button></div>'
            . '<a href="#" data-open-help>' . self::icon('menu', 18) . '<span>Klavye kısayolları</span></a>'
            . (empty($o['loginOff']) ? '<form method="post" action="?p=logout">' . $this->csrfField() . '<button class="pm-out">' . self::icon('logout', 18) . '<span>Çıkış yap</span></button></form>' : '')
            . '</div></details>';
        $toolbar = '<div class="toolbar glass" role="banner">'
            . '<button type="button" class="icon-btn js-only" data-toggle-side aria-label="Kenar menüyü gizle veya göster" aria-controls="menu">' . self::icon('sidebar') . '</button>'
            . '<span class="tb-title">' . self::e($title) . '</span>'
            . '<button type="button" class="live js-only" data-live aria-pressed="true" title="Canlı güncelleme: açık"><i class="pulse" aria-hidden="true"></i><span>Canlı</span></button>'
            . '<form method="get" class="search" role="search"><input type="hidden" name="p" value="jobs">' . self::icon('search', 16)
            . '<input type="search" name="q" placeholder="Servis ID, iş ya da kelime ara" aria-label="İşlerde ara" autocomplete="off"><kbd class="js-only" data-open-cmd title="Komut paleti">⌘K</kbd></form>'
            . '<button type="button" class="icon-btn js-only theme-btn" data-look="theme" data-val="' . ($theme === 'dark' ? 'light' : 'dark') . '" data-cycle="1" aria-label="Açık ve koyu modu değiştir" title="' . ($theme === 'dark' ? 'Açık moda geç' : 'Koyu moda geç') . '"><span aria-hidden="true">' . ($theme === 'dark' ? '☀' : '☾') . '</span></button>'
            . $menu . '</div>';

        $palette .= '<li><a href="?p=job_new" data-cmd="yeni iş duyuru yapıştır ekle">' . self::icon('plus', 16) . '<span>Yeni iş ekle</span><small>İşlem</small></a></li>'
            . '<li><a href="?p=settings&amp;tab=telegram" data-cmd="ayarlar telegram bot token e-posta">' . self::icon('gear', 16) . '<span>Ayarlar: Telegram ve e-posta</span><small>Ayarlar</small></a></li>'
            . '<li><a href="?p=settings&amp;tab=security" data-cmd="ayarlar güvenlik şifre oturum">' . self::icon('gear', 16) . '<span>Ayarlar: Güvenlik ve şifre</span><small>Ayarlar</small></a></li>'
            . '<li><a href="?p=settings&amp;tab=appearance" data-cmd="ayarlar görünüm tema renk koyu">' . self::icon('gear', 16) . '<span>Ayarlar: Görünüm ve tema</span><small>Ayarlar</small></a></li>';
        $help = '<dialog class="cmd glass help" id="help" aria-label="Klavye kısayolları"><div class="help-in"><h3>Klavye kısayolları</h3><dl>'
            . '<dt><kbd>⌘K</kbd> / <kbd>Ctrl K</kbd></dt><dd>Komut paleti: sayfa, ayar ya da iş ara</dd>'
            . '<dt><kbd>/</kbd></dt><dd>Aramaya odaklan</dd>'
            . '<dt><kbd>g</kbd> <kbd>d</kbd></dt><dd>Genel bakış</dd><dt><kbd>g</kbd> <kbd>q</kbd></dt><dd>Onay kuyruğu</dd>'
            . '<dt><kbd>g</kbd> <kbd>j</kbd></dt><dd>İşler</dd><dt><kbd>g</kbd> <kbd>c</kbd></dt><dd>Kanallar</dd>'
            . '<dt><kbd>g</kbd> <kbd>u</kbd></dt><dd>Okunamayanlar</dd><dt><kbd>g</kbd> <kbd>s</kbd></dt><dd>Ayarlar</dd>'
            . '<dt><kbd>n</kbd></dt><dd>Yeni iş</dd><dt><kbd>?</kbd></dt><dd>Bu pencere</dd></dl><form method="dialog"><button class="btn quiet">Kapat</button></form></div></dialog>';
        $cmd = '<dialog class="cmd glass" id="cmd" aria-label="Komut paleti"><form method="dialog" class="cmd-in">' . self::icon('search')
            . '<input type="text" id="cmd-q" placeholder="Sayfaya git ya da servis ID / iş ara…" autocomplete="off" aria-label="Komut ara"><kbd>esc</kbd></form>'
            . '<ul class="cmd-list" id="cmd-list">' . $palette . '<li data-search><a href="?p=jobs&amp;q=" id="cmd-search">' . self::icon('search', 16) . '<span>İşlerde ara: <b id="cmd-term"></b></span><small>Arama</small></a></li></ul></dialog>';

        return $doc . '<body class="app" data-queue="' . (int)($o['pending'] ?? 0) . '"><a class="skip" href="#main">İçeriğe geç</a>' . $side
            . '<div class="shell">' . $toolbar . '<main class="main" id="main">' . ($o['banner'] ?? '') . $head . $sub . $flash . $body . '<footer class="foot"><span>' . self::e($name) . ' v' . \TgWatch\VERSION . '</span><a href="?p=settings">Ayarlar</a><a href="?p=health">Sağlık</a><a href="?p=logs">Kayıtlar</a><a href="#" data-open-help>Kısayollar <kbd>?</kbd></a></footer></main></div>'
            . '<nav class="tabbar glass" aria-label="Sekmeler">' . $tabs . '</nav>'
            . $cmd . $help . '<div class="tip" id="tip" role="tooltip" hidden></div><div id="toasts" role="status" aria-live="polite"></div>'
            . '<script nonce="' . $this->nonce . '">' . self::JS . '</script></body></html>';
    }

    /** vurgu rengi: [açık/koyu düğme rengi, koyu temada yazı rengi] */
    public const STYLES = ['buta' => 'Buta', 'cam' => 'Cam', 'impuls' => 'İmpuls'];

    private const ACCENT_SET = [
        'blue' => ['#0a63ce', '#5aa9ff'], 'green' => ['#1b7f3b', '#4cc277'], 'purple' => ['#7b3fd4', '#a779f0'],
        'orange' => ['#b5500a', '#f08a3c'], 'pink' => ['#c2255f', '#f06a9c'],
    ];

    /** Seçilen tema ve vurgu rengine göre CSS: tema "koyu/açık" ise sistem ayarı yerine o kullanılır. */
    private static function css(string $theme, string $accent): string
    {
        [$a, $t] = self::ACCENT_SET[$accent];
        $css = self::CSS
            . ":root{--accent:$a;--accent-text:$a;--accent-soft:color-mix(in srgb,$a 10%,#fff)}"
            . "@media (prefers-color-scheme:dark){:root{--accent:$a;--accent-text:$t;--accent-soft:color-mix(in srgb,$a 32%,#000)}}";
        if ($theme === 'dark') {
            return str_replace('@media (prefers-color-scheme:dark)', '@media all', $css);
        }
        if ($theme === 'light') {
            return str_replace('@media (prefers-color-scheme:dark)', '@media not all', $css);
        }
        return $css;
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
  // Profil menüsü: dışarı tıklayınca, bağlantıya tıklayınca ya da Esc ile kapanır
  d.addEventListener('click', function (e) {
    var pm = d.querySelector('.profile-menu[open]'); if (!pm) return;
    if (!pm.contains(e.target) || (e.target.closest && e.target.closest('.pm-panel a'))) pm.removeAttribute('open');
  });
  d.addEventListener('click', function (e) {
    var b = e.target.closest && e.target.closest('[data-look]'); if (!b) return;
    var val = b.getAttribute('data-val'), k = b.getAttribute('data-look');
    if (k === 'theme' && b.hasAttribute('data-cycle')) {
      var f = d.documentElement.getAttribute('data-forced'), dark = f === 'dark' || (f === 'auto' && matchMedia('(prefers-color-scheme:dark)').matches);
      val = dark ? 'light' : 'dark';
    }
    try { d.cookie = 'tgw_' + k + '=' + val + ';path=' + location.pathname.replace(/[^\/]*$/, '') + ';max-age=31536000;samesite=lax'; } catch (x) {}
    location.reload();
  });
  d.addEventListener('keydown', function (e) { if (e.key === 'Escape') { var pm = d.querySelector('.profile-menu[open]'); if (pm) { pm.removeAttribute('open'); } } });

  // Kısayollar: "g" + harf sayfaya gider, "n" yeni iş, "?" yardım
  var gPending = false, gTimer = 0, GO = { d: 'dash', q: 'queue', j: 'jobs', c: 'channels', u: 'unparsed', s: 'settings', l: 'logs', h: 'health', r: 'reports' };
  var help = d.getElementById('help');
  d.addEventListener('click', function (e) { if (e.target.closest && e.target.closest('[data-open-help]')) { e.preventDefault(); if (help && help.showModal) help.showModal(); } });
  d.addEventListener('keydown', function (e) {
    var t = (e.target.tagName || '').toLowerCase();
    if (e.metaKey || e.ctrlKey || e.altKey || t === 'input' || t === 'textarea' || t === 'select' || d.querySelector('dialog[open]')) return;
    if (e.key === '?') { e.preventDefault(); if (help && help.showModal) help.showModal(); return; }
    if (gPending) { gPending = false; clearTimeout(gTimer); var p = GO[e.key.toLowerCase()]; if (p) { e.preventDefault(); load('?p=' + p); } return; }
    if (e.key === 'g') { gPending = true; gTimer = setTimeout(function () { gPending = false; }, 1200); }
    else if (e.key === 'n') { e.preventDefault(); load('?p=job_new'); }
  });
  d.addEventListener('keydown', function (e) {
    if ((e.metaKey || e.ctrlKey) && e.key.toLowerCase() === 'k') { e.preventDefault(); openCmd(); }
    var tag = (e.target.tagName || '').toLowerCase();
    if (e.key === '/' && tag !== 'input' && tag !== 'textarea' && tag !== 'select') {
      var s = d.querySelector('.search input[type=search]'); if (s) { e.preventDefault(); s.focus(); }
    }
  });

  // ===== Dinamik: yumuşak gezinme, canlı güncelleme, bildirimler, animasyon =====
  var reduce = window.matchMedia && matchMedia('(prefers-reduced-motion: reduce)').matches;
  var toastBox = d.getElementById('toasts'), mainEl = d.getElementById('main');
  var bar = d.createElement('div'); bar.className = 'loadbar'; d.body.appendChild(bar);
  var busyTimer = 0;
  function busy(on) { // hızlı yüklemelerde hiç göstermez (150 ms'den kısaysa titreme olmasın)
    clearTimeout(busyTimer);
    if (on) { busyTimer = setTimeout(function () { bar.classList.remove('done'); bar.classList.add('on'); if (mainEl) mainEl.classList.add('loading'); }, 150); }
    else { mainEl && mainEl.classList.remove('loading'); if (bar.classList.contains('on')) { bar.classList.remove('on'); bar.classList.add('done'); setTimeout(function () { bar.classList.remove('done'); }, 600); } }
  }
  function armToast(f) {
    var b = d.createElement('button'); b.type = 'button'; b.className = 'flash-x'; b.setAttribute('aria-label', 'Kapat'); b.textContent = '×';
    b.addEventListener('click', function () { f.remove(); }); f.appendChild(b);
    setTimeout(function () { f.classList.add('gone'); setTimeout(function () { f.remove(); }, 400); }, f.classList.contains('bad') ? 12000 : 5000);
  }
  function pushToast(f) { // aynı metin tekrar etmesin, en çok 3 bildirim görünsün
    var t = f.textContent;
    Array.prototype.slice.call(toastBox.children).forEach(function (o) { if (o.textContent === t) o.remove(); });
    toastBox.appendChild(f); armToast(f);
    while (toastBox.children.length > 3) toastBox.firstChild.remove();
  }
  function clearToasts() { if (toastBox) toastBox.textContent = ''; }
  function toasts() { // sayfadaki bildirimleri köşedeki yığına taşı
    if (!toastBox) return;
    d.querySelectorAll('#main .flash').forEach(function (f) { pushToast(f); });
  }
  function toast(msg, type) {
    if (!toastBox) return;
    var f = d.createElement('div'); f.className = 'flash ' + (type || 'ok'); var s = d.createElement('span'); s.textContent = msg; f.appendChild(s);
    pushToast(f);
  }
  function countUp(root) {
    if (reduce) return;
    root.querySelectorAll('.hero-num,.tile-value').forEach(function (el) {
      var w = d.createTreeWalker(el, NodeFilter.SHOW_TEXT), tn = w.nextNode();
      while (tn && !tn.nodeValue.trim()) tn = w.nextNode();
      if (!tn) return;
      var m = /^(\s*%?)(\d+)(\s*)$/.exec(tn.nodeValue); if (!m) return;
      var to = +m[2]; if (to < 1 || to > 100000) return;
      var t0 = performance.now();
      (function step(t) { var p = Math.min(1, (t - t0) / 700); tn.nodeValue = m[1] + Math.round(to * (1 - Math.pow(1 - p, 3))) + m[3]; if (p < 1) requestAnimationFrame(step); })(t0);
    });
  }
  toasts(); if (mainEl) countUp(mainEl);

  function labelTables(root) { // dar ekranda tablo satırları kart olur: hücrelere başlık etiketi ver
    (root || d).querySelectorAll('.table-wrap:not(.edit-table)').forEach(function (w) {
      var t = w.querySelector('table'); if (!t) return;
      var heads = Array.prototype.map.call(t.querySelectorAll('thead th'), function (h) { return h.textContent.trim(); });
      if (!heads.length) return;
      w.classList.add('cards');
      t.querySelectorAll('tbody tr').forEach(function (tr) {
        Array.prototype.forEach.call(tr.children, function (td, i) { if (heads[i] && !td.hasAttribute('data-label')) td.setAttribute('data-label', heads[i]); });
      });
    });
  }
  labelTables(d);
  function titleCount(n) { // sekme başlığında onay bekleyen sayısı
    if (n === undefined) n = parseInt(d.body.getAttribute('data-queue'), 10) || 0;
    var base = d.title.replace(/^\(\d+\)\s*/, ''); d.title = n > 0 ? '(' + n + ') ' + base : base;
  }
  titleCount();
  function bump(c) { c.classList.remove('bump'); void c.offsetWidth; c.classList.add('bump'); }
  function setSecBadge(sec, n) { // kenar menü ve alt çubuktaki bölüm rozeti
    d.querySelectorAll('[data-sec="' + sec + '"]').forEach(function (a) {
      var c = a.querySelector('.count, .tab-badge'), inBar = !!a.closest('.tabbar');
      if (n > 0) { if (!c) { c = d.createElement('span'); c.className = inBar ? 'tab-badge' : 'count'; a.appendChild(c); } if (c.textContent !== String(n)) { c.textContent = n; bump(c); } }
      else if (c) c.remove();
    });
  }
  function setSubBadge(page, n) { // sayfanın üstündeki sekmelerdeki sayı
    d.querySelectorAll('.subnav a[href="?p=' + page + '"]').forEach(function (a) {
      var c = a.querySelector('.n');
      if (n > 0) { if (!c) { c = d.createElement('span'); c.className = 'n'; a.appendChild(c); } c.textContent = n; }
      else if (c) c.remove();
    });
  }

  var NOSOFT = ['login', 'logout', 'setup', 'password_change', 'sessions_revoke', 'backup_import', 'backup', 'export', 'job_txt'];
  function swap(doc, mode) {
    var nm = doc.getElementById('main');
    if (!nm || doc.body.classList.contains('bare')) return false;
    var apply = function () {
      mainEl.classList.add('quiet'); // giriş animasyonu yalnızca ilk açılışta; sayfa geçişleri anında
      mainEl.innerHTML = nm.innerHTML;
      ['.side .nav', '.side-foot', '.tabbar', '.tb-title', '.profile-menu'].forEach(function (s) { var a = d.querySelector(s), b = doc.querySelector(s); if (a && b) a.innerHTML = b.innerHTML; });
      d.title = doc.title; d.body.setAttribute('data-queue', doc.body.getAttribute('data-queue') || '0'); titleCount();
      toasts(); labelTables(mainEl);
    };
    apply();
    return true;
  }
  var cache = {};
  function fetchPage(url, o) {
    return fetch(url, { method: (o && o.method) || 'GET', body: o && o.body, credentials: 'same-origin', headers: { 'X-Requested-With': 'fetch' } })
      .then(function (r) { return r.text().then(function (t) { return [r, t]; }); });
  }
  function prefetch(a) { // fare üzerine gelince ya da dokununca sayfayı önceden iste: tıklayınca anında açılır
    var u; try { u = new URL(a.href, location.href); } catch (x) { return; }
    if (u.origin !== location.origin || u.pathname !== location.pathname || a.target || a.hasAttribute('download')) return;
    var p = u.searchParams.get('p') || 'dash';
    if (NOSOFT.indexOf(p) >= 0 || p === 'live' || u.href === location.href) return;
    var c = cache[u.href]; if (c && Date.now() - c.t < 20000) return;
    cache[u.href] = { t: Date.now(), p: fetchPage(u.href).catch(function () { delete cache[u.href]; throw 0; }) };
  }
  ['pointerover', 'touchstart', 'focusin'].forEach(function (ev) {
    d.addEventListener(ev, function (e) { var a = e.target.closest && e.target.closest('a[href]'); if (a && !d.body.classList.contains('bare')) prefetch(a); }, { passive: true });
  });
  function load(url, o) {
    o = o || {}; var live = !!o.live; var post = (o.method || 'GET') === 'POST';
    if (!live) { busy(true); clearToasts(); }
    if (post) cache = {};
    var hit = !live && !post && cache[url] && Date.now() - cache[url].t < 20000 ? cache[url].p : null;
    return (hit || fetchPage(url, o))
      .then(function (x) {
        var r = x[0], doc = new DOMParser().parseFromString(x[1].replace(/<(style|script)[\s\S]*?<\/\1>/g, ''), 'text/html'), fin = r.url || url;
        var oldP = new URL(location.href).searchParams.get('p') || 'dash', newP = new URL(fin).searchParams.get('p') || 'dash';
        if (!swap(doc, live ? 'live' : 'nav')) { location.href = fin; return; }
        if (!live) {
          if (o.push !== false && fin !== location.href) { (o.method === 'POST' && oldP === newP ? history.replaceState : history.pushState).call(history, {}, '', fin); }
          if (oldP !== newP || o.method !== 'POST') window.scrollTo(0, 0);
          shown = null;
        }
      })
      .catch(function () { if (!live) location.href = url; })
      .then(function () { if (!live) busy(false); });
  }

  d.addEventListener('click', function (e) {
    var a = e.target.closest && e.target.closest('a[href]');
    if (!a || e.defaultPrevented || e.button || e.metaKey || e.ctrlKey || e.shiftKey || e.altKey || a.target || a.hasAttribute('download') || d.body.classList.contains('bare')) return;
    var raw = a.getAttribute('href'); if (!raw || raw.charAt(0) === '#') return;
    var u; try { u = new URL(a.href, location.href); } catch (x) { return; }
    if (u.origin !== location.origin || u.pathname !== location.pathname) return;
    if (NOSOFT.indexOf(u.searchParams.get('p') || 'dash') >= 0) return;
    e.preventDefault(); load(u.href);
  });
  d.addEventListener('submit', function (e) { // confirm yukarıda çalıştı; iptal edildiyse dokunma
    var f = e.target;
    if (e.defaultPrevented || !f.method || f.method.toLowerCase() !== 'post' || f.enctype === 'multipart/form-data' || d.body.classList.contains('bare')) return;
    var act; try { act = new URL(f.action, location.href); } catch (x) { return; }
    if (act.origin !== location.origin || NOSOFT.indexOf(act.searchParams.get('p') || '') >= 0) return;
    e.preventDefault();
    var sb = e.submitter; if (sb) sb.classList.add('busy');
    var fd; try { fd = new FormData(f, sb); } catch (x) { fd = new FormData(f); }
    load(act.href, { method: 'POST', body: fd });
  });
  window.addEventListener('popstate', function () { load(location.href, { push: false }); });

  // Canlı güncelleme: 15 sn'de bir hafif durum sorgusu; değişiklik varsa sayfa kullanıcıyı bozmadan yenilenir
  var LIVE_PAGES = ['dash', 'queue', 'changes', 'jobs', 'logs'];
  var liveOn = true, shown = null, newest = null, liveBtn = d.querySelector('[data-live]');
  try { liveOn = localStorage.getItem('tgw-live') !== 'off'; } catch (x) {}
  function liveUi(state) {
    if (!liveBtn) return;
    liveBtn.classList.toggle('off', !liveOn); liveBtn.classList.toggle('err', liveOn && state === 'err');
    liveBtn.setAttribute('aria-pressed', liveOn ? 'true' : 'false');
    liveBtn.title = liveOn ? (state === 'err' ? 'Bağlantı yok, tekrar denenecek' : 'Canlı güncelleme: açık') : 'Canlı güncelleme: kapalı';
    liveBtn.querySelector('span').textContent = liveOn ? (state === 'err' ? 'Çevrimdışı' : 'Canlı') : 'Duraklatıldı';
  }
  function userBusy() { // yazı yazarken, seçim yaparken ya da bir pencere açıkken sayfayı yenileme
    var ae = d.activeElement, tag = ae && ae.tagName ? ae.tagName.toLowerCase() : '';
    if (d.hidden || d.querySelector('dialog[open]')) return true;
    if (mainEl.contains(ae) && (tag === 'input' || tag === 'textarea' || tag === 'select')) return true;
    return mainEl.querySelector('details[open], input[type=checkbox]:checked') !== null;
  }
  function poll() {
    if (!liveOn || d.hidden) return;
    fetch('?p=live', { credentials: 'same-origin', headers: { 'X-Requested-With': 'fetch' } })
      .then(function (r) { if (!/json/.test(r.headers.get('content-type') || '')) { location.reload(); throw 0; } return r.json(); })
      .then(function (s) {
        d.body.setAttribute('data-queue', String(s.queue)); titleCount(s.queue); setSecBadge('work', s.queue + s.changes + s.unparsed); setSubBadge('queue', s.queue); setSubBadge('changes', s.changes); setSubBadge('unparsed', s.unparsed);
        if (shown === null) { shown = s.sig; newest = s.newest; liveUi('ok'); return; }
        if (s.newest > newest) { toast('Yeni servis duyurusu geldi', 'ok'); newest = s.newest; }
        var p = new URL(location.href).searchParams.get('p') || 'dash';
        if (s.sig !== shown) cache = {};
        if (s.sig !== shown && LIVE_PAGES.indexOf(p) >= 0 && !userBusy()) { shown = s.sig; load(location.href, { live: true, push: false }); }
        else if (s.sig !== shown && LIVE_PAGES.indexOf(p) < 0) shown = s.sig;
        liveUi('ok');
      })
      .catch(function (x) { if (x !== 0) liveUi('err'); });
  }
  if (liveBtn) {
    liveBtn.addEventListener('click', function () { liveOn = !liveOn; try { localStorage.setItem('tgw-live', liveOn ? 'on' : 'off'); } catch (x) {} liveUi('ok'); if (liveOn) poll(); });
    liveUi('ok'); setInterval(poll, 15000); setTimeout(poll, 1200);
    d.addEventListener('visibilitychange', function () { if (!d.hidden) poll(); });
  }
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
.row{display:flex;gap:8px;flex-wrap:wrap;align-items:center}.row.center{justify-content:center}.row.between{justify-content:space-between;width:100%}.row.nowrap{flex-wrap:nowrap}
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
.kv{display:grid;grid-template-columns:max-content 1fr;gap:8px 22px;margin:0}.kv dt{color:var(--text-2)}.kv dd{margin:0;font-weight:500;min-width:0;overflow-wrap:anywhere}
details>summary{cursor:pointer;color:var(--accent-text);font-weight:500;list-style:none;display:flex;align-items:center;gap:6px}
details>summary::before{content:"";width:7px;height:7px;border-right:2px solid currentColor;border-bottom:2px solid currentColor;transform:rotate(-45deg);transition:transform .2s;margin-right:2px}
details[open]>summary::before{transform:rotate(45deg)}
details>summary::-webkit-details-marker{display:none}
details[open]>summary{margin-bottom:12px}

/* ===== Butonlar (kapsül) ===== */
.btn{display:inline-flex;align-items:center;justify-content:center;gap:7px;min-height:36px;padding:7px 16px;border-radius:999px;border:0;
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
.btn.sm{min-height:32px;padding:5px 12px;font-size:13px}
.btn.done{color:var(--good)}
form.inline{display:inline}

/* ===== Form ===== */
input[type=text],input[type=password],input[type=number],input[type=search],input[type=url],input[type=email],select,textarea{
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
.lvl-error{color:var(--bad);font-weight:500}.lvl-good{color:var(--ok);font-weight:500}.lvl-warn{color:var(--warn);font-weight:500}.lvl-info{color:var(--text-2)}
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
.table-wrap{position:relative;overflow-x:auto;background:var(--surface);border-radius:var(--r-l);box-shadow:var(--shadow);border:1px solid var(--sep-2)}
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
input[type=file]{font:inherit;font-size:14px;color:var(--text-2);max-width:100%}
input[type=file]::file-selector-button{font:inherit;font-weight:500;background:var(--fill-2);color:var(--text);border:0;border-radius:999px;padding:7px 14px;margin-right:10px;cursor:pointer}
/* ===== Ayarlar, sekmeler, alt bilgi ===== */
.tabs{display:flex;gap:4px;overflow-x:auto;padding:4px;margin:0 0 18px;background:var(--fill-2);border-radius:var(--r-m);width:fit-content;max-width:100%;scrollbar-width:none}
.tabs::-webkit-scrollbar{display:none}
.tabs a{flex:none;padding:7px 14px;border-radius:9px;color:var(--text-2);font-weight:500;font-size:14px;min-height:34px;display:flex;align-items:center}
.tabs a:hover{text-decoration:none;color:var(--text)}
.tabs a[aria-current="page"]{background:var(--surface);color:var(--text);box-shadow:0 1px 3px rgba(0,0,0,.12)}
.settings{padding:0;overflow:hidden}
.setting{display:grid;grid-template-columns:minmax(0,5fr) minmax(0,6fr);gap:12px 28px;padding:16px 20px;border-top:1px solid var(--sep-2);align-items:start}
.setting:first-child{border-top:0}
.setting-l label{font-weight:600;color:var(--text)}
.setting-l p{margin:2px 0 0;font-size:13.5px}
.setting-l .src{font-size:12px;color:var(--text-3);display:inline-block;margin-top:4px}
.setting-r{display:flex;flex-direction:column;gap:8px;min-width:0}
.setting-r input:not([type=checkbox]),.setting-r select{width:100%}
.check.sm{font-size:13.5px;color:var(--text-2)}
.grid-2{display:grid;grid-template-columns:repeat(2,minmax(0,1fr));gap:12px 16px}
.foot{display:flex;gap:18px;flex-wrap:wrap;margin-top:44px;padding-top:16px;border-top:1px solid var(--sep-2);font-size:13px;color:var(--text-3)}
.foot a{color:var(--text-3)}.foot a:hover{color:var(--accent-text)}
.flash{position:relative;transition:opacity .4s,transform .4s}.flash.gone{opacity:0;transform:translateY(-6px)}
.flash-x{margin-left:auto;background:none;border:0;color:var(--text-2);font-size:20px;line-height:1;cursor:pointer;padding:0 4px;border-radius:6px}
.flash-x:hover{color:var(--text)}
.trunc{display:block;max-width:260px;overflow:hidden;text-overflow:ellipsis;white-space:nowrap}
.chan-extra{margin-top:6px}.chan-extra summary{font-size:13px}.chan-extra form{margin-top:8px;display:grid;gap:8px;min-width:240px}
/* Yoğunluk: sıkı */
[data-density="compact"] body{font-size:14px}
[data-density="compact"] th,[data-density="compact"] td{padding:7px 12px}
[data-density="compact"] .card,[data-density="compact"] .panel-pad{padding:13px 15px}
[data-density="compact"] h2{margin:26px 0 9px}
[data-density="compact"] .setting{padding:11px 16px}
@media (max-width:760px){.setting,.grid-2{grid-template-columns:1fr}.tabs{width:100%}}

/* ===== Dinamik: hareket, canlı durum, bildirim yığını ===== */
@keyframes rise{from{opacity:0;transform:translateY(12px)}to{opacity:1;transform:none}}
@keyframes growy{from{transform:scaleY(0)}to{transform:scaleY(1)}}
@keyframes growx{from{transform:scaleX(0)}to{transform:scaleX(1)}}
@keyframes ripple{0%{box-shadow:0 0 0 0 color-mix(in srgb,var(--good) 55%,transparent)}100%{box-shadow:0 0 0 9px transparent}}
@keyframes toast-in{from{opacity:0;transform:translateY(-10px) scale(.97)}to{opacity:1;transform:none}}
@keyframes bump{0%{transform:scale(1)}40%{transform:scale(1.35)}100%{transform:scale(1)}}
.main:not(.quiet)>*{animation:rise .28s cubic-bezier(.2,.8,.2,1) both}
.main:not(.quiet)>*:nth-child(2){animation-delay:.04s}.main:not(.quiet)>*:nth-child(3){animation-delay:.08s}.main:not(.quiet)>*:nth-child(4){animation-delay:.12s}
.main:not(.quiet) .dash>*{animation:rise .32s cubic-bezier(.2,.8,.2,1) both}
.main:not(.quiet) .dash>*:nth-child(2){animation-delay:.03s}.main:not(.quiet) .dash>*:nth-child(3){animation-delay:.06s}.main:not(.quiet) .dash>*:nth-child(4){animation-delay:.09s}
.main:not(.quiet) .dash>*:nth-child(5){animation-delay:.12s}.main:not(.quiet) .dash>*:nth-child(6){animation-delay:.14s}.main:not(.quiet) .dash>*:nth-child(7){animation-delay:.16s}.main:not(.quiet) .dash>*:nth-child(n+8){animation-delay:.18s}
.main:not(.quiet) .chart .bar rect.m{transform-box:fill-box;transform-origin:50% 100%;animation:growy .7s cubic-bezier(.2,.8,.2,1) .25s both}
.main:not(.quiet) .meter .fill,.main:not(.quiet) .mix rect{transform-box:fill-box;transform-origin:0 50%;animation:growx .9s cubic-bezier(.2,.8,.2,1) .25s both}
.card,.qcard{transition:transform .2s cubic-bezier(.2,.8,.2,1),box-shadow .2s}
.card:hover,.qcard:hover{transform:translateY(-2px);box-shadow:0 2px 4px rgba(0,0,0,.05),0 14px 30px rgba(0,0,0,.09)}
.btn{transition:transform .12s,background-color .15s,opacity .15s}.btn:active{transform:scale(.96)}
.btn.busy{opacity:.55;pointer-events:none}
.nav a{transition:background-color .15s,color .15s}
.count.bump,.tab-badge.bump{animation:bump .5s}
::view-transition-old(root),::view-transition-new(root){animation-duration:.2s}
.loadbar{position:fixed;top:0;left:0;height:3px;width:0;background:var(--accent);z-index:90;opacity:0;border-radius:0 3px 3px 0;pointer-events:none}
.loadbar.on{opacity:1;width:82%;transition:width 8s cubic-bezier(.1,.7,.2,1)}
.loadbar.done{width:100%;opacity:0;transition:width .2s,opacity .45s .2s}
#toasts{position:fixed;z-index:80;top:14px;right:14px;display:flex;flex-direction:column;gap:8px;width:min(400px,calc(100vw - 28px));pointer-events:none}
#toasts .flash{pointer-events:auto;margin:0;box-shadow:var(--shadow-pop);animation:toast-in .4s cubic-bezier(.2,.8,.2,1)}
.live{display:inline-flex;align-items:center;gap:7px;margin-left:auto;margin-right:10px;padding:5px 11px;border:0;border-radius:999px;background:var(--fill-2);color:var(--text-2);font:500 12.5px/1 var(--font);cursor:pointer;white-space:nowrap}
.live:hover{color:var(--text)}.toolbar .live+.search{margin-left:0}
.live .pulse{width:8px;height:8px;border-radius:50%;background:var(--good);animation:ripple 2s infinite}
.live.off .pulse{background:var(--text-3);animation:none}.live.err .pulse{background:var(--bad);animation:none}
@media (prefers-reduced-motion:reduce){*,*::before,*::after{animation:none!important;transition:none!important}}
@media (max-width:760px){.live span{display:none}.live{margin-right:6px}#toasts{top:auto;bottom:calc(90px + env(safe-area-inset-bottom));right:14px}}

/* ===== Dikkat gerekenler, yardım penceresi, tablo etkileşimi ===== */
.attn{margin:0 0 22px}
.attn-grid{display:grid;grid-template-columns:repeat(auto-fill,minmax(320px,1fr));gap:10px}
.attn-item{display:flex;align-items:center;gap:11px;padding:12px 14px;border-radius:var(--r-m);background:var(--surface);border:1px solid var(--sep-2);box-shadow:var(--shadow);color:var(--text);position:relative;overflow:hidden;transition:transform .18s,box-shadow .18s}
.attn-item::before{content:"";position:absolute;left:0;top:0;bottom:0;width:4px;background:var(--warn)}
.attn-item.bad::before{background:var(--bad)}.attn-item.info::before{background:var(--accent)}
.attn-item .ic{flex:none;color:var(--warn)}.attn-item.bad .ic{color:var(--bad)}.attn-item.info .ic{color:var(--accent-text)}
.attn-item span{flex:1;font-size:14px;line-height:1.35}
.attn-item b{flex:none;font-size:13px;color:var(--accent-text);font-weight:600;white-space:nowrap}
.attn-item:hover{text-decoration:none;transform:translateY(-1px);box-shadow:0 8px 22px rgba(0,0,0,.09)}
.help-in{padding:22px 24px;min-width:min(420px,86vw)}
.help-in h3{margin:0 0 14px;font-size:17px}
.help dl{display:grid;grid-template-columns:auto 1fr;gap:9px 18px;margin:0 0 18px;align-items:center}
.help dt{white-space:nowrap}.help dd{margin:0;color:var(--text-2);font-size:14px}
tbody tr{transition:background-color .12s}
tbody tr:hover>td{background:color-mix(in srgb,var(--accent) 5%,transparent)}
.edit-table tbody tr:hover>td{background:transparent}
.foot kbd{margin-left:4px}

.cmd-name{width:160px;min-width:130px;flex:none}
.edit-table td select{min-width:170px}

/* ===== Mobil ve dokunmatik uyum: tablo kartları, dokunma hedefleri, taşmalar ===== */
@media (max-width:760px){
  .table-wrap.cards{overflow:visible;background:transparent;border:0;box-shadow:none;border-radius:0}
  .table-wrap.cards table,.table-wrap.cards tbody,.table-wrap.cards tr,.table-wrap.cards td{display:block;width:100%}
  .table-wrap.cards thead{display:none}
  .table-wrap.cards tr{background:var(--surface);border:1px solid var(--sep-2);border-radius:var(--r-m);box-shadow:var(--shadow);padding:4px 14px;margin:0 0 10px}
  .table-wrap.cards tr{position:relative}
  .table-wrap.cards td{display:block;border:0;border-top:1px solid var(--sep-2);padding:9px 0;width:auto!important;white-space:normal!important;text-align:left;min-width:0;overflow-wrap:anywhere}
  .table-wrap.cards td[data-label]{display:grid;grid-template-columns:minmax(72px,34%) minmax(0,1fr);column-gap:12px;align-items:start}
  .table-wrap.cards td[data-label]::before{content:attr(data-label);grid-column:1;color:var(--text-2);font-size:12.5px;font-weight:500;padding-top:2px}
  .table-wrap.cards td[data-label]>*{grid-column:2}
  .table-wrap.cards td:first-child{border-top:0}
  .table-wrap.cards td:has(>input[type=checkbox]){position:absolute;top:8px;right:12px;padding:0;border:0;width:auto!important;z-index:1}
  .table-wrap.cards td:has(>input[type=checkbox])+td{border-top:0}
  .table-wrap.cards td.num{text-align:right}
  .table-wrap.cards td .row{flex-wrap:wrap}
  .table-wrap.cards .bulk{background:var(--surface);border:1px solid var(--sep-2);border-radius:var(--r-m);padding:12px 14px;margin-top:4px}
  .table-wrap.cards td form.row,.table-wrap.cards td form.field-row{width:100%}
  .kv{grid-template-columns:1fr;gap:2px 0}.kv dt{font-size:12.5px;margin-top:8px}
  .strip{grid-template-columns:repeat(2,minmax(0,1fr))}
  .page-title .sub{overflow-wrap:anywhere}
  .code pre,pre{overflow-wrap:anywhere}
  .search kbd{display:none!important}.js .search kbd.js-only{display:none!important}
  .live{width:34px;height:34px;padding:0;justify-content:center;margin-right:6px}
  .toolbar .live+.search{margin-left:0}
  .chips{max-width:100%}
  .setting-r .check.sm{padding:4px 0}
  .attn-grid{grid-template-columns:1fr}
}
@media (pointer:coarse),(max-width:760px){
  .btn{min-height:44px;padding:9px 18px}.btn.sm{min-height:40px;padding:7px 15px}
  input[type=text],input[type=password],input[type=number],input[type=search],input[type=url],input[type=email],select,textarea{min-height:44px;font-size:16px}
  input[type=checkbox],input[type=radio]{width:22px;height:22px}
  input[type=file]{min-height:44px;padding-top:9px}
  .chip{padding:10px 14px}
  .nav a{min-height:44px}.tabs a{min-height:42px}
  .icon-btn{min-width:44px;min-height:44px}
  .check{min-height:36px}
}

.panel-pad>p:first-child,.card>p:first-child{margin-top:0}.panel-pad>p:last-child,.card>p:last-child{margin-bottom:0}
.panel-pad.stack>p{margin:0}

@media (max-width:760px){
  .edit-table.labeled td[data-label]::before{content:attr(data-label);display:block;font-size:12.5px;font-weight:500;color:var(--text-2);margin-bottom:3px}
  .edit-table.labeled td{border:0;padding:2px 0}
  .edit-table.labeled td.tight .row{flex-wrap:nowrap}
  .edit-table.labeled tr{display:grid;grid-template-columns:auto 1fr;gap:4px 14px;align-items:end}
  .edit-table.labeled td:nth-child(n+3){grid-column:1/-1}
  .cmd-name{width:100%;min-width:0;flex:1}
}

.open-banner{display:flex;gap:10px;align-items:flex-start;padding:12px 16px;margin:0 0 18px;border-radius:var(--r-m);background:var(--bad-soft);border:1px solid color-mix(in srgb,var(--bad) 35%,transparent);color:var(--text);font-size:14px;line-height:1.45}
.open-banner .ic{color:var(--bad);flex:none;margin-top:2px}

.login-ver{text-align:center;color:var(--text-3);font-size:12px;margin:18px 0 0}

.main.loading{opacity:.55;transition:opacity .2s;pointer-events:none}
.open-banner.slim{padding:8px 14px;font-size:13px;margin-bottom:14px}

/* ===== iOS cam görünümü (sade menü, yarı saydam kartlar, yumuşak arka plan) ===== */
:root{
  --r-xl:26px;--r-l:22px;--r-m:16px;--r-s:12px;
  --bg:#eef1f7;--text-2:#5c5c63;
  --mesh-1:#c9ddff;--mesh-2:#ecd5ff;--mesh-3:#cff3e6;
  --g-card:rgba(255,255,255,.64);--g-strong:rgba(255,255,255,.80);--g-line:rgba(255,255,255,.78);--g-input:rgba(255,255,255,.72);--g-fill:rgba(110,120,150,.13);
  --g-shadow:0 10px 34px rgba(40,60,120,.10),0 1px 0 rgba(255,255,255,.7) inset;
}
@media (prefers-color-scheme:dark){:root{
  --bg:#080a14;--text-2:#a1a1a6;
  --mesh-1:#1b3b8f;--mesh-2:#4b1f7d;--mesh-3:#0f5a56;
  --g-card:rgba(30,33,46,.58);--g-strong:rgba(42,46,62,.78);--g-line:rgba(255,255,255,.10);--g-input:rgba(255,255,255,.07);--g-fill:rgba(160,170,200,.16);
  --g-shadow:0 12px 36px rgba(0,0,0,.45),0 1px 0 rgba(255,255,255,.06) inset;
}}
body.app{background:var(--bg)}
body.app::before{content:"";position:fixed;inset:0;z-index:-1;pointer-events:none;opacity:.8;
  background:radial-gradient(60vw 52vh at 10% 6%,var(--mesh-1),transparent 70%),radial-gradient(52vw 50vh at 94% 10%,var(--mesh-2),transparent 70%),radial-gradient(64vw 60vh at 68% 104%,var(--mesh-3),transparent 70%)}
.card,.panel,.table-wrap,.empty,.qcard,.attn-item,.strip>div,.login-card,.help-in,.hero-card{
  background:var(--g-card);border:1px solid var(--g-line);box-shadow:var(--g-shadow);
  -webkit-backdrop-filter:blur(18px) saturate(1.5);backdrop-filter:blur(18px) saturate(1.5)}
.hero-card{background:linear-gradient(160deg,color-mix(in srgb,var(--accent) 18%,transparent),transparent 72%),var(--g-card)}
.card,.panel,.table-wrap,.empty,.qcard,.hero-card{border-radius:var(--r-l)}
.table-wrap{overflow:hidden}.table-wrap.cards{overflow:visible}
.glass,.side,.toolbar,.tabbar,.cmd{background:var(--g-strong);border:1px solid var(--g-line);box-shadow:var(--g-shadow);-webkit-backdrop-filter:blur(26px) saturate(1.7);backdrop-filter:blur(26px) saturate(1.7)}
input[type=text],input[type=password],input[type=number],input[type=search],input[type=url],input[type=email],select,textarea{background:var(--g-input);border:1px solid var(--g-line);border-radius:14px}
input:focus,select:focus,textarea:focus{background:var(--g-strong)}
.btn{background:var(--g-fill)}.btn.primary{background:var(--accent)}.btn.danger{background:color-mix(in srgb,var(--bad) 14%,transparent)}.btn.ghost{background:transparent}
.btn.success{background:var(--good)}
.tabs{background:var(--g-fill)}.tabs a[aria-current="page"]{background:var(--g-strong);color:var(--text)}
.tabs .n{display:inline-block;min-width:18px;padding:0 5px;margin-left:4px;border-radius:9px;background:var(--accent);color:var(--on-accent);font-size:11px;font-weight:600;line-height:18px;text-align:center}
.subnav{margin:-6px 0 20px}
/* menü: yalnızca 6 bölüm */
.side{padding:16px 12px 12px;border-radius:var(--r-xl)}
.brand small{display:none}.brand{padding:2px 10px 18px}
.nav{display:grid;gap:4px}
.nav a{min-height:44px;padding:9px 14px;border-radius:15px;font-size:15px;gap:12px}
.nav a[aria-current="page"]{background:color-mix(in srgb,var(--accent) 16%,transparent);color:var(--accent-text);font-weight:600}
.nav a[aria-current="page"] .ic{color:var(--accent-text)}
.nav a .ic{color:var(--text-2)}
.side-foot{border-top:1px solid var(--g-line)}
/* üst çubuk: arama + tek nokta */
.toolbar{border-radius:20px;height:56px}
.live{width:30px;height:30px;padding:0;justify-content:center;margin-right:8px;background:transparent}
.live span{display:none}
.page-head h1,h1{font-size:30px;letter-spacing:-.025em}
/* mobil alt çubuk: 6 bölüm */
@media (max-width:900px){
  .tabbar{border-radius:26px}
  .tabbar a{font-size:10.5px;min-width:0;padding:0 2px;border-radius:20px}
  .table-wrap.cards tr{background:var(--g-card);border:1px solid var(--g-line);-webkit-backdrop-filter:none;backdrop-filter:none}
  .card,.panel,.attn-item,.strip>div,.qcard,.hero-card{-webkit-backdrop-filter:blur(14px) saturate(1.4);backdrop-filter:blur(14px) saturate(1.4)}
}
.flash{backdrop-filter:blur(14px)}
#toasts .flash{background:var(--g-strong)}
/* erişilebilirlik: saydamlık azaltılırsa düz yüzey */
@media (prefers-reduced-transparency:reduce){
  .card,.panel,.table-wrap,.empty,.qcard,.attn-item,.strip>div,.login-card,.help-in,.hero-card,.glass,.side,.toolbar,.tabbar,.cmd{background:var(--surface);-webkit-backdrop-filter:none;backdrop-filter:none}
  body.app::before{display:none}
}
@supports not ((backdrop-filter:blur(1px)) or (-webkit-backdrop-filter:blur(1px))){
  .card,.panel,.table-wrap,.empty,.qcard,.attn-item,.strip>div,.login-card,.help-in,.hero-card,.glass,.side,.toolbar,.tabbar,.cmd{background:var(--surface)}
}
@media (prefers-contrast:more){:root{--g-card:var(--surface);--g-strong:var(--surface)}body.app::before{display:none}}

.chk-grid{display:grid;grid-template-columns:repeat(auto-fill,minmax(260px,1fr));gap:8px 18px;margin-top:8px}

/* ===== Profil menüsü ===== */
.profile-menu{position:relative;flex:none;margin-left:8px}
.avatar{display:inline-flex;align-items:center;justify-content:center;width:36px;height:36px;border-radius:50%;background:linear-gradient(145deg,var(--accent),color-mix(in srgb,var(--accent) 55%,#7b3fd4));color:var(--on-accent);font-weight:700;font-size:13px;letter-spacing:.02em;cursor:pointer;list-style:none;user-select:none;box-shadow:0 2px 8px rgba(0,0,0,.18)}
.profile-menu>summary::-webkit-details-marker{display:none}
.avatar.big{width:44px;height:44px;font-size:16px;cursor:default;flex:none}
.pm-panel{position:absolute;right:0;top:calc(100% + 10px);width:260px;padding:10px;border-radius:20px;z-index:50;display:grid;gap:2px;box-shadow:var(--shadow-pop)}
.pm-head{display:flex;gap:12px;align-items:center;padding:6px 8px 12px;border-bottom:1px solid var(--g-line);margin-bottom:6px}
.pm-head b{display:block;font-size:15px}.pm-head small{color:var(--text-2);font-size:12.5px}
.pm-panel a,.pm-out{display:flex;align-items:center;gap:11px;min-height:42px;padding:8px 10px;border-radius:12px;color:var(--text);font:500 14.5px var(--font);text-decoration:none;border:0;background:none;width:100%;cursor:pointer;text-align:left}
.pm-panel a:hover,.pm-out:hover{background:var(--g-fill);text-decoration:none}
.pm-panel .ic{color:var(--text-2)}
.pm-out{color:var(--bad)}.pm-out .ic{color:var(--bad)}
.pm-panel form{margin:4px 0 0;border-top:1px solid var(--g-line);padding-top:4px}
.profile-head{display:flex;gap:18px;align-items:center;flex-wrap:wrap}
.profile-head .avatar.big{width:72px;height:72px;font-size:26px}
.kv-list{display:grid;grid-template-columns:minmax(120px,max-content) 1fr;gap:10px 22px;margin:0}
.kv-list dt{color:var(--text-2)}.kv-list dd{margin:0;font-weight:500;min-width:0;overflow-wrap:anywhere}
@media (max-width:760px){.pm-panel{position:fixed;right:12px;left:12px;top:72px;width:auto}.kv-list{grid-template-columns:1fr;gap:2px}.kv-list dt{margin-top:8px;font-size:12.5px}}

.nomargin{margin:0!important}

.inline-warn{display:flex;gap:10px;align-items:center;padding:10px 14px;border-radius:var(--r-m);background:var(--warn-soft);color:var(--text);font-size:14px}.inline-warn .ic{color:var(--warn);flex:none}

.pm-look{display:flex;align-items:center;gap:6px;padding:6px 10px;flex-wrap:wrap}.pm-look span{flex:1 0 100%;font-size:12.5px;color:var(--text-2)}
.pm-look button{flex:1;min-height:36px;border:1px solid var(--g-line,var(--rule-strong));border-radius:10px;background:none;color:var(--text);font:600 13.5px var(--font);cursor:pointer}
.pm-look button[aria-pressed="true"]{background:var(--text);color:var(--surface);border-color:var(--text)}
.theme-btn{font-size:18px;line-height:1}
.profile-menu>summary::before,.profile-menu>summary::after{content:none!important;display:none}.profile-menu>summary::marker{content:""}

.pm-panel.glass{background:color-mix(in srgb,var(--surface) 94%,transparent)}

/* ===== BUTA: kervansaray defteri (nila · rövənd · zəfəran, Zilla Slab, dokuma şerit) ===== */
@font-face{font-family:'Zilla Slab';font-weight:500;font-display:swap;src:url(assets/fonts/zilla-slab-latin-ext-500.woff2) format('woff2');unicode-range:U+0100-02BA,U+02BD-02C5,U+02C7-02CC,U+02CE-02D7,U+02DD-02FF,U+0304,U+0308,U+0329,U+1D00-1DBF,U+1E00-1E9F,U+1EF2-1EFF,U+2020,U+20A0-20AB,U+20AD-20C0,U+2113,U+2C60-2C7F,U+A720-A7FF}
@font-face{font-family:'Zilla Slab';font-weight:500;font-display:swap;src:url(assets/fonts/zilla-slab-latin-500.woff2) format('woff2');unicode-range:U+0000-00FF,U+0131,U+0152-0153,U+02BB-02BC,U+02C6,U+02DA,U+02DC,U+2000-206F,U+20AC,U+2122,U+2191,U+2193,U+2212,U+2215,U+FEFF,U+FFFD}
@font-face{font-family:'Zilla Slab';font-weight:700;font-display:swap;src:url(assets/fonts/zilla-slab-latin-ext-700.woff2) format('woff2');unicode-range:U+0100-02BA,U+02BD-02C5,U+02C7-02CC,U+02CE-02D7,U+02DD-02FF,U+0304,U+0308,U+0329,U+1D00-1DBF,U+1E00-1E9F,U+1EF2-1EFF,U+2020,U+20A0-20AB,U+20AD-20C0,U+2113,U+2C60-2C7F,U+A720-A7FF}
@font-face{font-family:'Zilla Slab';font-weight:700;font-display:swap;src:url(assets/fonts/zilla-slab-latin-700.woff2) format('woff2');unicode-range:U+0000-00FF,U+0131,U+0152-0153,U+02BB-02BC,U+02C6,U+02DA,U+02DC,U+2000-206F,U+20AC,U+2122,U+2191,U+2193,U+2212,U+2215,U+FEFF,U+FFFD}
html[data-style="buta"]{
  --display:'Zilla Slab','Rockwell','Roboto Slab',Georgia,serif;
  --nila:#243B6B;--rovend:#B3362F;--zefer:#D9A21B;--zeytun:#4A6B35;
  --bg:#F1F2EE;--surface:#FBFBF9;--surface-2:#E9EBE5;--fill:#E3E5DE;--fill-2:#ECEEE8;
  --text:#232120;--text-2:#55524D;--text-3:#7A766F;
  --sep:rgba(35,33,32,.30);--sep-2:rgba(35,33,32,.16);--rule:rgba(35,33,32,.16);--rule-strong:rgba(35,33,32,.42);
  --accent:#243B6B;--accent-text:#243B6B;--on-accent:#F8F4E8;--accent-soft:#DCE2F1;--accent-hover:#34508F;
  --good:#3F6A2E;--good-soft:#E1EAD5;--warn:#7A5600;--warn-soft:#F6E6B8;--bad:#A52D27;--bad-soft:#F3D9D5;
  --s1:#2F4A8F;--s2:#B3362F;--s3:#D9A21B;--s4:#4A6B35;--s5:#7A3F6B;
  --rail:#243B6B;--rail-ink:rgba(248,244,232,.72);
  --glass:var(--surface);--g-card:var(--surface);--g-strong:var(--surface);--g-line:var(--rule);--g-input:var(--surface);--g-fill:var(--fill-2);
  --shadow:none;--shadow-pop:0 18px 44px rgba(20,24,40,.22),0 2px 6px rgba(20,24,40,.12);--g-shadow:none;
  --r-xl:8px;--r-l:6px;--r-m:6px;--r-s:4px;
  --weave:url("data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' width='40' height='10' viewBox='0 0 40 10' shape-rendering='crispEdges'%3E%3Crect width='40' height='10' fill='%23243B6B'/%3E%3Cpath d='M10 1L14 5L10 9L6 5z' fill='%23B3362F'/%3E%3Cpath d='M30 1L34 5L30 9L26 5z' fill='%23D9A21B'/%3E%3Crect x='19' y='4' width='2' height='2' fill='%23F4EFE2'/%3E%3Crect width='40' height='1' fill='%23F4EFE2' fill-opacity='.35'/%3E%3Crect y='9' width='40' height='1' fill='%23F4EFE2' fill-opacity='.35'/%3E%3C/svg%3E");
}
@media (prefers-color-scheme:dark){html[data-style="buta"]{
  --bg:#121A33;--surface:#1A2445;--surface-2:#222E55;--fill:#26325C;--fill-2:#1F2A4E;
  --text:#EEE8DA;--text-2:#B4B9CC;--text-3:#8D93AB;
  --sep:rgba(238,232,218,.30);--sep-2:rgba(238,232,218,.16);--rule:rgba(238,232,218,.16);--rule-strong:rgba(238,232,218,.42);
  --accent:#E0AB2B;--accent-text:#EBC360;--on-accent:#1B1A14;--accent-soft:#2B3763;--accent-hover:#F0BE45;
  --good:#8DBF73;--good-soft:#22331F;--warn:#EBC360;--warn-soft:#3A3014;--bad:#F08B83;--bad-soft:#3F1D1D;
  --s1:#7F9AE0;--s2:#E0645C;--s3:#E0AB2B;--s4:#8DBF73;--s5:#C98AB8;
  --rail:#0C1226;--rail-ink:rgba(238,232,218,.66);
  color-scheme:dark;
}}
html[data-style="buta"] body.app{background:var(--bg);color:var(--text)}
html[data-style="buta"] body.app::before{display:none}
html[data-style="buta"] h1,html[data-style="buta"] h2,html[data-style="buta"] h3,html[data-style="buta"] .brand,html[data-style="buta"] .hero-num,html[data-style="buta"] .tile-value,html[data-style="buta"] .brief,html[data-style="buta"] .qcard .az,html[data-style="buta"] .pair .az{font-family:var(--display)}
html[data-style="buta"] h1{font-size:40px;line-height:1.05;font-weight:700;letter-spacing:-.012em}
html[data-style="buta"] h2{font-size:23px;font-weight:700;letter-spacing:-.005em;margin:40px 0 14px}
html[data-style="buta"] h3{font-size:18px;font-weight:700}
html[data-style="buta"] :focus-visible{outline:3px solid var(--zefer);outline-offset:2px;border-radius:4px}
/* şerit menü */
html[data-style="buta"] .side{left:0;top:0;bottom:0;width:84px;border-radius:0;border:0;background:var(--rail);box-shadow:none;-webkit-backdrop-filter:none;backdrop-filter:none;padding:12px 0;align-items:center;overflow:visible}
html[data-style="buta"] .shell{margin-left:84px}
html[data-style="buta"] .brand{padding:4px 0 16px;justify-content:center}
html[data-style="buta"] .brand>span:not(.mark),html[data-style="buta"] .close-menu{display:none}
html[data-style="buta"] .brand .mark{width:auto;height:auto;background:none;box-shadow:none;display:block}
html[data-style="buta"] .mark-svg .m1{fill:var(--m1,#243B6B)}html[data-style="buta"] .mark-svg .m2{fill:var(--m2,#B3362F)}html[data-style="buta"] .mark-svg .m3{fill:var(--m3,#D9A21B)}
html[data-style="buta"] .side .mark-svg,html[data-style="buta"] .tabbar .mark-svg{--m1:#D9A21B;--m2:#F4EFE2;--m3:#9DB2E8}
@media (prefers-color-scheme:dark){html[data-style="buta"] .mark-svg{--m1:#8FA6E8;--m2:#E0645C}}
html[data-style="buta"] .mark-svg.mono .m1,html[data-style="buta"] .mark-svg.mono .m2,html[data-style="buta"] .mark-svg.mono .m3{fill:currentColor}
html[data-style="buta"] .side .mark-svg{width:44px;height:44px}
html[data-style="buta"] .nav{gap:4px;width:100%;padding:0 8px;display:grid}
html[data-style="buta"] .nav a{flex-direction:column;justify-content:center;gap:5px;min-height:62px;padding:8px 2px;border-radius:8px;font-size:11.5px;font-weight:600;text-align:center;color:var(--rail-ink);position:relative;line-height:1.15}
html[data-style="buta"] .nav a .ic{color:inherit;opacity:.9;width:22px;height:22px}
html[data-style="buta"] .nav a:hover{background:rgba(255,255,255,.08);color:#fff}
html[data-style="buta"] .nav a[aria-current="page"]{background:rgba(255,255,255,.12);color:#fff}
html[data-style="buta"] .nav a[aria-current="page"]::before{content:"";position:absolute;left:-8px;top:10px;bottom:10px;width:4px;border-radius:0 3px 3px 0;background:var(--zefer)}
html[data-style="buta"] .nav a .count{position:absolute;top:5px;right:9px;margin:0;background:var(--zefer);color:#1B1A14;min-width:19px;padding:0 5px;font-size:11px}
html[data-style="buta"] .side-foot{border:0;padding:0 8px 4px;width:100%;margin-top:auto}
html[data-style="buta"] .side-status,html[data-style="buta"] .side-link{justify-content:center;padding:10px 0;color:var(--rail-ink);border-radius:8px}
html[data-style="buta"] .side-status span,html[data-style="buta"] .side-link span{display:none}
html[data-style="buta"] .side-status:hover,html[data-style="buta"] .side-link:hover{background:rgba(255,255,255,.08);color:#fff}
html[data-style="buta"] [data-toggle-side]{display:none!important}
/* dokuma şerit + üst çubuk */
html[data-style="buta"] .shell::before{content:"";display:block;height:10px;background:var(--weave) repeat-x 0 0/40px 10px}
html[data-style="buta"] .shell>.toolbar{position:sticky;top:0;margin:0;height:62px;border-radius:0;background:var(--bg);border:0;border-bottom:1px solid var(--rule);box-shadow:none;-webkit-backdrop-filter:none;backdrop-filter:none;padding:0 36px}
html[data-style="buta"] .search{background:var(--surface);border:1px solid var(--rule-strong);border-radius:6px}
html[data-style="buta"] .search input{background:transparent;border:0}
html[data-style="buta"] .live{background:transparent}
html[data-style="buta"] .avatar{border-radius:10px;background:var(--nila);color:#F8F4E8;box-shadow:none}
html[data-style="buta"] .main{max-width:1180px;padding:34px 36px 90px}
html[data-style="buta"] .page-head{margin:0 0 20px;padding:0}
html[data-style="buta"] .page-head::before,html[data-style="buta"] .with-aurora::before{display:none}
html[data-style="buta"] .page-head .sub{color:var(--text-2);font-size:16px;margin-top:6px}
/* alt sekmeler: defter sekmesi */
html[data-style="buta"] .tabs{background:none;border-bottom:1px solid var(--rule);border-radius:0;padding:0;gap:26px;width:100%;margin:0 0 26px}
html[data-style="buta"] .tabs a{padding:11px 0;border-radius:0;color:var(--text-2);font-weight:600;border-bottom:3px solid transparent;margin-bottom:-1px;min-height:0}
html[data-style="buta"] .tabs a:hover{color:var(--text);background:none}
html[data-style="buta"] .tabs a[aria-current="page"]{background:none;box-shadow:none;color:var(--text);border-bottom-color:var(--zefer)}
html[data-style="buta"] .tabs .n{background:var(--rovend);color:#fff;border-radius:4px}
html[data-style="buta"] .tabs:not(.subnav){border-bottom:0;gap:8px;flex-wrap:wrap;margin:0 0 20px}
html[data-style="buta"] .tabs:not(.subnav) a{border:1.5px solid var(--rule-strong);border-radius:6px;padding:6px 13px;margin:0;font-size:14px}
html[data-style="buta"] .tabs:not(.subnav) a[aria-current="page"]{background:var(--text);color:var(--surface);border-color:var(--text)}
/* bölümler: kutu değil, defter başlığı (üstte kalın çizgi) */
html[data-style="buta"] .card,html[data-style="buta"] .strip>div{background:transparent;border:0;border-top:2px solid var(--text);border-radius:0;box-shadow:none;-webkit-backdrop-filter:none;backdrop-filter:none;padding:16px 2px 6px}
html[data-style="buta"] .card:hover,html[data-style="buta"] .qcard:hover{transform:none;box-shadow:none}
html[data-style="buta"] .card-h h3{font-size:19px}
html[data-style="buta"] .panel,html[data-style="buta"] .table-wrap,html[data-style="buta"] .empty,html[data-style="buta"] .login-card,html[data-style="buta"] .help-in{background:var(--surface);border:1px solid var(--rule-strong);border-radius:6px;box-shadow:none;-webkit-backdrop-filter:none;backdrop-filter:none}
html[data-style="buta"] .table-wrap{overflow:hidden}html[data-style="buta"] .table-wrap.cards{overflow:visible;background:transparent;border:0}
html[data-style="buta"] th{font-size:12.5px;font-weight:700;color:var(--text-2);border-bottom:2px solid var(--text);background:transparent}
html[data-style="buta"] td{border-top:1px solid var(--rule)}
html[data-style="buta"] td.num,html[data-style="buta"] th.num,html[data-style="buta"] .tile-value,html[data-style="buta"] .hero-num{font-variant-numeric:lining-nums tabular-nums}
html[data-style="buta"] tbody tr:hover>td{background:color-mix(in srgb,var(--zefer) 10%,transparent)}
/* sıra defteri girdisi (onay kuyruğu) */
html[data-style="buta"] .qcard{background:var(--surface);border:1px solid var(--rule);border-left:6px solid var(--nila);border-radius:3px 6px 6px 3px;box-shadow:none;-webkit-backdrop-filter:none;backdrop-filter:none}
html[data-style="buta"] .qcard::before{display:none}
html[data-style="buta"] .attn-item{background:var(--surface);border:1px solid var(--rule);border-left:6px solid var(--zefer);border-radius:3px 6px 6px 3px;box-shadow:none;-webkit-backdrop-filter:none;backdrop-filter:none}
html[data-style="buta"] .attn-item.bad{border-left-color:var(--rovend)}
html[data-style="buta"] .attn-item::before{display:none}
html[data-style="buta"] .attn-item:hover{transform:none;box-shadow:none;background:var(--surface-2)}
/* Azerice ad = ürünün kendisi: slab serif */
html[data-style="buta"] .qcard .pair .az,html[data-style="buta"] .pair .az,html[data-style="buta"] .edit-table input[type=text]{font-family:var(--display);font-weight:700;font-size:19px;letter-spacing:0}
/* düğmeler: damga gibi dikdörtgen */
html[data-style="buta"] .btn{border-radius:6px;background:transparent;border:1.5px solid var(--text);color:var(--text);font-weight:700;min-height:38px;padding:7px 16px;transition:background-color .12s,color .12s}
html[data-style="buta"] .btn:hover{background:var(--text);color:var(--surface);text-decoration:none}
html[data-style="buta"] .btn:active{transform:none}
html[data-style="buta"] .btn.primary{background:var(--accent);border-color:var(--accent);color:var(--on-accent)}
html[data-style="buta"] .btn.primary:hover{background:var(--accent-hover);border-color:var(--accent-hover);color:var(--on-accent);box-shadow:inset 0 -3px 0 var(--zefer)}
html[data-style="buta"] .btn.danger{background:transparent;border-color:var(--bad);color:var(--bad)}
html[data-style="buta"] .btn.danger:hover{background:var(--bad);border-color:var(--bad);color:#fff}
html[data-style="buta"] .btn.success{background:var(--good);border-color:var(--good);color:#fff}
html[data-style="buta"] .btn.ghost{border-color:transparent;color:var(--accent-text)}
html[data-style="buta"] .btn.ghost:hover{background:var(--fill);color:var(--text)}
html[data-style="buta"] .btn.sm{min-height:32px;padding:4px 12px}
html[data-style="buta"] input[type=text],html[data-style="buta"] input[type=password],html[data-style="buta"] input[type=number],html[data-style="buta"] input[type=search],html[data-style="buta"] input[type=url],html[data-style="buta"] input[type=email],html[data-style="buta"] select,html[data-style="buta"] textarea{background:var(--surface);border:1.5px solid var(--rule-strong);border-radius:6px;color:var(--text)}
html[data-style="buta"] input:focus,html[data-style="buta"] select:focus,html[data-style="buta"] textarea:focus{background:var(--surface);border-color:var(--nila);box-shadow:0 0 0 3px color-mix(in srgb,var(--zefer) 45%,transparent)}
html[data-style="buta"] input[type=checkbox],html[data-style="buta"] input[type=radio]{accent-color:var(--nila)}
/* durum etiketleri: damga */
html[data-style="buta"] .badge,html[data-style="buta"] .via{font-family:var(--font);border-radius:3px;background:transparent!important;border:1.5px solid currentColor;font-weight:700;padding:1px 7px}
html[data-style="buta"] .chips{background:none;padding:0;gap:8px;border-radius:0}
html[data-style="buta"] .chip{border:1.5px solid var(--rule-strong);border-radius:6px;padding:5px 12px;background:transparent}
html[data-style="buta"] .chip:hover{background:var(--fill)}
html[data-style="buta"] .chip[aria-current="true"]{background:var(--text);color:var(--surface);border-color:var(--text);box-shadow:none}
html[data-style="buta"] .chip[aria-current="true"] .n{color:inherit;opacity:.8}
/* genel bakış: cümle + açık defter sütunları */
html[data-style="buta"] .hero-card{display:none}
html[data-style="buta"] .tiles{grid-column:span 12;grid-template-columns:repeat(4,minmax(0,1fr))}
html[data-style="buta"] .tile-value{font-size:46px;font-weight:700;line-height:1}
html[data-style="buta"] .tile-label{font-size:13.5px;color:var(--text-2)}
html[data-style="buta"] .brief{display:block;margin:0 0 30px;max-width:30em;font-size:clamp(25px,3.1vw,36px);line-height:1.22;font-weight:500;letter-spacing:-.005em}
html[data-style="buta"] .brief a{color:inherit;text-decoration:underline;text-decoration-color:var(--zefer);text-decoration-thickness:4px;text-underline-offset:5px}
html[data-style="buta"] .brief a:hover{background:color-mix(in srgb,var(--zefer) 28%,transparent)}
html[data-style="buta"] .brief .muted{font-size:.62em;display:block;margin-top:10px;font-family:var(--font);font-weight:400}
html[data-style="buta"] .attn{margin-bottom:26px}
/* bildirimler, menü, kutular */
html[data-style="buta"] #toasts .flash,html[data-style="buta"] .flash{background:var(--surface);border:1px solid var(--rule-strong);border-left:6px solid var(--zeytun);border-radius:4px;box-shadow:var(--shadow-pop);-webkit-backdrop-filter:none;backdrop-filter:none}
html[data-style="buta"] .flash.bad{border-left-color:var(--rovend);background:var(--surface)}
html[data-style="buta"] .flash .ic{color:var(--zeytun)}html[data-style="buta"] .flash.bad .ic{color:var(--rovend)}
html[data-style="buta"] .pm-panel.glass,html[data-style="buta"] .cmd,html[data-style="buta"] .tip{background:var(--surface);border:1px solid var(--rule-strong);border-radius:8px;-webkit-backdrop-filter:none;backdrop-filter:none}
html[data-style="buta"] .loadbar{background:var(--zefer);height:4px}
html[data-style="buta"] .open-banner{background:var(--bad-soft);border:1px solid var(--bad);border-radius:4px}
html[data-style="buta"] .inline-warn{border-left:5px solid var(--zefer);border-radius:3px}
html[data-style="buta"] .setting,html[data-style="buta"] .settings{border-color:var(--rule)}
html[data-style="buta"] .empty-ic{background:var(--accent-soft);color:var(--accent-text);border-radius:8px}
html[data-style="buta"] .foot{border-top:1px solid var(--rule)}
/* giriş ekranı: dokuma şerit + büyük logo */
html[data-style="buta"] .bare-main{max-width:460px;margin:8vh auto 0;padding:0 18px}
html[data-style="buta"] .login-card{overflow:hidden;position:relative;padding-top:18px}
html[data-style="buta"] .login-card::before{content:"";position:absolute;left:0;right:0;top:0;height:14px;background:var(--weave) repeat-x 0 0/56px 14px}
html[data-style="buta"] .login-card .body{padding-top:22px}
html[data-style="buta"] .login-mark{width:auto;height:auto;background:none;box-shadow:none;display:block}
html[data-style="buta"] .login-mark .mark-svg{width:58px;height:58px}
html[data-style="buta"] .login-card h1{font-size:44px}
html[data-style="buta"] .login-ver{color:var(--text-3)}
/* hareket: sayfa girişinde oynatma yok; yalnızca grafik çubukları */
html[data-style="buta"] .main:not(.quiet)>*,html[data-style="buta"] .main:not(.quiet) .dash>*{animation:none}
html[data-style="buta"] .btn:active,html[data-style="buta"] .count.bump{animation:none}
/* mobil: şerit menü alt çubuğa dönüşür */
@media (max-width:900px){
  html[data-style="buta"] .shell{margin-left:0}
  html[data-style="buta"] .shell>.toolbar{padding:0 14px;height:58px}
  html[data-style="buta"] .main{padding:22px 16px calc(96px + env(safe-area-inset-bottom))}
  html[data-style="buta"] h1{font-size:33px}
  html[data-style="buta"] .tabbar{left:0;right:0;bottom:0;border-radius:0;background:var(--rail);border:0;box-shadow:0 -1px 0 rgba(255,255,255,.08);padding:0 4px env(safe-area-inset-bottom);-webkit-backdrop-filter:none;backdrop-filter:none}
  html[data-style="buta"] .tabbar a{color:var(--rail-ink);border-radius:0;background:none;font-size:10.5px;position:relative;min-height:58px}
  html[data-style="buta"] .tabbar a[aria-current="page"]{color:#fff;background:none}
  html[data-style="buta"] .tabbar a[aria-current="page"]::before{content:"";position:absolute;left:18%;right:18%;top:0;height:4px;border-radius:0 0 3px 3px;background:var(--zefer)}
  html[data-style="buta"] .tab-badge{background:var(--zefer);color:#1B1A14}
  html[data-style="buta"] .tiles{grid-template-columns:repeat(2,minmax(0,1fr))}
  html[data-style="buta"] .tabs{gap:20px;overflow-x:auto}
  html[data-style="buta"] .qcard{border-left-width:5px}
  html[data-style="buta"] #toasts{bottom:calc(76px + env(safe-area-inset-bottom))}
}
@media (prefers-reduced-transparency:reduce){html[data-style="buta"] .tabbar,html[data-style="buta"] .side{background:var(--rail)}}

html[data-style="cam"] .brief{display:none}

/* ===== İMPULS: koyu kumanda paneli (sintetik firuzə üzerinə qara, mono rakamlar, ızgara) ===== */
html[data-style="impuls"]{
  --display:ui-monospace,'SFMono-Regular','SF Mono','JetBrains Mono','Roboto Mono',Menlo,Consolas,monospace;
  --imp-good:#0E8C7B;--imp-bad:#B8244B;--imp-warn:#9A6A00;
  --bg:#F2F5F4;--surface:#FFFFFF;--surface-2:#E7ECEB;--fill:#DEE6E4;--fill-2:#E9EFEE;
  --text:#0E1416;--text-2:#4B5A5C;--text-3:#7C8A8C;
  --sep:rgba(14,20,22,.30);--sep-2:rgba(14,20,22,.14);--rule:rgba(14,20,22,.14);--rule-strong:rgba(14,20,22,.34);
  --accent:#0E8C7B;--accent-text:#0E8C7B;--on-accent:#F4FFFC;--accent-soft:#D9F0EC;--accent-hover:#0B6F62;
  --good:#0E8C7B;--good-soft:#D9F0EC;--warn:#8A5E00;--warn-soft:#F5E6C2;--bad:#B8244B;--bad-soft:#F5D9E1;
  --s1:#0E8C7B;--s2:#B8244B;--s3:#9A6A00;--s4:#2E6B9E;--s5:#6B4F9E;
  --rail:#0B1215;--rail-ink:rgba(224,255,250,.64);
  --glass:var(--surface);--g-card:var(--surface);--g-strong:var(--surface);--g-line:var(--rule);--g-input:var(--surface);--g-fill:var(--fill-2);
  --shadow:none;--shadow-pop:0 16px 40px rgba(6,14,16,.20),0 2px 6px rgba(6,14,16,.12);--g-shadow:none;
  --r-xl:6px;--r-l:4px;--r-m:4px;--r-s:3px;
  --grid:url("data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' width='28' height='28' viewBox='0 0 28 28'%3E%3Cpath d='M0 .5H28M.5 0V28' stroke='%230E8C7B' stroke-opacity='.16'/%3E%3C/svg%3E");
}
@media (prefers-color-scheme:dark){html[data-style="impuls"]{
  --bg:#090D0F;--surface:#10171A;--surface-2:#141D20;--fill:#172124;--fill-2:#121A1D;
  --text:#E7F3F1;--text-2:#9FB3B2;--text-3:#71898A;
  --sep:rgba(231,243,241,.28);--sep-2:rgba(231,243,241,.14);--rule:rgba(231,243,241,.14);--rule-strong:rgba(231,243,241,.30);
  --accent:#35E1C6;--accent-text:#35E1C6;--on-accent:#04201C;--accent-soft:#113531;--accent-hover:#54ECD5;
  --good:#35E1C6;--good-soft:#0F2E29;--warn:#E8B23C;--warn-soft:#352a0e;--bad:#F2537D;--bad-soft:#36131C;
  --s1:#35E1C6;--s2:#F2537D;--s3:#E8B23C;--s4:#6EA8E8;--s5:#B28AF2;
  --rail:#05090A;--rail-ink:rgba(224,255,250,.60);
  --grid:url("data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' width='28' height='28' viewBox='0 0 28 28'%3E%3Cpath d='M0 .5H28M.5 0V28' stroke='%2335E1C6' stroke-opacity='.10'/%3E%3C/svg%3E");
  color-scheme:dark;
}}
html[data-style="impuls"] body.app{background:var(--bg) var(--grid);color:var(--text)}
html[data-style="impuls"] body.app::before{display:none}
html[data-style="impuls"] h1,html[data-style="impuls"] h2,html[data-style="impuls"] h3,html[data-style="impuls"] .brand,html[data-style="impuls"] .hero-num,html[data-style="impuls"] .tile-value{font-family:var(--display);letter-spacing:-.01em}
html[data-style="impuls"] h1{font-size:33px;line-height:1.15;font-weight:700}
html[data-style="impuls"] h2{font-size:18px;font-weight:700;margin:36px 0 14px;color:var(--text-2)}
html[data-style="impuls"] h3{font-size:15px;font-weight:700;font-family:var(--display)}
html[data-style="impuls"] :focus-visible{outline:3px solid var(--accent);outline-offset:2px;border-radius:2px}
/* ince komuta şeridi */
html[data-style="impuls"] .side{left:0;top:0;bottom:0;width:68px;border-radius:0;border:0;border-right:1px solid rgba(255,255,255,.06);background:var(--rail);box-shadow:none;-webkit-backdrop-filter:none;backdrop-filter:none;padding:12px 0;align-items:center;overflow:visible}
html[data-style="impuls"] .shell{margin-left:68px}
html[data-style="impuls"] .brand{padding:4px 0 14px;justify-content:center}
html[data-style="impuls"] .brand>span:not(.mark),html[data-style="impuls"] .close-menu{display:none}
html[data-style="impuls"] .brand .mark{width:auto;height:auto;background:none;box-shadow:none;display:block}
html[data-style="impuls"] .side .mark-svg,html[data-style="impuls"] .tabbar .mark-svg{--m1:currentColor;--m2:currentColor;--m3:currentColor}
html[data-style="impuls"] .side .mark-svg{width:30px;height:30px;color:var(--accent)}
html[data-style="impuls"] .mark-svg.mono .m1,html[data-style="impuls"] .mark-svg.mono .m2,html[data-style="impuls"] .mark-svg.mono .m3{fill:currentColor}
html[data-style="impuls"] .nav{gap:2px;width:100%;padding:0 6px;display:grid}
html[data-style="impuls"] .nav a{flex-direction:column;justify-content:center;gap:4px;min-height:54px;padding:6px 2px;border-radius:3px;font-size:10px;font-weight:600;text-align:center;color:var(--rail-ink);position:relative;line-height:1.1;font-family:var(--display)}
html[data-style="impuls"] .nav a .ic{color:inherit;opacity:.85;width:19px;height:19px}
html[data-style="impuls"] .nav a:hover{background:rgba(255,255,255,.06);color:#fff}
html[data-style="impuls"] .nav a[aria-current="page"]{background:rgba(53,225,198,.12);color:var(--accent)}
html[data-style="impuls"] .nav a[aria-current="page"] .ic{color:var(--accent)}
html[data-style="impuls"] .nav a[aria-current="page"]::before{content:"";position:absolute;left:0;top:8px;bottom:8px;width:2px;background:var(--accent)}
html[data-style="impuls"] .nav a .count{position:absolute;top:3px;right:6px;margin:0;background:var(--accent);color:var(--on-accent);min-width:16px;padding:0 4px;font-size:10px;border-radius:2px}
html[data-style="impuls"] .side-foot{border:0;padding:0 6px 4px;width:100%;margin-top:auto}
html[data-style="impuls"] .side-status,html[data-style="impuls"] .side-link{justify-content:center;padding:9px 0;color:var(--rail-ink);border-radius:3px}
html[data-style="impuls"] .side-status span,html[data-style="impuls"] .side-link span{display:none}
html[data-style="impuls"] .side-status:hover,html[data-style="impuls"] .side-link:hover{background:rgba(255,255,255,.06);color:#fff}
html[data-style="impuls"] [data-toggle-side]{display:none!important}
/* üst çubuk: düz, alt çizgi */
html[data-style="impuls"] .shell>.toolbar{position:sticky;top:0;margin:0;height:54px;border-radius:0;background:var(--bg);border:0;border-bottom:1px solid var(--rule);box-shadow:none;-webkit-backdrop-filter:none;backdrop-filter:none;padding:0 28px}
html[data-style="impuls"] .search{background:var(--surface);border:1px solid var(--rule-strong);border-radius:3px}
html[data-style="impuls"] .search input{background:transparent;border:0;font-family:var(--display);font-size:13px}
html[data-style="impuls"] .live{background:transparent;color:var(--good)}
html[data-style="impuls"] .pulse{border-radius:2px}
html[data-style="impuls"] .avatar{border-radius:3px;background:var(--accent);color:var(--on-accent);box-shadow:none;font-family:var(--display)}
html[data-style="impuls"] .main{max-width:1180px;padding:28px 28px 90px}
html[data-style="impuls"] .page-head{margin:0 0 18px;padding:0}
html[data-style="impuls"] .page-head::before,html[data-style="impuls"] .with-aurora::before{display:none}
html[data-style="impuls"] .page-head .sub{color:var(--text-2);font-size:14px;margin-top:4px}
/* sekmeler: alt çizgi, mono */
html[data-style="impuls"] .tabs{background:none;border-bottom:1px solid var(--rule);border-radius:0;padding:0;gap:22px;width:100%;margin:0 0 22px}
html[data-style="impuls"] .tabs a{padding:9px 0;border-radius:0;color:var(--text-2);font-weight:600;font-family:var(--display);font-size:13px;border-bottom:2px solid transparent;margin-bottom:-1px;min-height:0}
html[data-style="impuls"] .tabs a:hover{color:var(--text);background:none}
html[data-style="impuls"] .tabs a[aria-current="page"]{background:none;box-shadow:none;color:var(--accent);border-bottom-color:var(--accent)}
html[data-style="impuls"] .tabs .n{background:var(--accent);color:var(--on-accent);border-radius:2px;font-family:var(--display)}
html[data-style="impuls"] .tabs:not(.subnav){border-bottom:0;gap:7px;flex-wrap:wrap;margin:0 0 18px}
html[data-style="impuls"] .tabs:not(.subnav) a{border:1px solid var(--rule-strong);border-radius:3px;padding:5px 12px;margin:0;font-size:12.5px}
html[data-style="impuls"] .tabs:not(.subnav) a[aria-current="page"]{background:var(--accent);color:var(--on-accent);border-color:var(--accent)}
/* paneller: düz çerçeve, köşesiz */
html[data-style="impuls"] .card,html[data-style="impuls"] .panel,html[data-style="impuls"] .table-wrap,html[data-style="impuls"] .empty,html[data-style="impuls"] .strip>div,html[data-style="impuls"] .login-card,html[data-style="impuls"] .help-in{
  background:var(--surface);border:1px solid var(--rule);border-radius:var(--r-m);box-shadow:none;-webkit-backdrop-filter:none;backdrop-filter:none}
html[data-style="impuls"] .card:hover,html[data-style="impuls"] .qcard:hover{transform:none;box-shadow:none;border-color:var(--rule-strong)}
html[data-style="impuls"] .card-h h3{font-size:14px;text-transform:none}
html[data-style="impuls"] .table-wrap{overflow:hidden}html[data-style="impuls"] .table-wrap.cards{overflow:visible;background:transparent;border:0}
html[data-style="impuls"] th{font-size:11px;font-weight:700;color:var(--text-2);border-bottom:1px solid var(--rule-strong);background:var(--fill-2);font-family:var(--display)}
html[data-style="impuls"] td{border-top:1px solid var(--rule)}
html[data-style="impuls"] td.num,html[data-style="impuls"] th.num,html[data-style="impuls"] .tile-value,html[data-style="impuls"] .hero-num{font-family:var(--display);font-variant-numeric:lining-nums tabular-nums}
html[data-style="impuls"] tbody tr:hover>td{background:var(--fill-2)}
/* onay kuyruğu: ince sol çizgi, köşesiz */
html[data-style="impuls"] .qcard{background:var(--surface);border:1px solid var(--rule);border-left:3px solid var(--accent);border-radius:0 var(--r-m) var(--r-m) 0;box-shadow:none;-webkit-backdrop-filter:none;backdrop-filter:none}
html[data-style="impuls"] .qcard::before{display:none}
html[data-style="impuls"] .attn-item{background:var(--surface);border:1px solid var(--rule);border-left:3px solid var(--warn);border-radius:0 var(--r-m) var(--r-m) 0;box-shadow:none;-webkit-backdrop-filter:none;backdrop-filter:none}
html[data-style="impuls"] .attn-item.bad{border-left-color:var(--bad)}
html[data-style="impuls"] .attn-item::before{display:none}
html[data-style="impuls"] .attn-item:hover{transform:none;box-shadow:none;background:var(--fill-2)}
html[data-style="impuls"] .qcard .pair .az,html[data-style="impuls"] .pair .az{font-weight:600}
/* düğmeler: köşesiz, ince çerçeve */
html[data-style="impuls"] .btn{border-radius:3px;background:transparent;border:1px solid var(--rule-strong);color:var(--text);font-weight:600;font-family:var(--display);font-size:13px;min-height:36px;padding:7px 14px;transition:background-color .1s,color .1s,border-color .1s}
html[data-style="impuls"] .btn:hover{background:var(--fill);text-decoration:none}
html[data-style="impuls"] .btn:active{transform:none}
html[data-style="impuls"] .btn.primary{background:var(--accent);border-color:var(--accent);color:var(--on-accent)}
html[data-style="impuls"] .btn.primary:hover{background:var(--accent-hover);border-color:var(--accent-hover)}
html[data-style="impuls"] .btn.danger{background:transparent;border-color:var(--bad);color:var(--bad)}
html[data-style="impuls"] .btn.danger:hover{background:var(--bad);border-color:var(--bad);color:#fff}
html[data-style="impuls"] .btn.success{background:var(--good);border-color:var(--good);color:var(--on-accent)}
html[data-style="impuls"] .btn.ghost{border-color:transparent;color:var(--accent-text)}
html[data-style="impuls"] .btn.ghost:hover{background:var(--fill);color:var(--text)}
html[data-style="impuls"] .btn.sm{min-height:30px;padding:4px 11px;font-size:12px}
html[data-style="impuls"] input[type=text],html[data-style="impuls"] input[type=password],html[data-style="impuls"] input[type=number],html[data-style="impuls"] input[type=search],html[data-style="impuls"] input[type=url],html[data-style="impuls"] input[type=email],html[data-style="impuls"] select,html[data-style="impuls"] textarea{background:var(--surface);border:1px solid var(--rule-strong);border-radius:3px;color:var(--text);font-family:var(--display);font-size:13.5px}
html[data-style="impuls"] input:focus,html[data-style="impuls"] select:focus,html[data-style="impuls"] textarea:focus{background:var(--surface);border-color:var(--accent);box-shadow:0 0 0 3px color-mix(in srgb,var(--accent) 30%,transparent)}
html[data-style="impuls"] input[type=checkbox],html[data-style="impuls"] input[type=radio]{accent-color:var(--accent)}
/* etiketler: köşesiz, mono */
html[data-style="impuls"] .badge,html[data-style="impuls"] .via{font-family:var(--display);border-radius:2px;background:transparent!important;border:1px solid currentColor;font-weight:600;font-size:11.5px;padding:1px 6px}
html[data-style="impuls"] .chips{background:none;padding:0;gap:6px;border-radius:0}
html[data-style="impuls"] .chip{border:1px solid var(--rule-strong);border-radius:3px;padding:5px 11px;background:transparent;font-family:var(--display);font-size:12.5px}
html[data-style="impuls"] .chip:hover{background:var(--fill)}
html[data-style="impuls"] .chip[aria-current="true"]{background:var(--accent);color:var(--on-accent);border-color:var(--accent);box-shadow:none}
html[data-style="impuls"] .chip[aria-current="true"] .n{color:inherit;opacity:.85}
/* genel bakış: durum satırı + sık ızgaralı kutular */
html[data-style="impuls"] .hero-card{display:none}
html[data-style="impuls"] .tiles{grid-column:span 12;grid-template-columns:repeat(4,minmax(0,1fr))}
html[data-style="impuls"] .tile{border-radius:var(--r-m)}
html[data-style="impuls"] .tile-value{font-size:34px;font-weight:700;line-height:1}
html[data-style="impuls"] .tile-label{font-size:11.5px;color:var(--text-2);font-family:var(--display);text-transform:uppercase;letter-spacing:.03em}
html[data-style="impuls"] .brief{display:block;margin:0 0 22px;max-width:46em;font-size:16px;line-height:1.5;color:var(--text-2);font-family:var(--display);padding:10px 14px;background:var(--fill-2);border:1px solid var(--rule);border-radius:var(--r-m)}
html[data-style="impuls"] .brief a{color:var(--accent);text-decoration:underline;text-decoration-thickness:1px}
html[data-style="impuls"] .brief .muted{display:block;margin-top:4px;color:var(--text-3)}
html[data-style="impuls"] .attn{margin-bottom:22px}
html[data-style="impuls"] #toasts .flash,html[data-style="impuls"] .flash{background:var(--surface);border:1px solid var(--rule-strong);border-left:3px solid var(--good);border-radius:3px;box-shadow:var(--shadow-pop);-webkit-backdrop-filter:none;backdrop-filter:none;font-family:var(--display);font-size:13px}
html[data-style="impuls"] .flash.bad{border-left-color:var(--bad)}
html[data-style="impuls"] .flash .ic{color:var(--good)}html[data-style="impuls"] .flash.bad .ic{color:var(--bad)}
html[data-style="impuls"] .pm-panel.glass,html[data-style="impuls"] .cmd,html[data-style="impuls"] .tip{background:var(--surface);border:1px solid var(--rule-strong);border-radius:4px;-webkit-backdrop-filter:none;backdrop-filter:none}
html[data-style="impuls"] .loadbar{background:var(--accent);height:3px}
html[data-style="impuls"] .open-banner{background:var(--bad-soft);border:1px solid var(--bad);border-radius:3px}
html[data-style="impuls"] .inline-warn{border-left:3px solid var(--warn);border-radius:2px}
html[data-style="impuls"] .setting,html[data-style="impuls"] .settings{border-color:var(--rule)}
html[data-style="impuls"] .empty-ic{background:var(--accent-soft);color:var(--accent-text);border-radius:4px}
html[data-style="impuls"] .foot{border-top:1px solid var(--rule);font-family:var(--display);font-size:11.5px}
/* giriş ekranı */
html[data-style="impuls"] .bare-main{max-width:420px;margin:10vh auto 0;padding:0 18px}
html[data-style="impuls"] .login-card{overflow:hidden;position:relative}
html[data-style="impuls"] .login-mark{width:auto;height:auto;background:none;box-shadow:none;display:block;color:var(--accent)}
html[data-style="impuls"] .login-mark .mark-svg{width:46px;height:46px}
html[data-style="impuls"] .login-card h1{font-size:28px}
html[data-style="impuls"] .login-ver{color:var(--text-3);font-family:var(--display)}
/* hareket azaltımı: zaten sakin, fazladan devre dışı bırak */
html[data-style="impuls"] .main:not(.quiet)>*,html[data-style="impuls"] .main:not(.quiet) .dash>*{animation:none}
html[data-style="impuls"] .btn:active,html[data-style="impuls"] .count.bump{animation:none}
@media (max-width:900px){
  html[data-style="impuls"] .shell{margin-left:0}
  html[data-style="impuls"] .shell>.toolbar{padding:0 14px;height:52px}
  html[data-style="impuls"] .main{padding:18px 14px calc(94px + env(safe-area-inset-bottom))}
  html[data-style="impuls"] h1{font-size:26px}
  html[data-style="impuls"] .tabbar{left:0;right:0;bottom:0;border-radius:0;background:var(--rail);border:0;box-shadow:0 -1px 0 rgba(255,255,255,.06);padding:0 4px env(safe-area-inset-bottom);-webkit-backdrop-filter:none;backdrop-filter:none}
  html[data-style="impuls"] .tabbar a{color:var(--rail-ink);border-radius:0;background:none;font-size:10px;font-family:var(--display);position:relative;min-height:56px}
  html[data-style="impuls"] .tabbar a[aria-current="page"]{color:var(--accent);background:none}
  html[data-style="impuls"] .tabbar a[aria-current="page"]::before{content:"";position:absolute;left:28%;right:28%;top:0;height:2px;background:var(--accent)}
  html[data-style="impuls"] .tab-badge{background:var(--accent);color:var(--on-accent);border-radius:2px}
  html[data-style="impuls"] .tiles{grid-template-columns:repeat(2,minmax(0,1fr))}
  html[data-style="impuls"] .tabs{gap:16px;overflow-x:auto}
  html[data-style="impuls"] .qcard{border-left-width:3px}
  html[data-style="impuls"] #toasts{bottom:calc(74px + env(safe-area-inset-bottom))}
}
@media (prefers-reduced-transparency:reduce){html[data-style="impuls"] .tabbar,html[data-style="impuls"] .side{background:var(--rail)}}


CSS;
}
