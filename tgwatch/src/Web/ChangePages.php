<?php
declare(strict_types=1);

namespace TgWatch\Web;

use TgWatch\App;
use TgWatch\Enrich;
use TgWatch\Notices;

/** Panele ekleme paketi (iş sayfası) ve servis değişiklikleri sayfası. Admin içinde kullanılır. */
trait ChangePages
{
    private static function money(?float $v): string
    {
        return $v === null ? '—' : '$' . number_format($v, $v < 0.1 ? 3 : 2);
    }

    /** İş sayfasındaki "Panele ekleme paketi": sağlayıcı, kategori, fiyat önerisi, min/max, panelde var mı. */
    private function enrichCard(array $job): string
    {
        $en = new Enrich($this->db);
        $info = $en->forJob($job);
        $prov = $info['provider'];
        $ch = $this->db->one('SELECT id, source_id FROM channels WHERE username = ?', [$job['channel']]);

        $h = '<h2>Panele ekleme paketi</h2><div class="panel panel-pad stack">';
        // sağlayıcı ve kategori
        $h .= '<dl class="kv"><dt>Sağlayıcı</dt><dd>';
        if ($prov) {
            $h .= Ui::e($prov['name']) . ($prov['last_sync_at'] ? ' <span class="muted">(liste ' . strip_tags(Ui::ago((int)$prov['last_sync_at'])) . ' güncellendi)</span>' : ' <span class="lvl-warn">(liste henüz alınmadı)</span>');
        } else {
            $h .= '<span class="lvl-warn">Seçilmemiş</span>';
        }
        $provs = $en->providers();
        if ($ch && $provs && (count($provs) > 1 || !$prov)) {
            $opts = '<option value="">Seçin…</option>';
            foreach ($provs as $s) {
                $opts .= '<option value="' . (int)$s['id'] . '"' . ($prov && (int)$prov['id'] === (int)$s['id'] ? ' selected' : '') . '>' . Ui::e($s['name']) . '</option>';
            }
            $h .= '<form method="post" action="?p=channel_source" class="field-row gap-top">' . $this->ui->csrfField() . '<input type="hidden" name="id" value="' . (int)$ch['id'] . '"><input type="hidden" name="back" value="job:' . (int)$job['id'] . '">'
                . '<label>@' . Ui::e($job['channel']) . ' kanalının sağlayıcısı<select name="source_id">' . $opts . '</select></label><button class="btn quiet sm">Kaydet</button></form>';
        } elseif (!$provs) {
            $h .= ' <a href="?p=bank">Sağlayıcı ekleyin</a>';
        }
        $h .= '</dd><dt>Kategori</dt><dd>';
        $cat = $info['category'];
        if ($cat['az'] !== '') {
            $h .= '<span id="cat-az">' . Ui::e($cat['az']) . '</span> ' . ($cat['exists'] ? '<span class="badge s-published">panelde var</span>' : '<span class="badge s-approved">yeni kategori</span>') . ' ' . Ui::copy('cat-az')
                . '<div class="src muted">' . Ui::e($cat['en']) . '</div>';
        } else {
            $h .= '<span class="muted">Duyuruda kategori başlığı yok</span>';
        }
        $h .= '</dd><dt>Fiyat önerisi</dt><dd>' . ($info['formula'] ? Ui::e($info['formula']) : '<span class="lvl-warn">Formül yok</span> <span class="muted">(Çeviri bankasında eşleştirmeyi çalıştırın ya da Çeviri ayarlarından kâr formülünü girin)</span>') . '</dd></dl>';

        if ($info['duplicates'] > 0) {
            $h .= '<div class="flash bad">' . Ui::icon('alert') . '<span><b>' . $info['duplicates'] . ' servis panelinde zaten var görünüyor.</b> Aşağıdaki "Panelde" sütununa bakın; yinelenen eklemeyi önlemek için işaretlemeyin.</span></div>';
        }

        // servis tablosu
        $h .= '<div class="table-wrap"><table><thead><tr><th>ID</th><th>Azerice ad</th><th class="num">Sağlayıcı</th><th class="num">Önerilen satış</th><th>Min–Max</th><th>İade / İptal</th><th>Panelde</th></tr></thead><tbody>';
        foreach ($job['items'] as $it) {
            $x = $info['items'][(int)$it['id']];
            $flags = $x['remote'] ? ($x['refill'] === null ? '—' : ($x['refill'] ? 'İade ✓' : 'İade ✗') . ' · ' . ($x['cancel'] ? 'İptal ✓' : 'İptal ✗')) : '—';
            $own = $x['own'] ? '<span class="badge s-failed">var: #' . (int)$x['own']['service_id'] . '</span>' . (!empty($x['own']['rate']) ? '<div class="src">satış ' . self::money((float)$x['own']['rate']) . '</div>' : '') : '<span class="muted">yok</span>';
            $h .= '<tr><td class="tight"><b>' . (int)$it['id'] . '</b></td><td>' . Ui::e($it['az']) . '</td><td class="num tight">' . self::money($x['rate']) . '</td>'
                . '<td class="num tight"><b>' . self::money($x['suggest']) . '</b></td><td class="tight">' . ($x['min'] !== null ? number_format((int)$x['min'], 0, ',', '.') . '–' . number_format((int)$x['max'], 0, ',', '.') : '—') . '</td>'
                . '<td class="tight">' . $flags . '</td><td class="tight">' . $own . '</td></tr>';
        }
        $h .= '</tbody></table></div>';
        if (!$info['synced'] && $prov) {
            $h .= '<p class="muted">Bu servisler sağlayıcının güncel listesinde bulunamadı. Fiyat duyurudan alındı; sağlayıcıyı Çeviri bankası sayfasından senkronize edin.</p>';
        }
        return $h . '</div>';
    }

