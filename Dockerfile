# Laranode "system container"
#
# Laranode manages the machine it runs on (system users, Apache vhosts, PHP-FPM pools,
# MySQL, UFW, Let's Encrypt) and reads stats straight from systemd and sysstat.
# The image therefore mirrors laranode-scripts/bin/laranode-installer.sh and boots
# systemd as PID 1, so every service is managed exactly like on a regular VPS.

FROM ubuntu:24.04

ARG PHP_VERSION=8.4
ARG NODE_MAJOR=22

ENV container=docker \
    DEBIAN_FRONTEND=noninteractive \
    LANG=C.UTF-8 \
    LARANODE_PATH=/opt/laranode

# --------------------------------------------------------------------------------
# System packages: systemd, Apache, MySQL, PHP (ondrej PPA), sysstat, certbot, UFW
# --------------------------------------------------------------------------------
RUN apt-get update \
    && apt-get install -y --no-install-recommends \
        systemd systemd-sysv dbus ca-certificates curl gnupg software-properties-common \
        sudo git unzip openssl procps iproute2 iptables ufw less nano tzdata \
        openssh-server apache2 mysql-server sysstat certbot python3-certbot-apache \
        # app/Filesystem/safe_file.py performs every file manager mutation, so the
        # interpreter is a hard requirement, not just a certbot dependency
        python3 \
    && add-apt-repository -y ppa:ondrej/php \
    && curl -fsSL https://deb.nodesource.com/setup_${NODE_MAJOR}.x | bash - \
    && apt-get install -y --no-install-recommends \
        php${PHP_VERSION} php${PHP_VERSION}-fpm php${PHP_VERSION}-cli php${PHP_VERSION}-common \
        php${PHP_VERSION}-curl php${PHP_VERSION}-mbstring php${PHP_VERSION}-xml php${PHP_VERSION}-bcmath \
        php${PHP_VERSION}-zip php${PHP_VERSION}-mysql php${PHP_VERSION}-sqlite3 php${PHP_VERSION}-pgsql \
        php${PHP_VERSION}-gd php${PHP_VERSION}-imagick php${PHP_VERSION}-intl php${PHP_VERSION}-readline \
        php${PHP_VERSION}-soap php${PHP_VERSION}-opcache \
        nodejs \
    && apt-get clean \
    && rm -rf /var/lib/apt/lists/*

COPY --from=composer:2 /usr/bin/composer /usr/local/bin/composer

# --------------------------------------------------------------------------------
# systemd inside a container: drop units that only make sense on real hardware
# --------------------------------------------------------------------------------
RUN systemctl mask \
        systemd-udevd.service systemd-udevd-kernel.socket systemd-udevd-control.socket \
        systemd-modules-load.service systemd-remount-fs.service systemd-firstboot.service \
        sys-kernel-config.mount sys-kernel-debug.mount sys-kernel-tracing.mount \
        getty.target console-getty.service apache-htcacheclean.service \
    && systemctl set-default multi-user.target

# --------------------------------------------------------------------------------
# Service configuration (same as the installer)
# --------------------------------------------------------------------------------
RUN a2enmod proxy_fcgi rewrite setenvif headers ssl \
    && a2enconf php${PHP_VERSION}-fpm \
    && sed -i 's/ENABLED="false"/ENABLED="true"/' /etc/default/sysstat \
    # MySQL datadir and SSH host keys are generated per container on first boot
    && rm -rf /var/lib/mysql/* /etc/ssh/ssh_host_* \
    && useradd -m -s /bin/bash laranode_ln \
    && usermod -aG laranode_ln www-data \
    && mkdir -p /home/laranode_ln/logs /home/laranode_ln/domains \
    && chown -R laranode_ln:laranode_ln /home/laranode_ln \
    && chmod 770 /home/laranode_ln /home/laranode_ln/logs /home/laranode_ln/domains

COPY docker/rootfs/ /

# /etc/sudoers.d/laranode is not shipped in rootfs: it is generated further down by
# laranode-sudoers.sh, once the panel is on disk, so the image and a VPS install get
# the same explicit allowlist instead of two copies of a wildcard drifting apart
RUN chmod 755 /usr/local/sbin/laranode-* /usr/local/bin/laranode-artisan \
    && systemctl enable \
        apache2.service mysql.service php${PHP_VERSION}-fpm.service sysstat.service \
        ssh.service ufw.service certbot.timer \
        laranode-state.service laranode-state-save.timer laranode-php-restore.service \
        laranode-bootstrap.service laranode-queue-worker.service laranode-reverb.service \
        laranode-scheduler.timer

# --------------------------------------------------------------------------------
# Laranode panel
# --------------------------------------------------------------------------------
WORKDIR ${LARANODE_PATH}

COPY composer.json composer.lock ./
# dev dependencies are kept like the installer does: the database seeder uses factories (faker)
RUN composer install --no-interaction --no-scripts --no-autoloader --prefer-dist

COPY package.json ./
RUN npm install --no-audit --no-fund

COPY . .

RUN composer dump-autoload --optimize \
    # runtime state lives in volumes: .env in /var/lib/laranode, storage in its own volume
    && rm -f .env && ln -s /var/lib/laranode/.env .env \
    && rm -rf public/storage public/hot public/build \
    && mkdir -p storage bootstrap/cache \
    && chown -R www-data:www-data storage bootstrap/cache \
    && chmod -R ug+rwX storage bootstrap/cache \
    # the same allowlist the installer writes on a VPS, pointed at the image's panel
    # path - naming each script keeps a writable bin/ from becoming a root shell
    && bash laranode-scripts/bin/laranode-sudoers.sh ${LARANODE_PATH} \
    && chmod 700 laranode-scripts/bin/*.sh

EXPOSE 80 443 8080 22

HEALTHCHECK --interval=30s --timeout=5s --start-period=180s --retries=5 \
    CMD curl -fsS http://127.0.0.1/up > /dev/null || exit 1

STOPSIGNAL SIGRTMIN+3

CMD ["/sbin/init"]
