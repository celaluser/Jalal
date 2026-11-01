<?php
// IGFP-BUILD:10
if (!defined('IGFP_ADMIN')) { http_response_code(403); exit('Forbidden'); }
/**
 * Tek popup'ın istatistik bloğu (modal ve İstatistik sekmesi ortak kullanır). HTML döndürür.
 * Sade görünüm: 3 ana rakam, grafik, içerik tablosu; geri kalan her şey "Daha fazla ayrıntı" altında kapalı.
 * Çağıran tarafta h() ve stats-lib.php yüklü olmalı.
 */
function pstats_html(array $stats, array $vhist, array $names, string $pid, string $range, bool $chips = false): string {
    $from = st_start_date($stats, $range); $to = date('Y-m-d');
    $d = st_popup_detail($stats, $from, $to, $pid);
    $bk = st_buckets($d['daily']); $best = 1;
    foreach ($bk as $b) { if ($b['view'] > $best) $best = $b['view']; }
    $ver = st_popup_versions($stats, $vhist, $from, $to, $pid);
    $ranges = ['7' => '7 gün', '30' => '30 gün', '90' => '3 ay', '365' => '1 yıl', 'all' => 'Tümü'];
    ob_start(); ?>
    <?php if ($chips): ?>
      <div class="chips" style="margin-bottom:14px">
        <?php foreach ($ranges as $rk => $rv): ?>
          <a href="#" data-pstat-range="<?= h($rk) ?>" class="chip <?= (string) $rk === $range ? 'on' : '' ?>"><?= h($rv) ?></a>
        <?php endforeach; ?>
      </div>
    <?php endif; ?>

    <div class="tiles" style="grid-template-columns:repeat(3,1fr)">
      <div class="tile"><div class="t-lbl">Gösterim</div><div class="t-val"><?= number_format($d['view'], 0, ',', '.') ?></div></div>
      <div class="tile"><div class="t-lbl">Tıklama</div><div class="t-val"><?= number_format($d['click'], 0, ',', '.') ?></div></div>
      <div class="tile"><div class="t-lbl">Tıklama oranı</div><div class="t-val">%<?= st_ctr($d['view'], $d['click']) ?></div></div>
    </div>
    <?php if ($d['close'] || $d['uniq']): ?>
      <p class="hint" style="margin:8px 2px 0">
        <?= $d['uniq'] ? 'Tekil ziyaretçi: <b>' . (int) $d['uniq'] . '</b>' : '' ?>
        <?= $d['uniq'] && $d['close'] ? ' · ' : '' ?>
        <?= $d['close'] ? 'Ort. açık kalma: <b>' . $d['avgSec'] . ' sn</b>' : '' ?>
      </p>
    <?php endif; ?>

    <div class="card" style="margin-top:14px">
      <div class="body" style="padding-top:16px">
        <?php if ($d['view'] + $d['click'] === 0): ?>
          <p class="hint" style="margin:0">Bu aralıkta veri yok.</p>
        <?php else: ?>
          <div class="chart">
            <?php foreach ($bk as $b): $hh = round($b['view'] / $best * 100); ?>
              <div class="bar" style="height:<?= max(3, $hh) ?>%" title="<?= h($b['full']) ?> — <?= (int) $b['view'] ?> gösterim, <?= (int) $b['click'] ?> tıklama"></div>
            <?php endforeach; ?>
          </div>
          <div class="chart-x"><?php foreach ($bk as $b): ?><div><?= h($b['label']) ?></div><?php endforeach; ?></div>
        <?php endif; ?>
      </div>
    </div>

    <?php if (count($ver['groups']) > 1): ?>
    <div class="card" style="margin-top:14px">
      <h2>Hangi içerik ne kadar gösterildi</h2>
      <div class="body tbl-wrap">
        <table>
          <tr><th>İçerik</th><th class="num">Gösterim</th><th class="num">Tıklama</th><th class="num">Oran</th></tr>
          <?php foreach ($ver['groups'] as $lb => $g): $isNow = ($lb === $ver['current']); ?>
            <tr>
              <td><?= h($lb) ?><?= $isNow ? ' <em class="tag">şu an</em>' : '' ?>
                <div class="dim" style="font-size:11.5px"><?= h(date('d.m', strtotime($g['first']))) ?><?= $g['first'] !== $g['last'] ? ' – ' . h(date('d.m', strtotime($g['last']))) : '' ?></div></td>
              <td class="num"><b><?= number_format($g['view'], 0, ',', '.') ?></b></td>
              <td class="num"><?= number_format($g['click'], 0, ',', '.') ?></td>
              <td class="num">%<?= st_ctr($g['view'], $g['click']) ?></td>
            </tr>
          <?php endforeach; ?>
        </table>
      </div>
    </div>
    <?php endif; ?>

    <?php
      $hasMore = $d['act'] || $d['dev'] || $d['src'] || $d['pg'] || $d['svc'] || $ver['days'];
      if ($hasMore): ?>
    <details class="more" style="margin-top:14px">
      <summary style="cursor:pointer;font-weight:600;padding:10px 2px">Daha fazla ayrıntı</summary>

      <?php if ($d['act']): ?>
        <h4 class="mh">Popup içi eylemler</h4>
        <div class="tbl-wrap"><table>
          <?php foreach ($d['act'] as $k => $n): ?><tr><td><?= h(st_action_name((string) $k)) ?></td><td class="num"><b><?= (int) $n ?></b></td></tr><?php endforeach; ?>
        </table></div>
      <?php endif; ?>

      <?php foreach ([['dev', 'Cihaz'], ['src', 'Trafik kaynağı'], ['pg', 'Sayfa']] as $sec):
          list($key, $title) = $sec; if (!$d[$key]) continue; ?>
        <h4 class="mh"><?= h($title) ?></h4>
        <div class="tbl-wrap"><table>
          <?php foreach (array_slice($d[$key], 0, 5, true) as $k => $v): ?>
            <tr><td><?= $key === 'pg' ? '<code>' . h($k) . '</code>' : h($key === 'dev' ? st_device_name((string) $k) : $k) ?></td>
                <td class="num"><b><?= (int) $v['view'] ?></b> <span class="dim">gösterim</span></td><td class="num"><?= (int) $v['click'] ?> <span class="dim">tıkl.</span></td></tr>
          <?php endforeach; ?>
        </table></div>
      <?php endforeach; ?>

      <?php if ($d['svc']): ?>
        <h4 class="mh">En çok tıklanan servisler</h4>
        <div class="tbl-wrap"><table>
          <?php foreach (array_slice($d['svc'], 0, 6, true) as $sid => $n): $sn = st_service_name($vhist, (string) $sid); ?>
            <tr><td><?= $sn ? h($sn) : 'ID ' . h($sid) ?></td><td class="num"><b><?= (int) $n ?></b></td></tr>
          <?php endforeach; ?>
        </table></div>
      <?php endif; ?>

      <?php if ($ver['days']): ?>
        <h4 class="mh">Gün gün hangi içerik gösterildi</h4>
        <div class="tbl-wrap"><table>
          <?php foreach (array_slice($ver['days'], 0, 31, true) as $dy => $lst): foreach ($lst as $lb => $x): ?>
            <tr><td><?= h(date('d.m', strtotime($dy))) ?></td><td><?= h($lb) ?></td><td class="num"><b><?= (int) $x['view'] ?></b></td></tr>
          <?php endforeach; endforeach; ?>
        </table></div>
      <?php endif; ?>
    </details>
    <?php endif; ?>
    <?php return (string) ob_get_clean();
}
