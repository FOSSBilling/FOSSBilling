#!/usr/bin/env bash
set -euo pipefail

image="${1:?Usage: run-docker-live-tests.sh <test-image>}"
script_dir="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
repo_root="$(cd "${script_dir}/../.." && pwd)"
compose_file="${repo_root}/.github/docker/live-tests.compose.yml"
project="fossbilling-live-${GITHUB_RUN_ID:-local}-$$"
app_url="http://fossbilling-app/"

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
trap cleanup EXIT

export FOSSBILLING_TEST_IMAGE="${image}"
export FOSSBILLING_DB_NAME="${db_name}"
export FOSSBILLING_DB_PASS="${db_pass}"

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
  -F "admin_api_token=${test_api_key}"
  -X POST
  "http://127.0.0.1/install/install.php?a=install"
)

compose exec -T app curl -fsS "${install_payload[@]}" >/dev/null

report_mount=()
if [[ -n "${FOSSBILLING_TEST_RESULTS_DIR:-}" ]]; then
  mkdir -p "${FOSSBILLING_TEST_RESULTS_DIR}"
  report_mount=(--volume "${FOSSBILLING_TEST_RESULTS_DIR}:/test-results" --env FOSSBILLING_JUNIT_FILE=/test-results/live.xml)
fi

compose run --rm --no-deps \
  "${report_mount[@]}" \
  --env APP_ENV=test \
  --env APP_URL="${app_url}" \
  --env TEST_API_KEY="${test_api_key}" \
  app \
  sh -euxc '
    cd /workspace
    if [ -n "${FOSSBILLING_JUNIT_FILE:-}" ]; then
      set -- --log-junit "$FOSSBILLING_JUNIT_FILE"
    fi
    exec ./src/vendor/bin/pest --test-directory ../tests --testsuite=E2E --ci "$@"
  ' sh
