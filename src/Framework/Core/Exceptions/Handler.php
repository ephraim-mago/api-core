<?php

namespace Framework\Core\Exceptions;

use Throwable;
use GuzzleHttp\Psr7\Response;
use Framework\Support\Arr;
use Framework\Http\Exceptions\HttpException;
use Framework\Contracts\Debug\ExceptionHandler;
use Framework\Http\Exceptions\HttpExceptionInterface;

class Handler implements ExceptionHandler
{
    /**
     * Report or log an exception.
     *
     * @param  \Throwable  $e
     * @return void
     *
     * @throws \Throwable
     */
    public function report(Throwable $e)
    {
        $context = [
            'exception' => get_class($e),
            'file' => $e->getFile(),
            'line' => $e->getLine(),
            'trace' => array_map(
                fn($trace) => Arr::except($trace, ['args']),
                $e->getTrace()
            )
        ];

        $error = sprintf(
            '[%s] ERROR: %s context: %s',
            date('Y-m-d H:i:s'),
            $e->getMessage() . "\n",
            json_encode($context, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES)
        );

        file_put_contents(storage_path('logs/core.log'), "$error\n\n", FILE_APPEND);
    }

    /**
     * Render an exception into an HTTP response.
     *
     * @param \Psr\Http\Message\ServerRequestInterface  $request
     * @param  \Throwable  $e
     * @return \Psr\Http\Message\ResponseInterface
     *
     * @throws \Throwable
     */
    public function render($request, Throwable $e)
    {
        return $this->shouldReturnJson($request, $e)
            ? $this->prepareJsonResponse($request, $e)
            : $this->prepareResponse($request, $e);

        // $response = new Response();

        // $response->getBody()->write($this->renderExceptionWithWhoops($e));

        // return $response;
    }

    /**
     * Determine if the exception handler response should be JSON.
     *
     * @param \Psr\Http\Message\ServerRequestInterface  $request
     * @param  \Throwable  $e
     * @return bool
     */
    protected function shouldReturnJson($request, Throwable $e)
    {
        $acceptables = $request->getHeaderLine('Accept');

        if (strpos($acceptables, 'application/json') !== false || strpos($acceptables, 'application+json') !== false) {
            return true;
        }

        return false;
    }

    /**
     * Prepare a response for the given exception.
     *
     * @param  \Psr\Http\Message\ServerRequestInterface  $request
     * @param  \Throwable  $e
     * @return \Psr\Http\Message\ResponseInterface
     */
    protected function prepareResponse($request, Throwable $e)
    {
        if (!$this->isHttpException($e) && config('app.debug')) {
            return $this->convertExceptionToResponse($e);
        }

        if (!$this->isHttpException($e)) {
            $e = new HttpException(500, $e->getMessage(), $e);
        }

        return $this->convertExceptionToResponse($e);
    }

    /**
     * Create a Symfony response for the given exception.
     *
     * @param  \Throwable  $e
     * @return \Psr\Http\Message\ResponseInterface
     */
    protected function convertExceptionToResponse(Throwable $e)
    {
        $response = new Response(
            $this->isHttpException($e) ? $e->getStatusCode() : 500,
            $this->isHttpException($e) ? $e->getHeaders() : []
        );

        $response->getBody()->write($this->renderExceptionContent($e));

        return $response;
    }

    /**
     * Get the response content for the given exception.
     *
     * @param  \Throwable  $e
     * @return string
     */
    protected function renderExceptionContent(Throwable $e)
    {
        if (config('app.debug')) {
            return $this->renderExceptionWithWhoops($e);
        }

        return "<h1 style='color: red;'>Server Error.</h1>";
    }

    /**
     * Render an exception to a string using Whoops.
     *
     * @param  \Throwable  $e
     * @return string
     */
    protected function renderExceptionWithWhoops(Throwable $e)
    {
        $whoops = new \Whoops\Run;
        $whoops->allowQuit(false);
        $whoops->writeToOutput(false);
        $whoops->pushHandler(new \Whoops\Handler\PrettyPageHandler);
        return $whoops->handleException($e);
    }

    /**
     * Prepare a JSON response for the given exception.
     *
     * @param  \Psr\Http\Message\ServerRequestInterface  $request
     * @param  \Throwable  $e
     * @return \Psr\Http\Message\ResponseInterface
     */
    protected function prepareJsonResponse($request, Throwable $e)
    {
        $response = new Response(
            $this->isHttpException($e) ? $e->getStatusCode() : 500,
            $this->isHttpException($e) ? $e->getHeaders() : []
        );

        $json = json_encode($this->convertExceptionToArray($e), JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);

        $response = $response->withHeader('Content-Type', 'application/json');

        $response->getBody()->write($json);

        return $response;
    }

    /**
     * Convert the given exception to an array.
     *
     * @param  \Throwable  $e
     * @return array
     */
    protected function convertExceptionToArray(Throwable $e)
    {
        return config('app.debug') ? [
            'message' => $e->getMessage(),
            'exception' => get_class($e),
            'file' => $e->getFile(),
            'line' => $e->getLine(),
            'trace' => array_map(
                fn($trace) => Arr::except($trace, ['args']),
                $e->getTrace()
            )
        ] : [
            'message' => $this->isHttpException($e) ? $e->getMessage() : 'Server Error',
        ];
    }

    /**
     * Determine if the given exception is an HTTP exception.
     *
     * @param  \Throwable  $e
     * @return bool
     */
    protected function isHttpException(Throwable $e)
    {
        return $e instanceof HttpExceptionInterface;
    }
}
