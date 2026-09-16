#!/bin/bash

# Install a PHP-FPM version with common extensions
# Usage: ./laranode-php-install.sh {version}
# Example: ./laranode-php-install.sh 8.4

if [ $# -lt 1 ]; then
  echo "Usage: $0 {php version: example 8.4}"
  exit 1
fi

PHP_VERSION=$1

echo "Installing PHP $PHP_VERSION-FPM..."

# Update apt cache
apt-get update -qq

# Install PHP-FPM and common extensions
apt-get install -y \
  php${PHP_VERSION}-fpm \
  php${PHP_VERSION}-cli \
  php${PHP_VERSION}-common \
  php${PHP_VERSION}-mysql \
  php${PHP_VERSION}-xml \
  php${PHP_VERSION}-curl \
  php${PHP_VERSION}-mbstring \
  php${PHP_VERSION}-zip \
  php${PHP_VERSION}-gd \
  php${PHP_VERSION}-bcmath \
  php${PHP_VERSION}-intl

if [ $? -eq 0 ]; then
    echo "PHP $PHP_VERSION installed successfully"
    
    # Enable the service
    systemctl enable php${PHP_VERSION}-fpm
    
    # Start the service
    systemctl start php${PHP_VERSION}-fpm

    # the panel administers the system through sudo from php-fpm children
    "$(dirname "$0")/laranode-fpm-sandbox.sh" ${PHP_VERSION}
    
    echo "PHP $PHP_VERSION-FPM service enabled and started"
    exit 0
else
    echo "Failed to install PHP $PHP_VERSION"
    exit 1
fi
