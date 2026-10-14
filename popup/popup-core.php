<?php
/**
 * =============================================================
 *  POPUP ÇEKİRDEĞİ  —  ŞEMA v3
 *
 *  - Birden çok OTOMATİK popup (autos[]) : her biri kendi kategorisi,
 *    fiyat biçimi ve metinleriyle. API tek sefer çağrılır, hepsi paylaşır.
 *  - Birden çok MANUEL popup (popups[])  : serbest içerik + buton.
 *  - Segmentasyon, tetikleyici, hedefleme, zamanlama : popup başına.
 *  - Acil durdurma (kill), ayar yedekleme, denetim günlüğü.
 *  - API çökerse son başarılı otomatik veri kullanılır (stale-while-error).
 *
 *  Ayarlar admin panelden yapılır ve settings.json'a yazılır.
 * =============================================================
 */

if (!defined('POPUP_DIR')) { define('POPUP_DIR', __DIR__); }

const POPUP_SCHEMA = 3;

/* ================= VARSAYILAN ŞABLONLAR ================= */

/** Zamanlama */
function popup_schedule_default(): array {
    return ['hours' => ['from' => '', 'to' => ''], 'days' => [], 'dateFrom' => '', 'dateTo' => ''];
}

/** Tetikleyici */
function popup_trigger_default(): array {
    return ['type' => 'load', 'scrollPercent' => 50];   // 'load' | 'exit' | 'scroll'
}

/** Cihaz / kaynak hedeflemesi */
function popup_targeting_default(): array {
    return ['devices' => [], 'sources' => []];
}

/** Müşteri segmenti (panel verisine göre) */
function popup_segment_default(): array {
    return [
        'login'      => '',   // '' | 'in' (giriş yapmış) | 'out' (yapmamış)
        'balanceMin' => '',   // '' = sınırsız
        'balanceMax' => '',
        'ordersMin'  => '',
        'ordersMax'  => '',
        'visitor'    => '',   // '' | 'new' | 'returning'
        'visitMin'   => 0,    // en az kaçıncı ziyaret
        'countries'  => [],   // ['AZ','TR'] — boş = hepsi
    ];
}

/** Bir OTOMATİK popup şablonu (servis seçimi ayarları dahil) */
function popup_auto_default(): array {
    return [
        'id'                => '',
        'name'              => '',
        'enabled'           => true,
        'priority'          => 10,
        'theme'             => '',
        'schedule'          => popup_schedule_default(),
        'trigger'           => popup_trigger_default(),
        'targeting'         => popup_targeting_default(),
        'segment'           => popup_segment_default(),
        'rules'             => null,
        'pages'             => null,
        // --- servis seçimi ---
        'count'             => 3,
        'sort'              => 'cheapest',      // 'default' | 'cheapest'
        'skip_zero'         => true,
        'category_mode'     => 'first',         // 'first' | 'newest' | 'manual'
        'category_manual'   => '',
        'skip_categories'   => [],
        // --- fiyat ---
        'currency'          => '₼',
        'currency_before'   => false,
        'decimals'          => 'auto',
        'price_multiplier'  => 1,
        // --- metinler ---
        'order_url'         => 'https://panelbaku.com/?service={id}',
        'subtitle'          => 'Sifarişləriniz üçün ən uyğun {category} servisini seçin.',
        'badge_template'    => '{category}',
        'badge_strip_emoji' => true,
    ];
}

/** Görünüm şablonları (popup-skins.php) */
function popup_skins(): array {
    static $s = null;
    if ($s === null) { $f = __DIR__ . '/popup-skins.php'; $s = is_file($f) ? (array) include $f : []; }
    return $s;
}
function popup_skin_valid(string $k): string { return isset(popup_skins()[$k]) ? $k : ''; }
/** "Ad | Fiyat" satırlarını [[ad, fiyat], ...] yap */
function popup_parse_items(string $t): array {
    $o = [];
    foreach (preg_split('/\R/', $t) as $ln) {
        $ln = trim($ln); if ($ln === '') continue;
        $p = array_map('trim', explode('|', $ln, 2));
        $o[] = [$p[0], $p[1] ?? ''];
        if (count($o) >= 8) break;
    }
    return $o;
}

/** Bir MANUEL popup şablonu */
function popup_manual_default(): array {
    return [
        'id'           => '',
        'name'         => '',
        'enabled'      => true,
        'priority'     => 20,
        'theme'        => 'telegram',
        'schedule'     => popup_schedule_default(),
        'trigger'      => popup_trigger_default(),
        'targeting'    => popup_targeting_default(),
        'segment'      => popup_segment_default(),
        'rules'        => null,
        'pages'        => null,
        'badge'        => '',
        'skin'         => '',
        'icon'         => '',
        'items'        => '',
        'title'        => '',
        'subtitle'     => '',
        'image'        => '',
        'btn_text'     => '',
        'btn_url'      => '',
        'countdown'    => '',
        'coupon'       => '',
        'coupon_label' => '',
    ];
}

