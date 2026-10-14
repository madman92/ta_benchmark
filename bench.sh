#!/usr/bin/env bash
# Compatibility entrypoint for the original two-target comparison.
set -euo pipefail
cd "$(dirname "$0")"
export DURATION="${1:-30}s"
exec ./run_all_benches.sh symfony-swoole symfony-openswoole-bundle symfony-swoole-coroutine
