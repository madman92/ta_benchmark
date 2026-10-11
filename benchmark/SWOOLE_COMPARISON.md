# Swoole deployment comparison

Measured on 2026-10-11 after updating project dependency lockfiles and runtime base images. One repeat, offered 2,000 req/s, 30-second measurements, 5-second warmups. Mixed traffic is 70% /hello and 30% /test. Every deployment has sixteen workers, a four-CPU/2-GiB application budget and a two-CPU/2-GiB PostgreSQL budget.

Achieved RPS includes drain time; a timeout tail can reduce that average. These are deployment comparisons at one offered rate, not maximum-capacity measurements or repeat-based confidence estimates.

| Deployment | Mixed req/s | Mixed pass | Sequential req/s | Async req/s |
|---|---:|---|---:|---:|
| symfony-swoole | 1999.5 | yes | 1733.5 | — |
| symfony-swoole-coroutine | 1715.5 | no | 1501.0 | 1440.1 |
| symfony-openswoole-bundle | 1999.3 | yes | 1339.0 | 1353.0 |
| octane_swoole | 1715.4 | no | 1456.8 | — |

The new symfony-swoole bundle passed mixed traffic with zero HTTP errors; its sequential and async database trials failed the latency/drop thresholds. Regular Symfony Swoole achieved the highest sequential RPS among these deployments in this run. The original OpenSwoole coroutine target and Octane Swoole had mixed-request timeouts; their low aggregate P95 values do not imply that every request was fast.

[Full workload tables, errors, drops and P95](../results/20261011T134830Z-216859/results.md). Raw trial metrics, logs, runtime metadata and the source snapshot are beside that report.

Validation: all 29 low-rate correctness smoke measurements passed across eleven targets, six automated tests passed, all Composer manifests/locks validated, Node/Next builds passed as part of image builds, and the shared Laravel frontend dependency set built successfully.

The runner now stops serving processes before resetting between workloads, preventing requests abandoned by k6 from writing into the next measurement. Failed warmups still disqualify their measurement.

[Dependency versions](DEPENDENCIES.md).
