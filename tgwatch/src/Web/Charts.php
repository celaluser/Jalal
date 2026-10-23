<?php
declare(strict_types=1);

namespace TgWatch\Web;

/**
 * Sunucuda çizilen SVG grafikler (kütüphane yok).
 * Kurallar: çubuk ≤24px, 4px yuvarlak uç ve tabanda düz; yığılmış dilimler arasında 2px yüzey boşluğu;
 * tek eksen; seçici etiket; her işaret klavye/fare ile ipucu verir; yanında tablo görünümü bulunur.
 * Renkler sabit sırayla (s1, s2, s3…) ve doğrulanmış paletten gelir.
 */
final class Charts
{
    private const MONTHS = ['Oca', 'Şub', 'Mar', 'Nis', 'May', 'Haz', 'Tem', 'Ağu', 'Eyl', 'Eki', 'Kas', 'Ara'];

    public static function dayLabel(int $ts): string
    {
        return date('j', $ts) . ' ' . self::MONTHS[(int)date('n', $ts) - 1];
    }

    /** 1, 2, 5 × 10ⁿ ile yuvarlanmış eksen üst sınırı. */
    private static function niceMax(float $v): float
    {
        if ($v <= 4) {
            return 4;
        }
        $p = 10 ** floor(log10($v));
        foreach ([1, 2, 2.5, 5, 10] as $m) {
            if ($m * $p >= $v) {
                return $m * $p;
            }
        }
        return 10 * $p;
    }

    private static function compact(float $n): string
    {
        return $n >= 1000 ? rtrim(rtrim(number_format($n / 1000, 1, ',', ''), '0'), ',') . 'B' : (string)(int)$n;
    }

    /** Üst köşeleri yuvarlak, tabanı düz dikdörtgen yolu. */
    private static function topRounded(float $x, float $y, float $w, float $h, float $r): string
    {
        $r = min($r, $w / 2, $h);
        return sprintf('M%.1f %.1fV%.1fQ%.1f %.1f %.1f %.1fH%.1fQ%.1f %.1f %.1f %.1fV%.1fZ',
            $x, $y + $h, $y + $r, $x, $y, $x + $r, $y, $x + $w - $r, $x + $w, $y, $x + $w, $y + $r, $y + $h);
    }

