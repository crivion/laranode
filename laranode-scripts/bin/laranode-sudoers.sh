#!/bin/bash

# Installs the sudo rules www-data needs, as an explicit allowlist.
#
# The rules used to be a single appended /etc/sudoers line granting
# "bin/*.sh". That glob is only as strong as the directory it points at: any
# path bug in the panel that let www-data create a file under bin/ would have
# created a new root-runnable script. Naming each script closes that, and
# writing to /etc/sudoers.d makes the step idempotent instead of relying on
# sed against a live /etc/sudoers.
#
# Usage (as root): ./laranode-sudoers.sh [panel path]

PANEL_PATH=${1:-${PANEL_PATH:-/home/laranode_ln/panel}}
BIN_PATH="$PANEL_PATH/laranode-scripts/bin"
SUDOERS_FILE="/etc/sudoers.d/laranode"

if [ "$(id -u)" -ne 0 ]; then
  echo "Please run this script as root."
  exit 1
fi

# every script the panel invokes through sudo - keep in sync with the app.
# laranode-installer.sh, laranode-upgrade.sh and laranode-fpm-sandbox.sh are
# deliberately absent: they are run by an administrator as root, never by the
# web process.
SCRIPTS=(
  laranode-add-php-fpm-pool.sh
  laranode-add-vhost.sh
  laranode-backup-db.sh
  laranode-backup-files.sh
  laranode-backup-storage.sh
  laranode-create-directory.sh
  laranode-file-permissions.sh
  laranode-php-install.sh
  laranode-php-list.sh
  laranode-php-service.sh
  laranode-php-uninstall.sh
  laranode-remove-all-user-php-fpm-pools.sh
  laranode-remove-php-fpm-pool-for-user.sh
  laranode-restart-php-fpm.sh
  laranode-restore-db.sh
  laranode-restore-files.sh
  laranode-safe-file.sh
  laranode-ssl-manager.sh
  laranode-update-php-version.sh
  laranode-update-sh-access.sh
  laranode-update-sh-password.sh
  laranode-user-manager.sh
)

COMMANDS=""
for script in "${SCRIPTS[@]}"; do
  [ -n "$COMMANDS" ] && COMMANDS+=", "
  COMMANDS+="$BIN_PATH/$script"
done

# a drop-in is only read if /etc/sudoers includes the directory - without this
# the rules below would be silently ignored and the panel would lose sudo
if ! grep -Eq '^[@#]includedir[[:space:]]+/etc/sudoers\.d' /etc/sudoers; then
  echo "Error: /etc/sudoers does not include /etc/sudoers.d"
  echo "Add '@includedir /etc/sudoers.d' with visudo, then run this script again."
  exit 1
fi

TMP_FILE=$(mktemp)
cat > "$TMP_FILE" <<EOF
# Managed by laranode-sudoers.sh - edits here are overwritten on upgrade.
Cmnd_Alias LARANODE_SCRIPTS = $COMMANDS
Cmnd_Alias LARANODE_APACHE = /usr/sbin/a2dissite, /bin/rm /etc/apache2/sites-available/*.conf
www-data ALL=(ALL) NOPASSWD: LARANODE_SCRIPTS, LARANODE_APACHE, /usr/sbin/ufw
EOF

if ! visudo -cf "$TMP_FILE" > /dev/null; then
  echo "Error: generated sudoers rules are invalid, leaving the current ones untouched"
  rm -f "$TMP_FILE"
  exit 1
fi

install -m 0440 -o root -g root "$TMP_FILE" "$SUDOERS_FILE"
rm -f "$TMP_FILE"
echo "Sudoers rules written to $SUDOERS_FILE"

# drop the legacy appended line, now superseded - but only if /etc/sudoers is
# still valid afterwards, since a broken /etc/sudoers locks everyone out
if grep -q "^www-data ALL=.*laranode-scripts/bin" /etc/sudoers; then
  TMP_SUDOERS=$(mktemp)
  grep -v "^www-data ALL=.*laranode-scripts/bin" /etc/sudoers > "$TMP_SUDOERS"

  if visudo -cf "$TMP_SUDOERS" > /dev/null; then
    cat "$TMP_SUDOERS" > /etc/sudoers
    echo "Removed the legacy wildcard rule from /etc/sudoers"
  else
    echo "Refusing to write invalid sudoers file, leaving the current one untouched"
  fi

  rm -f "$TMP_SUDOERS"
fi
