<?php

namespace App\Service;

use OpenSwoole\Core\Coroutine\Client\PostgresConfig;
use OpenSwoole\Coroutine\Channel;
use OpenSwoole\Coroutine\PostgreSQL;
use OpenSwoole\Coroutine\PostgreSQLStatement;

class DbPool
{
    /** Tokens bound both checked-out clients and connection attempts. */
    private Channel $pool;
    private string $connectionString;
    /** @var list<PostgreSQL> */
    private array $idle = [];
    /** @var \WeakMap<PostgreSQL, array<string, PostgreSQLStatement>> */
    private \WeakMap $statements;

    public function __construct(
        int $maxSize = 10,
        private readonly float $waitTimeout = 1.0,
        private readonly int $statementTimeoutMs = 5000,
    ) {
        if ($maxSize < 1 || $waitTimeout <= 0 || $statementTimeoutMs < 1) {
            throw new \InvalidArgumentException('Pool size and timeouts must be positive');
        }
        $this->statements = new \WeakMap();
        $config = (new PostgresConfig())
            ->withHost(getenv('DB_HOST'))
            ->withPort((int) getenv('DB_PORT'))
            ->withDbname(getenv('DB_NAME'))
            ->withUsername(getenv('DB_USER'))
            ->withPassword(getenv('DB_PASSWORD'));

        $this->connectionString = $config->getConnectionString();
        $this->pool = new Channel($maxSize);
        for ($i = 0; $i < $maxSize; $i++) {
            $this->pool->push(true);
        }
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
        $slot = $this->pool->pop($this->waitTimeout);
        if ($slot === false) {
            throw new \RuntimeException('Timed out waiting for a PostgreSQL connection');
        }

        $pg = array_pop($this->idle);
        try {
            if ($pg === null) {
                $pg = new PostgreSQL();
                if (!$pg->connect($this->connectionString, 2.0)) {
                    $pg = null;
                    throw new \RuntimeException('PostgreSQL connection failed');
                }
                if ($pg->query('SET statement_timeout = ' . $this->statementTimeoutMs) === false) {
                    $pg = null;
                    throw new \RuntimeException('PostgreSQL connection setup failed');
                }
            }
            return $task($pg);
        } catch (\Throwable $error) {
            // SQL errors can leave a healthy connection. Transport failures cannot.
            // Never replay a failed write: the server may already have committed it.
            if ($pg !== null && ($pg->status() !== \OPENSWOOLE_PG_CONNECTION_OK ||
                in_array($pg->error, [
                    \OpenSwoole\Util::getErrorMessage(\SWOOLE_ERROR_CO_TIMEDOUT),
                    \OpenSwoole\Util::getErrorMessage(\SWOOLE_ERROR_CO_CANCELED),
                ], true))) {
                unset($this->statements[$pg]);
                $pg = null;
            }
            throw $error;
        } finally {
            // A failed connection attempt also returns its capacity for a later retry.
            if ($pg !== null) {
                $this->idle[] = $pg;
            }
            $this->pool->push(true);
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

        // The pool lends each client exclusively until rows have been fetched.
        // Named statements belong to that physical connection, so cache them
        // per client, never globally by SQL alone.
        $prepared = $this->statements[$pg] ?? [];
        $stmt = $prepared[$sql] ?? null;
        if ($stmt === null) {
            $stmt = $pg->prepare(self::withNamedParams($sql));
            if ($stmt === false) {
                throw new \RuntimeException('PostgreSQL statement preparation failed');
            }
            $prepared[$sql] = $stmt;
            $this->statements[$pg] = $prepared;
        }
        if (!$stmt->execute($params)) {
            unset($prepared[$sql]);
            $this->statements[$pg] = $prepared;
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
}
