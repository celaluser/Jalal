<?php
declare(strict_types=1);

namespace TgWatch\Web;

use TgWatch\App;
use TgWatch\Enrich;
use TgWatch\Flow;
use TgWatch\Notices;
use TgWatch\Parser;
use TgWatch\Reports;

/** Araçlar: elle iş ekleme, servis kataloğu, fiyat denetimi, raporlar, iş silme/not. */
trait ToolPages
{
    // ---------------------------------------------------------------- yeni iş

    private function jobNewPage(): void
    {
        $chans = $this->db->all('SELECT username FROM channels ORDER BY username');
        $opts = '<option value="elle">Elle (kanalsız)</option>';
        foreach ($chans as $c) {
            $opts .= '<option value="' . Ui::e($c['username']) . '">@' . Ui::e($c['username']) . '</option>';
        }
        $sample = "🆔 13125 Threads Followers | No Drop | Max 50K\n💰 Price: \$1.20 per 1000\n\n🆔 13126 Threads Likes | Fast\n💰 0.35\$";
        $h = '<form method="post" action="?p=job_create" class="stack">' . $this->ui->csrfField()
            . '<div class="panel panel-pad stack"><label>Duyuru metni<textarea name="text" rows="12" required placeholder="' . Ui::e($sample) . '">' . Ui::e((string)($_SESSION['job_new_text'] ?? '')) . '</textarea></label>'
            . '<label>Hangi kanalın ayarları kullanılsın (sağlayıcı, filtre)<select name="channel">' . $opts . '</select></label>'
            . '<p class="muted">Telegram’dan okunamayan duyuruları (özel kanal, başka kaynak, kendi notların) buraya yapıştır. Her servis satırı <code>🆔 ID Ad</code> biçiminde olmalı; fiyat satırı varsa okunur. Metin kanaldan okunanla aynı hattan geçer: çevrilir, bota onay mesajı gider.</p>'
            . '<div class="row"><button class="btn primary">' . Ui::icon('plus') . '<span>İş oluştur</span></button></div></div></form>';
        unset($_SESSION['job_new_text']);
        $this->render('Yeni iş', $h, ['active' => 'jobs', 'sub' => 'Duyuruyu elle yapıştır, kanaldan gelmiş gibi işlensin.']);
    }

    // ---------------------------------------------------------------- servis kataloğu

