<?php
/**
 * Tiny server-side SVG chart helpers (no JS chart library needed).
 * All labels are escaped; output scales with its container via viewBox.
 */

/** Vertical bar chart. $data = [label => value]. */
function svg_bar_chart(array $data, string $valuePrefix = '', int $height = 220): string
{
    if (!$data) {
        return '<p class="muted">No data yet.</p>';
    }
    $w = 640;
    $padL = 44; $padB = 28; $padT = 18;
    $max = max(1, max($data));
    $max = nice_ceiling($max);
    $n = count($data);
    $slot = ($w - $padL) / $n;
    $barW = max(6, min(46, $slot * .6));
    $plotH = $height - $padB - $padT;
    $svg = '<svg class="chart" viewBox="0 0 ' . $w . ' ' . $height . '" role="img" aria-label="Bar chart">';
    for ($i = 0; $i <= 4; $i++) {
        $y = $padT + $plotH - $plotH * $i / 4;
        $svg .= '<line class="grid-line" x1="' . $padL . '" x2="' . $w . '" y1="' . $y . '" y2="' . $y . '"/>';
        $svg .= '<text class="axis-label" x="' . ($padL - 8) . '" y="' . ($y + 4) . '" text-anchor="end">' . e(short_number($max * $i / 4)) . '</text>';
    }
    $i = 0;
    $labelEvery = (int) ceil($n / 12);
    foreach ($data as $label => $v) {
        $h = $plotH * $v / $max;
        $x = $padL + $slot * $i + ($slot - $barW) / 2;
        $y = $padT + $plotH - $h;
        $isLast = $i === $n - 1;
        $svg .= '<rect class="bar' . ($isLast ? ' accent' : '') . '" x="' . round($x, 1) . '" y="' . round($y, 1) . '" width="' . round($barW, 1) . '" height="' . round(max(0, $h), 1) . '" rx="5"><title>' . e($label . ': ' . $valuePrefix . number_format((float) $v, $v == (int) $v ? 0 : 2)) . '</title></rect>';
        if ($i % $labelEvery === 0 || ($isLast && ($n - 1) % $labelEvery > $labelEvery / 2)) {
            $svg .= '<text class="axis-label" x="' . round($x + $barW / 2, 1) . '" y="' . ($height - 8) . '" text-anchor="middle">' . e($label) . '</text>';
        }
        $i++;
    }
    return $svg . '</svg>';
}

/** Area/line chart. $data = [label => value]. */
function svg_line_chart(array $data, string $valuePrefix = '', int $height = 220): string
{
    if (count($data) < 2) {
        return svg_bar_chart($data, $valuePrefix, $height);
    }
    $w = 640;
    $padL = 44; $padB = 28; $padT = 18; $padR = 10;
    $max = nice_ceiling(max(1, max($data)));
    $n = count($data);
    $plotW = $w - $padL - $padR;
    $plotH = $height - $padB - $padT;
    $pts = [];
    $i = 0;
    foreach ($data as $label => $v) {
        $pts[] = [$padL + $plotW * $i / ($n - 1), $padT + $plotH - $plotH * $v / $max, $label, $v];
        $i++;
    }
    $svg = '<svg class="chart" viewBox="0 0 ' . $w . ' ' . $height . '" role="img" aria-label="Line chart">'
        . '<defs><linearGradient id="areaGrad" x1="0" y1="0" x2="0" y2="1"><stop offset="0" stop-color="#ff4d2e" stop-opacity=".28"/><stop offset="1" stop-color="#ff4d2e" stop-opacity="0"/></linearGradient></defs>';
    for ($g = 0; $g <= 4; $g++) {
        $y = $padT + $plotH - $plotH * $g / 4;
        $svg .= '<line class="grid-line" x1="' . $padL . '" x2="' . ($w - $padR) . '" y1="' . $y . '" y2="' . $y . '"/>';
        $svg .= '<text class="axis-label" x="' . ($padL - 8) . '" y="' . ($y + 4) . '" text-anchor="end">' . e(short_number($max * $g / 4)) . '</text>';
    }
    $line = '';
    foreach ($pts as $k => $p) {
        $line .= ($k ? 'L' : 'M') . round($p[0], 1) . ' ' . round($p[1], 1);
    }
    $area = $line . 'L' . round(end($pts)[0], 1) . ' ' . ($padT + $plotH) . 'L' . $padL . ' ' . ($padT + $plotH) . 'Z';
    $svg .= '<path class="area" d="' . $area . '"/><path class="line" d="' . $line . '"/>';
    $labelEvery = (int) ceil($n / 8);
    foreach ($pts as $k => $p) {
        $svg .= '<circle class="dot" cx="' . round($p[0], 1) . '" cy="' . round($p[1], 1) . '" r="3.5"><title>' . e($p[2] . ': ' . $valuePrefix . number_format((float) $p[3], 2)) . '</title></circle>';
        if ($k % $labelEvery === 0 || ($k === $n - 1 && ($n - 1) % $labelEvery > $labelEvery / 2)) {
            $svg .= '<text class="axis-label" x="' . round($p[0], 1) . '" y="' . ($height - 8) . '" text-anchor="middle">' . e($p[2]) . '</text>';
        }
    }
    return $svg . '</svg>';
}

/** Donut showing a percentage. */
function svg_donut(float $percent, string $label, string $color = '#15803d'): string
{
    $p = max(0, min(100, $percent));
    $r = 52;
    $c = 2 * M_PI * $r;
    $dash = $c * $p / 100;
    return '<svg class="donut" viewBox="0 0 150 150" role="img" aria-label="' . e(round($p) . '% ' . $label) . '">'
        . '<circle cx="75" cy="75" r="' . $r . '" fill="none" stroke="#f4efe6" stroke-width="16"/>'
        . '<circle cx="75" cy="75" r="' . $r . '" fill="none" stroke="' . e($color) . '" stroke-width="16" stroke-linecap="round" stroke-dasharray="' . round($dash, 2) . ' ' . round($c, 2) . '" transform="rotate(-90 75 75)"/>'
        . '<text class="donut-label" x="75" y="78" text-anchor="middle">' . round($p) . '%</text>'
        . '<text class="donut-sub" x="75" y="98" text-anchor="middle">' . e($label) . '</text></svg>';
}

function nice_ceiling(float $v): float
{
    if ($v <= 0) return 1;
    $exp = floor(log10($v));
    $base = pow(10, $exp);
    foreach ([1, 2, 2.5, 5, 10] as $m) {
        if ($v <= $m * $base) return $m * $base;
    }
    return 10 * $base;
}

function short_number(float $v): string
{
    if ($v >= 1000000) return round($v / 1000000, 1) . 'M';
    if ($v >= 1000) return round($v / 1000, 1) . 'k';
    return (string) round($v, $v < 10 && $v != (int) $v ? 1 : 0);
}

/** Fills missing days so time series have no gaps. Returns ['M j' => value]. */
function daily_series(array $rows, int $days, string $dateKey = 'd', string $valueKey = 'v'): array
{
    $map = [];
    foreach ($rows as $r) {
        $map[$r[$dateKey]] = (float) $r[$valueKey];
    }
    $out = [];
    for ($i = $days - 1; $i >= 0; $i--) {
        $d = date('Y-m-d', strtotime("-$i days"));
        $out[date('M j', strtotime($d))] = $map[$d] ?? 0;
    }
    return $out;
}
