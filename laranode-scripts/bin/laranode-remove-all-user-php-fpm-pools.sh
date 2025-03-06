#!/bin/bash

if [ -z "$1" ]; then
    echo "Usage: $0 SYSTEM_USER"
    exit 1
fi

SYSTEM_USER="$1"
count=0
affected_versions=()

# Find and process pool configs
while IFS= read -r file; do
    if [ -f "$file" ]; then
        version=$(echo "$file" | grep -oP '/etc/php/\K[0-9]+\.[0-9]+')
        echo "Removing $file"
        rm -v "$file"
        affected_versions+=("$version")
        ((count++))
    fi
done < <(find /etc/php/*/fpm/pool.d -name "${SYSTEM_USER}.conf")

echo "Removed $count pool configuration(s)"

# Restart only affected PHP-FPM versions
for version in "${affected_versions[@]}"; do
    echo "Restarting php${version}-fpm"
    #systemctl restart "php${version}-fpm"

    # get pid of this php-fpm and USR2 it
    #PID_FILE="/var/run/php/php${version}-fpm.pid"
    #kill -USR2 $(cat "$PID_FILE")

    (sleep 2 && systemctl reload "php${version}-fpm") >/dev/null 2>&1 &
done

if [ $count -eq 0 ]; then
    echo "No pool configurations found for $SYSTEM_USER"
fi
