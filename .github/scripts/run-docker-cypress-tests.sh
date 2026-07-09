#!/usr/bin/env bash
set -euo pipefail

image="${1:?Usage: run-docker-cypress-tests.sh <test-image>}"
script_dir="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
repo_root="$(cd "${script_dir}/../.." && pwd)"
compose_file="${repo_root}/.github/docker/live-tests.compose.yml"
project="fossbilling-cypress-${GITHUB_RUN_ID:-local}-$$"
cypress_image="${CYPRESS_DOCKER_IMAGE:-cypress/included:15.15.0}"
cypress_browser="${CYPRESS_BROWSER:-electron}"
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

  if [[ -n "${cypress_pull_pid:-}" ]]; then
    kill "${cypress_pull_pid}" 2>/dev/null || true
    wait "${cypress_pull_pid}" 2>/dev/null || true
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

set_commit_info() {
  if ! command -v git >/dev/null 2>&1 || ! git -C "${repo_root}" rev-parse --git-dir >/dev/null 2>&1; then
    return
  fi

  if [[ -z "${COMMIT_INFO_BRANCH:-}" ]]; then
    COMMIT_INFO_BRANCH="${GITHUB_HEAD_REF:-${GITHUB_REF_NAME:-}}"
    if [[ -z "${COMMIT_INFO_BRANCH}" ]]; then
      COMMIT_INFO_BRANCH="$(git -C "${repo_root}" rev-parse --abbrev-ref HEAD 2>/dev/null || true)"
      if [[ "${COMMIT_INFO_BRANCH}" == "HEAD" ]]; then
        COMMIT_INFO_BRANCH=""
      fi
    fi
  fi

  if [[ -z "${COMMIT_INFO_MESSAGE:-}" ]]; then
    COMMIT_INFO_MESSAGE="$(git -C "${repo_root}" show -s --pretty=%B 2>/dev/null || true)"
  fi
  if [[ -z "${COMMIT_INFO_EMAIL:-}" ]]; then
    COMMIT_INFO_EMAIL="$(git -C "${repo_root}" show -s --pretty=%ae 2>/dev/null || true)"
  fi
  if [[ -z "${COMMIT_INFO_AUTHOR:-}" ]]; then
    COMMIT_INFO_AUTHOR="$(git -C "${repo_root}" show -s --pretty=%an 2>/dev/null || true)"
  fi
  if [[ -z "${COMMIT_INFO_SHA:-}" ]]; then
    COMMIT_INFO_SHA="$(git -C "${repo_root}" rev-parse HEAD 2>/dev/null || true)"
  fi
  if [[ -z "${COMMIT_INFO_REMOTE:-}" ]]; then
    COMMIT_INFO_REMOTE="$(git -C "${repo_root}" config --get remote.origin.url 2>/dev/null || true)"
  fi

  export COMMIT_INFO_BRANCH COMMIT_INFO_MESSAGE COMMIT_INFO_EMAIL COMMIT_INFO_AUTHOR COMMIT_INFO_SHA COMMIT_INFO_REMOTE
}

if ! docker image inspect "${image}" >/dev/null 2>&1; then
  echo "Docker image '${image}' was not found. Build the test image before running Cypress tests."
  exit 1
fi

set_commit_info

cypress_args=(run --browser "${cypress_browser}")
if [[ -n "${CYPRESS_RECORD_KEY:-}" ]]; then
  cypress_args+=(--record --group "PHP 8.5 / ${cypress_browser}")
  if [[ -n "${GITHUB_RUN_ID:-}" ]]; then
    cypress_args+=(--ci-build-id "${GITHUB_RUN_ID}-${GITHUB_RUN_ATTEMPT:-1}")
  fi
else
  echo "CYPRESS_RECORD_KEY is not set. Running Cypress without Cloud recording."
fi

trap cleanup EXIT

docker pull "${cypress_image}" &
cypress_pull_pid=$!

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

wait "${cypress_pull_pid}"
cypress_pull_pid=""

# Chromium-based browsers are sensitive to Docker's default 64 MB /dev/shm size.
docker run --rm \
  --network "${project}_default" \
  --shm-size=2g \
  --env CI \
  --env CYPRESS_BASE_URL="${app_url}" \
  --env CYPRESS_ADMIN_EMAIL="${test_email}" \
  --env CYPRESS_ADMIN_PASSWORD="${test_pass}" \
  --env CYPRESS_PROJECT_ID \
  --env CYPRESS_RECORD_KEY \
  --env COMMIT_INFO_AUTHOR \
  --env COMMIT_INFO_BRANCH \
  --env COMMIT_INFO_EMAIL \
  --env COMMIT_INFO_MESSAGE \
  --env COMMIT_INFO_REMOTE \
  --env COMMIT_INFO_SHA \
  --env GITHUB_ACTIONS \
  --env GITHUB_API_URL \
  --env GITHUB_BASE_REF \
  --env GITHUB_EVENT_NAME \
  --env GITHUB_HEAD_REF \
  --env GITHUB_JOB \
  --env GITHUB_REF \
  --env GITHUB_REF_NAME \
  --env GITHUB_REPOSITORY \
  --env GITHUB_RUN_ATTEMPT \
  --env GITHUB_RUN_ID \
  --env GITHUB_SERVER_URL \
  --env GITHUB_SHA \
  --env GITHUB_WORKFLOW \
  --volume "${repo_root}:/workspace" \
  --workdir /workspace \
  --entrypoint cypress \
  "${cypress_image}" \
  "${cypress_args[@]}"
