<?php
declare(strict_types=1);

namespace TgWatch\Web;

use TgWatch\App;
use TgWatch\ChannelReader;
use TgWatch\CronRunner;
use TgWatch\Db;
use TgWatch\Dictionary;
use TgWatch\Flow;
use TgWatch\Health;
use TgWatch\Parser;
use TgWatch\Publisher\ManualPublisher;
use TgWatch\Translator;
use TgWatch\Watcher;

/** Web yönetim paneli. JavaScript yalnızca kolaylık içindir (kopyala, onay sorusu, tümünü seç); panel onsuz da çalışır. */
final class Admin
{
    use BankPages;

    private const MAX_FAILS = 5;
    private const LOCK_SECONDS = 900;
    private const IDLE_SECONDS = 7200;
    private const PER_PAGE = 30;
    private const OPEN = ['new', 'awaiting', 'editing'];

    private Db $db;
    private Ui $ui;
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
        $this->ui = new Ui((string)$_SESSION['csrf'], $this->nonce);
        try {
            $this->db = App::db();
        } catch (\Throwable $e) {
            $this->render('Veritabanı açılamadı', '<div class="flash bad">' . Ui::e($e->getMessage()) . '</div><p>config.php içindeki data_dir ayarını ve klasör izinlerini kontrol edin.</p>', ['nav' => false]);
            return;
        }
        $p = (string)($_GET['p'] ?? 'dash');

