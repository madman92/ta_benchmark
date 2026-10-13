<?php
require '/app/vendor/autoload.php';
use App\Service\DbPool;
use function OpenSwoole\Core\Coroutine\batch;

$scheduler = new OpenSwoole\Coroutine\Scheduler();
$scheduler->add(function () {
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
        $rows = batch(array_map(fn($value) => fn() => $pool->fetchAssociative($sql, [$value]), $values));
        if (array_map(fn($row) => (int) $row['value'], $rows) !== $values || max(array_column($rows, 'active')) < 2) {
            throw new RuntimeException('Concurrent clients mixed parameters or did not overlap');
        }
    }
    unset($pool);
    echo "DbPool regression passed: per-client statement reuse, fresh bound parameters, parallel queries and recovery.\n";
});
$scheduler->start();
