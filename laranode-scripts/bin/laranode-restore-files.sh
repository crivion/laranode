#!/bin/bash
set -euo pipefail

# Usage: laranode-restore-files.sh <tarFile> <destDir> <sysUser>
TAR_FILE="$1"
DEST_DIR="$2"
SYS_USER="$3"

# Reject any value that could be smuggled as a CLI flag to tar/chown.
for v in "$TAR_FILE" "$DEST_DIR" "$SYS_USER"; do
    case "$v" in -*) echo "Argument may not start with '-': $v" >&2; exit 1 ;; esac
done
# System user must be a plain account name (no path/colon).
[[ "$SYS_USER" =~ ^[A-Za-z0-9_]+$ ]] || { echo "Invalid system user: $SYS_USER" >&2; exit 1; }

mkdir -p -- "$DEST_DIR"
# Harden extraction against ownership/permission abuse and path traversal.
# GNU tar strips leading '/' and refuses members escaping the destination by default;
# these flags additionally prevent restoring archived ownership/permissions or
# clobbering existing directory metadata.
tar --no-same-owner --no-same-permissions --no-overwrite-dir \
    -xzf "$TAR_FILE" -C "$DEST_DIR"
chown -R -- "$SYS_USER:$SYS_USER" "$DEST_DIR"
