<?php
/**
 * Payment spam guard - panelbaku.com adminapi v2
 *
 * Cron ilə hər 2 dəqiqədən bir işlədin:
 *   0-58/2 * * * * php /path/payment_guard.php >> /var/log/payment_guard.log 2>&1
 *
 * Eyni istifadəçi eyni ödəniş üsulu ilə WINDOW saniyə içində THRESHOLD-dan
 * çox cəhd edibsə: Telegram-a bildiriş göndərir və həmin üsulu istifadəçinin
 * disallow_methods siyahısına əlavə edir.
 *
 * Mühit dəyişənləri (açar kodda saxlanmır):
 *   PANEL_API_KEY, TG_BOT_TOKEN, TG_CHAT_ID           (məcburi)
 *   PANEL_BASE   default https://panelbaku.com/adminapi/v2
 *   WINDOW=180   THRESHOLD=5   PAGE_LIMIT=100   MAX_PAGES=10
 *   METHOD_IDS   JSON: {"Perfect Money USD":50,"Card":51}
 *                (payments siyahısında üsul ADI gəlir, disallow_methods isə ID istəyir)
 *   STATE_FILE   default payment_guard_state.json
 *
 * İstifadə:
 *   php payment_guard.php --dry-run     # heç nəyi dəyişmir, Telegram göndərmir
 *   php payment_guard.php --discover    # payments/users cavabının nümunəsini göstərir
 */

declare(strict_types=1);

$cfg = [
    'base'      => rtrim(getenv('PANEL_BASE') ?: 'https://panelbaku.com/adminapi/v2', '/'),
    'key'       => getenv('PANEL_API_KEY') ?: '',
    'tg_token'  => getenv('TG_BOT_TOKEN') ?: '',
    'tg_chat'   => getenv('TG_CHAT_ID') ?: '',
    'window'    => (int)(getenv('WINDOW') ?: 180),
    'threshold' => (int)(getenv('THRESHOLD') ?: 5),
    'limit'     => (int)(getenv('PAGE_LIMIT') ?: 100),
    'max_pages' => (int)(getenv('MAX_PAGES') ?: 10),
    'state'     => getenv('STATE_FILE') ?: __DIR__ . '/payment_guard_state.json',
    // Üsul adı => disallow_methods ID. METHOD_IDS ilə əlavə/əvəz edilə bilər.
    'methods'   => (json_decode(getenv('METHOD_IDS') ?: '{}', true) ?: []) + [
        'Epoint #2' => 216157,
    ],
];
$dryRun   = in_array('--dry-run', $argv, true);
$discover = in_array('--discover', $argv, true);

function logmsg(string $m): void { echo '[' . date('Y-m-d H:i:s') . "] $m\n"; }

/** Panel API çağırışı. Uğursuzluqda Exception atır. */
function api(array $cfg, string $method, string $path, ?array $body = null): array
{
    for ($try = 1; $try <= 3; $try++) {
        $ch = curl_init($cfg['base'] . '/' . ltrim($path, '/'));
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT        => 30,
            CURLOPT_CUSTOMREQUEST  => $method,
            CURLOPT_HTTPHEADER     => ['Content-Type: application/json', 'X-Api-Key: ' . $cfg['key']],
        ]);
        if ($body !== null) {
            curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($body, JSON_UNESCAPED_UNICODE));
        }
        $raw  = curl_exec($ch);
        $err  = curl_error($ch);
        $code = (int)curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        if ($raw !== false && $code < 500 && $code !== 429) {
            $json = json_decode((string)$raw, true);
            if (!is_array($json)) {
                throw new RuntimeException("$method $path: JSON deyil (HTTP $code)");
            }
            if ($code >= 400 || (int)($json['error_code'] ?? 0) !== 0) {
                throw new RuntimeException("$method $path: HTTP $code " . ($json['error_message'] ?? ''));
            }
            return $json;
        }
        if ($try < 3) sleep(2 ** $try);
    }
    throw new RuntimeException("$method $path uğursuz: " . ($err ?: "HTTP $code"));
}

function telegram(array $cfg, string $text): void
{
    if ($cfg['tg_token'] === '' || $cfg['tg_chat'] === '') {
        logmsg("Telegram ayarlı deyil: " . strip_tags($text));
        return;
    }
    $ch = curl_init("https://api.telegram.org/bot{$cfg['tg_token']}/sendMessage");
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_TIMEOUT        => 20,
        CURLOPT_POSTFIELDS     => [
            'chat_id' => $cfg['tg_chat'], 'text' => $text,
            'parse_mode' => 'HTML', 'disable_web_page_preview' => 'true',
        ],
    ]);
    $res = curl_exec($ch);
    if ($res === false || (json_decode((string)$res, true)['ok'] ?? false) !== true) {
        logmsg('Telegram göndərilmədi: ' . ($res === false ? curl_error($ch) : $res));
    }
    curl_close($ch);
}

