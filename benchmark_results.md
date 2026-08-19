# Benchmark Results

| Framework | Req/s | Median Latency | P95 Latency | Error Rate | Dropped |
|---|---|---|---|---|---|
| fpm | 757.19 | 1106.71ms | 1505.75ms | 0.00% | 12179 |
| laravel-spawn | 1196.30 | 4.66ms | 69.00ms | 0.00% | 80 |
| octane_frankenphp | 1198.22 | 5.56ms | 86.97ms | 0.00% | 11 |
| octane_swoole | 1198.71 | 12.55ms | 94.67ms | 0.00% | 22 |
| symfony-spawn | 1199.63 | 0.19ms | 10.37ms | 0.00% | 0 |
| symfony-swoole | 1199.61 | 0.22ms | 10.36ms | 0.00% | 0 |
| symfony-swoole-coroutine | 1199.63 | 0.31ms | 10.45ms | 0.00% | 0 |
| nodejs-fastify | 1199.65 | 0.14ms | 10.38ms | 0.00% | 0 |
| nodejs-express | 1199.66 | 0.16ms | 10.41ms | 0.00% | 0 |
| nodejs-nestjs | 1199.62 | 0.18ms | 10.42ms | 0.00% | 0 |
| nodejs-nextjs | 1199.56 | 0.66ms | 11.07ms | 0.00% | 0 |

## /bench Endpoint Results

| Framework | Req/s | Median Latency | P95 Latency | Error Rate | Dropped |
|---|---|---|---|---|---|
| fpm | 856.97 | 6.27ms | 1648.42ms | 77.09% | 2516 |
| laravel-spawn | 997.01 | 6.92ms | 90.55ms | 0.00% | 84 |
| octane_frankenphp | 930.36 | 11.46ms | 975.52ms | 0.00% | 983 |
| octane_swoole | 958.84 | 10.32ms | 491.91ms | 0.00% | 534 |
| symfony-spawn | 998.59 | 3.57ms | 64.67ms | 0.00% | 36 |
| symfony-swoole | 994.01 | 5.85ms | 100.82ms | 0.00% | 105 |
| symfony-swoole-coroutine | 998.40 | 3.99ms | 88.81ms | 0.00% | 42 |
| nodejs-fastify | 998.07 | 3.63ms | 76.85ms | 0.00% | 49 |
| nodejs-express | 998.04 | 3.61ms | 70.32ms | 0.00% | 51 |
| nodejs-nestjs | 998.30 | 3.63ms | 73.58ms | 0.00% | 44 |
| nodejs-nextjs | 998.02 | 4.40ms | 79.62ms | 0.00% | 55 |
