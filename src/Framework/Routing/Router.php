<?php

namespace Framework\Routing;

use Closure;
use stdClass;
use ArrayObject;
use ReflectionClass;
use JsonSerializable;
use Framework\Support\Arr;
use GuzzleHttp\Psr7\Response;
use Framework\Container\Container;
use Framework\Routing\Utils\RouteGroup;
use Psr\Http\Message\ResponseInterface;
use Framework\Contracts\Events\Dispatcher;
use Psr\Http\Message\ServerRequestInterface;
use Framework\Routing\Utils\SortedMiddleware;
use Framework\Routing\Utils\MiddlewareNameResolver;
use Framework\Http\Exceptions\NotFoundHttpException;

class Router
{
    /**
     * The event dispatcher instance.
     *
     * @var \Framework\Contracts\Events\Dispatcher
     */
    protected $events;

    /**
     * The IoC container instance.
     *
     * @var \Framework\Container\Container
     */
    protected $container;

    /**
     * An array of the routes keyed by method.
     *
     * @var array
     */
    protected $routes = [];

    /**
     * The currently dispatched route instance.
     *
     * @var \Framework\Routing\Route|null
     */
    protected $current;

    /**
     * The request currently being dispatched.
     *
     * @var \Psr\Http\Message\ServerRequestInterface
     */
    protected $currentRequest;

    /**
     * All of the short-hand keys for middlewares.
     *
     * @var array
     */
    protected $middleware = [];

    /**
     * All of the middleware groups.
     *
     * @var array
     */
    protected $middlewareGroups = [];

    /**
     * The priority-sorted list of middleware.
     *
     * Forces the listed middleware to always be in the given order.
     *
     * @var array
     */
    public $middlewarePriority = [];

    /**
     * The route group attribute stack.
     *
     * @var array
     */
    protected $groupStack = [];

    /**
     * All of the verbs supported by the router.
     *
     * @var string[]
     */
    public static $verbs = ['GET', 'HEAD', 'POST', 'PUT', 'PATCH', 'DELETE', 'OPTIONS'];

    /**
     * Create a new Router instance.
     *
     * @param  \Framework\Contracts\Events\Dispatcher  $events
     * @param  \Framework\Container\Container|null  $container
     * @return void
     */
    public function __construct(Dispatcher $events, Container $container = null)
    {
        $this->events = $events;
        $this->container = $container ?: new Container;
    }

    /**
     * Register a set of routes with a set of shared attributes.
     *
     * @param  array  $attributes
     * @param  \Closure  $callback
     * @return void
     */
    public function group(array $attributes, Closure $callback)
    {
        $this->updateGroupStack($attributes);

        $callback($this);

        array_pop($this->groupStack);
    }

    /**
     * Update the group stack with the given attributes.
     *
     * @param  array  $attributes
     * @return void
     */
    protected function updateGroupStack(array $attributes)
    {
        if (!empty($this->groupStack)) {
            $attributes = $this->mergeWithLastGroup($attributes);
        }

        $this->groupStack[] = $attributes;
    }

    /**
     * Merge the given array with the last group stack.
     *
     * @param  array  $new
     * @param  bool  $prependExistingPrefix
     * @return array
     */
    public function mergeWithLastGroup($new, $prependExistingPrefix = true)
    {
        return RouteGroup::merge($new, end($this->groupStack), $prependExistingPrefix);
    }

    /**
     * Get the prefix from the last group on the stack.
     *
     * @return string
     */
    public function getLastGroupPrefix()
    {
        if ($this->hasGroupStack()) {
            $last = end($this->groupStack);

            return $last['prefix'] ?? '';
        }

        return '';
    }

    /**
     * Adds a route to the routing table.
     * 
     * @param string|array $method
     * @param string $uri
     * @param array|string|callable|null $action
     * @return void
     */
    protected function addRoute($method, $uri, $action)
    {
        // If the route is routing to a controller we will parse the route action into
        // an acceptable array format before registering it and creating this route
        // instance itself. We need to build the Closure that will call this out.
        if ($this->actionReferencesController($action)) {
            $action = $this->convertToControllerAction($action);
        }

        $route = (new Route($method, $this->prefix($uri), $action))
            ->setRouter($this)
            ->setContainer($this->container);

        // If we have groups that need to be merged, we will merge them now after this
        // route has already been created and is ready to go. After we're done with
        // the merge we will be ready to return the route back out to the caller.
        if ($this->hasGroupStack()) {
            $this->mergeGroupAttributesIntoRoute($route);
        }

        $this->addRouteToCollections($route);
    }

    /**
     * Determine if the action is routing to a controller.
     *
     * @param  mixed  $action
     * @return bool
     */
    protected function actionReferencesController($action)
    {
        if (!$action instanceof Closure) {
            return is_string($action) || (isset($action['uses']) && is_string($action['uses']));
        }

        return false;
    }

