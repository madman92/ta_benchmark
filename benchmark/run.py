#!/usr/bin/env python3
"""Isolated, reproducible deployment benchmarks. Standard library only."""
import hashlib
import json
import os
from pathlib import Path
import platform
import random
import statistics
import signal
import zipfile
import subprocess
import sys
import time
import urllib.request
from datetime import datetime, timezone

ROOT = Path(__file__).resolve().parent.parent
TARGETS = {
    'fpm': 8082, 'laravel-spawn': 8083, 'octane_frankenphp': 8086,
    'octane_swoole': 8084, 'symfony-spawn': 8085, 'symfony-swoole': 8087,
    'symfony-swoole-coroutine': 8088,
    'nodejs-express': 8090, 'nodejs-nestjs': 8091, 'nodejs-nextjs': 8092,
    'symfony-openswoole-bundle': 8093,
}
ASYNC = {'laravel-spawn', 'symfony-spawn', 'symfony-swoole-coroutine',
         'nodejs-express', 'nodejs-nestjs', 'nodejs-nextjs', 'symfony-openswoole-bundle'}


def command(args, **kwargs):
    return subprocess.run(args, check=True, text=True, **kwargs)


def get_json(url):
    with urllib.request.urlopen(url, timeout=10) as response:
        if response.status != 200:
            raise RuntimeError(f'Unexpected status for {url}: {response.status}')
        return json.load(response)


def valid_body(body, mode):
    user = body.get('user', '')
    try:
        uid = int(user.removeprefix('User '))
    except (ValueError, AttributeError):
        return False
    expected = dict(posts=10, other_posts=5, views=1, top_posts=5,
                    leaderboard=5, recent_views=10, queries=10, mode=mode,
                    other_user=f'User {uid % 100 + 1}')
    return (1 <= uid <= 100 and user == f'User {uid}' and
            isinstance(body.get('time'), str) and
            all(body.get(key) == value for key, value in expected.items()))


def summary_values(data, exit_code):
    metrics = data['metrics']
    def values(name):
        return metrics[name]['values']
    checks = values('checks')
    latency = values('http_req_duration')
    requests = values('http_reqs')
    dropped = metrics.get('dropped_iterations', {}).get('values', {}).get('count', 0)
    # Exit status includes k6 thresholds. Never turn missing metrics into zero errors.
    return dict(rps=requests['rate'], count=requests['count'], median=latency['med'],
                p95=latency['p(95)'], errors=values('http_req_failed')['rate'],
                checks=checks['rate'], dropped=dropped,
                passed=exit_code == 0 and checks['rate'] == 1 and dropped == 0,
                exit_code=exit_code)


