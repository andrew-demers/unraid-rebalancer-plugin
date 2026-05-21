# Unraid Rebalancer Plugin

Web GUI plugin for Unraid that rebalances data across disk array drives. Moves folders from overloaded disks to underloaded ones using rsync with checksum verification — all from your browser, no terminal required.

## Screenshots

| Disk Overview | Plan Summary | Settings |
|---|---|---|
| Color-coded usage bars per disk | Pending / completed / error counts | All config options in one form |

## Features

- **Disk overview** — color-coded usage bars for every array disk (green < 70%, yellow 70–80%, orange 80–90%, red > 90%)
- **One-click rebalance** — Start, Stop, Dry Run, and Retry Errors buttons
- **Live status** — auto-polls every 5 seconds while a job is running; shows the current transfer in real time
- **Dry run** — preview exactly what would move before committing
- **Transfer log** — built-in viewer for completed/failed transfers, filterable by line count
- **Full settings UI** — max used %, strategy, excluded shares, active hours, bandwidth limit, min free space, and timeouts — all saved to `config.json`
- **Safe** — same three-phase transfer as the CLI (rsync copy → checksum verify → delete source); source is never deleted unless verification passes

## Requirements

- Unraid 6.9.0 or later
- Python 3.10+ — install via [NerdTools](https://forums.unraid.net/topic/35866-unraid-6-nerdtools/) in Community Applications
- rsync and lsof (both included with Unraid)

## Installation

### From Community Applications (recommended)

Search for **Rebalancer** in the Community Applications plugin store.

### Manual install

1. In Unraid's web UI, go to **Plugins → Install Plugin**
2. Paste the raw URL to `rebalancer.plg`:
   ```
   https://raw.githubusercontent.com/ADemers-tricentis/unraid-rebalancer/main/plugin/rebalancer.plg
   ```
3. Click **Install**

The plugin installs under **Utilities → Rebalancer**.

## Usage

### Quick start

1. Open **Utilities → Rebalancer** in the Unraid web UI
2. Click **Dry Run** to preview what would move — no data is touched
3. Review the disk overview and plan summary
4. Click **Scan & Rebalance** to start

### Stopping a running job

Click **Stop Gracefully**. The current transfer will finish cleanly before the process exits. Data is safe at all times — if interrupted mid-copy, the next run resumes from where it left off.

### Retrying errors

If some transfers failed (disk full, file in use, etc.), click **Retry Errors** to reset them to pending and try again on the next run.

### Force rescan

Check **Force rescan (discard plan)** before starting to throw away the existing plan and rebuild it from scratch. Use this after adding or removing a disk from the array.

## Settings

All settings are under **Utilities → Rebalancer → Settings** and are saved to `/boot/config/plugins/rebalancer/config.json`.

| Setting | Default | Description |
|---------|---------|-------------|
| Max Used % | 80 | Disks above this threshold have data moved off them |
| Strategy | fullest-first | Order in which units are selected for transfer |
| Excluded Shares | Backups, Development, appdata | Share names to skip entirely |
| Active Hours | — | Restrict transfers to a time window (e.g. `22:00–06:00`) |
| Bandwidth Limit | — | rsync KB/s cap — blank = unlimited (50 000 ≈ 50 MB/s) |
| Min Free Space | 50G | Minimum free space that must remain on the target disk |
| Copy Timeout | 86400 | Max seconds for rsync copy phase per transfer (24 h) |
| Verify Timeout | 28800 | Max seconds for rsync verify phase per transfer (8 h) |
| lsof Timeout | 120 | Max seconds for open-file checks |

### Strategies

| Strategy | Behaviour |
|----------|-----------|
| `fullest-first` | Empties the most-loaded disk first, moving its largest folders — recommended |
| `largest-first` | Moves the largest items first across all overloaded disks |
| `smallest-first` | Moves the smallest items first |
| `auto` | Runs all three strategies and picks the one with fewest total bytes moved |

### Excluded shares

Add any share whose files must not move. Common ones to exclude:

- `appdata` — Docker container data; containers map paths to specific disks
- `system` — VM and system files
- `domains` — VM images
- `isos` — installation media (optional; low-risk but rarely needs rebalancing)

## State files

All state lives in `/boot/config/plugins/rebalancer/` on the USB boot drive (survives reboots).

| File | Description |
|------|-------------|
| `config.json` | Persistent settings |
| `plan.db` | SQLite transfer plan (WAL mode, crash-safe) |
| `drives.json` | Disk usage snapshot from last scan |
| `transfers.log` | Append-only TSV log of every completed or failed transfer |
| `run.log` | stdout/stderr from the most recent run |
| `rebalancer.pid` | PID of the running process (removed on stop) |

## Also available as a CLI

If you prefer the terminal, `rebalancer.py` can be run directly — see the [root README](../README.md) for full CLI documentation including remote mode (run from your Mac over SSH), `--status`, `--show-plan`, `--export-csv`, and more.

## Building from source

```bash
# Requires bash, tar, xz — works on macOS and Linux
cd plugin
./build.sh [VERSION]
# Outputs: plugin/rebalancer-<VERSION>.txz + MD5
```

Upload the `.txz` to a GitHub Release, then update the `version` entity in `rebalancer.plg` to match.

## License

MIT — see [LICENSE](../LICENSE).
