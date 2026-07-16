<?php

namespace App\Controller;

use App\Service\DoctrinePool;
use Doctrine\DBAL\Connection;
use OpenSwoole\Core\Coroutine\WaitGroup;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\Routing\Attribute\Route;

class BenchController
{
    public function __construct(
        private readonly DoctrinePool $doctrinePool,
        private readonly Connection $connection
    ) {
    }

    #[Route('/hello')]
    public function hello(): JsonResponse
    {
        return new JsonResponse([
            'message' => 'Hello from Swoole Symfony!',
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
        $pool = $this->doctrinePool;

        $userId = rand(1, 100);
        $otherUserId = ($userId % 100) + 1;

        $wg = new WaitGroup();
        $wg->add(3);

        $user = null;
        $posts = null;
        $postId = null;
        $stats = null;
        $topPosts = null;
        $leaderboard = null;
        $otherUser = null;
        $otherPosts = null;
        $recentViews = null;

        go(function () use ($pool, $wg, $userId, &$user, &$posts, &$postId, &$stats) {
            $conn = $pool->get();
            try {
                $user = $conn->fetchAssociative(
                    'SELECT id, name, email FROM users WHERE id = ?',
                    [$userId]
                );

                $posts = $conn->fetchAllAssociative(
                    'SELECT id, title, views_count FROM posts WHERE user_id = ? ORDER BY created_at DESC LIMIT 10',
                    [$userId]
                );

                $postId = !empty($posts) ? $posts[0]['id'] : 1;

                $conn->executeStatement(
                    'INSERT INTO post_views (post_id, ip_address, viewed_at) VALUES (?, ?, NOW())',
                    [$postId, '10.0.' . rand(0, 255) . '.' . rand(0, 255)]
                );

                $conn->executeStatement(
                    'UPDATE posts SET views_count = views_count + 1 WHERE id = ?',
                    [$postId]
                );

                $stats = $conn->fetchAssociative(
                    'SELECT COUNT(*) as total_views, MAX(viewed_at) as last_view FROM post_views WHERE post_id = ?',
                    [$postId]
                );
            } finally {
                $pool->put($conn);
            }
            $wg->done();
        });

        go(function () use ($pool, $wg, &$topPosts, &$leaderboard, &$recentViews) {
            $conn = $pool->get();
            try {
                $topPosts = $conn->fetchAllAssociative(
                    'SELECT id, title, views_count FROM posts ORDER BY views_count DESC LIMIT 5'
                );

                $leaderboard = $conn->fetchAllAssociative(
                    'SELECT user_id, COUNT(*) as post_count FROM posts GROUP BY user_id ORDER BY post_count DESC LIMIT 5'
                );

                $recentViews = $conn->fetchAllAssociative(
                    'SELECT post_id, ip_address, viewed_at FROM post_views ORDER BY viewed_at DESC LIMIT 10'
                );
            } finally {
                $pool->put($conn);
            }
            $wg->done();
        });

        go(function () use ($pool, $wg, $otherUserId, &$otherUser, &$otherPosts) {
            $conn = $pool->get();
            try {
                $otherUser = $conn->fetchAssociative(
                    'SELECT id, name, email FROM users WHERE id = ?',
                    [$otherUserId]
                );

                $otherPosts = $conn->fetchAllAssociative(
                    'SELECT id, title FROM posts WHERE user_id = ? LIMIT 5',
                    [$otherUserId]
                );
            } finally {
                $pool->put($conn);
            }
            $wg->done();
        });

        $wg->wait();

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
