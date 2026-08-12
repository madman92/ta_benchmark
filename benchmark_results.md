# Benchmark Results

| Framework | Req/s | Median Latency | P95 Latency | Error Rate | Dropped |
|---|---|---|---|---|---|
| fpm | 766.32 | 1068.34ms | 1527.70ms | 0.00% | 11798 |
| laravel-spawn | 1198.46 | 5.43ms | 71.47ms | 0.00% | 36 |
| octane_frankenphp | 1198.63 | 5.70ms | 88.73ms | 0.00% | 27 |
| octane_swoole | 1197.55 | 11.57ms | 91.17ms | 0.00% | 19 |
| symfony-spawn | 1199.63 | 0.17ms | 10.33ms | 0.00% | 0 |
| symfony-swoole | 1199.62 | 0.20ms | 10.33ms | 0.00% | 0 |
| symfony-swoole-coroutine | 1199.62 | 0.30ms | 10.43ms | 0.00% | 0 |

## /bench Endpoint Results

| Framework | Req/s | Median Latency | P95 Latency | Error Rate | Dropped |
|---|---|---|---|---|---|
| fpm | 875.74 | 6.16ms | 1582.91ms | 77.19% | 2295 |
| laravel-spawn | 994.68 | 12.33ms | 130.50ms | 0.00% | 137 |
| octane_frankenphp | 953.77 | 14.03ms | 1102.42ms | 0.00% | 1380 |
| octane_swoole | 957.46 | 81.70ms | 530.03ms | 0.00% | 522 |
| symfony-spawn | 993.53 | 5.80ms | 119.98ms | 0.00% | 174 |
| symfony-swoole | 998.28 | 8.34ms | 93.35ms | 0.00% | 45 |
| symfony-swoole-coroutine | 998.36 | 1.81ms | 80.67ms | 0.00% | 45 |
