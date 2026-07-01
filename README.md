# Laranode - Open-Source Hosting Control Panel

Laranode is a simple but powerful open-source alternative to cPanel and Plesk, designed to simplify VPS and dedicated server management. With an intuitive interface and robust features, Laranode makes it easy to deploy and manage websites, databases, SSL certificates, and more.

## Features

✅ **Self-Hosted** – Full control over your server with NO licensing fees.

✅ **Multi-Account Support** – Role-based access control for admins and users.  

✅ **Website Management** – Easily create and manage multiple websites.  

✅ **SSL with Let's Encrypt** – Secure your websites with free SSL certificates with a click of a button.

✅ **File Manager** – Built-in (from the ground up) web-based file manager for quick access.  

✅ **Live System Stats & Analytics** – Real-time CPU/memory/network monitoring plus historical usage charts and per-user quota tracking.

✅ **LAMP Stack Administration** – Manage Apache, MySQL, and PHP with ease.  

✅ **PHP Manager** – Install, update, and remove PHP versions from the web UI.

✅ **Alternative PHP Runtimes** – Switch individual sites between PHP-FPM and FrankenPHP.

✅ **Multi-Engine Database Management** – Create and manage MySQL, MariaDB, and PostgreSQL databases, with per-engine service control (start/stop/restart).

✅ **Automated Backups** – Scheduled or on-demand database and file backups to local disk or S3-compatible storage, with retention and restore.

✅ **Cron Job Manager** – Create and manage per-user scheduled tasks from the web UI.

✅ **Notifications** – In-app notification center plus email/webhook alerts for operations, SSL expiry, and more.

✅ **Async Operations with Live Progress** – Long-running tasks (SSL issuance, runtime switches, backups) stream real-time progress instead of blocking the request.

✅ **UFW Firewall** – Manage uncomplicated firewall rules with ease directly from the web interface.  

✅ **User-Friendly Interface** – Clean and simple UI designed for efficiency.  

## Installation

Laranode can be installed on a FRESH VPS or dedicated server.

### Min. Requirements
- Ubuntu 24.04+
- 1vCPU
- 2GB RAM
- 10GB Disk Space

### Quick Install
Run on a clean Ubuntu 24.04 server as root:
```bash
curl -sSL https://raw.githubusercontent.com/alexandre433/laranode/refs/heads/main/laranode-scripts/bin/laranode-installer.sh | bash
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

- 🔹 Git-Based Deployments – push-to-deploy workflow for websites
- 🔹 Fail2ban Integration – automatic intrusion prevention
- 🔹 DNS Zone Management – built-in authoritative DNS
- 🔹 One-Click App Installers – WordPress and more
- 🔹 Email Server – mailboxes with webmail
- 🔹 Teams & Granular Roles – per-resource collaborator access
- 🔹 Staging Environments – clone, sync, and promote sites

## Contributing
Laranode is open-source and welcomes contributions! Feel free to submit issues, feature requests, or pull requests.

## License
Laranode is open-source and released under the [MIT license](https://opensource.org/licenses/MIT).
---

⭐ **Star this repo to support the project!**
