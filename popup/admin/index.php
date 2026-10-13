<?php
define('IGFP_ADMIN', true);
/**
 * =============================================================
 *  POPUP ADMIN PANELİ  —  ŞEMA v3
 *  Adres: https://uplow.us/popup/admin/
 * =============================================================
 */

require __DIR__ . '/auth.php';
require __DIR__ . '/stats-lib.php';

$paths = popup_paths();
$msg = ''; $msgType = 'ok';

/* ---------------- ÇIKIŞ ---------------- */
if (isset($_GET['logout'])) { adm_logout(); header('Location: index.php'); exit; }

/* ---------------- İLK KURULUM ---------------- */
$hash = adm_hash();
if ($hash === '') {
    $new = '';
    if (($_POST['action'] ?? '') === 'makehash' && ($_POST['pass'] ?? '') !== '') {
        if (strlen($_POST['pass']) < 8) { $msg = 'Şifre en az 8 karakter olmalı.'; $msgType = 'err'; }
        else { $new = password_hash($_POST['pass'], PASSWORD_DEFAULT); }
    }
    $secret = popup_secret_file();
    include __DIR__ . '/view-setup.php';
    exit;
}

/* ---------------- GİRİŞ ---------------- */
if (!adm_logged_in()) {
    $wait = adm_blocked_for();
    if (($_POST['action'] ?? '') === 'login') {
        if ($wait > 0) {
            $msg = 'Çok fazla hatalı deneme. ' . ceil($wait / 60) . ' dakika sonra tekrar deneyin.';
            $msgType = 'err';
        } elseif (adm_login((string) ($_POST['pass'] ?? ''))) {
            header('Location: index.php'); exit;
        } else {
            $wait = adm_blocked_for();
            $msg = 'Şifre yanlış.'; $msgType = 'err';
        }
    }
    include __DIR__ . '/view-login.php';
    exit;
}

/* ---------------- POST YARDIMCILARI ---------------- */
function pv($k, $d = '') { return $_POST[$k] ?? $d; }
function pbool($k) { return isset($_POST[$k]) && $_POST[$k] !== '0'; }
function pint($k, $d = 0) { return (int) ($_POST[$k] ?? $d); }
function pfloat($k, $d = 0) { return (float) str_replace(',', '.', (string) ($_POST[$k] ?? $d)); }
function plines($k) {
    $out = [];
    foreach (preg_split('/\r\n|\r|\n/', (string) ($_POST[$k] ?? '')) as $l) {
        $l = trim($l); if ($l !== '') $out[] = $l;
    }
    return $out;
}
function h($s) { return htmlspecialchars((string) $s, ENT_QUOTES, 'UTF-8'); }

