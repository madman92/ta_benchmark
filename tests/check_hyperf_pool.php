<?php

// Run in an isolated Hyperf container against its benchmark database.
define('BASE_PATH', '/app');
require BASE_PATH . '/vendor/autoload.php';
define('SWOOLE_HOOK_FLAGS', SWOOLE_HOOK_ALL);
Hyperf\Di\ClassLoader::init();
$container = require BASE_PATH . '/config/container.php';
$container->get(Psr\EventDispatcher\EventDispatcherInterface::class)->dispatch(new Hyperf\Framework\Event\BootApplication());

function ensure(bool $condition, string $message): void
{
    if (!$condition) {
        throw new RuntimeException($message);
    }
}

Swoole\Coroutine\run(function () use ($container) {
    Swoole\Runtime::enableCoroutine(SWOOLE_HOOK_FLAGS);
    $db = $container->get(App\Service\Database::class);
    $tasks = new Hyperf\Coroutine\Parallel(7);
    for ($i = 0; $i < 7; ++$i) {
        $tasks->add(fn() => $db->fetchAssociative('SELECT pg_sleep(0.15), pg_backend_pid() AS pid'));
    }
    $rows = $tasks->wait();
    ensure(count(array_unique(array_column($rows, 'pid'))) === 7,
        'PostgreSQL operations do not overlap on distinct coroutine-local connections');

    $pool = $container->get(Hyperf\DbConnection\Pool\PoolFactory::class)->getPool('default');
    ensure($pool->getCurrentConnections() <= 10, 'Connection limit exceeded');
    $burst = new Hyperf\Coroutine\Parallel(20);
    for ($i = 0; $i < 20; ++$i) {
        $burst->add(fn() => $db->fetchAssociative('SELECT pg_sleep(0.03), 1 AS ok'));
    }
    ensure(count($burst->wait()) === 20, 'Checkout wait/release failed');
    ensure($pool->getCurrentConnections() <= 10, 'Burst exceeded connection limit');

    $joined = false;
    $errors = new Hyperf\Coroutine\Parallel(2);
    $errors->add(fn() => $db->fetchAssociative('SELECT 1 / 0'));
    $errors->add(function () use ($db, &$joined) {
        $db->fetchAssociative('SELECT pg_sleep(0.05)');
        $joined = true;
    });
    $failed = false;
    try {
        $errors->wait();
    } catch (Hyperf\Coroutine\Exception\ParallelExecutionException $error) {
        $failed = true;
    }
    ensure($failed && $joined, 'Parallel errors must propagate after every child joins');
    ensure((int) $db->fetchAssociative('SELECT 1 AS ok')['ok'] === 1, 'Pool did not recover after query error');
    echo "PostgreSQL hooks, coroutine isolation, overlap, connection cap, checkout, joining and error recovery passed.\n";
});
