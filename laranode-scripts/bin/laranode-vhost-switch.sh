#!/bin/bash
set -euo pipefail

# laranode-vhost-switch.sh <domain> <runtime> <port> <system_user> <php_version> <document_root> <template_dir>
# Writes Apache vhost for the given runtime. Does NOT touch systemd units.
# Called via sudo by SwitchRuntimeService.

# ---- arg validation ----
if [ $# -ne 7 ]; then
    echo "Usage: $0 <domain> <runtime> <port> <system_user> <php_version> <document_root> <template_dir>" >&2
    exit 1
fi

DOMAIN="$1"
RUNTIME="$2"
PORT="$3"
SYSTEM_USER="$4"
PHP_VERSION="$5"
DOCUMENT_ROOT="$6"
TEMPLATE_DIR="$7"

# Reject leading-dash args (ALL args — punch-list #6)
for arg in "$DOMAIN" "$RUNTIME" "$PORT" "$SYSTEM_USER" "$PHP_VERSION" "$DOCUMENT_ROOT" "$TEMPLATE_DIR"; do
    case "$arg" in -*) echo "Invalid argument: $arg" >&2; exit 1 ;; esac
done

# Validate domain — no leading dot, no consecutive dots, no path traversal
if ! echo "$DOMAIN" | grep -qE '^[a-zA-Z0-9][a-zA-Z0-9.-]+$'; then
    echo "Invalid domain '$DOMAIN'." >&2
    exit 1
fi
if echo "$DOMAIN" | grep -qE '\.\.'; then
    echo "Invalid domain '$DOMAIN': consecutive dots not allowed." >&2
    exit 1
fi

# Validate runtime
if ! echo "$RUNTIME" | grep -qE '^(php-fpm|frankenphp|swoole)$'; then
    echo "Invalid runtime '$RUNTIME'. Must be: php-fpm|frankenphp|swoole" >&2
    exit 1
fi

# Validate port — must be numeric
if ! echo "$PORT" | grep -qE '^[0-9]+$'; then
    echo "Invalid port '$PORT': must be numeric." >&2
    exit 1
fi

# Port range check ONLY when runtime is not php-fpm
# (FPM revert passes port=0 which is intentionally outside range — FIXED)
if [ "$RUNTIME" != "php-fpm" ]; then
    if [ "$PORT" -lt 9100 ] || [ "$PORT" -gt 9499 ]; then
        echo "Port $PORT out of allowed range 9100–9499 for runtime '$RUNTIME'." >&2
        exit 1
    fi
fi

# Validate system_user — must end in _ln
if ! echo "$SYSTEM_USER" | grep -qE '_ln$'; then
    echo "Invalid system_user '$SYSTEM_USER': must end in _ln." >&2
    exit 1
fi

# ---- select template ----
if [ "$RUNTIME" = "frankenphp" ] || [ "$RUNTIME" = "swoole" ]; then
    TEMPLATE_FILE="$TEMPLATE_DIR/apache-vhost-frankenphp.template"
else
    # php-fpm
    TEMPLATE_FILE="$TEMPLATE_DIR/apache-vhost.template"
fi

if [ ! -f "$TEMPLATE_FILE" ]; then
    echo "Template not found: $TEMPLATE_FILE" >&2
    exit 1
fi

# ---- substitute placeholders ----
VHOST_CONTENT=$(cat "$TEMPLATE_FILE")
VHOST_CONTENT=$(echo "$VHOST_CONTENT" | sed "s#{domain}#${DOMAIN}#g")
VHOST_CONTENT=$(echo "$VHOST_CONTENT" | sed "s#{user}#${SYSTEM_USER}#g")
VHOST_CONTENT=$(echo "$VHOST_CONTENT" | sed "s#{document_root}#${DOCUMENT_ROOT}#g")
VHOST_CONTENT=$(echo "$VHOST_CONTENT" | sed "s#{port}#${PORT}#g")
# {phpVersion} is used by FPM template; FrankenPHP template has no {phpVersion} placeholder
# (FrankenPHP uses its bundled PHP). The php_version arg is accepted but unused for FrankenPHP.
VHOST_CONTENT=$(echo "$VHOST_CONTENT" | sed "s#{phpVersion}#${PHP_VERSION}#g")

# Write vhost (Apache only — no unit file here)
echo "$VHOST_CONTENT" > "/etc/apache2/sites-available/${DOMAIN}.conf"
echo "Written vhost: /etc/apache2/sites-available/${DOMAIN}.conf"

# Enable + reload Apache
a2ensite "$DOMAIN"
apache2ctl graceful
echo "Apache reloaded for $DOMAIN."
