#!/usr/bin/env bash
set -euo pipefail

image="${1:?Usage: run-docker-live-tests.sh <test-image>}"
suffix="${GITHUB_RUN_ID:-local}-$$"
network="fossbilling-live-${suffix}"
db_container="fossbilling-db-${suffix}"
app_container="fossbilling-app-${suffix}"

db_name="fossbilling"
db_user="root"
db_pass="root"
db_port="3306"
test_email="email@example.com"
test_pass="4WGemqiihh8iM3"
test_api_key="AW6qEQCa7U7FG96J9NFIZXNYMJ79M8LH"

cleanup() {
  status=$?

  if [[ $status -ne 0 ]]; then
    docker exec "${app_container}" sh -c '
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
    docker logs "${app_container}" || true
    docker logs "${db_container}" || true
  fi

  docker rm -f "${app_container}" "${db_container}" >/dev/null 2>&1 || true
  docker network rm "${network}" >/dev/null 2>&1 || true

  exit "$status"
}
trap cleanup EXIT

docker network create "${network}" >/dev/null

docker run --detach \
  --name "${db_container}" \
  --network "${network}" \
  --env MYSQL_DATABASE="${db_name}" \
  --env MYSQL_ROOT_PASSWORD="${db_pass}" \
  mysql:8.4 >/dev/null

for _ in {1..60}; do
  if docker exec "${db_container}" mysqladmin ping --host=127.0.0.1 --user="${db_user}" --password="${db_pass}" --silent >/dev/null 2>&1; then
    break
  fi
  sleep 2
done

docker exec "${db_container}" mysqladmin ping --host=127.0.0.1 --user="${db_user}" --password="${db_pass}" --silent >/dev/null

docker run --detach \
  --name "${app_container}" \
  --network "${network}" \
  --env APP_ENV=test \
  "${image}" >/dev/null

for _ in {1..60}; do
  if docker run --rm --network "${network}" "${image}" curl -fsS "http://${app_container}/install/" >/dev/null; then
    break
  fi
  sleep 2
done

docker run --rm --network "${network}" "${image}" curl -fsS "http://${app_container}/install/" >/dev/null

docker run --rm \
  --network "${network}" \
  --env APP_ENV=test \
  --env APP_URL="http://${app_container}/" \
  --env TEST_API_KEY="${test_api_key}" \
  "${image}" \
  sh -euxc "
    curl -fsS \
      -H 'Content-type: multipart/form-data' \
      -F error_reporting=0 \
      -F database_hostname='${db_container}' \
      -F database_port='${db_port}' \
      -F database_name='${db_name}' \
      -F database_username='${db_user}' \
      -F database_password='${db_pass}' \
      -F admin_name='test' \
      -F admin_email='${test_email}' \
      -F admin_password='${test_pass}' \
      -F currency_code='USD' \
      -F currency_title='US Dollar' \
      -F admin_api_token='${test_api_key}' \
      -X POST \
      'http://${app_container}/install/install.php?a=install'

    ./src/vendor/bin/phpunit --configuration phpunit-live.xml
  "
