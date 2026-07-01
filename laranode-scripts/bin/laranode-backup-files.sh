#!/bin/bash
set -euo pipefail

# Usage: laranode-backup-files.sh <siteRoot> <outFile> <sysUser>
SITE_ROOT="$1"
OUT_FILE="$2"
SYS_USER="$3"

# Reject any value that could be smuggled as a CLI flag to tar/chown.
for v in "$SITE_ROOT" "$OUT_FILE" "$SYS_USER"; do
    case "$v" in -*) echo "Argument may not start with '-': $v" >&2; exit 1 ;; esac
done

tar -czf "$OUT_FILE" -C "$SITE_ROOT" .
chown www-data:www-data -- "$OUT_FILE"
