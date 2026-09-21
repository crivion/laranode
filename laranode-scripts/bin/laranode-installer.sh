#!/bin/bash

# Exit on any error
# set -e

export DEBIAN_FRONTEND=noninteractive

echo -e "\033[34m"
echo "--------------------------------------------------------------------------------"
echo "Installing software-properties-common and git"
echo "--------------------------------------------------------------------------------"
echo -e "\033[0m"

apt update
apt install -y software-properties-common git python3

echo -e "\033[34m"
echo "--------------------------------------------------------------------------------"
echo "Installing Apache Web Server"
echo "--------------------------------------------------------------------------------"
echo -e "\033[0m"

apt install -y apache2


echo -e "\033[34m"
echo "--------------------------------------------------------------------------------"
echo "Installing Sysstat"
echo "--------------------------------------------------------------------------------"
echo -e "\033[0m"

apt-get install -y sysstat
sed -i 's/ENABLED="false"/ENABLED="true"/' /etc/default/sysstat
systemctl restart sysstat
systemctl enable sysstat


echo -e "\033[34m"
echo "--------------------------------------------------------------------------------"
echo "Enabling and starting apache2"
echo "--------------------------------------------------------------------------------"
echo -e "\033[0m"

systemctl enable apache2
systemctl start apache2

echo -e "\033[34m"
echo "--------------------------------------------------------------------------------"
echo "Installing MySQL Server"
echo "--------------------------------------------------------------------------------"
echo -e "\033[0m"

apt install -y mysql-server
systemctl enable mysql
systemctl start mysql


echo -e "\033[34m"
echo "--------------------------------------------------------------------------------"
echo "Creating Laranode MySQL User & Database"
echo "--------------------------------------------------------------------------------"
echo -e "\033[0m"

LARANODE_RANDOM_PASS=$(openssl rand -base64 12)
ROOT_RANDOM_PASS=$(openssl rand -base64 12)

mysql -u root -e "CREATE USER 'laranode'@'localhost' IDENTIFIED BY '$LARANODE_RANDOM_PASS';"
mysql -u root -e "GRANT ALL PRIVILEGES ON *.* TO 'laranode'@'localhost' WITH GRANT OPTION;"
mysql -u root -e "FLUSH PRIVILEGES;"
mysql -u root -e "CREATE DATABASE laranode;"
mysql -u root -e "ALTER USER 'root'@'localhost' IDENTIFIED BY '$ROOT_RANDOM_PASS';"

echo -e "\033[34m"
echo "--------------------------------------------------------------------------------"
echo "Adding ppa:ondrej/php"
echo "--------------------------------------------------------------------------------"
echo -e "\033[0m"
add-apt-repository -y ppa:ondrej/php

echo -e "\033[34m"
echo "--------------------------------------------------------------------------------"
echo "Running apt update"
echo "--------------------------------------------------------------------------------"
echo -e "\033[0m"
apt update

echo -e "\033[34m"
echo "--------------------------------------------------------------------------------"
echo "Installing php8.4 and required extensions"
echo "--------------------------------------------------------------------------------"
echo -e "\033[0m"
apt install -y php8.4 php8.4-fpm php8.4-cli php8.4-common php8.4-curl php8.4-mbstring \
               php8.4-xml php8.4-bcmath php8.4-zip php8.4-mysql php8.4-sqlite3 php8.4-pgsql \
               php8.4-gd php8.4-imagick php8.4-intl php8.4-readline php8.4-tokenizer php8.4-fileinfo \
               php8.4-soap php8.4-opcache unzip curl


echo -e "\033[34m"
echo "--------------------------------------------------------------------------------"
echo "Enabling and starting PHP-FPM"
echo "--------------------------------------------------------------------------------"
echo -e "\033[0m"

systemctl enable php8.4-fpm
systemctl start php8.4-fpm

echo -e "\033[34m"
echo "--------------------------------------------------------------------------------"
echo "Enabling proxy_fcgi apache module"
echo "--------------------------------------------------------------------------------"
echo -e "\033[0m"
a2enmod proxy_fcgi

echo -e "\033[34m"
echo "--------------------------------------------------------------------------------"
echo "Enabling rewrite_module apache module"
echo "--------------------------------------------------------------------------------"
echo -e "\033[0m"
a2enmod rewrite

echo -e "\033[34m"
echo "--------------------------------------------------------------------------------"
echo "Enabling setenvif apache module"
echo "--------------------------------------------------------------------------------"
echo -e "\033[0m"
a2enmod setenvif


echo -e "\033[34m"
echo "--------------------------------------------------------------------------------"
echo "Enabling headers apache module"
echo "--------------------------------------------------------------------------------"
echo -e "\033[0m"
a2enmod headers

echo -e "\033[34m"
echo "--------------------------------------------------------------------------------"
echo "Enabling ssl apache module"
echo "--------------------------------------------------------------------------------"
echo -e "\033[0m"
a2enmod ssl

echo -e "\033[34m"
echo "--------------------------------------------------------------------------------"
echo "Installing certbot"
echo "--------------------------------------------------------------------------------"
echo -e "\033[0m"
apt -y install certbot python3-certbot-apache


