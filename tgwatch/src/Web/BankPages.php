<?php
declare(strict_types=1);

namespace TgWatch\Web;

use TgWatch\Ai;
use TgWatch\App;
use TgWatch\Dictionary;
use TgWatch\Engine;
use TgWatch\Matcher;
use TgWatch\Memory;
use TgWatch\SmmApi;

/** Çeviri bankası ve çeviri ayarları sayfaları (Admin içinde kullanılır). */
trait BankPages
{
    private static function mask(string $key): string
    {
        return $key === '' ? '—' : str_repeat('•', 8) . mb_substr($key, -4);
    }

    // ---------------------------------------------------------------- Çeviri bankası

    /** İzlenen sağlayıcıda şimdiye kadar görülen servis ID'leri (yedek ve kontrol için .txt). */
    private function seenTxt(int $id): void
    {
        $src = $this->db->one("SELECT * FROM sources WHERE id = ? AND kind = 'provider'", [$id]);
        if (!$src) {
            $this->redirect('bank');
            return;
        }
        header('Content-Type: text/plain; charset=utf-8');
        header('Content-Disposition: attachment; filename="gorulen-servisler-' . preg_replace('/[^A-Za-z0-9_-]/', '', (string)$src['name']) . '-' . date('Y-m-d') . '.txt"');
        echo "# {$src['name']}: şimdiye kadar görülen servis ID'leri (ID <TAB> ilk görülme <TAB> ad)\n";
        foreach ($this->db->all('SELECT a.service_id, a.first_seen, r.name FROM api_seen a LEFT JOIN remote_services r ON r.source_id = a.source_id AND r.service_id = a.service_id WHERE a.source_id = ? ORDER BY a.service_id', [$id]) as $r) {
            echo $r['service_id'] . "\t" . date('Y-m-d H:i', (int)$r['first_seen']) . "\t" . str_replace(["\t", "\r", "\n"], ' ', (string)($r['name'] ?? '')) . "\n";
        }
    }

