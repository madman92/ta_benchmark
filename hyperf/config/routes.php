<?php

use App\Controller\BenchController;
use Hyperf\HttpServer\Router\Router;

foreach (['hello', 'test', 'bench', 'bench-async', 'debug/runtime', 'debug/connections'] as $path) {
    $method = ['bench-async' => 'benchAsync', 'debug/runtime' => 'runtime', 'debug/connections' => 'connections'][$path] ?? $path;
    Router::get('/' . $path, [BenchController::class, $method]);
}
