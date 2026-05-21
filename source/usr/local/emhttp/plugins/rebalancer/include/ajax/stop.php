<?php
/**
 * stop.php — POST: send SIGTERM to the running rebalancer process.
 */
header('Content-Type: application/json');
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'error' => 'POST required']);
    exit;
}
require_once dirname(__DIR__) . '/common.php';

$pid = get_pid();
if ($pid === null) {
    echo json_encode(['success' => false, 'message' => 'No PID file found — rebalancer may not be running']);
    exit;
}

if (!file_exists("/proc/$pid")) {
    @unlink(PID_FILE);
    echo json_encode(['success' => true, 'message' => 'Process was already stopped']);
    exit;
}

// Send SIGTERM (15) — graceful shutdown
$result = posix_kill($pid, SIGTERM);
if ($result) {
    echo json_encode(['success' => true, 'message' => "Sent SIGTERM to PID $pid"]);
} else {
    $err = posix_strerror(posix_get_last_error());
    echo json_encode(['success' => false, 'message' => "Failed to send signal: $err"]);
}
