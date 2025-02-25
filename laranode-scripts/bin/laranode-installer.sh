# Exit on any error
# set -e

echo -e "\033[34m"
echo "--------------------------------------------------------------------------------"
echo "Installing software-properties-common and git"
echo "--------------------------------------------------------------------------------"
echo -e "\033[0m"

apt update
apt install -y software-properties-common git

echo -e "\033[34m"
echo "--------------------------------------------------------------------------------"
echo "Installing Apache Web Server"
echo "--------------------------------------------------------------------------------"
echo -e "\033[0m"

apt install -y apache2


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

$LARANODE_RANDOM_PASS=$(openssl rand -base64 12)
$ROOT_RANDOM_PASS=$(openssl rand -base64 12)
mysql -u root -e "CREATE USER 'laranode'@'localhost' IDENTIFIED BY '$LARANODE_RANDOM_PASS';"
mysql -u root -e "GRANT ALL PRIVILEGES ON *.* TO 'laranode'@'localhost';"
mysql -u root -e "FLUSH PRIVILEGES;"
mysql -u root -e "CREATE DATABASE laranode;"
mysql -u root -e "ALTER USER 'root'@'localhost' IDENTIFIED BY '$ROOT_RANDOM_PASS';"

echo -e "\033[34m"
echo "--------------------------------------------------------------------------------"
echo "Adding ppa:ondrej/php"
echo "--------------------------------------------------------------------------------"
add-apt-repository -y ppa:ondrej/php
echo -e "\033[0m"

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
echo "Adding www-data to sudoers and allowing to run laranode scripts"
echo "--------------------------------------------------------------------------------"
echo -e "\033[0m"

echo "www-data ALL=(ALL) NOPASSWD: /home/laranode_ln/panel/laranode-scripts/bin/*.sh" >> /etc/sudoers

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
echo "--------------------------------------------------------------------------------"
echo -e "\033[0m"

echo -e "\033[34m"
echo "--------------------------------------------------------------------------------"
echo "Cloning Laranode"
echo -e "\033[0m"

git clone https://github.com/crivion/laranode.git /home/laranode_ln/panel
echo "--------------------------------------------------------------------------------"


echo -e "\033[34m"
echo "--------------------------------------------------------------------------------"
echo "Installing Laranode"
echo "--------------------------------------------------------------------------------"
echo -e "\033[0m"

cd /home/laranode_ln/panel
composer install
cp .env.example .env

php artisan key:generate
php artisan migrate
php artisan db:seed
php artisan storage:link
php artisan reverb:install
chown -R laranode_ln:laranode_ln /home/laranode_ln/panel
find /home/laranode_ln -type d -exec chmod 770 {} \;
find /home/laranode_ln -type f -exec chmod 660 {} \;

echo -e "\033[34m"
echo "--------------------------------------------------------------------------------"
echo "Adding systemd services (queue worker and reverb)"
echo "--------------------------------------------------------------------------------"
echo -e "\033[0m"

cp /home/laranode_ln/laranode-scripts/templates/laranode-queue-worker.service /etc/systemd/system/laranode-queue-worker.service
cp /home/laranode_ln/laranode-scripts/templates/laranode-reverb.service /etc/systemd/system/laranode-reverb.service

systemctl daemon-reload
systemctl enable laranode-queue-worker.service
systemctl enable laranode-reverb.service
systemctl start laranode-queue-worker.service
systemctl start laranode-reverb.service


echo "================================================================================"
echo "================================================================================"
echo -e "\033[32m --- NOTES ---\033[0m"

echo "MySQL Root Password: $ROOT_RANDOM_PASS"
echo "Laranode MySQL Username: laranode"
echo "Laranode MySQL Password: $LARANODE_RANDOM_PASS"

echo -e "\033[32m --- IMPORTANT ---\033[0m"

echo "Final Step: Now create an admin account for Laranode by running the following command:"
echo -e "\033[33m php artisan laranode:create-admin \033[0m"

echo "================================================================================"
echo "================================================================================"