/** Zamanlama (alan öneki: sc_ / auto_) */
function pschedule(string $pre = 'sc'): array {
    $days = [];
    foreach ((array) ($_POST[$pre . '_days'] ?? []) as $d) {
        $d = (int) $d; if ($d >= 0 && $d <= 6) $days[] = $d;
    }
    return [
        'hours'    => ['from' => trim((string) pv($pre . '_hfrom')), 'to' => trim((string) pv($pre . '_hto'))],
        'days'     => $days,
        'dateFrom' => trim((string) pv($pre . '_dfrom')),
        'dateTo'   => trim((string) pv($pre . '_dto')),
    ];
}
/** Tetikleyici */
function ptrigger(string $pre = 'sc'): array {
    $type = (string) pv($pre . '_trigtype');
    return [
        'type'          => in_array($type, ['load','exit','scroll'], true) ? $type : 'load',
        'scrollPercent' => max(1, min(100, pint($pre . '_scrollpct', 50))),
    ];
}
/** Cihaz + kaynak hedefleme */
function ptargeting(string $pre = 'sc'): array {
    $dev = [];
    foreach ((array) ($_POST[$pre . '_dev'] ?? []) as $d) {
        if (($d === 'mobile' || $d === 'desktop') && !in_array($d, $dev, true)) $dev[] = $d;
    }
    return ['devices' => $dev, 'sources' => plines($pre . '_sources')];
}
/** Müşteri segmenti — boşsa null döner */
function psegment() {
    return popup_norm_segment([
        'login'      => (string) pv('seg_login'),
        'balanceMin' => trim((string) pv('seg_balmin')),
        'balanceMax' => trim((string) pv('seg_balmax')),
        'ordersMin'  => trim((string) pv('seg_ordmin')),
        'ordersMax'  => trim((string) pv('seg_ordmax')),
        'visitor'    => (string) pv('seg_visitor'),
        'visitMin'   => pint('seg_visitmin', 0),
        'countries'  => preg_split('/[\s,]+/', (string) pv('seg_countries'), -1, PREG_SPLIT_NO_EMPTY) ?: [],
    ]);
}
/** Kural / sayfa istisnaları */
function poverrides(): array {
    return [
        'rules' => pbool('rules_on') ? [
            'perDay'            => max(0, pint('r_perDay', 0)),
            'perSession'        => max(0, pint('r_perSession', 0)),
            'minGapMinutes'     => max(0, pint('r_minGapMinutes', 0)),
            'maxTotal'          => max(0, pint('r_maxTotal', 0)),
            'clickCooldownDays' => max(0, pint('r_clickCooldownDays', 0)),
        ] : null,
        'pages' => pbool('pages_on') ? [
            'mode'     => pv('pp_mode') === 'include' ? 'include' : 'exclude',
            'patterns' => plines('pp_patterns'),
        ] : null,
    ];
}
/** Bir popup'ın ortak (tema/zamanlama/tetik/hedef/segment/istisna) alanlarını POST'tan topla */
function pcommon(int $defPriority): array {
    $ov = poverrides();
    return [
        'name'      => trim((string) pv('name')),
        'enabled'   => pbool('enabled'),
        'priority'  => max(0, min(999, pint('priority', $defPriority))),
        'theme'     => in_array(pv('theme'), ['','instagram','tiktok','whatsapp','telegram','youtube','default'], true) ? pv('theme') : '',
        'schedule'  => pschedule(),
        'trigger'   => ptrigger(),
        'targeting' => ptargeting(),
        'segment'   => psegment(),
        'rules'     => $ov['rules'],
        'pages'     => $ov['pages'],
    ];
}

/* ---------------- ÖZETLER (liste satırları için) ---------------- */
function sched_summary(array $s): string {
    $out = [];
    $h = $s['hours'] ?? [];
    if (!empty($h['from']) && !empty($h['to'])) $out[] = $h['from'] . '–' . $h['to'];
    $dn = ['Paz','Pzt','Sal','Çar','Per','Cum','Cmt'];
    if (!empty($s['days'])) { $out[] = implode(',', array_map(function ($n) use ($dn) { return $dn[$n] ?? '?'; }, $s['days'])); }
    if (!empty($s['dateFrom']) || !empty($s['dateTo'])) $out[] = ($s['dateFrom'] ?: '…') . ' → ' . ($s['dateTo'] ?: '…');
    return $out ? implode(' · ', $out) : 'her zaman';
}
function trig_summary(array $t): string {
    $type = $t['type'] ?? 'load';
    if ($type === 'exit')   return 'çıkışta';
    if ($type === 'scroll') return 'kaydırma %' . (int) ($t['scrollPercent'] ?? 50);
    return 'açılışta';
}
function seg_summary($s): string {
    if (!is_array($s)) return '';
    $o = [];
    if (($s['login'] ?? '') === 'in')  $o[] = 'girişli';
    if (($s['login'] ?? '') === 'out') $o[] = 'girişsiz';
    if (($s['balanceMin'] ?? '') !== '' || ($s['balanceMax'] ?? '') !== '') {
        $o[] = 'bakiye ' . (($s['balanceMin'] ?? '') !== '' ? $s['balanceMin'] : '0') . '–' . (($s['balanceMax'] ?? '') !== '' ? $s['balanceMax'] : '∞');
    }
    if (($s['ordersMin'] ?? '') !== '' || ($s['ordersMax'] ?? '') !== '') {
        $o[] = 'sipariş ' . (($s['ordersMin'] ?? '') !== '' ? $s['ordersMin'] : '0') . '–' . (($s['ordersMax'] ?? '') !== '' ? $s['ordersMax'] : '∞');
    }
    if (($s['visitor'] ?? '') === 'new')       $o[] = 'yeni ziyaretçi';
    if (($s['visitor'] ?? '') === 'returning') $o[] = 'geri dönen';
    if (!empty($s['visitMin'])) $o[] = $s['visitMin'] . '+ ziyaret';
    if (!empty($s['countries'])) $o[] = implode('/', $s['countries']);
    return $o ? implode(' · ', $o) : '';
}

