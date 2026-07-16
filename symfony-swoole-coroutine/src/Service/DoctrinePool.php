<?php

namespace App\Service;

use OpenSwoole\Core\Coroutine\Client\PostgresClientFactory;
use OpenSwoole\Core\Coroutine\Client\PostgresConfig;
use OpenSwoole\Core\Coroutine\Pool\ClientPool;

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

    public function get(): NativeConnectionWrapper
    {
        $pg = $this->pool->get();
        return new NativeConnectionWrapper($pg);
    }

    public function put(?NativeConnectionWrapper $connection): void
    {
        $this->pool->put($connection?->unwrap());
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
