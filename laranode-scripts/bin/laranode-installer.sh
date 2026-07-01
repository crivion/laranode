#!/bin/bash

# Exit on any error
# set -e

export DEBIAN_FRONTEND=noninteractive

# Where the panel lives, and where to clone it from. LARANODE_REPO is overridable
# so the clean-room installer test can inject a local checkout instead of GitHub.
PANEL_PATH=/home/laranode_ln/panel
LARANODE_REPO="${LARANODE_REPO:-https://github.com/alexandre433/laranode.git}"

# ==============================================================================
# 1. System packages (no repo needed yet)
# ==============================================================================

echo -e "\033[34m"
echo "--------------------------------------------------------------------------------"
echo "Installing software-properties-common and git"
echo "--------------------------------------------------------------------------------"
echo -e "\033[0m"

apt update
# curl/ca-certificates/sudo are used throughout but aren't guaranteed on a bare image
apt install -y software-properties-common git curl ca-certificates sudo openssl

echo -e "\033[34m"
echo "--------------------------------------------------------------------------------"
echo "Installing Apache Web Server"
echo "--------------------------------------------------------------------------------"
echo -e "\033[0m"

apt install -y apache2

echo -e "\033[34m"
echo "--------------------------------------------------------------------------------"
echo "Installing Sysstat"
echo "--------------------------------------------------------------------------------"
echo -e "\033[0m"

apt-get install -y sysstat
systemctl enable sysstat

echo -e "\033[34m"
echo "--------------------------------------------------------------------------------"
echo "Enabling and starting apache2"
echo "--------------------------------------------------------------------------------"
echo -e "\033[0m"

systemctl enable apache2
systemctl start apache2

echo -e "\033[34m"
echo "--------------------------------------------------------------------------------"
echo "Installing MySQL Server"
echo "--------------------------------------------------------------------------------"
echo -e "\033[0m"

apt install -y mysql-server
systemctl enable mysql
systemctl start mysql

echo -e "\033[34m"
echo "--------------------------------------------------------------------------------"
echo "Creating Laranode MySQL User & Database"
echo "--------------------------------------------------------------------------------"
echo -e "\033[0m"

LARANODE_RANDOM_PASS=$(openssl rand -base64 12)
ROOT_RANDOM_PASS=$(openssl rand -base64 12)

mysql -u root -e "CREATE USER 'laranode'@'localhost' IDENTIFIED BY '$LARANODE_RANDOM_PASS';"
mysql -u root -e "GRANT ALL PRIVILEGES ON *.* TO 'laranode'@'localhost' WITH GRANT OPTION;"
mysql -u root -e "FLUSH PRIVILEGES;"
mysql -u root -e "CREATE DATABASE laranode;"
mysql -u root -e "ALTER USER 'root'@'localhost' IDENTIFIED BY '$ROOT_RANDOM_PASS';"

echo -e "\033[34m"
echo "--------------------------------------------------------------------------------"
echo "Adding ppa:ondrej/php"
echo "--------------------------------------------------------------------------------"
echo -e "\033[0m"
add-apt-repository -y ppa:ondrej/php

echo -e "\033[34m"
echo "--------------------------------------------------------------------------------"
echo "Running apt update"
echo "--------------------------------------------------------------------------------"
echo -e "\033[0m"
apt update

echo -e "\033[34m"
echo "--------------------------------------------------------------------------------"
echo "Installing php8.4 and required extensions"
echo "--------------------------------------------------------------------------------"
echo -e "\033[0m"
apt install -y php8.4 php8.4-fpm php8.4-cli php8.4-common php8.4-curl php8.4-mbstring \
               php8.4-xml php8.4-bcmath php8.4-zip php8.4-mysql php8.4-sqlite3 php8.4-pgsql \
               php8.4-gd php8.4-imagick php8.4-intl php8.4-readline php8.4-tokenizer php8.4-fileinfo \
               php8.4-soap php8.4-opcache unzip curl

echo -e "\033[34m"
echo "--------------------------------------------------------------------------------"
echo "Enabling and starting PHP-FPM"
echo "--------------------------------------------------------------------------------"
echo -e "\033[0m"

systemctl enable php8.4-fpm
systemctl start php8.4-fpm

echo -e "\033[34m"
echo "--------------------------------------------------------------------------------"
echo "Enabling required apache modules"
echo "--------------------------------------------------------------------------------"
echo -e "\033[0m"
a2enmod proxy_fcgi
a2enmod rewrite
a2enmod setenvif
a2enmod headers
a2enmod ssl
a2enmod proxy proxy_http
a2enconf php8.4-fpm

