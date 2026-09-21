#!/bin/bash

# Upgrades an existing Laranode installation to the current release.
# Safe to run repeatedly - every step checks the current state before changing anything.
#
# Usage (as root):
#   curl -sSL https://raw.githubusercontent.com/crivion/laranode/refs/heads/main/laranode-scripts/bin/laranode-upgrade.sh | bash
#   or: bash /home/laranode_ln/panel/laranode-scripts/bin/laranode-upgrade.sh
#
# Prefer the first form. The copy on disk is from the release you are upgrading
# FROM, so it cannot contain fixes to the upgrade process itself - installs
# predating the core.fileMode handling below will not upgrade with their local
# copy, and the curl form fetches a script that can.

export DEBIAN_FRONTEND=noninteractive

PANEL_PATH=${PANEL_PATH:-/home/laranode_ln/panel}
PANEL_USER=${PANEL_USER:-laranode_ln}

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

# A deployed tree is not a clean checkout: the installer and the permissions step
# below chmod these files at runtime (100 for the scripts, 640/770 elsewhere), and
# git tracks the executable bit. Any script whose committed mode disagrees with its
# deployed mode therefore reads as modified, and "git pull" refuses to run - which
# is how an install could sit on an old release while this script still reported
# success. The modes are this script's business, not git's.
git config core.fileMode false

if ! git pull origin "${LARANODE_BRANCH:-main}"; then
    echo -e "\033[31m"
    echo "--------------------------------------------------------------------------------"
    echo "Could not pull the latest release. Stopping before anything is changed - the"
    echo "panel is untouched and still running the version it was already on."
    echo ""
    echo "Inspect what is in the way with:"
    echo "  cd $PANEL_PATH && git status"
    echo "--------------------------------------------------------------------------------"
    echo -e "\033[0m"
    exit 1
fi

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

# replaces the old appended bin/*.sh wildcard with an explicit allowlist in
# /etc/sudoers.d, and removes the legacy line - see laranode-sudoers.sh
bash "$PANEL_PATH/laranode-scripts/bin/laranode-sudoers.sh" "$PANEL_PATH"

step "Relaxing PHP-FPM systemd sandbox so the panel can administer the system"

bash "$PANEL_PATH/laranode-scripts/bin/laranode-fpm-sandbox.sh"

step "Fixing ownership and permissions"

chown -R "$PANEL_USER:$PANEL_USER" "$PANEL_PATH"
# www-data is in the panel user's group so it can READ the panel it serves, but
# it must never be able to WRITE it: group-write here would mean any path bug in
# the panel lets the web process drop a PHP file into the panel's own
# DocumentRoot, and www-data holds NOPASSWD sudo. Group gets r-x / r-- only.
# Older installs were left group-writable, so this tightens them on upgrade.
find "$PANEL_PATH" -type d -exec chmod 750 {} +
find "$PANEL_PATH" -type f -exec chmod 640 {} +
# root-only execute: www-data runs these through sudo, it never reads or writes them
find "$PANEL_PATH/laranode-scripts/bin" -type f -exec chmod 100 {} +
# the only two trees Laravel writes at runtime, so the only two www-data may write
find "$PANEL_PATH/storage" "$PANEL_PATH/bootstrap/cache" -type d -exec chmod 770 {} +
find "$PANEL_PATH/storage" "$PANEL_PATH/bootstrap/cache" -type f -exec chmod 660 {} +
# setgid so files www-data creates here stay in the panel user's group: both
# write this tree (www-data serves, laranode_ln runs artisan during upgrades)
# and without it the group drifts to www-data and locks the other one out
find "$PANEL_PATH/storage" "$PANEL_PATH/bootstrap/cache" -type d -exec chmod g+s {} +
# the blanket chmod above drops the executable bit the tooling needs (vite, pint, ...)
chmod ug+x "$PANEL_PATH"/node_modules/.bin/* "$PANEL_PATH"/vendor/bin/* 2>/dev/null

step "Restarting services"

cp "$PANEL_PATH/laranode-scripts/templates/laranode-queue-worker.service" /etc/systemd/system/laranode-queue-worker.service
cp "$PANEL_PATH/laranode-scripts/templates/laranode-scheduler.service" /etc/systemd/system/laranode-scheduler.service
cp "$PANEL_PATH/laranode-scripts/templates/laranode-scheduler.timer" /etc/systemd/system/laranode-scheduler.timer
mkdir -p /var/lib/laranode/backups
chown root:www-data /var/lib/laranode/backups
chmod 750 /var/lib/laranode/backups

sudo -u "$PANEL_USER" php artisan config:clear
sudo -u "$PANEL_USER" php artisan cache:clear

systemctl daemon-reload
systemctl enable --now laranode-scheduler.timer
systemctl restart laranode-queue-worker.service
systemctl restart laranode-reverb.service
systemctl reload apache2

echo "================================================================================"
# naming the commit makes the claim checkable - a silent no-op upgrade was
# previously indistinguishable from a real one
echo -e "\033[32m Laranode has been upgraded to $(git -C "$PANEL_PATH" rev-parse --short HEAD). \033[0m"
echo "================================================================================"
