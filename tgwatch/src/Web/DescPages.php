<?php
declare(strict_types=1);

namespace TgWatch\Web;

use TgWatch\DescLearn;
use TgWatch\PanelServices;

/** Çeviri → Açıklamalar: paneldeki Azerice açıklamalardan kural, şablon ve tutarsızlık öğrenme (Admin içinde kullanılır). */
trait DescPages
{
    private function descPage(): void
    {
        $L = new DescLearn($this->db, $this->config);
        $rep = $L->report();
        $ready = (new PanelServices($this->db, $this->config))->configured();
        $sub = 'Açıklaması olmayan servisleri gör, kategori seç, açıklama ürettir, onayla; Chrome eklentisi panele yazar.';
        $tab = in_array($_GET['t'] ?? '', ['empty', 'rules', 'tpl', 'issues', 'cats', 'gen'], true) ? (string)$_GET['t'] : 'empty';

        $h = '<div class="panel panel-pad stack"><p><b>Nasıl çalışır:</b> 1) <b>Panelden oku</b> — servisler ve açıklamaları Admin API ile okunur, açıklamalı servislerden senin yazım kalıpların öğrenilir. '
            . '2) <b>Açıklamasızlar</b> sekmesinde kategoriyi aç, servisleri seç, <b>Açıklama üret</b>. 3) <b>Öneriler</b>de oku, düzelt, onayla. 4) Chrome eklentisi bildirim gösterir, <b>Panele ekle</b> deyince yazar.</p>'
            . ($rep ? '<p class="muted">Son okuma: ' . Ui::ago((int)$rep['at']) . ' · dil: ' . Ui::e($rep['langs'] ? implode(', ', $rep['langs']) : 'varsayılan') . '. Panelde açıklama ekledin/sildiysen yeniden oku.</p>' : '')
            . '<div class="row">' . ($ready ? $this->ui->action('desc_learn_run', $rep ? 'Panelden yeniden oku' : 'Panelden oku', [], 'primary', 'play')
                : Ui::link('?p=settings&tab=publish', 'Panel API\'yi bağla', 'primary', 'link')) . '</div></div>';
        if (!$rep) {
            $this->render('Açıklamalar', $h . Ui::empty('Panel henüz okunmadı', 'Panelden oku\'ya bas: servisler ve açıklamaları okunur (3–4 bin serviste yaklaşık yarım dakika). Sonra açıklamasız kategoriler burada listelenir.'), ['active' => 'desc', 'sub' => $sub]);
            return;
        }
        $pct = static fn($a, $b) => $b ? round($a * 100 / $b) : 0;
        $h .= '<section class="strip" aria-label="Özet">'
            . '<div><div class="k">' . Ui::dot('ok') . 'Paneldeki servis</div><div class="v">' . (int)$rep['services'] . '</div></div>'
            . '<div><div class="k">' . Ui::dot('ok') . 'Açıklamalı</div><div class="v">' . (int)$rep['with_desc'] . ' <small class="muted">%' . $pct($rep['with_desc'], $rep['services']) . '</small></div></div>'
            . '<div><div class="k">' . Ui::dot($rep['empty'] ? 'warn' : 'ok') . 'Açıklamasız</div><div class="v"><a href="?p=desc&amp;t=empty">' . (int)$rep['empty'] . '</a></div></div>'
            . '<div><div class="k">' . Ui::dot($rep['conflicts'] ? 'bad' : 'ok') . 'Adıyla çelişen</div><div class="v"><a href="?p=desc&amp;t=issues">' . (int)$rep['conflicts'] . '</a>'
            . ($rep['missing'] ? ' <small class="muted">+ ' . (int)$rep['missing'] . ' eksik</small>' : '') . '</div></div></section>';

        $nProp = (int)($this->db->one("SELECT COUNT(*) c FROM desc_props WHERE status IN ('proposed','approved','failed')")['c'] ?? 0);
        $tabs = ['empty' => 'Açıklamasızlar (' . (int)$rep['empty'] . ')', 'gen' => 'Öneriler' . ($nProp ? ' (' . $nProp . ')' : ''), 'rules' => 'Kurallar (' . count($rep['rules']) . ')', 'tpl' => 'Şablonlar (' . count($rep['templates']) . ')', 'issues' => 'Tutarsızlıklar (' . count($rep['issues']) . ')', 'cats' => 'Kategori özeti'];
        $h .= '<nav class="tabs" aria-label="Bölüm">';
        foreach ($tabs as $k => $l) {
            $h .= '<a href="?p=desc&amp;t=' . $k . '"' . ($tab === $k ? ' aria-current="page"' : '') . '>' . Ui::e($l) . '</a>';
        }
        $h .= '</nav>';

        if ($tab === 'rules') {
            $h .= '<p class="muted">Servis adında bir bilgi varken (ör. 30 gün garanti) açıklamada o bilginin hangi kalıpla yazıldığı. <code>{v}</code> addaki değerin yerine geçer, <code>#</code> başka sayılar. Oran: o bilgiyi açıklamasında yazan servislerden kaçı bu kalıbı kullanıyor.</p>';
            if ($rep['rules']) {
                $h .= '<div class="table-wrap"><table><thead><tr><th>Addaki bilgi</th><th>Açıklamadaki kalıp</th><th class="num">Servis</th><th>Örnek satır</th></tr></thead><tbody>';
                foreach ($rep['rules'] as $r) {
                    $h .= '<tr><td><b>' . Ui::e($r['label']) . '</b></td><td><code>' . Ui::e($r['tpl']) . '</code>'
                        . ($r['alts'] ? '<div class="src">Diğer: ' . Ui::e(implode(' · ', $r['alts'])) . '</div>' : '') . '</td>'
                        . '<td class="num tight">' . (int)$r['n'] . ' / ' . (int)$r['total'] . ' <span class="muted">%' . $pct($r['n'], $r['total']) . '</span></td>'
                        . '<td class="src">' . Ui::e($r['example']) . '</td></tr>';
                }
                $h .= '</tbody></table></div>';
            } else {
                $h .= Ui::empty('Kural çıkmadı', 'Açıklamalarda adlardaki bilgiler (garanti, maks, hız) bulunamadı.');
            }
            if ($rep['phrases']) {
                $h .= '<h2>Sık ifadeler</h2><p class="muted">Birçok açıklamada aynen geçen satırlar (sayılar #).</p><div class="table-wrap"><table><thead><tr><th>Satır</th><th class="num">Açıklama</th></tr></thead><tbody>';
                foreach ($rep['phrases'] as $p) {
                    $h .= '<tr><td>' . Ui::e($p['shape']) . '</td><td class="num tight">' . (int)$p['n'] . '</td></tr>';
                }
                $h .= '</tbody></table></div>';
            }
        } elseif ($tab === 'tpl') {
            $h .= '<p class="muted">Platform × tür grubu başına, açıklamaların en az %35\'inde geçen satırlar ortalama sıralarıyla. Altında bu yapıya en çok uyan gerçek açıklama.</p>';
            foreach ($rep['templates'] as $t) {
                $h .= '<details class="panel panel-pad gap-top"><summary><b>' . Ui::e(DescLearn::pname($t['platform'])) . ' · ' . Ui::e(DescLearn::TYPES[$t['type']][1] ?? $t['type']) . '</b> <span class="muted">— ' . (int)$t['n'] . ' açıklama, ' . count($t['lines']) . ' satırlık şablon</span></summary>'
                    . '<div class="table-wrap"><table><thead><tr><th>Şablon satırı</th><th class="num">Görülme</th></tr></thead><tbody>';
                foreach ($t['lines'] as $l) {
                    $h .= '<tr><td>' . Ui::e($l['shape']) . '</td><td class="num tight">%' . round($l['share'] * 100) . '</td></tr>';
                }
                $h .= '</tbody></table></div><p class="muted gap-top">Örnek açıklama:</p><pre class="src">' . Ui::e(implode("\n", $t['example'])) . '</pre></details>';
            }
            if (!$rep['templates']) {
                $h .= Ui::empty('Şablon çıkmadı', 'Bir şablon için aynı platform ve türde en az 3 açıklama gerekir.');
            }
        } elseif ($tab === 'issues') {
            $kind = in_array($_GET['k'] ?? '', ['conflict', 'missing'], true) ? (string)$_GET['k'] : 'conflict';
            $list = array_values(array_filter($rep['issues'], static fn($i) => $i['kind'] === $kind));
            $h .= '<nav class="tabs" aria-label="Tür"><a href="?p=desc&amp;t=issues&amp;k=conflict"' . ($kind === 'conflict' ? ' aria-current="page"' : '') . '>Çelişen</a>'
                . '<a href="?p=desc&amp;t=issues&amp;k=missing"' . ($kind === 'missing' ? ' aria-current="page"' : '') . '>Eksik (adında var, açıklamada yok)</a></nav>';
            if ($list) {
                $h .= '<div class="table-wrap"><table><thead><tr><th>Servis</th><th>Sorun</th><th>Açıklamadaki satır</th></tr></thead><tbody>';
                foreach (array_slice($list, 0, 200) as $i) {
                    $h .= '<tr><td><b>' . (int)$i['id'] . '</b> ' . Ui::e(mb_substr($i['name'], 0, 80)) . '<div class="src">' . Ui::e(mb_substr($i['category'], 0, 50)) . ($i['status'] !== 'enabled' ? ' · kapalı' : '') . '</div></td>'
                        . '<td class="' . ($kind === 'conflict' ? 'lvl-error' : 'lvl-warn') . '">' . Ui::e($i['msg']) . '</td><td class="src">' . Ui::e(mb_substr($i['line'], 0, 120)) . '</td></tr>';
                }
                $h .= '</tbody></table></div>' . (count($list) > 200 ? '<p class="muted">İlk 200 gösterildi.</p>' : '');
            } else {
                $h .= Ui::empty($kind === 'conflict' ? 'Çelişen açıklama yok' : 'Eksik bilgi yok', 'Adlarla açıklamalar uyumlu.');
            }
        } elseif ($tab === 'cats') {
            $q = trim((string)($_GET['q'] ?? ''));
            $cats = array_values(array_filter($rep['categories'], static fn($c) => $q === '' || mb_stripos($c['name'], $q) !== false));
            $h .= '<form method="get" class="toolbar"><input type="hidden" name="p" value="desc"><input type="hidden" name="t" value="cats">'
                . '<label class="grow">Kategori ara<input type="search" name="q" value="' . Ui::e($q) . '" placeholder="Instagram, TikTok…"></label>'
                . '<button class="btn quiet">' . Ui::icon('search') . '<span>Ara</span></button></form>'
                . '<p class="muted">En çok açıklamasız servisi olandan başlar. “Açıklama üret” o kategorideki açıklamasız servislere öneri hazırlar (panele bir şey yazmaz; önce sen onaylarsın).</p>';
            $h .= '<div class="table-wrap"><table><thead><tr><th>Kategori</th><th class="num">Servis</th><th class="num">Açıklamasız</th><th class="num">Çelişen</th><th class="num">Eksik</th><th></th></tr></thead><tbody>';
            foreach (array_slice($cats, 0, 300) as $c) {
                $h .= '<tr><td><b>' . Ui::e($c['name']) . '</b><div class="src">#' . (int)$c['id'] . ' · ' . (int)$c['on'] . ' açık</div></td><td class="num tight">' . (int)$c['n'] . '</td>'
                    . '<td class="num tight">' . ($c['empty'] ? '<b>' . (int)$c['empty'] . '</b>' : '<span class="muted">0</span>') . '</td>'
                    . '<td class="num tight' . ($c['conflict'] ? ' lvl-error' : '') . '">' . (int)$c['conflict'] . '</td><td class="num tight">' . (int)$c['missing'] . '</td>'
                    . '<td class="tight">' . ($c['empty'] ? $this->ui->action('desc_gen', 'Açıklama üret (' . (int)$c['empty'] . ')', ['cat' => $c['id']], 'quiet sm', 'play') : '') . '</td></tr>';
            }
            $h .= '</tbody></table></div>' . (count($cats) > 300 ? '<p class="muted">İlk 300 kategori gösterildi; aramayla daralt.</p>' : '');
        }
        if ($tab === 'gen') {
            $h .= $this->descProposals();
        } elseif ($tab === 'empty') {
            $h .= $this->descEmpty();
        }
        $this->render('Açıklamalar', $h, ['active' => 'desc', 'sub' => $sub]);
    }