/* ---------------- VARSAYILAN AYARLAR ---------------- */
function popup_defaults(): array {
    return [
        'schema_version' => POPUP_SCHEMA,
        'enabled'        => true,   // ana şalter

        // --- API (gizli, popup-config.php'den gelir) ---
        'api_url'   => '',
        'api_key'   => '',
        'timeout'   => 25,
        'run_token' => '',

        'autos'  => [],   // otomatik popuplar
        'popups' => [],   // manuel popuplar

        // --- tarayıcı tarafı (embed.js'e gömülür) ---
        'client' => [
            'delay'        => 1200,
            'footer'       => 'Bağlamaq üçün istənilən yerə klikləyin',
            'openInNewTab' => false,
            'maxAgeHours'  => 0,
            'forceTheme'   => '',
            'tzOffset'     => 240,   // dakika — Baku = UTC+4
            'maxPerLoad'   => 2,
            'queueGapMs'   => 700,
            'rules' => [
                'perDay'            => 3,
                'perSession'        => 1,
                'minGapMinutes'     => 60,
                'maxTotal'          => 0,
                'clickCooldownDays' => 7,
            ],
            'pages' => ['mode' => 'exclude', 'patterns' => []],
            'stats' => ['enabled' => true, 'url' => ''],
            // segmentasyon: panel kullanıcı verisini hangi JS değişkeninden okuyalım
            'segment' => ['varName' => 'PANEL_USER'],
            // acil durdurma kontrolü (cache'i aşar)
            'killCheck' => ['enabled' => true, 'url' => ''],
        ],
    ];
}

/* ---------------- DOSYA YOLLARI ---------------- */
function popup_paths(): array {
    return [
        'settings' => POPUP_DIR . '/settings.json',
        'output'   => POPUP_DIR . '/services.json',
        'template' => POPUP_DIR . '/popup-template.js',
        'embed'    => POPUP_DIR . '/embed.js',
        'log'      => POPUP_DIR . '/popup-cron.log',
        'stats'    => POPUP_DIR . '/stats.json',
        'versions' => POPUP_DIR . '/versions.json',
        'throttle' => POPUP_DIR . '/.admin-throttle.json',
        'kill'     => POPUP_DIR . '/kill.json',              // acil durdurma
        'history'  => POPUP_DIR . '/settings-history.json',  // ayar yedekleri
        'audit'    => POPUP_DIR . '/audit.json',             // değişiklik günlüğü
        'autolast' => POPUP_DIR . '/auto-last.json',         // son başarılı otomatik veri
    ];
}

/* ---------------- YARDIMCILAR ---------------- */
function popup_is_list(array $a): bool {
    $i = 0;
    foreach ($a as $k => $_ignored) { if ($k !== $i++) return false; }
    return true;
}

function popup_merge(array $base, array $over): array {
    foreach ($over as $k => $v) {
        if (is_array($v) && isset($base[$k]) && is_array($base[$k]) && !popup_is_list($v)) {
            $base[$k] = popup_merge($base[$k], $v);
        } else {
            $base[$k] = $v;
        }
    }
    return $base;
}

function popup_log(string $msg): void {
    $p = popup_paths()['log'];
    if (@filesize($p) > 262144) { @rename($p, $p . '.old'); }
    @file_put_contents($p, '[' . date('Y-m-d H:i:s') . '] ' . $msg . PHP_EOL, FILE_APPEND | LOCK_EX);
}

function popup_write_atomic(string $path, string $data): bool {
    $tmp = $path . '.tmp' . getmypid();
    if (@file_put_contents($tmp, $data, LOCK_EX) === false) return false;
    @chmod($tmp, 0644);
    if (!@rename($tmp, $path)) { @unlink($tmp); return false; }
    return true;
}

function popup_read_json(string $path, $fallback = []) {
    if (!is_file($path)) return $fallback;
    $j = json_decode((string) @file_get_contents($path), true);
    return is_array($j) ? $j : $fallback;
}

/** Gizli ayar dosyasını (public_html dışında) bul */
function popup_secret_file(): string {
    $try = [
        POPUP_DIR . '/../../popup-config.php',
        POPUP_DIR . '/../../../popup-config.php',
        POPUP_DIR . '/../../../../popup-config.php',
        POPUP_DIR . '/../popup-config.php',
    ];
    foreach ($try as $f) { if (is_file($f)) return $f; }
    return '';
}

function popup_base_url(): string {
    $https = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off')
          || (($_SERVER['HTTP_X_FORWARDED_PROTO'] ?? '') === 'https');
    $host  = $_SERVER['HTTP_HOST'] ?? 'localhost';
    return ($https ? 'https://' : 'http://') . $host . '/popup';
}

/* ================= #12  ŞEMA MİGRASYONU ================= */
/**
 * Eski (tek otomatik popup) yapısını yeni autos[] yapısına taşır.
 * Idempotent: birden çok kez çalıştırmak zararsızdır.
 */
