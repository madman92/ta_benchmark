"""Execute controllers with query spies. Native DB/pool checks remain separate."""
import importlib.util
import json
import os
from pathlib import Path
import shutil
import subprocess
import tempfile
import unittest

ROOT = Path(__file__).resolve().parents[1]
spec = importlib.util.spec_from_file_location('runner', ROOT / 'benchmark/run.py')
runner = importlib.util.module_from_spec(spec)
spec.loader.exec_module(runner)
QUERIES = [' '.join(q.lower().split()) for q in
           json.loads((ROOT / 'tests/workload.json').read_text())]
PHP_CONTROLLERS = (list(ROOT.glob('*/app/Http/Controllers/BenchmarkController.php')) +
                   list(ROOT.glob('*/src/Controller/BenchController.php')) +
                   [ROOT / 'hyperf/app/Controller/BenchController.php'])
NODE_CONTROLLERS = [ROOT / 'nodejs-express/server.js',
                    ROOT / 'nodejs-nestjs/src/bench/bench.service.ts',
                    ROOT / 'nodejs-nextjs/lib/bench.ts']
HAS_NODE = (shutil.which('node') and
            (ROOT / 'nodejs-nestjs/node_modules/typescript/lib/typescript.js').is_file())


def execute(source, mode):
    runtime, helper = ('php', 'trace_workload.php') if source.suffix == '.php' else ('node', 'trace_workload.cjs')
    result = subprocess.run([runtime, str(ROOT / 'tests' / helper), str(source), mode],
                            capture_output=True, text=True, timeout=15)
    if result.returncode:
        raise AssertionError(f'{source} / {mode}:\n{result.stdout}\n{result.stderr}')
    return json.loads(result.stdout)


class ExecutionAssertions:
    def assert_workload(self, result, mode):
        self.assertTrue(runner.valid_body(result['body'], mode), result['body'])
        trace = result['trace']
        starts = [event for event in trace if event['event'] == 'start']
        self.assertEqual([event['sql'] for event in starts], QUERIES)
        # Every query must complete, and no other query may execute.
        self.assertEqual(len(trace), 20)
        self.assertEqual(sorted(event['id'] for event in trace if event['event'] == 'end'),
                         sorted(event['id'] for event in starts))
        active = set()
        completed = set()
        peak = 0
        for event in trace:
            query = event['id']
            if event['event'] == 'start':
                if mode == 'sequential':
                    self.assertFalse(active, 'Sequential SQL operations overlapped')
                elif query >= 7:
                    self.assertTrue(set(range(7)).issubset(completed), 'Write began before all reads joined')
                    if query == 9:
                        self.assertTrue({7, 8}.issubset(completed), 'Aggregate began before both writes joined')
                active.add(query)
                peak = max(peak, len(active))
            else:
                self.assertIn(query, active)
                active.remove(query)
                completed.add(query)
        self.assertFalse(active)
        self.assertEqual(peak, 7 if mode == 'async' else 1)
        if mode == 'async':
            # Both writes must be dispatched before either finishes, too.
            writes = [event for event in trace if event['id'] in (7, 8)]
            self.assertEqual([event['event'] for event in writes], ['start', 'start', 'end', 'end'])
        uid = int(result['body']['user'].split()[1])
        other = uid % 100 + 1
        post = 900 + uid
        for index, params in [(0, [uid]), (1, [uid]), (4, [other]), (5, [other]),
                              (8, [post]), (9, [post])]:
            self.assertEqual(starts[index]['params'], params)
        self.assertEqual(starts[7]['params'][1], post)
        self.assertRegex(starts[7]['params'][0], r'^10\.0\.\d{1,3}\.\d{1,3}$')
        for index in (2, 3, 6):
            self.assertEqual(starts[index]['params'], [])

    def check_controller(self, source):
        target = source.relative_to(ROOT).parts[0]
        for mode in ['sequential'] + (['async'] if target in runner.ASYNC else []):
            with self.subTest(target=target, mode=mode):
                self.assert_workload(execute(source, mode), mode)
        with self.subTest(target=target, mode='test'):
            result = execute(source, 'test')
            self.assertTrue(result['body']['ok'])
            self.assertEqual(result['trace'], [
                dict(event='start', id=0, sql='select pg_sleep(0.01)', params=[]),
                dict(event='end', id=0)])