function h(string $s): string { return htmlspecialchars($s, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'); }

/** Son $window saniyənin ödənişlərini səhifə-səhifə çəkir (yeni -> köhnə). */
function fetchRecentPayments(array $cfg, int $now): array
{
    $out = [];
    for ($page = 0; $page < $cfg['max_pages']; $page++) {
        $offset = $page * $cfg['limit'];
        $res  = api($cfg, 'GET', "payments?offset=$offset&limit={$cfg['limit']}");
        $list = $res['data']['list'] ?? [];
        if (!$list) break;
        $oldestInPage = PHP_INT_MAX;
        foreach ($list as $p) {
            $ts = (int)($p['created_timestamp'] ?? 0);
            if ($ts === 0 && !empty($p['created'])) $ts = (int)strtotime($p['created']);
            $oldestInPage = min($oldestInPage, $ts);
            if ($ts >= $now - $cfg['window']) { $p['_ts'] = $ts; $out[] = $p; }
        }
        // Siyahı yenidən köhnəyə sıralıdırsa, pəncərədən çıxdıqda dayan.
        if ($oldestInPage < $now - $cfg['window'] || count($list) < $cfg['limit']) break;
    }
    return $out;
}

/** Üsulu istifadəçinin disallow_methods siyahısına əlavə edir. true = yeni əlavə olundu. */
function disallowMethod(array $cfg, array $payUser, int $methodId, bool $dryRun): bool
{
    $uid = (int)$payUser['user_ID'];

    // Mövcud profili oxumağa çalış ki, edit digər sahələri silməsin.
    $profile = [];
    try {
        $profile = api($cfg, 'GET', "users/$uid/edit")['data'] ?? [];
    } catch (Throwable $e) {
        logmsg("users/$uid/edit oxunmadı ({$e->getMessage()}), ödəniş məlumatından istifadə olunur");
    }
    if (!$profile) {
        $profile = ['username' => $payUser['username'] ?? '', 'email' => $payUser['user_email'] ?? ''];
    }

    $disallow = array_map('intval', $profile['disallow_methods'] ?? []);
    if (in_array($methodId, $disallow, true)) return false;
    $disallow[] = $methodId;

    $allow = array_values(array_diff(array_map('intval', $profile['allow_methods'] ?? []), [$methodId]));

    $body = array_intersect_key($profile, array_flip([
        'username', 'email', 'first_name', 'last_name', 'phone', 'whatsapp',
        'telegram', 'website', 'skype', 'invoice_details', 'hidden_services',
    ]));
    $body['allow_methods']    = $allow;
    $body['disallow_methods'] = array_values(array_unique($disallow));

    if ($dryRun) { logmsg("[dry-run] POST users/$uid/edit " . json_encode($body)); return true; }
    api($cfg, 'POST', "users/$uid/edit", $body);
    return true;
}

// ---------------------------------------------------------------- main
if ($cfg['key'] === '') { fwrite(STDERR, "PANEL_API_KEY təyin edilməyib\n"); exit(1); }

if ($discover) {
    $p = api($cfg, 'GET', 'payments?offset=0&limit=3');
    echo "PAYMENTS:\n" . json_encode($p, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE) . "\n";
    exit(0);
}

// Üst-üstə düşən cron icralarının qarşısını al.
$lock = fopen($cfg['state'] . '.lock', 'c');
if (!$lock || !flock($lock, LOCK_EX | LOCK_NB)) { logmsg('Başqa proses işləyir, çıxılır'); exit(0); }

$state = is_file($cfg['state']) ? (json_decode((string)file_get_contents($cfg['state']), true) ?: []) : [];
$state['blocked'] = $state['blocked'] ?? [];

try {
    $now      = time();
    $payments = fetchRecentPayments($cfg, $now);

    $groups = [];
    foreach ($payments as $p) {
        $uid = $p['user']['user_ID'] ?? null;
        $m   = (string)($p['method'] ?? '');
        if ($uid === null || $m === '') continue;
        $key = "$uid|$m";
        $groups[$key]['user']  = $p['user'];
        $groups[$key]['method'] = $m;
        $groups[$key]['count'] = ($groups[$key]['count'] ?? 0) + 1;
    }

    foreach ($groups as $key => $g) {
        if ($g['count'] <= $cfg['threshold'] || isset($state['blocked'][$key])) continue;

        $name     = (string)($g['user']['username'] ?? $g['user']['user_ID']);
        $methodId = $cfg['methods'][$g['method']] ?? null;
        logmsg("SPAM user=$name method={$g['method']} count={$g['count']}");

        if ($methodId === null) {
            $action = "⚠️ Üsulun ID-si METHOD_IDS-də yoxdur, deaktiv edilmədi";
            $ok = false;
        } else {
            try {
                $changed = disallowMethod($cfg, $g['user'], (int)$methodId, $dryRun);
                $action  = $changed ? '✅ Ödəniş üsulu deaktiv edildi' : 'ℹ️ Artıq qadağan idi';
                $ok = true;
            } catch (Throwable $e) {
                $action = '❌ Deaktiv edilə bilmədi: ' . h($e->getMessage());
                $ok = false;
            }
        }

        if (!$dryRun) {
            telegram($cfg,
                "🚨 <b>Ödəniş spam</b>\n"
                . "👤 İstifadəçi: <b>" . h($name) . "</b> (ID: " . (int)$g['user']['user_ID'] . ")\n"
                . "💳 Üsul: <b>" . h($g['method']) . "</b>\n"
                . "🔁 {$g['count']} cəhd / " . round($cfg['window'] / 60, 1) . " dəq\n$action");
        }
        if ($ok && !$dryRun) {
            $state['blocked'][$key] = ['user' => $name, 'count' => $g['count'], 'at' => $now];
        }
    }
    logmsg(count($payments) . ' ödəniş yoxlanıldı, ' . count($groups) . ' qrup');
} catch (Throwable $e) {
    logmsg('XƏTA: ' . $e->getMessage());
}

if (!$dryRun) {
    file_put_contents($cfg['state'], json_encode($state, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE), LOCK_EX);
}