    private function catalogPage(): void
    {
        $q = trim((string)($_GET['q'] ?? ''));
        $source = (string)($_GET['source'] ?? 'all');
        $page = max(1, (int)($_GET['page'] ?? 1));
        $rep = new Reports($this->db);
        $res = $rep->catalog($q, $source, $page);
        $enrich = new Enrich($this->db);

        $sources = $this->db->all('SELECT id, name, kind, services, last_sync_at FROM sources ORDER BY kind DESC, id');
        $so = '<option value="all">Tüm kaynaklar</option>';
        foreach ($sources as $s) {
            $so .= '<option value="' . (int)$s['id'] . '"' . ($source === (string)$s['id'] ? ' selected' : '') . '>' . Ui::e($s['name']) . ' (' . ($s['kind'] === 'own' ? 'senin panelin' : 'sağlayıcı') . ', ' . (int)$s['services'] . ')</option>';
        }
        $h = '<form method="get" class="toolbar">' . '<input type="hidden" name="p" value="catalog">'
            . '<label class="grow">Ara<input type="search" name="q" value="' . Ui::e($q) . '" placeholder="Servis ID, ad ya da kategori"></label>'
            . '<label>Kaynak<select name="source">' . $so . '</select></label>'
            . '<button class="btn primary">' . Ui::icon('search') . '<span>Ara</span></button></form>';
        $m = $enrich->formula();
        $h .= '<p class="muted lead">' . ($sources ? 'Son senkronizasyondaki servisler. ' . ($m ? Ui::e($m) . '.' : 'Fiyat formülü için Çeviri bankası > Eşleştirmeyi çalıştır.') : 'Henüz kaynak yok: Çeviri bankası sayfasından sağlayıcı ve kendi panelini ekle.') . '</p>';

        if (!$res['rows']) {
            $h .= Ui::empty('Servis bulunamadı', $sources ? 'Aramayı değiştir ya da kaynakları Çeviri bankası sayfasından yeniden senkronize et.' : 'Önce bir kaynak ekle.');
        } else {
            $h .= '<div class="table-wrap"><table><thead><tr><th>Kaynak</th><th>ID</th><th>Ad</th><th class="num">Fiyat</th><th class="num">Önerilen satış</th><th class="num">Min–Max</th><th></th></tr></thead><tbody>';
            foreach ($res['rows'] as $r) {
                $prov = $r['source_kind'] === 'provider';
                $sug = $prov && $r['rate'] !== null ? $enrich->suggestPrice((float)$r['rate']) : null;
                $h .= '<tr><td class="tight"><span class="src">' . Ui::e($r['source_name']) . '</span></td><td class="tight"><b>' . (int)$r['service_id'] . '</b></td>'
                    . '<td>' . Ui::e($r['name']) . ($r['category'] !== '' ? '<div class="src">' . Ui::e($r['category']) . '</div>' : '') . '</td>'
                    . '<td class="num tight">' . ($r['rate'] !== null ? '$' . rtrim(rtrim(number_format((float)$r['rate'], 4, '.', ''), '0'), '.') : '—') . '</td>'
                    . '<td class="num tight">' . ($sug !== null ? '$' . rtrim(rtrim(number_format($sug, 4, '.', ''), '0'), '.') : '<span class="muted">—</span>') . '</td>'
                    . '<td class="num tight">' . ($r['min'] !== null ? (int)$r['min'] . '–' . (int)$r['max'] : '—') . '</td>'
                    . '<td class="tight">' . ($prov ? '<a href="?p=jobs&amp;q=' . (int)$r['service_id'] . '">işlerde ara</a>' : '') . '</td></tr>';
            }
            $h .= '</tbody></table></div>' . $this->pager('catalog', ['q' => $q, 'source' => $source], $page, (int)$res['total'], 40);
        }

        // fiyat hesaplayıcı
        $h .= '<h2>Fiyat hesaplayıcı</h2><form method="get" class="panel panel-pad field-row"><input type="hidden" name="p" value="catalog">'
            . '<label>Sağlayıcı fiyatı ($/1000)<input type="text" name="calc" value="' . Ui::e((string)($_GET['calc'] ?? '')) . '" inputmode="decimal" placeholder="0.44" class="narrow"></label>'
            . '<button class="btn quiet">' . Ui::icon('refresh') . '<span>Hesapla</span></button>';
        $calc = str_replace(',', '.', trim((string)($_GET['calc'] ?? '')));
        if ($calc !== '' && is_numeric($calc)) {
            $s = $enrich->suggestPrice((float)$calc);
            $h .= '<span class="note">' . ($s !== null ? 'Önerilen satış fiyatı: <b>$' . rtrim(rtrim(number_format($s, 4, '.', ''), '0'), '.') . '</b>' : 'Formül yok: Eşleştirmeyi çalıştır ya da Çeviri ayarları > Fiyat önerisi\'ni doldur.') . '</span>';
        }
        $h .= '</form>';
        $this->render('Servisler', $h, ['active' => 'catalog', 'sub' => 'Sağlayıcı ve kendi panelindeki servisleri ara, fiyat hesapla.']);
    }

    private function pager(string $p, array $q, int $page, int $total, int $per): string
    {
        $pages = max(1, (int)ceil($total / $per));
        if ($pages <= 1) {
            return '';
        }
        $link = static fn(int $n, string $label, bool $on) => $on ? '<a class="btn quiet sm" href="?' . Ui::e(http_build_query(['p' => $p] + $q + ['page' => $n])) . '">' . $label . '</a>' : '<span class="btn quiet sm busy">' . $label . '</span>';
        return '<div class="row gap-top">' . $link($page - 1, '← Önceki', $page > 1) . '<span class="muted">Sayfa ' . $page . ' / ' . $pages . ' · ' . $total . ' servis</span>' . $link($page + 1, 'Sonraki →', $page < $pages) . '</div>';
    }

    // ---------------------------------------------------------------- fiyat denetimi