@unittest.skipUnless(shutil.which('php'), 'Install PHP CLI to check PHP syntax and workloads')
class PhpExecutionTests(ExecutionAssertions, unittest.TestCase):
    def test_php_application_and_test_sources_parse(self):
        paths = []
        for directory in [*(ROOT / target for target in runner.TARGETS), ROOT / 'tests']:
            for folder, directories, files in os.walk(directory):
                directories[:] = [d for d in directories if d not in
                                  {'vendor', 'node_modules', '.next', 'storage', '.git', 'cache'}]
                paths.extend(Path(folder) / name for name in files if name.endswith('.php'))
        for source in paths:
            with self.subTest(source=source.relative_to(ROOT)):
                result = subprocess.run(['php', '-l', str(source)], capture_output=True, text=True, timeout=10)
                self.assertEqual(result.returncode, 0, result.stdout + result.stderr)

    def test_php_controller_execution_and_scheduling(self):
        self.assertEqual(len(PHP_CONTROLLERS), 9)
        for source in PHP_CONTROLLERS:
            self.check_controller(source)

    def test_execution_checks_reject_serial_php_async(self):
        source = (ROOT / 'hyperf/app/Controller/BenchController.php').read_text()
        with tempfile.TemporaryDirectory() as tmp:
            controller = Path(tmp) / 'BenchController.php'
            controller.write_text(source)
            self.assert_workload(execute(controller, 'async'), 'async')
            serialized = source.replace('if ($parallel) {', 'if (false) {')
            self.assertNotEqual(source, serialized)
            controller.write_text(serialized)
            result = execute(controller, 'async')
            # The response and SQL are still correct: scheduling alone must fail.
            self.assertTrue(runner.valid_body(result['body'], 'async'))
            with self.assertRaises(AssertionError):
                self.assert_workload(result, 'async')


@unittest.skipUnless(HAS_NODE, 'Install Node and run npm ci --prefix nodejs-nestjs for execution checks')
class NodeExecutionTests(ExecutionAssertions, unittest.TestCase):
    def test_node_controller_execution_and_scheduling(self):
        for source in NODE_CONTROLLERS:
            self.check_controller(source)

    def test_execution_checks_reject_reordering_skipped_queries_and_serial_async(self):
        source = NODE_CONTROLLERS[1].read_text()
        loop = 'for (const query of queries) results.push(await query());'
        mutations = [
            ('sequential', source.replace(loop, 'for (const query of [...queries].reverse()) results.unshift(await query());')),
            ('sequential', source.replace(loop, 'for (const query of queries) results.push(results.length === 2 ? {rows: Array(5).fill({id: 1})} : await query());')),
            ('async', source.replace('if (parallel) return Promise.all(queries.map(query => query()));', '')),
        ]
        with tempfile.TemporaryDirectory() as tmp:
            mutated = Path(tmp) / 'bench.service.ts'
            mutated.write_text(source)
            self.assert_workload(execute(mutated, 'sequential'), 'sequential')
            self.assert_workload(execute(mutated, 'async'), 'async')
            for index, (mode, content) in enumerate(mutations):
                with self.subTest(mode=mode, mutation=index):
                    self.assertNotEqual(content, source, 'Mutation must change the controller')
                    mutated.write_text(content)
                    result = execute(mutated, mode)
                    self.assertTrue(runner.valid_body(result['body'], mode))
                    with self.assertRaises(AssertionError):
                        self.assert_workload(result, mode)


if __name__ == '__main__':
    unittest.main()
