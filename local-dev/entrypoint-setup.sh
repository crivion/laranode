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

# --- install PostgreSQL if not present ---
if ! dpkg -l postgresql-16 >/dev/null 2>&1; then
    log "installing postgresql-16 + client"
    apt-get update -qq
    apt-get install -y -qq postgresql-16 postgresql-client-16
fi

# --- install cron (crontab binary + daemon) if not present (cron-tasks feature) ---
if ! command -v crontab >/dev/null 2>&1; then
    log "installing cron"
    apt-get update -qq
    apt-get install -y -qq cron
fi

# --- core services ---
log "enabling + starting core services"
sed -i 's/ENABLED="false"/ENABLED="true"/' /etc/default/sysstat || true
systemctl enable --now apache2 mysql php8.4-fpm sysstat cron

# --- PostgreSQL: start ---
log "starting postgresql@16-main"
systemctl enable --now postgresql@16-main

# Wait for Postgres socket
for i in $(seq 1 30); do
    sudo -u postgres psql -c "SELECT 1" >/dev/null 2>&1 && break
    sleep 1
done
sudo -u postgres psql -c "SELECT 1" >/dev/null 2>&1 || { log "ERROR: postgresql did not start"; exit 1; }

# --- wait for mysql socket ---
log "waiting for mysql..."
for i in $(seq 1 30); do
  mysqladmin -u root ping >/dev/null 2>&1 && break
  sleep 1
done
mysqladmin -u root ping >/dev/null 2>&1 || { log "ERROR: mysql did not start"; exit 1; }

# --- load env (for DB_PASSWORD, ADMIN_*, PGSQL_PASSWORD, etc.) ---
set -a; . "$PANEL/local-dev/.env.docker"; set +a

# --- linux-native bin dir with executable scripts + patched ssl-manager ---
log "populating $BIN"
mkdir -p "$BIN"
cp -f /opt/laranode/bin-src/*.sh "$BIN"/
cp -f "$PANEL/local-dev/bin/laranode-ssl-manager.sh" "$BIN/laranode-ssl-manager.sh"
# Copy any scripts added directly to the panel's bin dir (e.g. laranode-cron.sh)
for f in "$PANEL/laranode-scripts/bin/"*.sh; do
    [ -f "$f" ] && cp -f "$f" "$BIN/$(basename "$f")"
done
chmod -R 0755 "$BIN"

# --- PostgreSQL: provision stats-reader role ---
# PGSQL_PASSWORD is now available from .env.docker (loaded above).
log "provisioning laranode_pg_reader role"
PGSQL_PASSWORD="${PGSQL_PASSWORD:-pg_reader_local_dev}"
PG_TAG=$(head -c 16 /dev/urandom | base64 | tr -dc 'a-z' | head -c 8)
sudo -u postgres psql -v ON_ERROR_STOP=1 --dbname=postgres <<SQL
DO \$\$
BEGIN
    IF NOT EXISTS (SELECT 1 FROM pg_roles WHERE rolname = 'laranode_pg_reader') THEN
        CREATE ROLE laranode_pg_reader LOGIN;
    END IF;
END\$\$;
ALTER ROLE laranode_pg_reader PASSWORD \$${PG_TAG}\$${PGSQL_PASSWORD}\$${PG_TAG}\$;
GRANT CONNECT ON DATABASE postgres TO laranode_pg_reader;
GRANT pg_read_all_stats TO laranode_pg_reader;
SQL

# Apply postgres sudoers drop-in
log "writing postgres sudoers"
cp -f "$PANEL/laranode-scripts/bin/laranode-postgres-sudoers" /etc/sudoers.d/laranode-postgres
chmod 440 /etc/sudoers.d/laranode-postgres

# Smoke test (BIN is now populated above)
log "postgres smoke test"
sudo "$BIN/laranode-postgres.sh" create-db ln_smoke_test UTF8 en_US.UTF-8
sudo "$BIN/laranode-postgres.sh" drop-db ln_smoke_test
log "postgres smoke test PASSED"

# --- container sudoers (www-data runs the scripts; mirrors installer line 172 + new path) ---
log "writing sudoers"
cat > /etc/sudoers.d/laranode <<EOF
www-data ALL=(ALL) NOPASSWD: $BIN/*.sh, /usr/sbin/a2dissite, /bin/rm /etc/apache2/sites-available/*.conf
EOF
chmod 440 /etc/sudoers.d/laranode

# --- cron sudoers drop-in (grants www-data NOPASSWD for laranode-cron.sh at panel path) ---
log "writing laranode-cron sudoers drop-in"
cp -f "$PANEL/laranode-scripts/etc/sudoers.d/laranode-cron" /etc/sudoers.d/laranode-cron
chmod 440 /etc/sudoers.d/laranode-cron

# --- runtimes sudoers drop-in (grants www-data NOPASSWD for runtime scripts) ---
log "writing laranode-runtimes sudoers drop-in"
cp -f "$PANEL/laranode-scripts/etc/sudoers.d/laranode-runtimes" /etc/sudoers.d/laranode-runtimes
chmod 440 /etc/sudoers.d/laranode-runtimes

# --- firewall (UFW) sudoers drop-in (firewall Actions call `sudo ufw` directly) ---
log "writing laranode-ufw sudoers drop-in"
cp -f "$PANEL/laranode-scripts/etc/sudoers.d/laranode-ufw" /etc/sudoers.d/laranode-ufw
chmod 440 /etc/sudoers.d/laranode-ufw

# --- enable Apache proxy modules (required for FrankenPHP/Swoole) ---
log "enabling apache proxy modules"
a2enmod proxy proxy_http

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
# .env is the bind-mounted file (shared with the Windows host). Back up any
# existing one ONCE, then force the container's known-good config so a fresh
# clone self-provisions without hand-editing. Restore from .env.local-backup
# if you keep a separate host .env.
if [ -f .env ] && [ ! -f .env.local-backup ]; then cp .env .env.local-backup; fi
cp -f local-dev/.env.docker .env
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
# ADMIN_EMAIL/ADMIN_PASSWORD live in .env.docker (Laravel env), not the shell, so
# default them here or the admin gets an empty email and you can't log in.
# updateOrCreate (not firstOrCreate) repairs a stale row's email/password too.
ADMIN_EMAIL="${ADMIN_EMAIL:-admin@laranode.test}"
ADMIN_PASSWORD="${ADMIN_PASSWORD:-password}"
log "seeding admin (${ADMIN_EMAIL})"
php artisan tinker --execute="\App\Models\User::updateOrCreate(['username' => 'laranode'], ['name' => 'Admin', 'email' => '${ADMIN_EMAIL}', 'password' => bcrypt('${ADMIN_PASSWORD}'), 'role' => 'admin', 'ssh_access' => true, 'email_verified_at' => now()]);"

# --- detect GPU once (stores the result; not re-probed every poll) ---
log "detecting GPU"
php artisan laranode:detect-gpu || true

# --- provision test system users for CronJob system tests ---
log "provisioning test system users (testuser_ln, testuser2_ln)"
useradd -m -s /bin/bash testuser_ln 2>/dev/null || true
useradd -m -s /bin/bash testuser2_ln 2>/dev/null || true

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
