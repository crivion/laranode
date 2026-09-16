# Laranode - Open-Source Hosting Control Panel

Laranode is a simple but powerful open-source alternative to cPanel and Plesk, designed to simplify VPS and dedicated server management. With an intuitive interface and robust features, Laranode makes it easy to deploy and manage websites, databases, SSL certificates, and more.

**[Features](#features)** · **[Install](#installation)** · **[Docker](docs/DOCKER.md)** · **[Upgrading](#upgrading)** · **[Screenshots](#screenshots)**

---

## Features

✅ **Self-Hosted** – Full control over your server with NO licensing fees.

✅ **Multi-Account Support** – Role-based access control for admins and users.  

✅ **Website Management** – Easily create and manage multiple websites.  

✅ **SSL with Let's Encrypt** – Secure your websites with free SSL certificates with a click of a button.

✅ **File Manager** – Built-in (from the ground up) web-based file manager for quick access.  

✅ **Live System Stats** – Monitor CPU, memory, and network usage in real-time. 

✅ **LAMP Stack Administration** – Manage Apache, MySQL, and PHP with ease.  

✅ **PHP Manager** – Install, enable and remove PHP versions (7.4 up to 8.5) and assign them per website.

✅ **User-Friendly Interface** – Clean and simple UI designed for efficiency.  

✅ **MySQL Database Management** – Create and control MySQL databases.  

✅ **UFW Firewall** – Manage uncomplicated firewall rules with ease directly from the web interface.  

## Installation

Laranode can be installed on a FRESH VPS or dedicated server.

### Min. Requirements
- Ubuntu 24.04+
- 1vCPU
- 2GB RAM
- 10GB Disk Space

### Quick Install
```bash
curl -sSL https://raw.githubusercontent.com/crivion/laranode/refs/heads/main/laranode-scripts/bin/laranode-installer.sh | bash
```

### Docker
Laranode also runs as a single Docker container that boots systemd and hosts Apache, MySQL, PHP-FPM, the queue worker and reverb inside it, the same way the installer sets up a VPS.

```bash
git clone https://github.com/crivion/laranode.git && cd laranode
docker compose up -d --build
docker compose exec laranode laranode-artisan laranode:create-admin
```

📖 **[Running Laranode in Docker](docs/DOCKER.md)** covers the settings, the volumes and how upgrades work.

## Upgrading

Existing installations are upgraded with:

```bash
curl -sSL https://raw.githubusercontent.com/crivion/laranode/refs/heads/main/laranode-scripts/bin/laranode-upgrade.sh | bash
```

It pulls the latest release, updates dependencies, runs migrations, rebuilds the assets and re-applies the system configuration the panel needs (sudoers rules and the PHP-FPM systemd sandbox). Every step is safe to run repeatedly. Pass `PANEL_PATH=/path/to/panel` if Laranode is not installed in `/home/laranode_ln/panel`.

Docker installations upgrade by rebuilding the image instead, which applies the same steps on boot:

```bash
git pull && docker compose up -d --build
```

## Getting Started
Once installed, access Laranode via your browser:
```
http://your-server-ip
OR if you pointed your domain/subdomain
http://your-domain.tld
```
Login with the credentials provided during installation.

## Screenshots

| Light | Dark |
|:------:|:----:|
| <img src="laranode-screenshots/2-dashboard.png" alt="Dashboard (Light)" width="400"/> | <img src="laranode-screenshots/1-dashboard-dark.png" alt="Dashboard (Dark)" width="400"/> |
| <img src="laranode-screenshots/3-stats-history.png" alt="Stats History (Light)" width="400"/> | <img src="laranode-screenshots/3-stats-history-dark.png" alt="Stats History (Dark)" width="400"/> |
| <img src="laranode-screenshots/4-accounts.png" alt="Accounts (Light)" width="400"/> | <img src="laranode-screenshots/4-accounts-dark.png" alt="Accounts (Dark)" width="400"/> |
| <img src="laranode-screenshots/5-create-account.png" alt="Create Account (Light)" width="400"/> | <img src="laranode-screenshots/5-create-account-dark.png" alt="Create Account (Dark)" width="400"/> |
| <img src="laranode-screenshots/6-websites.png" alt="Websites (Light)" width="400"/> | <img src="laranode-screenshots/6-websites-dark.png" alt="Websites (Dark)" width="400"/> |
| <img src="laranode-screenshots/7-filemanager.png" alt="File Manager (Light)" width="400"/> | <img src="laranode-screenshots/7-filemanager-dark.png" alt="File Manager (Dark)" width="400"/> |
| <img src="laranode-screenshots/8-db.png" alt="Database Manager (Light)" width="400"/> | <img src="laranode-screenshots/8-db-dark.png" alt="Database Manager (Dark)" width="400"/> |
| <img src="laranode-screenshots/9-firewall.png" alt="Firewall (Light)" width="400"/> | <img src="laranode-screenshots/9-firewall-dark.png" alt="Firewall (Dark)" width="400"/> |

## Minimum Requirements


## 1-Click Deployment with DigitalOcean
[![DigitalOcean Logo](https://opensource.nyc3.cdn.digitaloceanspaces.com/attribution/assets/SVG/DO_Logo_horizontal_blue.svg)](https://marketplace.digitalocean.com/apps/laranode-panel?refcode=833110c66c2c&action=deploy)

## Roadmap - Future Release Plans

- 🔹 Backup Manager - backup websites, databases, and files

## Contributing
Laranode is open-source and welcomes contributions! Feel free to submit issues, feature requests, or pull requests.

## License
Laranode is open-source and released under the [MIT license](https://opensource.org/licenses/MIT).
---

⭐ **Star this repo to support the project!**
