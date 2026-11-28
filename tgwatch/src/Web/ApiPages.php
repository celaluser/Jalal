<?php
declare(strict_types=1);

namespace TgWatch\Web;

use TgWatch\ApiStats;
use TgWatch\ExtApi;
use TgWatch\Settings;

/** API sekmesi: eklenti API'sinin bağlantı bilgileri, anahtarı, istatistikleri, son istekleri ve işlem belgesi. */
trait ApiPages
{
    private function apiPage(): void
    {
        $days = in_array((int)($_GET['days'] ?? 14), [7, 14, 30, 90], true) ? (int)($_GET['days'] ?? 14) : 14;
        $st = ApiStats::summary($this->db, $days);
        $key = (string)($this->config['ext_key'] ?? '');
        $url = $this->siblingUrl('api.php');
        $serverExt = ExtApi::extVersion();
        $last = $st['last'];

        // --- bağlantı
        $keyHtml = $key === ''
            ? '<span class="lvl-warn">Henüz anahtar yok.</span> Aşağıdaki “Yeni anahtar üret”e bas.'
            : ($this->loginOff() ? '•••••••• <span class="muted">(giriş kapalıyken gösterilmez)</span>'
                : '<code id="api-key">' . Ui::e($key) . '</code>' . Ui::copy('api-key') . ' <span class="muted">Paylaşma.</span>');
        $state = !$last ? Ui::dot('warn') . ' Henüz istek gelmedi'
            : (time() - (int)$last['at'] < 600 ? Ui::dot('ok') . ' Bağlı · son istek ' . Ui::ago((int)$last['at']) : Ui::dot('warn') . ' Son istek ' . Ui::ago((int)$last['at']));
        $conn = '<section class="card c-12"><div class="card-h"><h3>Bağlantı</h3><span class="src">' . $state . '</span></div>'
            . '<dl class="kv"><dt>API adresi</dt><dd><code id="api-url">' . Ui::e($url) . '</code>' . Ui::copy('api-url') . '</dd>'
            . '<dt>Anahtar</dt><dd>' . $keyHtml . '</dd>'
            . '<dt>Yöntem</dt><dd><code>POST</code>, JSON gövde, anahtar <code>X-Tgwatch-Key</code> başlığında</dd>'
            . '<dt>Eklenti sürümü</dt><dd>' . ($st['ext'] !== '' ? 'Bağlanan: <b>' . Ui::e($st['ext']) . '</b>' : 'Bağlanan: —') . ' · Sunucudaki: <b>' . Ui::e($serverExt ?: '—') . '</b>'
            . ($st['ext'] !== '' && $serverExt !== '' && version_compare($serverExt, $st['ext'], '>') ? ' <span class="lvl-warn">· eklentide “Güncelleme” var</span>' : '') . '</dd></dl>'
            . '<ol class="muted"><li>Eklenti simgesi → Ayarlar (bağlantı ayarları): panel adresi, yukarıdaki <b>API adresi</b> ve <b>anahtar</b> → Kaydet ve dene.</li>'
            . '<li>Mini panelin üstünde “Bağlı · ' . Ui::e(\TgWatch\VERSION) . '” görünmeli; buradaki “son istek” de güncellenir.</li></ol>'
            . '<div class="row">' . $this->ui->action('api_key_new', $key === '' ? 'Anahtar üret' : 'Yeni anahtar üret', [], $key === '' ? 'primary' : 'quiet', 'refresh', $key === '' ? null : 'Eski anahtar hemen geçersiz olur; eklentinin ayarlarına yenisini girmen gerekir. Devam edilsin mi?')
            . Ui::link('?p=ext_zip', 'Eklentiyi indir (.zip)', 'quiet', 'download') . '</div></section>';

        // --- istatistik kartları
        $t = $st['total'];
        $avg = $t['req'] ? round($t['ms'] / $t['req']) : 0;
        $errPct = $t['req'] ? round(100 * $t['err'] / $t['req'], 1) : 0;
        $tile = static fn(string $label, string $v, string $foot = '') => '<section class="card"><div class="tile-label">' . $label . '</div><div class="tile-value">' . $v . '</div><div class="tile-foot">' . $foot . '</div></section>';
        $chips = '';
        foreach ([7 => '7 gün', 14 => '14 gün', 30 => '30 gün', 90 => '90 gün'] as $d => $l) {
            $chips .= '<a class="chip" href="?p=api&amp;days=' . $d . '"' . ($days === $d ? ' aria-current="true"' : '') . '>' . $l . '</a>';
        }
        $h = '<div class="dash">' . $conn . '</div>'
            . '<div class="row between gap-top"><h2 class="first">İstatistikler</h2><div class="chips">' . $chips . '</div></div>'
            . '<div class="dash">'
            . '<div class="c-3">' . $tile('Bugün', (string)$st['today']['req'], $st['today']['err'] ? $st['today']['err'] . ' hata' : 'hatasız') . '</div>'
            . '<div class="c-3">' . $tile('Son ' . $days . ' gün', (string)$t['req'], 'istek') . '</div>'
            . '<div class="c-3">' . $tile('Hata oranı', '%' . $errPct, $t['err'] . ' hatalı istek') . '</div>'
            . '<div class="c-3">' . $tile('Ortalama süre', $avg . ' ms', 'sunucuda işlem') . '</div>'
            . '<section class="card c-12"><div class="card-h"><h3>Günlük istek</h3></div><div class="chart-scroll">'
            . Charts::stackedColumns($st['days'], ['Başarılı', 'Hatalı'], 'istek', 'Son ' . $days . ' günde günlük API isteği') . '</div></section></div>';

        // --- işlem başına
        if ($st['actions']) {
            $h .= '<h2>İşlemler</h2><div class="table-wrap"><table><thead><tr><th>İşlem</th><th class="num">İstek</th><th class="num">Hata</th><th class="num">Ort. süre</th><th>Son</th></tr></thead><tbody>';
            foreach ($st['actions'] as $a) {
                $h .= '<tr><td><code>' . Ui::e($a['action']) . '</code></td><td class="num">' . $a['req'] . '</td><td class="num">' . ($a['err'] ? '<span class="lvl-error">' . $a['err'] . '</span>' : '0') . '</td><td class="num">'
                    . ($a['req'] ? round($a['ms'] / $a['req']) : 0) . ' ms</td><td>' . ($a['last'] ? Ui::ago((int)$a['last']) : '—') . '</td></tr>';
            }
            $h .= '</tbody></table></div>';
        } else {
            $h .= Ui::empty('Bu dönemde istek yok', 'Eklenti tgwatch\'a bağlanıp istek attıkça işlem başına sayılar burada görünür.');
        }

        // --- son istekler
        if ($st['calls']) {
            $h .= '<h2>Son istekler</h2><div class="table-wrap"><table><thead><tr><th>Zaman</th><th>İşlem</th><th class="num">Kod</th><th class="num">Süre</th><th>IP</th><th>Eklenti</th><th>Hata</th></tr></thead><tbody>';
            foreach ($st['calls'] as $c) {
                $code = (int)$c['code'];
                $h .= '<tr><td>' . Ui::e(date('d.m H:i:s', (int)$c['at'])) . '</td><td><code>' . Ui::e($c['action']) . '</code></td><td class="num">'
                    . ($code >= 400 ? '<span class="lvl-error">' . $code . '</span>' : $code) . '</td><td class="num">' . Ui::e((string)$c['ms']) . ' ms</td><td>' . Ui::e($c['ip']) . '</td><td>' . Ui::e($c['ext'] ?: '—') . '</td><td class="muted">' . Ui::e($c['error']) . '</td></tr>';
            }
            $h .= '</tbody></table></div><div class="row gap-top">' . $this->ui->action('api_stats_reset', 'İstatistikleri sıfırla', [], 'ghost', 'trash', 'Bütün API istatistikleri ve son istek kaydı silinsin mi?') . '</div>';
        }

        // --- işlem belgesi
        $h .= '<h2>API işlemleri</h2><p class="muted">Her istek <code>POST ' . Ui::e($url) . '</code>, başlıkta <code>X-Tgwatch-Key</code>, gövdede <code>action</code>. Cevap JSON: <code>{"ok":true,…}</code> ya da <code>{"ok":false,"error":"…"}</code>.</p>'
            . '<div class="table-wrap"><table><thead><tr><th>İşlem</th><th>Ne yapar</th><th>Örnek gövde</th></tr></thead><tbody>';
        foreach (ExtApi::ACTIONS as $k => [$desc, $ex]) {
            $h .= '<tr><td><code>' . Ui::e($k) . '</code></td><td>' . Ui::e($desc) . '</td><td><code>' . Ui::e($ex) . '</code></td></tr>';
        }
        $h .= '</tbody></table></div>'
            . '<h3>Komut satırından deneme</h3><pre class="code" id="api-curl">curl -s -X POST ' . Ui::e($url) . " \\\n  -H 'Content-Type: application/json' -H 'X-Tgwatch-Key: ANAHTAR' \\\n  -d '{\"action\":\"ping\"}'</pre>" . Ui::copy('api-curl');
        $this->render('API', $h, ['active' => 'api', 'sub' => 'Chrome eklentisinin bağlandığı API: adres, anahtar, istatistikler ve işlemler.']);
    }

    private function postApi(string $p): bool
    {
        switch ($p) {
            case 'api_key_new':
                $errors = Settings::save($this->db, ['ext_key' => rtrim(strtr(base64_encode(random_bytes(32)), '+/', '-_'), '=')]);
                if ($errors) {
                    $this->say('bad', implode(' ', $errors));
                } else {
                    $this->db->log('info', 'web', 'Eklenti API anahtarı yenilendi');
                    $this->say('ok', 'Yeni anahtar üretildi. Eklentinin ayarlarına yeni anahtarı gir; eskisi artık çalışmaz.');
                }
                $this->redirect('api');
                return true;
            case 'api_stats_reset':
                ApiStats::reset($this->db);
                $this->say('ok', 'API istatistikleri sıfırlandı.');
                $this->redirect('api');
                return true;
        }
        return false;
    }
}
