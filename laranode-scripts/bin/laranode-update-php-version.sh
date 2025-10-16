#!/bin/bash

# Ensure arguments
if [ $# -lt 6 ]; then
  echo "Usage: $0 {system user} {domain} {documentRoot} {phpVersion} {php_pool_template_path} {apache_vhost_template_path}"
  exit 1
fi

SYSTEM_USER=$1
DOMAIN=$2
DOCUMENT_ROOT=$3
PHP_VERSION=$4
PHP_POOL_TEMPLATE_PATH=$5
APACHE_VHOST_TEMPLATE_PATH=$6

# Automatically append _ln to system user if not already present
if echo "$SYSTEM_USER" | grep -qv '_ln$'; then
    SYSTEM_USER+="_ln"
fi

# 1) Ensure PHP-FPM pool exists (create/overwrite from template)
POOL_TEMPLATE=$(cat "$PHP_POOL_TEMPLATE_PATH")
POOL_TEMPLATE=$(echo "$POOL_TEMPLATE" | sed "s#{user}#$SYSTEM_USER#g")
POOL_TEMPLATE=$(echo "$POOL_TEMPLATE" | sed "s#{version}#$PHP_VERSION#g")

echo "$POOL_TEMPLATE" > "/etc/php/$PHP_VERSION/fpm/pool.d/$SYSTEM_USER.conf"

# Reload php-fpm for this version in background to avoid blocking
(sleep 1 && systemctl reload "php${PHP_VERSION}-fpm") >/dev/null 2>&1 &

# 2) Update Apache vhost to use the new PHP-FPM socket
VHOST_TEMPLATE=$(cat "$APACHE_VHOST_TEMPLATE_PATH")
VHOST_TEMPLATE=$(echo "$VHOST_TEMPLATE" | sed "s#{user}#$SYSTEM_USER#g")
VHOST_TEMPLATE=$(echo "$VHOST_TEMPLATE" | sed "s#{domain}#$DOMAIN#g")
VHOST_TEMPLATE=$(echo "$VHOST_TEMPLATE" | sed "s#{document_root}#$DOCUMENT_ROOT#g")
VHOST_TEMPLATE=$(echo "$VHOST_TEMPLATE" | sed "s#{phpVersion}#$PHP_VERSION#g")

echo "$VHOST_TEMPLATE" > "/etc/apache2/sites-available/$DOMAIN.conf"

# Enable site (idempotent) and reload Apache
a2ensite "$DOMAIN" >/dev/null 2>&1 || true
systemctl reload apache2

echo "Updated PHP version to $PHP_VERSION for $DOMAIN"