    /**
     * Add a controller based route action to the action array.
     *
     * @param  array|string  $action
     * @return array
     */
    protected function convertToControllerAction($action)
    {
        if (is_string($action)) {
            $action = ['uses' => $action];
        }

        // Here we will set this controller name on the action array just so we always
        // have a copy of it for reference if we need it. This can be used while we
        // search for a controller name or do some other type of fetch operation.
        $action['controller'] = $action['uses'];

        return $action;
    }

    /**
     * Prefix the given URI with the last prefix.
     *
     * @param  string  $uri
     * @return string
     */
    protected function prefix($uri)
    {
        return trim(trim($this->getLastGroupPrefix(), '/') . '/' . trim($uri, '/'), '/') ?: '/';
    }

    /**
     * Merge the group stack with the controller action.
     *
     * @param  \Framework\Routing\Route  $route
     * @return void
     */
    protected function mergeGroupAttributesIntoRoute($route)
    {
        $route->setAction($this->mergeWithLastGroup(
            $route->getAction(),
            $prependExistingPrefix = false
        ));
    }

    /**
     * Add the given route to the arrays of routes.
     *
     * @param  \Framework\Routing\Route  $route
     * @return void
     */
    protected function addRouteToCollections($route)
    {
        $uri = $route->uri();

        foreach ($route->methods() as $method) {
            $this->routes[$method][$uri] = $route;
        }
    }

    /**
     * Register a new GET route with the router.
     * 
     * @param string $uri
     * @param array|string|callable|null $action
     * @return void
     */
    public function get($uri, $action)
    {
        $this->addRoute('GET', $uri, $action);
    }

    /**
     * Register a new POST route with the router.
     * 
     * @param string $uri
     * @param array|string|callable|null $action
     * @return void
     */
    public function post($uri, $action)
    {
        $this->addRoute('POST', $uri, $action);
    }

    /**
     * Register a new PUT route with the router.
     * 
     * @param string $uri
     * @param array|string|callable|null $action
     * @return void
     */
    public function put($uri, $action)
    {
        $this->addRoute('PUT', $uri, $action);
    }

    /**
     * Register a new PATCH route with the router.
     * 
     * @param string $uri
     * @param array|string|callable|null $action
     * @return void
     */
    public function patch($uri, $action)
    {
        $this->addRoute('PATCH', $uri, $action);
    }

    /**
     * Register a new DELETE route with the router.
     * 
     * @param string $uri
     * @param array|string|callable|null $action
     * @return void
     */
    public function delete($uri, $action)
    {
        $this->addRoute('DELETE', $uri, $action);
    }

    /**
     * Register a new OPTIONS route with the router.
     *
     * @param  string $uri
     * @param  array|string|callable|null $action
     * @return void
     */
    public function options($uri, $action = null)
    {
        return $this->addRoute('OPTIONS', $uri, $action);
    }

    /**
     * Register a new route responding to all verbs.
     *
     * @param  string $uri
     * @param  array|string|callable|null $action
     * @return void
     */
    public function any($uri, $action = null)
    {
        return $this->addRoute(self::$verbs, $uri, $action);
    }

    /**
     * Register a new route with the given verbs.
     * 
     * @param array|string $methods
     * @param string $uri
     * @param array|string|callable|null $action
     * @return void
     */
    public function match($methods, $uri, $action)
    {
        $this->addRoute(array_map('strtoupper', (array) $methods), $uri, $action);
    }

    /**
     * Dispatch the request to the application.
     * 
     * @param \Psr\Http\Message\ServerRequestInterface $request
     * @return mixed
     */
    public function dispatch(ServerRequestInterface $request)
    {
        $this->currentRequest = $request;
        $route = $this->findRoute($request);

        if ($route) {
            $this->current = $route;
            $route->setContainer($this->container);
            $this->container->instance(Route::class, $route);

            return $this->runRoute($request, $route);
        }

        throw new NotFoundHttpException(sprintf(
            'The route %s could not be found.',
            $request->getUri()->getPath()
        ));
    }

    /**
     * Find the route matching a given request.
     *
     * @param  \Psr\Http\Message\ServerRequestInterface  $request
     * @return \Framework\Routing\Route
     */
    protected function findRoute($request)
    {
        $routes = $this->getRouteByMethod($request->getMethod());

        return Arr::first(
            $routes,
            fn(Route $route) => $route->matches($request)
        );
    }

    /**
     * Return the response for the given route.
     *
     * @param \Psr\Http\Message\ServerRequestInterface $request
     * @param  \Framework\Routing\Route  $route
     * @return \Psr\Http\Message\ResponseInterface
     */
    protected function runRoute(ServerRequestInterface $request, Route $route)
    {
        return $this->prepareResponse(
            $request,
            $this->runRouteWithinStack($route, $request)
        );
    }

