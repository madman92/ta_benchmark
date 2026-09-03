<?php

namespace App\Http\Controllers;

use Illuminate\Support\Facades\DB;

final class BenchmarkController extends Controller
{
    public function home()
    {
        return view('welcome');
    }

    public function hello()
    {
        return response()->json([
            'message' => 'Hello from Octane Swoole!',
            'time' => now()->toIso8601String(),
        ]);
    }

    public function test()
    {
        DB::select('SELECT pg_sleep(0.01);');

        return response()->json(['ok' => true, 'time' => now()->toIso8601String()]);
    }

    public function bench()
    {
        $userId = rand(1, 100);
        $user = DB::selectOne('SELECT id, name, email FROM users WHERE id = ?', [$userId]);
        $posts = DB::select(
            'SELECT id, title, views_count FROM posts WHERE user_id = ? ORDER BY created_at DESC LIMIT 10',
            [$userId]
        );

        $postId = !empty($posts) ? $posts[0]->id : 1;
        DB::insert(
            'INSERT INTO post_views (post_id, ip_address, viewed_at) VALUES (?, ?, NOW())',
            [$postId, '10.0.' . rand(0, 255) . '.' . rand(0, 255)]
        );
        DB::update('UPDATE posts SET views_count = views_count + 1 WHERE id = ?', [$postId]);

        $stats = DB::selectOne(
            'SELECT COUNT(*) as total_views, MAX(viewed_at) as last_view FROM post_views WHERE post_id = ?',
            [$postId]
        );
        $topPosts = DB::select(
            'SELECT id, title, views_count FROM posts ORDER BY views_count DESC LIMIT 5'
        );
        $leaderboard = DB::select(
            'SELECT user_id, COUNT(*) as post_count FROM posts GROUP BY user_id ORDER BY post_count DESC LIMIT 5'
        );

        $otherUserId = ($userId % 100) + 1;
        $otherUser = DB::selectOne('SELECT id, name, email FROM users WHERE id = ?', [$otherUserId]);
        $otherPosts = DB::select(
            'SELECT id, title FROM posts WHERE user_id = ? LIMIT 5',
            [$otherUserId]
        );
        $recentViews = DB::select(
            'SELECT post_id, ip_address, viewed_at FROM post_views ORDER BY viewed_at DESC LIMIT 10'
        );

        return response()->json([
            'user' => $user->name,
            'posts' => count($posts),
            'views' => $stats->total_views,
            'top_posts' => count($topPosts),
            'leaderboard' => count($leaderboard),
            'other_user' => $otherUser->name,
            'other_posts' => count($otherPosts),
            'recent_views' => count($recentViews),
            'queries' => 10,
            'memory_usage' => memory_get_usage(),
            'memory_usage_real' => memory_get_usage(true),
            'memory_peak' => memory_get_peak_usage(),
            'memory_peak_real' => memory_get_peak_usage(true),
            'time' => now()->toIso8601String(),
        ]);
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
}