    private function bank(): void
    {
        $mem = new Memory($this->db);
        $st = $mem->stats();
        $sources = $this->db->all('SELECT * FROM sources ORDER BY kind DESC, id');
        $hasOwn = (bool)array_filter($sources, static fn($s) => $s['kind'] === 'own');

        $okSvc = $st['service']['ok'] ?? 0;
        $h = '<section class="strip" aria-label="Banka durumu">'
            . '<div><div class="k">' . Ui::dot($okSvc ? 'ok' : 'warn') . 'Bankadaki ad çifti</div><div class="v">' . $okSvc . '</div></div>'
            . '<div><div class="k">' . Ui::dot('ok') . 'Öğrenilen parça</div><div class="v">' . $st['segments'] . '</div></div>'
            . '<div><div class="k">' . Ui::dot(($st['service']['review'] ?? 0) ? 'warn' : 'ok') . 'Kontrol bekleyen eşleşme</div><div class="v"><a href="#review">' . ($st['service']['review'] ?? 0) . '</a></div></div>'
            . '<div><div class="k">' . Ui::dot('ok') . 'Kategori çifti</div><div class="v">' . ($st['category']['ok'] ?? 0) . '</div></div>'
            . '</section>';

        // Kaynaklar
        $h .= '<h2 class="first">Kaynaklar</h2><p class="muted">Kendi panelinizin ve sağlayıcıların API bilgileri. Anahtarlar yalnızca sunucudaki veritabanında saklanır, burada gizli gösterilir.</p>';
        if ($sources) {
            $h .= '<div class="table-wrap"><table><thead><tr><th>Kaynak</th><th>API adresi</th><th>Anahtar</th><th class="num">Servis</th><th>Son senkron</th><th>Yeni servis izleme</th><th></th></tr></thead><tbody>';
            foreach ($sources as $s) {
                $h .= '<tr><td><b>' . Ui::e($s['name']) . '</b><div class="src">' . ($s['kind'] === 'own' ? 'Kendi panelim (Azerice adlar)' : 'Sağlayıcı (İngilizce adlar)') . '</div>'
                    . ($s['last_error'] ? '<div class="src lvl-error">' . Ui::e($s['last_error']) . '</div>' : '') . '</td>'
                    . '<td class="src">' . Ui::e($s['api_url']) . '</td><td class="tight muted">' . self::mask($s['api_key']) . '</td>'
                    . '<td class="num tight">' . (int)$s['services'] . '</td><td class="tight">' . Ui::ago($s['last_sync_at'] ? (int)$s['last_sync_at'] : null) . '</td>'
                    . '<td>' . ($s['kind'] === 'provider'
                        ? $this->ui->action('source_watch', (int)$s['watch'] ? 'Açık: kapat' : 'Kapalı: aç', ['id' => $s['id']], (int)$s['watch'] ? 'primary sm' : 'quiet sm', 'bell')
                            . ((int)$s['watch'] ? '<div class="src">' . (int)($this->db->one('SELECT COUNT(*) c FROM api_seen WHERE source_id = ?', [$s['id']])['c'] ?? 0) . ' ID kayıtlı · <a href="?p=seen_txt&amp;id=' . (int)$s['id'] . '">.txt indir</a></div>' : '')
                            . ((int)$s['watch'] && (string)$s['watch_note'] !== '' ? '<div class="src">' . Ui::e((string)$s['watch_note']) . ($s['watch_at'] ? ' · ' . Ui::ago((int)$s['watch_at']) : '') . '</div>' : '')
                        : '<span class="muted">—</span>') . '</td>'
                    . '<td class="tight"><div class="row">' . $this->ui->action('source_sync', 'Senkronize et', ['id' => $s['id']], 'quiet sm', 'refresh')
                    . $this->ui->action('source_del', 'Sil', ['id' => $s['id']], 'ghost sm', 'trash', $s['name'] . ' ve servis listesi silinsin mi? Bankadaki çiftler kalır.') . '</div></td></tr>';
            }
            $h .= '</tbody></table></div>';
        }
        $h .= '<form method="post" action="?p=source_add" class="panel panel-pad field-row gap-top" autocomplete="off">' . $this->ui->csrfField()
            . '<label>Tür<select name="kind"><option value="own"' . ($hasOwn ? '' : ' selected') . '>Kendi panelim</option><option value="provider"' . ($hasOwn ? ' selected' : '') . '>Sağlayıcı</option></select></label>'
            . '<label class="grow">Ad<input type="text" name="name" placeholder="' . ($hasOwn ? 'Sağlayıcı adı' : 'panelbaku') . '" required></label>'
            . '<label class="grow">API adresi<input type="text" name="api_url" placeholder="https://site.com/api/v2" required></label>'
            . '<label class="grow">API anahtarı<input type="password" name="api_key" required autocomplete="new-password"></label>'
            . '<button class="btn primary">' . Ui::icon('plus') . '<span>Ekle ve senkronize et</span></button></form>';

        // Eşleştirme
        $rep = $this->db->getJson('match_report');
        $h .= '<h2>Otomatik eşleştirme</h2><div class="panel panel-pad stack">'
            . '<p class="muted">Kendi panelinizdeki Azerice servisleri sağlayıcıların İngilizce servisleriyle eşleştirir: aynı min/max, aynı platform, addaki sayılar, iade/iptal özellikleri ve fiyat. Fiyat formülünüz (kâr yüzdesi, sabit kâr, kur) kesin eşleşmelerden otomatik hesaplanır. Elle onayladığınız ya da reddettiğiniz eşleşmeler korunur.</p>';
        if (is_array($rep)) {
            $m = $rep['model'];
            $formula = match ($m['mode']) {
                'linear' => 'satış ≈ ' . $m['a'] . ' × sağlayıcı ' . ($m['b'] >= 0 ? '+ ' : '− ') . abs($m['b']),
                'ratio' => 'satış ≈ ' . round($m['a'], 4) . ' × sağlayıcı',
                default => 'hesaplanamadı (fiyat kullanılmadı)',
            };
            $h .= '<dl class="kv"><dt>Kendi servisleriniz</dt><dd>' . $rep['own'] . '</dd>'
                . '<dt>Kesin eşleşen (bankaya girdi)</dt><dd>' . $rep['high'] . '</dd>'
                . '<dt>Kontrol bekleyen</dt><dd>' . $rep['review'] . '</dd>'
                . '<dt>Eşleşmeyen (tarz örneği olarak kullanılır)</dt><dd>' . $rep['none'] . '</dd>'
                . '<dt>Kategori çifti</dt><dd>' . ($rep['categories'] ?? 0) . '</dd>'
                . '<dt>Bulunan fiyat formülü</dt><dd>' . Ui::e($formula) . ' <span class="muted">(' . $m['points'] . ' kesin eşleşmeden)</span></dd>'
                . '<dt>Son çalışma</dt><dd>' . Ui::ago((int)$rep['at']) . '</dd></dl>';
        }
        $h .= '<div class="row">' . $this->ui->action('match_run', 'Eşleştirmeyi çalıştır', [], 'primary', 'play') . '</div></div>';

        // Kontrol bekleyenler
        $review = $this->db->all("SELECT * FROM tm WHERE status = 'review' ORDER BY score DESC LIMIT 30");
        $h .= '<h2 id="review">Kontrol bekleyen eşleşmeler</h2>';
        if ($review) {
            $h .= '<p class="muted">Emin olunamayan eşleşmeler. Doğruysa bankaya girer, yanlışsa bir daha önerilmez.</p><div class="table-wrap"><table><thead><tr><th>Sağlayıcı adı</th><th>Sizin adınız</th><th class="num">Skor</th><th></th></tr></thead><tbody>';
            foreach ($review as $r) {
                $h .= '<tr><td>' . Ui::e($r['en']) . '</td><td><b>' . Ui::e($r['az']) . '</b></td><td class="num tight">' . round((float)$r['score'] * 100) . '%</td>'
                    . '<td class="tight"><div class="row">' . $this->ui->action('tm_confirm', 'Doğru', ['id' => $r['id']], 'quiet sm', 'check')
                    . $this->ui->action('tm_reject', 'Yanlış', ['id' => $r['id']], 'ghost sm', 'x') . '</div></td></tr>';
            }
            $h .= '</tbody></table></div>';
        } else {
            $h .= '<p class="muted">Kontrol bekleyen eşleşme yok.</p>';
        }

        // Banka arama
        $q = trim((string)($_GET['q'] ?? ''));
        $kind = (string)($_GET['kind'] ?? 'service');
        $kind = in_array($kind, ['service', 'category'], true) ? $kind : 'service';
        $page = max(1, (int)($_GET['page'] ?? 1));
        $args = [$kind];
        $where = "kind = ? AND status = 'ok'";
        if ($q !== '') {
            $where .= ' AND (en LIKE ? OR az LIKE ?)';
            $args[] = "%$q%";
            $args[] = "%$q%";
        }
        $total = (int)$this->db->one("SELECT COUNT(*) c FROM tm WHERE $where", $args)['c'];
        $rows = $this->db->all("SELECT * FROM tm WHERE $where ORDER BY updated_at DESC LIMIT 50 OFFSET " . (($page - 1) * 50), $args);
        $h .= '<h2>Bankadaki çiftler</h2>'
            . '<form method="get" class="toolbar"><input type="hidden" name="p" value="bank">'
            . '<label class="grow">Ara<input type="search" name="q" value="' . Ui::e($q) . '" placeholder="Followers, İzləyici…"></label>'
            . '<label>Tür<select name="kind"><option value="service"' . ($kind === 'service' ? ' selected' : '') . '>Servis adları</option><option value="category"' . ($kind === 'category' ? ' selected' : '') . '>Kategoriler</option></select></label>'
            . '<button class="btn quiet">' . Ui::icon('search') . '<span>Ara</span></button></form>';
        if ($rows) {
            $origin = ['manual' => 'Elle', 'approved' => 'Onaylanan iş', 'confirmed' => 'Onaylanan eşleşme', 'import' => 'Otomatik eşleşme'];
            $h .= '<div class="table-wrap"><table><thead><tr><th>Kaynak ad</th><th>Azerice</th><th>Geldiği yer</th><th></th></tr></thead><tbody>';
            foreach ($rows as $r) {
                $h .= '<tr><td>' . Ui::e($r['en']) . '</td><td><form method="post" action="?p=tm_save" class="field-row">' . $this->ui->csrfField()
                    . '<input type="hidden" name="id" value="' . (int)$r['id'] . '"><input type="text" name="az" value="' . Ui::e($r['az']) . '" class="grow" aria-label="Azerice">'
                    . '<button class="btn ghost sm">Kaydet</button></form></td><td class="tight muted">' . ($origin[$r['origin']] ?? Ui::e($r['origin'])) . '</td>'
                    . '<td class="tight">' . $this->ui->action('tm_del', 'Sil', ['id' => $r['id']], 'ghost sm', 'trash', 'Bu çift bankadan silinsin mi?') . '</td></tr>';
            }
            $h .= '</tbody></table></div>';
            $pages = max(1, (int)ceil($total / 50));
            if ($pages > 1) {
                $base = ['p' => 'bank', 'q' => $q, 'kind' => $kind];
                $h .= '<nav class="pager">' . ($page > 1 ? Ui::link('?' . http_build_query($base + ['page' => $page - 1]), 'Önceki', 'quiet sm') : '')
                    . '<span>Sayfa ' . $page . ' / ' . $pages . ', toplam ' . $total . '</span>'
                    . ($page < $pages ? Ui::link('?' . http_build_query($base + ['page' => $page + 1]), 'Sonraki', 'quiet sm') : '') . '</nav>';
            }
        } else {
            $h .= Ui::empty($q !== '' ? 'Aramayla eşleşen çift yok' : 'Banka henüz boş',
                $q !== '' ? 'Başka bir kelime deneyin.' : 'Kaynakları ekleyip eşleştirmeyi çalıştırın. Onayladığınız her iş de bankaya otomatik eklenir.');
        }

        $h .= '<h2>Elle ekle</h2><form method="post" action="?p=tm_add" class="panel panel-pad field-row">' . $this->ui->csrfField()
            . '<label>Tür<select name="kind"><option value="service">Servis adı</option><option value="category">Kategori</option></select></label>'
            . '<label class="grow">Kaynak (İngilizce)<input type="text" name="en" required placeholder="Threads Followers | No Drop"></label>'
            . '<label class="grow">Azerice<input type="text" name="az" required placeholder="Threads İzləyici | Azalma olmaz"></label>'
            . '<button class="btn quiet">' . Ui::icon('plus') . '<span>Ekle</span></button></form>';

        $this->render('Çeviri bankası', $h, ['active' => 'bank', 'sub' => 'Önceki çevirileriniz. Yeni adlar önce burada aranır, bulunamayan parçalar kurallarla ya da Claude ile çevrilir.']);
    }