    /** Açıklamasız servisler, sitedeki kategori sırasıyla: kategoriye tıkla → servisler açılır → servis başına üret / düzelt / onayla. */
    private function descEmpty(): string
    {
        $q = trim((string)($_GET['q'] ?? ''));
        $open = (int)($_GET['c'] ?? 0);
        $E = (new \TgWatch\DescGen($this->db))->emptyCategories();
        $cats = $E['cats'];
        if ($q !== '') {
            $cats = array_values(array_filter($cats, static fn($c) => mb_stripos((string)$c['category_name'], $q) !== false || (string)$c['category_id'] === $q));
        }
        $h = '<form method="get" class="toolbar"><input type="hidden" name="p" value="desc"><input type="hidden" name="t" value="empty">'
            . '<label class="grow">Kategori ara<input type="search" name="q" value="' . Ui::e($q) . '" placeholder="Instagram, TikTok… ya da kategori ID"></label>'
            . '<button class="btn">' . Ui::icon('search') . '<span>Ara</span></button></form>';
        if (!$cats) {
            return $h . Ui::empty($q !== '' ? 'Eşleşen kategori yok' : 'Açıklamasız servis yok', $q !== '' ? 'Başka bir ad dene.' : 'Son okumaya göre bütün servislerin açıklaması var.');
        }
        $h .= '<p class="muted">' . count($cats) . ' kategoride açıklamasız servis var. Sıra: '
            . ($E['source'] === 'site' ? '<b>sitedeki gibi</b> (' . Ui::e($E['from']) . ' ' . Ui::ago($E['site_at']) . ' okudu)' : '<b>Admin API listesindeki gibi</b> — sitedeki sıra henüz okunamadı: “Panelden yeniden oku”ya bas ya da panelde Services sayfasını bir kez aç (eklenti okur)')
            . '. Kategoriye tıkla, servisleri açılır; tek servise ya da seçtiklerine açıklama üret, metni düzelt, onayla.</p>';
        $label = ['proposed' => ['öneri hazır', 'lvl-warn'], 'approved' => ['onaylı, eklentiyi bekliyor', 'lvl-good'], 'applied' => ['panele yazıldı', 'lvl-good'], 'verified' => ['panele yazıldı ✓', 'lvl-good'], 'failed' => ['hatalı', 'lvl-error'], 'rejected' => ['reddedildi', 'muted']];
        $ids = array_map(static fn($c) => (int)$c['category_id'], $cats);
        $byCat = [];
        foreach (array_chunk($ids, 400) as $chunk) {
            foreach ($this->db->all('SELECT d.id, d.name, d.status, d.category_id, p.status pst, p.text, p.error, p.notes FROM panel_desc d LEFT JOIN desc_props p ON p.sid = d.id
                WHERE d.description = \'\' AND d.category_id IN (' . implode(',', $chunk) . ') ORDER BY d.pos, d.id') as $r) {
                $byCat[(int)$r['category_id']][] = $r;
            }
        }
        foreach ($cats as $c) {
            $cid = (int)$c['category_id'];
            $svcs = $byCat[$cid] ?? [];
            $nProp = count(array_filter($svcs, static fn($r) => $r['pst'] !== null));
            $nWait = count(array_filter($svcs, static fn($r) => $r['pst'] === 'approved'));
            $h .= '<details class="panel panel-pad gap-top desc-cat" id="c' . $cid . '"' . ($cid === $open || count($cats) === 1 ? ' open' : '') . '><summary><b>' . Ui::e($c['category_name']) . '</b> <span class="muted">#' . $cid . ' · ' . (int)$c['e'] . ' açıklamasız / ' . (int)$c['n'] . ' servis'
                . ((int)$c['eon'] < (int)$c['e'] ? ' · ' . ((int)$c['e'] - (int)$c['eon']) . ' kapalı' : '') . ($nProp ? ' · ' . $nProp . ' öneri' : '') . '</span></summary>'
                . '<form method="post" action="?p=desc_gen" class="stack gap-top" data-selbar>' . $this->ui->csrfField() . '<input type="hidden" name="cat" value="' . $cid . '"><input type="hidden" name="back" value="empty">'
                . '<div class="row desc-selbar">'
                . '<label class="row desc-all"><input type="checkbox" data-checkall="form"><span>Hepsini seç</span></label>'
                . '<span class="muted" data-selcount>Seçim yok</span>'
                . '<button class="btn sm" data-selbtn="Seçilenlere açıklama üret" disabled>' . Ui::icon('play') . '<span>Seçilenlere açıklama üret</span></button>'
                . ($nProp ? '<button class="btn primary sm" data-selbtn="Seçilenleri onayla" formaction="?p=desc_ok_sel" disabled>' . Ui::icon('check') . '<span>Seçilenleri onayla</span></button>' : '')
                . ($nProp ? '<button class="btn ghost sm" data-selbtn="Seçilenlerin önerisini sil" formaction="?p=desc_del_sel" data-confirm="Seçilen servislerin önerileri silinsin mi? (Panele yazılmış olanlar silinmez.)" disabled>' . Ui::icon('x') . '<span>Seçilenlerin önerisini sil</span></button>' : '')
                . ($nWait ? '<span class="lvl-good">' . $nWait . ' onaylı: eklenti panele yazacak</span>' : '')
                . '<button class="btn ghost sm" name="all" value="1" formaction="?p=desc_gen">' . Ui::icon('play') . '<span>Bu kategorideki hepsine üret (' . (int)$c['e'] . ')</span></button></div>'
                . '<div class="table-wrap"><table><tbody>';
            foreach ($svcs as $sv) {
                $sid = (int)$sv['id'];
                $pst = $sv['pst'];
                $st = $label[$pst ?? ''] ?? null;
                $locked = in_array($pst, ['applied', 'verified'], true);
                $h .= '<tr id="s' . $sid . '"><td class="tight"><input type="checkbox" name="sids[]" value="' . $sid . '"' . ($locked ? ' disabled' : '') . ' aria-label="Seç ' . $sid . '"></td><td><div class="row desc-svc">'
                    . '<span><b>' . $sid . '</b> ' . Ui::e($sv['name']) . ($sv['status'] !== 'enabled' ? ' <span class="muted">· kapalı</span>' : '') . '</span>'
                    . '<span>' . ($st ? '<span class="' . $st[1] . '">' . $st[0] . '</span> ' : '')
                    . (!$locked ? '<button class="btn quiet sm" formaction="?p=desc_gen" name="one" value="' . $sid . '">' . Ui::icon('play') . '<span>' . ($pst ? 'Yeniden üret' : 'Açıklama üret') . '</span></button>' : '')
                    . ($pst !== null && !$locked ? '<button class="btn ghost sm" formaction="?p=desc_del" name="sid" value="' . $sid . '" title="Öneriyi sil (panele yazılmaz)">' . Ui::icon('x') . '<span>Sil</span></button>' : '') . '</span></div>';
                if ($pst === 'approved') {
                    $h .= '<pre class="src">' . Ui::e($sv['text']) . '</pre><div class="row"><button class="btn ghost sm" formaction="?p=desc_undo" name="sid" value="' . $sid . '">' . Ui::icon('x') . '<span>Onayı geri al (düzeltmek için)</span></button></div>';
                } elseif ($pst !== null && !$locked) {
                    $warn = array_filter(json_decode((string)$sv['notes'], true) ?: [], static fn($n) => str_starts_with($n, '⚠'));
                    $h .= ($warn ? '<p class="lvl-error">' . Ui::e(implode(' · ', $warn)) . '</p>' : '') . ($sv['error'] !== '' ? '<p class="lvl-error">' . Ui::e($sv['error']) . '</p>' : '')
                        . '<textarea name="text[' . $sid . ']" rows="' . max(3, min(14, substr_count((string)$sv['text'], "\n") + 2)) . '" aria-label="Açıklama ' . $sid . '">' . Ui::e($sv['text']) . '</textarea>'
                        . '<div class="row"><button class="btn primary sm" formaction="?p=desc_ok" name="sid" value="' . $sid . '">' . Ui::icon('check') . '<span>Kaydet ve onayla</span></button>'
                        . '<button class="btn ghost sm" formaction="?p=desc_save" name="sid" value="' . $sid . '"><span>Yalnızca kaydet</span></button>'
                        . ($pst !== 'rejected' ? '<button class="btn ghost sm" formaction="?p=desc_reject" name="sid" value="' . $sid . '">' . Ui::icon('x') . '<span>Reddet</span></button>' : '') . '</div>';
                } elseif ($locked) {
                    $h .= '<pre class="src">' . Ui::e($sv['text']) . '</pre>'
                        . ($pst === 'approved' ? '<div class="row"><button class="btn ghost sm" formaction="?p=desc_undo" name="sid" value="' . $sid . '">' . Ui::icon('x') . '<span>Onayı geri al (düzeltmek için)</span></button></div>' : '');
                }
                $h .= '</td></tr>';
            }
            $h .= '</tbody></table></div><p class="muted">Panele hiçbir şey yazılmaz; üretilenler burada kutuda çıkar, sen onaylarsın.</p></form></details>';
        }
        return $h;
    }

