<?php

namespace App\Service;

use Hyperf\DbConnection\Db;

/** Hyperf acquires a pooled connection per coroutine and releases it on exit. */
class Database
{
    public function fetchAssociative(string $sql, array $params = []): array|false
    {
        $row = Db::selectOne($sql, $params);
        return $row === null ? false : (array) $row;
    }

    public function fetchAllAssociative(string $sql, array $params = []): array
    {
        return array_map(static fn($row) => (array) $row, Db::select($sql, $params));
    }

    public function executeStatement(string $sql, array $params = []): int
    {
        return Db::affectingStatement($sql, $params);
    }
}
