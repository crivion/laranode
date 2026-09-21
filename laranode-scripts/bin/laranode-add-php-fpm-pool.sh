#!/bin/bash

# Check if at least two arguments are provided (system user and php version)
if [ $# -lt 3 ]; then
  echo "Usage: $0 {system user} {php version} {template_file_path}"
  exit 1
fi

SYSTEM_USER=$1
PHP_VERSION=$2
TEMPLATE_FILE_PATH=$3

# Automatically append _ln to $USERNAME if not already present
if echo "$SYSTEM_USER" | grep -qv '_ln$'; then
    SYSTEM_USER+="_ln"
fi

# This runs as root, so validate the arguments before touching anything.
if [[ ! $SYSTEM_USER =~ ^[A-Za-z0-9_-]+$ ]]; then
  echo "Invalid system user" >&2
  exit 1
fi

if [[ ! $PHP_VERSION =~ ^[0-9]+\.[0-9]+$ ]]; then
  echo "Invalid PHP version" >&2
  exit 1
fi

if [ ! -f "$TEMPLATE_FILE_PATH" ]; then
  echo "Template file not found" >&2
  exit 1
fi

# read template file
TEMPLATE_FILE=$(cat "$TEMPLATE_FILE_PATH")

# replace {user} and {version} in template file; quoted replacements are
# always literal, unlike values interpolated into a sed program
TEMPLATE_FILE=${TEMPLATE_FILE//'{user}'/"$SYSTEM_USER"}
TEMPLATE_FILE=${TEMPLATE_FILE//'{version}'/"$PHP_VERSION"}


# write template file to /etc/php/{version}/fpm/pool.d/pool-{systemUser}.conf
printf '%s\n' "$TEMPLATE_FILE" > "/etc/php/$PHP_VERSION/fpm/pool.d/$SYSTEM_USER.conf"

# Get the PID of PHP-FPM based on PHP version
PID_FILE="/var/run/php/php${PHP_VERSION}-fpm.pid"

# reload php{version}-fpm
echo "Reloading php$PHP_VERSION-fpm..."

# restart in bg to avoid current request issues in the browser
(sleep 2 && systemctl reload "php${PHP_VERSION}-fpm") >/dev/null 2>&1 &
