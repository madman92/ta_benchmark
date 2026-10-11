"""Prove that HTTP 200 alone cannot pass the timed benchmark's checks."""
from http.server import BaseHTTPRequestHandler, ThreadingHTTPServer
import json
import os
from pathlib import Path
import shutil
import subprocess
import tempfile
import threading
import unittest

ROOT = Path(__file__).resolve().parents[1]


@unittest.skipUnless(shutil.which('k6'), 'k6 is not installed')
class K6ValidationTests(unittest.TestCase):
    def test_valid_and_invalid_success_responses(self):
        class Handler(BaseHTTPRequestHandler):
            body = {}
            def do_GET(self):
                self.send_response(200)
                self.send_header('Content-Type', 'application/json')
                self.end_headers()
                self.wfile.write(json.dumps(self.body).encode())
            def log_message(self, *_):
                pass
        server = ThreadingHTTPServer(('127.0.0.1', 0), Handler)
        thread = threading.Thread(target=server.serve_forever, daemon=True)
        thread.start()
        try:
            with tempfile.TemporaryDirectory() as tmp:
                for good in [False, True]:
                    Handler.body = (dict(user='User 1', other_user='User 2', posts=10,
                        other_posts=5, views=1, top_posts=5, leaderboard=5, recent_views=10,
                        queries=10, mode='sequential', time='2000-01-01T00:00:00Z') if good else {'ok': True})
                    summary = Path(tmp) / f'{good}.json'
                    env = {**os.environ, 'BASE_URL': f'http://127.0.0.1:{server.server_port}',
                           'MODE': 'sequential', 'RATE': '5', 'DURATION': '1s', 'VUS': '2',
                           'P95_MS': '5000', 'SUMMARY_FILE': str(summary)}
                    result = subprocess.run(['k6', 'run', str(ROOT / 'k6/bench.js')],
                        env=env, capture_output=True, text=True, timeout=30)
                    self.assertEqual(result.returncode, 0 if good else 99, result.stderr)
                    data = json.loads(summary.read_text())
                    self.assertEqual(data['metrics']['checks']['values']['rate'], 1 if good else 0.5)
        finally:
            server.shutdown()
            server.server_close()
            thread.join()


if __name__ == '__main__':
    unittest.main()
