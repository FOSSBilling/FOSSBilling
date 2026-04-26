#!/bin/bash

set -eu

PHP_VERSION="$(php -r 'echo PHP_MAJOR_VERSION . "." . PHP_MINOR_VERSION;')"
FPM_CONF_DIR="/etc/php/${PHP_VERSION}/fpm/conf.d"
SECURITY_INI="${FPM_CONF_DIR}/99-fossbilling-security.ini"

cat > "${SECURITY_INI}" <<'EOF'
[PHP]
disable_functions = exec,passthru,system,shell_exec,popen,proc_open
EOF
