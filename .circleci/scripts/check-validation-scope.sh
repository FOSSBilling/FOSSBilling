#!/usr/bin/env bash
set -euo pipefail

# Keep every required job visible. Only automatic documentation-only branches
# may halt successfully; manual runs and uncertain comparisons run all tests.
if [[ "${VALIDATION_TRIGGER:-}" != webhook || "${CIRCLE_BRANCH:-main}" == main || "${FORCE_FULL_VALIDATION:-false}" == true ]]; then
  echo 'Run full validation: main, manual, or explicitly requested run.'
  exit 0
fi
# A fork's own main may contain application changes not present upstream.
if ! git fetch --quiet --no-tags https://github.com/FOSSBilling/FOSSBilling.git '+refs/heads/main:refs/remotes/validation/main'; then
  echo 'Run full validation: could not fetch main.'
  exit 0
fi
if ! base=$(git merge-base HEAD refs/remotes/validation/main); then
  echo 'Run full validation: no verified merge base.'
  exit 0
fi
changes=$(mktemp)
trap 'rm -f "$changes"' EXIT
# Disable rename detection so moving executable code into a docs path is tested.
if ! git diff --no-renames --name-only -z "$base" HEAD >"$changes"; then
  echo 'Run full validation: could not inspect changes.'
  exit 0
fi
count=0
while IFS= read -r -d '' path; do
  case "$path" in
    README.md|CONTRIBUTING.md|CODE_OF_CONDUCT.md|GOVERNANCE.md|.circleci/README.md|docs/*.md) ;;
    *) echo "Run full validation: $path"; exit 0 ;;
  esac
  count=$((count + 1))
done <"$changes"
if (( count == 0 )); then
  echo 'Run full validation: empty comparison.'
  exit 0
fi
echo "Documentation-only branch ($count files): no application validation needed."
circleci-agent step halt
