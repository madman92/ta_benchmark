# Benchmark fairness review — implementation status

The harness now uses a shared SQL schema and deterministic bounded fixture, a common sequential endpoint across all eleven adapters, and a separately reported concurrent endpoint on supported adapters. Response validation and isolated database-effect checks gate timed measurements.

The runner uses concurrent warmup, fixture reset between measurements, shuffled repeated trials, multiple offered rates, equal total app/database resource budgets, serving-SAPI OPcache checks, and retained raw summaries/runtime metadata. Reports include variability and only label rates passing every repeat as a tested lower bound. Historical results remain separate.

Successful-request access logging is disabled, including the inherited PHP-FPM access log and nginx logging. Local regression checks lint PHP sources and execute all twelve controllers with tracing database doubles to verify actual query order, parameters and async phase scheduling. Mutation checks prove reordered/skipped work and serialized async execution are rejected. Native database and pool overlap still require the separate container checks documented in README.md.

Remaining interpretation limits:

- TrueAsync uses PHP 8.6; other PHP deployments use 8.5. These are deployment comparisons rather than isolated adapter measurements.
- Blocking and pooled deployments have different connection/concurrency models. Sixteen workers does not imply equal database concurrency.
- Next.js includes a local HTTP proxy; FPM includes nginx. Both count toward their app resource budget.
- k6, database and apps share host hardware; quotas do not isolate CPU cores. Generator saturation and host load must be monitored for performance claims.
- Image tags can change. Inspect the recorded image IDs/runtime versions when comparing runs.
- Writes are bounded in row count but still generate WAL, dead tuples and changing counters. Each measurement starts from a reset fixture; this is a bounded mixed read/write workload, not a read-only microbenchmark.
- Warmup is concurrent and timed; it does not certify every worker/connection has reached an identical internal state.

See README.md for commands, defaults and the workload contract. Existing historical results do not describe this new workload.