def write_report(output, rows, repeats):
    groups = {}
    for row in rows:
        groups.setdefault((row['target'], row['mode'], row['rate']), []).append(row)
    lines = ['# Benchmark results', '',
             ('Single-repeat exploratory comparison; repeat-based variability is unavailable.'
              if repeats == 1 else f'Configured repeats per target: {repeats}.'), '',
             'Bounded fixture; fixed offered rates. RPS is achieved load, not peak capacity.',
             'Median across trials; brackets show minimum–maximum. Raw metrics and runtime manifests are retained.', '',
             'Achieved req/s includes request drain time. Timeouts can extend a 30-second measurement and lower this average.', '']
    def spread(items, key):
        vals = [item[key] for item in items]
        return f'{statistics.median(vals):.2f} [{min(vals):.2f}–{max(vals):.2f}]'
    for mode, title in [('mixed', 'Mixed'), ('async', 'Async'), ('sequential', 'Sequential')]:
        lines += [f'## {title}', '',
                  '| Target | Offered req/s | Trials | Achieved req/s | P95 ms | Errors (max) | Drops (max) | Passes |',
                  '|---|---:|---:|---|---|---:|---:|---:|']
        selected = [(key, items) for key, items in sorted(groups.items()) if key[1] == mode]
        for (target, _, rate), items in selected:
            lines.append(f'| {target} | {rate} | {len(items)} | {spread(items, "rps")} | '
                         f'{spread(items, "p95")} | {max(x["errors"] for x in items):.2%} | '
                         f'{max(x["dropped"] for x in items)} | {sum(x["passed"] for x in items)}/{len(items)} |')
        if not selected:
            lines.append('| No trials recorded | — | — | — | — | — | — | — |')
        lines.append('')
    warmup_failures = [row for row in rows if row.get('warmup_exit_code') == 99]
    if warmup_failures:
        lines += ['', '## Failed warmup checks', '',
                  'These trials cannot pass, even if their measurement passes. See each workload’s warmup.log and warmup-summary.json.', '']
        for row in warmup_failures:
            lines.append(f'- Repeat {row["repeat"]}: {row["target"]} / {row["mode"]} / '
                         f'{row["rate"]} req/s: checks={row["warmup_checks"]:.2%}, '
                         f'HTTP errors={row["warmup_errors"]:.2%}.')
    lines += ['', '## Highest tested rate passing every repeat', '',
              'This is a tested lower bound, not a measured maximum. A missing or failing trial cannot qualify.', '']
    qualified = []
    workloads = sorted({(x['target'], x['mode']) for x in rows if x['mode'] != 'mixed'})
    for target, mode in workloads:
        passing = [rate for (t, m, rate), items in groups.items()
                   if (t, m) == (target, mode) and len(items) == repeats and all(x['passed'] for x in items)]
        if passing:
            qualified.append(f'- {target} / {mode}: {max(passing)} req/s')
    if qualified:
        lines += qualified
        if len(qualified) < len(workloads):
            lines += ['', 'Other sequential/async workloads have no rate passing every configured repeat.']
    elif workloads:
        tested = ', '.join(str(rate) for rate in sorted({x['rate'] for x in rows if x['mode'] != 'mixed'}))
        lines.append(f'No sequential or async workload passed every configured repeat at the tested rates: {tested} req/s. '
                     'This does not mean zero throughput. Lower passing rates remain unmeasured; '
                     'see the tables for achieved load, errors and drops.')
    else:
        lines.append('No sequential or async measurements were recorded.')
    (output / 'results.md').write_text('\n'.join(lines) + '\n')
    (output / 'trials.json').write_text(json.dumps(rows, indent=2) + '\n')


