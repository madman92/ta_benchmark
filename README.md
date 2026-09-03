# ta_benchmark

Benchmark comparing different PHP server adapters and Node.js servers under load using [k6](https://k6.io).

## Adapters

| Adapter                              | Port | Workers | Image                                        |
|--------------------------------------|------|---------|----------------------------------------------|
| PHP-FPM + nginx                      | 8082 | 12      | `php:8.4-fpm-alpine` + `nginx:alpine`        |
| Octane Swoole                        | 8084 | 12      | `phpswoole/swoole:5.1-php8.3-alpine`         |
| laravel-spawn (TrueAsync FrankenPHP) | 8083 | 12      | `trueasync/php-true-async:latest-frankenphp` |
| symfony-spawn (TrueAsync FrankenPHP) | 8085 | 12      | `trueasync/php-true-async:latest-frankenphp` |
| nodejs-fastify (cluster)             | 8089 | 16      | `node:24-alpine`                             |
| nodejs-express (cluster)             | 8090 | 16      | `node:24-alpine`                             |
| nodejs-nestjs (cluster)              | 8091 | 16      | `node:24-alpine`                             |
| nodejs-nextjs (16 standalone + proxy)| 8092 | 16      | `node:24-alpine`                             |

## Routes tested

| Route                    | Description                                                                            |
|--------------------------|----------------------------------------------------------------------------------------|
| `GET /hello`             | Pure JSON response, no DB                                                              |
| `GET /test`              | One DB query (`SELECT pg_sleep(0.01)`, simulates 10ms DB latency)                      |
| `GET /bench`             | 10 DB queries: 7 concurrent reads, 2 concurrent writes, and 1 aggregate read            |
| `GET /debug/connections` | Live PostgreSQL connection stats (active, idle, total vs max) — TrueAsync + Node.js |

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

# 2. Start services (production config and route caches are built at startup)
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

# 2. Start services (production config and route caches are built at startup)
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

# 2. Start the server (production config and route caches are built at startup)
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

### nodejs-fastify / nodejs-express

Both are built in the container (`npm install` at build time) and use a `pg` connection pool
(min 2 / max 10 per worker, mirroring the TrueAsync pool) with a cluster of **16 workers**.

```bash
cd nodejs-fastify   # or nodejs-express

# 1. Build and start
docker compose up -d --build

# 2. First-time setup (schema + seed)
docker compose exec app node init-db.js

curl http://localhost:8089/hello   # fastify
curl http://localhost:8090/hello   # express
```

The worker count and ports are configurable via `WORKERS`, `PORT` and `DB_*` env vars in `compose.yml`.

---

### nodejs-nestjs

NestJS 11 (Express platform) — the "full-stack" Node.js counterpart to Laravel/Symfony:
dependency injection, modules, controllers, guards, pipes, with a raw `pg` pool (min 2 / max 10).
Each of the **16 cluster workers** boots the full DI container, mirroring the Octane worker model.

```bash
cd nodejs-nestjs

# 1. Build and start (compiles TypeScript in the container)
docker compose up -d --build

# 2. First-time setup (schema + seed)
docker compose exec app node init-db.js

curl http://localhost:8091/hello
```

---

### nodejs-nextjs

Next.js 16 (App Router, `output: 'standalone'`) — route handlers only (`/hello`, `/test`,
`/bench`, `/debug/connections`), raw `pg` pool. Next.js runs a single-threaded server, so
`entry.js` forks **16 standalone instances** (each on its own internal port) behind a tiny
round-robin proxy — note this adds one proxy hop that the other adapters don't have.

```bash
cd nodejs-nextjs

# 1. Build and start (multi-stage: next build inside the container)
docker compose up -d --build

# 2. First-time setup (schema + seed)
docker compose exec app node init-db.js

curl http://localhost:8092/hello
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

# nodejs (Fastify / Express, 16 workers)
k6 run k6/nodejs_fastify.js
k6 run k6/nodejs_express.js

# nodejs (NestJS / Next.js)
k6 run k6/nestjs.js
k6 run k6/nextjs.js

# /bench endpoint (5 DB queries per request), target adapter via BASE_URL
BASE_URL=http://localhost:8083 k6 run k6/bench.js
BASE_URL=http://localhost:8085 k6 run k6/bench.js
BASE_URL=http://localhost:8084 k6 run k6/bench.js
BASE_URL=http://localhost:8082 k6 run k6/bench.js
BASE_URL=http://localhost:8086 k6 run k6/bench.js
BASE_URL=http://localhost:8089 k6 run k6/bench.js
BASE_URL=http://localhost:8090 k6 run k6/bench.js
BASE_URL=http://localhost:8091 k6 run k6/bench.js
BASE_URL=http://localhost:8092 k6 run k6/bench.js
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
- Laravel benchmark endpoints use the stateless API middleware group; Laravel targets run with `APP_ENV=production` and cache config/routes at startup
- OPcache enabled in PHP-FPM
- Node.js targets run 16 cluster workers (12-core host) with a `pg` pool of max 10 per worker
- NestJS uses the Express platform (same underlay as the express entry) with its full DI/module/controller stack — a like-for-like match for Laravel/Symfony's framework weight; all entries use raw SQL, no ORM
- Next.js runs 16 standalone instances behind a round-robin proxy inside the container — one extra hop, and each instance is single-threaded
- PostgreSQL `max_connections=500` in all setups
- Absolute numbers will be higher on bare metal (benchmarks run on WSL2)