    /** İşlemden sonra: Açıklamasızlar'da aynı kategori açık, aynı servise kaydırılmış. */
    private function descBack(string $tab, array $q): void
    {
        if (($_POST['back'] ?? '') === 'empty') {
            $this->redirect('desc', ['t' => 'empty', 'c' => (int)($_POST['cat'] ?? 0), 's' => (int)($_POST['sid'] ?? $_POST['one'] ?? 0)]);
            return;
        }
        $this->redirect('desc', ['t' => $tab] + $q);
    }

    private function descProposals(): string
    {
        $views = ['proposed' => 'Onay bekleyen', 'approved' => 'Onaylı (eklentiyi bekliyor)', 'done' => 'Panele yazıldı', 'failed' => 'Hatalı', 'rejected' => 'Reddedilen'];
        $v = isset($views[$_GET['v'] ?? '']) ? (string)$_GET['v'] : 'proposed';
        $cat = (int)($_GET['c'] ?? 0);
        $statusSql = $v === 'done' ? "status IN ('applied','verified')" : 'status = ?';
        $args = $v === 'done' ? [] : [$v];
        $counts = [];
        foreach ($this->db->all('SELECT status, COUNT(*) n FROM desc_props' . ($cat ? ' WHERE category_id = ' . $cat : '') . ' GROUP BY status') as $r) {
            $k = in_array($r['status'], ['applied', 'verified'], true) ? 'done' : $r['status'];
            $counts[$k] = ($counts[$k] ?? 0) + (int)$r['n'];
        }
        $h = '';
        $waiting = (int)($this->db->one("SELECT COUNT(*) c FROM desc_props WHERE status = 'approved'")['c'] ?? 0);
        if ($waiting) {
            $h .= '<div class="panel panel-pad gap-top"><b>' . $waiting . ' onaylı açıklama</b> panele yazılmayı bekliyor. Chrome eklentisi birkaç dakika içinde bildirim gösterir: <b>Panele ekle</b>\'ye bas (ya da eklenti simgesi → Panel → Açıklamaları panele ekle). '
                . 'Eklenti her servisi listede bulur, Edit description\'a yazar ve kaydeder; tgwatch sonra Admin API ile panelden okuyup doğrular.</div>';
        }
        $cats = $this->db->all('SELECT category_id, category_name, COUNT(*) n FROM desc_props GROUP BY category_id ORDER BY MAX(updated_at) DESC LIMIT 40');
        if ($cats) {
            $h .= '<form method="get" class="toolbar"><input type="hidden" name="p" value="desc"><input type="hidden" name="t" value="gen"><input type="hidden" name="v" value="' . Ui::e($v) . '">'
                . '<label class="grow">Kategori<select name="c" onchange="this.form.submit()"><option value="0">Hepsi</option>';
            foreach ($cats as $c) {
                $h .= '<option value="' . (int)$c['category_id'] . '"' . ($cat === (int)$c['category_id'] ? ' selected' : '') . '>' . Ui::e($c['category_name']) . ' (' . (int)$c['n'] . ')</option>';
            }
            $h .= '</select></label></form>';
        }
        $h .= '<nav class="tabs" aria-label="Durum">';
        foreach ($views as $k => $l) {
            $h .= '<a href="?p=desc&amp;t=gen&amp;v=' . $k . ($cat ? '&amp;c=' . $cat : '') . '"' . ($v === $k ? ' aria-current="page"' : '') . '>' . Ui::e($l) . (!empty($counts[$k]) ? ' (' . $counts[$k] . ')' : '') . '</a>';
        }
        $h .= '</nav>';
        $rows = $this->db->all('SELECT * FROM desc_props WHERE ' . $statusSql . ($cat ? ' AND category_id = ' . $cat : '') . ' ORDER BY category_id, sid LIMIT 200', $args);
        if (!$rows) {
            return $h . Ui::empty('Burada öneri yok', $v === 'proposed' ? 'Kategoriler sekmesinden bir kategoride “Açıklama üret”e bas.' : 'Başka bir sekmeye bak.');
        }
        if ($v === 'proposed') {
            $h .= '<div class="row gap-top">' . $this->ui->action('desc_ok_all', 'Listedekilerin hepsini onayla (' . count($rows) . ')', ['c' => $cat], 'primary', 'check', count($rows) . ' açıklama onaylansın mı? Onaylananları eklenti panele yazar.')
                . '<span class="muted">Önce birkaçını oku; metni kutuda düzeltip tek tek de onaylayabilirsin.</span></div>';
        }
        foreach ($rows as $r) {
            $notes = json_decode((string)$r['notes'], true) ?: [];
            $warn = array_filter($notes, static fn($n) => str_starts_with($n, '⚠'));
            $h .= '<div class="panel panel-pad stack gap-top"><div><b>' . (int)$r['sid'] . '</b> ' . Ui::e($r['name']) . '<div class="src">' . Ui::e($r['category_name']) . ' · temel: ' . Ui::e($r['base']) . '</div></div>';
            if ($warn) {
                $h .= '<p class="lvl-error">' . Ui::e(implode(' · ', $warn)) . '</p>';
            }
            if ($v === 'proposed' || $v === 'failed' || $v === 'rejected') {
                $h .= '<form method="post" action="?p=desc_ok" class="stack">' . $this->ui->csrfField() . '<input type="hidden" name="sid" value="' . (int)$r['sid'] . '"><input type="hidden" name="c" value="' . $cat . '">'
                    . '<textarea name="text" rows="' . max(3, min(14, substr_count((string)$r['text'], "\n") + 2)) . '" aria-label="Açıklama">' . Ui::e($r['text']) . '</textarea>'
                    . ($r['error'] !== '' ? '<p class="lvl-error">' . Ui::e($r['error']) . '</p>' : '')
                    . '<div class="row"><button class="btn primary sm">' . Ui::icon('check') . '<span>' . ($v === 'failed' ? 'Tekrar onayla' : 'Onayla') . '</span></button>'
                    . ($v === 'proposed' ? '</div></form>' . $this->ui->action('desc_reject', 'Reddet', ['sid' => $r['sid'], 'c' => $cat], 'ghost sm', 'x') : '</div></form>');
            } else {
                $h .= '<pre class="src">' . Ui::e($r['text']) . '</pre>'
                    . ($r['error'] !== '' ? '<p class="lvl-warn">Son deneme: ' . Ui::e($r['error']) . ' (' . (int)$r['attempts'] . '/3)</p>' : '')
                    . ($v === 'approved' ? '<div class="row">' . $this->ui->action('desc_undo', 'Onayı geri al', ['sid' => $r['sid'], 'c' => $cat], 'ghost sm', 'x') . '</div>' : '')
                    . ($r['status'] === 'verified' ? '<p class="lvl-good">✓ Panelden okunup doğrulandı</p>' : ($r['status'] === 'applied' ? '<p class="muted">Eklenti yazdı (panel API bağlı değilse doğrulanamaz)</p>' : ''));
            }
            if ($notes) {
                $h .= '<details><summary class="muted">Nasıl üretildi (' . count($notes) . ')</summary><ul class="muted">' . implode('', array_map(static fn($n) => '<li>' . Ui::e($n) . '</li>', $notes)) . '</ul></details>';
            }
            $h .= '</div>';
        }
        return $h;
    }