/** Popup verisini modal formunun alan adlarına düz haritaya çevir */
function form_data(array $p, bool $isAuto): array {
    $sc  = $p['schedule']  ?? [];
    $tr  = $p['trigger']   ?? [];
    $tg  = $p['targeting'] ?? [];
    $sg  = is_array($p['segment'] ?? null) ? $p['segment'] : [];
    $ru  = is_array($p['rules'] ?? null) ? $p['rules'] : null;
    $pgs = is_array($p['pages'] ?? null) ? $p['pages'] : null;

    $d = [
        'pid'            => (string) ($p['id'] ?? ''),
        'name'           => (string) ($p['name'] ?? ''),
        'enabled'        => !empty($p['enabled']) ? 1 : 0,
        'priority'       => (int) ($p['priority'] ?? ($isAuto ? 10 : 20)),
        'theme'          => (string) ($p['theme'] ?? ''),
        'sc_hfrom'       => (string) ($sc['hours']['from'] ?? ''),
        'sc_hto'         => (string) ($sc['hours']['to'] ?? ''),
        'sc_days'        => array_map('strval', (array) ($sc['days'] ?? [])),
        'sc_dfrom'       => (string) ($sc['dateFrom'] ?? ''),
        'sc_dto'         => (string) ($sc['dateTo'] ?? ''),
        'sc_trigtype'    => (string) ($tr['type'] ?? 'load'),
        'sc_scrollpct'   => (int) ($tr['scrollPercent'] ?? 50),
        'sc_dev'         => array_map('strval', (array) ($tg['devices'] ?? [])),
        'sc_sources'     => implode("\n", (array) ($tg['sources'] ?? [])),
        'seg_login'      => (string) ($sg['login'] ?? ''),
        'seg_balmin'     => (string) ($sg['balanceMin'] ?? ''),
        'seg_balmax'     => (string) ($sg['balanceMax'] ?? ''),
        'seg_ordmin'     => (string) ($sg['ordersMin'] ?? ''),
        'seg_ordmax'     => (string) ($sg['ordersMax'] ?? ''),
        'seg_visitor'    => (string) ($sg['visitor'] ?? ''),
        'seg_visitmin'   => (int) ($sg['visitMin'] ?? 0),
        'seg_countries'  => implode(', ', (array) ($sg['countries'] ?? [])),
        'rules_on'          => $ru ? 1 : 0,
        'r_perDay'          => (int) ($ru['perDay'] ?? 0),
        'r_perSession'      => (int) ($ru['perSession'] ?? 0),
        'r_minGapMinutes'   => (int) ($ru['minGapMinutes'] ?? 0),
        'r_maxTotal'        => (int) ($ru['maxTotal'] ?? 0),
        'r_clickCooldownDays' => (int) ($ru['clickCooldownDays'] ?? 0),
        'pages_on'       => $pgs ? 1 : 0,
        'pp_mode'        => (string) ($pgs['mode'] ?? 'exclude'),
        'pp_patterns'    => implode("\n", (array) ($pgs['patterns'] ?? [])),
    ];

    if ($isAuto) {
        $d += [
            'count'             => (int) ($p['count'] ?? 3),
            'sort'              => (string) ($p['sort'] ?? 'cheapest'),
            'skip_zero'         => !empty($p['skip_zero']) ? 1 : 0,
            'category_mode'     => (string) ($p['category_mode'] ?? 'first'),
            'category_manual'   => (string) ($p['category_manual'] ?? ''),
            'skip_categories'   => implode("\n", (array) ($p['skip_categories'] ?? [])),
            'currency'          => (string) ($p['currency'] ?? '₼'),
            'currency_before'   => !empty($p['currency_before']) ? 1 : 0,
            'decimals'          => (string) ($p['decimals'] ?? 'auto'),
            'price_multiplier'  => (string) ($p['price_multiplier'] ?? 1),
            'order_url'         => (string) ($p['order_url'] ?? ''),
            'subtitle'          => (string) ($p['subtitle'] ?? ''),
            'badge_template'    => (string) ($p['badge_template'] ?? '{category}'),
            'badge_strip_emoji' => !empty($p['badge_strip_emoji']) ? 1 : 0,
        ];
    } else {
        $d += [
            'badge'        => (string) ($p['badge'] ?? ''),
            'title'        => (string) ($p['title'] ?? ''),
            'subtitle'     => (string) ($p['subtitle'] ?? ''),
            'image'        => (string) ($p['image'] ?? ''),
            'btn_text'     => (string) ($p['btn_text'] ?? ''),
            'btn_url'      => (string) ($p['btn_url'] ?? ''),
            'countdown'    => (string) ($p['countdown'] ?? ''),
            'coupon'       => (string) ($p['coupon'] ?? ''),
            'coupon_label' => (string) ($p['coupon_label'] ?? ''),
        ];
    }
    return $d;
}

