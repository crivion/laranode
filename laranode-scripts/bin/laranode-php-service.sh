#!/bin/bash

# Manage PHP-FPM service (enable/disable/restart/reload)
# Usage: ./laranode-php-service.sh {action} {version}
# Example: ./laranode-php-service.sh enable 8.4

if [ $# -lt 2 ]; then
  echo "Usage: $0 {action: enable|disable|restart|reload} {php version: example 8.4}"
  exit 1
fi

ACTION=$1
PHP_VERSION=$2

# This runs as root, so validate the version before touching anything.
if [[ ! $PHP_VERSION =~ ^[0-9]+\.[0-9]+$ ]]; then
  echo "Invalid PHP version" >&2
  exit 1
fi

case $ACTION in
  enable)
    echo "Enabling PHP $PHP_VERSION-FPM service..."
    systemctl enable php${PHP_VERSION}-fpm
    systemctl start php${PHP_VERSION}-fpm
    ;;
  disable)
    echo "Disabling PHP $PHP_VERSION-FPM service..."
    systemctl stop php${PHP_VERSION}-fpm
    systemctl disable php${PHP_VERSION}-fpm
    ;;
  restart)
    echo "Restarting PHP $PHP_VERSION-FPM service..."
    systemctl restart php${PHP_VERSION}-fpm
    ;;
  reload)
    # a graceful reload replaces the workers and clears OPcache without
    # dropping in-flight requests
    echo "Reloading PHP $PHP_VERSION-FPM service..."
    systemctl reload php${PHP_VERSION}-fpm
    ;;
  *)
    echo "Invalid action: $ACTION"
    echo "Valid actions: enable, disable, restart, reload"
    exit 1
    ;;
esac

if [ $? -eq 0 ]; then
    echo "Action '$ACTION' completed successfully for PHP $PHP_VERSION-FPM"
    exit 0
else
    echo "Failed to $ACTION PHP $PHP_VERSION-FPM"
    exit 1
fi
