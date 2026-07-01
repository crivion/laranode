#!/usr/bin/env bash
# Clean-room install test.
#
# Boots a VANILLA ubuntu:24.04 + systemd container (nothing pre-installed),
# injects the current working tree, runs the REAL laranode-installer.sh end to
# end, and asserts the panel actually comes up. This is what proves a from-clean
# `curl | bash` install works — the normal `make up` lab uses a different,
# pre-provisioned image and can't catch installer ordering/packaging drift.
#
#   bash local-dev/install-test/run.sh         # run + teardown
#   KEEP=1 bash local-dev/install-test/run.sh   # keep container for inspection
#
# Uses docker run/exec only (no compose). MSYS guards keep git-bash on Windows
# from mangling in-container paths.

set -uo pipefail
export MSYS_NO_PATHCONV=1 MSYS2_ARG_CONV_EXCL='*'

NAME=laranode-install-test
IMAGE=jrei/systemd-ubuntu:24.04
REPO="$(cd "$(dirname "${BASH_SOURCE[0]}")/../.." && pwd)"
KEEP="${KEEP:-0}"

cleanup() { [ "$KEEP" = 1 ] || docker rm -f "$NAME" >/dev/null 2>&1 || true; }
fail() { echo "FAIL: $1"; [ "$KEEP" = 1 ] && echo "(container kept: docker exec -it $NAME bash)"; cleanup; exit 1; }

docker rm -f "$NAME" >/dev/null 2>&1 || true

echo "[1/5] Booting clean $IMAGE with systemd..."
docker run -d --name "$NAME" --privileged --cgroupns=host \
    --cap-add NET_ADMIN --cap-add NET_RAW --stop-signal SIGRTMIN+3 \
    -v /sys/fs/cgroup:/sys/fs/cgroup:rw \
    -v "$REPO":/src:ro \
    --tmpfs /run --tmpfs /run/lock --tmpfs /tmp \
    "$IMAGE" >/dev/null || fail "container did not start"

for _ in $(seq 1 30); do
    state=$(docker exec "$NAME" systemctl is-system-running 2>/dev/null || true)
    case "$state" in running | degraded | starting) break ;; esac
    sleep 2
done

echo "[2/5] Injecting working tree (fresh — no host vendor/.env/cache)..."
docker exec "$NAME" mkdir -p /home/laranode_ln/panel
docker exec "$NAME" bash -c 'tar -C /src \
    --exclude=./vendor --exclude=./node_modules --exclude=./.git \
    --exclude=./public/build --exclude=./.env --exclude=./.env.local-backup \
    -cf - . | tar -C /home/laranode_ln/panel -xf -' || fail "repo injection failed"
# Keep bootstrap/cache (Laravel needs the dir) but drop any stale cached config
# carried over from the host so the fresh .env/key actually take effect.
docker exec "$NAME" bash -c 'rm -f /home/laranode_ln/panel/bootstrap/cache/*.php' || true

echo "[3/5] Running the REAL installer (installs everything; can take 10+ min)..."
docker exec "$NAME" bash /home/laranode_ln/panel/laranode-scripts/bin/laranode-installer.sh \
    || fail "installer exited non-zero"

echo "[4/5] Seeding an admin..."
docker exec "$NAME" bash -lc 'cd /home/laranode_ln/panel && php artisan tinker --execute="App\Models\User::updateOrCreate([\"username\"=>\"laranode\"],[\"name\"=>\"Admin\",\"email\"=>\"admin@laranode.test\",\"password\"=>bcrypt(\"password\"),\"role\"=>\"admin\",\"ssh_access\"=>true,\"email_verified_at\"=>now()]);"' \
    || fail "admin creation failed"

echo "[5/5] Assertions:"
ok=1
for svc in apache2 mysql php8.4-fpm laranode-reverb laranode-queue-worker; do
    st=$(docker exec "$NAME" systemctl is-active "$svc" 2>/dev/null || echo inactive)
    printf "   %-26s %s\n" "$svc" "$st"
    [ "$st" = active ] || ok=0
done
pg=$(docker exec "$NAME" bash -c 'systemctl is-active postgresql@16-main 2>/dev/null || systemctl is-active postgresql 2>/dev/null || echo inactive')
printf "   %-26s %s\n" "postgresql" "$pg"
[ "$pg" = active ] || ok=0

code=$(docker exec "$NAME" curl -s -o /dev/null -w '%{http_code}' http://localhost/login 2>/dev/null || echo 000)
printf "   %-26s %s\n" "GET /login" "$code"
[ "$code" = 200 ] || ok=0

login=$(docker exec "$NAME" bash -lc 'cd /home/laranode_ln/panel && php artisan tinker --execute="echo Illuminate\Support\Facades\Auth::attempt([\"email\"=>\"admin@laranode.test\",\"password\"=>\"password\"])?\"yes\":\"no\";"' 2>/dev/null | tail -1)
printf "   %-26s %s\n" "admin login" "$login"
echo "$login" | grep -q yes || ok=0

if [ "$ok" = 1 ]; then
    echo "RESULT: PASS — clean from-scratch install works."
    cleanup
    exit 0
else
    echo "RESULT: FAIL — a check above did not pass."
    [ "$KEEP" = 1 ] && echo "(container kept for inspection)"
    cleanup
    exit 1
fi
