<?php
declare(strict_types=1);

namespace TgWatch\Web;

use TgWatch\App;
use TgWatch\ChannelReader;
use TgWatch\Db;
use TgWatch\Dictionary;
use TgWatch\Flow;
use TgWatch\Health;
use TgWatch\Parser;
use TgWatch\Translator;
use TgWatch\Watcher;

/** JavaScript'siz web yönetim paneli. */
final class Admin
{
    private const MAX_FAILS = 5;
    private const LOCK_SECONDS = 900;
    private const IDLE_SECONDS = 7200;

    private Db $db;
    private string $nonce;
    private array $flash = [];

    public function __construct(private array $config)
    {
    }

    public function handle(): void
    {
        $this->nonce = base64_encode(random_bytes(16));
        $this->headers();
        $this->session();
        try {
            $this->db = App::db();
        } catch (\Throwable $e) {
            $this->page('Hata', '<p class="bad">Veritabanı açılamadı: ' . $this->e($e->getMessage()) . '</p><p>data_dir ayarını ve izinlerini kontrol edin.</p>', false);
            return;
        }
        $p = (string)($_GET['p'] ?? 'dash');

        if (!$this->loggedIn()) {
            $this->login();
            return;
        }
        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            if (!hash_equals((string)($_SESSION['csrf'] ?? ''), (string)($_POST['csrf'] ?? ''))) {
                http_response_code(400);
                $this->page('Hata', '<p class="bad">Oturum doğrulaması başarısız (CSRF). Sayfayı yenileyip tekrar deneyin.</p>');
                return;
            }
            $this->post($p);
            return;
        }
        match ($p) {
            'jobs' => $this->jobs(),
            'job' => $this->job((int)($_GET['id'] ?? 0)),
            'channels' => $this->channels(),
            'dict' => $this->dict(),
            'logs' => $this->logs(),
            'health' => $this->health(false),
            default => $this->dash(),
        };
    }

    // --- altyapı ---

    private function headers(): void
    {
        header("Content-Security-Policy: default-src 'none'; style-src 'nonce-{$this->nonce}'; img-src 'self' data:; form-action 'self'; frame-ancestors 'none'; base-uri 'none'");
        header('X-Frame-Options: DENY');
        header('X-Content-Type-Options: nosniff');
        header('Referrer-Policy: no-referrer');
        header('Cache-Control: no-store');
        header('Content-Type: text/html; charset=utf-8');
    }

    private function session(): void
    {
        $https = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') || (($_SERVER['HTTP_X_FORWARDED_PROTO'] ?? '') === 'https');
        session_name('tgwsid');
        session_set_cookie_params(['lifetime' => 0, 'path' => '/', 'secure' => $https, 'httponly' => true, 'samesite' => 'Strict']);
        session_start();
        if (empty($_SESSION['csrf'])) {
            $_SESSION['csrf'] = bin2hex(random_bytes(32));
        }
        $this->flash = $_SESSION['flash'] ?? [];
        unset($_SESSION['flash']);
    }

    private function loggedIn(): bool
    {
        if (empty($_SESSION['auth'])) {
            return false;
        }
        if (time() - (int)($_SESSION['seen'] ?? 0) > self::IDLE_SECONDS) {
            $_SESSION = [];
            session_regenerate_id(true);
            return false;
        }
        $_SESSION['seen'] = time();
        return true;
    }

    private function ip(): string
    {
        return (string)($_SERVER['REMOTE_ADDR'] ?? '0');
    }

    private function login(): void
    {
        $hash = (string)$this->config['admin_password_hash'];
        $msg = '';
        $row = $this->db->one('SELECT * FROM login_attempts WHERE ip = ?', [$this->ip()]);
        $locked = $row && (int)$row['locked_until'] > time();

        if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_GET['p'] ?? '') === 'login') {
            if (!hash_equals((string)$_SESSION['csrf'], (string)($_POST['csrf'] ?? ''))) {
                $msg = 'Oturum süresi doldu, tekrar deneyin.';
            } elseif ($locked) {
                $msg = 'Çok fazla hatalı deneme. ' . ceil(((int)$row['locked_until'] - time()) / 60) . ' dk sonra tekrar deneyin.';
            } elseif ($hash !== '' && password_verify((string)($_POST['password'] ?? ''), $hash)) {
                $this->db->run('DELETE FROM login_attempts WHERE ip = ?', [$this->ip()]);
                session_regenerate_id(true);
                $_SESSION['auth'] = true;
                $_SESSION['seen'] = time();
                $_SESSION['csrf'] = bin2hex(random_bytes(32));
                $this->db->log('info', 'web', 'Panele giriş: ' . $this->ip());
                $this->redirect('dash');
                return;
            } else {
                $fails = ($row && (int)$row['locked_until'] <= time() && (int)$row['locked_until'] > 0) ? 1 : ((int)($row['fails'] ?? 0) + 1);
                $until = $fails >= self::MAX_FAILS ? time() + self::LOCK_SECONDS : 0;
                $this->db->run('INSERT INTO login_attempts(ip, fails, locked_until, last_at) VALUES(?,?,?,?) ON CONFLICT(ip) DO UPDATE SET fails = excluded.fails, locked_until = excluded.locked_until, last_at = excluded.last_at',
                    [$this->ip(), $fails, $until, time()]);
                $this->db->log('warn', 'web', "Hatalı giriş ($fails): " . $this->ip());
                usleep(500000);
                $msg = $until ? 'Çok fazla hatalı deneme, 15 dk kilitlendi.' : 'Şifre yanlış.';
            }
        }
        if ($hash === '') {
            $msg = 'admin_password_hash ayarlı değil. Sunucuda php tools/make_hash.php çalıştırıp config.php içine yazın.';
        }
        $html = '<form method="post" action="?p=login" class="login">'
            . ($msg !== '' ? '<p class="bad">' . $this->e($msg) . '</p>' : '')
            . '<input type="hidden" name="csrf" value="' . $this->e((string)$_SESSION['csrf']) . '">'
            . '<label>Şifre<br><input type="password" name="password" autofocus required autocomplete="current-password"></label>'
            . '<button>Giriş</button></form>';
        $this->page('Giriş', $html, false);
    }

    private function redirect(string $p, array $q = []): void
    {
        header('Location: ?' . http_build_query(['p' => $p] + $q), true, 303);
    }

    private function say(string $type, string $msg): void
    {
        $_SESSION['flash'][] = [$type, $msg];
    }

    private function e(string $s): string
    {
        return htmlspecialchars($s, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
    }

    private function csrf(): string
    {
        return '<input type="hidden" name="csrf" value="' . $this->e((string)$_SESSION['csrf']) . '">';
    }

    private function button(string $action, string $label, array $fields = [], string $class = ''): string
    {
        $h = '<form method="post" action="?p=' . $this->e($action) . '" class="inline">' . $this->csrf();
        foreach ($fields as $k => $v) {
            $h .= '<input type="hidden" name="' . $this->e($k) . '" value="' . $this->e((string)$v) . '">';
        }
        return $h . '<button class="' . $class . '">' . $label . '</button></form>';
    }

    private function when(?int $ts): string
    {
        return $ts ? date('Y-m-d H:i', $ts) : '—';
    }

    private function page(string $title, string $body, bool $nav = true): void
    {
        $menu = '';
        if ($nav) {
            $items = ['dash' => 'Özet', 'jobs' => 'İşler', 'channels' => 'Kanallar', 'dict' => 'Sözlük', 'logs' => 'Loglar', 'health' => 'Sağlık'];
            $cur = (string)($_GET['p'] ?? 'dash');
            foreach ($items as $k => $v) {
                $menu .= '<a href="?p=' . $k . '"' . ($cur === $k ? ' class="cur"' : '') . '>' . $v . '</a>';
            }
            $menu .= $this->button('logout', 'Çıkış', [], 'link');
        }
        $flash = '';
        foreach ($this->flash as [$t, $m]) {
            $flash .= '<p class="flash ' . $this->e($t) . '">' . $this->e($m) . '</p>';
        }
        echo '<!doctype html><html lang="tr"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1">'
            . '<meta name="robots" content="noindex,nofollow"><title>' . $this->e($title) . ' · tgwatch</title>'
            . '<style nonce="' . $this->nonce . '">' . self::CSS . '</style></head><body>'
            . ($nav ? '<nav>' . $menu . '</nav>' : '')
            . '<main><h1>' . $this->e($title) . '</h1>' . $flash . $body . '</main></body></html>';
    }

    private const CSS = <<<CSS
:root{--bg:#f6f7f9;--fg:#1d232b;--mut:#6b7480;--card:#fff;--line:#dfe3e8;--acc:#2a6df4;--ok:#1a7f37;--warn:#9a6700;--bad:#cf222e}
@media (prefers-color-scheme:dark){:root{--bg:#111418;--fg:#e6e8eb;--mut:#9aa3ad;--card:#1a1f25;--line:#2c333b;--acc:#5b93ff;--ok:#3fb950;--warn:#d29922;--bad:#f85149}}
*{box-sizing:border-box}body{margin:0;background:var(--bg);color:var(--fg);font:15px/1.5 system-ui,-apple-system,Segoe UI,Roboto,sans-serif}
nav{display:flex;flex-wrap:wrap;gap:4px;padding:8px 16px;background:var(--card);border-bottom:1px solid var(--line);align-items:center}
nav a{padding:6px 10px;border-radius:6px;color:var(--fg);text-decoration:none}nav a.cur{background:var(--acc);color:#fff}
main{max-width:1100px;margin:0 auto;padding:16px}h1{font-size:22px;margin:4px 0 16px}h2{font-size:17px;margin:24px 0 8px}
table{width:100%;border-collapse:collapse;background:var(--card);border:1px solid var(--line);border-radius:8px;overflow:hidden}
th,td{padding:8px 10px;border-bottom:1px solid var(--line);text-align:left;vertical-align:top}th{font-size:13px;color:var(--mut);font-weight:600}
.wrap{overflow-x:auto}.card{background:var(--card);border:1px solid var(--line);border-radius:8px;padding:12px 14px;margin:0 0 12px}
.grid{display:grid;grid-template-columns:repeat(auto-fill,minmax(160px,1fr));gap:10px}.num{font-size:26px;font-weight:700}
.ok{color:var(--ok)}.warn{color:var(--warn)}.bad{color:var(--bad)}.mut{color:var(--mut)}
.flash{padding:8px 12px;border-radius:6px;background:var(--card);border-left:4px solid var(--acc)}.flash.bad{border-color:var(--bad)}.flash.ok{border-color:var(--ok)}
button{font:inherit;padding:6px 12px;border-radius:6px;border:1px solid var(--line);background:var(--acc);color:#fff;cursor:pointer}
button.sec{background:var(--card);color:var(--fg)}button.danger{background:var(--bad)}button.link{background:none;border:0;color:var(--mut);padding:6px 10px}
form.inline{display:inline}input,textarea,select{font:inherit;padding:6px 8px;border:1px solid var(--line);border-radius:6px;background:var(--bg);color:var(--fg);max-width:100%}
textarea{width:100%;font-family:ui-monospace,Menlo,Consolas,monospace;font-size:13px}pre{white-space:pre-wrap;word-break:break-word;font-size:13px;margin:0}
.login{max-width:320px;margin:40px auto;display:grid;gap:12px}.login input{width:100%}.tag{display:inline-block;padding:1px 8px;border-radius:10px;font-size:12px;border:1px solid var(--line)}
.row{display:flex;gap:8px;flex-wrap:wrap;align-items:center}
CSS;

    // --- sayfalar ---

    private function statusTag(string $s): string
    {
        $cls = ['published' => 'ok', 'failed' => 'bad', 'rejected' => 'mut', 'awaiting' => 'warn', 'editing' => 'warn', 'new' => 'warn'][$s] ?? '';
        return '<span class="tag ' . $cls . '">' . $this->e(Flow::LABELS[$s] ?? $s) . '</span>';
    }

    private function dash(): void
    {
        $counts = [];
        foreach ($this->db->all('SELECT status, COUNT(*) c FROM jobs GROUP BY status') as $r) {
            $counts[$r['status']] = (int)$r['c'];
        }
        $h = '<div class="grid">';
        foreach (Flow::LABELS as $k => $v) {
            $h .= '<a class="card" href="?p=jobs&amp;status=' . $k . '"><div class="mut">' . $v . '</div><div class="num">' . ($counts[$k] ?? 0) . '</div></a>';
        }
        $h .= '</div>';
        $last = (int)$this->db->get('cron_last_run', '0');
        $age = time() - $last;
        $cls = $last === 0 ? 'bad' : ($age < 600 ? 'ok' : 'warn');
        $h .= '<div class="card">Cron son çalışma: <b class="' . $cls . '">' . ($last ? $this->when($last) . " ($age sn önce)" : 'hiç çalışmadı') . '</b>'
            . ' · Bot modu: <b>' . $this->e((string)$this->config['bot_mode']) . '</b> · Yayın: <b>' . $this->e((string)$this->config['publisher']) . '</b></div>';
        $bad = $this->db->all('SELECT username, last_error FROM channels WHERE enabled = 1 AND last_error IS NOT NULL');
        foreach ($bad as $b) {
            $h .= '<p class="flash bad">@' . $this->e($b['username']) . ': ' . $this->e($b['last_error']) . '</p>';
        }
        $h .= '<h2>Son işler</h2>' . $this->jobTable($this->db->all('SELECT * FROM jobs ORDER BY id DESC LIMIT 10'));
        $h .= '<h2>Son hatalar</h2>' . $this->logTable($this->db->all("SELECT * FROM logs WHERE level IN ('error','warn') ORDER BY id DESC LIMIT 8"));
        $this->page('Özet', $h);
    }

    private function jobTable(array $rows): string
    {
        if (!$rows) {
            return '<p class="mut">Henüz iş yok.</p>';
        }
        $h = '<div class="wrap"><table><tr><th>#</th><th>Kanal</th><th>Servis</th><th>Durum</th><th>Tarih</th></tr>';
        foreach ($rows as $r) {
            $items = json_decode($r['items_json'], true) ?: [];
            $first = $items[0]['az'] ?? '';
            $h .= '<tr><td><a href="?p=job&amp;id=' . $r['id'] . '">#' . $r['id'] . '</a></td><td>@' . $this->e($r['channel']) . '<div class="mut">' . $r['msg_id'] . '</div></td>'
                . '<td>' . count($items) . ' servis<div class="mut">' . $this->e(mb_substr($first, 0, 70)) . '</div></td><td>' . $this->statusTag($r['status'])
                . ($r['note'] !== '' ? '<div class="mut">' . $this->e(mb_substr($r['note'], 0, 80)) . '</div>' : '') . '</td><td>' . $this->when((int)$r['updated_at']) . '</td></tr>';
        }
        return $h . '</table></div>';
    }

    private function jobs(): void
    {
        $status = (string)($_GET['status'] ?? '');
        $rows = isset(Flow::LABELS[$status])
            ? $this->db->all('SELECT * FROM jobs WHERE status = ? ORDER BY id DESC LIMIT 200', [$status])
            : $this->db->all('SELECT * FROM jobs ORDER BY id DESC LIMIT 200');
        $f = '<p class="row"><a href="?p=jobs">Hepsi</a>';
        foreach (Flow::LABELS as $k => $v) {
            $f .= ' · <a href="?p=jobs&amp;status=' . $k . '">' . $v . '</a>';
        }
        $this->page('İşler', $f . '</p>' . $this->jobTable($rows));
    }

    private function job(int $id): void
    {
        $job = $this->db->job($id);
        if ($job === null) {
            $this->page('İş bulunamadı', '<p><a href="?p=jobs">← İşler</a></p>');
            return;
        }
        $open = in_array($job['status'], ['new', 'awaiting', 'editing'], true);
        $h = '<p><a href="?p=jobs">← İşler</a></p><div class="card">' . $this->statusTag($job['status'])
            . ' · <a href="https://t.me/' . $this->e($job['channel']) . '/' . $job['msg_id'] . '" rel="noopener noreferrer" target="_blank">@' . $this->e($job['channel']) . '/' . $job['msg_id'] . '</a>'
            . ' · oluşturma ' . $this->when((int)$job['created_at']) . ' · güncelleme ' . $this->when((int)$job['updated_at'])
            . ($job['note'] !== '' ? '<div class="mut">' . $this->e($job['note']) . '</div>' : '') . '</div>';

        $h .= '<div class="row">';
        if ($open || $job['status'] === 'failed') {
            $h .= $this->button('job_do', $job['status'] === 'failed' ? '🔁 Tekrar yayınla' : '✅ Onayla', ['id' => $id, 'do' => 'approve']);
        }
        if ($open) {
            $h .= $this->button('job_do', '🔄 Yeniden oluştur', ['id' => $id, 'do' => 'regen'], 'sec');
        }
        if ($open || $job['status'] === 'failed') {
            $h .= $this->button('job_do', '❌ Reddet', ['id' => $id, 'do' => 'reject'], 'danger');
        }
        $h .= $this->button('job_do', 'Bota tekrar gönder', ['id' => $id, 'do' => 'resend'], 'sec') . '</div>';

        $h .= '<h2>Servisler</h2><div class="wrap"><table><tr><th>ID</th><th>Orijinal</th><th>Azerice</th><th>Fiyat</th></tr>';
        foreach ($job['items'] as $it) {
            $h .= '<tr><td>' . (int)$it['id'] . '</td><td>' . $this->e($it['name']) . ($it['category'] ? '<div class="mut">📁 ' . $this->e($it['category']) . '</div>' : '') . '</td>'
                . '<td><b>' . $this->e($it['az']) . '</b>' . ($it['edited'] ? ' ✏️' : '')
                . ($it['unknown'] ? '<div class="warn">⚠️ Sözlükte yok: ' . $this->e(implode(', ', $it['unknown'])) . '</div>' : '') . '</td>'
                . '<td>' . ($it['price'] !== '' ? '$' . $this->e($it['price']) : '—') . '</td></tr>';
        }
        $h .= '</table></div>';

        if ($open) {
            $lines = implode("\n", array_map(static fn($i) => $i['id'] . ' | ' . $i['az'], $job['items']));
            $h .= '<h2>Düzenle</h2><form method="post" action="?p=job_edit">' . $this->csrf() . '<input type="hidden" name="id" value="' . $id . '">'
                . '<p class="mut">Her satır: <code>ID | ad</code>. Değiştirmediğin satırlar aynen kalır.</p>'
                . '<textarea name="lines" rows="' . min(30, count($job['items']) + 2) . '">' . $this->e($lines) . '</textarea><p><button>Kaydet</button></p></form>';
        }
        $h .= '<h2>Kaynak metin</h2><div class="card"><pre>' . $this->e($job['source_text']) . '</pre></div>';
        $this->page('İş #' . $id, $h);
    }

    private function channels(): void
    {
        $rows = $this->db->all('SELECT * FROM channels ORDER BY username');
        $h = '<form method="post" action="?p=channel_add" class="card row">' . $this->csrf()
            . '<input name="username" placeholder="@kanal ya da https://t.me/kanal" required> <button>Ekle</button>'
            . '<span class="mut">Kanal herkese açık olmalı (t.me/s/kanal açılmalı). İlk okumada eski gönderiler işlenmez.</span></form>';
        if ($rows) {
            $h .= '<div class="wrap"><table><tr><th>Kanal</th><th>Durum</th><th>Son gönderi</th><th>Son okuma</th><th></th></tr>';
            foreach ($rows as $c) {
                $st = !$c['enabled'] ? '<span class="mut">kapalı</span>' : ($c['last_error'] ? '<span class="bad">' . $this->e($c['last_error']) . '</span>' : ($c['last_ok_at'] ? '<span class="ok">çalışıyor</span>' : '<span class="warn">henüz okunmadı</span>'));
                $h .= '<tr><td><a href="https://t.me/s/' . $this->e($c['username']) . '" target="_blank" rel="noopener noreferrer">@' . $this->e($c['username']) . '</a><div class="mut">' . $this->e($c['title']) . '</div></td>'
                    . '<td>' . $st . '</td><td>' . ($c['last_post_id'] ?? '—') . '</td><td>' . $this->when($c['last_checked_at'] ? (int)$c['last_checked_at'] : null) . '</td><td class="row">'
                    . $this->button('channel_test', 'Test', ['id' => $c['id']], 'sec')
                    . $this->button('channel_toggle', $c['enabled'] ? 'Kapat' : 'Aç', ['id' => $c['id']], 'sec')
                    . $this->button('channel_delete', 'Sil', ['id' => $c['id']], 'danger') . '</td></tr>';
            }
            $h .= '</table></div>';
        } else {
            $h .= '<p class="mut">Henüz kanal yok.</p>';
        }
        if (!empty($_SESSION['test_report'])) {
            $h .= '<h2>Test sonucu</h2><div class="card"><pre>' . $this->e($_SESSION['test_report']) . '</pre></div>';
            unset($_SESSION['test_report']);
        }
        $this->page('Kanallar', $h);
    }

    private function dict(?array $draft = null, string $previewIn = '', string $previewOut = ''): void
    {
        $d = $draft ?? Dictionary::load($this->db);
        $guess = array_flip($d['guesses'] ?? []);
        $h = '<form method="post" action="?p=dict_save">' . $this->csrf()
            . '<h2>Ad kalıbı</h2><div class="card row">'
            . '<label><input type="checkbox" name="keep_max" value="1"' . (!empty($d['options']['keep_max']) ? ' checked' : '') . '> "Maks 50K" kısmını ada koy</label>'
            . '<label><input type="checkbox" name="keep_speed" value="1"' . (!empty($d['options']['keep_speed']) ? ' checked' : '') . '> "Gündə 5K" kısmını ada koy</label></div>'
            . '<h2>Kurallar (sözlükten önce, sırayla)</h2><p class="mut">Her satır: <code>regex =&gt; çıktı</code>. $1 yakalanan grubu yazar. Büyük/küçük harf duyarsız.</p>'
            . '<textarea name="rules" rows="10">' . $this->e(Dictionary::rulesToText($d['rules'])) . '</textarea>'
            . '<h2>Sözlük</h2><p class="mut">Her satır: <code>English = Azərbaycanca</code>. Uzun ifadeler önce eşleşir.</p>'
            . '<textarea name="phrases" rows="16">' . $this->e(Dictionary::phrasesToText($d['phrases'])) . '</textarea>'
            . '<h2>Çevrilmeyecek kelimeler</h2><p class="mut">Marka adları vb. Boşluk ya da virgülle ayırın.</p>'
            . '<textarea name="keep" rows="4">' . $this->e(implode(', ', $d['keep'])) . '</textarea>'
            . '<h2>Tahmini (onaylanmamış) çeviriler</h2><p class="mut">Buradaki İngilizce ifadeleri kontrol ettikten sonra listeden silin.</p>'
            . '<textarea name="guesses" rows="3">' . $this->e(implode(', ', array_keys($guess))) . '</textarea>'
            . '<h2>Önizleme</h2><p class="mut">Her satıra bir servis adı (İngilizce). Önizleme kaydetmez.</p>'
            . '<textarea name="preview" rows="5">' . $this->e($previewIn !== '' ? $previewIn : "Threads Followers | No Drop | Max 50K | 5K/Day | 30 Days Refill ♻️") . '</textarea>'
            . ($previewOut !== '' ? '<div class="card"><pre>' . $this->e($previewOut) . '</pre></div>' : '')
            . '<p class="row"><button name="do" value="preview" class="sec">Önizle</button><button name="do" value="save">Kaydet</button>'
            . '<button name="do" value="reset" class="danger">Varsayılanlara dön</button></p></form>';
        $this->page('Sözlük', $h);
    }

    private function logs(): void
    {
        $level = (string)($_GET['level'] ?? '');
        $rows = in_array($level, ['error', 'warn', 'info'], true)
            ? $this->db->all('SELECT * FROM logs WHERE level = ? ORDER BY id DESC LIMIT 300', [$level])
            : $this->db->all('SELECT * FROM logs ORDER BY id DESC LIMIT 300');
        $h = '<p><a href="?p=logs">Hepsi</a> · <a href="?p=logs&amp;level=error">Hatalar</a> · <a href="?p=logs&amp;level=warn">Uyarılar</a> · <a href="?p=logs&amp;level=info">Bilgi</a></p>';
        $this->page('Loglar', $h . $this->logTable($rows));
    }

    private function logTable(array $rows): string
    {
        if (!$rows) {
            return '<p class="mut">Kayıt yok.</p>';
        }
        $h = '<div class="wrap"><table><tr><th>Zaman</th><th>Seviye</th><th>Kaynak</th><th>Mesaj</th></tr>';
        foreach ($rows as $r) {
            $cls = ['error' => 'bad', 'warn' => 'warn'][$r['level']] ?? 'mut';
            $h .= '<tr><td>' . date('m-d H:i:s', (int)$r['ts']) . '</td><td class="' . $cls . '">' . $this->e($r['level']) . '</td><td>' . $this->e($r['source']) . '</td><td><pre>' . $this->e($r['message']) . '</pre></td></tr>';
        }
        return $h . '</table></div>';
    }

    private function health(bool $network): void
    {
        $rows = Health::local($this->config);
        if ($network) {
            $rows = array_merge($rows, Health::network($this->config));
        }
        $h = '<div class="wrap"><table><tr><th>Kontrol</th><th>Durum</th><th>Ayrıntı</th></tr>';
        foreach ($rows as [$name, $st, $detail]) {
            $cls = [Health::OK => 'ok', Health::WARN => 'warn', Health::FAIL => 'bad'][$st];
            $icon = [Health::OK => '✓', Health::WARN => '!', Health::FAIL => '✗'][$st];
            $h .= '<tr><td>' . $this->e($name) . '</td><td class="' . $cls . '"><b>' . $icon . '</b></td><td>' . $this->e($detail) . '</td></tr>';
        }
        $h .= '</table></div><p>' . $this->button('health_net', 'Ağ testlerini çalıştır (Telegram, bot, webhook)', [], 'sec') . '</p>';
        $this->page('Sağlık', $h);
    }

    // --- POST eylemleri ---

    private function post(string $p): void
    {
        $id = (int)($_POST['id'] ?? 0);
        try {
            switch ($p) {
                case 'logout':
                    $_SESSION = [];
                    session_regenerate_id(true);
                    $this->redirect('dash');
                    return;
                case 'job_do':
                    $flow = App::flow();
                    $do = (string)($_POST['do'] ?? '');
                    $msg = match ($do) {
                        'approve' => $flow->approve($id),
                        'reject' => $flow->reject($id),
                        'regen' => $flow->regenerate($id),
                        'resend' => $flow->sendPreview($id) ? 'Bota gönderildi.' : 'Bota gönderilemedi, loglara bakın.',
                        default => 'Bilinmeyen işlem',
                    };
                    $this->say(str_contains($msg, 'hata') || str_contains($msg, 'gönderilemedi') ? 'bad' : 'ok', $msg);
                    $this->redirect('job', ['id' => $id]);
                    return;
                case 'job_edit':
                    $flow = App::flow();
                    $r = $flow->applyEdits($id, (string)($_POST['lines'] ?? ''));
                    $flow->sendPreview($id);
                    $this->say($r['errors'] ? 'bad' : 'ok', "{$r['changed']} ad güncellendi." . ($r['errors'] ? ' ' . implode('; ', $r['errors']) : ''));
                    $this->redirect('job', ['id' => $id]);
                    return;
                case 'channel_add':
                    $w = new Watcher($this->db, App::flow(), new ChannelReader((int)$this->config['fetch_timeout']));
                    $u = $w->addChannel((string)($_POST['username'] ?? ''));
                    $this->db->log('info', 'web', "Kanal eklendi: @$u");
                    $this->say('ok', "@$u eklendi. Bir sonraki cron'da okunacak; şimdi Test ile deneyebilirsiniz.");
                    $this->redirect('channels');
                    return;
                case 'channel_toggle':
                    $this->db->run('UPDATE channels SET enabled = 1 - enabled WHERE id = ?', [$id]);
                    $this->redirect('channels');
                    return;
                case 'channel_delete':
                    $c = $this->db->one('SELECT username FROM channels WHERE id = ?', [$id]);
                    $this->db->run('DELETE FROM channels WHERE id = ?', [$id]);
                    $this->say('ok', 'Kanal silindi: @' . ($c['username'] ?? '?'));
                    $this->redirect('channels');
                    return;
                case 'channel_test':
                    $c = $this->db->one('SELECT * FROM channels WHERE id = ?', [$id]);
                    if ($c) {
                        $w = new Watcher($this->db, App::flow(), new ChannelReader((int)$this->config['fetch_timeout']));
                        $r = $w->checkChannel($c, 5, true);
                        $tr = App::flow()->translator();
                        $out = $r['ok'] ? "✓ @{$c['username']} okundu ({$r['title']}), son {$r['posts']} gönderide " . count($r['found']) . " servis duyurusu.\n" : "✗ {$r['error']}\n";
                        foreach ($r['found'] as $f) {
                            $out .= "\nGönderi {$f['id']}:\n";
                            foreach ($f['items'] as $it) {
                                $out .= "  {$it['id']}  {$it['name']}\n    → " . $tr->translate($it['name'])['text'] . "\n";
                            }
                        }
                        $_SESSION['test_report'] = $out;
                    }
                    $this->redirect('channels');
                    return;
                case 'dict_save':
                    $this->dictPost();
                    return;
                case 'health_net':
                    $this->health(true);
                    return;
            }
            $this->redirect('dash');
        } catch (\Throwable $e) {
            $this->db->log('error', 'web', "$p: " . $e->getMessage());
            $this->say('bad', $e->getMessage());
            $this->redirect($p === 'job_do' || $p === 'job_edit' ? 'job' : (str_starts_with($p, 'channel') ? 'channels' : 'dash'), $id ? ['id' => $id] : []);
        }
    }

    private function dictPost(): void
    {
        $do = (string)($_POST['do'] ?? 'preview');
        if ($do === 'reset') {
            Dictionary::save($this->db, Dictionary::defaults());
            $this->db->log('info', 'web', 'Sözlük varsayılanlara döndürüldü');
            $this->say('ok', 'Sözlük varsayılanlara döndürüldü.');
            $this->redirect('dict');
            return;
        }
        [$rules, $errors] = Dictionary::rulesFromText((string)($_POST['rules'] ?? ''));
        $draft = [
            'rules' => $rules,
            'phrases' => Dictionary::phrasesFromText((string)($_POST['phrases'] ?? '')),
            'keep' => Dictionary::listFromText((string)($_POST['keep'] ?? '')),
            'guesses' => array_values(array_filter(array_map('trim', explode(',', (string)($_POST['guesses'] ?? ''))))),
            'options' => ['keep_max' => !empty($_POST['keep_max']), 'keep_speed' => !empty($_POST['keep_speed'])],
        ];
        foreach ($errors as $err) {
            $this->flash[] = ['bad', $err];
        }
        $in = (string)($_POST['preview'] ?? '');
        $tr = new Translator($draft);
        $out = '';
        foreach (preg_split('/\R/u', $in) as $line) {
            if (trim($line) === '') {
                continue;
            }
            // Duyuru satırı yapıştırılırsa (🆔 123 ...) ID'yi ayıkla.
            $items = Parser::parse($line);
            $name = $items ? $items[0]['name'] : $line;
            $r = $tr->translate($name);
            $out .= ($items ? $items[0]['id'] . '  ' : '') . $r['text'] . ($r['unknown'] ? '   ⚠ sözlükte yok: ' . implode(', ', $r['unknown']) : '') . "\n";
        }
        if ($do === 'save' && !$errors) {
            Dictionary::save($this->db, $draft);
            $this->db->log('info', 'web', 'Sözlük kaydedildi');
            $this->flash[] = ['ok', 'Sözlük kaydedildi. Bekleyen işlere uygulamak için işte "Yeniden oluştur"a basın.'];
        } elseif ($do === 'save') {
            $this->flash[] = ['bad', 'Hatalı kurallar var, kaydedilmedi.'];
        }
        $this->dict($draft, $in, $out);
    }
}