class Trial:
    def __init__(self, target, project, directory):
        self.target, self.directory = target, directory
        self.service = 'php' if target == 'fpm' else 'app'
        self.base = f'http://localhost:{TARGETS[target]}'
        cpus = float(os.getenv('APP_CPUS', '4'))
        memory = int(os.getenv('APP_MEMORY_MB', '2048'))
        if cpus <= 0.5 or memory <= 256:
            raise ValueError('APP_CPUS must exceed 0.5 and APP_MEMORY_MB must exceed 256')
        services = {self.service: {'cpus': cpus, 'mem_limit': f'{memory}m'},
                    'db': {'cpus': float(os.getenv('DB_CPUS', '4')),
                           'mem_limit': os.getenv('DB_MEMORY', '2048m')}}
        if target == 'fpm':
            # The proxy is included within the same total application budget.
            services['php'] = {'cpus': cpus - 0.5, 'mem_limit': f'{memory - 256}m'}
            services['nginx'] = {'cpus': 0.5, 'mem_limit': '256m'}
        override = directory / 'resources.json'
        override.write_text(json.dumps({'services': services}, indent=2))
        self.cmd = ['docker', 'compose', '-p', project, '-f', str(ROOT / target / 'compose.yml'),
                    '-f', str(override)]

    def compose(self, *args, **kwargs):
        return command(self.cmd + list(args), **kwargs)

    def sql(self, sql):
        try:
            # TCP excludes the socket-only server used during image initialization.
            return self.compose('exec', '-T', 'db', 'sh', '-c',
                                'export PGPASSWORD="$POSTGRES_PASSWORD"; '
                                'exec psql -h 127.0.0.1 -X -v ON_ERROR_STOP=1 -At '
                                '-U "$POSTGRES_USER" -d "$POSTGRES_DB"',
                                input=sql, capture_output=True).stdout.strip()
        except subprocess.CalledProcessError as error:
            with (self.directory / 'sql-errors.log').open('a') as log:
                log.write(f'{sql}\n{error.stdout}\n{error.stderr}\n')
            raise RuntimeError(f'Database operation failed; see {self.directory / "sql-errors.log"}') from error

    def reset(self):
        self.sql((ROOT / 'benchmark/reset.sql').read_text())

    def start(self):
        with (self.directory / 'build.log').open('w') as log:
            self.compose('up', '-d', '--wait', 'db', stdout=log, stderr=subprocess.STDOUT)
            self.sql((ROOT / 'benchmark/schema.sql').read_text())
            self.reset()
            self.compose('up', '-d', '--build', stdout=log, stderr=subprocess.STDOUT)
        self.wait_ready()
        (self.directory / 'images.json').write_text(self.compose('images', '--format', 'json', capture_output=True).stdout)
        (self.directory / 'database.txt').write_text(self.sql('SELECT version(); SHOW max_connections;'))

    def wait_ready(self):
        deadline = time.monotonic() + 120
        ready = 0
        # Next's proxy cycles through sixteen independently booting servers.
        required = 32 if self.target == 'nodejs-nextjs' else 1
        while True:
            try:
                ready = ready + 1 if get_json(self.base + '/test').get('ok') is True else 0
                if ready >= required:
                    break
            except Exception:
                ready = 0
            if time.monotonic() >= deadline:
                raise RuntimeError(f'{self.target} did not become ready; inspect build.log and containers.log')
            if ready == 0:
                time.sleep(1)
        runtime = get_json(self.base + '/debug/runtime')
        (self.directory / 'runtime.json').write_text(json.dumps(runtime, indent=2))
        if not self.target.startswith('nodejs'):
            if not runtime.get('opcache_enabled'):
                raise RuntimeError(f'{self.target}: serving SAPI has OPcache disabled')
            expected = {'enable': '1', 'enable_cli': '1', 'validate_timestamps': '0',
                        'memory_consumption': '128', 'jit': 'disable'}
            if runtime.get('opcache') != expected:
                raise RuntimeError(f'{self.target}: OPcache configuration differs: {runtime}')

    def restart_application(self):
        # A client timeout does not cancel server handlers. Stop all serving
        # processes before resetting, so abandoned handlers cannot write later.
        services = ['nginx', 'php'] if self.target == 'fpm' else ['app']
        with (self.directory / 'restarts.log').open('a') as log:
            self.compose('stop', '-t', '10', *services, stdout=log, stderr=subprocess.STDOUT)
            self.reset()
            self.compose('start', *services, stdout=log, stderr=subprocess.STDOUT)
        self.wait_ready()

    def verify(self, mode):
        self.reset()
        path = '/bench-async' if mode == 'async' else '/bench'
        body = get_json(self.base + path)
        if not valid_body(body, mode):
            raise RuntimeError(f'{self.target} {mode}: invalid response {body}')
        # One isolated request must update exactly its selected post and view.
        uid = int(body['user'].split()[1])
        post_id = 900 + uid
        sql = f'''SELECT (SELECT COUNT(*) FROM users), (SELECT COUNT(*) FROM posts),
            (SELECT COUNT(*) FROM post_views), (SELECT SUM(views_count) FROM posts),
            (SELECT views_count FROM posts WHERE id = {post_id}),
            (SELECT COUNT(*) FROM post_views WHERE ip_address <> '0.0.0.0'),
            (SELECT COUNT(*) FROM post_views WHERE id = {post_id} AND post_id = {post_id}
             AND ip_address LIKE '10.0.%' AND viewed_at > TIMESTAMP '2000-01-01');'''
        actual = self.sql(sql)
        if actual != '100|1000|1000|1|1|1|1':
            raise RuntimeError(f'{self.target} {mode}: incorrect DB effects: {actual}')
        (self.directory / f'correctness-{mode}.json').write_text(json.dumps(body, indent=2))
        self.reset()

    def run(self, mode, rate):
        folder = self.directory / f'{mode}-{rate}'
        folder.mkdir()
        self.restart_application()
        env = {**os.environ, 'BASE_URL': self.base, 'MODE': 'async' if mode == 'async' else 'sequential'}
        warmup_summary = folder / 'warmup-summary.json'
        env['SUMMARY_FILE'] = str(warmup_summary)
        with (folder / 'warmup.log').open('w') as log:
            warmup_result = subprocess.run(['k6', 'run', str(ROOT / 'k6/warmup.js')],
                                           env=env, stdout=log, stderr=subprocess.STDOUT)
        if warmup_result.returncode not in (0, 99) or not warmup_summary.exists():
            raise RuntimeError(f'k6 warmup execution failed: {folder / "warmup.log"}')
        warmup_metrics = json.loads(warmup_summary.read_text())['metrics']
        warmup_checks = warmup_metrics['checks']['values']['rate']
        warmup_errors = warmup_metrics['http_req_failed']['values']['rate']
        warmup_passed = warmup_result.returncode == 0 and warmup_checks == 1 and warmup_errors == 0
        if not warmup_passed:
            print(f'  warmup failed validation; measurement will be marked failed. See {folder / "warmup.log"}', flush=True)
            self.restart_application()
        # Quiescent reset after concurrent warmup: every measurement starts at the same state.
        self.reset()
        summary = folder / 'summary.json'
        env.update(RATE=str(rate), SUMMARY_FILE=str(summary))
        script = 'mixed.js' if mode == 'mixed' else 'bench.js'
        with (folder / 'k6.log').open('w') as log:
            result = subprocess.run(['k6', 'run', str(ROOT / 'k6' / script)], env=env,
                                    stdout=log, stderr=subprocess.STDOUT)
        if result.returncode not in (0, 99) or not summary.exists():
            raise RuntimeError(f'k6 execution failed: {folder / "k6.log"}')
        values = summary_values(json.loads(summary.read_text()), result.returncode)
        state = self.sql('SELECT (SELECT COUNT(*) FROM users), (SELECT COUNT(*) FROM posts), '
                         '(SELECT COUNT(*) FROM post_views), (SELECT SUM(views_count) FROM posts);')
        (folder / 'database-after.txt').write_text(state + '\n')
        if values['passed']:
            expected_updates = 0 if mode == 'mixed' else int(values['count'])
            if state != f'100|1000|1000|{expected_updates}':
                values['passed'] = False
                values['correctness_error'] = f'Unexpected database totals: {state}'
        values.update(warmup_exit_code=warmup_result.returncode,
                      warmup_checks=warmup_checks, warmup_errors=warmup_errors)
        values['passed'] = values['passed'] and warmup_passed
        return dict(target=self.target, mode=mode, rate=rate, **values)

    def stop(self):
        with (self.directory / 'containers.log').open('w') as log:
            subprocess.run(self.cmd + ['logs', '--no-color'], stdout=log, stderr=subprocess.STDOUT)
            if not self.target.startswith('nodejs'):
                path = 'var/log' if self.target.startswith('symfony') else 'storage/logs'
                subprocess.run(self.cmd + ['cp', f'{self.service}:/app/{path}',
                                          str(self.directory / 'application-logs')],
                               stdout=log, stderr=subprocess.STDOUT)
        self.compose('down', '-v', '--remove-orphans')


