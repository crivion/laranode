#!/bin/bash
set -euo pipefail

# Usage: laranode-db-backup.sh <engine> <dbName> <dbUser> <cnfFile> <outFile>
ENGINE="$1"
DB_NAME="$2"
DB_USER="$3"
CNF_FILE="$4"
OUT_FILE="$5"

# Reject any value that could be smuggled as a CLI flag to mysqldump/gzip.
for v in "$ENGINE" "$DB_NAME" "$DB_USER" "$CNF_FILE" "$OUT_FILE"; do
    case "$v" in -*) echo "Argument may not start with '-': $v" >&2; exit 1 ;; esac
done

# Strict identifier allowlist for values interpolated into the dump command.
[[ "$DB_NAME" =~ ^[A-Za-z0-9_]+$ ]] || { echo "Invalid db name: $DB_NAME" >&2; exit 1; }
[[ "$DB_USER" =~ ^[A-Za-z0-9_]+$ ]] || { echo "Invalid db user: $DB_USER" >&2; exit 1; }

case "$ENGINE" in
    mysql)
        mysqldump --defaults-extra-file="$CNF_FILE" --user="$DB_USER" \
            --single-transaction --quick --lock-tables=false -- "$DB_NAME" | gzip > "$OUT_FILE"
        ;;
    *)
        echo "Unsupported engine: $ENGINE" >&2
        exit 1
        ;;
esac