function popup_migrate(array $cfg): array {
    if (!isset($cfg['autos']) || !is_array($cfg['autos'])) $cfg['autos'] = [];

    // autos boşsa ve eski alanlar varsa: eski tek otomatik popup'ı taşı
    if (!$cfg['autos']) {
        $a = popup_auto_default();
        $a['id']        = 'auto';
        $a['name']      = 'Otomatik servis popup';
        $a['enabled']   = array_key_exists('auto_enabled', $cfg)  ? (bool) $cfg['auto_enabled'] : true;
        $a['priority']  = (int) ($cfg['auto_priority'] ?? 10);
        $a['theme']     = (string) ($cfg['auto_theme'] ?? '');
        if (isset($cfg['auto_schedule']))  $a['schedule']  = $cfg['auto_schedule'];
        if (isset($cfg['auto_trigger']))   $a['trigger']   = $cfg['auto_trigger'];
        if (isset($cfg['auto_targeting'])) $a['targeting'] = $cfg['auto_targeting'];
        foreach (['count','sort','skip_zero','category_mode','category_manual','skip_categories',
                  'currency','currency_before','decimals','price_multiplier','order_url',
                  'subtitle','badge_template','badge_strip_emoji'] as $k) {
            if (array_key_exists($k, $cfg)) $a[$k] = $cfg[$k];
        }
        $cfg['autos'] = [$a];
    }

    // her otomatik popup'ı şablonla tamamla
    foreach ($cfg['autos'] as $i => $a) {
        if (!is_array($a)) { unset($cfg['autos'][$i]); continue; }
        $cfg['autos'][$i] = popup_merge(popup_auto_default(), $a);
        if (($cfg['autos'][$i]['id'] ?? '') === '') $cfg['autos'][$i]['id'] = 'a' . substr(md5((string) $i . microtime()), 0, 7);
    }
    $cfg['autos'] = array_values($cfg['autos']);

    // her manuel popup'ı şablonla tamamla (segment gibi yeni alanlar için)
    if (!isset($cfg['popups']) || !is_array($cfg['popups'])) $cfg['popups'] = [];
    foreach ($cfg['popups'] as $i => $p) {
        if (!is_array($p)) { unset($cfg['popups'][$i]); continue; }
        $cfg['popups'][$i] = popup_merge(popup_manual_default(), $p);
    }
    $cfg['popups'] = array_values($cfg['popups']);

    $cfg['schema_version'] = POPUP_SCHEMA;
    return $cfg;
}

/** defaults + settings.json + popup-config.php + migrasyon */
function popup_config(): array {
    static $persisted = false;

    $cfg = popup_defaults();
    $sf  = popup_paths()['settings'];
    $raw = popup_read_json($sf, []);
    if ($raw) $cfg = popup_merge($cfg, $raw);

    $secret = popup_secret_file();
    if ($secret !== '') {
        $o = @require $secret;
        if (is_array($o)) $cfg = popup_merge($cfg, $o);
    }

    $needMigrate = ((int) ($raw['schema_version'] ?? 0)) < POPUP_SCHEMA || empty($raw['autos']);
    $cfg = popup_migrate($cfg);

    // varsayılan uç adresleri
    if (($cfg['client']['stats']['url'] ?? '') === '') {
        $cfg['client']['stats']['url'] = popup_base_url() . '/stat.php';
    }
    if (($cfg['client']['killCheck']['url'] ?? '') === '') {
        $cfg['client']['killCheck']['url'] = popup_base_url() . '/data.php?state=1';
    }

    // migrasyonu bir kez kalıcı yaz (sadece dosya zaten varsa)
    if ($needMigrate && !$persisted && is_file($sf)) {
        $persisted = true;
        $keep = ['schema_version' => POPUP_SCHEMA, 'autos' => $cfg['autos'], 'popups' => $cfg['popups']];
        $new  = popup_merge($raw, $keep);
        // eski tek-auto alanlarını temizle
        foreach (['auto_enabled','auto_priority','auto_theme','auto_schedule','auto_trigger','auto_targeting',
                  'count','sort','skip_zero','category_mode','category_manual','skip_categories',
                  'currency','currency_before','decimals','price_multiplier','order_url',
                  'subtitle','badge_template','badge_strip_emoji',
                  'manual_mode','manual_badge','manual_title','manual_subtitle','manual_services'] as $k) {
            unset($new[$k]);
        }
        if (popup_write_atomic($sf, json_encode($new, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_PRETTY_PRINT))) {
            popup_log('Sema v' . POPUP_SCHEMA . ' migrasyonu yapildi (' . count($cfg['autos']) . ' otomatik, ' . count($cfg['popups']) . ' manuel).');
        }
    }

    return $cfg;
}

/* ================= #9  DENETİM GÜNLÜĞÜ ================= */
function popup_audit(string $action, string $detail = ''): void {
    $f = popup_paths()['audit'];
    $all = popup_read_json($f, []);
    if (!popup_is_list($all)) $all = [];
    array_unshift($all, [
        'ts'     => time(),
        'ip'     => (string) ($_SERVER['REMOTE_ADDR'] ?? 'cli'),
        'action' => $action,
        'detail' => mb_substr($detail, 0, 300),
    ]);
    if (count($all) > 200) $all = array_slice($all, 0, 200);
    popup_write_atomic($f, json_encode($all, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES));
}

function popup_audit_list(int $n = 40): array {
    $all = popup_read_json(popup_paths()['audit'], []);
    return popup_is_list($all) ? array_slice($all, 0, $n) : [];
}

/* ================= #8  AYAR YEDEKLEME / GERİ ALMA ================= */
/** Mevcut settings.json'un anlık görüntüsünü al (kayıttan ÖNCE çağrılır) */
function popup_backup_snapshot(string $note = ''): void {
    $sf = popup_paths()['settings'];
    if (!is_file($sf)) return;
    $data = popup_read_json($sf, null);
    if (!is_array($data)) return;

    $f   = popup_paths()['history'];
    $all = popup_read_json($f, []);
    if (!popup_is_list($all)) $all = [];

    // aynı içerik peş peşe yedeklenmesin
    $hash = md5(json_encode($data));
    if (isset($all[0]['hash']) && $all[0]['hash'] === $hash) return;

    array_unshift($all, ['ts' => time(), 'note' => mb_substr($note, 0, 120), 'hash' => $hash, 'data' => $data]);
    if (count($all) > 20) $all = array_slice($all, 0, 20);
    popup_write_atomic($f, json_encode($all, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES));
}

