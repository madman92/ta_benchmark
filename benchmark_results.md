# Benchmark Results

| Framework | Req/s | Median Latency | P95 Latency | Error Rate | Dropped |
|---|---|---|---|---|---|
| fpm | 774.79 | 1100.16ms | 1435.41ms | 0.00% | 11747 |
| laravel-spawn | 1198.94 | 4.52ms | 70.50ms | 0.00% | 17 |
| octane_frankenphp | 1199.06 | 3.17ms | 86.85ms | 0.00% | 13 |
| octane_swoole | 1198.99 | 9.12ms | 89.32ms | 0.00% | 17 |
| symfony-spawn | 1199.65 | 0.16ms | 10.36ms | 0.00% | 0 |

## /bench Endpoint Results

| Framework | Req/s | Median Latency | P95 Latency | Error Rate | Dropped |
|---|---|---|---|---|---|
| fpm | 566.27 | 1745.64ms | 1952.17ms | 0.00% | 11912 |
| laravel-spawn | 997.04 | 9.65ms | 120.01ms | 0.00% | 81 |
| octane_frankenphp | 981.95 | 15.25ms | 492.93ms | 0.00% | 497 |
| octane_swoole | 972.80 | 80.75ms | 738.07ms | 0.00% | 763 |
| symfony-spawn | 997.51 | 6.30ms | 97.63ms | 0.00% | 63 |