    private function auditPage(): void
    {
        $tol = max(0.01, min(1.0, ((float)str_replace(',', '.', (string)($_GET['tol'] ?? '10'))) / 100));
        $filter = (string)($_GET['f'] ?? '');
        $rows = (new Reports($this->db))->audit($tol);
        if (($_GET['csv'] ?? '') === '1') {
            $this->csv('fiyat-denetimi', ['Panel ID', 'Ad', 'Panel fiyatı', 'Sağlayıcı ID', 'Sağlayıcı fiyatı', 'Olması gereken', 'Fark %', 'Durum'],
                array_map(static fn($r) => [$r['own_id'], $r['own_name'], $r['own_rate'], $r['prov_id'], $r['prov_rate'], $r['want'] ?? '', $r['delta'] ?? '', $r['flag']], $rows));
            return;
        }
        $count = ['loss' => 0, 'low' => 0, 'high' => 0, 'ok' => 0];
        foreach ($rows as $r) {
            $count[$r['flag']]++;
        }
        $label = ['loss' => 'Zarar', 'low' => 'Düşük', 'high' => 'Yüksek', 'ok' => 'Uygun'];
        $chips = '<a class="chip" href="?p=audit&amp;tol=' . (int)round($tol * 100) . '"' . ($filter === '' ? ' aria-current="true"' : '') . '>Tümü ' . count($rows) . '</a>';
        foreach ($label as $k => $v) {
            $chips .= '<a class="chip" href="?p=audit&amp;f=' . $k . '&amp;tol=' . (int)round($tol * 100) . '"' . ($filter === $k ? ' aria-current="true"' : '') . '>' . $v . ' ' . $count[$k] . '</a>';
        }
        $h = '<form method="get" class="toolbar"><input type="hidden" name="p" value="audit"><input type="hidden" name="f" value="' . Ui::e($filter) . '">'
            . '<label>Tolerans (%)<input type="number" name="tol" value="' . (int)round($tol * 100) . '" min="1" max="100" class="narrow"></label>'
            . '<button class="btn quiet">' . Ui::icon('refresh') . '<span>Uygula</span></button>' . Ui::link('?p=audit&csv=1&tol=' . (int)round($tol * 100), 'CSV indir', 'ghost', 'download') . '</form>'
            . '<div class="chips">' . $chips . '</div>';
        $enrich = new Enrich($this->db);
        $h .= '<p class="muted lead">' . ($enrich->formula() ? Ui::e((string)$enrich->formula()) . '. ' : 'Fiyat formülü yok, yalnızca zarar kontrolü yapılır. ')
            . '<b>Zarar</b>: senin fiyatın sağlayıcıdan düşük. <b>Düşük/Yüksek</b>: beklenen satış fiyatından toleransın dışında.</p>';
        $shown = array_values(array_filter($rows, static fn($r) => $filter === '' || $r['flag'] === $filter));
        if (!$rows) {
            $h .= Ui::empty('Karşılaştırılacak servis yok', 'Çeviri bankası sayfasından sağlayıcı ve kendi panelini senkronize edip Eşleştirmeyi çalıştır.', Ui::link('?p=bank', 'Çeviri bankasını aç', 'primary'));
        } elseif (!$shown) {
            $h .= Ui::empty('Bu filtrede servis yok', 'Başka bir durum seç.');
        } else {
            $h .= '<div class="table-wrap"><table><thead><tr><th>Durum</th><th>Panel</th><th class="num">Fiyatın</th><th>Sağlayıcı</th><th class="num">Sağlayıcı</th><th class="num">Olması gereken</th><th class="num">Fark</th></tr></thead><tbody>';
            foreach (array_slice($shown, 0, 300) as $r) {
                $cls = ['loss' => 'lvl-error', 'low' => 'lvl-warn', 'high' => 'lvl-warn', 'ok' => ''][$r['flag']];
                $f = static fn($n) => '$' . rtrim(rtrim(number_format((float)$n, 4, '.', ''), '0'), '.');
                $h .= '<tr><td class="tight ' . $cls . '"><b>' . $label[$r['flag']] . '</b></td><td><b>#' . $r['own_id'] . '</b> ' . Ui::e(mb_strimwidth($r['own_name'], 0, 70, '…')) . '</td>'
                    . '<td class="num tight">' . $f($r['own_rate']) . '</td><td class="muted">#' . $r['prov_id'] . ' ' . Ui::e(mb_strimwidth($r['prov_name'], 0, 50, '…')) . '</td>'
                    . '<td class="num tight">' . $f($r['prov_rate']) . '</td><td class="num tight">' . ($r['want'] !== null ? $f($r['want']) : '—') . '</td>'
                    . '<td class="num tight ' . $cls . '">' . ($r['delta'] !== null ? ($r['delta'] > 0 ? '+' : '') . $r['delta'] . '%' : '—') . '</td></tr>';
            }
            $h .= '</tbody></table></div>' . (count($shown) > 300 ? '<p class="muted">İlk 300 satır gösteriliyor; hepsi için CSV indir.</p>' : '');
        }
        $this->render('Fiyat denetimi', $h, ['active' => 'audit', 'sub' => 'Eşleşen servislerde fiyatın sağlayıcıya ve formüle göre durumu.']);
    }