    /**
     * Günlük yığılmış sütun grafiği.
     * @param list<array{ts:int, values:list<int>}> $days
     * @param list<string> $series seri adları (s1, s2, s3 sırasıyla)
     */
    public static function stackedColumns(array $days, array $series, string $unit, string $title): string
    {
        $W = 640;
        $H = 220;
        $padL = 30;
        $padB = 24;
        $padT = 18;
        $innerW = $W - $padL - 4;
        $innerH = $H - $padB - $padT;
        $n = max(1, count($days));
        $slot = $innerW / $n;
        $bw = min(24.0, $slot * 0.62);
        $max = 0;
        foreach ($days as $d) {
            $max = max($max, array_sum($d['values']));
        }
        $top = self::niceMax((float)$max);
        $y = static fn(float $v): float => $padT + $innerH - ($v / $top) * $innerH;

        $svg = '<svg class="chart" viewBox="0 0 ' . $W . ' ' . $H . '" role="img" aria-label="' . Ui::e($title) . '">';
        for ($i = 0; $i <= 4; $i++) {
            $v = $top * $i / 4;
            $yy = $y($v);
            $svg .= '<line class="grid" x1="' . $padL . '" x2="' . $W . '" y1="' . round($yy, 1) . '" y2="' . round($yy, 1) . '"/>'
                . '<text class="axis" x="' . ($padL - 8) . '" y="' . round($yy + 4, 1) . '" text-anchor="end">' . self::compact($v) . '</text>';
        }
        $peak = -1;
        $peakIdx = -1;
        foreach ($days as $i => $d) {
            $t = array_sum($d['values']);
            if ($t > $peak) {
                $peak = $t;
                $peakIdx = $i;
            }
        }
        foreach ($days as $i => $d) {
            $cx = $padL + $slot * $i + $slot / 2;
            $x = $cx - $bw / 2;
            $total = array_sum($d['values']);
            $parts = [];
            foreach ($series as $k => $name) {
                $parts[] = ($d['values'][$k] ?? 0) . ' ' . mb_strtolower($name);
            }
            $label = self::dayLabel($d['ts']) . ': ' . implode(', ', $parts);
            $svg .= '<g class="bar" tabindex="0" data-tip="' . Ui::e($label) . '" data-tip-value="' . $total . ' ' . Ui::e($unit) . '"><title>' . Ui::e($label) . '</title>'
                . '<rect class="hit" x="' . round($cx - $slot / 2, 1) . '" y="' . $padT . '" width="' . round($slot, 1) . '" height="' . $innerH . '"/>';
            // Yığın: alttan üste, dilimler arasında 2px boşluk; yalnızca en üst dilimin ucu yuvarlak.
            $base = (float)($padT + $innerH);
            $nonZero = array_keys(array_filter($d['values'], static fn($v) => $v > 0));
            $last = $nonZero ? max($nonZero) : -1;
            foreach ($d['values'] as $k => $v) {
                if ($v <= 0) {
                    continue;
                }
                $h = ($v / $top) * $innerH;
                $gap = $k === $last ? 0 : 2;
                $segH = max(1.0, $h - $gap);
                $yTop = $base - $h;
                $cls = 'm f-s' . ($k + 1);
                $svg .= $k === $last
                    ? '<path class="' . $cls . '" d="' . self::topRounded($x, $yTop, $bw, $segH, 4) . '"/>'
                    : '<rect class="' . $cls . '" x="' . round($x, 1) . '" y="' . round($yTop + $gap, 1) . '" width="' . round($bw, 1) . '" height="' . round($segH, 1) . '"/>';
                $base -= $h;
            }
            // Seçici etiket: yalnızca en yüksek gün ve bugün.
            if ($total > 0 && ($i === $peakIdx || $i === $n - 1)) {
                $svg .= '<text class="axis" x="' . round($cx, 1) . '" y="' . round($y($total) - 6, 1) . '" text-anchor="middle">' . $total . '</text>';
            }
            if ($i % 2 === ($n - 1) % 2) {
                $svg .= '<text class="axis" x="' . round($cx, 1) . '" y="' . ($H - 6) . '" text-anchor="middle">' . ($i === $n - 1 ? 'Bugün' : self::dayLabel($d['ts'])) . '</text>';
            }
            $svg .= '</g>';
        }
        $svg .= '<line class="grid" x1="' . $padL . '" x2="' . $W . '" y1="' . ($padT + $innerH) . '" y2="' . ($padT + $innerH) . '"/></svg>';

        $totals = array_fill(0, count($series), 0);
        foreach ($days as $d) {
            foreach ($d['values'] as $k => $v) {
                $totals[$k] += $v;
            }
        }
        $legend = '<ul class="legend">';
        foreach ($series as $k => $name) {
            $legend .= '<li><span class="sw s' . ($k + 1) . '"></span>' . Ui::e($name) . ' <b>' . $totals[$k] . '</b></li>';
        }
        $legend .= '</ul>';

        $table = '<details class="chart-table"><summary>Tablo olarak göster</summary><div class="table-wrap"><table><thead><tr><th>Gün</th>';
        foreach ($series as $name) {
            $table .= '<th class="num">' . Ui::e($name) . '</th>';
        }
        $table .= '<th class="num">Toplam</th></tr></thead><tbody>';
        foreach (array_reverse($days) as $d) {
            $table .= '<tr><td>' . self::dayLabel($d['ts']) . '</td>';
            foreach ($series as $k => $_) {
                $table .= '<td class="num">' . ($d['values'][$k] ?? 0) . '</td>';
            }
            $table .= '<td class="num">' . array_sum($d['values']) . '</td></tr>';
        }
        $table .= '</tbody></table></div></details>';
        return $svg . $legend . $table;
    }