$cfg = popup_config();
$tab = preg_replace('/[^a-z]/', '', (string) ($_GET['tab'] ?? 'durum'));

/* ---------------- İŞLEMLER ---------------- */
$action = (string) ($_POST['action'] ?? '');
if ($action !== '' && !adm_csrf_ok()) {
    $msg = 'Oturum doğrulaması başarısız, sayfayı yenileyin.'; $msgType = 'err'; $action = '';
}

$runCats = [];
$newHash = '';

/* --- şifre hash --- */
if ($action === 'newpass') {
    $tab = 'sifre';
    $pw = (string) pv('pass');
    if (strlen($pw) < 8) { $msg = 'Şifre en az 8 karakter olmalı.'; $msgType = 'err'; }
    else { $newHash = password_hash($pw, PASSWORD_DEFAULT); $msg = 'Hash üretildi — aşağıdaki satırı popup-config.php içine yapıştırın.'; }
}

/* --- elle güncelle --- */
if ($action === 'run') {
    $res = popup_run();
    $runCats = $res['categories'] ?? [];
    $msg = ($res['ok'] ? 'Güncellendi — ' : 'Hata: ') . $res['msg'];
    $msgType = $res['ok'] ? 'ok' : 'err';
    popup_audit('run', $res['msg']);
    $cfg = popup_config();
}

/* --- #7 acil durdurma --- */
if ($action === 'kill_on' || $action === 'kill_off') {
    popup_kill_set($action === 'kill_on', (string) pv('note'));
    $msg = $action === 'kill_on'
        ? 'ACİL DURDURULDU — tüm popuplar sitede anında durdu.'
        : 'Popuplar yeniden yayında.';
    $msgType = $action === 'kill_on' ? 'err' : 'ok';
}

/* --- #8 yedekten geri al --- */
if ($action === 'restore') {
    $tab = 'bakim';
    $ts = (int) pv('ts');
    if (popup_backup_restore($ts)) {
        $res = popup_run();
        $msg = 'Yedek geri yüklendi (' . date('d.m.Y H:i', $ts) . '). ' . ($res['ok'] ? 'embed.js yenilendi.' : $res['msg']);
        $msgType = $res['ok'] ? 'ok' : 'err';
    } else {
        $msg = 'Yedek bulunamadı.'; $msgType = 'err';
    }
    $cfg = popup_config();
}

