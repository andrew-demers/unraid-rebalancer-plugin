#!/bin/bash
# build.sh — Build the rebalancer plugin .txz package.
#
# Usage:
#   ./build.sh [VERSION]
#
# If VERSION is omitted it defaults to today's date in YYYY.MM.DD format.
# The output file is placed at:
#   plugin/rebalancer-<VERSION>.txz
#
# Requirements (on the build host):
#   - bash, tar, find, md5sum (or md5 on macOS)
#   - makepkg is used when available (Slackware/Unraid); otherwise falls back to tar+xz.

set -euo pipefail

# ---------- Configuration ----------
SCRIPT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
REPO_ROOT="$SCRIPT_DIR"
SOURCE_DIR="$SCRIPT_DIR/source"
BUILD_DIR="$SCRIPT_DIR/build"

VERSION="${1:-$(date +%Y.%m.%d)}"
PKG_NAME="rebalancer-${VERSION}.txz"
OUT_FILE="$SCRIPT_DIR/${PKG_NAME}"

echo "=========================================="
echo "  Building Rebalancer plugin v${VERSION}"
echo "=========================================="

# ---------- Clean / create build tree ----------
rm -rf "$BUILD_DIR"
mkdir -p "$BUILD_DIR/pkg"

# ---------- Copy plugin source files ----------
echo "[1/4] Copying plugin source files..."
cp -a "$SOURCE_DIR/." "$BUILD_DIR/pkg/"

# ---------- Copy rebalancer.py ----------
echo "[2/4] Copying rebalancer.py..."
PY_DEST="$BUILD_DIR/pkg/usr/local/emhttp/plugins/rebalancer/rebalancer.py"
mkdir -p "$(dirname "$PY_DEST")"

if [ -f "$REPO_ROOT/rebalancer.py" ]; then
    cp "$REPO_ROOT/rebalancer.py" "$PY_DEST"
    chmod 755 "$PY_DEST"
    echo "      Copied from: $REPO_ROOT/rebalancer.py"
else
    echo "ERROR: rebalancer.py not found at $REPO_ROOT/rebalancer.py" >&2
    exit 1
fi

# ---------- Fix ownership / permissions ----------
echo "[3/4] Setting permissions..."
# Web files should be readable by the web server
find "$BUILD_DIR/pkg" -type f -name "*.php"  -exec chmod 644 {} \;
find "$BUILD_DIR/pkg" -type f -name "*.page" -exec chmod 644 {} \;
find "$BUILD_DIR/pkg" -type f -name "*.css"  -exec chmod 644 {} \;
find "$BUILD_DIR/pkg" -type f -name "*.py"   -exec chmod 755 {} \;
find "$BUILD_DIR/pkg" -type d -exec chmod 755 {} \;

# ---------- Build the .txz ----------
echo "[4/4] Building package: ${PKG_NAME}..."

if command -v makepkg &>/dev/null; then
    echo "      Using makepkg (Slackware)..."
    (
        cd "$BUILD_DIR/pkg"
        makepkg -l y -c y "$OUT_FILE"
    )
else
    echo "      makepkg not found — using tar+xz fallback..."
    (
        cd "$BUILD_DIR/pkg"
        # Create install/doinst.sh if it doesn't exist (required by upgradepkg)
        mkdir -p install
        if [ ! -f install/doinst.sh ]; then
            cat > install/doinst.sh <<'DOINST'
#!/bin/sh
# Post-install script for the rebalancer plugin
chmod 755 /usr/local/emhttp/plugins/rebalancer/rebalancer.py 2>/dev/null || true
DOINST
        fi
        tar --owner=root --group=root -cJf "$OUT_FILE" .
    )
fi

# ---------- Checksum ----------
echo ""
echo "=========================================="
echo "  Package built: $OUT_FILE"
echo ""

if command -v md5sum &>/dev/null; then
    MD5=$(md5sum "$OUT_FILE" | awk '{print $1}')
elif command -v md5 &>/dev/null; then
    MD5=$(md5 -q "$OUT_FILE")
else
    MD5="(md5 not available)"
fi

echo "  MD5: $MD5"
echo ""
echo "  Next steps:"
echo "  1. Upload $PKG_NAME to GitHub Releases under tag v${VERSION}"
echo "  2. Update rebalancer.plg if the version entity has changed:"
echo "     <!ENTITY version   \"${VERSION}\">"
echo "  3. Install on Unraid:"
echo "     Plugins → Install Plugin → paste the raw .plg URL"
echo "=========================================="
