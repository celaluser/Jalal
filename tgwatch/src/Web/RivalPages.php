<?php
declare(strict_types=1);

namespace TgWatch\Web;

use TgWatch\Rivals;

/** Rakipler bölümü: fiyat kıyası ve rakip listesi (Admin içinde kullanılır). */
trait RivalPages
{
    private function rivalsPage(): void
    {
        $R = new Rivals($this->db, $this->config);
        $filters = ['all' => 'Kıyaslananlar', 'pricier' => 'Rakipten pahalıyım', 'cheaper' => 'Rakipten ucuzum', 'review' => 'Kontrol bekleyen', 'none' => 'Eşleşmeyen'];
        $f = isset($filters[(string)($_GET['f'] ?? '')]) ? (string)$_GET['f'] : 'all';
        $q = trim((string)($_GET['q'] ?? ''));
        $page = max(1, (int)($_GET['page'] ?? 1));
        $c = $R->compare($f, $q, $page, 40);
        $sub = 'Kendi servislerinin fiyatını rakiplerle yan yana gösterir. Pahalı olduğun yer kırmızı, ucuz olduğun yer yeşil.';

        if (!$c['rivals']) {
            $this->render('Fiyat kıyası', Ui::empty('Henüz rakip eklenmemiş', 'Rakip panelin API adresini ve anahtarını Rakip listesi sayfasından ekle. Servisleri çekilir ve seninkilerle eşleştirilir.', Ui::link('?p=rival_list', 'Rakip ekle', 'primary', 'plus')), ['active' => 'rivals', 'sub' => $sub]);
            return;
        }
        $s = $c['summary'];
        $pct = (int)round($R->alertPct() * 100);
        $h = '<section class="strip" aria-label="Özet">'
            . '<div><div class="k">' . Ui::dot('ok') . 'Kıyaslanan servis</div><div class="v">' . $s['compared'] . '</div></div>'
            . '<div><div class="k">' . Ui::dot($s['pricier'] ? 'bad' : 'ok') . 'Rakipten pahalı (%' . $pct . '+)</div><div class="v"><a href="?p=rivals&amp;f=pricier">' . $s['pricier'] . '</a></div></div>'
            . '<div><div class="k">' . Ui::dot('ok') . 'Rakipten ucuz</div><div class="v"><a href="?p=rivals&amp;f=cheaper">' . $s['cheaper'] . '</a></div></div>'
            . '<div><div class="k">' . Ui::dot($s['review'] ? 'warn' : 'ok') . 'Kontrol bekleyen</div><div class="v"><a href="?p=rivals&amp;f=review">' . $s['review'] . '</a></div></div></section>';
        if ($s['compared']) {
            $avg = $s['avg'] * 100;
            $h .= '<p class="muted">En pahalı rakibe göre ortalama fark: <b>' . ($avg >= 0 ? '+' : '−') . number_format(abs($avg), 1, ',', '') . '%</b> (+ sen daha pahalısın).</p>';
        }
        $nav = '<nav class="tabs" aria-label="Süzgeç">';
        foreach ($filters as $k => $l) {
            $nav .= '<a href="?p=rivals&amp;f=' . $k . '"' . ($f === $k ? ' aria-current="page"' : '') . '>' . Ui::e($l) . '</a>';
        }
        $h .= $nav . '</nav>'
            . '<form method="get" class="toolbar"><input type="hidden" name="p" value="rivals"><input type="hidden" name="f" value="' . Ui::e($f) . '">'
            . '<label class="grow">Ara<input type="search" name="q" value="' . Ui::e($q) . '" placeholder="Servis adı ya da ID"></label>'
            . '<button class="btn quiet">' . Ui::icon('search') . '<span>Ara</span></button>'
            . $this->ui->action('rival_run', 'Rakipleri şimdi yenile', [], 'quiet', 'refresh') . '</form>';

        if (!$c['rows']) {
            $h .= Ui::empty($f === 'pricier' ? 'Rakipten pahalı servisin yok' : 'Bu süzgeçte servis yok', $c['summary']['compared'] === 0 ? 'Rakipler henüz yenilenmedi ya da eşleşme bulunamadı. "Rakipleri şimdi yenile" ile dene.' : 'Başka bir süzgeç seç.');
            $this->render('Fiyat kıyası', $h, ['active' => 'rivals', 'sub' => $sub]);
            return;
        }
        $h .= '<div class="table-wrap"><table><thead><tr><th>Senin servisin</th><th class="num">Fiyatın</th>';
        foreach ($c['rivals'] as $rv) {
            $h .= '<th>' . Ui::e($rv['name']) . '</th>';
        }
        $h .= '</tr></thead><tbody>';
        foreach ($c['rows'] as $r) {
            $h .= '<tr><td><b>' . Ui::e(mb_substr($r['name'], 0, 90)) . '</b><div class="src">#' . $r['id'] . ($r['category'] !== '' ? ' · ' . Ui::e(mb_substr($r['category'], 0, 40)) : '') . '</div></td><td class="num tight">' . self::fmtRate($r['rate']) . '</td>';
            foreach ($c['rivals'] as $rv) {
                $cell = $r['cells'][(int)$rv['id']] ?? null;
                if ($cell === null) {
                    $h .= '<td class="muted">—</td>';
                    continue;
                }
                $d = $cell['diff'] * 100;
                $cls = $cell['diff'] > $R->alertPct() ? 'lvl-error' : ($cell['diff'] < -$R->alertPct() ? 'lvl-good' : 'muted');
                $h .= '<td><b>' . self::fmtRate($cell['rate']) . '</b> <span class="' . $cls . '">' . ($d >= 0 ? '+' : '−') . number_format(abs($d), 0) . '%</span>'
                    . '<div class="src">' . Ui::e(mb_substr($cell['name'], 0, 70)) . ' · #' . $cell['service'] . '</div>';
                if ($cell['status'] === 'review') {
                    $h .= '<div class="row">' . $this->ui->action('rival_confirm', 'Doğru', ['own' => $r['id'], 'src' => $rv['id'], 'f' => $f], 'quiet sm', 'check')
                        . $this->ui->action('rival_reject', 'Yanlış', ['own' => $r['id'], 'src' => $rv['id'], 'f' => $f], 'ghost sm', 'x') . '</div>';
                }
                $h .= '</td>';
            }
            $h .= '</tr>';
        }
        $h .= '</tbody></table></div>';
        $pages = max(1, (int)ceil($c['total'] / 40));
        if ($pages > 1) {
            $base = ['p' => 'rivals', 'f' => $f, 'q' => $q];
            $h .= '<nav class="pager">' . ($page > 1 ? Ui::link('?' . http_build_query($base + ['page' => $page - 1]), 'Önceki', 'quiet sm') : '')
                . '<span>Sayfa ' . $page . ' / ' . $pages . ', toplam ' . $c['total'] . '</span>'
                . ($page < $pages ? Ui::link('?' . http_build_query($base + ['page' => $page + 1]), 'Sonraki', 'quiet sm') : '') . '</nav>';
        }
        $this->render('Fiyat kıyası', $h, ['active' => 'rivals', 'sub' => $sub]);
    }

