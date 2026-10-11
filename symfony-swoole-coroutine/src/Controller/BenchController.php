<?php

namespace App\Controller;

use App\Service\DoctrinePool;
use Doctrine\DBAL\Connection;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\Routing\Attribute\Route;
use function OpenSwoole\Core\Coroutine\batch;

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
        return $this->runBench(false);
    }

    #[Route('/bench-async')]
    public function benchAsync(): JsonResponse
    {
        return $this->runBench(true);
    }

    private function runBench(bool $parallel): JsonResponse
    {
        $userId = rand(1, 100);
        $otherUserId = ($userId % 100) + 1;

        // All reads precede writes in both modes. Only scheduling differs.
        [$user, $posts, $topPosts, $leaderboard, $otherUser, $otherPosts, $recentViews] = $this->runQueries([
            fn() => $this->doctrinePool->fetchAssociative('SELECT id, name, email FROM users WHERE id = ?', [$userId]),
            fn() => $this->doctrinePool->fetchAllAssociative('SELECT id, title, views_count FROM posts WHERE user_id = ? ORDER BY created_at DESC, id DESC LIMIT 10', [$userId]),
            fn() => $this->doctrinePool->fetchAllAssociative('SELECT id, title, views_count FROM posts ORDER BY views_count DESC, id DESC LIMIT 5'),
            fn() => $this->doctrinePool->fetchAllAssociative('SELECT user_id, COUNT(*) as post_count FROM posts GROUP BY user_id ORDER BY post_count DESC, user_id ASC LIMIT 5'),
            fn() => $this->doctrinePool->fetchAssociative('SELECT id, name, email FROM users WHERE id = ?', [$otherUserId]),
            fn() => $this->doctrinePool->fetchAllAssociative('SELECT id, title FROM posts WHERE user_id = ? ORDER BY id LIMIT 5', [$otherUserId]),
            fn() => $this->doctrinePool->fetchAllAssociative('SELECT post_id, ip_address, viewed_at FROM post_views ORDER BY viewed_at DESC, id DESC LIMIT 10'),
        ], $parallel);

        $postId = $posts[0]['id'];
        $ip = '10.0.' . rand(0, 255) . '.' . rand(0, 255);
        $this->runQueries([
            fn() => $this->doctrinePool->executeStatement('UPDATE post_views SET ip_address = ?, viewed_at = NOW() WHERE id = ?', [$ip, $postId]),
            fn() => $this->doctrinePool->executeStatement('UPDATE posts SET views_count = views_count + 1 WHERE id = ?', [$postId]),
        ], $parallel);

        $stats = $this->doctrinePool->fetchAssociative('SELECT COUNT(*) as total_views, MAX(viewed_at) as last_view FROM post_views WHERE post_id = ?', [$postId]);

        return new JsonResponse([
            'user' => $user['name'],
            'posts' => count($posts),
            'views' => (int) $stats['total_views'],
            'top_posts' => count($topPosts),
            'leaderboard' => count($leaderboard),
            'other_user' => $otherUser['name'],
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
            return batch($queries);
        }
        $results = [];
        foreach ($queries as $query) {
            $results[] = $query();
        }
        return $results;
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
    #[Route('/debug/runtime')]
    public function runtime(): JsonResponse
    {
        return new JsonResponse([
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