/** Yedek listesi (içerik olmadan — panelde listelemek için) */
function popup_backup_list(): array {
    $all = popup_read_json(popup_paths()['history'], []);
    if (!popup_is_list($all)) return [];
    $out = [];
    foreach ($all as $b) {
        $d = $b['data'] ?? [];
        $out[] = [
            'ts'     => (int) ($b['ts'] ?? 0),
            'note'   => (string) ($b['note'] ?? ''),
            'autos'  => is_array($d['autos'] ?? null)  ? count($d['autos'])  : 0,
            'popups' => is_array($d['popups'] ?? null) ? count($d['popups']) : 0,
        ];
    }
    return $out;
}

/** Bir yedeği geri yükle */
function popup_backup_restore(int $ts): bool {
    $all = popup_read_json(popup_paths()['history'], []);
    if (!popup_is_list($all)) return false;
    foreach ($all as $b) {
        if ((int) ($b['ts'] ?? 0) === $ts && is_array($b['data'] ?? null)) {
            popup_backup_snapshot('geri alma öncesi');
            $ok = popup_write_atomic(popup_paths()['settings'],
                json_encode($b['data'], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_PRETTY_PRINT));
            if ($ok) popup_audit('restore', 'yedek: ' . date('Y-m-d H:i', $ts));
            return $ok;
        }
    }
    return false;
}

/* ================= #7  ACİL DURDURMA ================= */
function popup_kill_get(): array {
    $k = popup_read_json(popup_paths()['kill'], []);
    return ['off' => !empty($k['off']), 'ts' => (int) ($k['ts'] ?? 0), 'note' => (string) ($k['note'] ?? '')];
}

function popup_kill_set(bool $off, string $note = ''): bool {
    $ok = popup_write_atomic(popup_paths()['kill'],
        json_encode(['off' => $off, 'ts' => time(), 'note' => mb_substr($note, 0, 200)], JSON_UNESCAPED_UNICODE));
    if ($ok) popup_audit($off ? 'kill_on' : 'kill_off', $note);
    return $ok;
}

/* ---------------- METİN / FİYAT ---------------- */
function popup_strip_emoji(string $s): string {
    $s = preg_replace('/[\x{1F000}-\x{1FAFF}\x{1F1E6}-\x{1F1FF}\x{2190}-\x{21FF}\x{2300}-\x{27BF}'
                    . '\x{2B00}-\x{2BFF}\x{FE00}-\x{FE0F}\x{200D}\x{20E3}\x{E0000}-\x{E007F}]/u', '', $s);
    return trim(preg_replace('/\s{2,}/u', ' ', $s));
}

function popup_clean_title(string $s): string {
    $s = popup_strip_emoji($s);
    $s = preg_split('/\s*[|\x{2013}\x{2014}\x{00BB}\x{203A}~>]+\s*/u', $s)[0] ?? $s;
    $s = preg_replace('/\[[^\]]*\]|\([^)]*\)|\{[^}]*\}/u', ' ', $s);
    $s = preg_replace('/^[\s\-\x{2013}\x{2014}:,.\x{00B7}]+|[\s\-\x{2013}\x{2014}:,.\x{00B7}]+$/u', '', $s);
    return trim(preg_replace('/\s{2,}/u', ' ', $s));
}

/** Fiyatı biçimlendir — $a: otomatik popup ayarları */
function popup_price(array $a, $rate): string {
    $v = (float) $rate * (float) $a['price_multiplier'];
    $d = $a['decimals'];
    if ($d === 'auto') { $d = ($v > 0 && $v < 0.01) ? 4 : 2; }
    $n = number_format($v, (int) $d, '.', '');
    return !empty($a['currency_before']) ? $a['currency'] . $n : $n . ' ' . $a['currency'];
}

/* ---------------- API ---------------- */
function popup_api(array $c, array $fields) {
    if (function_exists('curl_init')) {
        $ch = curl_init($c['api_url']);
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true, CURLOPT_POST => true,
            CURLOPT_POSTFIELDS => http_build_query($fields),
            CURLOPT_TIMEOUT => (int) $c['timeout'], CURLOPT_CONNECTTIMEOUT => 10,
            CURLOPT_FOLLOWLOCATION => true, CURLOPT_SSL_VERIFYPEER => true,
            CURLOPT_USERAGENT => 'popup-cron/4.0',
        ]);
        $body = curl_exec($ch); $err = curl_error($ch);
        $code = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);
        if ($body === false) return ['err' => 'cURL: ' . $err];
        if ($code < 200 || $code >= 300) return ['err' => 'API HTTP ' . $code];
        return ['body' => (string) $body];
    }
    $ctx = stream_context_create(['http' => [
        'method' => 'POST', 'header' => "Content-Type: application/x-www-form-urlencoded\r\n",
        'content' => http_build_query($fields), 'timeout' => (int) $c['timeout'],
    ]]);
    $body = @file_get_contents($c['api_url'], false, $ctx);
    if ($body === false) return ['err' => 'API istegi basarisiz (file_get_contents).'];
    return ['body' => $body];
}

/**
 * Servis listesini TEK sefer çeker (tüm otomatik popuplar paylaşır).
 * @return array ['list'=>array] | ['err'=>string]
 */