    // ---------------------------------------------------------------- raporlar

    private function reportsPage(): void
    {
        $days = in_array((int)($_GET['days'] ?? 30), [7, 30, 90, 365], true) ? (int)($_GET['days'] ?? 30) : 30;
        $r = (new Reports($this->db))->channels($days);
        if (($_GET['csv'] ?? '') === '1') {
            $this->csv('rapor-' . $days . 'gun', ['Kanal', 'İş', 'Onaylanan', 'Reddedilen', 'Açık', 'Onay %', 'Ort. karar (sn)'],
                array_map(static fn($c) => [$c['channel'], $c['jobs'], $c['approved'], $c['rejected'], $c['open'], $c['rate'] ?? '', $c['avg_seconds'] ?? ''], $r['channels']));
            return;
        }
        $dur = static function (?int $s): string {
            if ($s === null) {
                return '—';
            }
            return $s < 90 ? $s . ' sn' : ($s < 5400 ? round($s / 60) . ' dk' : ($s < 172800 ? round($s / 3600, 1) . ' sa' : round($s / 86400, 1) . ' gün'));
        };
        $chips = '';
        foreach ([7 => '7 gün', 30 => '30 gün', 90 => '90 gün', 365 => '1 yıl'] as $d => $l) {
            $chips .= '<a class="chip" href="?p=reports&amp;days=' . $d . '"' . ($days === $d ? ' aria-current="true"' : '') . '>' . $l . '</a>';
        }
        $t = $r['total'];
        $tile = static fn(string $label, string $v, string $foot = '') => '<section class="card"><div class="tile-label">' . $label . '</div><div class="tile-value">' . $v . '</div><div class="tile-foot">' . $foot . '</div></section>';
        $h = '<div class="chips">' . $chips . '</div><div class="dash gap-top">'
            . '<div class="c-3">' . $tile('Gelen iş', (string)$t['jobs'], $t['services'] . ' servis') . '</div>'
            . '<div class="c-3">' . $tile('Onaylanan', (string)$t['approved'], $t['rate'] !== null ? 'Onay oranı %' . $t['rate'] : '') . '</div>'
            . '<div class="c-3">' . $tile('Reddedilen', (string)$t['rejected'], $t['open'] . ' iş açık') . '</div>'
            . '<div class="c-3">' . $tile('Ort. karar süresi', $dur($t['avg_seconds']), 'gelişten karara') . '</div></div>';
        if ($r['channels']) {
            $h .= '<h2>Kanallara göre</h2><div class="table-wrap"><table><thead><tr><th>Kanal</th><th class="num">İş</th><th class="num">Onay</th><th class="num">Ret</th><th class="num">Açık</th><th class="num">Onay %</th><th class="num">Ort. karar</th></tr></thead><tbody>';
            foreach ($r['channels'] as $c) {
                $h .= '<tr><td><a href="?p=jobs&amp;channel=' . Ui::e($c['channel']) . '"><b>@' . Ui::e($c['channel']) . '</b></a></td><td class="num">' . $c['jobs'] . '</td><td class="num">' . $c['approved'] . '</td><td class="num">' . $c['rejected'] . '</td><td class="num">' . $c['open']
                    . '</td><td class="num">' . ($c['rate'] !== null ? '%' . $c['rate'] : '—') . '</td><td class="num">' . $dur($c['avg_seconds']) . '</td></tr>';
            }
            $h .= '</tbody></table></div><div class="row gap-top">' . Ui::link('?p=reports&csv=1&days=' . $days, 'CSV indir', 'ghost', 'download') . '</div>';
        } else {
            $h .= Ui::empty('Bu dönemde iş yok', 'Kanallar okundukça burada kanal başına istatistikler oluşur.');
        }
        if ($r['via']) {
            $total = array_sum($r['via']);
            $h .= '<h2>Çevirinin kaynağı</h2><div class="panel panel-pad stack">';
            foreach (\TgWatch\Engine::VIA_LABELS as $k => $l) {
                if (empty($r['via'][$k])) {
                    continue;
                }
                $h .= '<div class="row">' . Ui::via($k) . '<span class="muted">' . $r['via'][$k] . ' ad · %' . round(100 * $r['via'][$k] / $total) . '</span></div>';
            }
            $h .= '</div>';
        }
        if ($r['notices']) {
            $h .= '<h2>Servis değişiklikleri</h2><div class="panel panel-pad row">';
            foreach ($r['notices'] as $k => $n) {
                $h .= '<span><b>' . $n . '</b> ' . Ui::e(Notices::KINDS[$k] ?? $k) . '</span>';
            }
            $h .= '</div>';
        }
        $this->render('Raporlar', $h, ['active' => 'reports', 'sub' => 'Son ' . $days . ' günün özeti.']);
    }

