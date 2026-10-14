<?php

namespace App\Controller;

use App\Service\Database;
use Hyperf\Coroutine\Parallel;
use Hyperf\HttpServer\Contract\ResponseInterface;
use Psr\Http\Message\ResponseInterface as PsrResponse;

class BenchController
{
    public function __construct(
        private readonly Database $connection,
        private readonly ResponseInterface $response
    ) {
    }

    public function hello(): PsrResponse
    {
        return $this->response->json([
            'message' => 'Hello from Hyperf!',
            'time' => (new \DateTimeImmutable())->format(\DateTimeInterface::ATOM),
        ]);
    }

    public function test(): PsrResponse
    {
        $this->connection->fetchAssociative('SELECT pg_sleep(0.01)');

        return $this->response->json([
            'ok' => true,
            'time' => (new \DateTimeImmutable())->format(\DateTimeInterface::ATOM),
        ]);
    }

    public function bench(): PsrResponse
    {
        return $this->runBench(false);
    }

    public function benchAsync(): PsrResponse
    {
        return $this->runBench(true);
    }

    private function runBench(bool $parallel): PsrResponse
    {
        $userId = rand(1, 100);
        $otherUserId = ($userId % 100) + 1;

        // Both modes use Hyperf coroutine-local pooled PDO connections.
        $db = $this->connection;
        [$user, $posts, $topPosts, $leaderboard, $otherUser, $otherPosts, $recentViews] = $this->runQueries([
            fn() => $db->fetchAssociative('SELECT id, name, email FROM users WHERE id = ?', [$userId]),
            fn() => $db->fetchAllAssociative('SELECT id, title, views_count FROM posts WHERE user_id = ? ORDER BY created_at DESC, id DESC LIMIT 10', [$userId]),
            fn() => $db->fetchAllAssociative('SELECT id, title, views_count FROM posts ORDER BY views_count DESC, id DESC LIMIT 5'),
            fn() => $db->fetchAllAssociative('SELECT user_id, COUNT(*) as post_count FROM posts GROUP BY user_id ORDER BY post_count DESC, user_id ASC LIMIT 5'),
            fn() => $db->fetchAssociative('SELECT id, name, email FROM users WHERE id = ?', [$otherUserId]),
            fn() => $db->fetchAllAssociative('SELECT id, title FROM posts WHERE user_id = ? ORDER BY id LIMIT 5', [$otherUserId]),
            fn() => $db->fetchAllAssociative('SELECT post_id, ip_address, viewed_at FROM post_views ORDER BY viewed_at DESC, id DESC LIMIT 10'),
        ], $parallel);

        $postId = $posts[0]['id'];
        $ip = '10.0.' . rand(0, 255) . '.' . rand(0, 255);
        $this->runQueries([
            fn() => $db->executeStatement('UPDATE post_views SET ip_address = ?, viewed_at = NOW() WHERE id = ?', [$ip, $postId]),
            fn() => $db->executeStatement('UPDATE posts SET views_count = views_count + 1 WHERE id = ?', [$postId]),
        ], $parallel);

        $stats = $db->fetchAssociative('SELECT COUNT(*) as total_views, MAX(viewed_at) as last_view FROM post_views WHERE post_id = ?', [$postId]);

        return $this->response->json([
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
            $tasks = new Parallel(count($queries));
            foreach ($queries as $key => $query) {
                $tasks->add($query, $key);
            }
            // Parallel joins every child before propagating a child failure.
            return $tasks->wait();
        }
        $results = [];
        foreach ($queries as $query) {
            $results[] = $query();
        }
        return $results;
    }

    public function connections(): PsrResponse
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

        return $this->response->json([
            'max_connections' => $limit['max_connections'],
            'total' => $total,
            'by_state' => $byState,
            'time' => (new \DateTimeImmutable())->format(\DateTimeInterface::ATOM),
        ]);
    }
    public function runtime(): PsrResponse
    {
        return $this->response->json([
            'hyperf_version' => \Composer\InstalledVersions::getPrettyVersion('hyperf/framework'),
            'swoole_version' => SWOOLE_VERSION,
            'pdo_pgsql_hook' => (bool) (\Swoole\Runtime::getHookFlags() & SWOOLE_HOOK_PDO_PGSQL),
            'workers' => 16, 'pool_max_per_worker' => 10,
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
