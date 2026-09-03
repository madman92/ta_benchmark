# Benchmark Results

| Framework | Req/s | Median Latency | P95 Latency | Error Rate | Dropped |
|---|---|---|---|---|---|
| fpm | 1199.41 | 1.30ms | 16.25ms | 0.00% | 0 |
| laravel-spawn | 1199.63 | 0.42ms | 10.70ms | 0.00% | 0 |
| octane_frankenphp | 1199.59 | 0.68ms | 10.95ms | 0.00% | 0 |
| octane_swoole | 1199.61 | 0.83ms | 11.08ms | 0.00% | 0 |
| symfony-spawn | 1199.64 | 0.21ms | 10.40ms | 0.00% | 0 |
| symfony-swoole | 1199.64 | 0.27ms | 10.42ms | 0.00% | 0 |
| symfony-swoole-coroutine | 1199.69 | 0.38ms | 10.54ms | 0.00% | 0 |
| nodejs-fastify | 1199.60 | 0.16ms | 10.42ms | 0.00% | 0 |
| nodejs-express | 1199.64 | 0.18ms | 10.45ms | 0.00% | 0 |
| nodejs-nestjs | 1199.62 | 0.20ms | 10.48ms | 0.00% | 0 |
| nodejs-nextjs | 1199.62 | 0.74ms | 11.13ms | 0.00% | 0 |

## /bench Endpoint Results

| Framework | Req/s | Median Latency | P95 Latency | Error Rate | Dropped |
|---|---|---|---|---|---|
| fpm | 655.16 | 1540.98ms | 1694.31ms | 0.90% | 9455 |
| laravel-spawn | 993.78 | 6.08ms | 104.67ms | 0.00% | 177 |
| octane_frankenphp | 909.16 | 7.27ms | 1372.97ms | 0.00% | 1537 |
| octane_swoole | 997.68 | 10.46ms | 109.11ms | 0.00% | 64 |
| symfony-spawn | 997.04 | 4.03ms | 93.61ms | 0.00% | 78 |
| symfony-swoole | 995.09 | 6.03ms | 96.50ms | 0.00% | 44 |
| symfony-swoole-coroutine | 997.38 | 4.28ms | 101.95ms | 0.00% | 72 |
| nodejs-fastify | 993.46 | 3.70ms | 102.98ms | 0.00% | 188 |
| nodejs-express | 998.09 | 3.75ms | 85.28ms | 0.00% | 53 |
| nodejs-nestjs | 997.99 | 3.63ms | 78.45ms | 0.00% | 56 |
| nodejs-nextjs | 997.54 | 6.33ms | 75.61ms | 0.00% | 69 |
