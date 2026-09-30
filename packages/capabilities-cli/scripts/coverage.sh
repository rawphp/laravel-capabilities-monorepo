#!/usr/bin/env bash
# Run the Go unit tests with coverage and fail when the module total is below the floor.
#
# Usage (from packages/capabilities-cli):
#   bash scripts/coverage.sh
#
# The floor applies to the module total from `go tool cover -func`, not to each package.
set -euo pipefail

MIN_COVERAGE=95
profile="$(mktemp)"
trap 'rm -f "${profile}"' EXIT

go test -coverprofile="${profile}" ./...

go tool cover -func="${profile}" | awk -v min="${MIN_COVERAGE}" '
  /^total:/ {
    found = 1
    pct = $NF
    sub(/%/, "", pct)
    printf "Go module total coverage: %s%% (floor %s%%)\n", pct, min
    if (pct + 0 < min + 0) { print "coverage below floor" > "/dev/stderr"; exit 1 }
  }
  END { if (!found) { print "no total line from go tool cover" > "/dev/stderr"; exit 1 } }
'
