<?php
if (!defined('IGFP_ADMIN')) { http_response_code(403); exit('Forbidden'); }
/**
 * Tek popup'ın istatistik bloğu (modal ve İstatistik sekmesi ortak kullanır). HTML döndürür.
 * Çağıran tarafta h() ve stats-lib.php yüklü olmalı.
 */
function pstats_html(array $stats, array $vhist, array $names, string $pid, string $range, bool $chips = false): string {
    $from = st_start_date($stats, $range); $to = date('Y-m-d');
    $d = st_popup_detail($stats, $from, $to, $pid);
    $bk = st_buckets($d['daily']); $best = 1;
    foreach ($bk as $b) { if ($b['view'] > $best) $best = $b['view']; }
    $nm = $names[$pid] ?? $pid;
    ob_start(); ?>
    <?php if ($chips): ?>
      <div class="chips" style="margin-bottom:12px">
        <?php foreach (st_ranges() as $rk => $rv): ?>
          <a href="#" data-pstat-range="<?= h($rk) ?>" class="chip <?= (string) $rk === $range ? 'on' : '' ?>"><?= h($rv) ?></a>
        <?php endforeach; ?>
      </div>
    <?php endif; ?>
    <div class="tiles">
      <div class="tile"><div class="t-lbl">Gösterim</div><div class="t-val"><?= number_format($d['view'], 0, ',', '.') ?></div>
        <div class="t-sub"><?= h(date('d.m.Y', strtotime($from))) ?> — <?= h(date('d.m.Y', strtotime($to))) ?></div></div>
      <div class="tile"><div class="t-lbl">Tıklama</div><div class="t-val"><?= number_format($d['click'], 0, ',', '.') ?></div>
        <div class="t-sub">oran %<?= st_ctr($d['view'], $d['click']) ?></div></div>
      <div class="tile"><div class="t-lbl">Tekil ziyaretçi</div><div class="t-val"><?= $d['hasDetail'] || $d['uniq'] ? number_format($d['uniq'], 0, ',', '.') : '—' ?></div>
        <div class="t-sub">günlük tekil toplamı</div></div>
      <div class="tile"><div class="t-lbl">Ort. açık kalma</div><div class="t-val"><?= $d['close'] ? $d['avgSec'] . 'sn' : '—' ?></div>
        <div class="t-sub"><?= $d['close'] ? '%' . $d['quickPct'] . ' hemen kapattı' : 'veri yok' ?></div></div>
    </div>

    <div class="card" style="margin-top:14px">
      <h2>Günlük gösterim</h2>
      <div class="body">
        <?php if ($d['view'] + $d['click'] === 0): ?>
          <p class="hint" style="margin:0">Bu popup için seçili aralıkta veri yok.</p>
        <?php else: ?>
          <div class="chart">
            <?php foreach ($bk as $b): $hh = round($b['view'] / $best * 100); ?>
              <div class="bar" style="height:<?= max(3, $hh) ?>%" title="<?= h($b['full']) ?> — <?= (int) $b['view'] ?> gösterim, <?= (int) $b['click'] ?> tıklama">
                <?php if ($b['view'] > 0 && count($bk) <= 20): ?><span><?= (int) $b['view'] ?></span><?php endif; ?>
              </div>
            <?php endforeach; ?>
          </div>
          <div class="chart-x"><?php foreach ($bk as $b): ?><div><?= h($b['label']) ?></div><?php endforeach; ?></div>
        <?php endif; ?>
      </div>
    </div>

    <?php if (!$d['hasDetail'] && $d['view'] > 0): ?>
      <p class="hint" style="margin:10px 4px 0">Cihaz, kaynak ve sayfa ayrıntısı bu güncellemeden sonraki gösterimler için toplanır; eski kayıtlarda yalnızca günlük toplam vardır.</p>
    <?php endif; ?>

    <?php if ($d['act']): ?>
    <div class="card" style="margin-top:14px"><h2>Popup içi eylemler</h2>
      <div class="body tbl-wrap"><table><tr><th>Eylem</th><th class="num">Adet</th></tr>
        <?php foreach ($d['act'] as $k => $n): ?><tr><td><?= h(st_action_name((string) $k)) ?></td><td class="num"><b><?= (int) $n ?></b></td></tr><?php endforeach; ?>
      </table></div></div>
    <?php endif; ?>

    <?php foreach ([['dev', 'Cihaz', 'Cihaz'], ['src', 'Trafik kaynağı', 'Kaynak'], ['pg', 'Sayfa bazında', 'Sayfa']] as $sec):
        list($key, $title, $col) = $sec; if (!$d[$key]) continue; ?>
    <div class="card" style="margin-top:14px"><h2><?= h($title) ?></h2>
      <div class="body tbl-wrap"><table><tr><th><?= h($col) ?></th><th class="num">Gösterim</th><th class="num">Tıklama</th><th class="num">Oran</th></tr>
        <?php foreach (array_slice($d[$key], 0, 12, true) as $k => $v): ?>
          <tr><td><?= $key === 'pg' ? '<code>' . h($k) . '</code>' : h($key === 'dev' ? st_device_name((string) $k) : $k) ?></td>
              <td class="num"><b><?= (int) $v['view'] ?></b></td><td class="num"><?= (int) $v['click'] ?></td><td class="num">%<?= st_ctr($v['view'], $v['click']) ?></td></tr>
        <?php endforeach; ?>
      </table></div></div>
    <?php endforeach; ?>

    <?php if ($d['svc']): ?>
    <div class="card" style="margin-top:14px"><h2>Tıklanan servisler</h2>
      <div class="body tbl-wrap"><table><tr><th>ID</th><th>Servis</th><th class="num">Tıklama</th></tr>
        <?php foreach (array_slice($d['svc'], 0, 15, true) as $sid => $n): $sn = st_service_name($vhist, (string) $sid); ?>
          <tr><td><?= h($sid) ?></td><td><?= $sn ? h($sn) : '<span class="dim">—</span>' ?></td><td class="num"><b><?= (int) $n ?></b></td></tr>
        <?php endforeach; ?>
      </table></div></div>
    <?php endif; ?>
    <?php return (string) ob_get_clean();
}
