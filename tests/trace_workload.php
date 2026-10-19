<?php

// Execute the actual controller with framework/DB doubles, without vendor packages.
namespace Psr\Http\Message { interface ResponseInterface {} }
namespace App\Http\Controllers { class Controller {} }
namespace Illuminate\Support\Facades {
    class DB {
        public static function __callStatic($name, $args) {
            return $GLOBALS['database']->$name(...$args);
        }
    }
}
namespace Async {
    function spawn($query) {
        $fiber = new \Fiber($query);
        $fiber->start();
        return $fiber;
    }
    function await_all_or_fail($fibers) { return \joinTasks($fibers); }
}
namespace OpenSwoole\Core\Coroutine {
    function batch($queries) { return \parallelTasks($queries); }
}
namespace Hyperf\Coroutine {
    class Parallel {
        private array $queries = [];
        public function __construct($limit) {}
        public function add($query, $key) { $this->queries[$key] = $query; }
        public function wait() { return \parallelTasks($this->queries); }
    }
}
namespace {
    class TracedResponse implements \Psr\Http\Message\ResponseInterface {
        public function __construct(public array $body) {}
    }
    class ResponseFactory {
        public function json($body) { return new TracedResponse($body); }
    }
    function response() { return new ResponseFactory(); }
    function now() {
        return new class {
            public function toIso8601String() { return gmdate('c'); }
        };
    }
    function app($name) {
        if ($name !== 'benchmark.connection') { throw new \RuntimeException($name); }
        return $GLOBALS['database'];
    }
    function joinTasks($fibers) {
        $error = null;
        // Complete in reverse order to exercise result-to-query association.
        foreach (array_reverse($fibers, true) as $fiber) {
            try {
                while ($fiber->isSuspended()) { $fiber->resume(); }
            } catch (\Throwable $caught) { $error ??= $caught; }
        }
        if ($error !== null) { throw $error; }
        return array_map(fn($fiber) => $fiber->getReturn(), $fibers);
    }
    function parallelTasks($queries) {
        return joinTasks(array_map(fn($query) => \Async\spawn($query), $queries));
    }
    function normalizeSql($sql) {
        return strtolower(preg_replace('/\s+/', ' ', trim($sql, " \t\n\r;")));
    }
    class TracedDatabase {
        public array $trace = [];
        private int $sequence = 0;
        private array $queries;
        public function __construct() {
            $this->queries = array_map('normalizeSql', json_decode(file_get_contents(__DIR__ . '/workload.json'), true));
        }
        private function rows($sql, $params = []) {
            $sql = normalizeSql($sql);
            $id = $this->sequence++;
            $this->trace[] = ['event' => 'start', 'id' => $id, 'sql' => $sql, 'params' => $params];
            if (\Fiber::getCurrent() !== null) { \Fiber::suspend(); }
            $this->trace[] = ['event' => 'end', 'id' => $id];
            if ($sql === 'select pg_sleep(0.01)') { return [[]]; }
            $index = array_search($sql, $this->queries, true);
            return match ($index) {
                0 => [['id' => $params[0], 'name' => 'User ' . $params[0], 'email' => 'fixture@bench.test']],
                1 => array_fill(0, 10, ['id' => 900 + $params[0], 'title' => 'Post', 'views_count' => 0]),
                2, 3, 5 => array_fill(0, 5, ['id' => 1]),
                6 => array_fill(0, 10, ['post_id' => 1]),
                7, 8 => [],
                9 => [['total_views' => 1, 'last_view' => '2000-01-02']],
                default => throw new \RuntimeException('Unexpected SQL: ' . $sql),
            };
        }
        public function fetchAssociative($sql, $params = []) { return $this->rows($sql, $params)[0] ?? false; }
        public function fetchAllAssociative($sql, $params = []) { return $this->rows($sql, $params); }
        public function executeQuery($sql, $params = []) { return $this->rows($sql, $params); }
        public function executeStatement($sql, $params = []) { $this->rows($sql, $params); return 1; }
        public function select($sql, $params = []) { return array_map(fn($row) => (object) $row, $this->rows($sql, $params)); }
        public function selectOne($sql, $params = []) { return $this->select($sql, $params)[0] ?? null; }
        public function update($sql, $params = []) { return $this->executeStatement($sql, $params); }
    }
    foreach (['Doctrine\\DBAL\\Connection', 'Illuminate\\Database\\Connection',
              'App\\Service\\DbPool', 'App\\Service\\Database'] as $name) {
        class_alias(TracedDatabase::class, $name);
    }
    class_alias(TracedResponse::class, 'Symfony\\Component\\HttpFoundation\\JsonResponse');
    class_alias(ResponseFactory::class, 'Hyperf\\HttpServer\\Contract\\ResponseInterface');

    [$script, $source, $mode] = $argv;
    require $source;
    $database = new TracedDatabase();
    $class = str_contains($source, '/app/Http/')
        ? 'App\\Http\\Controllers\\BenchmarkController' : 'App\\Controller\\BenchController';
    $reflection = new \ReflectionClass($class);
    $args = [];
    foreach ($reflection->getConstructor()?->getParameters() ?? [] as $parameter) {
        $args[] = $parameter->getType()->getName() === 'Hyperf\\HttpServer\\Contract\\ResponseInterface'
            ? new ResponseFactory() : $database;
    }
    $controller = $reflection->newInstanceArgs($args);
    $method = match ($mode) { 'async' => 'benchAsync', 'sequential' => 'bench', 'test' => 'test' };
    $body = $controller->$method()->body;
    echo json_encode(['body' => $body, 'trace' => $database->trace], JSON_THROW_ON_ERROR);
}
