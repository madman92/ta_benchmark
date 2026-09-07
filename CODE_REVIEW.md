# Code Review: `ta_benchmark`

## Overview
Benchmark harness comparing 11 adapters (PHP-FPM, Octane Swoole/FrankenPHP, TrueAsync `laravel-spawn`/`symfony-spawn`, `symfony-swoole` variants, Node Fastify/Express/Nest/Next) via k6 + Docker Compose. Methodology and docs (`README.md`, `RESULTS.md`) are strong. Main risks are fairness/bias issues, not crashes.

## Strengths
* Uniform Postgres (`postgres:17-alpine`, `max_connections=500`), uniform `APP_ENV=production`, `APP_DEBUG=false`.
* `/hello`, `/test` (`SELECT pg_sleep(0.01)`), `/bench` (10 queries) SQL text identical across stacks.
* `composer install --no-dev --optimize-autoloader`, `config:cache + route:cache` in all entrypoints.
* Phase-1 k6 scripts identical except `BASE_URL`; phase-2 `k6/bench.js` shared via `BASE_URL`.

## High

1. **Asymmetric 1s timeout only on TrueAsync** — ✅ Fixed. Removed `timeout(1000)` from `laravel-spawn/app/Http/Controllers/BenchmarkController.php` and `symfony-spawn/src/Controller/BenchController.php` (`await_all_or_fail([...])` now waits like Swoole/FPM/Node).
2. **No warmup in `run_all_benches.sh`** — ✅ Fixed. Now waits for `/test` (DB-backed) + warmup: 20x `/hello` + 20x `/test` + 10x `/bench` (~1s/target, primes OPcache/JIT, pools, pg buffers).
3. **Next.js proxy ignores respawn map** — ✅ Fixed. `nodejs-nextjs/entry.js` now round-robins over live `[...portByWorker.values()]` with 502 only if zero workers.

## Medium

4. **PHP version skew** — `laravel-spawn/Dockerfile:1`, `symfony-spawn/Dockerfile:1` = PHP 8.6 (`trueasync/...:php8.6`), all others (`fpm/docker/Dockerfile:3`, `octane_swoole/Dockerfile:1`, `octane_frankenphp/Dockerfile:1`, `symfony-swoole/docker/Dockerfile:1`) = PHP 8.5. Confounds framework claims.
5. **OPcache not verifiably equal** — `laravel-spawn/Dockerfile`, `symfony-spawn/Dockerfile` never `COPY opcache.ini` (files exist but unreferenced). Others do. Verify at runtime via `opcache_get_status()`, don't assume.
6. **Unequal DB pooling** — Node `pg.Pool max:10` x16 workers (~160 conns); coroutine Symfony `DoctrinePool maxSize:500` (`symfony-swoole-coroutine/config/services.yaml:11`); Laravel blocking stacks use stock single PDO/worker. Correct as baseline, but must be disclosed; current README implies equal pools.
7. **Single run, fixed order** — `run_all_benches.sh:10-22,146-147`: always `fpm -> ... -> nodejs-nextjs`, once each, `down -v --build` between. Page-cache/thermal state biases fixed positions. Fix: randomize + >=3 repeats, report variance.
8. **Execution model undisclosed** — async stacks do ~3 sequential round-trips (`Promise.all(7)+Promise.all(2)+1`), sync stacks do 10 sequential. That's the hypothesis, but `10 queries` label without concurrency note is misleading.

## Low

9. **Dead/stale files**: `k6/laravel_spawn_copy.js` (points to `:8088`, never used), `bench.sh` (refs missing `trueasync/` dir, `wrk`, 4 workers). Delete.
10. **Hardcoded absolute path**: `bench_laravel_cli.php:3-5` (`/home/edmond/...`) — breaks on any other machine. Use `__DIR__`.
11. **Seeding not identical**: Laravel `migrate:fresh --seed` vs Symfony row-by-row `password_hash(BCRYPT)` vs Node `scryptSync`; RNG unseeded. Same shape, different distribution/duration. Don't claim identical dataset.
12. **Incomparable memory fields**: Node `heapUsed` vs PHP `memory_get_usage()` (+`_real` only in Laravel); `COUNT(*)` int vs string. Breaks strict body/memory chart comparison.
13. **`max_request:200` only on `symfony-swoole-coroutine/config/packages/open_swoole.yaml:7`** — periodic recycle cost paid by one stack only. `opcache.enable_cli=0` inconsistently set. `nodejs-nestjs/Dockerfile` ships devDeps (no `--omit=dev`/multi-stage).
14. **Error swallowing**: `run_all_benches.sh:99-121` redirects migrate/seed to `/dev/null`; `wait_for .../hello` doesn't prove DB ready. Gate on `/bench` 200. `dropped_iterations` conflates client starvation with server failure.

## Recommended actions (priority order)
1. ~~Remove/unify `timeout(1000)`.~~ Done.
2. ~~Re-add warmup + gate on DB endpoint.~~ Done (~1s/target).
3. ~~Fix Next.js proxy to use live port map.~~ Done.
4. Pin PHP minor, verify OPcache at runtime, document pool sizes + concurrency model.
5. Randomize order, 3x repeats, delete dead files, fix hardcoded path.