/* --- GLOBAL AYARLAR --- */
if ($action === 'save') {
    $s = [
        'enabled' => pbool('enabled_global'),
        'client' => [
            'delay'        => max(0, min(60000, pint('delay', 1200))),
            'footer'       => (string) pv('footer'),
            'openInNewTab' => pbool('openInNewTab'),
            'maxAgeHours'  => max(0, pint('maxAgeHours', 0)),
            'tzOffset'     => max(-720, min(840, pint('tzOffset', 240))),
            'maxPerLoad'   => max(1, min(6, pint('maxPerLoad', 2))),
            'queueGapMs'   => max(0, min(60000, pint('queueGapMs', 700))),
            'forceTheme'   => in_array(pv('forceTheme'), ['','instagram','tiktok','whatsapp','telegram','youtube','default'], true) ? pv('forceTheme') : '',
            'rules' => [
                'perDay'            => max(0, pint('perDay', 3)),
                'perSession'        => max(0, pint('perSession', 1)),
                'minGapMinutes'     => max(0, pint('minGapMinutes', 60)),
                'maxTotal'          => max(0, pint('maxTotal', 0)),
                'clickCooldownDays' => max(0, pint('clickCooldownDays', 7)),
            ],
            'pages' => [
                'mode'     => pv('pages_mode') === 'include' ? 'include' : 'exclude',
                'patterns' => plines('pages_patterns'),
            ],
            'stats' => [
                'enabled' => pbool('stats_enabled'),
                'url'     => trim((string) pv('stats_url')),
            ],
            'segment'   => ['varName' => trim((string) pv('seg_varname')) ?: 'PANEL_USER'],
            'killCheck' => ['enabled' => pbool('kill_check'), 'url' => trim((string) pv('kill_url'))],
        ],
    ];
    if (!popup_settings_save($s, 'global ayarlar')) {
        $msg = 'settings.json yazılamadı — popup klasörünün izni 755 mi?'; $msgType = 'err';
    } else {
        $res = popup_run();
        $runCats = $res['categories'] ?? [];
        $msg = 'Kaydedildi. ' . ($res['ok'] ? 'embed.js yenilendi — ' . $res['msg'] : 'AMA: ' . $res['msg']);
        $msgType = $res['ok'] ? 'ok' : 'err';
    }
    $cfg = popup_config();
}

/* --- #10 OTOMATİK POPUP: kaydet / sil / aç-kapa --- */
if ($action === 'auto_save') {
    $tab = 'popuplar';
    $autos = $cfg['autos'];
    $pid = preg_replace('/[^0-9A-Za-z_-]/', '', (string) pv('pid'));
    if ($pid === '') $pid = 'a' . substr(md5(uniqid('', true)), 0, 7);

    $entry = array_merge(popup_auto_default(), pcommon(10), [
        'id'                => $pid,
        'count'             => max(1, min(6, pint('count', 3))),
        'sort'              => pv('sort') === 'default' ? 'default' : 'cheapest',
        'skip_zero'         => pbool('skip_zero'),
        'category_mode'     => in_array(pv('category_mode'), ['first','newest','manual'], true) ? pv('category_mode') : 'first',
        'category_manual'   => trim((string) pv('category_manual')),
        'skip_categories'   => plines('skip_categories'),
        'currency'          => trim((string) pv('currency', '₼')),
        'currency_before'   => pbool('currency_before'),
        'decimals'          => pv('decimals') === 'auto' ? 'auto' : (int) pv('decimals'),
        'price_multiplier'  => pfloat('price_multiplier', 1) ?: 1,
        'order_url'         => trim((string) pv('order_url')),
        'subtitle'          => trim((string) pv('subtitle')),
        'badge_template'    => trim((string) pv('badge_template')),
        'badge_strip_emoji' => pbool('badge_strip_emoji'),
    ]);
    if ($entry['name'] === '') $entry['name'] = 'Otomatik ' . $pid;

    $found = false;
    foreach ($autos as $i => $a) { if (($a['id'] ?? '') === $pid) { $autos[$i] = $entry; $found = true; break; } }
    if (!$found) $autos[] = $entry;

    if (!popup_settings_save(['autos' => array_values($autos)], ($found ? 'otomatik güncelle: ' : 'otomatik ekle: ') . $entry['name'])) {
        $msg = 'Kaydedilemedi — settings.json izni?'; $msgType = 'err';
    } else {
        $res = popup_run();
        $runCats = $res['categories'] ?? [];
        $msg = ($found ? 'Otomatik popup güncellendi. ' : 'Otomatik popup eklendi. ') . ($res['ok'] ? 'embed.js yenilendi.' : 'AMA: ' . $res['msg']);
        $msgType = $res['ok'] ? 'ok' : 'err';
    }
    $cfg = popup_config();
}

if ($action === 'auto_del' || $action === 'auto_toggle') {
    $tab = 'popuplar';
    $pid = preg_replace('/[^0-9A-Za-z_-]/', '', (string) pv('pid'));
    $autos = $cfg['autos'];
    if ($action === 'auto_del') {
        $autos = array_values(array_filter($autos, function ($a) use ($pid) { return ($a['id'] ?? '') !== $pid; }));
        $note = 'otomatik sil: ' . $pid;
    } else {
        foreach ($autos as $i => $a) { if (($a['id'] ?? '') === $pid) { $autos[$i]['enabled'] = !($a['enabled'] ?? false); break; } }
        $note = 'otomatik aç/kapa: ' . $pid;
    }
    popup_settings_save(['autos' => array_values($autos)], $note);
    $res = popup_run();
    $msg = ($action === 'auto_del' ? 'Otomatik popup silindi. ' : 'Durum değişti. ') . ($res['ok'] ? 'embed.js yenilendi.' : $res['msg']);
    $msgType = $res['ok'] ? 'ok' : 'err';
    $cfg = popup_config();
}

