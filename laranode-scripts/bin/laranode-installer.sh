echo "--------------------------------------------------------------------------------"
echo "Installing software-properties-common and git"
apt update
apt install -y software-properties-common git
echo "--------------------------------------------------------------------------------"

echo "--------------------------------------------------------------------------------"
echo "Installing Apache Web Server"
apt install -y apache2
echo "--------------------------------------------------------------------------------"

echo "--------------------------------------------------------------------------------"
echo "Enabling and starting apache2"
systemctl enable apache2
systemctl start apache2
echo "--------------------------------------------------------------------------------"

echo "--------------------------------------------------------------------------------"
echo "Installing MySQL Server"
apt install -y mysql-server
echo "--------------------------------------------------------------------------------"

echo "--------------------------------------------------------------------------------"
echo "Adding ppa:ondrej/php"
add-apt-repository -y ppa:ondrej/php
echo "--------------------------------------------------------------------------------"

echo "--------------------------------------------------------------------------------"
echo "Running apt update"
apt update
echo "--------------------------------------------------------------------------------"

echo "--------------------------------------------------------------------------------"
echo "Installing php8.4 and required extensions"
apt install -y php8.4 php8.4-fpm php8.4-cli php8.4-common php8.4-curl php8.4-mbstring \
               php8.4-xml php8.4-bcmath php8.4-zip php8.4-mysql php8.4-sqlite3 php8.4-pgsql \
               php8.4-gd php8.4-imagick php8.4-intl php8.4-readline php8.4-tokenizer php8.4-fileinfo \
               php8.4-soap php8.4-opcache unzip curl
echo "--------------------------------------------------------------------------------"


echo "--------------------------------------------------------------------------------"
echo "Enabling and starting PHP-FPM"
systemctl enable php8.4-fpm
systemctl start php8.4-fpm
echo "--------------------------------------------------------------------------------"

echo "--------------------------------------------------------------------------------"
echo "Enabling proxy_fcgi apache module"
a2enmod proxy_fcgi
echo "--------------------------------------------------------------------------------"

echo "--------------------------------------------------------------------------------"
echo "Enabling rewrite_module apache module"
a2enmod rewrite
echo "--------------------------------------------------------------------------------"

echo "--------------------------------------------------------------------------------"
echo "Enabling setenvif apache module"
a2enmod setenvif
echo "--------------------------------------------------------------------------------"

echo "--------------------------------------------------------------------------------"
echo "Enabling headers apache module"
a2enmod headers
echo "--------------------------------------------------------------------------------"


echo "--------------------------------------------------------------------------------"
echo "Enabling php8.4-fpm apache configuration"
a2enconf php8.4-fpm
echo "--------------------------------------------------------------------------------"

echo "--------------------------------------------------------------------------------"
echo "Restarting apache2"
systemctl restart apache2
echo "--------------------------------------------------------------------------------"

echo "--------------------------------------------------------------------------------"
echo "Adding www-data to sudoers and allowing to run laranode scripts"
echo "www-data ALL=(ALL) NOPASSWD: /home/laranode_ln/panel/laranode-scripts/bin/*.sh" >> /etc/sudoers
echo "--------------------------------------------------------------------------------"

echo "--------------------------------------------------------------------------------"
echo "Installing Composer"
curl -sS https://getcomposer.org/installer | php -- --install-dir=/usr/local/bin --filename=composer
echo "--------------------------------------------------------------------------------"

echo "--------------------------------------------------------------------------------"
echo "Installing NodeJS"
curl -fsSL https://deb.nodesource.com/setup_22.x | sudo -E bash -
sudo apt install -y nodejs
echo "--------------------------------------------------------------------------------"


echo "--------------------------------------------------------------------------------"
echo "Creating Laranode User"
useradd -m -s /bin/bash laranode_ln
echo "--------------------------------------------------------------------------------"

echo "--------------------------------------------------------------------------------"
echo "Cloning Laranode"
git clone https://github.com/crivion/laranode-inertia.git /home/laranode_ln/panel
echo "--------------------------------------------------------------------------------"


echo "--------------------------------------------------------------------------------"
echo "Installing Laranode"
echo "--------------------------------------------------------------------------------"
cd /home/laranode_ln/panel
composer install
cp .env.example .env

php artisan key:generate
php artisan migrate
php artisan db:seed
php artisan storage:link
php artisan reverb:install

echo "================================================================================"
echo "================================================================================"
echo "DONE"
echo "================================================================================"
echo "================================================================================"
