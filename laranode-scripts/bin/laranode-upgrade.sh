#!/bin/bash

# Upgrades an existing Laranode installation to the current release.
# Safe to run repeatedly - every step checks the current state before changing anything.
#
# Usage (as root):
#   curl -sSL https://raw.githubusercontent.com/crivion/laranode/refs/heads/main/laranode-scripts/bin/laranode-upgrade.sh | bash
#   or: bash /home/laranode_ln/panel/laranode-scripts/bin/laranode-upgrade.sh

export DEBIAN_FRONTEND=noninteractive

PANEL_PATH=${PANEL_PATH:-/home/laranode_ln/panel}
PANEL_USER=${PANEL_USER:-laranode_ln}
SUDOERS_LINE="www-data ALL=(ALL) NOPASSWD: $PANEL_PATH/laranode-scripts/bin/*.sh, /usr/sbin/a2dissite, /bin/rm /etc/apache2/sites-available/*.conf, /usr/sbin/ufw"

step() {
    echo -e "\033[34m"
    echo "--------------------------------------------------------------------------------"
    echo "$1"
    echo "--------------------------------------------------------------------------------"
    echo -e "\033[0m"
}

if [ "$(id -u)" -ne 0 ]; then
    echo "Please run this script as root."
    exit 1
fi

if [ ! -d "$PANEL_PATH" ]; then
    echo "Laranode was not found in $PANEL_PATH."
    echo "Set PANEL_PATH=/path/to/panel and run this script again."
    exit 1
fi

step "Pulling the latest Laranode release"

cd "$PANEL_PATH" || exit 1
git config --global --add safe.directory "$PANEL_PATH"
git pull origin "${LARANODE_BRANCH:-main}"

step "Updating PHP dependencies"

composer install --no-interaction

step "Running database migrations"

sudo -u "$PANEL_USER" php artisan migrate --force

step "Rebuilding frontend assets"

npm install
# earlier installs chmod 660 across the panel, which leaves vite unexecutable
chmod ug+x "$PANEL_PATH"/node_modules/.bin/* "$PANEL_PATH"/vendor/bin/* 2>/dev/null
npm run build

step "Updating sudoers rules for www-data"

# older installs are missing ufw, which the firewall page needs
if grep -q "laranode-scripts/bin" /etc/sudoers; then
    TMP_SUDOERS=$(mktemp)
    cp /etc/sudoers "$TMP_SUDOERS"
    sed -i "s|^www-data ALL=.*laranode-scripts/bin.*$|$SUDOERS_LINE|" "$TMP_SUDOERS"

    if visudo -cf "$TMP_SUDOERS" > /dev/null; then
        cat "$TMP_SUDOERS" > /etc/sudoers
        echo "Sudoers rules updated"
    else
        echo "Refusing to write invalid sudoers file, leaving the current one untouched"
    fi

    rm -f "$TMP_SUDOERS"
else
    echo "$SUDOERS_LINE" >> /etc/sudoers
    echo "Sudoers rules added"
fi

step "Relaxing PHP-FPM systemd sandbox so the panel can administer the system"

bash "$PANEL_PATH/laranode-scripts/bin/laranode-fpm-sandbox.sh"

step "Fixing ownership and permissions"

chown -R "$PANEL_USER:$PANEL_USER" "$PANEL_PATH"
find "$PANEL_PATH/laranode-scripts/bin" -type f -exec chmod 100 {} +
find "$PANEL_PATH/storage" "$PANEL_PATH/bootstrap/cache" -type d -exec chmod 775 {} +
find "$PANEL_PATH/storage" "$PANEL_PATH/bootstrap/cache" -type f -exec chmod 664 {} +

step "Restarting services"

sudo -u "$PANEL_USER" php artisan config:clear
sudo -u "$PANEL_USER" php artisan cache:clear

systemctl daemon-reload
systemctl restart laranode-queue-worker.service
systemctl restart laranode-reverb.service
systemctl reload apache2

echo "================================================================================"
echo -e "\033[32m Laranode has been upgraded. \033[0m"
echo "================================================================================"
