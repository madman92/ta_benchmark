# Benchmark Results

| Framework | Req/s | Median Latency | P95 Latency | Error Rate | Dropped |
|---|---|---|---|---|---|
| fpm | 775.90 | 1101.74ms | 1413.56ms | 0.00% | 11646 |
| laravel-spawn | 1197.43 | 4.28ms | 79.30ms | 0.00% | 66 |
| octane_frankenphp | 1199.09 | 5.54ms | 88.88ms | 0.00% | 14 |
| octane_swoole | 1198.89 | 8.46ms | 94.26ms | 0.00% | 17 |
| symfony-spawn | 1199.64 | 0.17ms | 10.32ms | 0.00% | 0 |
| symfony-swoole | 1199.64 | 0.21ms | 10.33ms | 0.00% | 0 |
| symfony-swoole-coroutine | 1199.65 | 0.30ms | 10.42ms | 0.00% | 0 |
| nodejs-fastify | 1199.65 | 0.13ms | 10.33ms | 0.00% | 0 |
| nodejs-express | 1199.70 | 0.15ms | 10.36ms | 0.00% | 0 |

## /bench Endpoint Results

| Framework | Req/s | Median Latency | P95 Latency | Error Rate | Dropped |
|---|---|---|---|---|---|
| fpm | 923.17 | 5.90ms | 1556.54ms | 81.52% | 2300 |
| laravel-spawn | 994.41 | 13.51ms | 138.24ms | 0.00% | 160 |
| octane_frankenphp | 940.67 | 9.89ms | 1199.01ms | 0.00% | 1698 |
| octane_swoole | 912.36 | 124.27ms | 1225.50ms | 0.00% | 1463 |
| symfony-spawn | 997.68 | 5.80ms | 91.27ms | 0.00% | 57 |
| symfony-swoole | 997.92 | 7.19ms | 96.70ms | 0.00% | 58 |
| symfony-swoole-coroutine | 998.27 | 2.25ms | 76.69ms | 0.00% | 44 |
| nodejs-fastify | 996.23 | 6.00ms | 100.93ms | 0.00% | 102 |
| nodejs-express | 996.25 | 5.94ms | 97.21ms | 0.00% | 101 |
