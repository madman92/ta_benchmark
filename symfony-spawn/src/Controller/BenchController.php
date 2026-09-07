<?php

namespace App\Controller;

use Doctrine\DBAL\Connection;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\Routing\Attribute\Route;
use function Async\await_all_or_fail;
use function Async\spawn;

class BenchController
{
    public function __construct(private readonly Connection $connection)
    {
    }

    #[Route('/hello')]
    public function hello(): JsonResponse
    {
        return new JsonResponse([
            'message' => 'Hello from TrueAsync Symfony!',
            'time' => (new \DateTimeImmutable())->format(\DateTimeInterface::ATOM),
        ]);
    }

    #[Route('/yan')]
    public function yan(): JsonResponse
    {
        return new JsonResponse([
            'true_async_server' => extension_loaded('true_async_server'),
            'class_exists' => !class_exists('TrueAsync\HttpServer'),
            'time' => (new \DateTimeImmutable())->format(\DateTimeInterface::ATOM),
        ]);
    }

    #[Route('/test')]
    public function test(): JsonResponse
    {
        $this->connection->executeQuery('SELECT pg_sleep(0.01)');

        return new JsonResponse([
            'ok' => true,
            'time' => (new \DateTimeImmutable())->format(\DateTimeInterface::ATOM),
        ]);
    }

    #[Route('/bench')]
    public function bench(): JsonResponse
    {
        $userId = rand(1, 100);
        $otherUserId = ($userId % 100) + 1;

        [$user, $posts, $topPosts, $leaderboard, $otherUser, $otherPosts, $recentViews] = await_all_or_fail([
            spawn(fn() => $this->connection->fetchAssociative(
                'SELECT id, name, email FROM users WHERE id = ?',
                [$userId]
            )),
            spawn(fn() => $this->connection->fetchAllAssociative(
                'SELECT id, title, views_count FROM posts WHERE user_id = ? ORDER BY created_at DESC LIMIT 10',
                [$userId]
            )),
            spawn(fn() => $this->connection->fetchAllAssociative(
                'SELECT id, title, views_count FROM posts ORDER BY views_count DESC LIMIT 5'
            )),
            spawn(fn() => $this->connection->fetchAllAssociative(
                'SELECT user_id, COUNT(*) as post_count FROM posts GROUP BY user_id ORDER BY post_count DESC LIMIT 5'
            )),
            spawn(fn() => $this->connection->fetchAssociative(
                'SELECT id, name, email FROM users WHERE id = ?',
                [$otherUserId]
            )),
            spawn(fn() => $this->connection->fetchAllAssociative(
                'SELECT id, title FROM posts WHERE user_id = ? LIMIT 5',
                [$otherUserId]
            )),
            spawn(fn() => $this->connection->fetchAllAssociative(
                'SELECT post_id, ip_address, viewed_at FROM post_views ORDER BY viewed_at DESC LIMIT 10'
            )),
        ]);

        $postId = !empty($posts) ? $posts[0]['id'] : 1;

        await_all_or_fail([
            spawn(fn() => $this->connection->executeStatement(
                'INSERT INTO post_views (post_id, ip_address, viewed_at) VALUES (?, ?, NOW())',
                [$postId, '10.0.' . rand(0, 255) . '.' . rand(0, 255)]
            )),
            spawn(fn() => $this->connection->executeStatement(
                'UPDATE posts SET views_count = views_count + 1 WHERE id = ?',
                [$postId]
            )),
        ]);

        $stats = $this->connection->fetchAssociative(
            'SELECT COUNT(*) as total_views, MAX(viewed_at) as last_view FROM post_views WHERE post_id = ?',
            [$postId]
        );

        return new JsonResponse([
            'user' => $user['name'],
            'posts' => count($posts),
            'views' => $stats['total_views'],
            'top_posts' => count($topPosts),
            'leaderboard' => count($leaderboard),
            'other_user' => $otherUser['name'],
            'other_posts' => count($otherPosts),
            'recent_views' => count($recentViews),
            'queries' => 10,
            'memory_usage' => memory_get_usage(),
            'memory_peak' => memory_get_peak_usage(),
            'time' => (new \DateTimeImmutable())->format(\DateTimeInterface::ATOM),
        ]);
    }

    #[Route('/debug/connections')]
    public function connections(): JsonResponse
    {
        $byState = $this->connection->fetchAllAssociative("
            SELECT state, count(*) as cnt
            FROM pg_stat_activity
            WHERE datname = current_database()
            GROUP BY state
            ORDER BY cnt DESC
        ");

        $limit = $this->connection->fetchAssociative(
            "SELECT current_setting('max_connections')::int AS max_connections"
        );

        $total = array_sum(array_column($byState, 'cnt'));

        return new JsonResponse([
            'max_connections' => $limit['max_connections'],
            'total' => $total,
            'by_state' => $byState,
            'time' => (new \DateTimeImmutable())->format(\DateTimeInterface::ATOM),
        ]);
    }
}