    private function csv(string $name, array $head, array $rows): void
    {
        header('Content-Type: text/csv; charset=utf-8');
        header('Content-Disposition: attachment; filename="tgwatch-' . $name . '-' . date('Y-m-d') . '.csv"');
        $out = fopen('php://output', 'w');
        fwrite($out, "\xEF\xBB\xBF");
        fputcsv($out, $head, ';', '"', '');
        foreach ($rows as $r) {
            fputcsv($out, $r, ';', '"', '');
        }
        fclose($out);
    }

    // ---------------------------------------------------------------- okunamayanlar

    private function unparsedCount(): int
    {
        return (int)$this->db->one("SELECT COUNT(*) c FROM unparsed WHERE status = 'open'")['c'];
    }

    private function unparsedPage(): void
    {
        $rows = $this->db->all("SELECT * FROM unparsed WHERE status = 'open' ORDER BY id DESC LIMIT 100");
        $h = '<p class="muted lead">Servis duyurusuna benzeyen ama otomatik ayrıştırılamayan gönderiler: ID satırı yok, biçim farklı ya da liste bölünmüş olabilir. Metni düzeltip iş yap ya da yoksay.</p>';
        if (!$rows) {
            $this->render('Okunamayanlar', $h . Ui::empty('Okunamayan gönderi yok', 'Servis duyurusuna benzeyen her gönderi iş oluşturabildi.'), ['active' => 'unparsed']);
            return;
        }
        foreach ($rows as $r) {
            $h .= '<section class="card gap-top"><div class="card-h"><h3><a href="https://t.me/' . Ui::e($r['channel']) . '/' . (int)$r['msg_id'] . '" target="_blank" rel="noopener noreferrer">@' . Ui::e($r['channel']) . ' · ' . (int)$r['msg_id'] . '</a></h3>'
                . '<span class="muted">' . Ui::ago((int)$r['created_at']) . '</span></div>'
                . '<p class="lvl-warn">' . Ui::e($r['reason']) . '</p><div class="code"><pre>' . Ui::e(mb_strimwidth($r['text'], 0, 1200, '…')) . '</pre></div>'
                . '<div class="row gap-top">' . $this->ui->action('unparsed_fix', 'Düzeltip iş yap', ['id' => $r['id']], 'primary sm', 'edit')
                . $this->ui->action('unparsed_dismiss', 'Yoksay', ['id' => $r['id']], 'ghost sm', 'x') . '</div></section>';
        }
        $this->render('Okunamayanlar', $h, ['active' => 'unparsed', 'actions' => $this->ui->action('unparsed_dismiss', 'Hepsini yoksay', ['id' => 'all'], 'quiet', 'x', 'Listedeki tüm gönderiler yoksayılsın mı?')]);
    }

    // ---------------------------------------------------------------- bot komutları

