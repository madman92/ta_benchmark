<?php

namespace App\Exception;

use Hyperf\ExceptionHandler\ExceptionHandler;
use Hyperf\HttpMessage\Stream\SwooleStream;
use Hyperf\Logger\LoggerFactory;
use Psr\Http\Message\ResponseInterface;
use Throwable;

class Handler extends ExceptionHandler
{
    public function __construct(private readonly LoggerFactory $loggerFactory)
    {
    }

    public function handle(Throwable $throwable, ResponseInterface $response): ResponseInterface
    {
        $this->loggerFactory->get('default')->error('Benchmark request failed', ['exception' => $throwable]);
        $this->stopPropagation();
        return $response->withStatus(500)->withHeader('Content-Type', 'application/json')
            ->withBody(new SwooleStream('{"ok":false,"error":"Database or request failure"}'));
    }

    public function isValid(Throwable $throwable): bool
    {
        return true;
    }
}