echo -e "\033[34m"
echo "--------------------------------------------------------------------------------"
echo "Enabling php8.4-fpm apache configuration"
echo "--------------------------------------------------------------------------------"
echo -e "\033[0m"
a2enconf php8.4-fpm

echo -e "\033[34m"
echo "--------------------------------------------------------------------------------"
echo "Restarting apache2"
echo "--------------------------------------------------------------------------------"
echo -e "\033[0m"

systemctl restart apache2

echo -e "\033[34m"
echo "--------------------------------------------------------------------------------"
echo "Installing Composer"
echo "--------------------------------------------------------------------------------"
echo -e "\033[0m"

curl -sS https://getcomposer.org/installer | php -- --install-dir=/usr/local/bin --filename=composer

echo -e "\033[34m"
echo "--------------------------------------------------------------------------------"
echo "Installing NodeJS"
echo "--------------------------------------------------------------------------------"
echo -e "\033[0m"

curl -fsSL https://deb.nodesource.com/setup_22.x | sudo -E bash -
sudo apt install -y nodejs


echo -e "\033[34m"
echo "--------------------------------------------------------------------------------"
echo "Creating Laranode User"
useradd -m -s /bin/bash laranode_ln
usermod -aG laranode_ln www-data
echo "--------------------------------------------------------------------------------"
echo -e "\033[0m"

echo -e "\033[34m"
echo "--------------------------------------------------------------------------------"
echo "Cloning Laranode"
echo -e "\033[0m"

# install a specific branch with: curl -sSL <installer url> | LARANODE_BRANCH=my-branch bash
git clone -b "${LARANODE_BRANCH:-main}" https://github.com/crivion/laranode.git /home/laranode_ln/panel
echo "--------------------------------------------------------------------------------"

echo -e "\033[34m"
echo "--------------------------------------------------------------------------------"
echo "Relaxing PHP-FPM systemd sandbox so the panel can administer the system"
echo "--------------------------------------------------------------------------------"
echo -e "\033[0m"
bash /home/laranode_ln/panel/laranode-scripts/bin/laranode-fpm-sandbox.sh 8.4

echo -e "\033[34m"
echo "--------------------------------------------------------------------------------"
echo "Adding www-data to sudoers and allowing to run laranode scripts"
echo "--------------------------------------------------------------------------------"
echo -e "\033[0m"

# named scripts rather than a bin/*.sh glob - see laranode-sudoers.sh
bash /home/laranode_ln/panel/laranode-scripts/bin/laranode-sudoers.sh /home/laranode_ln/panel



echo -e "\033[34m"
echo "--------------------------------------------------------------------------------"
echo "Installing Laranode"
echo "--------------------------------------------------------------------------------"
echo -e "\033[0m"

cd /home/laranode_ln/panel
composer install
cp .env.example .env
sed -i "s#DB_PASSWORD=.*#DB_PASSWORD=\"$LARANODE_RANDOM_PASS\"#" ".env"

# Work out the address the panel is reached on.
#
# This used to be a bare "curl icanhazip.com". On a host with IPv6 connectivity
# that resolves over IPv6 and returns a bare IPv6 address, which is not a valid
# URI host unless bracketed - so APP_URL became http://2001:db8::1 and every
# artisan command then died with "Invalid URI: Host is malformed", including the
# create-admin step. Asking for IPv4 explicitly avoids the whole problem.
detect_panel_host() {
  local ip

  for endpoint in https://icanhazip.com https://ifconfig.me/ip; do
    ip=$(curl -4 -fsS --max-time 10 "$endpoint" 2>/dev/null | tr -d '[:space:]')
    if echo "$ip" | grep -Eq '^([0-9]{1,3}\.){3}[0-9]{1,3}$'; then
      echo "$ip"
      return
    fi
  done

  # no reachable echo service - fall back to whichever address this host would
  # route out of, which is right for a NAT'd or firewalled box too
  ip=$(ip -4 route get 1.1.1.1 2>/dev/null | sed -n 's/.* src \([0-9.]*\).*/\1/p')
  if echo "$ip" | grep -Eq '^([0-9]{1,3}\.){3}[0-9]{1,3}$'; then
    echo "$ip"
    return
  fi

  echo ""
}

PANEL_HOST=$(detect_panel_host)

if [ -z "$PANEL_HOST" ]; then
  PANEL_HOST="127.0.0.1"
  echo -e "\033[33m"
  echo "--------------------------------------------------------------------------------"
  echo "Could not determine an IPv4 address for this machine (IPv6-only host?)."
  echo ""
  echo "Falling back to 127.0.0.1 so the install can finish. The panel will work on the"
  echo "machine itself but not over the network until you set APP_URL, REVERB_HOST and"
  echo "VITE_REVERB_HOST in /home/laranode_ln/panel/.env to the address you reach this"
  echo "server on, then run: npm run build"
  echo "--------------------------------------------------------------------------------"
  echo -e "\033[0m"
fi

