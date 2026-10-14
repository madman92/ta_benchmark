<?php
return ['default' => [
    'handler' => ['class' => Monolog\Handler\StreamHandler::class,
        'constructor' => ['stream' => BASE_PATH . '/runtime/logs/app.log', 'level' => Monolog\Level::Warning]],
]];
