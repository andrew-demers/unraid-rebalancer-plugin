<?php
/**
 * start.php — POST: start the rebalancer in background.
 *
 * POST params:
 *   force_rescan  (0|1)
 *   limit         (int, 0=unlimited)
 */
header('Content-Type: application/json');
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'error' => 'POST required']);
    exit;
}
require_once dirname(__DIR__) . '/common.php';

if (is_running()) {
    echo json_encode(['success' => false, 'error' => 'Rebalancer is already running', 'pid' => get_pid()]);
    exit;
}

// Sanitize inputs
$force_rescan = isset($_POST['force_rescan']) && (int)$_POST['force_rescan'] === 1;
$limit        = isset($_POST['limit']) ? max(0, (int)$_POST['limit']) : 0;

// Ensure state dir exists
if (!is_dir(STATE_DIR)) {
    @mkdir(STATE_DIR, 0755, true);
}

$python   = find_python3();
$script   = PLUGIN_DIR . '/rebalancer.py';
$log_file = RUN_LOG;

// Build command (all parts are hard-coded strings or sanitized ints — no user string passthrough)
$cmd_parts = [$python, $script, '--yes'];
if ($force_rescan) {
    $cmd_parts[] = '--force-rescan';
}
if ($limit > 0) {
    $cmd_parts[] = '--limit';
    $cmd_parts[] = (string)$limit;
}

// Redirect stdout+stderr to run.log, run in background, capture PID
$cmd = 'nohup ' . implode(' ', array_map('escapeshellarg', $cmd_parts))
     . ' >> ' . escapeshellarg($log_file) . ' 2>&1 & echo $!';

$pid_str = trim((string)shell_exec($cmd));

if (!ctype_digit($pid_str) || (int)$pid_str <= 0) {
    echo json_encode(['success' => false, 'error' => 'Failed to start process']);
    exit;
}

$pid = (int)$pid_str;
file_put_contents(PID_FILE, (string)$pid);

echo json_encode(['success' => true, 'pid' => $pid]);
