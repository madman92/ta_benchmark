# Benchmark Results

| Framework | Req/s | Median Latency | P95 Latency | Error Rate | Dropped |
|---|---|---|---|---|---|
| fpm | 831.18 | 1004.15ms | 1224.40ms | 0.00% | 10091 |
| laravel-spawn | 1198.56 | 2.68ms | 73.89ms | 0.00% | 29 |
| octane_frankenphp | 1199.15 | 5.69ms | 91.17ms | 0.00% | 15 |
| octane_swoole | 1198.82 | 11.24ms | 97.78ms | 0.00% | 19 |
| symfony-spawn | 1199.69 | 0.18ms | 10.39ms | 0.00% | 0 |
| symfony-swoole | 1199.62 | 0.25ms | 10.40ms | 0.00% | 0 |
| symfony-swoole-coroutine | 1199.61 | 0.36ms | 10.50ms | 0.00% | 0 |

## /bench Endpoint Results

| Framework | Req/s | Median Latency | P95 Latency | Error Rate | Dropped |
|---|---|---|---|---|---|
| fpm | 931.74 | 4.25ms | 1316.40ms | 89.18% | 2002 |
| laravel-spawn | 994.97 | 12.68ms | 124.06ms | 0.00% | 141 |
| octane_frankenphp | 969.96 | 26.16ms | 284.49ms | 0.00% | 349 |
| octane_swoole | 941.38 | 59.68ms | 1228.72ms | 0.00% | 1699 |
| symfony-spawn | 997.97 | 6.17ms | 96.03ms | 0.00% | 56 |
| symfony-swoole | 994.95 | 5.78ms | 100.24ms | 0.00% | 49 |
| symfony-swoole-coroutine | 998.59 | 1.73ms | 75.68ms | 0.00% | 41 |
