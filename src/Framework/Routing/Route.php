<?php

namespace Framework\Routing;

use Throwable;
use LogicException;
use Framework\Support\Arr;
use UnexpectedValueException;
use Framework\Container\Container;
use Psr\Http\Message\ServerRequestInterface;

class Route
{
    /**
     * The URI pattern the route responds to.
     *
     * @var string
     */
    protected $uri;

    /**
     * The HTTP methods the route responds to.
     *
     * @var array
     */
    protected $methods;

    /**
     * The route action array.
     *
     * @var array
     */
    protected $action;

    /**
     * The controller instance.
     *
     * @var mixed
     */
    public $controller;

    /**
     * The array of matched parameters.
     *
     * @var array|null
     */
    protected $parameters;

    /**
     * The parameter names for the route.
     *
     * @var array|null
     */
    protected $parameterNames;

    /**
     * The computed gathered middleware.
     *
     * @var array|null
     */
    public $computedMiddleware;

    /**
     * The compiled version of the route.
     *
     * @var string
     */
    protected $compiled;

    /**
     * The router instance used by the route.
     *
     * @var \Framework\Routing\Router
     */
    protected $router;

    /**
     * The container instance used by the route.
     *
     * @var \Framework\Container\Container
     */
    protected $container;

    /**
     * Create a new Route instance.
     *
     * @param  array|string  $methods
     * @param  string  $uri
     * @param  \Closure|array  $action
     * @return void
     */
    public function __construct($methods, $uri, $action)
    {
        $this->uri = $uri;
        $this->methods = (array) $methods;
        $this->action = $this->parseAction($action);

        if (in_array('GET', $this->methods) && !in_array('HEAD', $this->methods)) {
            $this->methods[] = 'HEAD';
        }

        $this->compileRoute();
    }

    /**
     * Parse the route action into a standard array.
     *
     * @param  callable|array|null  $action
     * @return array
     *
     * @throws \UnexpectedValueException
     */
    protected function parseAction($action)
    {
        $uri = $this->uri;

        // If no action is passed in right away, we assume the user will make use of
        // fluent routing. In that case, we set a default closure, to be executed
        // if the user never explicitly sets an action to handle the given uri.
        if (is_null($action)) {
            return [
                'uses' => function () use ($uri) {
                    throw new LogicException("Route for [{$uri}] has no action.");
                }
            ];
        }

        // If the action is already a Closure instance, we will just set that instance
        // as the "uses" property, because there is nothing else we need to do when
        // it is available. Otherwise we will need to find it in the action list.
        if (is_callable($action, true)) {
            return !is_array($action) ? ['uses' => $action] : [
                'uses' => $action[0] . '@' . $action[1],
                'controller' => $action[0] . '@' . $action[1],
            ];
        }

        // If no "uses" property has been set, we will dig through the array to find a
        // Closure instance within this list. We will set the first Closure we come
        // across into the "uses" property that will get fired off by this route.
        elseif (!isset($action['uses'])) {
            $action['uses'] = Arr::first(
                $action,
                fn($value, $key) => is_callable($value) && is_numeric($key)
            );
        }

        if (is_string($action['uses']) && !str_contains($action['uses'], '@')) {
            if (!method_exists($action, '__invoke')) {
                throw new UnexpectedValueException("Invalid route action: [{$action}].");
            }

            $action['uses'] = $action . '@__invoke';
        }

        return $action;
    }

    /**
     * Run the route action and return the response.
     *
     * @return mixed
     */
    public function run()
    {
        try {
            if ($this->isControllerAction()) {
                return $this->runController();
            }

            return $this->runCallable();
        } catch (Throwable $e) {
            throw $e;
        }
    }

    /**
     * Checks whether the route's action is a controller.
     *
     * @return bool
     */
    protected function isControllerAction()
    {
        return is_string($this->action['uses']);
    }

