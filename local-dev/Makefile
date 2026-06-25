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

test:
	$(EXEC) 'cd /home/laranode_ln/panel && php artisan test'

test-system:
	$(EXEC) 'cd /home/laranode_ln/panel && LARANODE_SYSTEM_TESTS=1 php artisan test'

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
