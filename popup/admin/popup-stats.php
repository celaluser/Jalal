<?php
define('IGFP_ADMIN', true);
/** Panelde popup başına istatistik modalının içeriği (HTML parçası). Girişi olmayan açamaz. */
require __DIR__ . '/auth.php';
if (!adm_logged_in()) { http_response_code(403); exit('Forbidden'); }
require __DIR__ . '/stats-lib.php';
require __DIR__ . '/view-pstats.php';
function h($s) { return htmlspecialchars((string) $s, ENT_QUOTES, 'UTF-8'); }

$pid = preg_replace('/[^0-9A-Za-z_-]/', '', (string) ($_GET['pid'] ?? ''));
$range = (string) ($_GET['range'] ?? '30');
if (!array_key_exists($range, st_ranges())) $range = '30';

$paths = popup_paths();
$stats = is_file($paths['stats']) ? json_decode((string) @file_get_contents($paths['stats']), true) : [];
if (!is_array($stats)) $stats = [];
ksort($stats);
$vh = is_file($paths['versions']) ? json_decode((string) @file_get_contents($paths['versions']), true) : [];
if (!is_array($vh)) $vh = [];
$cfg = popup_config();
$names = [];
foreach ($cfg['autos']  as $a) { if (!empty($a['id'])) $names[$a['id']] = ($a['name'] ?? $a['id']) . ' (oto)'; }
foreach ($cfg['popups'] as $p) { if (!empty($p['id'])) $names[$p['id']] = $p['name'] ?? $p['id']; }

header('Content-Type: text/html; charset=utf-8');
header('Cache-Control: no-store');
echo pstats_html($stats, $vh, $names, $pid, $range, true);
