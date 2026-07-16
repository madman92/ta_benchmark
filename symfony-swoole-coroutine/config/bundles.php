<?php

if (!defined('SWOOLE_HOOK_PDO_PGSQL')) {
    define('SWOOLE_HOOK_PDO_PGSQL', 65536);
}

if (!defined('SWOOLE_HOOK_ALL')) {
    define('SWOOLE_HOOK_ALL', 2147457023);
}

return [
    Symfony\Bundle\FrameworkBundle\FrameworkBundle::class => ['all' => true],
    Doctrine\Bundle\DoctrineBundle\DoctrineBundle::class => ['all' => true],
    Doctrine\Bundle\MigrationsBundle\DoctrineMigrationsBundle::class => ['all' => true],
    OpenSwooleBundle\OpenSwooleBundle::class => ['all' => true],
    Symfony\Bundle\MonologBundle\MonologBundle::class => ['all' => true],
];
