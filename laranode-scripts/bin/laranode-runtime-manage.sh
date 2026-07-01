#!/bin/bash
set -euo pipefail

# laranode-runtime-manage.sh <action> <unit-name>
# Manages systemd lifecycle for a Laranode runtime unit.
# Called via sudo by SwitchRuntimeService.

# ---- arg validation ----
if [ $# -ne 2 ]; then
    echo "Usage: $0 <action> <unit-name>" >&2
    exit 1
fi

ACTION="$1"
UNIT="$2"

# Reject leading-dash args
case "$ACTION" in -*) echo "Invalid action: $ACTION" >&2; exit 1 ;; esac
case "$UNIT"   in -*) echo "Invalid unit: $UNIT" >&2; exit 1 ;; esac

# Validate action
if ! echo "$ACTION" | grep -qE '^(enable|disable|start|stop|restart|status|remove)$'; then
    echo "Invalid action '$ACTION'. Must be: enable|disable|start|stop|restart|status|remove" >&2
    exit 1
fi

# Validate unit name — must be a Laranode runtime unit only
# Pattern: laranode-(frankenphp|swoole)-<slug>.service
# Slug: alphanumeric, dots, hyphens, underscores — NO slashes (prevents path traversal)
if ! echo "$UNIT" | grep -qE '^laranode-(frankenphp|swoole)-[a-zA-Z0-9._-]+\.service$'; then
    echo "Invalid unit name '$UNIT'. Must match laranode-(frankenphp|swoole)-<slug>.service" >&2
    exit 1
fi
# Reject consecutive dots in unit name (punch-list #4 — prevents foo..bar.service traversal)
if echo "$UNIT" | grep -qE '\.\.'; then
    echo "Invalid unit name '$UNIT': consecutive dots not allowed." >&2
    exit 1
fi

# ---- dispatch ----
if [ "$ACTION" = "remove" ]; then
    systemctl disable --now "$UNIT" 2>/dev/null || true
    rm -f "/etc/systemd/system/$UNIT"
    systemctl daemon-reload
    echo "Removed unit $UNIT"
    exit 0
fi

systemctl "$ACTION" "$UNIT"
