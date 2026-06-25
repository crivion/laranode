# CLAUDE.md

This file provides guidance to Claude Code (claude.ai/code) when working with code in this repository.

## What this is

Laranode is a self-hosted server control panel (cPanel/Plesk alternative) built on Laravel 12 + Inertia 2 + React 18. It manages the **host machine itself** — Apache vhosts, per-site PHP-FPM pools, MySQL databases, Let's Encrypt SSL, UFW firewall, a web file manager, and live system stats. Target host is Ubuntu 24.04+; the panel is deployed at `/home/laranode_ln/panel`.

## Local dev/test (Docker)

`local-dev/` provides a single systemd-enabled Ubuntu 24.04 container ("VPS-in-a-box") with the full Laranode stack (Apache, PHP-FPM, MySQL, Reverb, queue worker). No real Linux VPS needed for integration testing.

Key targets (run from repo root):
- `make up` — build image + start container + run entrypoint provisioning
- `make verify` — check all services running + HTTP panel response
- `make test` — run Pest suite inside container
- `make test-system` — Pest with `LARANODE_SYSTEM_TESTS=1` (exercises sudo scripts)
- `make ssl-test` — bring up Pebble ACME sidecar + test SSL issuance
- `make nuke` — destroy container + all named volumes (full reset)

Admin login: `admin@laranode.test` / `password`

> **Windows:** Run `make` and `docker compose` from **PowerShell or cmd**, NOT Git Bash.
> Git Bash (MSYS) strips the Windows environment that `docker.exe` needs to locate its
> compose plugin. Plain `docker exec laranode-lab …` works from any shell.

## Commands

```bash
composer dev          # all-in-one dev: php artisan serve + queue:listen + pail (logs) + vite, concurrently
npm run dev           # vite only
npm run build         # production asset build
php artisan reverb:start   # websocket server — NOT started by `composer dev`; needed for live stats
./vendor/bin/pest                       # run tests (Pest 3)
./vendor/bin/pest --filter="text"       # single test by name
php artisan test --filter=AccountsTest  # alt runner, by file/test
./vendor/bin/pint     # format (Laravel Pint) — run before committing PHP
php artisan migrate
php artisan laranode:create-admin   # interactive admin creation (username is forced to "laranode")
```

Tests use Pest with `RefreshDatabase` (see `tests/Pest.php`); feature tests live in `tests/Feature/<Domain>/`.

## Environment caveat

System-touching features (sudo scripts, `systemctl`, `/proc`, `certbot`, `ufw`) only run on a real Linux host. On Windows/macOS dev machines those `Process` calls fail — exercise that behavior on a Linux VPS, not locally. DB is MySQL in prod (`.env.example`).

## Architecture

### Request layering
Controllers are thin. The pattern is: **Controller → FormRequest (validation) → Service or Action (work)**.

- `app/Services/<Domain>/` — orchestration, usually wrapping system calls (`Websites`, `MySQL`, `Accounts`, `Dashboard`, and `Laranode` infra helpers). Convention: a single `handle()` method, and a sibling custom `*Exception` class declared in the same file (e.g. `CreateWebsiteException`).
- `app/Actions/<Domain>/` — single-purpose units (`Filemanager`, `Firewall`, `SSL`, `MySQL`). Filemanager actions receive a Flysystem `Filesystem` injected by `AppServiceProvider`, sandboxed to the authenticated user's homedir (`DISALLOW_LINKS`).

### How the panel touches the system (the core idea)
Two distinct mechanisms, both via the `Process` facade:

1. **Privileged mutations** shell out to whitelisted bash scripts:
   ```php
   Process::run(['sudo', config('laranode.laranode_bin_path') . '/laranode-add-vhost.sh', ...$args]);
   ```
   Scripts live in `laranode-scripts/bin/`, config templates (Apache vhost, PHP-FPM pool, systemd units) in `laranode-scripts/templates/`. The installer grants `www-data` NOPASSWD sudo for `laranode-scripts/bin/*.sh`. When adding a privileged op: add a `*.sh` script there and call it through a Service — do not run privileged commands inline.
2. **Read-only stats** call system tools directly (`top`, `free`, `df`, `systemctl`, `ps`, `certbot`, `/proc/net/dev`) via `Process::run('…')` / `Process::pipe([...])`. See `app/Services/Dashboard/SystemStatsService.php`.

### Identity & path conventions (computed, never stored)
Used throughout the codebase — accessors on the models, not DB columns (comments note casts were unreliable here):
- System user = `{username}_ln` (`User::systemUsername`)
- Home dir = `/home/{username}_ln` (`User::homedir`)
- Website root = `{homedir}/domains/{url}`; `fullDocumentRoot` = website root + `document_root` (`Website`)

### Auth & multi-tenancy
- `users.role` is `admin` | `user`. `AdminMiddleware` gates admin-only routes (accounts, firewall, PHP manager, admin dashboard, stats history).
- Non-admins are scoped to their own rows via the `scopeMine()` query scope on `Website`/`Database`.
- Admins impersonate users via `lab404/laravel-impersonate`. Shared Inertia props (`HandleInertiaRequests`): `auth.user`, `auth.isImpersonating`, `flash.{success,error}`.

### Live stats over websockets (no polling)
Reverb-based push, not polling:
1. React page subscribes to a private channel and whispers a `client-typing` event (`resources/js/Pages/Dashboard/...`).
2. Server's `MessageReceivedListener` (auto-discovered, hooks `Laravel\Reverb\Events\MessageReceived`) matches the channel and dispatches `SystemStatsEvent` / `TopStatsEvent`.
3. Those events gather fresh stats in their constructor and broadcast back on private channels `systemstats` / `topstats` — both authorized to admins only (`routes/channels.php`).

Historical stats use sysstat/`sar`: `app/Services/Dashboard/{SarHistory,CPUHistoryService,MemoryHistoryService,NetworkHistoryService}.php`, all implementing `HistoricStatsContract`.

### Frontend
Inertia + React (JSX, **not** TypeScript). Pages in `resources/js/Pages/<Domain>/`, layouts in `resources/js/Layouts/`. `route()` in JS comes from Ziggy; websockets from Echo/Reverb (`resources/js/echo.js`). Tables use `react-data-table-component`, charts use `chart.js`/`react-chartjs-2`.

### Production runtime
Apache2 (vhost per site) + per-site PHP-FPM pools + MySQL + certbot (Let's Encrypt, 90-day certs) + UFW. Two systemd services from `laranode-scripts/templates/`: `laranode-reverb.service` (websockets) and `laranode-queue-worker.service` (queue, `QUEUE_CONNECTION=database`). Full provisioning is in `laranode-scripts/bin/laranode-installer.sh`.
