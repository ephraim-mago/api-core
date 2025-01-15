<?php

namespace Framework\Http\Middleware;

use Closure;

class HandleCors
{
    /**
     * Handle the incoming request.
     *
     * @param  \Psr\Http\Message\ServerRequestInterface  $request
     * @param  \Closure  $next
     * @return \Psr\Http\Message\ResponseInterface
     */
    public function handle($request, Closure $next)
    {
        $response = $next($request);

        if (strpos($request->getUri()->getPath(), '/api') !== false) {
            // This variable should be set to the allowed host from which your API can be accessed with
            $origin = $_SERVER['HTTP_ORIGIN'] ?? '*';

            $response = $response
                ->withHeader('Access-Control-Allow-Credentials', 'true')
                ->withHeader('Access-Control-Allow-Origin', $origin)
                ->withHeader(
                    'Access-Control-Allow-Headers',
                    'X-Requested-With, Content-Type, Accept, Origin, Authorization',
                )
                ->withHeader('Access-Control-Allow-Methods', 'GET, POST, PUT, PATCH, DELETE, OPTIONS')
                ->withHeader('Cache-Control', 'no-store, no-cache, must-revalidate, max-age=0')
                ->withAddedHeader('Cache-Control', 'post-check=0, pre-check=0')
                ->withHeader('Pragma', 'no-cache');
        }


        if (ob_get_contents()) {
            ob_clean();
        }

        return $response;
    }
}
