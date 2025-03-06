#!/bin/bash

# Check if at least two arguments are provided (system user and php version)
if [ $# -lt 1 ]; then
  echo "Usage: $0 {php version: example 8.4}"
  exit 1
fi

PHP_VERSION=$1

# reload php{version}-fpm
echo "Reloading php$PHP_VERSION-fpm..."

(sleep 2 && systemctl restart "php${PHP_VERSION}-fpm") >/dev/null 2>&1 &

