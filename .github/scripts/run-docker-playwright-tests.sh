#!/usr/bin/env bash
set -euo pipefail

image="${1:?Usage: run-docker-playwright-tests.sh <test-image>}"
script_dir="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
repo_root="$(cd "${script_dir}/../.." && pwd)"
compose_file="${repo_root}/.github/docker/live-tests.compose.yml"
project="fossbilling-playwright-${GITHUB_RUN_ID:-local}-$$"
playwright_version="1.62.1"
playwright_image="${PLAYWRIGHT_DOCKER_IMAGE:-mcr.microsoft.com/playwright:v${playwright_version}-noble}"
app_host="fossbilling-app"
app_url="http://${app_host}/"
install_url="${app_url}install/"

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

cleanup() {
  status=$?

  if [[ -n "${playwright_pull_pid:-}" ]]; then
    kill "${playwright_pull_pid}" 2>/dev/null || true
    wait "${playwright_pull_pid}" 2>/dev/null || true
  fi

  if [[ $status -ne 0 ]]; then
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
  fi

  compose down --volumes --remove-orphans >/dev/null 2>&1 || true

  exit "$status"
}

if ! docker image inspect "${image}" >/dev/null 2>&1; then
  echo "Docker image '${image}' was not found. Build the test image before running Playwright tests."
  exit 1
fi

trap cleanup EXIT

docker pull "${playwright_image}" &
playwright_pull_pid=$!

export FOSSBILLING_TEST_IMAGE="${image}"
export FOSSBILLING_DB_NAME="${db_name}"
export FOSSBILLING_DB_PASS="${db_pass}"

compose up --detach
compose exec -T app rm -f /var/www/html/config.php

for _ in {1..60}; do
  if compose exec -T app curl -fsS "${install_url}" >/dev/null; then
    break
  fi
  sleep 2
done

compose exec -T app curl -fsS "${install_url}" >/dev/null

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
  "${install_url}install.php?a=install"
)

compose exec -T app curl -fsS "${install_payload[@]}" >/dev/null

compose exec -T app php -r '
$configPath = "/var/www/html/config.php";
$config = require $configPath;
$config["security"]["perform_session_fingerprinting"] = false;
file_put_contents($configPath, "<?php\n\nreturn " . var_export($config, true) . ";\n");
'

wait "${playwright_pull_pid}"
playwright_pull_pid=""

# Chromium-based browsers need more shared memory than Docker's default /dev/shm.
# The runner package is installed into a container-local prefix; a tmpfs shadows the
# workspace's @playwright directory so specs resolve that exact copy (one package
# instance) without touching the host's node_modules.
docker run --rm \
  --network "${project}_default" \
  --shm-size=2g \
  --tmpfs /workspace/node_modules/@playwright:rw \
  --env CI \
  --env PLAYWRIGHT_BASE_URL="${app_url}" \
  --env ADMIN_EMAIL="${test_email}" \
  --env ADMIN_PASSWORD="${test_pass}" \
  --env GITHUB_ACTIONS \
  --volume "${repo_root}:/workspace" \
  --workdir /workspace \
  "${playwright_image}" \
  bash -c '
    set -euo pipefail
    runner=/opt/playwright-runner
    mkdir -p "$runner" && cd "$runner"
    npm init -y >/dev/null
    npm install --no-audit --no-fund "@playwright/test@'"${playwright_version}"'" >/dev/null
    ln -sn "$runner/node_modules/@playwright/test" /workspace/node_modules/@playwright/test
    cd /workspace
    exec "$runner/node_modules/.bin/playwright" test
  '
