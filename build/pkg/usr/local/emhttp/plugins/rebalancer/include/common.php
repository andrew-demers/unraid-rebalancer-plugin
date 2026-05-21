<?php
/**
 * common.php — Shared helpers for the Rebalancer plugin.
 */

define('STATE_DIR',  '/boot/config/plugins/rebalancer');
define('PLUGIN_DIR', '/usr/local/emhttp/plugins/rebalancer');
define('PID_FILE',   STATE_DIR . '/rebalancer.pid');
define('RUN_LOG',    STATE_DIR . '/run.log');
define('CONFIG_FILE',STATE_DIR . '/config.json');
define('DRIVES_FILE',STATE_DIR . '/drives.json');
define('DB_FILE',    STATE_DIR . '/plan.db');

/**
 * Locate python3 binary.
 */
function find_python3(): string {
    foreach (['/usr/local/bin/python3', '/usr/bin/python3'] as $p) {
        if (is_executable($p)) return $p;
    }
    return 'python3';
}

/**
 * Load config.json, merging with defaults.
 */
function get_config(): array {
    $defaults = [
        'max_used'       => 80,
        'strategy'       => 'fullest-first',
        'excludes'       => ['Backups', 'Development', 'appdata'],
        'active_hours'   => null,
        'min_free_space' => '50G',
        'bwlimit'        => null,
        'copy_timeout'   => 86400,
        'verify_timeout' => 28800,
        'lsof_timeout'   => 120,
        'remote'         => null,
    ];
    if (file_exists(CONFIG_FILE)) {
        $raw = @file_get_contents(CONFIG_FILE);
        if ($raw !== false) {
            $user = @json_decode($raw, true);
            if (is_array($user)) {
                $defaults = array_merge($defaults, $user);
            }
        }
    }
    // Coerce max_used to int
    $defaults['max_used'] = (int)$defaults['max_used'];
    return $defaults;
}

/**
 * Atomically write $data array to config.json.
 */
function save_config(array $data): bool {
    $dir = STATE_DIR;
    if (!is_dir($dir)) {
        @mkdir($dir, 0755, true);
    }
    $tmp = tempnam($dir, 'cfg_');
    if ($tmp === false) return false;
    $ok = file_put_contents($tmp, json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));
    if ($ok === false) {
        @unlink($tmp);
        return false;
    }
    return rename($tmp, CONFIG_FILE);
}

/**
 * Check whether the rebalancer process is currently running.
 */
function is_running(): bool {
    $pid = get_pid();
    if ($pid === null) return false;
    return file_exists("/proc/$pid");
}

/**
 * Read PID from PID_FILE. Returns int or null.
 */
function get_pid(): ?int {
    if (!file_exists(PID_FILE)) return null;
    $raw = trim(@file_get_contents(PID_FILE));
    if (!ctype_digit($raw) || (int)$raw <= 0) return null;
    return (int)$raw;
}

/**
 * Format bytes to human-readable string.
 */
function format_bytes(int $n): string {
    if ($n <= 0) return '0 B';
    $units = ['B','KB','MB','GB','TB','PB'];
    $i = 0;
    $v = (float)$n;
    while ($v >= 1024 && $i < count($units) - 1) {
        $v /= 1024;
        $i++;
    }
    return ($i === 0) ? "{$n} B" : sprintf('%.1f %s', $v, $units[$i]);
}

/**
 * Open plan.db in read-only mode. Returns SQLite3 instance or null on failure.
 */
function get_plan_db(): ?SQLite3 {
    if (!file_exists(DB_FILE)) return null;
    try {
        $db = new SQLite3(DB_FILE, SQLITE3_OPEN_READONLY);
        $db->busyTimeout(3000);
        return $db;
    } catch (Exception $e) {
        return null;
    }
}

/**
 * Return ['status' => count] summary from an open plan DB.
 */
function get_plan_summary(SQLite3 $db): array {
    $summary = [];
    try {
        $res = $db->query("SELECT status, COUNT(*) AS cnt FROM plan GROUP BY status");
        if ($res) {
            while ($row = $res->fetchArray(SQLITE3_ASSOC)) {
                $summary[$row['status']] = (int)$row['cnt'];
            }
        }
    } catch (Exception $e) {
        // Return whatever we have
    }
    return $summary;
}
