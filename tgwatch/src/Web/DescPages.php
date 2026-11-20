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
        $sub = 'Paneldeki Azerice açıklamalardan senin üslubunu ve kurallarını öğrenir. Panelde hiçbir şeyi değiştirmez.';
        $tab = in_array($_GET['t'] ?? '', ['rules', 'tpl', 'issues', 'cats'], true) ? (string)$_GET['t'] : 'rules';

        $h = '<div class="panel panel-pad stack"><p class="muted">Admin API ile panelindeki bütün açıklamalar okunur. Öğrenilenler: '
            . '<b>addaki bilgi → açıklama satırı</b> kuralları (adında “30 Gün Qarantili” olan servislerin açıklamasında 30\'un geçtiği satır, garanti satırının kalıbı olur), '
            . 'platform × tür <b>şablonları</b>, <b>sık ifadeler</b> ve adıyla <b>çelişen</b> açıklamalar. Bunlar sonraki adımda açıklamasız servislere öneri üretmek için kullanılacak.</p>'
            . ($rep ? '<dl class="kv"><dt>Son öğrenme</dt><dd>' . Ui::ago((int)$rep['at']) . ' · ' . round(((int)$rep['ms']) / 1000, 1) . ' sn</dd>'
                . '<dt>Panelde açık diller</dt><dd>' . Ui::e($rep['langs'] ? implode(', ', $rep['langs']) : 'varsayılan dil') . (count($rep['langs']) <= 1 ? ' <span class="muted">— İngilizce sürüm olmadığı için çeviri çifti değil, üslup ve kurallar öğrenilir</span>' : '') . '</dd>'
                . '<dt>Açıklama çeviri çifti (bankada)</dt><dd>' . (int)$rep['pairs'] . ' <span class="muted">(sağlayıcı açıklamalarından ve eklentiyle import\'tan)</span></dd></dl>' : '')
            . '<div class="row">' . ($ready ? $this->ui->action('desc_learn_run', $rep ? 'Panelden yeniden öğren' : 'Panelden öğren', [], 'primary', 'play')
                : Ui::link('?p=settings&tab=publish', 'Panel API\'yi bağla', 'primary', 'link')) . '</div></div>';
        if (!$rep) {
            $this->render('Açıklamalar', $h . Ui::empty('Henüz öğrenilmedi', 'Panelden öğren\'e bas: açıklamalar okunur (3–4 bin serviste yaklaşık yarım dakika).'), ['active' => 'desc', 'sub' => $sub]);
            return;
        }
        $pct = static fn($a, $b) => $b ? round($a * 100 / $b) : 0;
        $h .= '<section class="strip" aria-label="Özet">'
            . '<div><div class="k">' . Ui::dot('ok') . 'Paneldeki servis</div><div class="v">' . (int)$rep['services'] . '</div></div>'
            . '<div><div class="k">' . Ui::dot('ok') . 'Açıklamalı</div><div class="v">' . (int)$rep['with_desc'] . ' <small class="muted">%' . $pct($rep['with_desc'], $rep['services']) . '</small></div></div>'
            . '<div><div class="k">' . Ui::dot($rep['empty'] ? 'warn' : 'ok') . 'Açıklamasız</div><div class="v"><a href="?p=desc&amp;t=cats">' . (int)$rep['empty'] . '</a></div></div>'
            . '<div><div class="k">' . Ui::dot($rep['conflicts'] ? 'bad' : 'ok') . 'Adıyla çelişen</div><div class="v"><a href="?p=desc&amp;t=issues">' . (int)$rep['conflicts'] . '</a>'
            . ($rep['missing'] ? ' <small class="muted">+ ' . (int)$rep['missing'] . ' eksik</small>' : '') . '</div></div></section>';

        $tabs = ['rules' => 'Kurallar (' . count($rep['rules']) . ')', 'tpl' => 'Şablonlar (' . count($rep['templates']) . ')', 'issues' => 'Tutarsızlıklar (' . count($rep['issues']) . ')', 'cats' => 'Kategoriler'];
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
        } else {
            $q = trim((string)($_GET['q'] ?? ''));
            $cats = array_values(array_filter($rep['categories'], static fn($c) => $q === '' || mb_stripos($c['name'], $q) !== false));
            $h .= '<form method="get" class="toolbar"><input type="hidden" name="p" value="desc"><input type="hidden" name="t" value="cats">'
                . '<label class="grow">Kategori ara<input type="search" name="q" value="' . Ui::e($q) . '" placeholder="Instagram, TikTok…"></label>'
                . '<button class="btn quiet">' . Ui::icon('search') . '<span>Ara</span></button></form>'
                . '<p class="muted">En çok açıklamasız servisi olandan başlar. Sonraki adımda buradan kategori seçip açıklamasız servislere öneri üreteceksin.</p>';
            $h .= '<div class="table-wrap"><table><thead><tr><th>Kategori</th><th class="num">Servis</th><th class="num">Açıklamasız</th><th class="num">Çelişen</th><th class="num">Eksik</th></tr></thead><tbody>';
            foreach (array_slice($cats, 0, 300) as $c) {
                $h .= '<tr><td><b>' . Ui::e($c['name']) . '</b><div class="src">#' . (int)$c['id'] . ' · ' . (int)$c['on'] . ' açık</div></td><td class="num tight">' . (int)$c['n'] . '</td>'
                    . '<td class="num tight">' . ($c['empty'] ? '<b>' . (int)$c['empty'] . '</b>' : '<span class="muted">0</span>') . '</td>'
                    . '<td class="num tight' . ($c['conflict'] ? ' lvl-error' : '') . '">' . (int)$c['conflict'] . '</td><td class="num tight">' . (int)$c['missing'] . '</td></tr>';
            }
            $h .= '</tbody></table></div>' . (count($cats) > 300 ? '<p class="muted">İlk 300 kategori gösterildi; aramayla daralt.</p>' : '');
        }
        $this->render('Açıklamalar', $h, ['active' => 'desc', 'sub' => $sub]);
    }

    private function postDesc(string $p): bool
    {
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