/* --- KATMAN SIRASI: popuplarin gosterim sirasini kaydet --- */
if ($action === 'order_save') {
    $tab = 'popuplar';
    $ids = array_values(array_filter(array_map(function ($x) {
        return preg_replace('/[^0-9A-Za-z_:-]/', '', (string) $x);
    }, explode(',', (string) pv('order')))));
    $pri = [];
    foreach ($ids as $i => $k) { $pri[$k] = ($i + 1) * 10; }   // 10, 20, 30...
    $autos = $cfg['autos']; $pops = $cfg['popups'];
    foreach ($autos as $i => $a) { if (isset($pri['a:' . ($a['id'] ?? '')])) $autos[$i]['priority'] = $pri['a:' . $a['id']]; }
    foreach ($pops as $i => $m) { if (isset($pri['m:' . ($m['id'] ?? '')])) $pops[$i]['priority'] = $pri['m:' . $m['id']]; }
    popup_settings_save(['autos' => array_values($autos), 'popups' => array_values($pops)], 'katman sırası');
    $res = popup_run();
    $msg = 'Katman sırası kaydedildi. ' . ($res['ok'] ? 'embed.js yenilendi.' : $res['msg']);
    $msgType = $res['ok'] ? 'ok' : 'err';
    $cfg = popup_config();
}

/* --- MANUEL POPUP: kaydet / sil / aç-kapa --- */
if ($action === 'popup_save') {
    $tab = 'popuplar';
    $pops = $cfg['popups'];
    $pid = preg_replace('/[^0-9A-Za-z_-]/', '', (string) pv('pid'));
    if ($pid === '') $pid = 'p' . substr(md5(uniqid('', true)), 0, 7);

    $entry = array_merge(popup_manual_default(), pcommon(20), [
        'id'           => $pid,
        'badge'        => trim((string) pv('badge')),
        'title'        => trim((string) pv('title')),
        'subtitle'     => trim((string) pv('subtitle')),
        'image'        => trim((string) pv('image')),
        'btn_text'     => trim((string) pv('btn_text')),
        'btn_url'      => trim((string) pv('btn_url')),
        'countdown'    => trim((string) pv('countdown')),
        'coupon'       => trim((string) pv('coupon')),
        'coupon_label' => trim((string) pv('coupon_label')),
    ]);
    if ($entry['theme'] === '') $entry['theme'] = 'telegram';
    if ($entry['name'] === '') $entry['name'] = 'Popup ' . $pid;

    if ($entry['title'] === '' && $entry['subtitle'] === '' && $entry['btn_text'] === '') {
        $msg = 'Popup kaydedilmedi: en az bir başlık, açıklama ya da buton gir.'; $msgType = 'err';
    } else {
        $found = false;
        foreach ($pops as $i => $p) { if (($p['id'] ?? '') === $pid) { $pops[$i] = $entry; $found = true; break; } }
        if (!$found) $pops[] = $entry;

        if (!popup_settings_save(['popups' => array_values($pops)], ($found ? 'manuel güncelle: ' : 'manuel ekle: ') . $entry['name'])) {
            $msg = 'Kaydedilemedi — settings.json izni?'; $msgType = 'err';
        } else {
            $res = popup_run();
            $msg = ($found ? 'Popup güncellendi. ' : 'Popup eklendi. ') . ($res['ok'] ? 'embed.js yenilendi.' : 'AMA: ' . $res['msg']);
            $msgType = $res['ok'] ? 'ok' : 'err';
        }
        $cfg = popup_config();
    }
}

