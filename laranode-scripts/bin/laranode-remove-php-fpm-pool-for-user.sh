#!/bin/bash

# Check if at least two arguments are provided (system user and php version)
if [ $# -lt 2 ]; then
  echo "Usage: $0 {system user} {php version}"
  exit 1
fi

SYSTEM_USER=$1
PHP_VERSION=$2

# Automatically append _ln to $USERNAME if not already present
if echo "$SYSTEM_USER" | grep -qv '_ln$'; then
    SYSTEM_USER+="_ln"
fi

# Check if the pool file exists and remove it
if [ -f "/etc/php/$PHP_VERSION/fpm/pool.d/$SYSTEM_USER.conf" ]; then
    echo "Removing pool file..."
    rm "/etc/php/$PHP_VERSION/fpm/pool.d/$SYSTEM_USER.conf"
fi

# reload php{version}-fpm
echo "Reloading php$PHP_VERSION-fpm..."

# Get the PID of PHP-FPM based on PHP version
PID_FILE="/var/run/php/php${PHP_VERSION}-fpm.pid"
#systemctl reload php"$PHP_VERSION"-fpm

kill -USR2 $(cat "$PID_FILE")
