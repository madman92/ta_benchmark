<?php

use Hyperf\Server\Event;
use Hyperf\Server\Server;

return [
    'mode' => SWOOLE_BASE,
    'servers' => [[
        'name' => 'http', 'type' => Server::SERVER_HTTP,
        'host' => '0.0.0.0', 'port' => 8080, 'sock_type' => SWOOLE_SOCK_TCP,
        'callbacks' => [Event::ON_REQUEST => [Hyperf\HttpServer\Server::class, 'onRequest']],
    ]],
    'settings' => [
        'enable_coroutine' => true, 'worker_num' => 16, 'max_request' => 0,
        'hook_flags' => SWOOLE_HOOK_FLAGS, 'max_coroutine' => 100000,
        'open_tcp_nodelay' => true, 'pid_file' => BASE_PATH . '/runtime/hyperf.pid',
        'log_file' => BASE_PATH . '/runtime/logs/swoole.log',
    ],
    'callbacks' => [
        Event::ON_WORKER_START => [Hyperf\Framework\Bootstrap\WorkerStartCallback::class, 'onWorkerStart'],
        Event::ON_PIPE_MESSAGE => [Hyperf\Framework\Bootstrap\PipeMessageCallback::class, 'onPipeMessage'],
        Event::ON_WORKER_EXIT => [Hyperf\Framework\Bootstrap\WorkerExitCallback::class, 'onWorkerExit'],
    ],
];
