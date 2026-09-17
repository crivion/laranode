#!/bin/bash
set -e

print_error() { echo -e "\033[31m$1\033[0m" >&2; }

DB_NAME="$1"
ARCHIVE_PATH="$2"
DEFAULTS_FILE="$3"

[[ "$DB_NAME" =~ ^[a-zA-Z0-9_]+$ ]] || { print_error "Invalid database name"; exit 1; }
[ -f "$ARCHIVE_PATH" ] || { print_error "Database archive does not exist"; exit 1; }
[ -f "$DEFAULTS_FILE" ] || { print_error "MySQL defaults file does not exist"; exit 1; }
gunzip -c "$ARCHIVE_PATH" | mysql --defaults-extra-file="$DEFAULTS_FILE" "$DB_NAME"
