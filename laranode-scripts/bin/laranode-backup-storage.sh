#!/bin/bash
set -e

ROOT="$1"
ACTION="$2"
RELATIVE="$3"

if [[ "$RELATIVE" == /* ]] || [[ "$RELATIVE" == *".."* ]] || [[ ! "$RELATIVE" =~ ^[a-zA-Z0-9_/-]+$ ]]; then
    echo "Invalid backup path" >&2
    exit 1
fi

case "$ACTION" in
    prepare)
        mkdir -p "$ROOT/$RELATIVE"
        chown -R root:www-data "$ROOT/$RELATIVE"
        chmod 770 "$ROOT/$RELATIVE"
        ;;
    remove)
        rm -rf "$ROOT/$RELATIVE"
        ;;
    *)
        echo "Usage: $0 <root> <prepare|remove> <relative-path>" >&2
        exit 1
        ;;
esac
