<?php

namespace App\Service;

use OpenSwoole\Coroutine\PostgreSQL;
use OpenSwoole\Coroutine\PostgreSQLStatement;

class NativeConnectionWrapper
{
    public function __construct(
        private readonly PostgreSQL $pg,
    ) {
    }

    public function fetchAssociative(string $sql, array $params = []): array|false
    {
        $stmt = $this->execute($sql, $params);
        if ($stmt === false) {
            return false;
        }
        return $stmt->fetchAssoc();
    }

    public function fetchAllAssociative(string $sql, array $params = []): array
    {
        $stmt = $this->execute($sql, $params);
        if ($stmt === false) {
            return [];
        }
        return $stmt->fetchAll();
    }

    public function executeStatement(string $sql, array $params = []): int
    {
        $stmt = $this->execute($sql, $params);
        if ($stmt === false) {
            return 0;
        }
        return $stmt->affectedRows();
    }

    public function unwrap(): PostgreSQL
    {
        return $this->pg;
    }

    private function execute(string $sql, array $params = []): PostgreSQLStatement|false
    {
        if (empty($params)) {
            return $this->pg->query($sql);
        }

        $interpolated = self::interpolate($sql, $params, $this->pg);
        return $this->pg->query($interpolated);
    }

    private static function interpolate(string $sql, array $params, PostgreSQL $pg): string
    {
        $i = 0;
        return preg_replace_callback('/\?/', function () use ($params, $pg, &$i) {
            $value = $params[$i++];
            if ($value === null) {
                return 'NULL';
            }
            if (is_int($value) || is_float($value)) {
                return (string) $value;
            }
            return "'" . $pg->escapeLiteral($value) . "'";
        }, $sql);
    }
}
