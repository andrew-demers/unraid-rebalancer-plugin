<?php
/**
 * reset_errors.php — POST: reset error entries to pending via --retry-errors.
 */
header('Content-Type: application/json');
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'error' => 'POST required']);
    exit;
}
require_once dirname(__DIR__) . '/common.php';

if (is_running()) {
    echo json_encode(['success' => false, 'error' => 'Cannot reset errors while rebalancer is running']);
    exit;
}

$python = find_python3();
$script = PLUGIN_DIR . '/rebalancer.py';

$cmd = implode(' ', array_map('escapeshellarg', [$python, $script, '--retry-errors', '--yes'])) . ' 2>&1';

$old_limit = ini_set('max_execution_time', 30);
$output    = shell_exec($cmd);
if ($old_limit !== false) {
    ini_set('max_execution_time', $old_limit);
}

echo json_encode([
    'success' => true,
    'output'  => is_string($output) ? $output : '',
]);
