#!/usr/bin/env bash
# Build the local redcap-docker image from the current working tree, then run
# the integration test suite inside Docker (no local Python install needed).
#
# Usage:
#   ./run-tests.sh                          # run all tests (clean output)
#   ./run-tests.sh -v                       # verbose: stream container logs live
#   ./run-tests.sh -v test_fresh_install.py # verbose + single file
#   ./run-tests.sh -k test_reconciler       # pass any extra pytest flags
#   ./run-tests.sh -v -k test_config -x     # combine -v with pytest flags
#
# -v enables pytest's -s flag (no output capture) so container boot logs and
# print() calls inside tests are streamed to the terminal in real time.
#
# Prerequisites: Docker, tests/.env filled in (copy from tests/.env.example).

set -euo pipefail

SCRIPT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
IMAGE="dzdde/redcap-docker:test-local"

VERBOSE=false
PYTEST_ARGS=()

while [[ $# -gt 0 ]]; do
    case "$1" in
        -v|--verbose)
            VERBOSE=true
            shift
            ;;
        *)
            PYTEST_ARGS+=("$1")
            shift
            ;;
    esac
done

if [ ! -f "$SCRIPT_DIR/tests/.env" ]; then
    echo "ERROR: tests/.env not found."
    echo "Copy tests/.env.example to tests/.env and fill in your REDCap credentials."
    exit 1
fi

echo "==> Building redcap-docker image from local source..."
docker build -t "$IMAGE" "$SCRIPT_DIR"

# Always show test names (-v). Add -s only in verbose mode to stream captured output.
PYTEST_FLAGS=("-v" "--tb=short")
if $VERBOSE; then
    PYTEST_FLAGS+=("-s")
fi

# Default target: all tests in the current directory.
if [ ${#PYTEST_ARGS[@]} -eq 0 ]; then
    PYTEST_ARGS=(".")
fi

echo "==> Running tests$(${VERBOSE} && echo ' [verbose]' || true)..."
REDCAP_DOCKER_IMAGE="$IMAGE" \
    docker compose -f "$SCRIPT_DIR/tests/docker-compose.test.yml" \
    run --rm --build test-runner \
    "${PYTEST_FLAGS[@]}" "${PYTEST_ARGS[@]}"
