#!/usr/bin/env bash
# cspell:words enmod fcgi setenvif dissite ensite enconf configtest dbname NFIZXNYMJ Gemqiihh
set -euo pipefail

repo_root="$(pwd)"
mkdir -p test-results/server src/data/cache src/data/log src/data/uploads
rm -f src/config.php

# PHP runs as the checkout owner; Apache serves static files and the real .htaccess.
if ! command -v apache2ctl >/dev/null 2>&1; then
  sudo apt-get update
  sudo apt-get install -y --no-install-recommends apache2
fi
sudo a2enmod rewrite proxy_fcgi setenvif
sudo tee /etc/apache2/conf-available/fossbilling-ci.conf >/dev/null <<EOF
ServerName localhost
User $(id -un)
Group $(id -gn)
EOF
sudo a2enconf fossbilling-ci
cat > /tmp/fossbilling-ci-fpm.conf <<EOF
[global]
error_log = ${repo_root}/test-results/server/php-fpm.log
[www]
listen = 127.0.0.1:9000
pm = static
pm.max_children = 4
catch_workers_output = yes
clear_env = no
php_admin_flag[log_errors] = on
php_admin_value[error_log] = ${repo_root}/test-results/server/php-error.log
EOF
php-fpm --fpm-config /tmp/fossbilling-ci-fpm.conf --daemonize

sudo tee /etc/apache2/sites-available/fossbilling-ci.conf >/dev/null <<EOF
<VirtualHost *:80>
    ServerName localhost
    DocumentRoot ${repo_root}/src
    DirectoryIndex index.php
    <Directory ${repo_root}/src>
        Options FollowSymLinks
        AllowOverride All
        Require all granted
        <FilesMatch "\.php$">
            SetHandler "proxy:fcgi://127.0.0.1:9000"
        </FilesMatch>
    </Directory>
    ErrorLog ${repo_root}/test-results/server/apache-error.log
    CustomLog ${repo_root}/test-results/server/apache-access.log combined
</VirtualHost>
EOF
sudo a2dissite 000-default
sudo a2ensite fossbilling-ci
sudo apache2ctl configtest
sudo service apache2 restart

# Service containers start in parallel with checkout; wait for SQL readiness.
for attempt in {1..60}; do
  if php -r 'new PDO("mysql:host=127.0.0.1;dbname=fossbilling", "root", "root");' 2>/dev/null; then
    break
  fi
  sleep 1
done
php -r 'new PDO("mysql:host=127.0.0.1;dbname=fossbilling", "root", "root");'
curl --fail --silent --show-error http://localhost/install/ >/dev/null
curl --fail --silent --show-error \
  -F error_reporting=0 -F database_hostname=127.0.0.1 -F database_port=3306 \
  -F database_name=fossbilling -F database_username=root -F database_password=root \
  -F admin_name=test -F admin_email=email@example.com -F admin_password=4WGemqiihh8iM3 \
  -F currency_code=USD -F 'currency_title=US Dollar' \
  -F admin_api_token=AW6qEQCa7U7FG96J9NFIZXNYMJ79M8LH \
  'http://localhost/install/install.php?a=install' >/dev/null

# Match the browser pilot's session configuration.
if [[ "${NATIVE_SUITE:-}" == browser ]]; then
  php -r '$path = "src/config.php"; $config = require $path; $config["security"]["perform_session_fingerprinting"] = false; file_put_contents($path, "<?php\nreturn " . var_export($config, true) . ";\n");'
fi

# Check a rewritten route and .htaccess protection of configuration files.
curl --fail --silent --show-error http://localhost/login >/dev/null
test "$(curl --silent --show-error --output /dev/null --write-out '%{http_code}' http://localhost/config.php)" = 404
