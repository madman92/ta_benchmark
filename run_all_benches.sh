#!/bin/bash
set -euo pipefail

# Ensure we're in the benchmark directory
cd "$(dirname "$0")"

RESULTS_FILE="benchmark_results.md"

echo "# Benchmark Results" > "$RESULTS_FILE"
echo "" >> "$RESULTS_FILE"
echo "| Framework | Req/s | Median Latency | P95 Latency | Error Rate | Dropped |" >> "$RESULTS_FILE"
echo "|---|---|---|---|---|---|" >> "$RESULTS_FILE"

# The targets correspond to directory names
declare -a targets=(
    "fpm"
    "laravel-spawn"
    "octane_frankenphp"
    "octane_swoole"
    "symfony-spawn"
)

# Port mappings to verify the service is up
declare -A ports=(
    ["fpm"]="8082"
    ["laravel-spawn"]="8083"
    ["octane_frankenphp"]="8086"
    ["octane_swoole"]="8084"
    ["symfony-spawn"]="8085"
)

# K6 script mapping
declare -A k6_scripts=(
    ["fpm"]="k6/fpm.js"
    ["laravel-spawn"]="k6/laravel_spawn.js"
    ["octane_frankenphp"]="k6/octane_frankenphp.js"
    ["octane_swoole"]="k6/octane_swoole.js"
    ["symfony-spawn"]="k6/symfony_spawn.js"
)

# The name of the app container for artisan/console commands
declare -A services=(
    ["fpm"]="php"
    ["laravel-spawn"]="app"
    ["octane_frankenphp"]="app"
    ["octane_swoole"]="app"
    ["symfony-spawn"]="app"
)

wait_for() {
    local name=$1 url=$2 expect_200=${3:-0} max=60 i=0
    printf "Waiting for %s..." "$name"
    local curl_opts="-s"
    if [ "$expect_200" -eq 1 ]; then
        curl_opts="-sf"
    fi
    while ! curl $curl_opts -o /dev/null "$url" 2>/dev/null; do
        i=$((i + 1))
        if [ $i -ge $max ]; then
            echo " FAILED"
            return 1
        fi
        sleep 1
        printf "."
    done
    echo " OK"
}

migrate_and_seed() {
    local target=$1 svc=$2 quick=${3:-0}
    local max_retries=15
    local i=0
    if [ "$quick" -eq 1 ]; then
        echo "--- Migrating database (quick mode, no seed) for $target ---"
    else
        echo "--- Migrating and seeding database for $target ---"
    fi
    while [ $i -lt $max_retries ]; do
        if [ "$target" == "symfony-spawn" ]; then
            if docker compose exec -T -e APP_ENV=cli "$svc" php bin/console doctrine:migrations:migrate --no-interaction 2>/dev/null && \
               { [ "$quick" -eq 1 ] || docker compose exec -T -e APP_ENV=cli "$svc" php bin/console app:seed 2>/dev/null; }; then
                echo "Migration & seeding successful."
                return 0
            fi
        else
            if [ "$quick" -eq 1 ]; then
                if docker compose exec -T "$svc" php artisan migrate:fresh --force 2>/dev/null; then
                    echo "Migration successful (quick mode)."
                    return 0
                fi
            else
                if docker compose exec -T "$svc" php artisan migrate:fresh --seed --force 2>/dev/null; then
                    echo "Migration & seeding successful."
                    return 0
                fi
            fi
        fi
        i=$((i + 1))
        echo "Migration failed or container not ready yet, retrying in 2 seconds... ($i/$max_retries)"
        sleep 2
    done
    echo "Migrations failed after $max_retries retries!"
    return 1
}

