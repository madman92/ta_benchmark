import importlib.util
import json
from pathlib import Path
import re
import tempfile
import unittest
from unittest.mock import Mock

ROOT = Path(__file__).resolve().parents[1]
spec = importlib.util.spec_from_file_location('runner', ROOT / 'benchmark/run.py')
runner = importlib.util.module_from_spec(spec)
spec.loader.exec_module(runner)


class ContractTests(unittest.TestCase):
    def test_reset_happens_only_after_serving_processes_stop(self):
        for target, services in [('fpm', ['nginx', 'php']), ('nodejs-express', ['app'])]:
            with self.subTest(target=target), tempfile.TemporaryDirectory() as tmp:
                trial = object.__new__(runner.Trial)
                trial.target, trial.directory = target, Path(tmp)
                events = []
                trial.compose = Mock(side_effect=lambda *args, **kwargs: events.append(args))
                trial.reset = Mock(side_effect=lambda: events.append(('reset',)))
                trial.wait_ready = Mock(side_effect=lambda: events.append(('ready',)))
                trial.restart_application()
                self.assertEqual(events, [('stop', '-t', '10', *services), ('reset',),
                                          ('start', *services), ('ready',)])

    def test_every_adapter_uses_same_ten_queries_in_same_order(self):
        files = (list(ROOT.glob('*/app/Http/Controllers/BenchmarkController.php')) +
                 list(ROOT.glob('*/src/Controller/BenchController.php')) +
                 [ROOT / 'hyperf/app/Controller/BenchController.php', ROOT / 'nodejs-express/server.js',
                  ROOT / 'nodejs-nestjs/src/bench/bench.service.ts', ROOT / 'nodejs-nextjs/lib/bench.ts'])
        self.assertEqual(len(files), 12)
        reference = None
        for file in files:
            text = file.read_text()
            if file.suffix == '.php':
                text = text.split('private function runBench(', 1)[1].split('private function runQueries(', 1)[0]
            elif 'async function bench(' in text:
                text = text.split('async function bench(', 1)[1].split("app.get('/bench'", 1)[0]
            elif 'async bench(' in text:
                text = text.split('async bench(', 1)[1].split('async connections(', 1)[0]
            queries = re.findall(r"'(SELECT [^'\n]+|UPDATE [^'\n]+|INSERT [^'\n]+)'", text)
            queries = [' '.join(re.sub(r'\$\d+', '?', q).lower().split()) for q in queries]
            self.assertEqual(len(queries), 10, file)
            self.assertFalse(any(q.startswith('insert') for q in queries), file)
            if reference is None:
                reference = queries
            self.assertEqual(queries, reference, file)

    def test_response_contract_rejects_old_or_incomplete_work(self):
        body = dict(user='User 100', other_user='User 1', posts=10, other_posts=5,
                    views=1, top_posts=5, leaderboard=5, recent_views=10,
                    queries=10, mode='sequential', time='2000-01-01T00:00:00Z')
        self.assertTrue(runner.valid_body(body, 'sequential'))
        for key, value in [('views', 0), ('queries', 5), ('posts', 0),
                           ('mode', 'async'), ('other_user', 'User 2'), ('user', None)]:
            self.assertFalse(runner.valid_body(dict(body, **{key: value}), 'sequential'))

    def test_failed_or_incomplete_trials_never_become_capacity_claims(self):
        row = dict(target='fpm', mode='sequential', rate=1000, rps=999,
                   p95=20, errors=0, dropped=0, passed=True)
        with tempfile.TemporaryDirectory() as tmp:
            path = Path(tmp)
            runner.write_report(path, [row], repeats=3)
            self.assertIn('No sequential or async workload passed', (path / 'results.md').read_text())
            runner.write_report(path, [row, row, dict(row, passed=False)], repeats=3)
            self.assertIn('No sequential or async workload passed', (path / 'results.md').read_text())
            runner.write_report(path, [row, row, row], repeats=3)
            self.assertIn('fpm / sequential: 1000 req/s', (path / 'results.md').read_text())

    def test_summary_requires_valid_metrics_and_honors_failures(self):
        data = {'metrics': {name: {'values': values} for name, values in {
            'checks': {'rate': 1}, 'http_req_duration': {'med': 2, 'p(95)': 5},
            'http_reqs': {'rate': 1000, 'count': 30000}, 'http_req_failed': {'rate': 0},
        }.items()}}
        self.assertTrue(runner.summary_values(data, 0)['passed'])
        self.assertFalse(runner.summary_values(data, 99)['passed'])
        data['metrics']['checks']['values']['rate'] = 0.9
        self.assertFalse(runner.summary_values(data, 0)['passed'])
        del data['metrics']['http_req_failed']
        with self.assertRaises(KeyError):
            runner.summary_values(data, 0)


if __name__ == '__main__':
    unittest.main()
