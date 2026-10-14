#!/usr/bin/env php
<?php

define('BASE_PATH', dirname(__DIR__));

if (!extension_loaded('swoole') || !defined('SWOOLE_HOOK_PDO_PGSQL') ||
    !in_array('pgsql', PDO::getAvailableDrivers(), true)) {
    throw new RuntimeException('Hyperf benchmark requires Swoole PDO PostgreSQL coroutine hooks.');
}
ob_start();
phpinfo(INFO_MODULES);
$info = ob_get_clean();
if (!str_contains($info, 'coroutine_pgsql => enabled')) {
    throw new RuntimeException('Swoole must be compiled with --enable-swoole-pgsql.');
}
define('SWOOLE_HOOK_FLAGS', SWOOLE_HOOK_ALL);
// Server hook_flags enables hooks in workers, after the CLI has bootstrapped.
if (!(SWOOLE_HOOK_FLAGS & SWOOLE_HOOK_PDO_PGSQL)) {
    throw new RuntimeException('PostgreSQL coroutine hooks are disabled.');
}
ini_set('memory_limit', '1G');
require BASE_PATH . '/vendor/autoload.php';
Hyperf\Di\ClassLoader::init();
$container = require BASE_PATH . '/config/container.php';
$container->get(Hyperf\Contract\ApplicationInterface::class)->run();