    // ---------------------------------------------------------------- Çeviri ayarları

    private function aiSettings(): void
    {
        $ai = new Ai($this->db, $this->config);
        $dict = Dictionary::load($this->db);
        $u = $ai->usage();
        $limit = $ai->monthlyLimit();

        $modeOpts = '';
        foreach (Ai::MODES as $k => $v) {
            $modeOpts .= '<label class="check"><input type="radio" name="mode" value="' . $k . '"' . ($ai->mode() === $k ? ' checked' : '') . '> ' . Ui::e($v) . '</label>';
        }
        $modelOpts = '';
        foreach (Ai::MODELS as $k => [$name, $in, $out]) {
            $modelOpts .= '<option value="' . $k . '"' . ($ai->model() === $k ? ' selected' : '') . '>' . Ui::e($name) . ' — $' . $in . ' / $' . $out . ' (1M token giriş / çıkış)</option>';
        }
        $effOpts = '';
        foreach (Ai::EFFORTS as $k => $v) {
            $effOpts .= '<option value="' . $k . '"' . ($ai->effort() === $k ? ' selected' : '') . '>' . Ui::e($v) . '</option>';
        }
        $round = max(0, min(4, (int)$this->db->get('markup_round', '2')));
        $keyFromConfig = trim((string)($this->config['claude_api_key'] ?? '')) !== '' && trim((string)$this->db->get('claude_api_key', '')) === '';

        $h = '<form method="post" action="?p=ai_save" class="stack" autocomplete="off">' . $this->ui->csrfField()
            . '<h2 class="first">Çeviri yöntemi</h2><div class="panel panel-pad stack">' . $modeOpts
            . ($ai->mode() !== 'bank' && !$ai->configured() ? '<div class="flash bad">' . Ui::icon('alert') . '<span>Claude API anahtarı girilmediği için şu an banka ve kurallar kullanılıyor.</span></div>' : '')
            . '<p class="muted">Her durumda iş sayfasındaki <b>claude.ai için hazırla</b> ile Pro aboneliğinizi kullanarak elle de çevirebilirsiniz (API gerekmez).</p></div>'

            . '<h2>Claude API</h2><div class="panel panel-pad stack">'
            . '<p class="muted">API, claude.ai Pro aboneliğinden ayrıdır: console.anthropic.com üzerinden hesap açılır ve kredi yüklenir. Kullandıkça ücretlendirilir.</p>'
            . '<div class="field-row"><label class="grow">API anahtarı ' . ($ai->configured() ? '<span class="hint">(kayıtlı: ' . self::mask($ai->apiKey()) . ($keyFromConfig ? ', config.php' : '') . ')</span>' : '') . '<input type="password" name="api_key" placeholder="' . ($ai->configured() ? 'Değiştirmek için yeni anahtarı yazın' : 'sk-ant-…') . '" autocomplete="new-password"></label>'
            . ($ai->configured() && !$keyFromConfig ? '<label class="check"><input type="checkbox" name="api_key_clear" value="1"> Anahtarı sil</label>' : '') . '</div>'
            . '<div class="field-row"><label class="grow">Model<select name="model">' . $modelOpts . '</select></label>'
            . '<label>Düşünme düzeyi (effort)<select name="effort">' . $effOpts . '</select></label>'
            . '<label>Aylık istek sınırı<input type="number" name="limit" min="0" value="' . $limit . '" class="narrow"></label></div>'
            . '<p class="muted">Bu ay: ' . (int)$u['requests'] . ' istek' . ($limit ? ' / ' . $limit : '') . ', ' . number_format((int)$u['input']) . ' giriş + ' . number_format((int)$u['output']) . ' çıkış token, tahmini $' . number_format((float)$u['cost'], 4) . '. Fiyatlar yaklaşık; kesin tutar Anthropic konsolundadır. Sınır 0 ise sınırsız.</p>'
            . '</div>'

            . '<h2>Fiyat önerisi</h2><div class="panel panel-pad stack"><p class="muted">Yeni servis için önerilen satış fiyatı: <b>satış = sağlayıcı fiyatı × çarpan + sabit</b>. Boş bırakırsanız formül, kendi servisleriniz sağlayıcı servisleriyle eşleştirilirken otomatik bulunur'
            . (($f = (new \TgWatch\Enrich($this->db))->formula()) ? ' (şu an: ' . Ui::e($f) . ')' : ' (henüz bulunamadı)') . '. Sağlayıcı ile paneliniz farklı para birimi kullanıyorsa çarpan kuru da içerir.</p>'
            . '<div class="field-row"><label>Çarpan<input type="text" name="markup_a" value="' . Ui::e((string)$this->db->get('markup_a', '')) . '" placeholder="otomatik" class="narrow" inputmode="decimal"></label>'
            . '<label>Sabit<input type="text" name="markup_b" value="' . Ui::e((string)$this->db->get('markup_b', '')) . '" placeholder="0" class="narrow" inputmode="decimal"></label>'
            . '<label>Ondalık<select name="markup_round">' . implode('', array_map(static fn($n) => '<option' . ($n === $round ? ' selected' : '') . '>' . $n . '</option>', [0, 1, 2, 3, 4])) . '</select></label></div></div>'

            . '<h2>Kurallarınız</h2><div class="panel panel-pad stack"><p class="muted">Claude’a her istekte bu metin, sözlük ve bankadan benzer örneklerle birlikte gönderilir. Kendi cümlelerinizle yazabilirsiniz.</p>'
            . '<textarea name="style_rules" rows="12">' . Ui::e($ai->styleRules()) . '</textarea></div>'

            . '<h2>Silinecek ifadeler</h2><div class="panel panel-pad stack"><p class="muted">Çeviriden önce sağlayıcı adından çıkarılır (reklam etiketleri vb.). Her satıra bir ifade. Düz metin büyük/küçük harf duyarsızdır, <code>/…/</code> biçimi regex olarak işlenir. Örnek: <code>🔥 BEST</code>, <code>[RECOMMENDED]</code>, <code>/\[ ?OWNER ?\]/i</code></p>'
            . '<textarea name="remove" rows="5">' . Ui::e(implode("\n", $dict['remove'] ?? [])) . '</textarea></div>'

            . '<div class="actionbar"><button class="btn primary">' . Ui::icon('check') . '<span>Kaydet</span></button><span class="spacer"></span>'
            . ($ai->configured() ? '<button class="btn quiet" name="test" value="1">' . Ui::icon('spark') . '<span>Kaydet ve Claude’u dene</span></button>' : '') . '</div></form>';

        if (!empty($_SESSION['ai_test'])) {
            $h .= '<h2>Deneme sonucu</h2><div class="code"><pre>' . Ui::e($_SESSION['ai_test']) . '</pre></div>';
            unset($_SESSION['ai_test']);
        }
        $this->render('Çeviri ayarları', $h, ['active' => 'ai']);
    }