    /**
     * Run the given route within a Stack "onion" instance.
     *
     * @param  \Framework\Routing\Route  $route
     * @param  \Psr\Http\Message\ServerRequestInterface  $request
     * @return mixed
     */
    protected function runRouteWithinStack(Route $route, ServerRequestInterface $request)
    {
        $shouldSkipMiddleware = $this->container->bound('middleware.disable') &&
            $this->container->make('middleware.disable') === true;

        $middleware = $shouldSkipMiddleware ? [] : $this->gatherRouteMiddleware($route);

        return (new Pipeline($this->container))
            ->send($request)
            ->through($middleware)
            ->then(fn($request) => $this->prepareResponse(
                $request,
                $route->run()
            ));
    }

    /**
     * Gather the middleware for the given route with resolved class names.
     *
     * @param  \Framework\Routing\Route  $route
     * @return array
     */
    public function gatherRouteMiddleware(Route $route)
    {
        return $this->resolveMiddleware($route->gatherMiddleware(), $route->excludedMiddleware());
    }

    /**
     * Resolve a flat array of middleware classes from the provided array.
     *
     * @param  array  $middleware
     * @param  array  $excluded
     * @return array
     */
    public function resolveMiddleware(array $middleware, array $excluded = [])
    {
        $excluded = Arr::flatten(
            array_map(function ($name) {
                return (array) MiddlewareNameResolver::resolve($name, $this->middleware, $this->middlewareGroups);
            }, $excluded)
        );

        $middleware = Arr::flatten(
            array_map(function ($name) {
                return (array) MiddlewareNameResolver::resolve($name, $this->middleware, $this->middlewareGroups);
            }, $middleware)
        );

        $middleware = array_diff(array_unique(array_merge(
            $middleware,
            $excluded
        )));

        return $this->sortMiddleware($middleware);
    }

    /**
     * Sort the given middleware by priority.
     *
     * @param  array  $middlewares
     * @return array
     */
    protected function sortMiddleware($middlewares)
    {
        return (new SortedMiddleware($this->middlewarePriority, $middlewares))->all();
    }

    /**
     * Prepare the response for the given request.
     *
     * @param \Psr\Http\Message\ServerRequestInterface $request
     * @param  mixed  $response
     * @return \Psr\Http\Message\ResponseInterface
     */
    protected function prepareResponse(ServerRequestInterface $request, $response)
    {
        if ($response instanceof ResponseInterface) {
            return $response;
        }

        if ($response instanceof Response) {
            return $response;
        }

        $baseResponse = new Response();

        // foreach ($request->getHeaders() as $key => $value) {
        // Implement your custom logic to process and set headers as needed
        // $baseResponse = $baseResponse->withAddedHeader($key, $value);
        // }

        if (
            $response instanceof JsonSerializable ||
            $response instanceof ArrayObject ||
            $response instanceof stdClass ||
            is_array($response)
        ) {
            $response = json_encode($response, JSON_PRETTY_PRINT);
            $baseResponse = $baseResponse->withHeader('Content-Type', 'application/json');
        }

        $baseResponse->getBody()->write($response);

        return $baseResponse;
    }

    /**
     * Get the route collection.
     *
     * @return \Framework\Routing\Route[]
     */
    public function getRouteByMethod($method)
    {
        $method = strtoupper($method);

        return $this->routes[$method] ?? [];
    }

    /**
     * Determine if the router currently has a group stack.
     *
     * @return bool
     */
    public function hasGroupStack()
    {
        return !empty($this->groupStack);
    }

    /**
     * Get the current group stack for the router.
     *
     * @return array
     */
    public function getGroupStack()
    {
        return $this->groupStack;
    }

    /**
     * Get all of the defined middleware short-hand names.
     *
     * @return array
     */
    public function getMiddleware()
    {
        return $this->middleware;
    }

    /**
     * Register a short-hand name for a middleware.
     *
     * @param  string  $name
     * @param  string  $class
     * @return $this
     */
    public function aliasMiddleware($name, $class)
    {
        $this->middleware[$name] = $class;

        return $this;
    }

    /**
     * Check if a middlewareGroup with the given name exists.
     *
     * @param  string  $name
     * @return bool
     */
    public function hasMiddlewareGroup($name)
    {
        return array_key_exists($name, $this->middlewareGroups);
    }

    /**
     * Get all of the defined middleware groups.
     *
     * @return array
     */
    public function getMiddlewareGroups()
    {
        return $this->middlewareGroups;
    }

    /**
     * Register a group of middleware.
     *
     * @param  string  $name
     * @param  array  $middleware
     * @return $this
     */
    public function middlewareGroup($name, array $middleware)
    {
        $this->middlewareGroups[$name] = $middleware;

        return $this;
    }

    /**
     * Remove any duplicate middleware from the given array.
     *
     * @param  array  $middleware
     * @return array
     */
    public static function uniqueMiddleware(array $middleware)
    {
        $seen = [];
        $result = [];

        foreach ($middleware as $value) {
            $key = \is_object($value) ? \spl_object_id($value) : $value;

            if (!isset($seen[$key])) {
                $seen[$key] = true;
                $result[] = $value;
            }
        }

        return $result;
    }
}
