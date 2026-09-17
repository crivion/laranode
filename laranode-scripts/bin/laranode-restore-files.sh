#!/bin/bash
set -e

print_error() { echo -e "\033[31m$1\033[0m" >&2; }

SYSTEM_USER="$1"
DOMAIN="$2"
ARCHIVE_PATH="$3"

if [[ ! "$SYSTEM_USER" =~ ^[a-zA-Z0-9_-]+$ ]] || [[ ! "$DOMAIN" =~ ^[a-zA-Z0-9._-]+$ ]] || [[ "$DOMAIN" == *".."* ]]; then
    print_error "Invalid system user or domain"
    exit 1
fi
[ -f "$ARCHIVE_PATH" ] || { print_error "Archive does not exist"; exit 1; }

DOMAINS_ROOT="/home/$SYSTEM_USER/domains"
STAGE="$DOMAINS_ROOT/.restore-$$"
trap 'rm -rf "$STAGE"' EXIT
mkdir -p "$STAGE"
# GNU tar already strips leading slashes during extraction unless --absolute-names
# is explicitly requested. There is no inverse --no-absolute-names option.
tar -xzf "$ARCHIVE_PATH" --numeric-owner --no-overwrite-dir -C "$STAGE"
[ -d "$STAGE/$DOMAIN" ] || { print_error "Archive does not contain the expected domain"; exit 1; }
rm -rf "$DOMAINS_ROOT/$DOMAIN"
mv "$STAGE/$DOMAIN" "$DOMAINS_ROOT/$DOMAIN"
chown -R "$SYSTEM_USER:$SYSTEM_USER" "$DOMAINS_ROOT/$DOMAIN"
find "$DOMAINS_ROOT/$DOMAIN" -type d -exec chmod 770 {} +
find "$DOMAINS_ROOT/$DOMAIN" -type f -exec chmod 660 {} +