    // ---------------------------------------------------------------- claude.ai elle çeviri

    private function jobPrompt(int $id): void
    {
        $job = $this->db->job($id);
        if ($job === null) {
            $this->render('İş bulunamadı', Ui::empty('Bu numarada iş yok', '', Ui::link('?p=jobs', 'İşlere dön', 'primary')), ['active' => 'jobs']);
            return;
        }
        $dict = Dictionary::load($this->db);
        $prompt = (new Ai($this->db, $this->config))->manualPrompt($job['items'], new Engine($this->db, $dict), $dict);
        $h = '<ol class="stack steps"><li>Metni kopyalayın ve claude.ai’de yeni bir sohbete yapıştırın.</li><li>Claude’un cevabını (her satır <code>ID | ad</code>) aşağıya yapıştırıp uygulayın.</li>'
            . '<li>Her ad kontrol edilir: sayılar, emojiler, parça sayısı ve zorunlu sözlük terimleri değişmişse o ad uygulanmaz ve nedeni gösterilir.</li></ol>'
            . '<div class="panel panel-pad stack gap-top"><div class="row"><b>claude.ai’ye yapıştırılacak metin</b>' . Ui::copy('prompt-text', 'Metni kopyala') . '</div>'
            . '<textarea id="prompt-text" rows="14" readonly>' . Ui::e($prompt) . '</textarea></div>'
            . '<form method="post" action="?p=job_paste" class="panel panel-pad stack gap-top">' . $this->ui->csrfField() . '<input type="hidden" name="id" value="' . $id . '">'
            . '<label>Claude’un cevabı<textarea name="answer" rows="8" placeholder="13125 | Threads İzləyici | Azalma olmaz | …" required></textarea></label>'
            . '<div class="row"><button class="btn primary">' . Ui::icon('check') . '<span>Cevabı uygula</span></button>' . Ui::link('?p=job&id=' . $id, 'İşe dön', 'ghost') . '</div></form>';
        $this->render('İş ' . $id . ': claude.ai ile çevir', $h, ['active' => 'jobs']);
    }

