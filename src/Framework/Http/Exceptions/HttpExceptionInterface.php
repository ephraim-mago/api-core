<?php

namespace Framework\Http\Exceptions;

interface HttpExceptionInterface
{
    /**
     * Returns the status code.
     */
    public function getStatusCode(): int;

    /**
     * Returns response headers.
     */
    public function getHeaders(): array;
}