    /**
     * Run the route action and return the response.
     *
     * @return mixed
     */
    protected function runCallable()
    {
        $callable = $this->action['uses'];

        return $this->container->call($callable, $this->parameters);
    }

    /**
     * Run the route action and return the response.
     *
     * @return mixed
     *
     */
    protected function runController()
    {
        $method = $this->getControllerMethod();

        if (!method_exists($this->getController(), $method)) {
            $class = $this->getControllerClass();

            throw new \Exception("Controller class or action does not exist for class $class::$method");
        }

        return $this->container->call([
            $this->getController(),
            $method
        ], $this->parameters);
    }

    /**
     * Get the controller instance for the route.
     *
     * @return mixed
     */
    public function getController()
    {
        if (!$this->isControllerAction()) {
            return null;
        }

        if (!$this->controller) {
            $class = $this->getControllerClass();

            $this->controller = $this->container->make(ltrim($class, '\\'));
        }

        return $this->controller;
    }

    /**
     * Get the controller class used for the route.
     *
     * @return string|null
     */
    public function getControllerClass()
    {
        return $this->isControllerAction() ? $this->parseControllerCallback()[0] : null;
    }

    /**
     * Get the controller method used for the route.
     *
     * @return string
     */
    protected function getControllerMethod()
    {
        return $this->parseControllerCallback()[1];
    }

    /**
     * Parse the controller.
     *
     * @return array
     */
    protected function parseControllerCallback()
    {
        return explode('@', $this->action['uses']);
    }

    /**
     * Determine if the route matches a given request.
     *
     * @param  \Psr\Http\Message\ServerRequestInterface  $request
     * @return bool
     */
    public function matches(ServerRequestInterface $request)
    {
        $path = trim($request->getUri()->getPath(), '/') ?: '/';

        if (
            preg_match($this->getCompiled(), $path, $matches) &&
            in_array(
                $request->getMethod(),
                $this->methods()
            )
        ) {
            $this->setParameters($matches);

            return true;
        }

        return false;
    }

    /**
     * Compile the route into a regular expression.
     *
     * @return string
     */
    protected function compileRoute()
    {
        if (!$this->compiled) {
            // Convertir les paramètres dynamiques en expressions régulières
            $pattern = preg_replace_callback(
                '/\{(\w+)(:([^}]+))?\}/',
                function ($matches) {
                    $name = $matches[1];
                    $regex = $matches[3] ?? '[^/]+';
                    return "(?P<$name>$regex)";
                },
                $this->uri
            );

            // Ajouter les délimiteurs d'expression régulière
            $this->compiled = '#^' . $pattern . '$#';
        }

        return $this->compiled;
    }

    /**
     * Get a given parameter from the route.
     *
     * @param  string  $name
     * @param  string|object|null  $default
     * @return string|object|null
     */
    public function parameter($name, $default = null)
    {
        return $this->parameters[$name] ?? $default;
    }

    /**
     * Set a parameter to the given value.
     *
     * @param  string  $name
     * @param  string|object|null  $value
     * @return void
     */
    public function setParameter($name, $value)
    {
        $this->parameters();

        $this->parameters[$name] = $value;
    }

    /**
     * Set a parameters to the given matches route.
     *
     * @param  array  $matches
     * @return void
     * 
     * @throws \LogicException
     */
    public function setParameters($matches)
    {
        if (is_null($this->compiled)) {
            throw new LogicException('Route is not compiled.');
        }

        if (empty($parameterNames = $this->parameterNames())) {
            $this->parameters = [];
        }

        $parameters = array_intersect_key($matches, array_flip($parameterNames));

        $this->parameters = array_filter($parameters, function ($value) {
            return is_string($value) && strlen($value) > 0;
        });
    }

    /**
     * Get the key / value list of parameters for the route.
     *
     * @return array
     *
     * @throws \LogicException
     */
    public function parameters()
    {
        if (isset($this->parameters)) {
            return $this->parameters;
        }

        throw new LogicException('Route is not bound.');
    }

