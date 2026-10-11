<?php

namespace App\Service;

use OpenSwoole\Core\Coroutine\Client\PostgresClientFactory;
use OpenSwoole\Core\Coroutine\Client\PostgresConfig;
use OpenSwoole\Core\Coroutine\Pool\ClientPool;
use OpenSwoole\Coroutine\PostgreSQL;
use OpenSwoole\Coroutine\PostgreSQLStatement;

class DoctrinePool
{
    private ClientPool $pool;

    public function __construct(int $maxSize = 10)
    {
        $config = (new PostgresConfig())
            ->withHost(getenv('DB_HOST'))
            ->withPort((int) getenv('DB_PORT'))
            ->withDbname(getenv('DB_NAME'))
            ->withUsername(getenv('DB_USER'))
            ->withPassword(getenv('DB_PASSWORD'));

        $this->pool = new ClientPool(PostgresClientFactory::class, $config, $maxSize);
    }

    public function fetchAssociative(string $sql, array $params = []): array|false
    {
        return $this->withConn(fn (PostgreSQL $pg) => $this->fetchAssoc($pg, $sql, $params));
    }

    public function fetchAllAssociative(string $sql, array $params = []): array
    {
        return $this->withConn(fn (PostgreSQL $pg) => $this->fetchAll($pg, $sql, $params));
    }

    public function executeStatement(string $sql, array $params = []): int
    {
        return $this->withConn(fn (PostgreSQL $pg) => $this->affectedRows($pg, $sql, $params));
    }

    private function withConn(callable $task): mixed
    {
        $pg = $this->pool->get();

        try {
            return $task($pg);
        } finally {
            $this->pool->put($pg);
        }
    }

    private function fetchAssoc(PostgreSQL $pg, string $sql, array $params): array|false
    {
        $stmt = $this->execute($pg, $sql, $params);
        return $stmt->fetchAssoc();
    }

    private function fetchAll(PostgreSQL $pg, string $sql, array $params): array
    {
        $stmt = $this->execute($pg, $sql, $params);
        $rows = $stmt->fetchAll();
        if ($rows === false) {
            throw new \RuntimeException('PostgreSQL result fetch failed');
        }

        return $rows;
    }

    private function affectedRows(PostgreSQL $pg, string $sql, array $params): int
    {
        $stmt = $this->execute($pg, $sql, $params);
        return $stmt->affectedRows();
    }

    private function execute(PostgreSQL $pg, string $sql, array $params): PostgreSQLStatement
    {
        if (empty($params)) {
            $stmt = $pg->query($sql);
            if ($stmt === false) {
                throw new \RuntimeException('PostgreSQL query failed');
            }

            return $stmt;
        }

        $stmt = $pg->prepare(self::withNamedParams($sql));

        if ($stmt === false) {
            throw new \RuntimeException('PostgreSQL statement preparation failed');
        }
        if (!$stmt->execute($params)) {
            throw new \RuntimeException('PostgreSQL statement execution failed');
        }

        return $stmt;
    }

    private static function withNamedParams(string $sql): string
    {
        $i = 0;

        return preg_replace_callback('/\?/', function () use (&$i) {
            return '$' . ++$i;
        }, $sql);
    }

    public function fill(): void
    {
        $this->pool->fill();
    }

    public function close(): void
    {
        $this->pool->close();
    }
}