    // ---------------------------------------------------------------- servis değişiklikleri

    private function changes(): void
    {
        $st = (string)($_GET['status'] ?? 'open');
        $st = isset(Notices::STATUS[$st]) ? $st : 'open';
        $nt = new Notices($this->db);
        $count = [];
        foreach ($this->db->all('SELECT status, COUNT(*) n FROM notices GROUP BY status') as $r) {
            $count[$r['status']] = (int)$r['n'];
        }
        $rows = $this->db->all('SELECT id FROM notices WHERE status = ? ORDER BY id DESC LIMIT 50', [$st]);

        $h = '<div class="chips" role="navigation">';
        foreach (Notices::STATUS as $k => $v) {
            $h .= '<a class="chip" href="?p=changes&amp;status=' . $k . '"' . ($st === $k ? ' aria-current="true"' : '') . '>' . $v . ' <span class="n">' . ($count[$k] ?? 0) . '</span></a>';
        }
        $h .= '</div><div class="stack gap-top">';
        if (!$rows) {
            $h .= Ui::empty($st === 'open' ? 'Açık servis değişikliği yok' : 'Kayıt yok', $st === 'open' ? 'Sağlayıcı kanallarında fiyat değişikliği ya da kapatılan servis duyurusu çıkınca burada ve botta görünür.' : 'Bu durumda bildirim bulunmuyor.');
        }
        foreach ($rows as $r) {
            $n = $nt->get((int)$r['id']);
            $impact = $nt->impact($n);
            $h .= '<article class="card"><div class="qhead"><b class="id">Bildirim ' . (int)$n['id'] . '</b><span class="badge ' . ($n['kind'] === 'closed' ? 's-failed' : 's-approved') . '">' . Ui::e(Notices::KINDS[$n['kind']] ?? $n['kind']) . '</span>'
                . '<span class="meta"><a href="https://t.me/' . Ui::e($n['channel']) . '/' . (int)$n['msg_id'] . '" target="_blank" rel="noopener noreferrer">@' . Ui::e($n['channel']) . '</a>, ' . Ui::ago((int)$n['created_at']) . '</span>';
            if ($n['status'] === 'open') {
                $h .= '<div class="row">' . $this->ui->action('notice_do', 'Yaptım', ['id' => $n['id'], 'do' => 'done'], 'primary sm', 'check') . $this->ui->action('notice_do', 'Yoksay', ['id' => $n['id'], 'do' => 'ignored'], 'quiet sm', 'x') . '</div>';
            } else {
                $h .= '<span class="badge">' . Ui::e(Notices::STATUS[$n['status']]) . '</span>';
            }
            $h .= '</div><div class="table-wrap"><table><thead><tr><th>Servis</th><th>Panelinde</th>' . ($n['kind'] === 'price' ? '<th class="num">Sağlayıcı fiyatı</th><th class="num">Mevcut satış</th><th class="num">Yeni satış</th>' : '') . '<th>Yapılacak</th></tr></thead><tbody>';
            foreach ($impact as $x) {
                $h .= '<tr><td class="tight"><b>' . $x['id'] . '</b>' . ($x['provider'] ? '<div class="src">' . Ui::e(mb_strimwidth($x['provider']['name'], 0, 46, '…')) . '</div>' : '') . '</td>'
                    . '<td>' . ($x['own'] ? '#' . (int)$x['own']['service_id'] . '<div class="src">' . Ui::e(mb_strimwidth((string)$x['own']['name'], 0, 56, '…')) . '</div>' : '<span class="muted">eşleşme yok</span>') . '</td>';
                if ($n['kind'] === 'price') {
                    $h .= '<td class="num tight">' . ($x['old'] !== null ? self::money($x['old']) . ' → ' : '') . '<b>' . self::money($x['new']) . '</b></td><td class="num tight">' . self::money($x['current']) . '</td><td class="num tight"><b>' . self::money($x['suggest']) . '</b></td>';
                }
                $h .= '<td>' . Ui::e($x['action']) . '</td></tr>';
            }
            $h .= '</tbody></table></div><details class="gap-top"><summary>Duyuru metni</summary><pre>' . Ui::e($n['text']) . '</pre></details></article>';
        }
        $h .= '</div>';
        $this->render('Servis değişiklikleri', $h, ['active' => 'changes', 'sub' => 'Sağlayıcıların fiyat değişikliği ve kapatılan servis duyuruları. Panelinde elle yapılacakları söyler.']);
    }

    /** @return bool işlendi mi */
    private function postChanges(string $p): bool
    {
        $id = (int)($_POST['id'] ?? 0);
        switch ($p) {
            case 'notice_do':
                $do = (string)($_POST['do'] ?? '');
                if (!in_array($do, ['done', 'ignored'], true)) {
                    throw new \InvalidArgumentException('Bilinmeyen işlem.');
                }
                $this->say('ok', App::flow()->resolveNotice($id, $do));
                $this->redirect('changes');
                return true;
            case 'channel_source':
                $sid = (int)($_POST['source_id'] ?? 0);
                if ($sid && !$this->db->one("SELECT id FROM sources WHERE id = ? AND kind = 'provider'", [$sid])) {
                    throw new \InvalidArgumentException('Geçersiz sağlayıcı.');
                }
                $this->db->run('UPDATE channels SET source_id = ? WHERE id = ?', [$sid ?: null, $id]);
                $this->say('ok', 'Kanalın sağlayıcısı kaydedildi.');
                $back = (string)($_POST['back'] ?? '');
                preg_match('/^job:(\d+)$/', $back, $m) ? $this->redirect('job', ['id' => (int)$m[1]]) : $this->redirect('channels');
                return true;
        }
        return false;
    }
}
