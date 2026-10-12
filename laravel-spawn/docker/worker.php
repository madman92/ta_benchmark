<?php

ini_set('display_errors', '0');
ini_set('log_errors', '1');
error_reporting(E_ALL);

require_once dirname(__DIR__) . '/vendor/autoload.php';

$app = require dirname(__DIR__) . '/bootstrap/app.php';
$app->make(\Illuminate\Contracts\Http\Kernel::class)->bootstrap();

$server = new \Spawn\Laravel\Server\FrankenPhpServer($app);
$server->prepareApp();

// One PDO template per worker, shared by every request and child coroutine.
// The native pool assigns physical connections to coroutines automatically.
$config = $app->make('config')->get('database.connections.pgsql');
$pdo = new \PDO(
    sprintf('pgsql:host=%s;port=%s;dbname=%s;sslmode=%s',
        $config['host'], $config['port'], $config['database'], $config['sslmode']),
    $config['username'],
    $config['password'],
    [
        \PDO::ATTR_ERRMODE => \PDO::ERRMODE_EXCEPTION,
        \PDO::ATTR_EMULATE_PREPARES => false,
        \PDO::ATTR_POOL_ENABLED => true,
        // FrankenPHP starts the scheduler in onRequest(); connect lazily.
        \PDO::ATTR_POOL_MIN => 0,
        \PDO::ATTR_POOL_MAX => 10,
    ],
);
$connection = $app->make(\Illuminate\Database\Connectors\ConnectionFactory::class)->make($config);
$connection->setPdo($pdo);
$connection->setReadPdo($pdo);
$app->instance('benchmark.connection', $connection);
$server->start();
