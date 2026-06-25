#!/usr/bin/env bash
set -euo pipefail

PANEL=/home/laranode_ln/panel
BIN=/opt/laranode/bin
SENTINEL=/home/laranode_ln/.laranode-setup-done

log() { echo -e "\033[34m[setup]\033[0m $*"; }

[ -f "$SENTINEL" ] && { log "already provisioned; skipping."; exit 0; }

# --- wait for systemd ---
log "waiting for systemd..."
for i in $(seq 1 30); do
  state=$(systemctl is-system-running 2>/dev/null || true)
  [ "$state" = running ] || [ "$state" = degraded ] && break
  sleep 1
done

# --- core services ---
log "enabling + starting core services"
sed -i 's/ENABLED="false"/ENABLED="true"/' /etc/default/sysstat || true
systemctl enable --now apache2 mysql php8.4-fpm sysstat

# --- wait for mysql socket ---
log "waiting for mysql..."
for i in $(seq 1 30); do
  mysqladmin -u root ping >/dev/null 2>&1 && break
  sleep 1
done
mysqladmin -u root ping >/dev/null 2>&1 || { log "ERROR: mysql did not start"; exit 1; }

# --- load env (for DB_PASSWORD, ADMIN_*, etc.) ---
set -a; . "$PANEL/local-dev/.env.docker"; set +a

# --- linux-native bin dir with executable scripts + patched ssl-manager ---
log "populating $BIN"
mkdir -p "$BIN"
cp -f /opt/laranode/bin-src/*.sh "$BIN"/
cp -f "$PANEL/local-dev/bin/laranode-ssl-manager.sh" "$BIN/laranode-ssl-manager.sh"
chmod -R 0755 "$BIN"

# --- container sudoers (www-data runs the scripts; mirrors installer line 172 + new path) ---
log "writing sudoers"
cat > /etc/sudoers.d/laranode <<EOF
www-data ALL=(ALL) NOPASSWD: $BIN/*.sh, /usr/sbin/a2dissite, /bin/rm /etc/apache2/sites-available/*.conf
EOF
chmod 440 /etc/sudoers.d/laranode

# --- MySQL user + db (idempotent; grant for localhost and 127.0.0.1) ---
log "creating mysql user + db"
mysql -u root <<SQL
CREATE DATABASE IF NOT EXISTS ${DB_DATABASE};
CREATE USER IF NOT EXISTS '${DB_USERNAME}'@'localhost' IDENTIFIED BY '${DB_PASSWORD}';
CREATE USER IF NOT EXISTS '${DB_USERNAME}'@'127.0.0.1' IDENTIFIED BY '${DB_PASSWORD}';
GRANT ALL PRIVILEGES ON *.* TO '${DB_USERNAME}'@'localhost' WITH GRANT OPTION;
GRANT ALL PRIVILEGES ON *.* TO '${DB_USERNAME}'@'127.0.0.1' WITH GRANT OPTION;
FLUSH PRIVILEGES;
SQL

# --- app: .env, deps, key, migrate, seed ---
cd "$PANEL"
[ -f .env ] || cp local-dev/.env.docker .env
mkdir -p storage/logs
[ -d vendor ] && [ -n "$(ls -A vendor 2>/dev/null)" ] || composer install --no-interaction
grep -q '^APP_KEY=base64' .env || php artisan key:generate --force
php artisan migrate --force
php artisan db:seed --force || true
php artisan storage:link || true
php artisan reverb:install --no-interaction || true

# --- node deps + build (only if missing) ---
[ -d node_modules ] && [ -n "$(ls -A node_modules 2>/dev/null)" ] || npm install
[ -d public/build ] || npm run build

# --- seed admin non-interactively (username 'laranode' to match systemUsername laranode_ln) ---
log "seeding admin"
php artisan tinker --execute "
\App\Models\User::firstOrCreate(
  ['username' => 'laranode'],
  ['name' => 'Admin', 'email' => '${ADMIN_EMAIL}', 'password' => bcrypt('${ADMIN_PASSWORD}'), 'role' => 'admin', 'ssh_access' => true]
);"

# --- apache default vhost (serves the panel from /public) ---
cp -f laranode-scripts/templates/apache2-default.template /etc/apache2/sites-available/000-default.conf
systemctl reload apache2

# --- seed one sysstat sample so dashboard history isn't empty ---
mkdir -p /var/log/sysstat
sadc 1 1 "/var/log/sysstat/sa$(date +%d)" 2>/dev/null || true

# --- firewall (container netns only) ---
ufw --force enable || true
for p in 22 80 443 8080; do ufw allow "$p" || true; done

# --- panel services: reverb + queue worker ---
cp -f laranode-scripts/templates/laranode-queue-worker.service /etc/systemd/system/laranode-queue-worker.service
cp -f laranode-scripts/templates/laranode-reverb.service /etc/systemd/system/laranode-reverb.service
systemctl daemon-reload
systemctl enable --now laranode-queue-worker.service laranode-reverb.service
systemctl restart apache2 php8.4-fpm

# --- ownership (best-effort over bind mount) ---
chown -R laranode_ln:laranode_ln /home/laranode_ln/logs || true

touch "$SENTINEL"
log "DONE. Panel at http://localhost  (admin: ${ADMIN_EMAIL} / ${ADMIN_PASSWORD})"
