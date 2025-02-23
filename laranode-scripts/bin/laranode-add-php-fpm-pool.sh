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

# read template file
TEMPLATE_FILE=$(cat "$TEMPLATE_FILE_PATH")

# replace {user} and {version} in template file
TEMPLATE_FILE=$(echo "$TEMPLATE_FILE" | sed "s/{user}/$SYSTEM_USER/g")
TEMPLATE_FILE=$(echo "$TEMPLATE_FILE" | sed "s/{version}/$PHP_VERSION/g")

# write template file to /etc/php/{version}/fpm/pool.d/pool-{systemUser}.conf
echo "$TEMPLATE_FILE" > "/etc/php/$PHP_VERSION/fpm/pool.d/$SYSTEM_USER.conf"

# reload php{version}-fpm
echo "Reloading php$PHP_VERSION-fpm..."
systemctl reload php"$PHP_VERSION"-fpm