    private static function fmtRate(float $v): string
    {
        return rtrim(rtrim(number_format($v, 4, '.', ''), '0'), '.');
    }

    private function rivalListPage(): void
    {
        $R = new Rivals($this->db, $this->config);
        $h = '<p class="muted lead">Rakip = servis listesini standart SMM API ile (<code>action=services</code>) veren başka bir panel. Anahtar yalnızca sunucudaki veritabanında saklanır. Fiyatlar cron ile her '
            . $R->minutes() . ' dakikada bir yenilenir; rakip belirlediğin yüzdeden ucuza düşerse bota haber gelir. Süre ve yüzde: <a href="?p=settings&amp;tab=features">Ayarlar → Özellikler</a>.</p>';
        $rows = $R->sources();
        if ($rows) {
            $h .= '<div class="table-wrap"><table><thead><tr><th>Rakip</th><th>API adresi</th><th class="num">Servis</th><th class="num">Kur çarpanı</th><th>Son yenileme</th><th></th></tr></thead><tbody>';
            foreach ($rows as $s) {
                $h .= '<tr><td><b>' . Ui::e($s['name']) . '</b>' . ($s['last_error'] ? '<div class="src lvl-error">' . Ui::e($s['last_error']) . '</div>' : '')
                    . ((string)$s['watch_note'] !== '' ? '<div class="src">' . Ui::e((string)$s['watch_note']) . '</div>' : '') . '</td>'
                    . '<td class="src">' . Ui::e($s['api_url']) . '</td><td class="num tight">' . (int)$s['services'] . '</td>'
                    . '<td class="tight"><form method="post" action="?p=rival_fx" class="field-row">' . $this->ui->csrfField() . '<input type="hidden" name="id" value="' . (int)$s['id'] . '">'
                    . '<input type="text" name="fx" value="' . Ui::e(rtrim(rtrim(number_format((float)$s['fx'], 6, '.', ''), '0'), '.')) . '" size="6" aria-label="Kur çarpanı"><button class="btn ghost sm">Kaydet</button></form></td>'
                    . '<td class="tight">' . Ui::ago($s['last_sync_at'] ? (int)$s['last_sync_at'] : null) . '</td>'
                    . '<td class="tight"><div class="row">' . $this->ui->action('rival_refresh', 'Yenile', ['id' => $s['id']], 'quiet sm', 'refresh')
                    . $this->ui->action('rival_del', 'Sil', ['id' => $s['id']], 'ghost sm', 'trash', $s['name'] . ' ve eşleşmeleri silinsin mi?') . '</div></td></tr>';
            }
            $h .= '</tbody></table></div>';
        }
        $h .= '<h2>Rakip ekle</h2><form method="post" action="?p=rival_add" class="panel panel-pad field-row gap-top" autocomplete="off">' . $this->ui->csrfField()
            . '<label class="grow">Ad<input type="text" name="name" placeholder="Rakip panel" required></label>'
            . '<label class="grow">API adresi<input type="text" name="api_url" placeholder="https://rakip.com/api/v2" required></label>'
            . '<label class="grow">API anahtarı<input type="password" name="api_key" required autocomplete="new-password"></label>'
            . '<label>Kur çarpanı<input type="text" name="fx" value="1" size="6"></label>'
            . '<button class="btn primary">' . Ui::icon('plus') . '<span>Ekle ve kıyasla</span></button></form>'
            . '<p class="muted">Kur çarpanı: rakibin fiyatı × bu sayı = senin para biriminle fiyat. İkiniz de dolar kullanıyorsanız 1 bırak; rakip dolar, sen manat kullanıyorsan 1.70 gibi yaz.</p>';
        $this->render('Rakip listesi', $h, ['active' => 'rival_list', 'sub' => 'Fiyatını izlemek istediğin paneller.']);
    }

