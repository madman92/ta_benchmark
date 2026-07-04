# ta_benchmark

Benchmark comparing different PHP server adapters under load using [k6](https://k6.io).

## Adapters

| Adapter                              | Port | Workers | Image                                        |
|--------------------------------------|------|---------|----------------------------------------------|
| PHP-FPM + nginx                      | 8082 | 12      | `php:8.4-fpm-alpine` + `nginx:alpine`        |
| Octane Swoole                        | 8084 | 12      | `phpswoole/swoole:5.1-php8.3-alpine`         |
| laravel-spawn (TrueAsync FrankenPHP) | 8083 | 12      | `trueasync/php-true-async:latest-frankenphp` |
| symfony-spawn (TrueAsync FrankenPHP) | 8085 | 12      | `trueasync/php-true-async:latest-frankenphp` |

## Routes tested

| Route                    | Description                                                                            |
|--------------------------|----------------------------------------------------------------------------------------|
| `GET /hello`             | Pure JSON response, no DB                                                              |
| `GET /test`              | One DB query (`SELECT pg_sleep(0.01)`, simulates 10ms DB latency)                      |
| `GET /bench`             | 5 DB queries: SELECT user, SELECT posts, INSERT view, UPDATE counter, SELECT aggregate |
| `GET /debug/connections` | Live PostgreSQL connection stats (active, idle, total vs max) — TrueAsync only         |

---

## Setup

### Requirements

- Docker + Docker Compose
- [k6](https://k6.io/docs/get-started/installation/)

### Clone

```bash
git clone https://github.com/YanGusik/ta_benchmark.git
cd ta_benchmark
```

---

### PHP-FPM

```bash
cd fpm

# 1. Build the image, then install dependencies before starting
#    (the container crashes on startup if vendor/ is missing)
docker compose build
docker compose run --rm php composer install --ignore-platform-reqs

# 2. Start services
docker compose up -d

# 3. First-time setup
docker compose exec php chmod -R 777 /app/storage /app/bootstrap/cache
docker compose exec php php artisan key:generate
docker compose exec php php artisan migrate
docker compose exec php php artisan db:seed --class=BenchmarkSeeder
curl http://localhost:8082/hello
```

---

### Octane Swoole

```bash
cd octane_swoole

# 1. Build the image, then install dependencies before starting
docker compose build
docker compose run --rm app composer install --ignore-platform-reqs

# 2. Start services
docker compose up -d

# 3. First-time setup
docker compose exec app chmod -R 777 /app/storage /app/bootstrap/cache
docker compose exec app php artisan key:generate
docker compose exec app php artisan migrate
docker compose exec app php artisan db:seed --class=BenchmarkSeeder
curl http://localhost:8084/hello
```

---

### laravel-spawn — TrueAsync FrankenPHP

laravel-spawn uses a custom PHP extension for coroutines and requires **PHP 8.6** (only available inside the Docker
image — not a standard PHP release).
The package [`yangusik/laravel-spawn`](https://github.com/YanGusik/laravel-spawn) is pulled from a VCS repository.
Use `--ignore-platform-reqs` so Composer doesn't reject the PHP 8.6 requirement on your local machine.

```bash
cd laravel-spawn

# 1. Install dependencies before starting
#    (uses pre-built image — no build step needed)
docker compose run --rm app composer install --ignore-platform-reqs

# 2. Start the server
docker compose up -d

# 3. First-time setup
docker compose exec app chmod -R 777 /app/storage /app/bootstrap/cache
docker compose exec app php artisan key:generate
docker compose exec app php artisan migrate
docker compose exec app php artisan db:seed --class=BenchmarkSeeder
docker compose exec app php artisan vendor:publish --tag=async-config

curl http://localhost:8083/hello
```

#### Updating the adapter

When [`yangusik/laravel-spawn`](https://github.com/YanGusik/laravel-spawn) is updated, pull the latest version:

```bash
cd laravel-spawn
docker compose run --rm app composer update yangusik/laravel-spawn --ignore-platform-reqs
docker compose restart app
```

#### DB connection pool

laravel-spawn uses a coroutine-aware PDO pool. The pool size is configured in `config/async.php`
(publish with `php artisan vendor:publish --tag=async-config`):

```php
'db_pool' => [
    'enabled' => true,
    'min'     => 2,
    'max'     => 10,   // per worker — 12 workers × 10 = 120 max connections total
    'healthcheck_interval' => 30,
],
```

PostgreSQL `max_connections` is set to **500** in `compose.yml` to accommodate the pool.
Monitor live connections during load:

```bash
curl -s http://localhost:8083/debug/connections | jq .

# Or watch during k6 run
watch -n1 'curl -s http://localhost:8083/debug/connections | jq "{total,max_connections,by_state}"'
```

---

### symfony-spawn — TrueAsync FrankenPHP

symfony-spawn uses the same TrueAsync FrankenPHP image but with a Symfony application.
The package [`yangusik/symfony-spawn`](https://github.com/YanGusik/symfony-spawn) is available on Packagist.
Use `--ignore-platform-reqs` so Composer doesn't reject the PHP 8.6 requirement on your local machine.

```bash
cd symfony-spawn

# 1. Install dependencies before starting
#    (uses pre-built image — no build step needed)
docker compose run --rm app composer install --ignore-platform-reqs

# 2. Start the server
docker compose up -d

# 3. First-time setup
docker compose exec app php bin/console doctrine:migrations:migrate --no-interaction
docker compose exec app php bin/console app:seed

curl http://localhost:8085/hello
```

#### Updating the adapter

When [`yangusik/symfony-spawn`](https://github.com/YanGusik/symfony-spawn) is updated, pull the latest version:

```bash
cd symfony-spawn
docker compose run --rm app composer update yangusik/symfony-spawn --ignore-platform-reqs
docker compose restart app
```

---

## Running benchmarks

All scripts use the same load: **840 hello req/s + 360 DB req/s = 1200 req/s total**.

```bash
# PHP-FPM
k6 run k6/fpm.js

# Octane Swoole
k6 run k6/octane_swoole.js

k6 run k6/octane_frankenphp.js

# laravel-spawn (TrueAsync FrankenPHP)
k6 run k6/laravel_spawn.js

# symfony-spawn (TrueAsync FrankenPHP)
k6 run k6/symfony_spawn.js

# /bench endpoint (5 DB queries per request), target adapter via BASE_URL
BASE_URL=http://localhost:8083 k6 run k6/bench.js
BASE_URL=http://localhost:8085 k6 run k6/bench.js
BASE_URL=http://localhost:8084 k6 run k6/bench.js
BASE_URL=http://localhost:8082 k6 run k6/bench.js
BASE_URL=http://localhost:8086 k6 run k6/bench.js
```

---

## Results

Load: **840 req/s `/hello` + 360 req/s `/test` = 1200 req/s total**, `constant-arrival-rate`, 30s duration.
PHP-FPM and Octane Swoole: **12 workers**. laravel-spawn and symfony-spawn: **12 workers**.
Environment: WSL2 (12 cores).

| Metric                | PHP-FPM (12w) | Octane Swoole (12w) | laravel-spawn (12w) | symfony-spawn (12w) |
|-----------------------|---------------|---------------------|---------------------|---------------------|
| Target rate           | 1200 req/s    | 1200 req/s          | 1200 req/s          | 1200 req/s          |
| Actual throughput     | ~200 req/s    | ~752 req/s          | **~1186 req/s**     | **~1200 req/s**     |
| Dropped iterations    | ~28 000       | ~5 000              | 405                 | **0**               |
| avg latency           | ~4 000ms      | ~880ms              | ~20ms               | **~8ms**            |
| p(95) latency         | ~5 000ms      | 2 320ms             | 34ms                | **11ms**            |
| p(95) < 200ms         | ✗             | ✗                   | **✓**               | **✓**               |
| Failed requests       | 0%            | 0%                  | **0%**              | **0%**              |
| DB connections (peak) | —             | —                   | 120                 | 120                 |

---

## Architecture comparison

|               | PHP-FPM                      | Octane Swoole                   | laravel-spawn                                    | symfony-spawn                                    |
|---------------|------------------------------|---------------------------------|--------------------------------------------------|--------------------------------------------------|
| Framework     | Laravel                      | Laravel                         | Laravel                                          | Symfony                                          |
| Request model | Process per request          | 1 process = 1 request at a time | 1 worker = N coroutines                          | 1 worker = N coroutines                          |
| DB I/O        | Blocking (new conn each req) | Blocking (PDO synchronous)      | **Non-blocking (coroutine yield)**               | **Non-blocking (coroutine yield)**               |
| Memory model  | Stateless                    | Long-lived process              | Long-lived process + coroutine context isolation | Long-lived process + coroutine context isolation |
| App bootstrap | Every request                | Once per worker                 | Once per worker                                  | Once per worker                                  |

**Why TrueAsync wins on DB-bound load:**
Swoole keeps the app in memory (avoids bootstrap cost) but PDO is still synchronous —
a worker blocked on `pg_sleep(0.01)` cannot accept another request.
TrueAsync yields the coroutine on every DB call, so one worker handles hundreds of
concurrent DB-bound requests without blocking.

---

## Notes

- Each adapter has its own PostgreSQL instance on a separate port to avoid interference
- `APP_DEBUG=false` in all setups for fair comparison
- OPcache enabled in PHP-FPM
- PostgreSQL `max_connections=500` in all setups
- Absolute numbers will be higher on bare metal (benchmarks run on WSL2)
