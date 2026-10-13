# ta_benchmark

Deployment benchmarks for PHP server adapters and Node.js frameworks, using k6 and an identical PostgreSQL fixture.

## Workloads

- `/hello`: JSON response, no database.
- `/test`: one `SELECT pg_sleep(0.01)` query.
- `/bench`: **ten sequential SQL operations**, in the same order on all targets.
- `/bench-async`: the same operations, with seven concurrent reads, two concurrent writes, then one aggregate. Available on TrueAsync, Symfony OpenSwoole coroutine, and all Node.js targets. Reported separately.

The seven reads fetch a user, ten posts, top posts, a leaderboard, another user, five other posts, and ten recent views. Both writes follow the reads: update a pre-existing view row and increment its post counter. The final query aggregates that post's views. Neither mode promises a transactional snapshot under concurrent requests.

`benchmark/schema.sql` and `benchmark/reset.sql` are the source of truth: **100 users, 1,000 posts (ten per user), 1,000 view rows**, identical integer types, indexes, timestamps and initial counters. Writes do not add rows. Stable secondary sort keys remove timestamp ties. There is an index for recent views. The former growing INSERT workload and its historical results are not comparable to this workload.

## Targets

All targets configure **16 application workers**. This is not equal DB concurrency: blocking deployments generally have one active query per worker, while pooled async targets can use up to ten connections per worker. This architectural difference remains part of the deployment comparison.

Laravel TrueAsync uses an explicit FrankenPHP configuration with ten request queue slots per worker. The default single-slot queues rejected bursts during concurrent warmup, producing empty responses in the pinned runtime. Worker count and endpoint workloads are unchanged.

| Target | Port | Runtime family | Concurrent endpoint |
|---|---:|---|---|
| fpm | 8082 | PHP 8.5 FPM + nginx | No |
| laravel-spawn | 8083 | TrueAsync PHP 8.6 FrankenPHP | Yes |
| octane_swoole | 8084 | PHP 8.5 Swoole | No |
| symfony-spawn | 8085 | TrueAsync PHP 8.6 FrankenPHP | Yes |
| octane_frankenphp | 8086 | PHP 8.5 FrankenPHP | No |
| symfony-swoole | 8087 | PHP 8.5 Swoole | No |
| symfony-swoole-coroutine | 8088 | PHP 8.5 OpenSwoole, silpo-tech bundle | Yes |
| symfony-openswoole-bundle | 8093 | PHP 8.5 OpenSwoole, symfony-swoole bundle | Yes |
| nodejs-express | 8090 | Node 24 | Yes |
| nodejs-nestjs | 8091 | Node 24 | Yes |
| nodejs-nextjs | 8092 | Node 24, 16 instances behind a local proxy | Yes |

PHP images intentionally retain their supported version families. TrueAsync PHP 8.6 versus PHP 8.5, framework differences, connection lifecycle, and Next.js's proxy mean these are **deployment comparisons**, not isolated measurements of adapter overhead. Do not claim that all runtime costs are equal.

## Run

Requirements: Docker with Compose v2, k6, Python 3.10+. Ports listed above and the Compose database ports must be free. The runner creates uniquely named Compose projects and removes only its own containers and volumes. It does not stop existing projects or overwrite `benchmark_results.md`.

```bash
./run_all_benches.sh

# Select targets
./run_all_benches.sh fpm laravel-spawn nodejs-express

# Quick correctness/build smoke test; not suitable for performance claims
REPEATS=1 RATES=20 DURATION=3s WARMUP_DURATION=2s WARMUP_VUS=32 VUS=100 \
  ./run_all_benches.sh fpm nodejs-express
```

The default is a single-repeat comparison: 29 measurements across all 11 targets, about **16 minutes 55 seconds** of warmup and measurement plus build, startup and drain time. It does not establish repeat-based confidence. Use existing environment variables for longer runs; there are no profiles.

To investigate mixed capacity, use `MIXED_RATES="3000 4000 6000" ./run_all_benches.sh`; this adds mixed measurements while keeping database benchmark rates unchanged.

Defaults:

| Setting | Default | Meaning |
|---|---|---|
| `REPEATS` | `1` | Independent runs per target |
| `RATES` | `2000` | Offered `/bench` and `/bench-async` req/s |
| `MIXED_RATES` | `3000` | Total mixed req/s; supports space-separated rates |
| `DURATION` | `30s` | Measurement duration per workload/rate |
| `WARMUP_DURATION` | `5s` | Concurrent warmup before each measurement |
| `WARMUP_VUS` | `64` | Warmup concurrency |
| `VUS` | `1000` | Preallocated and maximum VUs per arrival-rate scenario |
| `REQUEST_TIMEOUT` | `10s` | Request timeout and measurement drain period |
| `ORDER_SEED` | `42` | Reproducible shuffle of target and workload/rate order |
| `APP_CPUS` / `APP_MEMORY_MB` | `4` / `2048` | Total application CPU quota and memory budget |
| `DB_CPUS` / `DB_MEMORY` | `2` / `2048m` | PostgreSQL CPU quota and memory budget |
| `RESULTS_DIR` | New directory under `results/` | Must not already exist |

The mixed workload defaults to 2,100 `/hello` + 900 `/test` requests/s in separate scenarios. `MIXED_RATES` scales the same 70% / 30% split (rounding hello down for nonmultiples of ten). Its default aggregate P95 limit is 200 ms; `/bench` is 500 ms. `P95_MS` overrides the aggregate limit; mixed per-endpoint P95 limits remain 200 ms. Passing requires all body/status checks, HTTP error rate below 1%, and zero dropped iterations. All VUs are preallocated to avoid allocation bursts during measurement.

