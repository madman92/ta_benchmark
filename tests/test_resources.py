import importlib.util
import json
from pathlib import Path
import subprocess
import sys
import tempfile
import unittest
from unittest.mock import Mock, patch

spec = importlib.util.spec_from_file_location('runner', Path(__file__).resolve().parents[1] / 'benchmark/run.py')
runner = importlib.util.module_from_spec(spec)
spec.loader.exec_module(runner)


def reading(cid, cpu='12.50%', memory='16MiB / 1GiB'):
    return dict(ID=cid, Name=cid, CPUPerc=cpu, MemUsage=memory)


class ResourceTests(unittest.TestCase):
    def test_units_and_missing_readings(self):
        self.assertEqual(runner.memory_bytes('1.5GiB'), 1.5 * 1024**3)
        self.assertEqual(runner.memory_bytes('2MB'), 2_000_000)
        for value in ('', '--', 'NaNMiB', '-1MiB'):
            with self.assertRaises(ValueError):
                runner.memory_bytes(value)
        for raw in (reading('a', cpu=''), reading('a', cpu='NaN%'),
                    reading('a', memory='0B / 0B'), reading('unknown')):
            with self.assertRaises(ValueError):
                runner.resource_sample(raw, {'a': 'app'}, 0)
        self.assertEqual(runner.resource_sample(reading('a', cpu='0%'), {'a': 'app'}, 0)['cpu_percent'], 0)

    def test_memory_trend_and_short_runs(self):
        self.assertIsNone(runner.resource_summary([])['memory_peak_mib'])
        samples = [dict(elapsed_s=i * 10, memory_bytes=(10 + i) * 1024**2, cpu_percent=i * 10)
                   for i in range(11)]
        summary = runner.resource_summary(samples)
        self.assertEqual(summary['memory_change_mib'], 10)
        self.assertAlmostEqual(summary['memory_slope_mib_per_min'], 6)
        self.assertIsNone(runner.resource_summary(samples[:3])['memory_slope_mib_per_min'])
        self.assertIsNone(runner.resource_summary(samples[:1])['memory_change_mib'])

    def test_fpm_totals_do_not_include_database_and_frames_are_not_mixed(self):
        with tempfile.TemporaryDirectory() as tmp:
            profile = runner.ResourceProfile(Mock(target='fpm'), Path(tmp), 'measurement')
            profile.containers = {'p': 'php', 'n': 'nginx', 'd': 'db'}
            lines = [reading('p', '10%', '10MiB / 1GiB'), reading('n', '20%', '2MiB / 1GiB'),
                     reading('d', '90%', '30MiB / 1GiB')]
            profile.process = Mock(stdout=['\x1b[H' + json.dumps(line) + '\x1b[K\n' for line in lines] + ['\x1b[K\n', '\x1b[J'])
            profile.raw = Mock()
            profile.stopping = True
            profile.collect()
            app = profile.samples['application'][0]
            db = profile.samples['database'][0]
            self.assertEqual(app['cpu_percent'], 30)
            self.assertEqual(app['memory_bytes'], 12 * 1024**2)
            self.assertEqual(db['memory_bytes'], 30 * 1024**2)
            profile.process = Mock(stdout=[json.dumps(reading('p')), json.dumps(reading('p'))])
            profile.collect()
            self.assertTrue(profile.errors)
            self.assertEqual(len(profile.samples['application']), 1)

    def test_discovery_failure_keeps_null_metrics_and_original_exception(self):
        with tempfile.TemporaryDirectory() as tmp:
            trial = Mock(target='symfony-swoole')
            trial.compose.side_effect = RuntimeError('Docker unavailable')
            profile = runner.ResourceProfile(trial, Path(tmp), 'measurement')
            with self.assertRaisesRegex(ValueError, 'k6 failed'):
                with profile:
                    raise ValueError('k6 failed')
            self.assertFalse(profile.summary['complete'])
            self.assertIsNone(profile.summary['application']['cpu_avg_percent'])
            saved = json.loads((Path(tmp) / 'resource-summary.json').read_text())
            self.assertIn('Docker unavailable', saved['measurement']['errors'][0])

    def test_stream_is_stopped_on_completion_and_interruption(self):
        popen = subprocess.Popen
        for interrupt in (False, True):
            with self.subTest(interrupt=interrupt), tempfile.TemporaryDirectory() as tmp:
                trial = Mock(target='symfony-swoole')
                trial.compose.side_effect = [Mock(stdout='a\n'), Mock(stdout='d\n')]
                # A real long-lived subprocess verifies that collection cannot remain running.
                def launch(args, **kwargs):
                    return popen([sys.executable, '-c', 'import time; time.sleep(60)'], **kwargs)
                profile = runner.ResourceProfile(trial, Path(tmp), 'measurement')
                with patch.object(runner.subprocess, 'Popen', side_effect=launch):
                    if interrupt:
                        with self.assertRaises(KeyboardInterrupt):
                            with profile:
                                raise KeyboardInterrupt()
                    else:
                        with profile:
                            pass
                self.assertIsNotNone(profile.process.poll())
                self.assertFalse(profile.thread.is_alive())
                self.assertFalse(profile.summary['complete'])
                self.assertTrue((Path(tmp) / 'resource-summary.json').exists())

    def test_report_labels_incomplete_data(self):
        with tempfile.TemporaryDirectory() as tmp:
            empty = runner.resource_summary([])
            row = dict(target='fpm', mode='sequential', rate=20, rps=20, p95=1,
                       errors=0, dropped=0, passed=True,
                       resources={'measurement': dict(complete=False, application=empty, database=empty)})
            runner.write_report(Path(tmp), [row], 1)
            report = (Path(tmp) / 'results.md').read_text()
            self.assertIn('| N/A / N/A | N/A / N/A / N/A | N/A | N/A | incomplete (0/1) |', report)
            self.assertEqual(report.count('| fpm |'), 1)
            self.assertNotIn('| database |', report)
            self.assertNotIn('## CPU and RAM per trial', report)
            self.assertIn('not a leak verdict', report)


if __name__ == '__main__':
    unittest.main()