    private function botCommandsPage(): void
    {
        $list = \TgWatch\BotRegistry::load($this->db);
        $master = \TgWatch\BotRegistry::masterOn($this->db);
        $actions = \TgWatch\BotRegistry::ACTIONS;
        if (isset($_SESSION['botcmds_draft'])) { // hatalı kayıttan sonra yazdıkların kaybolmasın
            $d = $_SESSION['botcmds_draft'];
            unset($_SESSION['botcmds_draft']);
            $master = $d['master'];
            $list = array_map(static fn($r) => ['name' => (string)($r['name'] ?? ''), 'action' => (string)($r['action'] ?? 'text'), 'enabled' => !empty($r['enabled']),
                'desc' => (string)($r['desc'] ?? ''), 'text' => (string)($r['text'] ?? ''), 'new' => !empty($r['new'])], $d['rows']);
        } else {
            for ($i = 0; $i < 3; $i++) { // yeni komut için boş satırlar
                $list[] = ['name' => '', 'action' => 'text', 'enabled' => true, 'desc' => '', 'text' => '', 'new' => true];
            }
        }
        $h = '<p class="muted lead">Telegram botuna yazılan komutların hangi işi yapacağını burada belirlersin. Komut adını değiştirebilir, aynı işe ikinci bir ad (takma ad) verebilir, istemediğini kapatabilir ve sabit cevaplı kendi komutunu ekleyebilirsin. Komutları yalnızca sen (owner) kullanabilirsin.</p>'
            . '<form method="post" action="?p=botcmds_save" class="stack">' . $this->ui->csrfField()
            . '<label class="check"><input type="checkbox" name="master" value="1"' . ($master ? ' checked' : '') . '><span><b>Bot komutları açık</b><br><span class="muted">Kapalıysa hiçbir komut çalışmaz (düğmeler ve /start, /id, /iptal etkilenmez)</span></span></label>'
            . '<div class="table-wrap edit-table labeled"><table><thead><tr><th>Açık</th><th>Komut</th><th>Ne yapsın</th><th>Menüde görünen açıklama</th><th>Sabit cevap metni</th><th>Sil</th></tr></thead><tbody>';
        foreach ($list as $i => $c) {
            $opts = '';
            foreach ($actions as $k => [$label]) {
                $opts .= '<option value="' . $k . '"' . ($c['action'] === $k ? ' selected' : '') . '>' . Ui::e($label) . '</option>';
            }
            $h .= '<tr><td class="tight" data-label="Açık"><input type="checkbox" name="c[' . $i . '][enabled]" value="1"' . ($c['enabled'] ? ' checked' : '') . ' aria-label="Komut açık"></td>'
                . '<td class="tight" data-label="Komut"><div class="row nowrap"><span class="muted">/</span><input type="text" name="c[' . $i . '][name]" value="' . Ui::e($c['name']) . '" placeholder="yeni_komut" maxlength="32" class="cmd-name" aria-label="Komut adı" autocapitalize="off"></div></td>'
                . '<td data-label="Ne yapsın"><select name="c[' . $i . '][action]" aria-label="Eylem">' . $opts . '</select></td>'
                . '<td data-label="Menüde görünen açıklama"><input type="text" name="c[' . $i . '][desc]" value="' . Ui::e($c['desc']) . '" maxlength="120" aria-label="Açıklama"></td>'
                . '<td data-label="Sabit cevap metni"><input type="text" name="c[' . $i . '][text]" value="' . Ui::e($c['text']) . '" maxlength="1500" placeholder="yalnızca “Sabit cevap” için" aria-label="Sabit cevap"></td>'
                . '<td class="tight" data-label="Sil">' . (empty($c['new']) ? '<input type="checkbox" name="c[' . $i . '][del]" value="1" aria-label="Sil">' : '<span class="muted">yeni</span><input type="hidden" name="c[' . $i . '][new]" value="1">') . '</td></tr>';
        }
        $h .= '</tbody></table></div>'
            . '<div class="row"><button class="btn primary">' . Ui::icon('check') . '<span>Kaydet ve menüyü güncelle</span></button></div>'
            . '<p class="muted">En alttaki boş satırlara yeni komut yazabilirsin. Komut adı küçük harf (a-z), rakam ve _ olmalı (Telegram kuralı); “özet” gibi Türkçe harfle yazarsan “ozet” komutuna denk gelir.</p></form>';

        $h .= '<div class="row">' . $this->ui->action('botcmds_menu', 'Yalnızca Telegram menüsünü güncelle', [], 'quiet', 'send')
            . $this->ui->action('botcmds_reset', 'Varsayılana döndür', [], 'ghost', 'refresh', 'Tüm komutlar varsayılana dönsün mü? Eklediğin komutlar silinir.') . '</div>';

        $h .= '<h2>Eylemler ne yapar</h2><div class="table-wrap"><table><thead><tr><th>Eylem</th><th>Yazım</th><th>Açıklama</th></tr></thead><tbody>';
        foreach ($actions as [$label, $hint, $what]) {
            $h .= '<tr><td class="tight"><b>' . Ui::e($label) . '</b></td><td class="tight">' . ($hint !== '' ? '<code>/komut ' . Ui::e($hint) . '</code>' : '<span class="muted">—</span>') . '</td><td>' . Ui::e($what) . '</td></tr>';
        }
        $h .= '</tbody></table></div><p class="muted">Sabit komutlar: <code>/start</code> (ID’ni gösterir), <code>/id</code>, <code>/iptal</code> (düzenlemeyi bırakır). Bunlar değiştirilemez.</p>';
        $this->render('Bot komutları', $h, ['active' => 'botcmds', 'sub' => 'Hangi komut hangi işi yapsın, panelden belirle.']);
    }

