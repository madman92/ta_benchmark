# Symfony Swoole — Coroutines

## Enable coroutines

```yaml
# config/packages/swoole.yaml
swoole:
  platform:
    coroutines:
      enabled: true
      max_coroutines: 100
      max_concurrency: 100
      max_service_instances: 100
```

## Requirements

| Requirement | Details |
|---|---|
| PHP FFI extension | `php-ffi` must be installed |
| z-engine fork | `composer require swoole-bundle/z-engine` (original `lisachenko/z-engine` is unmaintained) |
| Kernel trait | Add `CoroutinesSupportingKernel` to `App\Kernel` |
| Stateful services | All services implementing `ResetInterface` are auto-proxified; register additional ones via config, tags, or compile processors |
| Opcache blacklist | Add `opcache.blacklist_filename` pointing to the generated blacklist file under `var/cache/{env}/swoole_bundle/opcache/blacklist.txt` |

## Known problems

1. **Highly experimental** — explicitly marked as "not recommended for production" in the docs; the z-engine dependency is unmaintained
2. **Final classes** — z-engine removes the `final` keyword at runtime via FFI to allow proxying, which is fragile
3. **Race conditions** — coroutines share memory within a single worker; stateful services **must** be properly proxified or you get data corruption
4. **Doctrine** — entity manager needs stability checking (use `swoole-bundle/resetter-bundle`)
5. **Opcache segfaults** — requires the blacklist workaround when `opcache.enable_cli` is on
6. **Resource limits** — must carefully tune `max_service_instances` to avoid exhausting DB connections and other pooled resources
7. **No production testing** — the feature was merged for maintenance convenience, not because it was battle-tested

## How it works

Without coroutines, the bundle handles concurrent requests via **multiple OS worker processes**. Each process handles one request at a time — no shared memory issues, simple and safe.

With coroutines enabled, multiple requests run **concurrently within a single worker** on the same thread via Swoole's coroutine scheduler. This is more memory-efficient and allows concurrent I/O without adding processes, but requires proxification of all stateful services to isolate their state per-coroutine.

## Key config references

- `max_coroutines` — max coroutines per process (default 100000)
- `max_concurrency` — limits concurrent request handling (default null = unlimited)
- `max_service_instances` — limits proxified service instances (default = max_concurrency or max_coroutines)
- `stateful_services` — FQCN list of additional services to proxify
- `compile_processors` — programmatic way to proxify services during container compilation

## Tags for services

| Tag | Purpose |
|---|---|
| `swoole_bundle.stateful_service` | Mark a service for proxification |
| `swoole_bundle.decorated_stateful_service` | Mark a decorated service for proxification |
| `swoole_bundle.safe_stateful_service` | Mark a resettable service as safe (no proxification needed) |
| `swoole_bundle.unmanaged_factory` | Mark services that create stateful services at runtime |
| `swoole_bundle.stability_checker` | Mark a service as a stability checker |
