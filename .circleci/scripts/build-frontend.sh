#!/usr/bin/env bash
set -euo pipefail

npm run check
# Each build owns a separate output directory; wait for every exit status.
mkdir -p test-results/frontend-build
builds=(build-core build-admin_default build-huraga)
pids=()
for build in "${builds[@]}"; do
  npm run "$build" >"test-results/frontend-build/${build}.log" 2>&1 &
  pids+=("$!")
done

status=0
for index in "${!pids[@]}"; do
  if wait "${pids[$index]}"; then
    echo "${builds[$index]} passed"
  else
    echo "${builds[$index]} failed" >&2
    status=1
  fi
  cat "test-results/frontend-build/${builds[$index]}.log"
done
if (( status != 0 )); then
  exit "$status"
fi
npm run pw:tsc
