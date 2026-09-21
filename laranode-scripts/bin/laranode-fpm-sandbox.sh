#!/bin/bash

# The php*-fpm units shipped by the ondrej PPA run with ProtectSystem=full, which mounts
# /etc (and /usr) read-only for php-fpm and every process it spawns. Laranode administers
# the system through sudo from those children - useradd, Apache vhosts, FPM pools, ufw and
# apt installs - so without this they fail with errors like "cannot lock /etc/passwd".
# Pools still run as unprivileged per-site users, so this grants websites nothing they
# could not already do.
#
# Usage: ./laranode-fpm-sandbox.sh [php version: example 8.4]
#        without a version, every installed PHP-FPM version is updated

PHP_VERSION=$1
changed=0

if [ -n "$PHP_VERSION" ]; then
    units="/usr/lib/systemd/system/php${PHP_VERSION}-fpm.service"
else
    units=$(ls /usr/lib/systemd/system/php*-fpm.service 2>/dev/null)
fi

for unit in $units; do
    [ -e "$unit" ] || continue

    UNIT_NAME=$(basename "$unit")
    DROPIN="/etc/systemd/system/$UNIT_NAME.d/laranode.conf"

    mkdir -p "$(dirname "$DROPIN")"

    # written to a temp file first so an older override from a previous release is
    # replaced rather than kept
    cat > "$DROPIN.new" <<EOF
# Laranode administers the system through sudo from PHP-FPM children, which the
# unit's ProtectSystem=full would block. See laranode-fpm-sandbox.sh
[Service]
ProtectSystem=false
ProtectKernelTunables=false
ProtectKernelModules=false
# PrivateDevices/RestrictNamespaces/RestrictRealtime in the unit make systemd imply
# NoNewPrivileges, which stops sudo dead: "the no new privileges flag is set"
NoNewPrivileges=false
EOF

    if cmp -s "$DROPIN.new" "$DROPIN"; then
        rm -f "$DROPIN.new"
        continue
    fi

    mv "$DROPIN.new" "$DROPIN"
    echo "Relaxed systemd sandbox for $UNIT_NAME"
    changed=1
done

if [ "$changed" = "1" ]; then
    systemctl daemon-reload

    for unit in $units; do
        [ -e "$unit" ] || continue
        # try-restart covers units that are still starting up, which "is-active"
        # reports as inactive, and does nothing for units that are stopped
        systemctl try-restart "$(basename "$unit")"
    done
fi

exit 0