    // ---------------------------------------------------------------- POST

    /** @return bool işlendi mi */
    private function postBank(string $p): bool
    {
        $id = (int)($_POST['id'] ?? 0);
        switch ($p) {
            case 'source_add':
                $kind = ($_POST['kind'] ?? '') === 'own' ? 'own' : 'provider';
                $name = trim((string)($_POST['name'] ?? ''));
                $url = trim((string)($_POST['api_url'] ?? ''));
                $key = trim((string)($_POST['api_key'] ?? ''));
                if ($name === '' || $key === '' || !preg_match('~^https://~i', $url)) {
                    throw new \InvalidArgumentException('Ad, https:// ile başlayan API adresi ve anahtar gerekli.');
                }
                if ($kind === 'own' && $this->db->one("SELECT id FROM sources WHERE kind = 'own'")) {
                    throw new \InvalidArgumentException('Kendi paneliniz zaten ekli. Değiştirmek için önce silin.');
                }
                $this->db->run('INSERT INTO sources(kind, name, api_url, api_key, created_at) VALUES(?,?,?,?,?)', [$kind, $name, $url, $key, time()]);
                $sid = (int)$this->db->pdo->lastInsertId();
                $this->db->log('info', 'bank', "Kaynak eklendi: $name");
                try {
                    $n = SmmApi::sync($this->db, $sid);
                    $this->say('ok', "$name eklendi, $n servis alındı.");
                } catch (\Throwable $e) {
                    $this->say('bad', "$name eklendi ama senkronize edilemedi: " . $e->getMessage());
                }
                $this->redirect('bank');
                return true;
            case 'source_watch':
                $src = $this->db->one("SELECT * FROM sources WHERE id = ? AND kind = 'provider'", [$id]);
                if (!$src) {
                    throw new \InvalidArgumentException('Sağlayıcı bulunamadı.');
                }
                $on = (int)$src['watch'] ? 0 : 1;
                $this->db->run('UPDATE sources SET watch = ?, watch_at = NULL, watch_note = ? WHERE id = ?', [$on, $on ? 'Sıradaki cron çalışmasında başlar.' : '', $id]);
                if ($on) {
                    $r = (new \TgWatch\ApiWatch($this->db, \TgWatch\App::flow(), $this->config))->check($src);
                    $this->say($r['ok'] ? 'ok' : 'bad', $r['ok'] ? $src['name'] . ': izleme açıldı, ' . ($r['baseline'] ? 'mevcut servisler kaydedildi. Bundan sonra eklenenler bota gelir.' : $r['new'] . ' yeni servis bulundu.') : $src['name'] . ': izleme açıldı ama API okunamadı: ' . $r['error']);
                } else {
                    $this->say('ok', $src['name'] . ': izleme kapatıldı.');
                }
                $this->redirect(($_POST['back'] ?? '') === 'features' ? 'settings' : 'bank', ($_POST['back'] ?? '') === 'features' ? ['tab' => 'features'] : []);
                return true;
            case 'watch_now':
                $reps = (new \TgWatch\ApiWatch($this->db, \TgWatch\App::flow(), $this->config))->run(0, true);
                $new = array_sum(array_column($reps, 'new'));
                $bad = array_filter($reps, static fn($r) => !$r['ok']);
                $this->say($bad ? 'bad' : 'ok', $bad ? 'Bazı sağlayıcılar okunamadı: ' . implode('; ', array_map(static fn($r) => $r['name'] . ': ' . $r['error'], $bad)) : ($new ? "$new yeni servis bulundu, bota gönderildi." : 'Yeni servis yok.'));
                $this->redirect('settings', ['tab' => 'features']);
                return true;
            case 'source_sync':
                $n = SmmApi::sync($this->db, $id);
                $this->say('ok', "$n servis alındı. Bankayı güncellemek için eşleştirmeyi çalıştırın.");
                $this->redirect('bank');
                return true;
            case 'source_del':
                $this->db->run('DELETE FROM remote_services WHERE source_id = ?', [$id]);
                $this->db->run('DELETE FROM sources WHERE id = ?', [$id]);
                $this->say('ok', 'Kaynak silindi.');
                $this->redirect('bank');
                return true;
            case 'match_run':
                @set_time_limit(120);
                $r = (new Matcher($this->db))->run();
                $n = $this->refreshOpenJobs();
                $this->say('ok', "Eşleştirme bitti: {$r['high']} kesin, {$r['review']} kontrol bekliyor, {$r['none']} eşleşmedi." . ($n ? " $n açık iş güncellendi." : ''));
                $this->redirect('bank');
                return true;
            case 'tm_confirm':
                $this->db->run("UPDATE tm SET status = 'ok', origin = 'confirmed', updated_at = ? WHERE id = ?", [time(), $id]);
                $r = $this->db->one('SELECT en, az FROM tm WHERE id = ?', [$id]);
                if ($r) {
                    (new Memory($this->db))->learnSegments($r['en'], $r['az'], 2.0);
                }
                $this->say('ok', 'Eşleşme bankaya eklendi.');
                $this->redirect('bank', ['_' => 'review']);
                return true;
            case 'tm_reject':
                $this->db->run("UPDATE tm SET status = 'rejected', updated_at = ? WHERE id = ?", [time(), $id]);
                $this->say('ok', 'Eşleşme reddedildi, bir daha önerilmeyecek.');
                $this->redirect('bank');
                return true;
            case 'tm_save':
                $az = trim((string)($_POST['az'] ?? ''));
                if ($az === '') {
                    throw new \InvalidArgumentException('Azerice ad boş olamaz.');
                }
                $this->db->run("UPDATE OR IGNORE tm SET az = ?, origin = 'manual', updated_at = ? WHERE id = ?", [$az, time(), $id]);
                (new Memory($this->db))->rebuildSegments();
                $n = $this->refreshOpenJobs();
                $this->say('ok', 'Kaydedildi.' . ($n ? " $n açık iş güncellendi." : ''));
                $this->redirect('bank', array_filter(['q' => $_GET['q'] ?? null]));
                return true;
            case 'tm_del':
                $this->db->run('DELETE FROM tm WHERE id = ?', [$id]);
                (new Memory($this->db))->rebuildSegments();
                $this->say('ok', 'Silindi.');
                $this->redirect('bank');
                return true;
            case 'tm_add':
                $kind = ($_POST['kind'] ?? '') === 'category' ? 'category' : 'service';
                $en = trim((string)($_POST['en'] ?? ''));
                $az = trim((string)($_POST['az'] ?? ''));
                if ($en === '' || $az === '') {
                    throw new \InvalidArgumentException('İki alan da dolu olmalı.');
                }
                (new Memory($this->db))->learn($kind, $en, $az, 'manual');
                $n = $this->refreshOpenJobs();
                $this->say('ok', 'Bankaya eklendi.' . ($n ? " $n açık iş güncellendi." : ''));
                $this->redirect('bank');
                return true;

            case 'ai_save':
                $mode = (string)($_POST['mode'] ?? 'bank');
                $this->db->set('translate_mode', isset(Ai::MODES[$mode]) ? $mode : 'bank');
                $model = (string)($_POST['model'] ?? '');
                $this->db->set('claude_model', isset(Ai::MODELS[$model]) ? $model : 'claude-opus-5-5');
                $eff = (string)($_POST['effort'] ?? '');
                $this->db->set('claude_effort', isset(Ai::EFFORTS[$eff]) ? $eff : '');
                $this->db->set('claude_monthly_limit', (string)max(0, (int)($_POST['limit'] ?? 300)));
                foreach (['markup_a', 'markup_b'] as $k) {
                    $v = trim(str_replace(',', '.', (string)($_POST[$k] ?? '')));
                    if ($v !== '' && !is_numeric($v)) {
                        throw new \InvalidArgumentException('Çarpan ve sabit sayı olmalı (örn. 1.7 ve 0.1).');
                    }
                    $this->db->set($k, $v);
                }
                $this->db->set('markup_round', (string)max(0, min(4, (int)($_POST['markup_round'] ?? 2))));
                $key = trim((string)($_POST['api_key'] ?? ''));
                if (!empty($_POST['api_key_clear'])) {
                    $this->db->set('claude_api_key', '');
                } elseif ($key !== '') {
                    if (!str_starts_with($key, 'sk-ant-')) {
                        throw new \InvalidArgumentException('Bu bir Claude API anahtarına benzemiyor (sk-ant- ile başlamalı).');
                    }
                    $this->db->set('claude_api_key', $key);
                }
                $rules = trim((string)($_POST['style_rules'] ?? ''));
                $this->db->set('style_rules', $rules === trim(Ai::DEFAULT_RULES) ? '' : $rules);
                $d = Dictionary::load($this->db);
                $d['remove'] = array_values(array_filter(array_map('trim', preg_split('/\R/u', (string)($_POST['remove'] ?? '')) ?: []), static fn($x) => $x !== ''));
                Dictionary::save($this->db, $d);
                $n = $this->refreshOpenJobs();
                $this->db->log('info', 'web', 'Çeviri ayarları kaydedildi');
                if (!empty($_POST['test'])) {
                    $this->aiTest();
                }
                $this->say('ok', 'Çeviri ayarları kaydedildi.' . ($n ? " $n açık iş güncellendi." : ''));
                $this->redirect('ai');
                return true;

            case 'job_paste':
                $r = App::flow()->applyPaste($id, (string)($_POST['answer'] ?? ''));
                $msg = "{$r['applied']} ad uygulandı." . ($r['missing'] ? " {$r['missing']} servis cevapta yoktu." : '');
                if ($r['rejected']) {
                    $msg .= ' Kontrolden geçmeyen: ' . implode(' | ', $r['rejected']);
                }
                $this->say($r['rejected'] ? 'bad' : 'ok', $msg);
                $this->redirect('job', ['id' => $id]);
                return true;
        }
        return false;
    }