echo -e "\033[34m"
echo "--------------------------------------------------------------------------------"
echo "Installing certbot"
echo "--------------------------------------------------------------------------------"
echo -e "\033[0m"
apt -y install certbot python3-certbot-apache

echo -e "\033[34m"
echo "--------------------------------------------------------------------------------"
echo "Installing PostgreSQL server + client"
echo "--------------------------------------------------------------------------------"
echo -e "\033[0m"
apt install -y postgresql postgresql-client

echo -e "\033[34m"
echo "--------------------------------------------------------------------------------"
echo "Installing Composer"
echo "--------------------------------------------------------------------------------"
echo -e "\033[0m"
curl -sS https://getcomposer.org/installer | php -- --install-dir=/usr/local/bin --filename=composer

echo -e "\033[34m"
echo "--------------------------------------------------------------------------------"
echo "Installing NodeJS"
echo "--------------------------------------------------------------------------------"
echo -e "\033[0m"
curl -fsSL https://deb.nodesource.com/setup_22.x | sudo -E bash -
sudo apt install -y nodejs

# ==============================================================================
# 2. Fetch the panel (must happen before anything that reads repo files)
# ==============================================================================

echo -e "\033[34m"
echo "--------------------------------------------------------------------------------"
echo "Creating Laranode User"
echo "--------------------------------------------------------------------------------"
echo -e "\033[0m"
useradd -m -s /bin/bash laranode_ln 2>/dev/null || true
usermod -aG laranode_ln www-data

echo -e "\033[34m"
echo "--------------------------------------------------------------------------------"
echo "Cloning Laranode from ${LARANODE_REPO}"
echo "--------------------------------------------------------------------------------"
echo -e "\033[0m"
# Idempotent: skip if a checkout is already present (the installer test injects one).
if [ ! -d "${PANEL_PATH}/laranode-scripts" ]; then
    git clone "${LARANODE_REPO}" "${PANEL_PATH}"
else
    echo "Repo already present at ${PANEL_PATH}, skipping clone."
fi

echo -e "\033[34m"
echo "--------------------------------------------------------------------------------"
echo "Installing PHP dependencies + generating app key"
echo "--------------------------------------------------------------------------------"
echo -e "\033[0m"
cd "${PANEL_PATH}"
composer install --no-interaction
[ -f .env ] || cp .env.example .env
sed -i "s#DB_PASSWORD=.*#DB_PASSWORD=\"$LARANODE_RANDOM_PASS\"#" ".env"
sed -i "s#APP_URL=.*#APP_URL=\"http://$(curl -s icanhazip.com)\"#" ".env"
php artisan key:generate --force

# ==============================================================================
# 3. Privileged plumbing that depends on the repo + .env existing
# ==============================================================================

echo -e "\033[34m"
echo "--------------------------------------------------------------------------------"
echo "Installing sudoers drop-ins for www-data"
echo "--------------------------------------------------------------------------------"
echo -e "\033[0m"

for drop in laranode-panel laranode-cron laranode-runtimes laranode-ufw; do
    SRC="${PANEL_PATH}/laranode-scripts/etc/sudoers.d/${drop}"
    if ! visudo -c -f "${SRC}"; then
        echo "ERROR: sudoers file ${drop} failed syntax check — aborting install" >&2
        exit 1
    fi
    install -m 440 "${SRC}" "/etc/sudoers.d/${drop}"
done
# Remove legacy monolithic drop-in if it exists
rm -f /etc/sudoers.d/laranode-postgres

echo -e "\033[34m"
echo "--------------------------------------------------------------------------------"
echo "Provisioning PostgreSQL stats-reader role (laranode_pg_reader)"
echo "--------------------------------------------------------------------------------"
echo -e "\033[0m"

# Enable and start the versioned unit for Ubuntu 24.04 (so it survives reboots)
systemctl enable --now postgresql@16-main 2>/dev/null || systemctl enable --now postgresql || true

# Generate a random password for the stats-reader role and write it to .env
PGSQL_READER_PASS=$(openssl rand -base64 18)
PGSQL_PG_TAG=$(head -c 16 /dev/urandom | base64 | tr -dc 'a-z' | head -c 8)
sudo -u postgres psql -v ON_ERROR_STOP=1 --dbname=postgres <<SQL
DO \$\$
BEGIN
    IF NOT EXISTS (SELECT 1 FROM pg_roles WHERE rolname = 'laranode_pg_reader') THEN
        CREATE ROLE laranode_pg_reader LOGIN;
    END IF;
