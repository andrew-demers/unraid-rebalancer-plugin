<?php
/**
 * get_log.php — GET: return last N lines of transfers.log as JSON.
 *
 * GET param:
 *   lines  (int, default 50)
 *
 * TSV format: timestamp, status, size_bytes, source_disk, target_disk, path[, detail]
 */
header('Content-Type: application/json');
require_once dirname(__DIR__) . '/common.php';

$count    = isset($_GET['lines']) ? max(1, min(500, (int)$_GET['lines'])) : 50;
$log_path = STATE_DIR . '/transfers.log';

if (!file_exists($log_path)) {
    echo json_encode([]);
    exit;
}

// Read the last $count non-empty lines
$fp = @fopen($log_path, 'r');
if (!$fp) {
    echo json_encode([]);
    exit;
}

// Efficient tail: read from end in chunks
fseek($fp, 0, SEEK_END);
$size     = ftell($fp);
$buf      = '';
$chunk    = 8192;
$read_pos = $size;

while ($read_pos > 0 && substr_count($buf, "\n") < $count + 2) {
    $read_size = min($chunk, $read_pos);
    $read_pos -= $read_size;
    fseek($fp, $read_pos);
    $buf = fread($fp, $read_size) . $buf;
}
fclose($fp);

$all_lines = array_filter(explode("\n", $buf), fn($l) => trim($l) !== '');
$last_n    = array_slice(array_values($all_lines), -$count);

$entries = [];
foreach ($last_n as $line) {
    $parts = explode("\t", $line);
    if (count($parts) < 6) continue;
    $entries[] = [
        'timestamp'   => $parts[0],
        'status'      => $parts[1],
        'size_bytes'  => (int)$parts[2],
        'source_disk' => $parts[3],
        'target_disk' => $parts[4],
        'path'        => $parts[5],
        'detail'      => $parts[6] ?? '',
    ];
}

// Return in reverse chronological order (newest first)
echo json_encode(array_reverse($entries));
