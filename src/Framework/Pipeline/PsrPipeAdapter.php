<?php

namespace Framework\Pipeline;

use Closure;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\RequestHandlerInterface;

class PsrPipeAdapter implements RequestHandlerInterface
{
    /**
     * @var \Closure
     */
    protected $pipe;

    public function __construct(Closure $pipe)
    {
        $this->pipe = $pipe;
    }

    /**
     * @inheritDoc
     */
    public function handle(ServerRequestInterface $request): ResponseInterface
    {
        return ($this->pipe)($request);
    }
}