END\$\$;
ALTER ROLE laranode_pg_reader PASSWORD \$${PGSQL_PG_TAG}\$${PGSQL_READER_PASS}\$${PGSQL_PG_TAG}\$;
GRANT CONNECT ON DATABASE postgres TO laranode_pg_reader;
GRANT pg_read_all_stats TO laranode_pg_reader;
SQL

# Write the generated password into .env so the pgsql_admin connection can authenticate
sed -i "s#^PGSQL_PASSWORD=.*#PGSQL_PASSWORD=\"${PGSQL_READER_PASS}\"#" "${PANEL_PATH}/.env" || \
    echo "PGSQL_PASSWORD=\"${PGSQL_READER_PASS}\"" >> "${PANEL_PATH}/.env"

# ==============================================================================
# 4. App provisioning: DB, assets, reverb, GPU, vhost
# ==============================================================================

echo -e "\033[34m"
echo "--------------------------------------------------------------------------------"
echo "Migrating database, seeding, building assets"
echo "--------------------------------------------------------------------------------"
echo -e "\033[0m"
php artisan migrate --force
php artisan db:seed --force
php artisan storage:link
php artisan reverb:install --no-interaction
php artisan laranode:detect-gpu

sed -i "s#VITE_REVERB_HOST=.*#VITE_REVERB_HOST=$(curl -s icanhazip.com)#" "${PANEL_PATH}/.env"
sed -i "s#REVERB_HOST=.*#REVERB_HOST=$(curl -s icanhazip.com)#" "${PANEL_PATH}/.env"

cp "${PANEL_PATH}/laranode-scripts/templates/apache2-default.template" /etc/apache2/sites-available/000-default.conf

echo -e "\033[34m"
echo "--------------------------------------------------------------------------------"
echo "Hold tight, pouring node_modules with npm install & compiling assets"
echo "--------------------------------------------------------------------------------"
echo -e "\033[0m"
npm install
npm run build

# ==============================================================================
# 5. Services, firewall, permissions
# ==============================================================================

echo -e "\033[34m"
echo "--------------------------------------------------------------------------------"
echo "Adding systemd services (queue worker and reverb)"
echo "--------------------------------------------------------------------------------"
echo -e "\033[0m"

cp "${PANEL_PATH}/laranode-scripts/templates/laranode-queue-worker.service" /etc/systemd/system/laranode-queue-worker.service
cp "${PANEL_PATH}/laranode-scripts/templates/laranode-reverb.service" /etc/systemd/system/laranode-reverb.service

echo -e "\033[34m"
echo "--------------------------------------------------------------------------------"
echo "Adding default UFW rules for SSH | HTTP | HTTPS | REVERB WEBSOCKETS"
echo "--------------------------------------------------------------------------------"
echo -e "\033[0m"
ufw allow 22
ufw allow 80
ufw allow 443
ufw allow 8080

echo -e "\033[34m"
echo "--------------------------------------------------------------------------------"
echo "Setting permissions"
echo "--------------------------------------------------------------------------------"
echo -e "\033[0m"
mkdir -p /home/laranode_ln/logs
chown -R laranode_ln:laranode_ln /home/laranode_ln
find /home/laranode_ln -type d -exec chmod 770 {} \;
find /home/laranode_ln -type f -exec chmod 660 {} \;
find /home/laranode_ln/panel/laranode-scripts/bin -type f -exec chmod 100 {} \;
find /home/laranode_ln/panel/storage /home/laranode_ln/panel/bootstrap -type d -exec chmod 775 {} \;

systemctl daemon-reload
systemctl enable laranode-queue-worker.service
systemctl enable laranode-reverb.service
systemctl start laranode-queue-worker.service
systemctl start laranode-reverb.service
systemctl restart apache2
systemctl restart php8.4-fpm

echo "================================================================================"
echo "================================================================================"
echo -e "\033[32m --- NOTES ---\033[0m"

echo "MySQL Root Password: $ROOT_RANDOM_PASS"
echo "Laranode MySQL Username: laranode"
echo "Laranode MySQL Password: $LARANODE_RANDOM_PASS"

echo -e "\033[32m --- IMPORTANT ---\033[0m"

echo "Final Step: Now create an admin account for Laranode by running the following command:"
echo -e "\033[33m cd /home/laranode_ln/panel && php artisan laranode:create-admin \033[0m"

echo "================================================================================"
echo "================================================================================"
