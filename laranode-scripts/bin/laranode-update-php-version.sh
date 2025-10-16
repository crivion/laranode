#!/bin/bash

if [ $# -lt 5 ]; then
  echo "Usage: $0 {domain} {phpVersion}"
  exit 1
fi

DOMAIN=$1
PHP_VERSION=$2


# read vhost file
VHOST_FILE="/etc/apache2/sites-available/$DOMAIN.conf"

# replace {user} and {version} in template file
VHOST_FILE=$(echo "$VHOST_FILE" | sed "s#{phpVersion}#$PHP_VERSION#g")

# write template file to /etc/apache2/sites-available/{domain}.conf
#echo "$VHOST_FILE" > "/etc/apache2/sites-available/$DOMAIN.conf"
echo "$VHOST_FILE"


# reaload apache
echo "Reload apache"
systemctl reload apache2