    // ---------------------------------------------------------------- dikkat gerekenler (Genel bakış)

    /** @return list<array{0:string,1:string,2:string,3:string}> [önem, metin, bağlantı, düğme] */
    private function attentionItems(): array
    {
        $a = [];
        $n = $this->unparsedCount();
        if ($n > 0) {
            $a[] = ['warn', $n . ' gönderi okunamadı, servis duyurusu olabilir', '?p=unparsed', 'İncele'];
        }
        $s = $this->db->one("SELECT COUNT(*) c FROM jobs WHERE status = 'approved' AND updated_at < ?", [time() - 24 * 3600]);
        if ($s && (int)$s['c'] > 0) {
            $a[] = ['warn', $s['c'] . ' onaylı iş 24 saatten uzun süredir panele eklenmeyi bekliyor', '?p=jobs&status=approved', 'Aç'];
        }
        $old = $this->db->one("SELECT COUNT(*) c FROM jobs WHERE status IN ('new','awaiting','editing') AND created_at < ?", [time() - 12 * 3600]);
        if ($old && (int)$old['c'] > 0) {
            $a[] = ['warn', $old['c'] . ' iş 12 saatten uzun süredir karar bekliyor', '?p=queue', 'Kuyruk'];
        }
        $gap = $this->db->one("SELECT COUNT(*) c FROM logs WHERE level = 'warn' AND source = 'channel' AND message LIKE '%atlandı%' AND ts > ?", [time() - 86400]);
        if ($gap && (int)$gap['c'] > 0) {
            $a[] = ['bad', 'Son 24 saatte kaçırılmış gönderi olabilir (cron durmuş)', '?p=logs&source=channel', 'Kayıtlar'];
        }
        $mon = \TgWatch\Monitor::problems($this->db);
        foreach (array_slice($mon, 0, 3) as $m) {
            $a[] = ['bad', $m, '?p=health', 'Sağlık'];
        }
        $cfg = $this->config;
        if ((string)($cfg['alert_email'] ?? '') === '' && (string)($cfg['monitor_key'] ?? '') === '') {
            $a[] = ['info', 'Telegram dışında ikinci bir uyarı yolu yok (e-posta ya da dış izleme)', '?p=settings&tab=telegram', 'Ayarla'];
        }
        return $a;
    }

    private function attentionStrip(): string
    {
        $items = $this->attentionItems();
        if (!$items) {
            return '';
        }
        $h = '<section class="attn" aria-label="Dikkat gerekenler"><h2 class="first">Dikkat gerekenler</h2><div class="attn-grid">';
        foreach ($items as [$sev, $text, $href, $btn]) {
            $h .= '<a class="attn-item ' . Ui::e($sev) . '" href="' . Ui::e($href) . '">' . Ui::icon($sev === 'info' ? 'bell' : 'alert', 18) . '<span>' . Ui::e($text) . '</span><b>' . Ui::e($btn) . ' →</b></a>';
        }
        return $h . '</div></section>';
    }

    // ---------------------------------------------------------------- POST

    private function deleteJob(int $id): string
    {
        $j = $this->db->one('SELECT status FROM jobs WHERE id = ?', [$id]);
        if (!$j) {
            return 'İş yok.';
        }
        if ($j['status'] === 'publishing') {
            return 'İş şu an yayınlanıyor, biraz sonra dene.';
        }
        $this->db->run('DELETE FROM jobs WHERE id = ?', [$id]);
        $this->db->log('info', 'web', "İş #$id silindi");
        return 'Silindi.';
    }

