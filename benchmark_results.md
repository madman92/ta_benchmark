# Benchmark Results

| Framework | Req/s | Median Latency | P95 Latency | Error Rate | Dropped |
|---|---|---|---|---|---|
| fpm | 1199.50 | 1.30ms | 16.15ms | 0.00% | 0 |
| laravel-spawn | 1199.63 | 0.36ms | 10.63ms | 0.00% | 0 |
| octane_frankenphp | 1199.60 | 0.61ms | 10.87ms | 0.00% | 0 |
| octane_swoole | 1198.89 | 0.65ms | 10.84ms | 0.00% | 0 |
| symfony-spawn | 1199.58 | 0.18ms | 10.35ms | 0.00% | 0 |
| symfony-swoole | 1199.71 | 0.22ms | 10.34ms | 0.00% | 0 |
| symfony-swoole-coroutine | 1199.62 | 0.32ms | 10.45ms | 0.00% | 0 |
| nodejs-fastify | 1199.63 | 0.13ms | 10.33ms | 0.00% | 0 |
| nodejs-express | 1199.63 | 0.16ms | 10.37ms | 0.00% | 0 |
| nodejs-nestjs | 1199.59 | 0.18ms | 10.39ms | 0.00% | 0 |
| nodejs-nextjs | 1199.62 | 0.70ms | 11.05ms | 0.00% | 0 |

## /bench Endpoint Results

| Framework | Req/s | Median Latency | P95 Latency | Error Rate | Dropped |
|---|---|---|---|---|---|
| fpm | 770.23 | 1352.64ms | 1523.94ms | 17.07% | 6890 |
| laravel-spawn | 996.15 | 5.85ms | 58.43ms | 0.00% | 55 |
| octane_frankenphp | 999.18 | 6.82ms | 68.70ms | 0.00% | 12 |
| octane_swoole | 967.55 | 6.77ms | 96.17ms | 0.00% | 355 |
| symfony-spawn | 999.77 | 3.49ms | 35.58ms | 0.00% | 0 |
| symfony-swoole | 998.98 | 5.79ms | 71.56ms | 0.00% | 19 |
| symfony-swoole-coroutine | 992.32 | 3.98ms | 172.45ms | 0.00% | 215 |
| nodejs-fastify | 998.70 | 3.76ms | 46.47ms | 0.00% | 35 |
| nodejs-express | 999.86 | 3.61ms | 41.95ms | 0.00% | 0 |
| nodejs-nestjs | 999.06 | 4.84ms | 50.38ms | 0.00% | 24 |
| nodejs-nextjs | 996.92 | 6.62ms | 57.74ms | 0.00% | 88 |