function popup_fetch_services(array $c): array {
    if (trim((string) $c['api_url']) === '' || trim((string) $c['api_key']) === ''
        || strpos((string) $c['api_key'], 'BURAYA') !== false) {
        return ['err' => 'api_url / api_key ayarlanmamis (popup-config.php).'];
    }
    $r = popup_api($c, ['key' => $c['api_key'], 'action' => 'services']);
    if (isset($r['err'])) return ['err' => $r['err']];

    $list = json_decode($r['body'], true);
    if (!is_array($list))      return ['err' => 'API JSON dondurmedi: ' . substr($r['body'], 0, 200)];
    if (isset($list['error'])) return ['err' => 'API hatasi: ' . (is_string($list['error']) ? $list['error'] : json_encode($list['error']))];
    if (!$list || !isset($list[0])) return ['err' => 'Servis listesi bos geldi.'];
    return ['list' => $list];
}

/* ---------------- NORMALİZASYON ---------------- */
function popup_norm_schedule($s): array {
    $d = popup_schedule_default();
    if (!is_array($s)) return $d;
    $hf = (string) ($s['hours']['from'] ?? '');
    $ht = (string) ($s['hours']['to'] ?? '');
    $d['hours']['from'] = preg_match('/^\d{1,2}:\d{2}$/', $hf) ? $hf : '';
    $d['hours']['to']   = preg_match('/^\d{1,2}:\d{2}$/', $ht) ? $ht : '';
    $days = [];
    foreach ((array) ($s['days'] ?? []) as $n) {
        $n = (int) $n;
        if ($n >= 0 && $n <= 6 && !in_array($n, $days, true)) $days[] = $n;
    }
    sort($days);
    $d['days'] = $days;
    $df = (string) ($s['dateFrom'] ?? ''); $dt = (string) ($s['dateTo'] ?? '');
    $d['dateFrom'] = preg_match('/^\d{4}-\d{2}-\d{2}$/', $df) ? $df : '';
    $d['dateTo']   = preg_match('/^\d{4}-\d{2}-\d{2}$/', $dt) ? $dt : '';
    return $d;
}

function popup_norm_trigger($t): array {
    $d = popup_trigger_default();
    if (!is_array($t)) return $d;
    $type = (string) ($t['type'] ?? 'load');
    $d['type'] = in_array($type, ['load', 'exit', 'scroll'], true) ? $type : 'load';
    $d['scrollPercent'] = max(1, min(100, (int) ($t['scrollPercent'] ?? 50)));
    return $d;
}

function popup_norm_targeting($t): array {
    $d = popup_targeting_default();
    if (!is_array($t)) return $d;
    $dev = [];
    foreach ((array) ($t['devices'] ?? []) as $x) {
        $x = (string) $x;
        if (($x === 'mobile' || $x === 'desktop') && !in_array($x, $dev, true)) $dev[] = $x;
    }
    $d['devices'] = $dev;
    $src = [];
    foreach ((array) ($t['sources'] ?? []) as $x) { $x = trim((string) $x); if ($x !== '') $src[] = $x; }
    $d['sources'] = $src;
    return $d;
}

/** Segmenti normalize et. Hiç kısıt yoksa null döner (istemcide hiç kontrol edilmez). */
function popup_norm_segment($s) {
    if (!is_array($s)) return null;
    $d = popup_segment_default();
    $login = (string) ($s['login'] ?? '');
    $d['login'] = in_array($login, ['in', 'out'], true) ? $login : '';
    foreach (['balanceMin','balanceMax','ordersMin','ordersMax'] as $k) {
        $v = $s[$k] ?? '';
        $d[$k] = ($v === '' || $v === null) ? '' : (float) $v;
    }
    $vis = (string) ($s['visitor'] ?? '');
    $d['visitor']  = in_array($vis, ['new', 'returning'], true) ? $vis : '';
    $d['visitMin'] = max(0, (int) ($s['visitMin'] ?? 0));
    $cs = [];
    foreach ((array) ($s['countries'] ?? []) as $x) {
        $x = strtoupper(trim((string) $x));
        if (preg_match('/^[A-Z]{2}$/', $x) && !in_array($x, $cs, true)) $cs[] = $x;
    }
    $d['countries'] = $cs;

    // hiçbir kısıt yoksa null
    $empty = $d['login'] === '' && $d['visitor'] === '' && $d['visitMin'] === 0 && !$d['countries']
          && $d['balanceMin'] === '' && $d['balanceMax'] === '' && $d['ordersMin'] === '' && $d['ordersMax'] === '';
    return $empty ? null : $d;
}

function popup_norm_rules($r) {
    if ($r === null || $r === '' || (is_array($r) && !$r) || !is_array($r)) return null;
    return [
        'perDay'            => max(0, (int) ($r['perDay']            ?? 0)),
        'perSession'        => max(0, (int) ($r['perSession']        ?? 0)),
        'minGapMinutes'     => max(0, (int) ($r['minGapMinutes']     ?? 0)),
        'maxTotal'          => max(0, (int) ($r['maxTotal']          ?? 0)),
        'clickCooldownDays' => max(0, (int) ($r['clickCooldownDays'] ?? 0)),
    ];
}

function popup_norm_pages($p) {
    if (!is_array($p)) return null;
    return [
        'mode'     => (($p['mode'] ?? 'exclude') === 'include') ? 'include' : 'exclude',
        'patterns' => array_values(array_filter(array_map('strval', (array) ($p['patterns'] ?? [])))),
    ];
}