    private function postDesc(string $p): bool
    {
        $G = new \TgWatch\DescGen($this->db, $this->config);
        $cat = (int)($_POST['c'] ?? 0);
        switch ($p) {
            case 'desc_gen':
                $c = (int)($_POST['cat'] ?? 0);
                $sids = !empty($_POST['one']) ? [(int)$_POST['one']] : (!empty($_POST['all']) ? null : (isset($_POST['sids']) && is_array($_POST['sids']) ? array_map('intval', $_POST['sids']) : (($_POST['back'] ?? '') === 'empty' ? [] : null)));
                if ($sids === [] && ($_POST['back'] ?? '') === 'empty') {
                    $this->say('bad', 'Hiç servis seçilmedi: servisleri işaretle ya da servisin yanındaki “Açıklama üret”e bas.');
                    $this->descBack('empty', ['c' => $c]);
                    return true;
                }
                try {
                    if ($sids !== null && count($sids) === 1) {
                        $G->setStatus($sids, 'proposed'); // reddedilmiş/hatalı olsa da yeniden üretilebilsin
                    }
                    $r = $G->generate($c, $sids, false, true); // elle: onaylı (yazılmamış) öneri de yenilenir
                    $this->say('ok', $r['made'] . ' öneri hazır' . ($r['skipped'] ? ', ' . $r['skipped'] . ' serviste kullanılabilir bilgi yok' : '') . ($r['kept'] ? ', ' . $r['kept'] . ' onaylı öneri korundu' : '') . '. Metni aşağıda düzeltip onaylayabilirsin.');
                } catch (\Throwable $e) {
                    $this->say('bad', $e->getMessage());
                }
                if (($_POST['back'] ?? '') === 'empty') {
                    $this->redirect('desc', ['t' => 'empty', 'c' => $c, 's' => $sids && count($sids) === 1 ? $sids[0] : 0]);
                } else {
                    $this->redirect('desc', ['t' => 'gen', 'v' => 'proposed', 'c' => $c]);
                }
                return true;
            case 'desc_ok':
            case 'desc_save':
                $sid = (int)($_POST['sid'] ?? 0);
                $text = $_POST['text'] ?? '';
                $text = is_array($text) ? (string)($text[$sid] ?? '') : (string)$text;
                $text = trim(str_replace(["\r\n", "\r"], "\n", $text));
                if ($text === '' || mb_strlen($text) > 5000) {
                    $this->say('bad', 'Açıklama boş olamaz (en çok 5000 karakter).');
                } elseif ($p === 'desc_save') {
                    $G->setStatus([$sid], 'proposed', $text);
                    $this->say('ok', 'Kaydedildi (henüz onaylanmadı).');
                } else {
                    $G->setStatus([$sid], 'approved', $text);
                    $this->say('ok', 'Onaylandı: eklenti panele yazacak.');
                }
                $this->descBack('gen', ['v' => 'proposed', 'c' => $cat]);
                return true;
            case 'desc_ok_sel':
                // Seçilenleri onayla: kutudaki (düzeltilmiş) metinle; önerisi olmayan seçilmişler atlanır
                $texts = is_array($_POST['text'] ?? null) ? $_POST['text'] : [];
                $n = $skip = 0;
                foreach (isset($_POST['sids']) && is_array($_POST['sids']) ? array_map('intval', $_POST['sids']) : [] as $sid) {
                    $row = $this->db->one("SELECT text FROM desc_props WHERE sid = ? AND status IN ('proposed','failed','rejected')", [$sid]);
                    $t = trim(str_replace(["\r\n", "\r"], "\n", (string)($texts[$sid] ?? ($row['text'] ?? ''))));
                    if (!$row || $t === '' || mb_strlen($t) > 5000) {
                        $skip++;
                        continue;
                    }
                    $n += $G->setStatus([$sid], 'approved', $t);
                }
                $this->say($n ? 'ok' : 'bad', $n ? "$n açıklama onaylandı: Chrome eklentisi birkaç dakika içinde “Panele ekle” bildirimi gösterir." . ($skip ? " ($skip seçili serviste önce açıklama üret.)" : '') : 'Onaylanacak öneri yok: önce seçilenlere açıklama üret.');
                $this->descBack('gen', ['v' => 'approved', 'c' => $cat]);
                return true;
            case 'desc_del':
            case 'desc_del_sel':
                $ids = $p === 'desc_del' ? [(int)($_POST['sid'] ?? 0)] : (isset($_POST['sids']) && is_array($_POST['sids']) ? array_map('intval', $_POST['sids']) : []);
                $n = $G->delete($ids);
                $this->say($n ? 'ok' : 'bad', $n ? "$n öneri silindi: servis yine “açıklama yok”, istersen yeniden üret." : 'Silinecek öneri yok (panele yazılmış olanlar silinmez).');
                $this->descBack('gen', ['v' => 'proposed', 'c' => $cat]);
                return true;
            case 'desc_ok_all':
                $ids = array_column($this->db->all("SELECT sid FROM desc_props WHERE status = 'proposed' AND text != ''" . ($cat ? ' AND category_id = ' . $cat : '') . ' ORDER BY category_id, sid LIMIT 200'), 'sid');
                $n = $G->setStatus($ids, 'approved');
                $this->say('ok', "$n açıklama onaylandı: eklenti panele yazacak.");
                $this->redirect('desc', ['t' => 'gen', 'v' => 'approved', 'c' => $cat]);
                return true;
            case 'desc_reject':
            case 'desc_undo':
                $G->setStatus([(int)($_POST['sid'] ?? 0)], $p === 'desc_reject' ? 'rejected' : 'proposed');
                $this->say('ok', $p === 'desc_reject' ? 'Reddedildi.' : 'Onay geri alındı: metni düzeltebilirsin.');
                $this->descBack($p === 'desc_reject' ? 'gen' : 'gen', ['v' => $p === 'desc_reject' ? 'proposed' : 'approved', 'c' => $cat]);
                return true;
        }
        if ($p !== 'desc_learn_run') {
            return false;
        }
        try {
            $r = (new DescLearn($this->db, $this->config))->run();
            $this->say('ok', "Öğrenildi: {$r['services']} servis, {$r['with_desc']} açıklamalı, {$r['empty']} açıklamasız; " . count($r['rules']) . ' kural, ' . count($r['templates']) . " şablon, {$r['conflicts']} çelişki.");
        } catch (\Throwable $e) {
            $this->say('bad', 'Panelden okunamadı: ' . $e->getMessage());
        }
        $this->redirect('desc');
        return true;
    }
}
