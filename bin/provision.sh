#!/usr/bin/env bash

mysql -u root -e "DROP DATABASE IF EXISTS boxbilling"
mysql -u root -e "CREATE DATABASE boxbilling"
mysql -u root boxbilling < /var/www/src/install/structure.sql
mysql -u root boxbilling -e "INSERT INTO admin (role, name, email, pass, protected, created_at, updated_at) VALUES('admin', 'Admin', 'admin@boxbilling.com', SHA1('admin'), 1, NOW(), NOW());"

cd /var/www/src
cp htaccess.txt .htaccess
cp bb-config-sample.php bb-config.php

cd /var/www
ln -s src public
curl -sS https://getcomposer.org/installer | php
php composer.phar install --no-interaction --prefer-source --dev

yum install php-mcrypt -y
service httpd restart
