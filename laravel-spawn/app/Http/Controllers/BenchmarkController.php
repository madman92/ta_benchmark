<?php

namespace App\Http\Controllers;

use Illuminate\Support\Facades\DB;
use function Async\await_all_or_fail;
use function Async\spawn;

final class BenchmarkController extends Controller
{
    public function home()
    {
        return view('welcome');
    }

    public function hello()
    {
        return response()->json([
            'message' => 'Hello from TrueAsync!',
            'time' => now()->toIso8601String(),
        ]);
    }

    public function test()
    {
        DB::select('SELECT pg_sleep(0.01);');

        return response()->json(['ok' => true, 'time' => now()->toIso8601String()]);
    }

    public function connections()
    {
        $byState = DB::select("
            SELECT state, count(*) as cnt
            FROM pg_stat_activity
            WHERE datname = current_database()
            GROUP BY state
            ORDER BY cnt DESC
        ");

        $byClient = DB::select("
            SELECT client_addr, state, count(*) as cnt
            FROM pg_stat_activity
            WHERE datname = current_database()
            GROUP BY client_addr, state
            ORDER BY cnt DESC
            LIMIT 20
        ");

        $limit = DB::selectOne("SELECT current_setting('max_connections')::int AS max_connections");

        return response()->json([
            'max_connections' => $limit->max_connections,
            'total' => array_sum(array_column($byState, 'cnt')),
            'by_state' => $byState,
            'by_client' => $byClient,
            'time' => now()->toIso8601String(),
        ]);
    }

    public function bench()
    {
        return $this->runBench(false);
    }

    public function benchAsync()
    {
        return $this->runBench(true);
    }

    private function runBench(bool $parallel)
    {
        $userId = rand(1, 100);
        $otherUserId = ($userId % 100) + 1;

        // All reads precede writes in both modes. Only scheduling differs.
        [$user, $posts, $topPosts, $leaderboard, $otherUser, $otherPosts, $recentViews] = $this->runQueries([
            fn() => DB::selectOne('SELECT id, name, email FROM users WHERE id = ?', [$userId]),
            fn() => DB::select('SELECT id, title, views_count FROM posts WHERE user_id = ? ORDER BY created_at DESC, id DESC LIMIT 10', [$userId]),
            fn() => DB::select('SELECT id, title, views_count FROM posts ORDER BY views_count DESC, id DESC LIMIT 5'),
            fn() => DB::select('SELECT user_id, COUNT(*) as post_count FROM posts GROUP BY user_id ORDER BY post_count DESC, user_id ASC LIMIT 5'),
            fn() => DB::selectOne('SELECT id, name, email FROM users WHERE id = ?', [$otherUserId]),
            fn() => DB::select('SELECT id, title FROM posts WHERE user_id = ? ORDER BY id LIMIT 5', [$otherUserId]),
            fn() => DB::select('SELECT post_id, ip_address, viewed_at FROM post_views ORDER BY viewed_at DESC, id DESC LIMIT 10'),
        ], $parallel);

        $postId = $posts[0]->id;
        $ip = '10.0.' . rand(0, 255) . '.' . rand(0, 255);
        $this->runQueries([
            fn() => DB::update('UPDATE post_views SET ip_address = ?, viewed_at = NOW() WHERE id = ?', [$ip, $postId]),
            fn() => DB::update('UPDATE posts SET views_count = views_count + 1 WHERE id = ?', [$postId]),
        ], $parallel);

        $stats = DB::selectOne('SELECT COUNT(*) as total_views, MAX(viewed_at) as last_view FROM post_views WHERE post_id = ?', [$postId]);

        return response()->json([
            'user' => $user->name,
            'posts' => count($posts),
            'views' => (int) $stats->total_views,
            'top_posts' => count($topPosts),
            'leaderboard' => count($leaderboard),
            'other_user' => $otherUser->name,
            'other_posts' => count($otherPosts),
            'recent_views' => count($recentViews),
            'queries' => 10,
            'mode' => $parallel ? 'async' : 'sequential',
            'time' => gmdate('c'),
        ]);
    }

    private function runQueries(array $queries, bool $parallel): array
    {
        if ($parallel) {
            return await_all_or_fail(array_map(fn($query) => spawn($query), $queries));
        }
        $results = [];
        foreach ($queries as $query) {
            $results[] = $query();
        }
        return $results;
    }

    public function opcacheStatus()
    {
        $status = opcache_get_status(false);

        return response()->json([
            'sapi' => php_sapi_name(),
            'opcache_enabled' => $status['opcache_enabled'] ?? false,
            'cached_scripts' => $status['opcache_statistics']['num_cached_scripts'] ?? 0,
            'memory_used_mb' => round(($status['memory_usage']['used_memory'] ?? 0) / 1024 / 1024, 1),
        ]);
    }
    public function runtime()
    {
        return response()->json([
            'version' => PHP_VERSION,
            'sapi' => PHP_SAPI,
            'zts' => (bool) PHP_ZTS,
            'opcache_loaded' => extension_loaded('Zend OPcache'),
            'opcache_enabled' => function_exists('opcache_get_status') && (bool) (opcache_get_status(false)['opcache_enabled'] ?? false),
            'opcache' => array_combine(
                ['enable', 'enable_cli', 'validate_timestamps', 'memory_consumption', 'jit'],
                array_map(fn($key) => ini_get('opcache.' . $key),
                    ['enable', 'enable_cli', 'validate_timestamps', 'memory_consumption', 'jit'])
            ),
        ]);
    }
}