        if ($p === 'theme') {
            $this->theme();
            return;
        }
        if (!$this->loggedIn()) {
            $this->login();
            return;
        }
        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            if (!hash_equals((string)($_SESSION['csrf'] ?? ''), (string)($_POST['csrf'] ?? ''))) {
                http_response_code(400);
                $this->render('Oturum doğrulanamadı', Ui::empty('İstek reddedildi', 'Sayfa çok uzun süre açık kaldı ya da başka bir sekmeden çıkış yapıldı. Sayfayı yenileyip tekrar deneyin.', Ui::link('?p=dash', 'Panele dön', 'primary')));
                return;
            }
            $this->post($p);
            return;
        }
        match ($p) {
            'jobs' => $this->jobs(),
            'job' => $this->job((int)($_GET['id'] ?? 0)),
            'job_txt' => $this->jobTxt((int)($_GET['id'] ?? 0)),
            'channels' => $this->channels(),
            'dict' => $this->dict(),
            'bank' => $this->bank(),
            'ai' => $this->aiSettings(),
            'job_prompt' => $this->jobPrompt((int)($_GET['id'] ?? 0)),
            'logs' => $this->logs(),
            'health' => $this->health(false),
            default => $this->dash(),
        };
    }

    // ---------------------------------------------------------------- altyapı

    private function headers(): void
    {
        $n = $this->nonce;
        header("Content-Security-Policy: default-src 'none'; script-src 'nonce-$n'; style-src 'nonce-$n' https://fonts.googleapis.com; font-src https://fonts.gstatic.com; img-src 'self' data:; form-action 'self'; frame-ancestors 'none'; base-uri 'none'");
        header('X-Frame-Options: DENY');
        header('X-Content-Type-Options: nosniff');
        header('Referrer-Policy: no-referrer');
        header('Cache-Control: no-store');
        header('Content-Type: text/html; charset=utf-8');
    }

    private function https(): bool
    {
        return (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') || (($_SERVER['HTTP_X_FORWARDED_PROTO'] ?? '') === 'https');
    }

    private function session(): void
    {
        session_name('tgwsid');
        session_set_cookie_params(['lifetime' => 0, 'path' => '/', 'secure' => $this->https(), 'httponly' => true, 'samesite' => 'Strict']);
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
            $_SESSION['csrf'] = bin2hex(random_bytes(32));
            $this->ui = new Ui($_SESSION['csrf'], $this->nonce);
            return false;
        }
        $_SESSION['seen'] = time();
        return true;
    }

    private function ip(): string
    {
        return (string)($_SERVER['REMOTE_ADDR'] ?? '0');
    }

    public static function randomKey(): string
    {
        return rtrim(strtr(base64_encode(random_bytes(32)), '+/', '-_'), '=');
    }

    private function siblingUrl(string $file): string
    {
        return substr($this->suggestedWebhook(), 0, -strlen('bot.php')) . $file;
    }

    private function suggestedWebhook(): string
    {
        $host = preg_replace('/[^A-Za-z0-9.\-:]/', '', (string)($_SERVER['HTTP_HOST'] ?? 'ALANADI'));
        $dir = rtrim(str_replace('\\', '/', dirname((string)($_SERVER['SCRIPT_NAME'] ?? '/index.php'))), '/');
        return 'https://' . $host . $dir . '/bot.php';
    }

    private function redirect(string $p, array $q = []): void
    {
        header('Location: ?' . http_build_query(['p' => $p] + $q), true, 303);
    }

    private function say(string $type, string $msg): void
    {
        $_SESSION['flash'][] = [$type, $msg];
    }

    private function themeName(): string
    {
        $t = (string)($_COOKIE['tgw_theme'] ?? 'auto');
        return in_array($t, ['auto', 'light', 'dark'], true) ? $t : 'auto';
    }

    private function theme(): void
    {
        $t = (string)($_GET['t'] ?? 'auto');
        $t = in_array($t, ['auto', 'light', 'dark'], true) ? $t : 'auto';
        setcookie('tgw_theme', $t, ['expires' => time() + 31536000, 'path' => '/', 'secure' => $this->https(), 'httponly' => true, 'samesite' => 'Lax']);
        $back = (string)($_GET['back'] ?? 'dash');
        $this->redirect(in_array($back, ['dash', 'jobs', 'channels', 'dict', 'bank', 'ai', 'logs', 'health'], true) ? $back : 'dash');
    }

    private function pending(): int
    {
        return (int)$this->db->one("SELECT COUNT(*) c FROM jobs WHERE status IN ('new','awaiting','editing','failed')")['c'];
    }

    /** @return array{state:string,text:string} */
    private function cronState(): array
    {
        $last = (int)$this->db->get('cron_last_run', '0');
        if ($last === 0) {
            return ['state' => 'bad', 'text' => 'hiç çalışmadı'];
        }
        $age = time() - $last;
        return ['state' => $age < 180 ? 'ok' : ($age < 900 ? 'warn' : 'bad'), 'text' => Ui::ago($last)];
    }

    private function render(string $title, string $body, array $o = []): void
    {
        $nav = $o['nav'] ?? true;
        if ($nav && isset($this->db)) {
            $o += ['pending' => $this->pending(), 'cron' => $this->cronState()];
        }
        $o['theme'] = $this->themeName();
        $o['flash'] = $this->flash;
        echo $this->ui->page($title, $body, $o);
    }

    // ---------------------------------------------------------------- giriş ve kurulum

    private function bare(string $title, string $sub, string $inner): void
    {
        $this->render($title, '<div class="login-card"><div class="flag" aria-hidden="true"><i></i><i></i><i></i></div>'
            . '<div class="body"><div><h1>' . Ui::e($title) . '</h1><p class="sub">' . $sub . '</p></div>' . $inner . '</div></div>', ['nav' => false]);
    }

    private function setup(): void
    {
        $out = '';
        $msg = '';
        if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_GET['p'] ?? '') === 'setup') {
            $pw = (string)($_POST['password'] ?? '');
            if (!hash_equals((string)$_SESSION['csrf'], (string)($_POST['csrf'] ?? ''))) {
                $msg = 'Sayfanın süresi doldu. Tekrar deneyin.';
            } elseif (mb_strlen($pw) < 12) {
                $msg = 'Şifre en az 12 karakter olmalı.';
            } elseif ($pw !== (string)($_POST['password2'] ?? '')) {
                $msg = 'İki şifre aynı değil.';
            } else {
                $algo = defined('PASSWORD_ARGON2ID') ? PASSWORD_ARGON2ID : PASSWORD_DEFAULT;
                $out = "    'admin_password_hash' => '" . password_hash($pw, $algo) . "',\n";
                foreach (['webhook_secret' => self::randomKey(), 'webhook_url' => $this->suggestedWebhook(), 'cron_key' => self::randomKey()] as $k => $v) {
                    if ((string)$this->config[$k] === '') {
                        $out .= "    '$k' => '$v',\n";
                    }
                }
            }
        }
        $cfg = TGWATCH_ROOT . '/config.php';
        $steps = '<ol class="stack steps">'
            . '<li><b>config.sample.php</b> dosyasını aynı klasörde <b>config.php</b> adıyla kopyalayın. ' . (is_file($cfg) ? '<span class="muted">(var)</span>' : '<span class="lvl-error">(henüz yok)</span>') . '</li>'
            . '<li>Aşağıda şifrenizi belirleyin ve çıkan satırları config.php içindeki aynı satırların yerine yapıştırın.</li>'
            . '<li>bot_token değerini doldurun, kaydedin ve bu sayfayı yenileyin.</li></ol>';
        if ($out !== '') {
            $inner = $steps . '<div class="code"><pre id="setup-out">' . Ui::e($out) . '</pre></div><div class="row">' . Ui::copy('setup-out', 'Satırları kopyala') . '</div>'
                . '<p class="muted">Bu satırlar gizlidir. Yalnızca config.php dosyasına yapıştırın.</p>';
        } else {
            $inner = $steps . '<form method="post" action="?p=setup" class="stack">' . ($msg !== '' ? '<div class="flash bad">' . Ui::e($msg) . '</div>' : '') . $this->ui->csrfField()
                . '<label>Panel şifresi<input type="password" name="password" required minlength="12" autocomplete="new-password"></label>'
                . '<label>Şifre tekrar<input type="password" name="password2" required minlength="12" autocomplete="new-password"></label>'
                . '<button class="btn primary">Satırları oluştur</button></form>';
        }
        $this->bare('İlk kurulum', 'Panel şifresi henüz ayarlanmamış. Bu sayfa hiçbir dosyaya yazmaz.', $inner);
    }

    private function login(): void
    {
        $hash = (string)$this->config['admin_password_hash'];
        if ($hash === '') {
            $this->setup();
            return;
        }
        $msg = '';
        $row = $this->db->one('SELECT * FROM login_attempts WHERE ip = ?', [$this->ip()]);
        $locked = $row && (int)$row['locked_until'] > time();

        if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_GET['p'] ?? '') === 'login') {
            if (!hash_equals((string)$_SESSION['csrf'], (string)($_POST['csrf'] ?? ''))) {
                $msg = 'Sayfanın süresi doldu. Tekrar deneyin.';
            } elseif ($locked) {
                $msg = 'Çok fazla hatalı deneme. ' . ceil(((int)$row['locked_until'] - time()) / 60) . ' dakika sonra tekrar deneyin.';
            } elseif (password_verify((string)($_POST['password'] ?? ''), $hash)) {
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
                $msg = $until ? 'Çok fazla hatalı deneme. Giriş 15 dakika kilitlendi.' : 'Şifre yanlış.';
            }
        }
        $inner = '<form method="post" action="?p=login" class="stack">' . ($msg !== '' ? '<div class="flash bad">' . Ui::e($msg) . '</div>' : '')
            . $this->ui->csrfField()
            . '<label>Şifre<input type="password" name="password" autofocus required autocomplete="current-password"></label>'
            . '<button class="btn primary">Giriş yap</button></form>';
        $this->bare('tgwatch', 'Servis duyurularını çevirme ve onaylama paneli', $inner);
    }

    // ---------------------------------------------------------------- yardımcılar

    private function channelsList(): array
    {
        return array_column($this->db->all('SELECT username FROM channels ORDER BY username'), 'username');
    }

    /** İş satırlarını kart olarak gösterir (kuyrukta). */
    private function queueCard(array $job, int $show = 4, bool $quick = true): string
    {
        $id = (int)$job['id'];
        $items = $job['items'];
        $unknown = 0;
        foreach ($items as $it) {
            $unknown += count($it['unknown'] ?? []);
        }
        $actions = '';
        if ($quick) {
            if (in_array($job['status'], self::OPEN, true)) {
                $actions .= $this->ui->action('job_do', 'Onayla', ['id' => $id, 'do' => 'approve', 'back' => 'dash'], 'primary sm', 'check');
            }
            $actions .= Ui::link('?p=job&id=' . $id, 'İncele', 'quiet sm', 'edit');
        }
        $h = '<article class="qcard st-' . Ui::e($job['status']) . '"><div class="qhead">'
            . '<a class="id" href="?p=job&amp;id=' . $id . '">İş ' . $id . '</a>' . Ui::badge($job['status'])
            . '<span class="meta">@' . Ui::e($job['channel']) . ', ' . count($items) . ' servis, ' . Ui::ago((int)$job['created_at']) . '</span>'
            . ($unknown ? '<span class="badge s-approved">' . $unknown . ' eksik kelime</span>' : '')
            . '<div class="row">' . $actions . '</div></div>';
        foreach (array_slice($items, 0, $show) as $it) {
            $h .= '<div class="pair"><div class="sid">' . (int)$it['id'] . '</div><div>'
                . '<div class="az">' . Ui::e($it['az']) . Ui::via($it['via'] ?? null) . '</div>'
                . ($it['name'] !== $it['az'] ? '<div class="src">' . Ui::e($it['name']) . '</div>' : '')
                . (!empty($it['unknown']) ? '<div class="warn">Sözlükte yok: ' . Ui::e(implode(', ', $it['unknown'])) . '</div>' : '')
                . (!empty($it['ai_note']) ? '<div class="warn">' . Ui::e($it['ai_note']) . '</div>' : '')
                . '</div></div>';
        }
        if (count($items) > $show) {
            $h .= '<div class="more">ve ' . (count($items) - $show) . ' servis daha</div>';
        }
        if ($job['status'] === 'failed' && $job['note'] !== '') {
            $h .= '<div class="warn lvl-error">' . Ui::e($job['note']) . '</div>';
        }
        return $h . '</article>';
    }

    private function loadJobs(string $where, array $args, string $tail = ''): array
    {
        $rows = $this->db->all("SELECT * FROM jobs $where $tail", $args);
        foreach ($rows as &$r) {
            $r['items'] = json_decode($r['items_json'], true) ?: [];
        }
        return $rows;
    }

    /** Açık işlerdeki sözlükte olmayan kelimeler: kelime => [sayı, örnek iş]. */
    private function missingWords(): array
    {
        $out = [];
        foreach ($this->loadJobs("WHERE status IN ('new','awaiting','editing')", [], 'ORDER BY id DESC LIMIT 200') as $job) {
            foreach ($job['items'] as $it) {
                foreach ($it['unknown'] ?? [] as $w) {
                    $k = mb_strtolower($w);
                    $out[$k] ??= ['word' => $w, 'n' => 0, 'job' => (int)$job['id'], 'example' => $it['name']];
                    $out[$k]['n']++;
                }
            }
        }
        uasort($out, static fn($a, $b) => $b['n'] <=> $a['n']);
        return $out;
    }

    // ---------------------------------------------------------------- sayfalar

    private function dash(): void
    {
        $open = $this->loadJobs("WHERE status IN ('new','awaiting','editing','failed')", [], "ORDER BY CASE status WHEN 'failed' THEN 0 ELSE 1 END, id ASC LIMIT 20");
        $approved = (int)$this->db->one("SELECT COUNT(*) c FROM jobs WHERE status = 'approved'")['c'];
        $ch = $this->db->one('SELECT COUNT(*) total, SUM(enabled = 1 AND last_error IS NULL AND last_ok_at IS NOT NULL) ok, SUM(enabled = 1 AND last_error IS NOT NULL) bad FROM channels');
        $cron = $this->cronState();
        $res = $this->db->getJson('cron_last_result');

        $chText = (int)$ch['total'] === 0 ? '<a href="?p=channels">Kanal ekleyin</a>'
            : (int)$ch['ok'] . ' çalışıyor' . ((int)$ch['bad'] ? ', <span class="lvl-error">' . (int)$ch['bad'] . ' hatalı</span>' : '');
        $h = '<section class="strip" aria-label="Sistem durumu">'
            . '<div><div class="k">' . Ui::dot($open ? 'warn' : 'ok') . 'Onay bekleyen</div><div class="v">' . count($open) . ' iş</div></div>'
            . '<div><div class="k">' . Ui::dot($approved ? 'warn' : 'ok') . 'Sitede yayın bekleyen</div><div class="v"><a href="?p=jobs&amp;status=approved">' . $approved . ' iş</a></div></div>'
            . '<div><div class="k">' . Ui::dot((int)$ch['bad'] ? 'bad' : ((int)$ch['total'] ? 'ok' : 'warn')) . 'Kanallar</div><div class="v">' . $chText . '</div></div>'
            . '<div><div class="k">' . Ui::dot($cron['state']) . 'Son kanal okuma</div><div class="v">' . $cron['text']
            . (is_array($res) && $res['errors'] ? ' <span class="lvl-error">(' . (int)$res['errors'] . ' hata)</span>' : '') . '</div></div>'
            . '</section>';

        if ($open) {
            $h .= '<div class="queue">';
            foreach ($open as $job) {
                $h .= $this->queueCard($job);
            }
            $h .= '</div>';
        } else {
            $h .= Ui::empty('Onay bekleyen iş yok', 'Kanallarda yeni bir servis duyurusu çıkınca burada ve Telegram botunda görünür.',
                $this->ui->action('run_cron', 'Kanalları şimdi oku', [], 'primary', 'refresh'));
        }

        $errors = $this->db->all("SELECT * FROM logs WHERE level = 'error' AND ts > ? ORDER BY id DESC LIMIT 5", [time() - 86400]);
        if ($errors) {
            $h .= '<h2>Son 24 saatteki hatalar</h2>' . $this->logTable($errors) . '<p><a href="?p=logs&amp;level=error">Tüm hatalar</a></p>';
        }
        $this->render('Onay kuyruğu', $h, [
            'active' => 'dash',
            'sub' => 'Onaylanan servisler sitede yayın için hazırlanır.',
            'actions' => $open ? $this->ui->action('run_cron', 'Kanalları şimdi oku', [], 'quiet', 'refresh') : '',
        ]);
    }

    private function jobs(): void
    {
        $status = (string)($_GET['status'] ?? '');
        $channel = (string)($_GET['channel'] ?? '');
        $q = trim((string)($_GET['q'] ?? ''));
        $page = max(1, (int)($_GET['page'] ?? 1));

        $where = [];
        $args = [];
        if (isset(Flow::LABELS[$status])) {
            $where[] = 'status = ?';
            $args[] = $status;
        }
        if ($channel !== '') {
            $where[] = 'channel = ?';
            $args[] = $channel;
        }
        if ($q !== '') {
            if (ctype_digit($q)) {
                $where[] = '(id = ? OR items_json LIKE ? OR items_json LIKE ?)';
                array_push($args, (int)$q, '%"id":' . $q . ',%', '%"id":' . $q . '}%');
            } else {
                $where[] = '(items_json LIKE ? OR source_text LIKE ?)';
                $like = '%' . str_replace(['\\', '%', '_'], ['\\\\', '\\%', '\\_'], $q) . '%';
                array_push($args, $like, $like);
                $where[count($where) - 1] = "(items_json LIKE ? ESCAPE '\\' OR source_text LIKE ? ESCAPE '\\')";
            }
        }
        $sqlWhere = $where ? 'WHERE ' . implode(' AND ', $where) : '';
        $total = (int)$this->db->one("SELECT COUNT(*) c FROM jobs $sqlWhere", $args)['c'];
        $pages = max(1, (int)ceil($total / self::PER_PAGE));
        $page = min($page, $pages);
        $rows = $this->loadJobs($sqlWhere, $args, 'ORDER BY id DESC LIMIT ' . self::PER_PAGE . ' OFFSET ' . (($page - 1) * self::PER_PAGE));

        // durum çipleri
        $counts = [];
        foreach ($this->db->all('SELECT status, COUNT(*) c FROM jobs GROUP BY status') as $r) {
            $counts[$r['status']] = (int)$r['c'];
        }
        $base = ['p' => 'jobs', 'channel' => $channel, 'q' => $q];
        $chips = '<div class="chips" role="navigation" aria-label="Duruma göre">'
            . '<a class="chip" href="?' . Ui::e(http_build_query($base)) . '"' . ($status === '' ? ' aria-current="true"' : '') . '>Hepsi <span class="n">' . array_sum($counts) . '</span></a>';
        foreach (Flow::LABELS as $k => $v) {
            if (empty($counts[$k]) && $status !== $k) {
                continue;
            }
            $chips .= '<a class="chip" href="?' . Ui::e(http_build_query($base + ['status' => $k])) . '"' . ($status === $k ? ' aria-current="true"' : '') . '>' . $v . ' <span class="n">' . ($counts[$k] ?? 0) . '</span></a>';
        }
        $chips .= '</div>';

        $chOpts = '<option value="">Tüm kanallar</option>';
        foreach ($this->channelsList() as $c) {
            $chOpts .= '<option value="' . Ui::e($c) . '"' . ($c === $channel ? ' selected' : '') . '>@' . Ui::e($c) . '</option>';
        }
        $h = '<form method="get" class="toolbar"><input type="hidden" name="p" value="jobs"><input type="hidden" name="status" value="' . Ui::e($status) . '">'
            . '<label class="grow">Ara<input type="search" name="q" value="' . Ui::e($q) . '" placeholder="Servis ID, iş numarası ya da kelime"></label>'
            . '<label>Kanal<select name="channel">' . $chOpts . '</select></label>'
            . '<button class="btn quiet">' . Ui::icon('search') . '<span>Filtrele</span></button></form>' . $chips;

        if (!$rows) {
            $h .= '<div class="gap-top">' . Ui::empty($q !== '' || $status !== '' || $channel !== '' ? 'Bu filtreyle iş bulunamadı' : 'Henüz iş yok',
                $q !== '' || $status !== '' || $channel !== '' ? 'Filtreyi değiştirin ya da temizleyin.' : 'Kanal okununca servis duyuruları burada iş olarak görünür.',
                $q !== '' || $status !== '' || $channel !== '' ? Ui::link('?p=jobs', 'Filtreyi temizle') : Ui::link('?p=channels', 'Kanal ekle', 'primary')) . '</div>';
            $this->render('İşler', $h, ['active' => 'jobs']);
            return;
        }

        $h .= '<form method="post" action="?p=bulk" class="gap-top">' . $this->ui->csrfField()
            . '<div class="table-wrap"><table><thead><tr><th class="tight"><input type="checkbox" data-checkall aria-label="Tümünü seç" class="js-only"></th>'
            . '<th>İş</th><th>Servisler</th><th>Durum</th><th>Güncelleme</th></tr></thead><tbody>';
        foreach ($rows as $r) {
            $first = $r['items'][0] ?? ['id' => '', 'az' => ''];
            $h .= '<tr><td class="tight"><input type="checkbox" name="ids[]" value="' . (int)$r['id'] . '" aria-label="İş ' . (int)$r['id'] . '"></td>'
                . '<td class="tight"><a href="?p=job&amp;id=' . (int)$r['id'] . '"><b>İş ' . (int)$r['id'] . '</b></a><div class="src">@' . Ui::e($r['channel']) . '</div></td>'
                . '<td><div>' . Ui::e(mb_strimwidth((string)$first['az'], 0, 90, '…')) . '</div><div class="src">' . count($r['items']) . ' servis, ilk ID ' . Ui::e((string)$first['id']) . '</div></td>'
                . '<td class="tight">' . Ui::badge($r['status']) . ($r['status'] === 'failed' && $r['note'] !== '' ? '<div class="src lvl-error">' . Ui::e(mb_strimwidth($r['note'], 0, 60, '…')) . '</div>' : '') . '</td>'
                . '<td class="tight">' . Ui::ago((int)$r['updated_at']) . '</td></tr>';
        }
        $h .= '</tbody></table><div class="bulk"><span class="muted">Seçilenler:</span>'
            . '<button class="btn primary sm" name="do" value="approve">' . Ui::icon('check', 16) . '<span>Onayla</span></button>'
            . '<button class="btn quiet sm" name="do" value="mark">' . Ui::icon('globe', 16) . '<span>Sitede yayınlandı say</span></button>'
            . '<button class="btn danger sm" name="do" value="reject" data-confirm="Seçilen işler reddedilsin mi?">' . Ui::icon('x', 16) . '<span>Reddet</span></button>'
            . '</div></div></form>';

        if ($pages > 1) {
            $h .= '<nav class="pager" aria-label="Sayfalar">'
                . ($page > 1 ? Ui::link('?' . http_build_query($base + ['status' => $status, 'page' => $page - 1]), 'Önceki', 'quiet sm') : '')
                . '<span>Sayfa ' . $page . ' / ' . $pages . ', toplam ' . $total . ' iş</span>'
                . ($page < $pages ? Ui::link('?' . http_build_query($base + ['status' => $status, 'page' => $page + 1]), 'Sonraki', 'quiet sm') : '')
                . '</nav>';
        }
        $this->render('İşler', $h, ['active' => 'jobs']);
    }

    private function job(int $id): void
    {
        $job = $this->db->job($id);
        if ($job === null) {
            $this->render('İş bulunamadı', Ui::empty('Bu numarada iş yok', 'Silinmiş ya da numara yanlış olabilir.', Ui::link('?p=jobs', 'İşlere dön', 'primary')), ['active' => 'jobs']);
            return;
        }
        $st = $job['status'];
        $open = in_array($st, self::OPEN, true);
        $sub = Ui::badge($st) . ' <a href="https://t.me/' . Ui::e($job['channel']) . '/' . (int)$job['msg_id'] . '" target="_blank" rel="noopener noreferrer">@' . Ui::e($job['channel']) . ' gönderisi ' . (int)$job['msg_id'] . '</a>'
            . ', ' . Ui::ago((int)$job['created_at']) . ' geldi';
        $actions = ($open ? Ui::link('?p=job_prompt&id=' . $id, 'claude.ai için hazırla', 'quiet', 'spark') : '')
            . $this->ui->action('job_do', 'Bota tekrar gönder', ['id' => $id, 'do' => 'resend'], 'ghost', 'send');
        $aiState = ['pending' => 'Claude çevirisi sırada (bir sonraki kanal okumada)', 'running' => 'Claude çevirisi yapılıyor', 'failed' => 'Claude çevirisi başarısız, Kayıtlar’a bakın'][$job['ai_state'] ?? ''] ?? '';
        if ($aiState !== '' && $open) {
            $sub .= ', <span class="lvl-warn">' . $aiState . '</span>';
        }

        $h = '';
        if ($job['note'] !== '' && in_array($st, ['failed', 'new'], true)) {
            $h .= '<div class="flash bad">' . Ui::icon('alert') . '<span>' . Ui::e($job['note']) . '</span></div>';
        }

        if ($open) {
            $h .= '<form method="post" action="?p=job_save">' . $this->ui->csrfField() . '<input type="hidden" name="id" value="' . $id . '">'
                . '<div class="table-wrap edit-table"><table><thead><tr><th>ID</th><th>Azerice ad</th><th class="num">Fiyat</th></tr></thead><tbody>';
            foreach ($job['items'] as $it) {
                $sid = (int)$it['id'];
                $h .= '<tr><td class="tight"><b>' . $sid . '</b></td><td>'
                    . '<input type="text" name="az[' . $sid . ']" value="' . Ui::e($it['az']) . '" aria-label="Servis ' . $sid . ' Azerice adı">'
                    . '<div class="src">' . Ui::e($it['name']) . '</div>'
                    . '<div class="src">' . Ui::via($it['via'] ?? null) . '</div>'
                    . (!empty($it['unknown']) ? '<div class="src lvl-warn">Sözlükte yok: ' . Ui::e(implode(', ', $it['unknown'])) . '</div>' : '')
                    . (!empty($it['ai_note']) ? '<div class="src lvl-warn">' . Ui::e($it['ai_note']) . '</div>' : '')
                    . '</td><td class="num tight">' . ($it['price'] !== '' ? '$' . Ui::e($it['price']) : '<span class="muted">—</span>') . '</td></tr>';
            }
            $h .= '</tbody></table></div>'
                . '<div class="actionbar">'
                . '<button class="btn primary" name="do" value="approve">' . Ui::icon('check') . '<span>Kaydet ve onayla</span></button>'
                . '<button class="btn quiet" name="do" value="save">' . Ui::icon('edit') . '<span>Değişiklikleri kaydet</span></button>'
                . '<span class="spacer"></span>'
                . '<button class="btn quiet" name="do" value="regen" data-confirm="Elle yaptığınız değişiklikler silinip ad yeniden oluşturulsun mu?">' . Ui::icon('refresh') . '<span>' . ((new \TgWatch\Ai($this->db, $this->config))->active() ? 'Claude ile yeniden çevir' : 'Yeniden oluştur') . '</span></button>'
                . '<button class="btn danger" name="do" value="reject" data-confirm="Bu iş reddedilsin mi?">' . Ui::icon('x') . '<span>Reddet</span></button>'
                . '</div></form>';

            // eksik kelimeleri tek tıkla sözlüğe ekle
            $words = [];
            foreach ($job['items'] as $it) {
                foreach ($it['unknown'] ?? [] as $w) {
                    $words[mb_strtolower($w)] = $w;
                }
            }
            if ($words) {
                $h .= '<h2>Sözlükte olmayan kelimeler</h2><p class="muted">Çevirisini ekleyin. Bu ve diğer açık işler hemen güncellenir (elle düzenlenen adlar hariç).</p><div class="table-wrap"><table><tbody>';
                foreach ($words as $w) {
                    $h .= '<tr><td class="tight"><b>' . Ui::e($w) . '</b></td><td><form method="post" action="?p=dict_add" class="field-row">' . $this->ui->csrfField()
                        . '<input type="hidden" name="en" value="' . Ui::e($w) . '"><input type="hidden" name="back" value="' . $id . '">'
                        . '<input type="text" name="az" placeholder="Azerice karşılığı" aria-label="' . Ui::e($w) . ' Azerice karşılığı" class="grow">'
                        . '<button class="btn quiet sm">' . Ui::icon('plus', 16) . '<span>Sözlüğe ekle</span></button></form></td></tr>';
                }
                $h .= '</tbody></table></div>';
            }
        } else {
            $h .= '<div class="queue">' . $this->queueCard($job, 1000, false) . '</div>';
            $h .= '<div class="actionbar">';
            if ($st === 'approved') {
                $h .= $this->ui->action('job_do', 'Sitede yayınlandı olarak işaretle', ['id' => $id, 'do' => 'mark'], 'success', 'globe');
                if (($this->config['publisher'] ?? 'manual') !== 'manual') {
                    $h .= $this->ui->action('job_do', 'API ile yayınla', ['id' => $id, 'do' => 'approve'], 'primary', 'send');
                }
            }
            if ($st === 'failed') {
                $h .= $this->ui->action('job_do', 'Tekrar yayınla', ['id' => $id, 'do' => 'approve'], 'primary', 'refresh');
            }
            $h .= '<span class="spacer"></span>';
            if (in_array($st, ['approved', 'failed'], true)) {
                $h .= $this->ui->action('job_do', 'Reddet', ['id' => $id, 'do' => 'reject'], 'danger', 'x', 'Bu iş reddedilsin mi?');
            }
            $h .= '</div>';
        }

        if (in_array($st, ['approved', 'published'], true)) {
            $ids = implode(',', array_map(static fn($i) => (string)$i['id'], $job['items']));
            $lines = implode("\n", array_map(static fn($i) => $i['id'] . ' → ' . $i['az'], $job['items']));
            $h .= '<h2>Panele ekleme bilgisi</h2><p class="muted">Perfect Panel → Services → Import services → sağlayıcıyı seçin ve bu ID\'leri işaretleyin.</p>'
                . '<div class="split"><div class="panel panel-pad stack"><div class="row"><b>Servis ID\'leri</b>' . Ui::copy('pkg-ids') . '</div><div class="code"><pre id="pkg-ids">' . Ui::e($ids) . '</pre></div></div>'
                . '<div class="panel panel-pad stack"><div class="row"><b>Azerice adlar</b>' . Ui::copy('pkg-lines') . Ui::link('?p=job_txt&id=' . $id, '.txt indir', 'ghost sm', 'download') . '</div><div class="code"><pre id="pkg-lines">' . Ui::e($lines) . '</pre></div></div></div>';
        }

        $h .= '<h2>Kaynak duyuru</h2><details class="panel panel-pad"><summary>Telegram\'daki orijinal metni göster</summary><pre>' . Ui::e($job['source_text']) . '</pre></details>';
        $this->render('İş ' . $id, $h, ['active' => 'jobs', 'sub' => $sub, 'actions' => $actions]);
    }

    private function jobTxt(int $id): void
    {
        $job = $this->db->job($id);
        if ($job === null) {
            http_response_code(404);
            $this->render('İş bulunamadı', '', ['active' => 'jobs']);
            return;
        }
        header('Content-Type: text/plain; charset=utf-8');
        header('Content-Disposition: attachment; filename="import_' . $id . '.txt"');
        echo ManualPublisher::package($job);
    }

    private function channels(): void
    {
        $rows = $this->db->all('SELECT c.*, (SELECT COUNT(*) FROM jobs j WHERE j.channel = c.username) jobs FROM channels c ORDER BY c.username');
        $h = '<form method="post" action="?p=channel_add" class="toolbar">' . $this->ui->csrfField()
            . '<label class="grow">Kanal ekle<input type="text" name="username" placeholder="@kanaladi ya da https://t.me/kanaladi" required></label>'
            . '<button class="btn primary">' . Ui::icon('plus') . '<span>Ekle</span></button></form>'
            . '<p class="muted lead">Kanal herkese açık olmalı (t.me/s/kanaladi açılabilmeli). İlk okumada eski gönderiler atlanır, sonraki duyurular işlenir.</p>';

        if (!empty($_SESSION['test_report'])) {
            $h .= '<div class="panel panel-pad stack"><b>Okuma sonucu</b><div class="code"><pre>' . Ui::e($_SESSION['test_report']) . '</pre></div></div>';
            unset($_SESSION['test_report']);
        }

        if (!$rows) {
            $h .= Ui::empty('Henüz kanal yok', 'Yukarıdan bir sağlayıcı kanalı ekleyin. Test ile kaydetmeden okuyup çeviriyi görebilirsiniz.');
            $this->render('Kanallar', $h, ['active' => 'channels']);
            return;
        }
        $h .= '<div class="table-wrap gap-top"><table><thead><tr><th>Kanal</th><th>Durum</th><th>Son gönderi</th><th>Son okuma</th><th class="num">İş</th><th></th></tr></thead><tbody>';
        foreach ($rows as $c) {
            $u = Ui::e($c['username']);
            [$state, $txt] = !$c['enabled'] ? ['', 'Duraklatıldı'] : ($c['last_error'] ? ['bad', Ui::e($c['last_error'])] : ($c['last_ok_at'] ? ['ok', 'Çalışıyor'] : ['warn', 'Henüz okunmadı']));
            $h .= '<tr><td><a href="https://t.me/s/' . $u . '" target="_blank" rel="noopener noreferrer"><b>@' . $u . '</b></a>' . ($c['title'] !== '' ? '<div class="src">' . Ui::e($c['title']) . '</div>' : '') . '</td>'
                . '<td><span class="row nowrap">' . Ui::dot($state) . '<span>' . $txt . '</span></span></td>'
                . '<td class="tight">' . ($c['last_post_id'] ? '<a href="https://t.me/' . $u . '/' . (int)$c['last_post_id'] . '" target="_blank" rel="noopener noreferrer">' . (int)$c['last_post_id'] . '</a>' : '<span class="muted">—</span>') . '</td>'
                . '<td class="tight">' . Ui::ago($c['last_checked_at'] ? (int)$c['last_checked_at'] : null) . '</td>'
                . '<td class="num tight"><a href="?p=jobs&amp;channel=' . $u . '">' . (int)$c['jobs'] . '</a></td>'
                . '<td class="tight"><div class="row">'
                . $this->ui->action('channel_test', 'Test', ['id' => $c['id']], 'quiet sm', 'play')
                . $this->ui->action('channel_toggle', $c['enabled'] ? 'Duraklat' : 'Devam et', ['id' => $c['id']], 'ghost sm', $c['enabled'] ? 'pause' : 'play')
                . $this->ui->action('channel_delete', 'Sil', ['id' => $c['id']], 'ghost sm', 'trash', '@' . $c['username'] . ' silinsin mi? İşleri silinmez.')
                . '</div></td></tr>';
        }
        $h .= '</tbody></table></div>';
        $h .= '<h2>Geçmiş duyuruları işle</h2><form method="post" action="?p=backfill" class="panel panel-pad field-row">' . $this->ui->csrfField()
            . '<label>Her kanalın son<input type="number" name="n" value="3" min="1" max="20" class="narrow"></label>'
            . '<span class="muted note">gönderisini okuyup servis duyurularından iş oluşturur ve bota onay mesajı gönderir. Daha önce işlenen gönderiler atlanır.</span>'
            . '<button class="btn quiet">' . Ui::icon('play') . '<span>Çalıştır</span></button></form>';
        $this->render('Kanallar', $h, ['active' => 'channels', 'actions' => $this->ui->action('run_cron', 'Şimdi oku', ['back' => 'channels'], 'quiet', 'refresh')]);
    }

    private function dict(?array $draft = null, string $previewIn = '', string $previewOut = '', bool $openBulk = false): void
    {
        $d = $draft ?? Dictionary::load($this->db);
        $guess = array_flip($d['guesses'] ?? []);
        $q = trim((string)($_GET['q'] ?? ''));

        $h = '';
        $missing = $this->missingWords();
        $h .= '<h2 class="first">Eksik kelimeler</h2>';
        if ($missing) {
            $h .= '<p class="muted">Açık işlerde geçen ama sözlükte olmayan kelimeler. Çevirisini eklediğinizde açık işler hemen güncellenir.</p><div class="table-wrap"><table><thead><tr><th>Kelime</th><th class="num">Geçtiği yer</th><th>Azerice karşılığı</th></tr></thead><tbody>';
            foreach (array_slice($missing, 0, 40, true) as $m) {
                $h .= '<tr><td class="tight"><b>' . Ui::e($m['word']) . '</b><div class="src">' . Ui::e(mb_strimwidth($m['example'], 0, 60, '…')) . '</div></td>'
                    . '<td class="num tight"><a href="?p=job&amp;id=' . $m['job'] . '">' . $m['n'] . ' servis</a></td>'
                    . '<td><form method="post" action="?p=dict_add" class="field-row">' . $this->ui->csrfField() . '<input type="hidden" name="en" value="' . Ui::e($m['word']) . '"><input type="hidden" name="back" value="dict">'
                    . '<input type="text" name="az" placeholder="Azerice karşılığı" class="grow" aria-label="' . Ui::e($m['word']) . ' Azerice karşılığı"><button class="btn quiet sm">' . Ui::icon('plus', 16) . '<span>Ekle</span></button>'
                    . '<button class="btn ghost sm" name="keep" value="1" title="Marka adı gibi, çevrilmesin">Çevirme</button></form></td></tr>';
            }
            $h .= '</tbody></table></div>';
        } else {
            $h .= '<p class="muted">Açık işlerde sözlükte olmayan kelime yok.</p>';
        }

        $h .= '<h2>Yeni ifade</h2><form method="post" action="?p=dict_add" class="panel panel-pad field-row">' . $this->ui->csrfField() . '<input type="hidden" name="back" value="dict">'
            . '<label class="grow">İngilizce<input type="text" name="en" placeholder="Story Views" required></label>'
            . '<label class="grow">Azerice<input type="text" name="az" placeholder="Hekayə Baxışı" required></label>'
            . '<button class="btn primary">' . Ui::icon('plus') . '<span>Ekle</span></button></form>';

        $h .= '<h2>Ad kalıbı</h2><form method="post" action="?p=dict_opts" class="panel panel-pad field-row">' . $this->ui->csrfField()
            . '<label class="check"><input type="checkbox" name="keep_max" value="1"' . (!empty($d['options']['keep_max']) ? ' checked' : '') . '> Adda “Maks 50K” kalsın</label>'
            . '<label class="check"><input type="checkbox" name="keep_speed" value="1"' . (!empty($d['options']['keep_speed']) ? ' checked' : '') . '> Adda “Gündə 5K” kalsın</label>'
            . '<span class="grow"></span><button class="btn quiet">Kaydet</button></form>';

        // sözlük tablosu
        $phrases = $d['phrases'];
        if ($q !== '') {
            $phrases = array_filter($phrases, static fn($az, $en) => mb_stripos($en, $q) !== false || mb_stripos($az, $q) !== false, ARRAY_FILTER_USE_BOTH);
        }
        ksort($phrases, SORT_NATURAL | SORT_FLAG_CASE);
        $h .= '<h2>Sözlük <span class="hint">(' . count($d['phrases']) . ' ifade)</span></h2>'
            . '<form method="get" class="toolbar"><input type="hidden" name="p" value="dict"><label class="grow">Sözlükte ara<input type="search" name="q" value="' . Ui::e($q) . '" placeholder="Followers, İzləyici…"></label><button class="btn quiet">' . Ui::icon('search') . '<span>Ara</span></button></form>';
        if ($phrases) {
            $h .= '<div class="table-wrap"><table><thead><tr><th>İngilizce</th><th>Azerice</th><th></th></tr></thead><tbody>';
            foreach ($phrases as $en => $az) {
                $h .= '<tr><td>' . Ui::e((string)$en) . '</td><td><b>' . Ui::e((string)$az) . '</b>' . (isset($guess[$en]) ? ' <span class="badge s-approved" title="Henüz onaylanmamış tahmin">tahmin</span>' : '') . '</td>'
                    . '<td class="tight"><div class="row">'
                    . (isset($guess[$en]) ? $this->ui->action('dict_confirm', 'Doğru', ['en' => $en], 'ghost sm', 'check') : '')
                    . $this->ui->action('dict_del', 'Sil', ['en' => $en], 'ghost sm', 'trash', '“' . $en . '” sözlükten silinsin mi?') . '</div></td></tr>';
            }
            $h .= '</tbody></table></div>';
        } else {
            $h .= '<p class="muted">Aramayla eşleşen ifade yok.</p>';
        }

        // toplu düzenleme
        $h .= '<h2>Toplu düzenleme ve kurallar</h2><details class="panel panel-pad"' . ($openBulk ? ' open' : '') . '><summary>Kuralları, sözlüğü ve çevrilmeyecek kelimeleri metin olarak düzenle</summary>'
            . '<form method="post" action="?p=dict_save" class="stack">' . $this->ui->csrfField()
            . '<input type="hidden" name="keep_max" value="' . (!empty($d['options']['keep_max']) ? '1' : '') . '"><input type="hidden" name="keep_speed" value="' . (!empty($d['options']['keep_speed']) ? '1' : '') . '">'
            . '<label>Kurallar <span class="hint">— her satır “regex =&gt; çıktı”, sözlükten önce ve sırayla uygulanır, $1 yakalanan grubu yazar</span><textarea name="rules" rows="9">' . Ui::e(Dictionary::rulesToText($d['rules'])) . '</textarea></label>'
            . '<label>Sözlük <span class="hint">— her satır “English = Azərbaycanca”, uzun ifadeler önce eşleşir</span><textarea name="phrases" rows="12">' . Ui::e(Dictionary::phrasesToText($d['phrases'])) . '</textarea></label>'
            . '<label>Çevrilmeyecek kelimeler <span class="hint">— marka adları, virgülle ayırın</span><textarea name="keep" rows="3">' . Ui::e(implode(', ', $d['keep'])) . '</textarea></label>'
            . '<label>Tahmini çeviriler <span class="hint">— doğruladıklarınızı silin</span><textarea name="guesses" rows="2">' . Ui::e(implode(', ', array_keys($guess))) . '</textarea></label>'
            . '<label>Önizleme <span class="hint">— her satıra bir servis adı ya da duyuru satırı</span><textarea name="preview" rows="4">' . Ui::e($previewIn !== '' ? $previewIn : 'Threads Followers | No Drop | Max 50K | 5K/Day | 30 Days Refill ♻️') . '</textarea></label>'
            . ($previewOut !== '' ? '<div class="code"><pre>' . Ui::e($previewOut) . '</pre></div>' : '')
            . '<div class="row"><button class="btn quiet" name="do" value="preview">Önizle</button><button class="btn primary" name="do" value="save">Kaydet</button><span class="grow"></span>'
            . '<button class="btn danger" name="do" value="reset" data-confirm="Sözlük ve kurallar varsayılana dönsün mü? Eklediğiniz ifadeler silinir.">Varsayılana dön</button></div></form></details>';

        $this->render('Sözlük', $h, ['active' => 'dict', 'sub' => 'Servis adları önce kurallarla, sonra bu sözlükle Azericeye çevrilir.']);
    }

    private function logs(): void
    {
        $level = (string)($_GET['level'] ?? '');
        $source = (string)($_GET['source'] ?? '');
        $where = [];
        $args = [];
        if (in_array($level, ['error', 'warn', 'info'], true)) {
            $where[] = 'level = ?';
            $args[] = $level;
        }
        if ($source !== '') {
            $where[] = 'source = ?';
            $args[] = $source;
        }
        $rows = $this->db->all('SELECT * FROM logs ' . ($where ? 'WHERE ' . implode(' AND ', $where) : '') . ' ORDER BY id DESC LIMIT 300', $args);
        $sources = array_column($this->db->all('SELECT DISTINCT source FROM logs ORDER BY source'), 'source');

        $chips = '<div class="chips">';
        foreach (['' => 'Hepsi', 'error' => 'Hatalar', 'warn' => 'Uyarılar', 'info' => 'Bilgi'] as $k => $v) {
            $chips .= '<a class="chip" href="?' . Ui::e(http_build_query(['p' => 'logs', 'level' => $k, 'source' => $source])) . '"' . ($level === $k ? ' aria-current="true"' : '') . '>' . $v . '</a>';
        }
        $chips .= '</div>';
        $srcOpts = '<option value="">Tüm kaynaklar</option>';
        foreach ($sources as $s) {
            $srcOpts .= '<option' . ($s === $source ? ' selected' : '') . '>' . Ui::e($s) . '</option>';
        }
        $h = '<form method="get" class="toolbar"><input type="hidden" name="p" value="logs"><input type="hidden" name="level" value="' . Ui::e($level) . '">'
            . '<label>Kaynak<select name="source">' . $srcOpts . '</select></label><button class="btn quiet">Filtrele</button><span class="grow"></span>'
            . '</form>' . $chips . '<div class="gap-top">' . $this->logTable($rows) . '</div>';
        $this->render('Kayıtlar', $h, ['active' => 'logs', 'actions' => $this->ui->action('logs_clear', '7 günden eskileri sil', [], 'ghost', 'trash', '7 günden eski kayıtlar silinsin mi?')]);
    }

    private function logTable(array $rows): string
    {
        if (!$rows) {
            return Ui::empty('Kayıt yok', 'Bu filtreyle eşleşen kayıt bulunmadı.');
        }
        $label = ['error' => 'Hata', 'warn' => 'Uyarı', 'info' => 'Bilgi'];
        $h = '<div class="table-wrap"><table><thead><tr><th>Zaman</th><th>Tür</th><th>Kaynak</th><th>Mesaj</th></tr></thead><tbody>';
        foreach ($rows as $r) {
            $h .= '<tr><td class="tight">' . Ui::ago((int)$r['ts']) . '</td><td class="tight lvl-' . Ui::e($r['level']) . '">' . ($label[$r['level']] ?? Ui::e($r['level'])) . '</td>'
                . '<td class="tight muted">' . Ui::e($r['source']) . '</td><td><pre>' . Ui::e($r['message']) . '</pre></td></tr>';
        }
        return $h . '</tbody></table></div>';
    }

    private function health(bool $network): void
    {
        $rows = Health::local($this->config);
        if ($network) {
            $rows = array_merge($rows, Health::network($this->config));
        }
        $bad = count(array_filter($rows, static fn($r) => $r[1] === Health::FAIL));
        $h = $bad ? '<div class="flash bad">' . Ui::icon('alert') . '<span>' . $bad . ' sorun var. Kırmızı satırları düzeltin.</span></div>' : '';
        $h .= '<div class="table-wrap"><table><thead><tr><th>Kontrol</th><th>Sonuç</th></tr></thead><tbody>';
        foreach ($rows as [$name, $st, $detail]) {
            $dot = [Health::OK => 'ok', Health::WARN => 'warn', Health::FAIL => 'bad'][$st];
            $h .= '<tr><td class="tight"><span class="row nowrap">' . Ui::dot($dot) . '<span>' . Ui::e($name) . '</span></span></td><td>' . Ui::e($detail) . '</td></tr>';
        }
        $h .= '</tbody></table></div>';
        if (!$network) {
            $h .= '<p class="muted">Telegram, bot ve webhook bağlantısı için ağ testlerini çalıştırın.</p>';
        }

        $cronUrl = (string)$this->config['cron_key'] !== '' ? $this->siblingUrl('webcron.php') . '?key=' . $this->config['cron_key'] : '';
        $h .= '<h2>Kurulum bilgileri</h2><div class="table-wrap"><table><tbody>'
            . '<tr><td class="tight">tgwatch klasörü</td><td><code>' . Ui::e(TGWATCH_ROOT) . '</code></td></tr>'
            . '<tr><td class="tight">Webhook adresi</td><td><div class="row"><code id="hook-url">' . Ui::e($this->suggestedWebhook()) . '</code>' . Ui::copy('hook-url')
            . ((string)$this->config['webhook_url'] === $this->suggestedWebhook() ? '<span class="muted">config.php ile aynı</span>' : '<span class="lvl-warn">config.php’dekinden farklı</span>') . '</div></td></tr>'
            . '<tr><td class="tight">cron-job.org adresi</td><td>' . ($cronUrl !== ''
                ? '<div class="row"><code id="cron-url">' . Ui::e($cronUrl) . '</code>' . Ui::copy('cron-url') . '</div><div class="src">Her dakika çağrılacak şekilde ayarlayın. Anahtar içerir, paylaşmayın.</div>'
                : '<span class="lvl-error">config.php içinde cron_key yok</span>') . '</td></tr>'
            . '<tr><td class="tight">Sunucu cron komutu</td><td><code>PHP_YOLU ' . Ui::e(TGWATCH_ROOT) . '/cron.php &gt;/dev/null 2&gt;&amp;1</code><div class="src">Yalnızca hosting cron’u kullanırsanız. PHP_YOLU hosting’e göre değişir.</div></td></tr>'
            . '</tbody></table></div>';

        if ($this->config['bot_mode'] === 'webhook') {
            $h .= '<h2>Telegram webhook</h2><div class="row">'
                . $this->ui->action('webhook', 'Webhook’u kur', ['do' => 'set'], 'primary', 'link')
                . $this->ui->action('webhook', 'Durumu göster', ['do' => 'info'], 'quiet')
                . $this->ui->action('webhook', 'Webhook’u kaldır', ['do' => 'delete'], 'danger', 'x', 'Webhook kaldırılırsa butonlar çalışmaz (polling moduna geçmiyorsanız). Devam edilsin mi?') . '</div>';
        } else {
            $h .= '<h2>Telegram</h2><p class="muted">Bot polling modunda: butonlar cron her çalıştığında işlenir. Telegram’da webhook kurulu kalmışsa kaldırın.</p>'
                . $this->ui->action('webhook', 'Webhook’u kaldır', ['do' => 'delete'], 'quiet');
        }
        if (!empty($_SESSION['webhook_report'])) {
            $h .= '<div class="code gap-top"><pre>' . Ui::e($_SESSION['webhook_report']) . '</pre></div>';
            unset($_SESSION['webhook_report']);
        }
        $this->render('Sağlık', $h, ['active' => 'health', 'actions' => $this->ui->action('health_net', 'Ağ testlerini çalıştır', [], 'quiet', 'play')]);
    }

    // ---------------------------------------------------------------- POST eylemleri

    /** Açık işlerin adlarını güncel sözlükle yeniler (elle düzenlenenler hariç). */
    private function refreshOpenJobs(): int
    {
        $flow = App::flow();
        $n = 0;
        foreach ($this->db->all("SELECT id FROM jobs WHERE status IN ('new','awaiting','editing')") as $r) {
            $n += $flow->refreshTranslations((int)$r['id']) ? 1 : 0;
        }
        return $n;
    }

    private function backTo(string $back): void
    {
        ctype_digit($back) ? $this->redirect('job', ['id' => (int)$back]) : $this->redirect('dict');
    }

    private function post(string $p): void
    {
        $id = (int)($_POST['id'] ?? 0);
        try {
            if ($this->postBank($p)) {
                return;
            }
            switch ($p) {
                case 'logout':
                    $_SESSION = [];
                    session_regenerate_id(true);
                    $this->redirect('dash');
                    return;

                case 'job_save':
                    $flow = App::flow();
                    $do = (string)($_POST['do'] ?? 'save');
                    $job = $this->db->job($id);
                    $changed = 0;
                    if ($job && in_array($do, ['save', 'approve'], true) && is_array($_POST['az'] ?? null)) {
                        $lines = [];
                        foreach ($job['items'] as $it) {
                            $new = trim((string)($_POST['az'][$it['id']] ?? $it['az']));
                            if ($new !== '' && $new !== $it['az']) {
                                $lines[] = $it['id'] . ' | ' . $new;
                            }
                        }
                        if ($lines) {
                            $changed = $flow->applyEdits($id, implode("\n", $lines))['changed'];
                        }
                    }
                    $msg = match ($do) {
                        'approve' => $flow->approve($id),
                        'regen' => $flow->regenerate($id),
                        'reject' => $flow->reject($id),
                        default => $changed ? "$changed ad kaydedildi." : 'Değişiklik yok.',
                    };
                    if ($do === 'save' && $changed) {
                        $flow->sendPreview($id);
                    }
                    $this->say(str_contains($msg, 'hata') ? 'bad' : 'ok', ($changed && $do === 'approve' ? "$changed ad kaydedildi. " : '') . $msg);
                    $do === 'approve' && !str_contains($msg, 'hata') ? $this->redirect('dash') : $this->redirect('job', ['id' => $id]);
                    return;

                case 'job_do':
                    $flow = App::flow();
                    $do = (string)($_POST['do'] ?? '');
                    $msg = match ($do) {
                        'approve' => $flow->approve($id, true),
                        'reject' => $flow->reject($id),
                        'regen' => $flow->regenerate($id),
                        'resend' => $flow->sendPreview($id) ? 'Onay mesajı bota gönderildi.' : 'Bota gönderilemedi. Kayıtlar sayfasına bakın.',
                        'mark' => $flow->markPublished($id),
                        default => 'Bilinmeyen işlem.',
                    };
                    $this->say(str_contains($msg, 'hata') || str_contains($msg, 'gönderilemedi') || str_contains($msg, 'zaten') ? 'bad' : 'ok', "İş $id: $msg");
                    ($_POST['back'] ?? '') === 'dash' ? $this->redirect('dash') : $this->redirect('job', ['id' => $id]);
                    return;

                case 'bulk':
                    $flow = App::flow();
                    $ids = array_values(array_unique(array_map('intval', (array)($_POST['ids'] ?? []))));
                    $do = (string)($_POST['do'] ?? '');
                    if (!$ids) {
                        $this->say('bad', 'Önce listeden iş seçin.');
                        $this->redirect('jobs');
                        return;
                    }
                    $okCount = 0;
                    foreach ($ids as $jid) {
                        $msg = match ($do) {
                            'approve' => $flow->approve($jid),
                            'reject' => $flow->reject($jid),
                            'mark' => $flow->markPublished($jid),
                            default => 'x',
                        };
                        $okCount += (str_starts_with($msg, 'Onaylandı') || str_starts_with($msg, 'Reddedildi') || str_starts_with($msg, 'Yayınlandı')) ? 1 : 0;
                    }
                    $verb = ['approve' => 'onaylandı', 'reject' => 'reddedildi', 'mark' => 'yayınlandı olarak işaretlendi'][$do] ?? 'işlendi';
                    $skipped = count($ids) - $okCount;
                    $this->say($okCount ? 'ok' : 'bad', "$okCount iş $verb." . ($skipped ? " $skipped iş bu duruma uygun olmadığı için atlandı." : ''));
                    $this->redirect('jobs');
                    return;

                case 'run_cron':
                    $lines = [];
                    $limit = max(10, min((int)$this->config['web_cron_time_limit'], 25));
                    $errors = (new CronRunner($this->config, static function (string $s) use (&$lines): void {
                        $lines[] = $s;
                    }))->run(false, 0, null, $limit);
                    $new = (int)$this->db->one("SELECT COUNT(*) c FROM jobs WHERE created_at >= ?", [time() - $limit - 10])['c'];
                    $this->say($errors ? 'bad' : 'ok', $errors === null ? 'Okuma zaten çalışıyor, biraz sonra tekrar deneyin.'
                        : ($errors ? "Kanallar okundu, $errors hata var. Ayrıntı Kayıtlar sayfasında." : 'Kanallar okundu' . ($new ? ", $new yeni iş." : ', yeni duyuru yok.')));
                    $this->redirect(($_POST['back'] ?? '') === 'channels' ? 'channels' : 'dash');
                    return;

                case 'channel_add':
                    $w = new Watcher($this->db, App::flow(), new ChannelReader((int)$this->config['fetch_timeout']));
                    $u = $w->addChannel((string)($_POST['username'] ?? ''));
                    $this->db->log('info', 'web', "Kanal eklendi: @$u");
                    $this->say('ok', "@$u eklendi. Kaydetmeden denemek için Test’e basın.");
                    $this->redirect('channels');
                    return;
                case 'channel_toggle':
                    $this->db->run('UPDATE channels SET enabled = 1 - enabled WHERE id = ?', [$id]);
                    $this->redirect('channels');
                    return;
                case 'channel_delete':
                    $c = $this->db->one('SELECT username FROM channels WHERE id = ?', [$id]);
                    $this->db->run('DELETE FROM channels WHERE id = ?', [$id]);
                    $this->say('ok', '@' . ($c['username'] ?? '?') . ' silindi.');
                    $this->redirect('channels');
                    return;
                case 'channel_test':
                    $c = $this->db->one('SELECT * FROM channels WHERE id = ?', [$id]);
                    if ($c) {
                        $w = new Watcher($this->db, App::flow(), new ChannelReader((int)$this->config['fetch_timeout']));
                        $r = $w->checkChannel($c, 5, true);
                        $tr = App::flow()->engine();
                        $out = $r['ok'] ? "@{$c['username']} okundu" . ($r['title'] !== '' ? " ({$r['title']})" : '') . ". Son {$r['posts']} gönderide " . count($r['found']) . " servis duyurusu var. Hiçbir şey kaydedilmedi.\n" : "@{$c['username']} okunamadı: {$r['error']}\n";
                        foreach ($r['found'] as $f) {
                            $out .= "\nGönderi {$f['id']}\n";
                            foreach ($f['items'] as $it) {
                                $t = $tr->translate($it['name']);
                                $out .= "  {$it['id']}  {$t['text']}" . ($t['unknown'] ? '   (sözlükte yok: ' . implode(', ', $t['unknown']) . ')' : '') . "\n";
                            }
                        }
                        $_SESSION['test_report'] = $out;
                    }
                    $this->redirect('channels');
                    return;
                case 'backfill':
                    $n = max(1, min(20, (int)($_POST['n'] ?? 3)));
                    $w = new Watcher($this->db, App::flow(), new ChannelReader((int)$this->config['fetch_timeout']));
                    $out = '';
                    foreach ($w->runAll($n) as $u => $r) {
                        $out .= $r['ok']
                            ? "@$u: son {$r['posts']} gönderi, " . count($r['found']) . ' servis duyurusu, yeni iş: ' . (implode(', ', $r['jobs']) ?: 'yok (daha önce işlenmiş olabilir)') . "\n"
                            : "@$u okunamadı: {$r['error']}\n";
                    }
                    $this->db->log('info', 'web', "Son $n gönderi işlendi");
                    $_SESSION['test_report'] = $out !== '' ? $out : 'Etkin kanal yok.';
                    $this->redirect('channels');
                    return;

                case 'dict_add':
                    $en = trim((string)($_POST['en'] ?? ''));
                    $az = trim((string)($_POST['az'] ?? ''));
                    $back = (string)($_POST['back'] ?? 'dict');
                    $d = Dictionary::load($this->db);
                    if ($en === '') {
                        throw new \InvalidArgumentException('İngilizce ifade boş olamaz.');
                    }
                    if (!empty($_POST['keep'])) {
                        $d['keep'][] = $en;
                        $d['keep'] = array_values(array_unique($d['keep']));
                        $label = "“{$en}” çevrilmeyecek kelimelere eklendi.";
                    } else {
                        if ($az === '') {
                            throw new \InvalidArgumentException("“{$en}” için Azerice karşılık yazın.");
                        }
                        $d['phrases'][$en] = $az;
                        $label = "“{$en}” → “{$az}” eklendi.";
                    }
                    Dictionary::save($this->db, $d);
                    $n = $this->refreshOpenJobs();
                    $this->db->log('info', 'web', 'Sözlük: ' . $label);
                    $this->say('ok', $label . ($n ? " $n açık iş güncellendi." : ''));
                    $this->backTo($back);
                    return;
                case 'dict_del':
                case 'dict_confirm':
                    $en = (string)($_POST['en'] ?? '');
                    $d = Dictionary::load($this->db);
                    if ($p === 'dict_del') {
                        unset($d['phrases'][$en]);
                        $msg = "“{$en}” silindi.";
                    } else {
                        $msg = "“{$en}” doğrulandı.";
                    }
                    $d['guesses'] = array_values(array_diff($d['guesses'] ?? [], [$en]));
                    Dictionary::save($this->db, $d);
                    $n = $p === 'dict_del' ? $this->refreshOpenJobs() : 0;
                    $this->say('ok', $msg . ($n ? " $n açık iş güncellendi." : ''));
                    $this->redirect('dict');
                    return;
                case 'dict_opts':
                    $d = Dictionary::load($this->db);
                    $d['options'] = ['keep_max' => !empty($_POST['keep_max']), 'keep_speed' => !empty($_POST['keep_speed'])];
                    Dictionary::save($this->db, $d);
                    $n = $this->refreshOpenJobs();
                    $this->say('ok', 'Ad kalıbı kaydedildi.' . ($n ? " $n açık iş güncellendi." : ''));
                    $this->redirect('dict');
                    return;
                case 'dict_save':
                    $this->dictPost();
                    return;

                case 'logs_clear':
                    $n = $this->db->run('DELETE FROM logs WHERE ts < ?', [time() - 7 * 86400]);
                    $this->say('ok', "$n eski kayıt silindi.");
                    $this->redirect('logs');
                    return;

                case 'health_net':
                    $this->health(true);
                    return;
                case 'webhook':
                    $tg = App::telegram();
                    $do = (string)($_POST['do'] ?? '');
                    if ($do === 'set') {
                        $url = (string)$this->config['webhook_url'];
                        $secret = (string)$this->config['webhook_secret'];
                        if (!str_starts_with($url, 'https://') || !preg_match('/^[A-Za-z0-9_-]{16,256}$/', $secret)) {
                            throw new \RuntimeException('config.php içinde webhook_url (https://…) ve webhook_secret ayarlı olmalı.');
                        }
                        $tg->call('setWebhook', ['url' => $url, 'secret_token' => $secret, 'allowed_updates' => ['message', 'callback_query'], 'max_connections' => 5]);
                        $report = "Webhook kuruldu: $url\nBirkaç saniye sonra Durumu göster’e basın, sonra bota /start yazın.";
                    } elseif ($do === 'delete') {
                        $tg->call('deleteWebhook', ['drop_pending_updates' => false]);
                        $report = 'Webhook kaldırıldı.';
                    } else {
                        $i = $tg->call('getWebhookInfo');
                        $report = 'Adres: ' . (($i['url'] ?? '') ?: '(kurulu değil)') . "\nBekleyen güncelleme: " . ($i['pending_update_count'] ?? 0)
                            . "\nSon hata: " . (!empty($i['last_error_message']) ? $i['last_error_message'] . ' (' . date('d.m H:i', (int)$i['last_error_date']) . ')' : 'yok');
                    }
                    $this->db->log('info', 'web', "Webhook: $do");
                    $_SESSION['webhook_report'] = $report;
                    $this->redirect('health');
                    return;
            }
            $this->redirect('dash');
        } catch (\Throwable $e) {
            $this->db->log('error', 'web', "$p: " . $e->getMessage());
            $this->say('bad', $e->getMessage());
            if (in_array($p, ['job_do', 'job_save'], true) && $id) {
                $this->redirect('job', ['id' => $id]);
            } elseif (str_starts_with($p, 'channel') || $p === 'backfill') {
                $this->redirect('channels');
            } elseif (str_starts_with($p, 'dict')) {
                $this->backTo((string)($_POST['back'] ?? 'dict'));
            } elseif ($p === 'webhook' || $p === 'health_net') {
                $this->redirect('health');
            } elseif ($p === 'job_paste' && $id) {
                $this->redirect('job_prompt', ['id' => $id]);
            } elseif (str_starts_with($p, 'source') || str_starts_with($p, 'tm_') || $p === 'match_run') {
                $this->redirect('bank');
            } elseif ($p === 'ai_save') {
                $this->redirect('ai');
            } else {
                $this->redirect('dash');
            }
        }
    }

    private function dictPost(): void
    {
        $do = (string)($_POST['do'] ?? 'preview');
        if ($do === 'reset') {
            Dictionary::save($this->db, Dictionary::defaults());
            $n = $this->refreshOpenJobs();
            $this->db->log('info', 'web', 'Sözlük varsayılanlara döndürüldü');
            $this->say('ok', 'Sözlük varsayılana döndü.' . ($n ? " $n açık iş güncellendi." : ''));
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
            'remove' => Dictionary::load($this->db)['remove'] ?? [], // Çeviri ayarları sayfasında düzenlenir
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
            $items = Parser::parse($line);
            $name = $items ? $items[0]['name'] : $line;
            $r = $tr->translate($name);
            $out .= ($items ? $items[0]['id'] . '  ' : '') . $r['text'] . ($r['unknown'] ? '   (sözlükte yok: ' . implode(', ', $r['unknown']) . ')' : '') . "\n";
        }
        if ($do === 'save' && !$errors) {
            Dictionary::save($this->db, $draft);
            $n = $this->refreshOpenJobs();
            $this->db->log('info', 'web', 'Sözlük kaydedildi');
            $this->flash[] = ['ok', 'Sözlük kaydedildi.' . ($n ? " $n açık iş güncellendi." : '')];
        } elseif ($do === 'save') {
            $this->flash[] = ['bad', 'Hatalı kurallar olduğu için kaydedilmedi.'];
        }
        $this->dict($draft, $in, $out, true);
    }
}
