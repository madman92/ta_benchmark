<?php

return ['default' => [
    'driver' => 'pgsql', 'host' => getenv('DB_HOST'), 'port' => (int) getenv('DB_PORT'),
    'database' => getenv('DB_NAME'), 'username' => getenv('DB_USER'), 'password' => getenv('DB_PASSWORD'),
    'charset' => 'utf8',
    'pool' => ['min_connections' => 0, 'max_connections' => 10,
        'connect_timeout' => 10.0, 'wait_timeout' => 10.0, 'heartbeat' => -1, 'max_idle_time' => 60.0],
    'options' => [Pdo\Pgsql::ATTR_DISABLE_PREPARES => true],
]];
