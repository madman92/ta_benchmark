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

    public function __construct(int $maxSize = 500)
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
        return $stmt !== false ? $stmt->fetchAssoc() : false;
    }

    private function fetchAll(PostgreSQL $pg, string $sql, array $params): array
    {
        $stmt = $this->execute($pg, $sql, $params);
        return $stmt !== false ? $stmt->fetchAll() : [];
    }

    private function affectedRows(PostgreSQL $pg, string $sql, array $params): int
    {
        $stmt = $this->execute($pg, $sql, $params);
        return $stmt !== false ? $stmt->affectedRows() : 0;
    }

    private function execute(PostgreSQL $pg, string $sql, array $params): PostgreSQLStatement|false
    {
        if (empty($params)) {
            return $pg->query($sql);
        }

        $stmt = $pg->prepare(self::withNamedParams($sql));

        return $stmt !== false && $stmt->execute($params) ? $stmt : false;
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