function popup_norm_countdown($v): string {
    $v = trim((string) $v);
    return preg_match('/^\d{4}-\d{2}-\d{2}T\d{2}:\d{2}$/', $v) ? $v : '';
}

/** Her popup'ta ortak olan alanları paylaşılan biçime çevir */
function popup_common_fields(array $x, int $defPriority): array {
    return [
        'priority'  => (int) ($x['priority'] ?? $defPriority),
        'theme'     => (string) ($x['theme'] ?? ''),
        'schedule'  => popup_norm_schedule($x['schedule']  ?? null),
        'trigger'   => popup_norm_trigger($x['trigger']    ?? null),
        'targeting' => popup_norm_targeting($x['targeting'] ?? null),
        'segment'   => popup_norm_segment($x['segment']    ?? null),
        'rules'     => popup_norm_rules($x['rules']        ?? null),
        'pages'     => popup_norm_pages($x['pages']        ?? null),
    ];
}

/* ================= #10  OTOMATİK POPUP (paylaşılan listeden) ================= */
/**
 * Tek bir otomatik popup'ı hazır servis listesinden kurar.
 * @return array ['ok'=>bool,'msg'=>string,'payload'=>array|null,'categories'=>array]
 */
function popup_build_auto(array $list, array $a, array $c): array {
    $out = ['ok' => false, 'msg' => '', 'payload' => null, 'categories' => []];
    $a = popup_merge(popup_auto_default(), $a);

    $skip = array_map('mb_strtolower', (array) $a['skip_categories']);
    $items = [];
    foreach ($list as $s) {
        if (!is_array($s)) continue;
        $cat  = trim((string) ($s['category'] ?? ''));
        $name = trim((string) ($s['name'] ?? ''));
        $id   = (string) ($s['service'] ?? $s['id'] ?? '');
        $rate = $s['rate'] ?? null;
        if ($cat === '' || $name === '' || $id === '') continue;
        if (!empty($a['skip_zero']) && (float) $rate <= 0) continue;
        $lc = mb_strtolower($cat);
        foreach ($skip as $k) { if ($k !== '' && mb_strpos($lc, $k) !== false) { continue 2; } }
        $items[] = ['id' => $id, 'name' => $name, 'cat' => $cat, 'rate' => $rate];
    }
    if (!$items) { $out['msg'] = 'filtrelerden sonra servis kalmadi'; return $out; }

    $seen = [];
    foreach ($items as $it) { $seen[$it['cat']] = true; if (count($seen) >= 40) break; }
    $out['categories'] = array_keys($seen);

    switch ($a['category_mode']) {
        case 'manual':
            $target = trim((string) $a['category_manual']);
            if ($target === '') { $out['msg'] = "kategori modu 'elle' ama kategori adi bos"; return $out; }
            break;
        case 'newest':
            $best = null; $bestId = -1;
            foreach ($items as $it) {
                $n = (int) preg_replace('/\D/', '', $it['id']);
                if ($n > $bestId) { $bestId = $n; $best = $it['cat']; }
            }
            $target = $best;
            break;
        default:
            $target = $items[0]['cat'];
    }

    // 'manual' modda kısmi eşleşmeye de izin ver (tam ad yazmak zorunda kalmasın)
    $picked = [];
    foreach ($items as $it) { if ($it['cat'] === $target) $picked[] = $it; }
    if (!$picked && $a['category_mode'] === 'manual') {
        $lt = mb_strtolower($target);
        foreach ($items as $it) { if (mb_strpos(mb_strtolower($it['cat']), $lt) !== false) { $picked[] = $it; } }
        if ($picked) $target = $picked[0]['cat'];
    }
    if (!$picked) { $out['msg'] = 'kategori bulunamadi: ' . $target; return $out; }

    if ($a['sort'] === 'cheapest') {
        usort($picked, function ($x, $y) { return (float) $x['rate'] <=> (float) $y['rate']; });
    }
    $picked = array_slice($picked, 0, max(1, (int) $a['count']));

    $badge = !empty($a['badge_strip_emoji']) ? popup_strip_emoji($target) : $target;
    $badge = str_replace('{category}', $badge, $a['badge_template'] !== '' ? $a['badge_template'] : '{category}');
    $title = popup_clean_title($target);
    if ($title === '') $title = $badge;

    $services = [];
    foreach ($picked as $it) {
        $services[] = [
            'id'    => $it['id'],
            'name'  => $it['name'],
            'price' => popup_price($a, $it['rate']),
            'url'   => str_replace('{id}', rawurlencode($it['id']), $a['order_url']),
        ];
    }

    $out['payload'] = array_merge([
        'pid'      => (string) $a['id'],
        'kind'     => 'services',
        'updated'  => time(),
        'category' => $target,
        'badge'    => $badge,
        'title'    => $title,
        'subtitle' => str_replace('{category}', $title, $a['subtitle']),
        'services' => $services,
    ], popup_common_fields($a, 10));

    $out['ok']  = true;
    $out['msg'] = $a['id'] . ':' . $target . '(' . count($services) . ')';
    return $out;
}

