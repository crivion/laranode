#!/bin/bash
set -euo pipefail

# laranode-runtime-unit.sh <sub-command> <domain> <port> <system_user> <document_root> <template_dir>
# Writes a systemd unit file for a Laranode runtime and reloads the daemon.
# Called via sudo by SwitchRuntimeService.

# ---- arg validation ----
if [ $# -ne 6 ]; then
    echo "Usage: $0 <sub-command> <domain> <port> <system_user> <document_root> <template_dir>" >&2
    exit 1
fi

SUBCMD="$1"
DOMAIN="$2"
PORT="$3"
SYSTEM_USER="$4"
DOCUMENT_ROOT="$5"
TEMPLATE_DIR="$6"

# Reject leading-dash args (ALL args — punch-list #6)
for arg in "$SUBCMD" "$DOMAIN" "$PORT" "$SYSTEM_USER" "$DOCUMENT_ROOT" "$TEMPLATE_DIR"; do
    case "$arg" in -*) echo "Invalid argument: $arg" >&2; exit 1 ;; esac
done

# Validate sub-command
if ! echo "$SUBCMD" | grep -qE '^(write-unit)$'; then
    echo "Invalid sub-command '$SUBCMD'. Must be: write-unit" >&2
    exit 1
fi

# Validate domain — no leading dot, no consecutive dots, no path traversal
# Must start with alphanumeric, contain only alphanumeric/dot/hyphen
if ! echo "$DOMAIN" | grep -qE '^[a-zA-Z0-9][a-zA-Z0-9.-]+$'; then
    echo "Invalid domain '$DOMAIN'." >&2
    exit 1
fi
# Reject consecutive dots (e.g. ..evil.com)
if echo "$DOMAIN" | grep -qE '\.\.'; then
    echo "Invalid domain '$DOMAIN': consecutive dots not allowed." >&2
    exit 1
fi

# Validate port — must be numeric and in range 9100–9499
if ! echo "$PORT" | grep -qE '^[0-9]+$'; then
    echo "Invalid port '$PORT': must be numeric." >&2
    exit 1
fi
if [ "$PORT" -lt 9100 ] || [ "$PORT" -gt 9499 ]; then
    echo "Port $PORT out of allowed range 9100–9499." >&2
    exit 1
fi

# Validate system_user — must end in _ln
if ! echo "$SYSTEM_USER" | grep -qE '_ln$'; then
    echo "Invalid system_user '$SYSTEM_USER': must end in _ln." >&2
    exit 1
fi

# ---- write-unit ----
TEMPLATE_FILE="$TEMPLATE_DIR/laranode-frankenphp.service.template"
UNIT_NAME="laranode-frankenphp-${DOMAIN}.service"
UNIT_PATH="/etc/systemd/system/${UNIT_NAME}"

if [ ! -f "$TEMPLATE_FILE" ]; then
    echo "Template not found: $TEMPLATE_FILE" >&2
    exit 1
fi

UNIT_CONTENT=$(cat "$TEMPLATE_FILE")
UNIT_CONTENT=$(echo "$UNIT_CONTENT" | sed "s#{user}#${SYSTEM_USER}#g")
UNIT_CONTENT=$(echo "$UNIT_CONTENT" | sed "s#{domain}#${DOMAIN}#g")
UNIT_CONTENT=$(echo "$UNIT_CONTENT" | sed "s#{port}#${PORT}#g")
UNIT_CONTENT=$(echo "$UNIT_CONTENT" | sed "s#{document_root}#${DOCUMENT_ROOT}#g")

echo "$UNIT_CONTENT" > "$UNIT_PATH"
echo "Written unit file: $UNIT_PATH"

# daemon-reload MUST happen before enable/start (FIXED: sequencing)
systemctl daemon-reload
echo "daemon-reload complete."
