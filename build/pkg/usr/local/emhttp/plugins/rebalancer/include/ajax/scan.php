<?php
/**
 * scan.php — POST: run a dry-run scan synchronously (max 120s).
 *
 * POST params:
 *   force_rescan  (0|1)
 */
header('Content-Type: application/json');
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'error' => 'POST required']);
    exit;
}
require_once dirname(__DIR__) . '/common.php';

$force_rescan = isset($_POST['force_rescan']) && (int)$_POST['force_rescan'] === 1;

$python = find_python3();
$script = PLUGIN_DIR . '/rebalancer.py';

$cmd_parts = [$python, $script, '--dry-run', '--yes'];
if ($force_rescan) {
    $cmd_parts[] = '--force-rescan';
}

// Build safe command string
$cmd = implode(' ', array_map('escapeshellarg', $cmd_parts)) . ' 2>&1';

// Set a 120-second time limit for the scan
$old_limit = ini_set('max_execution_time', 150);
$output = '';
$exit_code = -1;

$descriptors = [
    0 => ['pipe', 'r'],
    1 => ['pipe', 'w'],
    2 => ['pipe', 'w'],
];
$proc = proc_open($cmd, $descriptors, $pipes);
if ($proc === false) {
    ini_set('max_execution_time', $old_limit);
    echo json_encode(['success' => false, 'output' => '', 'error' => 'Failed to launch scan process']);
    exit;
}
fclose($pipes[0]);

// Read with 120s deadline
$deadline = time() + 120;
$stdout   = '';
$stderr   = '';
stream_set_blocking($pipes[1], false);
stream_set_blocking($pipes[2], false);
while (true) {
    $read = [$pipes[1], $pipes[2]];
    $write = null;
    $except = null;
    $remaining = max(1, $deadline - time());
    $n = stream_select($read, $write, $except, $remaining);
    if ($n === false) break;
    foreach ($read as $s) {
        $chunk = fread($s, 8192);
        if ($chunk !== false && $chunk !== '') {
            if ($s === $pipes[1]) $stdout .= $chunk;
            else $stderr .= $chunk;
        }
    }
    if (feof($pipes[1]) && feof($pipes[2])) break;
    if (time() >= $deadline) break;
}
fclose($pipes[1]);
fclose($pipes[2]);
$exit_code = proc_close($proc);

if ($old_limit !== false) {
    ini_set('max_execution_time', $old_limit);
}

$output = $stdout . ($stderr ? "\n--- stderr ---\n" . $stderr : '');
$success = ($exit_code === 0);

// Refresh drives and summary after scan
$drives  = [];
$summary = [];

if (file_exists(DRIVES_FILE)) {
    $raw = @file_get_contents(DRIVES_FILE);
    if ($raw !== false) {
        $d = @json_decode($raw, true);
        if (is_array($d)) $drives = $d;
    }
}

$db = get_plan_db();
if ($db !== null) {
    $summary = get_plan_summary($db);
    $db->close();
}

echo json_encode([
    'success' => $success,
    'output'  => $output,
    'drives'  => $drives,
    'summary' => $summary,
]);
