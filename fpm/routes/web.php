<?php

use Illuminate\Support\Facades\Route;

Route::get('/', function () {
    return view('welcome');
});

Route::get('/hello', fn() => response()->json([
    'message' => 'Hello from FPM!',
    'time'    => now()->toIso8601String(),
]));

Route::get('/test', function () {
    DB::select('SELECT pg_sleep(0.01);');
    return response()->json(['ok' => true, 'time' => now()->toIso8601String()]);
});

Route::get('/bench', function () {
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
});