For FPM, the application budget includes nginx (0.5 CPU and 256 MiB); PHP receives the remainder. Other adapters include their server/proxy within their app container. PostgreSQL uses the same `postgres:17-alpine` image family and `max_connections=500` everywhere. CPU quotas are not CPU affinity: k6 and the database still share the host. Monitor the load generator and use a quiet host with sufficient memory before interpreting saturation.

## What the runner verifies and retains

1. Starts a dedicated database, creates the common schema/fixture, builds and starts the app.
2. Checks DB readiness and retrieves `/debug/runtime` from the serving process. PHP must have OPcache enabled, 128 MiB, timestamp checks disabled, and JIT disabled. A mismatch stops the run.
3. Calls each supported benchmark mode in isolation, validates its response, and queries PostgreSQL to prove exactly one counter increment and the expected view update, with unchanged row counts. Passing load runs also verify the total counter increments against the completed request count.
4. Stops serving processes and resets the fixture before each workload, then starts them again to prevent abandoned requests from writing into the next trial. Performs concurrent warmup before every measurement, drains requests, then resets and analyzes the common fixture. Resetting also removes accumulated table/index bloat between measurements.
5. Runs each offered rate in shuffled order, retaining threshold failures as failed trials. Warmup threshold failures also mark the trial failed, but measurements and remaining targets continue. Warmup metrics are retained in `warmup-summary.json`; invalid response samples are logged in `warmup.log` or `k6.log` (up to nine per run). Execution/setup failures stop the run and retain logs.
6. Writes raw summaries, per-scenario metrics, exit status, runtime details, image IDs, fixture hash, a source archive including untracked files, source changes, execution order, and logs under `results/`. Reports medians and min–max across trials.

The reported highest passing rate must pass **every repeat**. It is a tested lower bound, not maximum capacity. Extend `RATES` if every offered rate passes. A value around 1,000 req/s at a 1,000 req/s offered rate cannot establish a winner.

Worker recycling is disabled for Symfony Swoole/OpenSwoole and effectively disabled for Octane using a billion-request limit. Image IDs and serving PHP/Node versions are recorded because floating image tags can change. PHP heap and Node heap statistics have been removed from benchmark responses; they are not comparable memory measurements. Use container-level instrumentation for a separate memory study.

## Manual endpoint testing

Use the shared SQL files rather than the old framework seeders or Node `init-db.js` files (retained as application scaffolding). On a dedicated local target:

```bash
cd nodejs-express  # or another target directory
# Start only the database before creating the benchmark schema.
docker compose up -d --wait db
cat ../benchmark/schema.sql ../benchmark/reset.sql | \
  docker compose exec -T db sh -c 'exec psql -X -v ON_ERROR_STOP=1 -U "$POSTGRES_USER" -d "$POSTGRES_DB"'
docker compose up -d --build
cd ..
BASE_URL=http://localhost:8090 k6 run k6/bench.js
BASE_URL=http://localhost:8090 MODE=async k6 run k6/bench.js
```

The compatibility scripts such as `k6/fpm.js` use the same mixed workload with target-specific URL defaults. Prefer the runner for published comparisons.

## Checks

```bash
python3 -m unittest discover -s tests -v
bash -n run_all_benches.sh bench.sh
k6 inspect k6/bench.js
```

With an OpenSwoole app and its database already started, check native statement reuse, changing bound parameters, parallel execution and recovery after a query error:

```bash
docker compose -f symfony-openswoole-bundle/compose.yml run --rm --no-deps \
  --entrypoint php -v "$PWD/tests/check_openswoole_dbpool.php:/tmp/check-dbpool.php:ro" \
  app /tmp/check-dbpool.php
```

The same check works with `symfony-swoole-coroutine/compose.yml`.

`RESULTS.md` and `benchmark_results.md` contain historical results from earlier workloads/configurations. New runs are deliberately written elsewhere.

## Swoole comparison

`./run_all_benches.sh octane_swoole symfony-swoole symfony-swoole-coroutine symfony-openswoole-bundle` compares the four Swoole deployments with the same fixture, offered rates and resource budgets. The new target uses [symfony-swoole/swoole-bundle](https://github.com/symfony-swoole/swoole-bundle) 0.35.3 with OpenSwoole 26.2, coroutine service isolation and a ten-connection native PostgreSQL pool per worker. It retains the same sequential and async SQL workloads. Its `/test` uses a coroutine-isolated Doctrine connection, provided by resetter-bundle 2.1.1. This is a comparison of complete deployments and integrations, not just the server extension.

Both Symfony OpenSwoole adapters use Doctrine DBAL/PDO for sequential `/bench`, matching `symfony-swoole` and its default `ATTR_DISABLE_PREPARES` behavior. Async `/bench-async` uses `DbPool` with the native coroutine PostgreSQL client and a maximum of ten connections per worker. Parameterized statements are prepared once per SQL per physical client and reused with new bound parameters; this remains named prepared execution, rather than PDO's unnamed parameterized execution. All ten SQL operations and their read/write phases remain the same. Sequential/async comparisons therefore include a driver difference as well as scheduling. PDO PostgreSQL blocks the worker in the current OpenSwoole image.

Updated package versions are recorded in [benchmark/DEPENDENCIES.md](benchmark/DEPENDENCIES.md).

Laravel Spawn creates one shared PDO template per worker with TrueAsync's native pool enabled (`PDO::ATTR_POOL_ENABLED`, maximum ten connections). The Laravel connection wrapper uses that same PDO for reads and writes, including concurrent child operations. Physical connection checkout and return are handled by TrueAsync; no application-level pool is used. The template is created before HTTP handling with `POOL_MIN=0`, so connections open lazily after FrankenPHP starts its scheduler. See the [TrueAsync PDO Pool documentation](https://true-async.github.io/en/docs/components/pdo-pool.html#binding-connections-to-coroutines).