    /** Küçük eğilim çizgisi: gri çizgi, son dönem vurgu renginde. Tek seri, eksen yok. */
    public static function sparkline(array $values, string $label): string
    {
        $n = count($values);
        if ($n < 2) {
            return '';
        }
        $max = max(1, max($values));
        $W = 240;
        $H = 36;
        $pts = [];
        foreach (array_values($values) as $i => $v) {
            $pts[] = [round($i * $W / ($n - 1), 1), round($H - 3 - ($v / $max) * ($H - 6), 1)];
        }
        $d = 'M' . implode('L', array_map(static fn($p) => $p[0] . ' ' . $p[1], array_slice($pts, 0, -1)));
        $last = 'M' . $pts[$n - 2][0] . ' ' . $pts[$n - 2][1] . 'L' . $pts[$n - 1][0] . ' ' . $pts[$n - 1][1];
        return '<svg class="spark" viewBox="0 0 ' . $W . ' ' . $H . '" preserveAspectRatio="none" role="img" aria-label="' . Ui::e($label) . '">'
            . '<path d="' . $d . '"/><path class="last" d="' . $last . '"/></svg>';
    }

    /**
     * Oran çubuğu (%100 yığılmış yatay) + değerli açıklama.
     * @param list<array{0:string,1:int}> $parts [ad, değer] sabit sırayla
     */
    public static function mix(array $parts, string $title): string
    {
        $total = array_sum(array_column($parts, 1));
        if ($total === 0) {
            return '<p class="muted">Henüz veri yok.</p>';
        }
        // SVG ile çizilir (satır içi stil CSP'de yasak). Dilimler arasında yüzey boşluğu, uçlar yuvarlak.
        $W = 1000;
        $gap = 4;
        $live = array_values(array_filter(array_keys($parts), static fn($k) => $parts[$k][1] > 0));
        $avail = $W - $gap * max(0, count($live) - 1);
        $bar = '<svg class="mix" viewBox="0 0 ' . $W . ' 14" preserveAspectRatio="none" role="img" aria-label="' . Ui::e($title) . '">'
            . '<defs><clipPath id="mixclip"><rect width="' . $W . '" height="14" rx="7"/></clipPath></defs><g clip-path="url(#mixclip)">';
        $legend = '<ul class="legend">';
        $x = 0.0;
        foreach ($parts as $k => [$name, $v]) {
            $pct = round($v * 100 / $total);
            if ($v > 0) {
                $w = max(6.0, $avail * $v / $total);
                $bar .= '<rect class="f-s' . ($k + 1) . '" x="' . round($x, 1) . '" y="0" width="' . round($w, 1) . '" height="14" tabindex="0" data-tip="' . Ui::e($name) . '" data-tip-value="' . $v . ' ad, %' . $pct . '"><title>' . Ui::e($name) . ': ' . $v . ' (%' . $pct . ')</title></rect>';
                $x += $w + $gap;
            }
            $legend .= '<li><span class="sw s' . ($k + 1) . '"></span>' . Ui::e($name) . ' <b>%' . $pct . '</b> <span>(' . $v . ')</span></li>';
        }
        $bar .= '</g></svg>';
        return $bar . $legend . '</ul>';
    }

    /** Doluluk çubuğu (yüzde). Boş kısım aynı rampanın açık tonu; dolu kısım duruma göre renk alır. */
    public static function meter(int $pct, string $state, string $label): string
    {
        $pct = max(0, min(100, $pct));
        return '<svg class="meter ' . Ui::e($state) . '" viewBox="0 0 100 8" preserveAspectRatio="none" role="img" aria-label="' . Ui::e($label) . ': %' . $pct . '">'
            . '<rect class="track" width="100" height="8" rx="4"/>' . ($pct > 0 ? '<rect class="fill" width="' . max(4, $pct) . '" height="8" rx="4"/>' : '') . '</svg>';
    }
}
