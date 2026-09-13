# Local dev environment — Docker-based "VPS-in-a-box" for Laranode.
#
# WINDOWS REQUIREMENT: Run make and docker compose from PowerShell or cmd.
# Git Bash (MSYS) strips the Windows environment that docker.exe needs to find
# its compose plugin — recipes will fail with "unknown command: docker compose".
# Plain `docker exec laranode-lab ...` works from any shell.
#
# Run all recipes from the REPO ROOT (not from inside local-dev/).
#
# MSYS (the make/sh that ships with Git Bash & MSYS2) rewrites in-container
# absolute paths like /home/... into Windows paths (C:/msys64/home/...) when it
# calls the native docker.exe — which breaks `exec` of in-container scripts.
# Disable that conversion for every recipe. No-op on Linux/macOS.
export MSYS_NO_PATHCONV := 1
export MSYS2_ARG_CONV_EXCL := *

COMPOSE = docker compose -f local-dev/docker-compose.yml
EXEC = $(COMPOSE) exec laranode bash -lc

.PHONY: up provision sh verify test test-system build-assets sync-scripts logs nuke ssl-test

up:
	$(COMPOSE) up -d --build
	$(MAKE) -f local-dev/Makefile provision

provision:
	$(EXEC) '/home/laranode_ln/panel/local-dev/entrypoint-setup.sh'

sh:
	$(COMPOSE) exec laranode bash

verify:
	$(EXEC) 'ps -p 1 -o comm=; systemctl is-system-running || true; for s in apache2 mysql php8.4-fpm laranode-reverb laranode-queue-worker; do printf "%s: " "$$s"; systemctl is-active $$s; done'
	@echo "--- HTTP check ---"
	@curl -s -o /dev/null -w "panel http status: %{http_code}\n" http://localhost || true

# Clear cached config first: with config cached, phpunit.xml's DB_CONNECTION=sqlite
# override is ignored and RefreshDatabase runs against the live MySQL panel DB,
# wiping it. Re-cache afterwards (Reverb relies on it), preserving the exit code.
test:
	$(EXEC) 'cd /home/laranode_ln/panel && php artisan config:clear && php artisan test; rc=$$?; php artisan config:cache >/dev/null; exit $$rc'

test-system:
	$(EXEC) 'cd /home/laranode_ln/panel && php artisan config:clear && LARANODE_SYSTEM_TESTS=1 php artisan test; rc=$$?; php artisan config:cache >/dev/null; exit $$rc'

build-assets:
	$(EXEC) 'cd /home/laranode_ln/panel && npm run build'

sync-scripts:
	$(EXEC) 'cp -f /opt/laranode/bin-src/*.sh /opt/laranode/bin/ && cp -f /home/laranode_ln/panel/local-dev/bin/laranode-ssl-manager.sh /opt/laranode/bin/ && chmod -R 0755 /opt/laranode/bin'

ssl-test:
	$(COMPOSE) --profile ssl up -d
	$(EXEC) 'sudo LARANODE_ACME_SERVER=$${LARANODE_ACME_SERVER:-https://pebble:14000/dir} /opt/laranode/bin/laranode-ssl-manager.sh status localhost || true'

logs:
	$(COMPOSE) logs -f

nuke:
	$(COMPOSE) --profile ssl down -v