for target in "${targets[@]}"; do
    echo "============================================="
    echo " Benchmarking: $target"
    echo "============================================="

    port="${ports[$target]}"
    script="${k6_scripts[$target]}"
    svc="${services[$target]}"

    echo "--- Starting $target ---"
    pushd "$target" > /dev/null
    
    # Ensure fresh state
    docker compose down -v 2>/dev/null || true
    docker compose up -d --build
    
    # Run migrations only (no seed — /hello and /test don't need seed data)
    migrate_and_seed "$target" "$svc" 1
    
    # Wait for the server to return 200 OK (DB is ready and server is listening)
    wait_for "$target (ready)" "http://localhost:${port}/hello" 1
    popd > /dev/null

    echo "--- Running k6 for $target ---"
    # Wait a few seconds for things to stabilize after seeding
    sleep 3
    
    SUMMARY_FILE="summary_${target}.json"
    
    # Run k6 and export summary as json
    if k6 run --summary-export="$SUMMARY_FILE" "$script"; then
        echo "k6 run successful"
    else
        echo "k6 run failed, continuing..."
    fi
    
    # Parse results
    if [ -f "$SUMMARY_FILE" ]; then
        REQ_S=$(jq '.metrics.http_reqs.rate // 0' "$SUMMARY_FILE" | awk '{printf "%.2f", $1}')
        MED_LAT=$(jq '.metrics.http_req_duration.med // 0' "$SUMMARY_FILE" | awk '{printf "%.2f", $1}')
        P95_LAT=$(jq '.metrics.http_req_duration."p(95)" // 0' "$SUMMARY_FILE" | awk '{printf "%.2f", $1}')
        ERR_RATE=$(jq '.metrics.http_req_failed.value // 0' "$SUMMARY_FILE" | awk '{printf "%.2f%%", $1 * 100}')
        DROPPED=$(jq '.metrics.dropped_iterations.count // 0' "$SUMMARY_FILE" | awk '{printf "%.0f", $1}')
        
        echo "| $target | $REQ_S | ${MED_LAT}ms | ${P95_LAT}ms | ${ERR_RATE} | $DROPPED |" >> "$RESULTS_FILE"
        rm "$SUMMARY_FILE"
    else
        echo "| $target | N/A | N/A | N/A | Error | N/A |" >> "$RESULTS_FILE"
    fi

    echo "--- Tearing down $target ---"
    pushd "$target" > /dev/null
    docker compose down -v
    popd > /dev/null

    echo "Finished $target"
    echo ""
done

# ──────────────────────────────────────────────
# Phase 2: /bench endpoint (5 DB queries per request)
# ──────────────────────────────────────────────
echo "" >> "$RESULTS_FILE"
echo "## /bench Endpoint Results" >> "$RESULTS_FILE"
echo "" >> "$RESULTS_FILE"
echo "| Framework | Req/s | Median Latency | P95 Latency | Error Rate | Dropped |" >> "$RESULTS_FILE"
echo "|---|---|---|---|---|---|" >> "$RESULTS_FILE"

for target in "${targets[@]}"; do
    echo "============================================="
    echo " Benchmarking /bench: $target"
    echo "============================================="

    port="${ports[$target]}"
    svc="${services[$target]}"

    echo "--- Starting $target for /bench ---"
    pushd "$target" > /dev/null
    docker compose up -d --build
    migrate_and_seed "$target" "$svc"
    wait_for "$target (ready)" "http://localhost:${port}/hello" 1
    popd > /dev/null

    echo "--- Running k6 bench for $target ---"
    sleep 3

    SUMMARY_FILE="summary_bench_${target}.json"

    if BASE_URL="http://localhost:${port}" k6 run --summary-export="$SUMMARY_FILE" k6/bench.js; then
        echo "k6 bench run successful"
    else
        echo "k6 bench run failed, continuing..."
    fi

    if [ -f "$SUMMARY_FILE" ]; then
        REQ_S=$(jq '.metrics.http_reqs.rate // 0' "$SUMMARY_FILE" | awk '{printf "%.2f", $1}')
        MED_LAT=$(jq '.metrics.http_req_duration.med // 0' "$SUMMARY_FILE" | awk '{printf "%.2f", $1}')
        P95_LAT=$(jq '.metrics.http_req_duration."p(95)" // 0' "$SUMMARY_FILE" | awk '{printf "%.2f", $1}')
        ERR_RATE=$(jq '.metrics.http_req_failed.value // 0' "$SUMMARY_FILE" | awk '{printf "%.2f%%", $1 * 100}')
        DROPPED=$(jq '.metrics.dropped_iterations.count // 0' "$SUMMARY_FILE" | awk '{printf "%.0f", $1}')

        echo "| $target | $REQ_S | ${MED_LAT}ms | ${P95_LAT}ms | ${ERR_RATE} | $DROPPED |" >> "$RESULTS_FILE"
        rm "$SUMMARY_FILE"
    else
        echo "| $target | N/A | N/A | N/A | Error | N/A |" >> "$RESULTS_FILE"
    fi

    echo "--- Tearing down $target ---"
    pushd "$target" > /dev/null
    docker compose down -v
    popd > /dev/null

    echo "Finished $target"
    echo ""
done

echo "============================================="
echo "All benchmarks completed!"
echo "Results written to $RESULTS_FILE"
echo "============================================="
cat "$RESULTS_FILE"