/* ---------------- MANUEL POPUP ---------------- */
function popup_build_manual(array $m, array $c): ?array {
    $m = popup_merge(popup_manual_default(), $m);
    $title    = trim((string) $m['title']);
    $subtitle = trim((string) $m['subtitle']);
    $btnText  = trim((string) $m['btn_text']);
    $btnUrl   = trim((string) $m['btn_url']);
    if ($title === '' && $subtitle === '' && $btnText === '') return null;

    return array_merge([
        'pid'       => (string) $m['id'],
        'kind'      => 'content',
        'updated'   => time(),
        'badge'     => trim((string) $m['badge']),
        'skin'      => popup_skin_valid((string) $m['skin']),
        'icon'      => trim((string) $m['icon']),
        'items'     => popup_parse_items((string) $m['items']),
        'title'     => $title,
        'subtitle'  => $subtitle,
        'image'     => trim((string) $m['image']),
        'button'    => ($btnText !== '' && $btnUrl !== '') ? ['text' => $btnText, 'url' => $btnUrl] : null,
        'countdown' => popup_norm_countdown($m['countdown']),
        'coupon'    => (trim((string) $m['coupon']) !== '')
            ? ['code' => trim((string) $m['coupon']), 'label' => trim((string) $m['coupon_label'])]
            : null,
    ], popup_common_fields($m, 20));
}

/* ---------------- SON BAŞARILI OTOMATİK VERİ (stale-while-error) ---------------- */
function popup_autolast_read(): array { return popup_read_json(popup_paths()['autolast'], []); }

function popup_autolast_write(array $byPid): void {
    popup_write_atomic(popup_paths()['autolast'],
        json_encode($byPid, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES));
}

/* ================= TÜM POPUPLARI TOPLA ================= */
/**
 * @return array ['ok'=>bool,'msg'=>string,'popups'=>array,'autos'=>array,'categories'=>array,'stale'=>bool]
 */
function popup_assemble(array $c): array {
    $popups = []; $notes = []; $cats = []; $autoPayloads = []; $stale = false;

    $activeAutos = [];
    foreach ((array) $c['autos'] as $a) {
        if (is_array($a) && !empty($a['enabled']) && ($a['id'] ?? '') !== '') $activeAutos[] = $a;
    }

    if ($activeAutos) {
        $fetch = popup_fetch_services($c);

        if (isset($fetch['list'])) {
            $built = [];
            foreach ($activeAutos as $a) {
                $r = popup_build_auto($fetch['list'], $a, $c);
                if (!$cats && $r['categories']) $cats = $r['categories'];
                if ($r['ok']) {
                    $popups[] = $r['payload'];
                    $autoPayloads[] = $r['payload'];
                    $built[$a['id']] = $r['payload'];
                    $notes[] = $r['msg'];
                } else {
                    $notes[] = 'oto ' . $a['id'] . ' HATA(' . $r['msg'] . ')';
                }
            }
            // son başarılı veriyi sakla
            if ($built) {
                $cache = popup_autolast_read();
                foreach ($built as $pid => $pl) $cache[$pid] = $pl;
                popup_autolast_write($cache);
            }
        } else {
            /* --- API erişilemedi: son başarılı veriyle devam et --- */
            $cache = popup_autolast_read();
            $used  = 0;
            foreach ($activeAutos as $a) {
                $pid = (string) $a['id'];
                if (isset($cache[$pid]) && is_array($cache[$pid])) {
                    $pl = $cache[$pid];
                    // güncel ayarlarla (zamanlama/segment/tetik) tazele, içerik eski kalsın
                    $pl = array_merge($pl, popup_common_fields(popup_merge(popup_auto_default(), $a), 10));
                    $pl['stale'] = true;
                    $popups[] = $pl;
                    $autoPayloads[] = $pl;
                    $used++;
                }
            }
            $stale = $used > 0;
            $notes[] = 'API HATASI (' . $fetch['err'] . ')' . ($used ? " — {$used} otomatik popup son iyi veriyle korundu" : ' — otomatik popup yok');
        }
    } else {
        $notes[] = 'aktif otomatik popup yok';
    }

    // --- manuel popuplar ---
    $mc = 0;
    foreach ((array) $c['popups'] as $m) {
        if (!is_array($m) || empty($m['enabled'])) continue;
        $p = popup_build_manual($m, $c);
        if ($p === null || $p['pid'] === '') continue;
        $popups[] = $p; $mc++;
    }
    $notes[] = $mc . ' manuel';

    if (!$popups) {
        return ['ok' => false, 'msg' => 'Yayinlanacak aktif popup yok. (' . implode(' | ', $notes) . ')',
                'popups' => [], 'autos' => [], 'categories' => $cats, 'stale' => $stale];
    }

    usort($popups, function ($x, $y) { return ($x['priority'] ?? 0) <=> ($y['priority'] ?? 0); });

    return ['ok' => true, 'msg' => implode(' | ', $notes), 'popups' => $popups,
            'autos' => $autoPayloads, 'categories' => $cats, 'stale' => $stale];
}

/* ---------------- SÜRÜM TAKİBİ ---------------- */
function popup_version_id(array $p): string {
    $parts = [$p['pid'] ?? '', $p['category'] ?? ($p['title'] ?? '')];
    if (($p['kind'] ?? '') === 'services') {
        foreach ((array) ($p['services'] ?? []) as $s) { $parts[] = $s['id'] . ':' . $s['price']; }
    } else {
        $parts[] = ($p['title'] ?? '') . '|' . ($p['button']['url'] ?? '');
    }
    return substr(md5(implode('#', $parts)), 0, 10);
}

