<?php

use function Async\await_all_or_fail;
use function Async\spawn;
use function Async\timeout;
use Illuminate\Support\Facades\Route;

Route::get('/', function () {
    return view('welcome');
});

Route::get('/hello', fn() => response()->json([
    'message' => 'Hello from TrueAsync!',
    'time' => now()->toIso8601String(),
]));

Route::get('/test', function () {
    DB::select('SELECT pg_sleep(0.01);');
    return response()->json(['ok' => true, 'time' => now()->toIso8601String()]);
});

Route::get('/debug/connections', function () {
    // Total connections grouped by state
    $byState = DB::select("
        SELECT state, count(*) as cnt
        FROM pg_stat_activity
        WHERE datname = current_database()
        GROUP BY state
        ORDER BY cnt DESC
    ");

    // Per-application (client_addr) breakdown — useful to confirm connections from this app
    $byClient = DB::select("
        SELECT client_addr, state, count(*) as cnt
        FROM pg_stat_activity
        WHERE datname = current_database()
        GROUP BY client_addr, state
        ORDER BY cnt DESC
        LIMIT 20
    ");

    // PostgreSQL server limit
    $limit = DB::selectOne("SELECT current_setting('max_connections')::int AS max_connections");

    $total = array_sum(array_column($byState, 'cnt'));

    return response()->json([
        'max_connections' => $limit->max_connections,
        'total' => $total,
        'by_state' => $byState,
        'by_client' => $byClient,
        'time' => now()->toIso8601String(),
    ]);
});

Route::get('/bench', function () {
    $userId = rand(1, 100);
    $otherUserId = ($userId % 100) + 1;

    [$user, $posts, $topPosts, $leaderboard, $otherUser, $otherPosts, $recentViews] = await_all_or_fail([
        spawn(fn() => DB::selectOne('SELECT id, name, email FROM users WHERE id = ?', [$userId])),
        spawn(fn() => DB::select(
            'SELECT id, title, views_count FROM posts WHERE user_id = ? ORDER BY created_at DESC LIMIT 10',
            [$userId]
        )),
        spawn(fn() => DB::select(
            'SELECT id, title, views_count FROM posts ORDER BY views_count DESC LIMIT 5'
        )),
        spawn(fn() => DB::select(
            'SELECT user_id, COUNT(*) as post_count FROM posts GROUP BY user_id ORDER BY post_count DESC LIMIT 5'
        )),
        spawn(fn() => DB::selectOne('SELECT id, name, email FROM users WHERE id = ?', [$otherUserId])),
        spawn(fn() => DB::select(
            'SELECT id, title FROM posts WHERE user_id = ? LIMIT 5',
            [$otherUserId]
        )),
        spawn(fn() => DB::select(
            'SELECT post_id, ip_address, viewed_at FROM post_views ORDER BY viewed_at DESC LIMIT 10'
        )),
    ], timeout(1000));

    $postId = !empty($posts) ? $posts[0]->id : 1;

    await_all_or_fail([
        spawn(fn() => DB::insert(
            'INSERT INTO post_views (post_id, ip_address, viewed_at) VALUES (?, ?, NOW())',
            [$postId, '10.0.' . rand(0, 255) . '.' . rand(0, 255)]
        )),
        spawn(fn() => DB::update('UPDATE posts SET views_count = views_count + 1 WHERE id = ?', [$postId])),
    ], timeout(1000));

    $stats = DB::selectOne(
        'SELECT COUNT(*) as total_views, MAX(viewed_at) as last_view FROM post_views WHERE post_id = ?',
        [$postId]
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
});

Route::get('/opcache-status', function () {
    $status = opcache_get_status(false);
    return response()->json([
        'sapi' => php_sapi_name(),
        'opcache_enabled' => $status['opcache_enabled'] ?? false,
        'cached_scripts' => $status['opcache_statistics']['num_cached_scripts'] ?? 0,
        'memory_used_mb' => round(($status['memory_usage']['used_memory'] ?? 0) / 1024 / 1024, 1),
    ]);
});