    private function postRival(string $p): bool
    {
        if (!str_starts_with($p, 'rival_')) {
            return false;
        }
        $id = (int)($_POST['id'] ?? 0);
        $R = new Rivals($this->db, $this->config);
        switch ($p) {
            case 'rival_add':
                $name = trim((string)($_POST['name'] ?? ''));
                $url = trim((string)($_POST['api_url'] ?? ''));
                $key = trim((string)($_POST['api_key'] ?? ''));
                $fx = (float)str_replace(',', '.', (string)($_POST['fx'] ?? '1'));
                if ($name === '' || $key === '' || !preg_match('~^https://~i', $url)) {
                    throw new \InvalidArgumentException('Ad, https:// ile başlayan API adresi ve anahtar gerekli.');
                }
                $this->db->run("INSERT INTO sources(kind, name, api_url, api_key, created_at, fx) VALUES('rival',?,?,?,?,?)", [$name, $url, $key, time(), $fx > 0 ? $fx : 1.0]);
                $sid = (int)$this->db->pdo->lastInsertId();
                try {
                    $m = $R->refresh($sid);
                    $this->say('ok', "$name eklendi: {$m['matched']} servis eşleşti, {$m['review']} kontrol bekliyor.");
                } catch (\Throwable $e) {
                    $this->say('bad', "$name eklendi ama okunamadı: " . $e->getMessage());
                }
                $this->redirect('rival_list');
                return true;
            case 'rival_fx':
                $fx = (float)str_replace(',', '.', (string)($_POST['fx'] ?? '1'));
                if ($fx <= 0 || $fx > 100000) {
                    throw new \InvalidArgumentException('Kur çarpanı sıfırdan büyük bir sayı olmalı.');
                }
                $this->db->run("UPDATE sources SET fx = ? WHERE id = ? AND kind = 'rival'", [$fx, $id]);
                $this->say('ok', 'Kur çarpanı kaydedildi.');
                $this->redirect('rival_list');
                return true;
            case 'rival_refresh':
                $m = $R->refresh($id);
                $this->say('ok', "{$m['matched']} servis eşleşti, {$m['review']} kontrol bekliyor.");
                $this->redirect('rival_list');
                return true;
            case 'rival_run':
                $out = $R->cron(0, true);
                $bad = array_filter($out, static fn($r) => !$r['ok']);
                $this->say($bad ? 'bad' : 'ok', $bad ? 'Okunamayan rakip: ' . implode('; ', array_map(static fn($r) => $r['name'] . ': ' . $r['error'], $bad)) : 'Rakipler yenilendi.');
                $this->redirect('rivals');
                return true;
            case 'rival_del':
                $this->db->run("DELETE FROM rival_map WHERE rival_source = ?", [$id]);
                $this->db->run("DELETE FROM rival_state WHERE rival_source = ?", [$id]);
                $this->db->run("DELETE FROM remote_services WHERE source_id = ?", [$id]);
                $this->db->run("DELETE FROM sources WHERE id = ? AND kind = 'rival'", [$id]);
                $this->say('ok', 'Rakip silindi.');
                $this->redirect('rival_list');
                return true;
            case 'rival_confirm':
            case 'rival_reject':
                $own = (int)($_POST['own'] ?? 0);
                $src = (int)($_POST['src'] ?? 0);
                $this->db->run('UPDATE rival_map SET status = ? WHERE own_service = ? AND rival_source = ?', [$p === 'rival_confirm' ? 'confirmed' : 'rejected', $own, $src]);
                if ($p === 'rival_reject') {
                    $R->match($src); // reddedilen eşleşme yerine bir sonraki en iyi aday aranır
                }
                $f = (string)($_POST['f'] ?? 'all');
                $this->redirect('rivals', ['f' => $f]);
                return true;
        }
        return false;
    }
}