sed -i "s#APP_URL=.*#APP_URL=\"http://$PANEL_HOST\"#" ".env"

php artisan key:generate
php artisan migrate
php artisan db:seed
php artisan storage:link
php artisan reverb:install

# reuse the address resolved above rather than asking again - three separate
# lookups could each return something different
sed -i "s#VITE_REVERB_HOST=.*#VITE_REVERB_HOST=$PANEL_HOST#" ".env"
sed -i "s#REVERB_HOST=.*#REVERB_HOST=$PANEL_HOST#" ".env"

cp /home/laranode_ln/panel/laranode-scripts/templates/apache2-default.template /etc/apache2/sites-available/000-default.conf

echo -e "\033[34m"
echo "--------------------------------------------------------------------------------"
echo "Hold tight, pouring node_modules with npm install & compiling assets"
echo "--------------------------------------------------------------------------------"
echo -e "\033[0m"
npm install
npm run build


echo -e "\033[34m"
echo "--------------------------------------------------------------------------------"
echo "Adding systemd services (queue worker and reverb)"
echo "--------------------------------------------------------------------------------"
echo -e "\033[0m"

cp /home/laranode_ln/panel/laranode-scripts/templates/laranode-queue-worker.service /etc/systemd/system/laranode-queue-worker.service
cp /home/laranode_ln/panel/laranode-scripts/templates/laranode-reverb.service /etc/systemd/system/laranode-reverb.service
cp /home/laranode_ln/panel/laranode-scripts/templates/laranode-scheduler.service /etc/systemd/system/laranode-scheduler.service
cp /home/laranode_ln/panel/laranode-scripts/templates/laranode-scheduler.timer /etc/systemd/system/laranode-scheduler.timer
mkdir -p /var/lib/laranode/backups
chown root:www-data /var/lib/laranode/backups
chmod 750 /var/lib/laranode/backups


echo -e"\033[34m"
echo "--------------------------------------------------------------------------------"
echo "Adding default UFW rules for SSH | HTTP | HTTPS | REVERB WEBSOCKETS"
echo "--------------------------------------------------------------------------------"
echo -e "\033[0m"
ufw allow 22
ufw allow 80
ufw allow 443
ufw allow 8080


echo -e "\033[34m"
echo "--------------------------------------------------------------------------------"
echo "Setting permissions"
echo "--------------------------------------------------------------------------------"
echo -e "\033[0m"
mkdir -p /home/laranode_ln/logs
chown -R laranode_ln:laranode_ln /home/laranode_ln
# www-data is in the laranode_ln group so it can READ the panel it serves, but
# it must never be able to WRITE it: group-write here would mean any path bug in
# the panel lets the web process drop a PHP file into the panel's own
# DocumentRoot, and www-data holds NOPASSWD sudo. Group gets r-x / r-- only.
# "+" passes many files to one chmod, "\;" would spawn one process per file
find /home/laranode_ln -type d -exec chmod 750 {} +
find /home/laranode_ln -type f -exec chmod 640 {} +
# root-only execute: www-data runs these through sudo, it never reads or writes them
find /home/laranode_ln/panel/laranode-scripts/bin -type f -exec chmod 100 {} +
# the only two trees Laravel writes at runtime, so the only two www-data may write
find /home/laranode_ln/panel/storage /home/laranode_ln/panel/bootstrap/cache -type d -exec chmod 770 {} +
find /home/laranode_ln/panel/storage /home/laranode_ln/panel/bootstrap/cache -type f -exec chmod 660 {} +
# setgid so files www-data creates here stay in the panel user's group: both
# write this tree (www-data serves, laranode_ln runs artisan during upgrades)
# and without it the group drifts to www-data and locks the other one out
find /home/laranode_ln/panel/storage /home/laranode_ln/panel/bootstrap/cache -type d -exec chmod g+s {} +
# the blanket chmod above drops the executable bit the tooling needs (vite, pint, ...)
# globbed, not -R: these are symlinks and chmod only follows them when named directly
chmod ug+x /home/laranode_ln/panel/node_modules/.bin/* /home/laranode_ln/panel/vendor/bin/* 2>/dev/null


systemctl daemon-reload
systemctl enable laranode-queue-worker.service
systemctl enable laranode-reverb.service
systemctl enable laranode-scheduler.timer
systemctl start laranode-queue-worker.service
systemctl start laranode-reverb.service
systemctl start laranode-scheduler.timer
systemctl restart apache2
systemctl restart php8.4-fpm


echo "================================================================================"
echo "================================================================================"
echo -e "\033[32m --- NOTES ---\033[0m"

echo "MySQL Root Password: $ROOT_RANDOM_PASS"
echo "Laranode MySQL Username: laranode"
echo "Laranode MySQL Password: $LARANODE_RANDOM_PASS"

echo -e "\033[32m --- IMPORTANT ---\033[0m"

echo "Final Step: Now create an admin account for Laranode by running the following command:"
echo -e "\033[33m cd /home/laranode_ln/panel && php artisan laranode:create-admin \033[0m"

echo "================================================================================"
echo "================================================================================"
