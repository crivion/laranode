#!/bin/bash
set -e

print_error() { echo -e "\033[31m$1\033[0m" >&2; }

DB_NAME="$1"
DEST_DIR="$2"
DEFAULTS_FILE="$3"

[[ "$DB_NAME" =~ ^[a-zA-Z0-9_]+$ ]] || { print_error "Invalid database name"; exit 1; }
[ -f "$DEFAULTS_FILE" ] || { print_error "MySQL defaults file does not exist"; exit 1; }
mkdir -p "$DEST_DIR"
mysqldump --defaults-extra-file="$DEFAULTS_FILE" --single-transaction --quick --no-tablespaces \
    --routines --triggers --events --default-character-set=utf8mb4 --set-gtid-purged=OFF "$DB_NAME" \
    | gzip > "$DEST_DIR/$DB_NAME.sql.gz"
chown root:www-data "$DEST_DIR/$DB_NAME.sql.gz"
chmod 640 "$DEST_DIR/$DB_NAME.sql.gz"
