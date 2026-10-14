<?php
require '/app/vendor/autoload.php';
use App\Service\DbPool;
use App\Controller\BenchController;

$scheduler = new OpenSwoole\Coroutine\Scheduler();
$scheduler->add(function () {
    // Exercise the controller's actual batch wrapper without constructing its DBAL dependency.
    $controller = (new ReflectionClass(BenchController::class))->newInstanceWithoutConstructor();
    $method = new ReflectionMethod(BenchController::class, 'runQueries');
    $runQueries = fn(array $queries) => $method->invoke($controller, $queries, true);

    $pool = new DbPool(1);
    foreach ([10, 20, 30] as $value) {
        $row = $pool->fetchAssociative('SELECT ?::integer AS value', [$value]);
        if ((int) $row['value'] !== $value) {
            throw new RuntimeException('Reused statement returned stale parameters');
        }
    }
    $row = $pool->fetchAssociative('SELECT count(*) AS count FROM pg_prepared_statements');
    if ((int) $row['count'] !== 1) {
        throw new RuntimeException('Same SQL was prepared more than once on one client');
    }
    try {
        $pool->fetchAssociative('SELECT ?::integer AS value', ['invalid integer']);
        throw new RuntimeException('Expected execution failure');
    } catch (RuntimeException $error) {
        if ($error->getMessage() !== 'PostgreSQL statement execution failed') {
            throw $error;
        }
    }
    if ((int) $pool->fetchAssociative('SELECT ?::integer AS value', [40])['value'] !== 40) {
        throw new RuntimeException('Pool did not recover after execution failure');
    }
    unset($pool);

    $pool = new DbPool(2);
    for ($repeat = 0; $repeat < 3; $repeat++) {
        $sql = "SELECT ?::integer AS value, pg_sleep(0.05), (SELECT count(*) FROM pg_stat_activity WHERE state='active' AND query LIKE '%pg_sleep(0.05)%') AS active";
        $values = [$repeat * 2 + 1, $repeat * 2 + 2];
        $rows = $runQueries(array_map(fn($value) => fn() => $pool->fetchAssociative($sql, [$value]), $values));
        if (array_map(fn($row) => (int) $row['value'], $rows) !== $values || max(array_column($rows, 'active')) < 2) {
            throw new RuntimeException('Concurrent clients mixed parameters or did not overlap');
        }
    }
    unset($pool);
    // Idle clients are reused before opening more; concurrent demand cannot exceed the cap.
    $pool = new DbPool(2);
    $first = (int) $pool->fetchAssociative('SELECT pg_backend_pid() AS pid')['pid'];
    for ($i = 0; $i < 4; $i++) {
        if ((int) $pool->fetchAssociative('SELECT pg_backend_pid() AS pid')['pid'] !== $first) {
            throw new RuntimeException('Pool eagerly opened an unnecessary idle connection');
        }
    }
    $rows = $runQueries(array_fill(0, 8,
        fn() => $pool->fetchAssociative('SELECT pg_backend_pid() AS pid, pg_sleep(0.02)')));
    if (count(array_unique(array_column($rows, 'pid'))) !== 2) {
        throw new RuntimeException('Pool did not enforce its physical connection cap');
    }

    // Failure stays in the child until every sibling has released its connection.
    $pool = new DbPool(2);
    $finished = false;
    try {
        $runQueries([
            fn() => $pool->fetchAssociative('SELECT ?::integer', ['invalid']),
            function () use ($pool, &$finished) {
                $pool->fetchAssociative('SELECT pg_sleep(0.05)');
                $finished = true;
            },
        ]);
        throw new RuntimeException('Expected batch failure');
    } catch (RuntimeException $error) {
        if ($error->getMessage() !== 'PostgreSQL statement execution failed' || !$finished) {
            throw new RuntimeException('Batch failed to propagate error after joining siblings', 0, $error);
        }
    }
    if ($runQueries(['first' => fn() => 1, 'second' => fn() => 2]) !== ['first' => 1, 'second' => 2] ||
        $runQueries([]) !== []) {
        throw new RuntimeException('Batch result order or empty batch changed');
    }

    echo "DbPool regression passed: statement reuse, parallelism, connection cap and safe batch errors.\n";
});
$scheduler->start();
