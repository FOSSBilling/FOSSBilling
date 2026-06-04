#!/usr/bin/env bash
set -euo pipefail

action="${1:?Usage: run-docker-browser-tests.sh <setup|teardown> [test-image]}"
script_dir="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
repo_root="$(cd "${script_dir}/../.." && pwd)"
compose_file="${repo_root}/.github/docker/live-tests.compose.yml"
project="fossbilling-browser-tests"
host_port="${FOSSBILLING_TEST_PORT:-8080}"

db_name="fossbilling"
db_user="root"
db_pass="root"
db_port="3306"
test_email="email@example.com"
test_pass="4WGemqiihh8iM3"
test_api_key="AW6qEQCa7U7FG96J9NFIZXNYMJ79M8LH"

compose() {
  docker compose --file "${compose_file}" --project-name "${project}" "$@"
}

dump_logs() {
  compose exec -T app sh -c '
    cd /var/www/html

    for file in install/php_error.log data/log/php_error.log; do
      if [ -f "$file" ]; then
        echo "===== $file ====="
        cat "$file"
      fi
    done

    find data/log -type f ! -name "*.html" ! -name "php_error.log" -print 2>/dev/null | while read -r file; do
      echo "===== $file ====="
      cat "$file"
    done
  ' || true
  compose logs --no-color app db || true
}

case "${action}" in
  setup)
    image="${2:?Usage: run-docker-browser-tests.sh setup <test-image>}"

    if ! docker image inspect "${image}" >/dev/null 2>&1; then
      echo "Docker image '${image}' was not found. Build the test image before running browser tests."
      exit 1
    fi

    export FOSSBILLING_TEST_IMAGE="${image}"
    export FOSSBILLING_DB_NAME="${db_name}"
    export FOSSBILLING_DB_PASS="${db_pass}"
    export FOSSBILLING_TEST_PORT="${host_port}"

    compose up --detach
    compose exec -T app rm -f /var/www/html/config.php

    for _ in {1..60}; do
      if compose exec -T app curl -fsS "http://127.0.0.1/install/" >/dev/null; then
        break
      fi
      sleep 2
    done

    compose exec -T app curl -fsS "http://127.0.0.1/install/" >/dev/null

    install_payload=(
      -H 'Content-type: multipart/form-data'
      -F error_reporting=0
      -F "database_hostname=db"
      -F "database_port=${db_port}"
      -F "database_name=${db_name}"
      -F "database_username=${db_user}"
      -F "database_password=${db_pass}"
      -F admin_name=test
      -F "admin_email=${test_email}"
      -F "admin_password=${test_pass}"
      -F currency_code=USD
      -F 'currency_title=US Dollar'
      -F "admin_api_token=${test_api_key}"
      -X POST
      "http://127.0.0.1/install/install.php?a=install"
    )

    compose exec -T app curl -fsS "${install_payload[@]}" >/dev/null

    compose exec -T app php -r '
    $configPath = "/var/www/html/config.php";
    $config = require $configPath;
    $config["security"]["perform_session_fingerprinting"] = false;
    file_put_contents($configPath, "<?php\n\nreturn " . var_export($config, true) . ";\n");
    '

    for _ in {1..30}; do
      if curl -fsS "http://localhost:${host_port}/" >/dev/null 2>&1; then
        break
      fi
      sleep 2
    done

    echo "FOSSBilling is running at http://localhost:${host_port}/"
    ;;

  teardown)
    if [[ "${BROWSER_TESTS_FAILED:-}" == "true" ]]; then
      dump_logs
    fi
    compose down --volumes --remove-orphans >/dev/null 2>&1 || true
    ;;

  *)
    echo "Unknown action: ${action}"
    echo "Usage: run-docker-browser-tests.sh <setup|teardown> [test-image]"
    exit 1
    ;;
esac
