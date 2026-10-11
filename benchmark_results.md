# Benchmark Results

| Framework | Req/s | Median Latency | P95 Latency | Error Rate | Dropped |
|---|---|---|---|---|---|
| fpm | 1199.43 | 1.29ms | 16.10ms | 0.00% | 0 |
| laravel-spawn | 1199.59 | 0.37ms | 10.63ms | 0.00% | 0 |
| octane_frankenphp | 1199.62 | 0.66ms | 10.91ms | 0.00% | 0 |
| octane_swoole | 1199.59 | 0.74ms | 10.94ms | 0.00% | 0 |
| symfony-spawn | 1199.63 | 0.17ms | 10.32ms | 0.00% | 0 |
| symfony-swoole | 1199.57 | 0.22ms | 10.35ms | 0.00% | 0 |
| symfony-swoole-coroutine | 1199.64 | 0.33ms | 10.45ms | 0.00% | 0 |
| nodejs-fastify | 1199.61 | 0.13ms | 10.35ms | 0.00% | 0 |
| nodejs-express | 1199.62 | 0.16ms | 10.39ms | 0.00% | 0 |
| nodejs-nestjs | 1199.62 | 0.18ms | 10.40ms | 0.00% | 0 |
| nodejs-nextjs | 1199.65 | 0.64ms | 10.98ms | 0.00% | 0 |

## /bench Endpoint Results

| Framework | Req/s | Median Latency | P95 Latency | Error Rate | Dropped |
|---|---|---|---|---|---|
| fpm | 677.52 | 1454.91ms | 1679.59ms | 4.28% | 9248 |
| laravel-spawn | 997.75 | 4.14ms | 84.82ms | 0.00% | 61 |
| octane_frankenphp | 997.12 | 10.28ms | 97.86ms | 0.00% | 65 |
| octane_swoole | 996.92 | 11.26ms | 117.23ms | 0.00% | 86 |
| symfony-spawn | 998.12 | 3.53ms | 75.33ms | 0.00% | 48 |
| symfony-swoole | 998.01 | 5.98ms | 91.45ms | 0.00% | 49 |
| symfony-swoole-coroutine | 993.60 | 3.93ms | 90.88ms | 0.00% | 46 |
| nodejs-fastify | 998.19 | 4.35ms | 77.56ms | 0.00% | 41 |
| nodejs-express | 997.96 | 3.60ms | 83.16ms | 0.00% | 56 |
| nodejs-nestjs | 997.86 | 3.69ms | 79.67ms | 0.00% | 60 |
| nodejs-nextjs | 997.76 | 4.22ms | 89.05ms | 0.00% | 62 |
