<?php
/**
 * save_settings.php — POST: validate and save config.json.
 *
 * POST params: max_used, strategy, excludes, active_hours,
 *              min_free_space, bwlimit, copy_timeout,
 *              verify_timeout, lsof_timeout
 */
header('Content-Type: application/json');
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'error' => 'POST required']);
    exit;
}
require_once dirname(__DIR__) . '/common.php';

$errors = [];

// max_used: 1-99
$max_used = isset($_POST['max_used']) ? (int)$_POST['max_used'] : 80;
if ($max_used < 1 || $max_used > 99) {
    $errors[] = 'max_used must be between 1 and 99';
}

// strategy
$valid_strategies = ['fullest-first', 'largest-first', 'smallest-first', 'auto'];
$strategy = isset($_POST['strategy']) ? trim($_POST['strategy']) : 'fullest-first';
if (!in_array($strategy, $valid_strategies, true)) {
    $errors[] = 'Invalid strategy value';
    $strategy = 'fullest-first';
}

// excludes: comma-separated share names — strip whitespace, filter empties
$excludes_raw = isset($_POST['excludes']) ? trim($_POST['excludes']) : '';
$excludes = array_values(array_filter(
    array_map('trim', explode(',', $excludes_raw)),
    fn($s) => $s !== ''
));

// active_hours: blank means no restriction, or HH:MM-HH:MM
$active_hours_raw = isset($_POST['active_hours']) ? trim($_POST['active_hours']) : '';
$active_hours = null;
if ($active_hours_raw !== '') {
    if (!preg_match('/^\d{2}:\d{2}-\d{2}:\d{2}$/', $active_hours_raw)) {
        $errors[] = 'active_hours must be in HH:MM-HH:MM format or blank';
    } else {
        $active_hours = $active_hours_raw;
    }
}

// min_free_space: e.g. "50G", "100G", "1T" — must match size pattern
$min_free_space = isset($_POST['min_free_space']) ? trim($_POST['min_free_space']) : '50G';
if (!preg_match('/^\d+(\.\d+)?\s*[KMGTP]?B?$/i', $min_free_space)) {
    $errors[] = 'Invalid min_free_space format (use e.g. 50G, 100G, 1T)';
    $min_free_space = '50G';
}

// bwlimit: optional integer KB/s
$bwlimit_raw = isset($_POST['bwlimit']) ? trim($_POST['bwlimit']) : '';
$bwlimit = null;
if ($bwlimit_raw !== '') {
    if (!ctype_digit($bwlimit_raw) || (int)$bwlimit_raw <= 0) {
        $errors[] = 'bwlimit must be a positive integer (KB/s) or blank';
    } else {
        $bwlimit = (int)$bwlimit_raw;
    }
}

// Timeout fields: must be positive integers
$copy_timeout = isset($_POST['copy_timeout']) ? (int)$_POST['copy_timeout'] : 86400;
if ($copy_timeout <= 0) {
    $errors[] = 'copy_timeout must be > 0';
    $copy_timeout = 86400;
}

$verify_timeout = isset($_POST['verify_timeout']) ? (int)$_POST['verify_timeout'] : 28800;
if ($verify_timeout <= 0) {
    $errors[] = 'verify_timeout must be > 0';
    $verify_timeout = 28800;
}

$lsof_timeout = isset($_POST['lsof_timeout']) ? (int)$_POST['lsof_timeout'] : 120;
if ($lsof_timeout <= 0) {
    $errors[] = 'lsof_timeout must be > 0';
    $lsof_timeout = 120;
}

if (!empty($errors)) {
    echo json_encode(['success' => false, 'error' => implode('; ', $errors)]);
    exit;
}

// Load existing config to preserve fields we don't manage (e.g. remote)
$existing = get_config();
$data = array_merge($existing, [
    'max_used'       => $max_used,
    'strategy'       => $strategy,
    'excludes'       => $excludes,
    'active_hours'   => $active_hours,
    'min_free_space' => $min_free_space,
    'bwlimit'        => $bwlimit,
    'copy_timeout'   => $copy_timeout,
    'verify_timeout' => $verify_timeout,
    'lsof_timeout'   => $lsof_timeout,
]);

if (!save_config($data)) {
    echo json_encode(['success' => false, 'error' => 'Failed to write config.json — check permissions on ' . STATE_DIR]);
    exit;
}

echo json_encode(['success' => true]);
