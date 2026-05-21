<?php
/**
 * status.php — GET: returns JSON status for the rebalancer plugin.
 */
header('Content-Type: application/json');
require_once dirname(__DIR__) . '/common.php';

$response = [
    'running'         => false,
    'drives'          => [],
    'summary'         => [],
    'current'         => [],
    'pending'         => [],
    'remaining_bytes' => 0,
    'total_bytes'     => 0,
    'log_lines'       => [],
];

// Running status
$response['running'] = is_running();

// Drives from drives.json
if (file_exists(DRIVES_FILE)) {
    $raw = @file_get_contents(DRIVES_FILE);
    if ($raw !== false) {
        $drives = @json_decode($raw, true);
        if (is_array($drives)) {
            $response['drives'] = $drives;
        }
    }
}

// Plan data from SQLite
$db = get_plan_db();
if ($db !== null) {
    try {
        // Summary
        $response['summary'] = get_plan_summary($db);

        // Total bytes
        $row = $db->querySingle("SELECT COALESCE(SUM(size_bytes),0) FROM plan");
        $response['total_bytes'] = (int)$row;

        // Remaining bytes (pending + in_progress)
        $row = $db->querySingle(
            "SELECT COALESCE(SUM(size_bytes),0) FROM plan WHERE status IN ('pending','in_progress')"
        );
        $response['remaining_bytes'] = (int)$row;

        // Current (in_progress) entries
        $res = $db->query(
            "SELECT path, size_bytes, source_disk, target_disk FROM plan WHERE status='in_progress' LIMIT 5"
        );
        if ($res) {
            while ($row = $res->fetchArray(SQLITE3_ASSOC)) {
                $response['current'][] = [
                    'path'        => $row['path'],
                    'size_bytes'  => (int)$row['size_bytes'],
                    'source_disk' => $row['source_disk'],
                    'target_disk' => $row['target_disk'],
                ];
            }
        }

        // Pending (up to 5)
        $res = $db->query(
            "SELECT path, size_bytes, source_disk, target_disk FROM plan WHERE status='pending' LIMIT 5"
        );
        if ($res) {
            while ($row = $res->fetchArray(SQLITE3_ASSOC)) {
                $response['pending'][] = [
                    'path'        => $row['path'],
                    'size_bytes'  => (int)$row['size_bytes'],
                    'source_disk' => $row['source_disk'],
                    'target_disk' => $row['target_disk'],
                ];
            }
        }
    } catch (Exception $e) {
        // Partial data is acceptable; don't crash
    } finally {
        $db->close();
    }
}

// Last 30 lines of run.log
$response['log_lines'] = [];
if (file_exists(RUN_LOG)) {
    // Read last 30 lines efficiently
    $lines = [];
    $fp = @fopen(RUN_LOG, 'r');
    if ($fp) {
        $buf = '';
        fseek($fp, 0, SEEK_END);
        $size = ftell($fp);
        $chunk = 8192;
        $pos = max(0, $size - $chunk * 4);
        fseek($fp, $pos);
        $buf = fread($fp, $size - $pos);
        fclose($fp);
        $allLines = explode("\n", $buf);
        $last30 = array_slice(array_filter($allLines, fn($l) => $l !== ''), -30);
        $response['log_lines'] = array_values($last30);
    }
}

echo json_encode($response);