def main():
    targets = sys.argv[1:] or [target for target in TARGETS if target != 'nodejs-express']
    if len(set(targets)) != len(targets) or any(t not in TARGETS for t in targets):
        raise ValueError(f'Choose unique targets from: {", ".join(TARGETS)}')
    repeats = int(os.getenv('REPEATS', '1'))
    rates = sorted(set(map(int, os.getenv('RATES', '2000').split())))
    mixed_rates = sorted(set(map(int, os.getenv('MIXED_RATES', '3000').split())))
    if repeats < 1 or not rates or min(rates) < 1 or not mixed_rates or min(mixed_rates) < 2:
        raise ValueError('REPEATS and RATES must be positive; MIXED_RATES must be at least 2')
    os.environ.setdefault('DURATION', '30s')
    os.environ.setdefault('WARMUP_DURATION', '5s')
    measurements = repeats * sum(len(mixed_rates) + len(rates) * (2 if t in ASYNC else 1) for t in targets)
    print(f'{measurements} measurements: {os.environ["WARMUP_DURATION"]} warmup + '
          f'{os.environ["DURATION"]} measurement each; build, startup and drain time are additional.', flush=True)
    stamp = datetime.now(timezone.utc).strftime('%Y%m%dT%H%M%SZ') + f'-{os.getpid()}'
    output = Path(os.getenv('RESULTS_DIR', str(ROOT / 'results' / stamp))).resolve()
    output.mkdir(parents=True, exist_ok=False)
    # Include untracked implementation files, which git diff alone cannot reproduce.
    sources = command(['git', 'ls-files', '-z', '--cached', '--others', '--exclude-standard'],
                      cwd=ROOT, capture_output=True).stdout.split('\0')
    with zipfile.ZipFile(output / 'source.zip', 'w', zipfile.ZIP_DEFLATED) as archive:
        for name in sorted(set(sources)):
            path = ROOT / name
            if name and path.is_file() and output not in path.parents:
                archive.write(path, name)
    seed = int(os.getenv('ORDER_SEED', '42'))
    rng = random.Random(seed)
    plan = []
    for repeat in range(1, repeats + 1):
        order = targets.copy()
        rng.shuffle(order)
        plan.extend((repeat, t) for t in order)
    settings = {k: os.environ[k] for k in ['DURATION', 'VUS', 'P95_MS', 'REQUEST_TIMEOUT',
                'WARMUP_VUS', 'WARMUP_DURATION', 'APP_CPUS', 'APP_MEMORY_MB', 'DB_CPUS', 'DB_MEMORY'] if k in os.environ}
    manifest = dict(created_utc=stamp, host=platform.platform(), cpus=os.cpu_count(),
                    repeats=repeats, rates=rates, mixed_rates=mixed_rates, order_seed=seed, plan=plan, overrides=settings,
                    fixture_sha256=hashlib.sha256((ROOT / 'benchmark/schema.sql').read_bytes() +
                                                  (ROOT / 'benchmark/reset.sql').read_bytes()).hexdigest())
    for name, args in [('k6', ['k6', 'version']), ('docker', ['docker', 'version']),
                       ('git_commit', ['git', 'rev-parse', 'HEAD'])]:
        manifest[name] = command(args, cwd=ROOT, capture_output=True).stdout.strip()
    (output / 'manifest.json').write_text(json.dumps(manifest, indent=2))
    (output / 'source.diff').write_text(command(['git', 'diff', '--', '.'], cwd=ROOT, capture_output=True).stdout)
    rows = []
    for repeat, target in plan:
        directory = output / f'{repeat}-{target}'
        directory.mkdir()
        trial = Trial(target, f'ta-bench-{stamp.lower()}-{target.replace("_", "-")}', directory)
        print(f'[{repeat}/{repeats}] {target}: starting; logs in {directory}', flush=True)
        try:
            trial.start()
            modes = ['sequential'] + (['async'] if target in ASYNC else [])
            for mode in modes:
                trial.verify(mode)
            jobs = [('mixed', rate) for rate in mixed_rates] + [(mode, rate) for mode in modes for rate in rates]
            rng.shuffle(jobs)
            (directory / 'order.json').write_text(json.dumps(jobs))
            for mode, rate in jobs:
                print(f'  {mode} @ {rate} req/s: warming and measuring', flush=True)
                row = trial.run(mode, rate)
                rows.append(dict(repeat=repeat, **row))
                write_report(output, rows, repeats)
                print(f'  achieved={row["rps"]:.1f}, p95={row["p95"]:.1f}ms, passed={row["passed"]}', flush=True)
        finally:
            trial.stop()
    print(f'Results: {output / "results.md"}')


if __name__ == '__main__':
    def interrupted(signum, frame):
        raise KeyboardInterrupt(f'Interrupted by signal {signum}')
    signal.signal(signal.SIGTERM, interrupted)
    main()
