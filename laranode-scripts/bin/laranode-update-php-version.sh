#!/bin/bash

if [ $# -lt 3 ]; then
  echo "Usage: $0 {domain} {currentPhpVersion} {newPhpVersion}"
  exit 1
fi

DOMAIN=$1
CURRENT_PHP_VERSION=$2
NEW_PHP_VERSION=$3

# These values are interpolated into sed programs below, so only allow
# plain domains and "X.Y" versions.
if [[ ! $DOMAIN =~ ^([A-Za-z0-9]([A-Za-z0-9-]*[A-Za-z0-9])?\.)+[A-Za-z]{2,}$ ]]; then
  echo "Invalid domain" >&2
  exit 1
fi

if [[ ! $CURRENT_PHP_VERSION =~ ^[0-9]+\.[0-9]+$ || ! $NEW_PHP_VERSION =~ ^[0-9]+\.[0-9]+$ ]]; then
  echo "Invalid PHP version" >&2
  exit 1
fi

# read vhost file
VHOST_FILE="/etc/apache2/sites-available/$DOMAIN.conf"
VHOST_FILE=$(cat "$VHOST_FILE")

# replace {user} and {version} in template file
VHOST_FILE=$(echo "$VHOST_FILE" | sed "s#$CURRENT_PHP_VERSION#$NEW_PHP_VERSION#g")

# read ssl-vhost file
SSL_VHOST_FILE="/etc/apache2/sites-available/$DOMAIN-ssl.conf"
SSL_VHOST_FILE=$(cat "$SSL_VHOST_FILE")

# replace {user} and {version} in SSL template file
SSL_VHOST_FILE=$(echo "$SSL_VHOST_FILE" | sed "s#$CURRENT_PHP_VERSION#$NEW_PHP_VERSION#g")

# write template file to /etc/apache2/sites-available/{domain}.conf
echo "$VHOST_FILE" > "/etc/apache2/sites-available/$DOMAIN.conf"
echo "Currently on $CURRENT_PHP_VERSION"
echo "Switching to $NEW_PHP_VERSION"
echo "$VHOST_FILE"

# reaload apache
echo "Reload apache"
systemctl reload apache2