    /** @return bool işlendi mi */
    private function postTools(string $p): bool
    {
        switch ($p) {
            case 'job_create':
                $text = trim((string)($_POST['text'] ?? ''));
                $channel = preg_replace('/[^A-Za-z0-9_]/', '', (string)($_POST['channel'] ?? '')) ?: 'elle';
                if (!Parser::parse($text)) {
                    $_SESSION['job_new_text'] = $text;
                    $this->say('bad', 'Metinde servis bulunamadı. Her satır "🆔 ID Ad" biçiminde olmalı (ID 2–7 hane).');
                    $this->redirect('job_new');
                    return true;
                }
                $msgId = time();
                while ($this->db->one('SELECT 1 FROM jobs WHERE channel = ? AND msg_id = ?', [$channel, $msgId])) {
                    $msgId++;
                }
                $id = App::flow()->createJob($channel, $msgId, $text);
                if ($id === null) {
                    $this->say('bad', 'Bu metin bu kanalda daha önce işlenmiş.');
                    $this->redirect('jobs');
                } else {
                    $this->say('ok', "İş $id oluşturuldu ve bota gönderildi.");
                    $this->redirect('job', ['id' => $id]);
                }
                return true;
            case 'botcmds_save':
                $rows = [];
                foreach ((array)($_POST['c'] ?? []) as $r) {
                    if (is_array($r) && empty($r['del'])) {
                        $rows[] = $r;
                    }
                }
                $draft = ['master' => !empty($_POST['master']), 'rows' => $rows];
                $errors = \TgWatch\BotRegistry::save($this->db, $rows, !empty($_POST['master']));
                if ($errors) {
                    foreach ($errors as $e) {
                        $this->say('bad', $e);
                    }
                    $this->say('bad', 'Hata olduğu için kaydedilmedi. Yazdıkların aşağıda duruyor, düzeltip tekrar kaydet.');
                    $_SESSION['botcmds_draft'] = $draft;
                } else {
                    $this->db->log('info', 'web', 'Bot komutları kaydedildi');
                    try {
                        App::telegram()->setCommands(\TgWatch\BotRegistry::menu($this->db));
                        $this->say('ok', 'Komutlar kaydedildi ve Telegram menüsü güncellendi.');
                    } catch (\Throwable $e) {
                        $this->say('ok', 'Komutlar kaydedildi. Telegram menüsü güncellenemedi (bot ayarlarını kontrol edin); komutlar yine de çalışır.');
                    }
                }
                $this->redirect('botcmds');
                return true;
            case 'botcmds_menu':
                try {
                    App::telegram()->setCommands(\TgWatch\BotRegistry::menu($this->db));
                    $this->say('ok', 'Telegram menüsü güncellendi.');
                } catch (\Throwable $e) {
                    $this->say('bad', 'Güncellenemedi: ' . preg_replace('~bot\\d+:[A-Za-z0-9_-]+~', 'bot***', $e->getMessage()));
                }
                $this->redirect('botcmds');
                return true;
            case 'botcmds_reset':
                \TgWatch\BotRegistry::reset($this->db);
                $this->say('ok', 'Komutlar varsayılana döndü.');
                $this->redirect('botcmds');
                return true;
            case 'unparsed_fix':
                $r = $this->db->one('SELECT * FROM unparsed WHERE id = ?', [(int)($_POST['id'] ?? 0)]);
                if ($r) {
                    $_SESSION['job_new_text'] = $r['text'];
                    $this->db->run("UPDATE unparsed SET status = 'converted' WHERE id = ?", [(int)$r['id']]);
                }
                $this->redirect('job_new');
                return true;
            case 'unparsed_dismiss':
                if (($_POST['id'] ?? '') === 'all') {
                    $this->db->run("UPDATE unparsed SET status = 'dismissed' WHERE status = 'open'");
                } else {
                    $this->db->run("UPDATE unparsed SET status = 'dismissed' WHERE id = ?", [(int)($_POST['id'] ?? 0)]);
                }
                $this->redirect('unparsed');
                return true;
            case 'job_delete':
                $msg = $this->deleteJob((int)($_POST['id'] ?? 0));
                $this->say($msg === 'Silindi.' ? 'ok' : 'bad', $msg);
                $this->redirect('jobs');
                return true;
            case 'job_note':
                $id = (int)($_POST['id'] ?? 0);
                $this->db->run('UPDATE jobs SET note = ? WHERE id = ? AND status NOT IN (\'failed\')', [mb_substr(trim((string)($_POST['note'] ?? '')), 0, 300), $id]);
                $this->say('ok', 'Not kaydedildi.');
                $this->redirect('job', ['id' => $id]);
                return true;
        }
        return false;
    }
}