    private function aiTest(): void
    {
        $ai = new Ai($this->db, $this->config);
        $dict = Dictionary::load($this->db);
        $engine = new Engine($this->db, $dict);
        $items = $engine->translateItems([
            ['id' => 101, 'name' => 'Instagram Followers | Arab Targeted | No Drop | Max 50K | 5K/Day | 30 Days Refill ♻️', 'price' => '', 'category' => ''],
            ['id' => 102, 'name' => 'TikTok Custom Comments | Instant Start | Lifetime Refill', 'price' => '', 'category' => ''],
        ]);
        try {
            $t0 = microtime(true);
            $res = $ai->call($ai->system($dict), $ai->userMessage($items, $engine));
            $r = Ai::apply($items, $res['map'], $dict);
            $out = 'Model: ' . $res['model'] . ', ' . round(microtime(true) - $t0, 1) . " sn, {$res['usage']['input']} giriş + {$res['usage']['output']} çıkış token\n\n";
            foreach ($r['items'] as $it) {
                $out .= $it['id'] . '  ' . $it['name'] . "\n   taslak: " . ($items[array_search($it['id'], array_column($items, 'id'), true)]['az'] ?? '') . "\n   Claude: " . ($res['map'][$it['id']] ?? '(yok)') . "\n"
                    . (!empty($it['ai_note']) ? '   ' . $it['ai_note'] . "\n" : "   kontrolden geçti\n");
            }
            $_SESSION['ai_test'] = $out;
        } catch (\Throwable $e) {
            $_SESSION['ai_test'] = 'Hata: ' . $e->getMessage();
        }
    }
}