if ($action === 'popup_del' || $action === 'popup_toggle') {
    $tab = 'popuplar';
    $pid = preg_replace('/[^0-9A-Za-z_-]/', '', (string) pv('pid'));
    $pops = $cfg['popups'];
    if ($action === 'popup_del') {
        $pops = array_values(array_filter($pops, function ($p) use ($pid) { return ($p['id'] ?? '') !== $pid; }));
        $note = 'manuel sil: ' . $pid;
    } else {
        foreach ($pops as $i => $p) { if (($p['id'] ?? '') === $pid) { $pops[$i]['enabled'] = !($p['enabled'] ?? false); break; } }
        $note = 'manuel aç/kapa: ' . $pid;
    }
    popup_settings_save(['popups' => array_values($pops)], $note);
    $res = popup_run();
    $msg = ($action === 'popup_del' ? 'Popup silindi. ' : 'Durum değişti. ') . ($res['ok'] ? 'embed.js yenilendi.' : $res['msg']);
    $msgType = $res['ok'] ? 'ok' : 'err';
    $cfg = popup_config();
}

/* ---------------- DURUM BİLGİSİ ---------------- */
$kill     = popup_kill_get();
$payload  = is_file($paths['output']) ? json_decode((string) @file_get_contents($paths['output']), true) : null;
$embedAge = is_file($paths['embed']) ? time() - filemtime($paths['embed']) : null;
$logTail  = [];
if (is_file($paths['log'])) {
    $lines = preg_split('/\r\n|\n/', (string) @file_get_contents($paths['log']));
    $logTail = array_slice(array_filter($lines), -12);
}
$stats = is_file($paths['stats']) ? json_decode((string) @file_get_contents($paths['stats']), true) : [];
if (!is_array($stats)) $stats = [];
ksort($stats);

$vhist = is_file($paths['versions']) ? json_decode((string) @file_get_contents($paths['versions']), true) : [];
if (!is_array($vhist)) $vhist = [];

$backups = popup_backup_list();
$audit   = popup_audit_list(40);

/* --- istatistik aralığı --- */
$range = (string) ($_GET['range'] ?? '30');
if (!array_key_exists($range, st_ranges())) $range = '30';
$stTo   = date('Y-m-d');
$stFrom = st_start_date($stats, $range);
$stDaily    = st_daily($stats, $stFrom, $stTo);
$stBuckets  = st_buckets($stDaily);
$stVersions = st_versions($stats, $stFrom, $stTo);
$stServices = st_services($stats, $stFrom, $stTo);
$stPopups   = st_popups($stats, $stFrom, $stTo);
$stDev      = st_breakdown($stats, $stFrom, $stTo, 'dev');
$stSrc      = st_breakdown($stats, $stFrom, $stTo, 'src');
$stPg       = st_breakdown($stats, $stFrom, $stTo, 'pg');
$stEng      = st_engagement($stats, $stFrom, $stTo);
$stSumV = 0; $stSumC = 0;
foreach ($stDaily as $d) { $stSumV += $d['view']; $stSumC += $d['click']; }
$stBest = 0;
foreach ($stBuckets as $b) { if ($b['view'] > $stBest) $stBest = $b['view']; }
$stBest = max(1, $stBest);
$stCurrent = $payload['v'] ?? '';

/* popup id -> ad eşlemesi */
$popupNames = ['bilinmiyor' => 'Bilinmeyen'];
foreach ($cfg['autos']  as $a) { if (!empty($a['id'])) $popupNames[$a['id']] = ($a['name'] ?? $a['id']) . ' (oto)'; }
foreach ($cfg['popups'] as $p) { if (!empty($p['id'])) $popupNames[$p['id']] = $p['name'] ?? $p['id']; }

/* modal formları için veri */
$formAutos = [];
foreach ($cfg['autos'] as $a) { $formAutos[$a['id']] = form_data($a, true); }
$formPopups = [];
foreach ($cfg['popups'] as $p) { $formPopups[$p['id']] = form_data($p, false); }
$formDefAuto   = form_data(popup_auto_default(), true);
$formDefManual = form_data(array_merge(popup_manual_default(), ['theme' => 'telegram']), false);

/* JS'in açacağı sekme */
$forceTab = '';
if (isset($_GET['tab']) || in_array($action, ['save','auto_save','auto_del','auto_toggle','order_save','popup_save','popup_del','popup_toggle','restore','newpass'], true)) {
    $forceTab = $tab;
}

include __DIR__ . '/view-panel.php';
