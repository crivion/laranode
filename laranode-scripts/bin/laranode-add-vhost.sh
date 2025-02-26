#!/bin/bash

if [ $# -lt 5 ]; then
  echo "Usage: $0 {system user} {domain} {documentRoot} {phpVersion} {template_file_path}"
  exit 1
fi

SYSTEM_USER=$1
DOMAIN=$2
DOCUMENT_ROOT=$3
PHP_VERSION=$4
TEMPLATE_FILE_PATH=$5

# Automatically append _ln to $USERNAME if not already present
if echo "$SYSTEM_USER" | grep -qv '_ln$'; then
    SYSTEM_USER+="_ln"
fi

# read template file
TEMPLATE_FILE=$(cat "$TEMPLATE_FILE_PATH")

# replace {user} and {version} in template file
TEMPLATE_FILE=$(echo "$TEMPLATE_FILE" | sed "s#{user}#$SYSTEM_USER#g")
TEMPLATE_FILE=$(echo "$TEMPLATE_FILE" | sed "s#{domain}#$DOMAIN#g")
TEMPLATE_FILE=$(echo "$TEMPLATE_FILE" | sed "s#{document_root}#$DOCUMENT_ROOT#g")
TEMPLATE_FILE=$(echo "$TEMPLATE_FILE" | sed "s#{phpVersion}#$PHP_VERSION#g")

# write template file to /etc/apache2/sites-available/{domain}.conf
echo "$TEMPLATE_FILE" > "/etc/apache2/sites-available/$DOMAIN.conf"
#echo "$TEMPLATE_FILE"

# enable vhost
echo "Enabling vhost $DOMAIN"
a2ensite "$DOMAIN"

# relaod apache
echo "Reload apache"
systemctl reload apache2
