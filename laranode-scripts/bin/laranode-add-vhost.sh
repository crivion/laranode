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

# This runs as root with tenant-supplied values, so validate every argument
# before touching anything. These mirror the checks in CreateWebsiteRequest.
USER_REGEX='^[A-Za-z0-9_-]+$'
DOMAIN_REGEX='^([A-Za-z0-9]([A-Za-z0-9-]*[A-Za-z0-9])?\.)+[A-Za-z]{2,}$'
DOCUMENT_ROOT_REGEX='^/([A-Za-z0-9_-][A-Za-z0-9._-]*(/[A-Za-z0-9_-][A-Za-z0-9._-]*)*/?)?$'
PHP_VERSION_REGEX='^[0-9]+\.[0-9]+$'

if [[ ! $SYSTEM_USER =~ $USER_REGEX ]]; then
  echo "Invalid system user" >&2
  exit 1
fi

if [[ ${#DOMAIN} -gt 255 || ! $DOMAIN =~ $DOMAIN_REGEX ]]; then
  echo "Invalid domain" >&2
  exit 1
fi

if [[ ${#DOCUMENT_ROOT} -gt 255 || ! $DOCUMENT_ROOT =~ $DOCUMENT_ROOT_REGEX ]]; then
  echo "Invalid document root" >&2
  exit 1
fi

if [[ ! $PHP_VERSION =~ $PHP_VERSION_REGEX ]]; then
  echo "Invalid PHP version" >&2
  exit 1
fi

if [ ! -f "$TEMPLATE_FILE_PATH" ]; then
  echo "Template file not found" >&2
  exit 1
fi

# read template file
TEMPLATE_FILE=$(cat "$TEMPLATE_FILE_PATH")

# Replace placeholders with bash string substitution rather than sed: the
# replacement is quoted, so it is always literal data and never parsed as
# part of a program (sed's "e" command used to allow running commands here).
TEMPLATE_FILE=${TEMPLATE_FILE//'{user}'/"$SYSTEM_USER"}
TEMPLATE_FILE=${TEMPLATE_FILE//'{domain}'/"$DOMAIN"}
TEMPLATE_FILE=${TEMPLATE_FILE//'{document_root}'/"$DOCUMENT_ROOT"}
TEMPLATE_FILE=${TEMPLATE_FILE//'{phpVersion}'/"$PHP_VERSION"}

# write template file to /etc/apache2/sites-available/{domain}.conf
printf '%s\n' "$TEMPLATE_FILE" > "/etc/apache2/sites-available/$DOMAIN.conf"

# enable vhost
echo "Enabling vhost $DOMAIN"
a2ensite "$DOMAIN"

# relaod apache
echo "Reload apache"
systemctl reload apache2
