# Proven on this machine: jrei/systemd-ubuntu:24.04 boots systemd as PID 1 under
# Docker Desktop / WSL2 (cgroup2fs). It sets STOPSIGNAL + CMD [/lib/systemd/systemd]
# and masks the noisy units for us.
FROM jrei/systemd-ubuntu:24.04

ENV DEBIAN_FRONTEND=noninteractive

# Block package post-install scripts from trying to start services during BUILD
# (no systemd running in a build layer). Runtime systemctl is unaffected.
RUN printf '#!/bin/sh\nexit 101\n' > /usr/sbin/policy-rc.d && chmod +x /usr/sbin/policy-rc.d

# Base tooling + Apache + MySQL + PostgreSQL + sysstat + ufw + certbot + the ondrej PPA.
RUN apt-get update && apt-get install -y \
        software-properties-common git curl unzip openssl ca-certificates \
        iproute2 dbus sudo \
        locales \
        apache2 \
        mysql-server \
        postgresql-16 postgresql-client-16 \
        sysstat \
        ufw \
        certbot python3-certbot-apache \
    && add-apt-repository -y ppa:ondrej/php \
    && apt-get update \
    && locale-gen en_US.UTF-8 \
    && rm -rf /var/lib/apt/lists/*

# PHP 8.4 + the exact extension set from laranode-scripts/bin/laranode-installer.sh
RUN apt-get update && apt-get install -y \
        php8.4 php8.4-fpm php8.4-cli php8.4-common php8.4-curl php8.4-mbstring \
        php8.4-xml php8.4-bcmath php8.4-zip php8.4-mysql php8.4-sqlite3 php8.4-pgsql \
        php8.4-gd php8.4-imagick php8.4-intl php8.4-readline php8.4-tokenizer php8.4-fileinfo \
        php8.4-soap php8.4-opcache \
    && rm -rf /var/lib/apt/lists/*

# Composer (php is present now) + Node 22
RUN curl -sS https://getcomposer.org/installer | php -- --install-dir=/usr/local/bin --filename=composer \
    && curl -fsSL https://deb.nodesource.com/setup_22.x | bash - \
    && apt-get install -y nodejs \
    && rm -rf /var/lib/apt/lists/*

# Apache modules + php-fpm conf, enabled at build (no service start needed for a2enmod)
RUN a2enmod proxy_fcgi rewrite setenvif headers ssl && a2enconf php8.4-fpm

# Panel system user; www-data shares its group so Apache can read panel files
RUN useradd -m -s /bin/bash laranode_ln && usermod -aG laranode_ln www-data \
    && mkdir -p /home/laranode_ln/logs

# Snapshot the privileged scripts to a Linux-native path (entrypoint copies these
# to /opt/laranode/bin with +x; the bind-mounted copies can't be relied on for exec).
COPY laranode-scripts/bin/ /opt/laranode/bin-src/
RUN chmod -R 0755 /opt/laranode/bin-src

# systemd remains PID 1 from the base image (CMD + STOPSIGNAL inherited).