function popup_version_record(string $vid, array $p): void {
    $f = popup_paths()['versions'];
    $all = popup_read_json($f, []);
    $now = time();
    if (!isset($all[$vid])) {
        $all[$vid] = [
            'pid'      => $p['pid'] ?? '',
            'kind'     => $p['kind'] ?? '',
            'category' => $p['category'] ?? '',
            'title'    => $p['title'] ?? '',
            'badge'    => $p['badge'] ?? '',
            'services' => array_map(function ($s) {
                return ['id' => $s['id'], 'name' => $s['name'], 'price' => $s['price']];
            }, (array) ($p['services'] ?? [])),
            'first' => $now, 'last' => $now, 'runs' => 1,
        ];
    } else {
        $all[$vid]['last'] = $now;
        $all[$vid]['runs'] = ((int) ($all[$vid]['runs'] ?? 0)) + 1;
    }
    if (count($all) > 300) {
        uasort($all, function ($x, $y) { return ($y['last'] ?? 0) <=> ($x['last'] ?? 0); });
        $all = array_slice($all, 0, 300, true);
    }
    popup_write_atomic($f, json_encode($all, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES));
}

/* ---------------- YAYINLA ---------------- */
function popup_publish(array $c, array $popups, array $autos): array {
    $p = popup_paths();

    foreach ($popups as &$pp) {
        $pp['v'] = popup_version_id($pp);
        popup_version_record($pp['v'], $pp);
    }
    unset($pp);

    // services.json = ilk otomatik popup (geriye dönük uyum / data.php)
    $svcPayload = $autos ? $autos[0] : $popups[0];
    $json = json_encode($svcPayload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_PRETTY_PRINT);
    if ($json === false) return ['ok' => false, 'msg' => 'JSON olusturulamadi'];
    if (!popup_write_atomic($p['output'], $json)) return ['ok' => false, 'msg' => 'services.json yazilamadi (klasor izni 755 mi?)'];

    if (!is_file($p['template'])) return ['ok' => false, 'msg' => 'popup-template.js bulunamadi'];
    $tpl = (string) file_get_contents($p['template']);
    if (strpos($tpl, 'var POPUPS = null;') === false || strpos($tpl, 'var SETTINGS = null;') === false) {
        return ['ok' => false, 'msg' => 'popup-template.js eski surum (POPUPS/SETTINGS satiri yok)'];
    }

    $enc = function ($v) {
        $s = json_encode($v, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        return str_replace(['</', '<!--'], ['<\\/', '<\\u0021--'], $s);
    };

    $client = $c['client'];
    $client['enabled'] = !empty($c['enabled']);

    $js = str_replace('var POPUPS = null;',   'var POPUPS = '   . $enc(array_values($popups)) . ';', $tpl);
    $js = str_replace('var SETTINGS = null;', 'var SETTINGS = ' . $enc($client) . ';', $js);
    $skinCss = [];
    foreach (popup_skins() as $k => $v) { unset($v['t'], $v['n']); $skinCss[$k] = $v; }
    $js = str_replace('var SKINS = null;', 'var SKINS = ' . $enc($skinCss) . ';', $js);
    $js = "/* Otomatik uretildi: " . date('Y-m-d H:i:s') . " — elle duzenlemeyin. */\n" . $js;

    if (!popup_write_atomic($p['embed'], $js)) return ['ok' => false, 'msg' => 'embed.js yazilamadi'];
    return ['ok' => true, 'msg' => count($popups) . ' popup yayinda'];
}

/** Tek adımda: ayarları al, popupları topla, dosyaları yaz */
function popup_run(): array {
    $c = popup_config();

    if (empty($c['enabled'])) {
        popup_log('Ana salter kapali, guncelleme yapilmadi.');
        return ['ok' => false, 'msg' => 'Popup ana salteri kapali.', 'categories' => []];
    }

    $a = popup_assemble($c);
    if (!$a['ok']) {
        popup_log('HATA: ' . $a['msg']);
        return ['ok' => false, 'msg' => $a['msg'], 'categories' => $a['categories']];
    }
    $pub = popup_publish($c, $a['popups'], $a['autos']);
    if (!$pub['ok']) {
        popup_log('HATA: ' . $pub['msg']);
        return ['ok' => false, 'msg' => $pub['msg'], 'categories' => $a['categories']];
    }
    popup_log(($a['stale'] ? 'UYARI(eski veri) — ' : 'OK — ') . $a['msg'] . ' | ' . $pub['msg']);
    return ['ok' => true, 'msg' => $a['msg'] . ' | ' . $pub['msg'], 'stale' => $a['stale'],
            'popups' => $a['popups'], 'autos' => $a['autos'], 'categories' => $a['categories']];
}

/* ---------------- AYARLARI KAYDET ---------------- */
/** settings.json'a yazılabilecek alanlar (şifre/anahtar asla buraya girmez) */
function popup_settings_save(array $new, string $auditNote = ''): bool {
    $allow = ['schema_version', 'enabled', 'autos', 'popups', 'client'];
    $clean = [];
    foreach ($allow as $k) { if (array_key_exists($k, $new)) $clean[$k] = $new[$k]; }
    $clean['schema_version'] = POPUP_SCHEMA;

    // kayıttan önce yedek al (#8)
    popup_backup_snapshot($auditNote);

    $sf = popup_paths()['settings'];
    $existing = popup_read_json($sf, []);
    $merged   = popup_merge($existing, $clean);

    $ok = popup_write_atomic($sf,
        json_encode($merged, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_PRETTY_PRINT));
    if ($ok && $auditNote !== '') popup_audit('save', $auditNote);
    return $ok;
}
