# Running Laranode in Docker

[← Back to the README](../README.md)

Laranode can also run as a single Docker container. Because the panel manages the machine it runs on (system users, Apache, PHP-FPM, MySQL, UFW, Let's Encrypt) and reads its stats from systemd and sysstat, the container boots systemd and runs all of those services inside it, just like a VPS. That is why it needs `privileged: true`: without it systemd cannot set up the sandboxes its units ask for, and it then forces `NoNewPrivileges` on, which stops the panel from running its own scripts through `sudo`.

The container administers a full machine, so treat it like a VPS and give it its own host rather than one shared with unrelated workloads.

```bash
git clone https://github.com/crivion/laranode.git && cd laranode
docker compose up -d --build
docker compose logs -f   # the first boot compiles the frontend assets, give it a few minutes
docker compose exec laranode laranode-artisan laranode:create-admin
```

Set these in a `.env` file next to `docker-compose.yml` to change the defaults:

| Variable | Default | Description |
|---|---|---|
| `LARANODE_URL` | `http://localhost` | Public URL of the panel |
| `LARANODE_HTTP_PORT` / `LARANODE_HTTPS_PORT` | `80` / `443` | Published web ports |
| `LARANODE_REVERB_HOST` | host of `LARANODE_URL` | Host browsers use to reach the websocket server |
| `LARANODE_REVERB_PORT` | `8080` | Published websocket port (live stats) |
| `LARANODE_SSH_PORT` | `2222` | Published SSH port for accounts with shell access |
| `LARANODE_ADMIN_NAME` / `LARANODE_ADMIN_EMAIL` / `LARANODE_ADMIN_PASSWORD` | | Optional, creates the admin account on first boot |
| `TZ` | `UTC` | Timezone |

Data lives in named volumes: MySQL, `/home` (websites), Let's Encrypt certificates, sysstat history, panel storage and `.env`. System users, Apache vhosts, PHP-FPM pools, firewall rules and PHP versions added through the PHP manager are saved to the state volume on shutdown (and every 5 minutes), then restored on boot, so `docker compose down` and image upgrades keep your hosting accounts.

Stats such as CPU, memory, uptime and disk describe the Docker host (or the Docker Desktop VM), because containers share the host kernel.

## How it works

Laranode manages the machine it runs on: it creates system users, writes Apache vhosts and PHP-FPM pools, drives `ufw` and certbot, and reads its dashboard stats straight from systemd and sysstat. A container per service would break most of that, so the image is a single "system container" that boots systemd as PID 1 and is set up exactly like `laranode-installer.sh` sets up a VPS.

Running inside the container:

- Apache, MySQL 8 and PHP 8.4-FPM
- the queue worker and reverb, as systemd services
- sysstat collection timers, for the stats history pages
- certbot's renewal timer
- UFW, SSH for accounts with shell access, and D-Bus so the panel can query systemd

## Everyday commands

```bash
docker compose logs -f                                    # follow the boot and the services
docker compose exec laranode bash                         # a root shell inside
docker compose exec laranode laranode-artisan migrate     # artisan, as the web user
docker compose restart laranode
```

Run `artisan` through the `laranode-artisan` wrapper rather than `php artisan`. It runs as `www-data`, the same user as PHP-FPM, so the files it writes into `storage/` stay writable by the panel.

## Upgrading

```bash
git pull && docker compose up -d --build
```

The container applies the same steps on boot that the upgrade script applies to a VPS. The panel is unreachable for a minute or so while the new container migrates and rebuilds its assets.

## Accounts with shell access

The container's sshd is published on `LARANODE_SSH_PORT` (2222 by default), because the host usually keeps port 22 for itself, and accounts sign in as `<username>_ln`:

```bash
ssh -p 2222 myaccount_ln@your-host
```

The panel shows the exact command on each user's profile page.

## Notes

- CPU, memory, uptime and disk figures describe the Docker host, not the container, because containers share the host's kernel.
- PHP versions installed through the PHP manager are reinstalled automatically when the container is recreated.