    /**
     * Get all of the parameter names for the route.
     *
     * @return array
     */
    public function parameterNames()
    {
        if (isset($this->parameterNames)) {
            return $this->parameterNames;
        }

        return $this->parameterNames = $this->compileParameterNames();
    }

    /**
     * Get the parameter names for the route.
     *
     * @return array
     */
    protected function compileParameterNames()
    {
        preg_match_all('/\{(.*?)\}/', $this->uri, $matches);

        return array_map(fn($m) => trim($m, '?'), $matches[1]);
    }

    /**
     * Get the compiled version of the route.
     *
     * @return string
     */
    public function getCompiled()
    {
        return $this->compiled;
    }

    /**
     * Get the URI associated with the route.
     *
     * @return string
     */
    public function uri()
    {
        return $this->uri;
    }

    /**
     * Get the HTTP methods associated with the route.
     *
     * @return array
     */
    public function methods()
    {
        return $this->methods;
    }

    /**
     * Get the action array or one of its properties for the route.
     *
     * @param  string|null  $key
     * @return mixed
     */
    public function getAction($key = null)
    {
        return Arr::get($this->action, $key);
    }

    /**
     * Set the action array for the route.
     *
     * @param  array  $action
     * @return $this
     */
    public function setAction(array $action)
    {
        $this->action = $action;

        return $this;
    }

    /**
     * Get all middleware, including the ones from the controller.
     *
     * @return array
     */
    public function gatherMiddleware()
    {
        if (!is_null($this->computedMiddleware)) {
            return $this->computedMiddleware;
        }

        $this->computedMiddleware = [];

        return $this->computedMiddleware = Router::uniqueMiddleware(array_merge(
            $this->middleware(),
            $this->controllerMiddleware()
        ));
    }

    /**
     * Get or set the middlewares attached to the route.
     *
     * @param  array|string|null  $middleware
     * @return $this|array
     */
    public function middleware($middleware = null)
    {
        if (is_null($middleware)) {
            return (array) ($this->action['middleware'] ?? []);
        }

        if (!is_array($middleware)) {
            $middleware = func_get_args();
        }

        foreach ($middleware as $index => $value) {
            $middleware[$index] = (string) $value;
        }

        $this->action['middleware'] = array_merge(
            (array) ($this->action['middleware'] ?? []),
            $middleware
        );

        return $this;
    }

    /**
     * Get the middleware for the route's controller.
     *
     * @return array
     */
    public function controllerMiddleware()
    {
        if (!$this->isControllerAction()) {
            return [];
        }

        [$controllerClass, $controllerMethod] = [
            $this->getControllerClass(),
            $this->getControllerMethod(),
        ];

        if (method_exists($controllerClass, 'getMiddleware')) {
            return Arr::pluck($this->getController()->getMiddleware(), 'middleware');
        }

        return [];
    }

    /**
     * Specify middleware that should be removed from the given route.
     *
     * @param  array|string  $middleware
     * @return $this
     */
    public function withoutMiddleware($middleware)
    {
        $this->action['excluded_middleware'] = array_merge(
            (array) ($this->action['excluded_middleware'] ?? []),
            Arr::wrap($middleware)
        );

        return $this;
    }

    /**
     * Get the middleware that should be removed from the route.
     *
     * @return array
     */
    public function excludedMiddleware()
    {
        return (array) ($this->action['excluded_middleware'] ?? []);
    }

    /**
     * Set the router instance on the route.
     *
     * @param  \Framework\Routing\Router  $router
     * @return $this
     */
    public function setRouter(Router $router)
    {
        $this->router = $router;

        return $this;
    }

    /**
     * Set the container instance on the route.
     *
     * @param  \Framework\Container\Container  $container
     * @return $this
     */
    public function setContainer(Container $container)
    {
        $this->container = $container;

        return $this;
    }
}
