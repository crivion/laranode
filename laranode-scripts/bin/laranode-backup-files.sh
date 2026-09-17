#!/bin/bash
set -e

print_error() { echo -e "\033[31m$1\033[0m" >&2; }

SYSTEM_USER="$1"
DOMAIN="$2"
DEST_DIR="$3"

if [[ ! "$SYSTEM_USER" =~ ^[a-zA-Z0-9_-]+$ ]] || [[ ! "$DOMAIN" =~ ^[a-zA-Z0-9._-]+$ ]] || [[ "$DOMAIN" == *".."* ]]; then
    print_error "Invalid system user or domain"
    exit 1
fi

SRC="/home/$SYSTEM_USER/domains/$DOMAIN"
[ -d "$SRC" ] || { print_error "Website directory does not exist: $SRC"; exit 1; }
mkdir -p "$DEST_DIR"
tar --numeric-owner -czf "$DEST_DIR/$DOMAIN.tar.gz" -C "/home/$SYSTEM_USER/domains" "$DOMAIN"
chown root:www-data "$DEST_DIR/$DOMAIN.tar.gz"
chmod 640 "$DEST_DIR/$DOMAIN.tar.gz"
