<?php

namespace Framework\Core\Configuration;

class Middleware
{
    /**
     * The user defined global middleware stack.
     *
     * @var array
     */
    protected $global = [];

    /**
     * The user defined middleware groups.
     *
     * @var array
     */
    protected $groups = [];

    /**
     * The custom middleware aliases.
     *
     * @var array
     */
    protected $customAliases = [];

    /**
     * The custom middleware priority definition.
     *
     * @var array
     */
    protected $priority = [];

    /**
     * Define the global middleware for the application.
     *
     * @param  array  $middleware
     * @return $this
     */
    public function use(array $middleware)
    {
        $this->global = $middleware;

        return $this;
    }

    /**
     * Define a middleware group.
     *
     * @param  string  $group
     * @param  array  $middleware
     * @return $this
     */
    public function group(string $group, array $middleware)
    {
        $this->groups[$group] = $middleware;

        return $this;
    }

    /**
     * Register additional middleware aliases.
     *
     * @param  array  $aliases
     * @return $this
     */
    public function alias(array $aliases)
    {
        $this->customAliases = $aliases;

        return $this;
    }

    /**
     * Define the middleware priority for the application.
     *
     * @param  array  $priority
     * @return $this
     */
    public function priority(array $priority)
    {
        $this->priority = $priority;

        return $this;
    }

    /**
     * Get the global middleware.
     *
     * @return array
     */
    public function getGlobalMiddleware()
    {
        $midleware = [
            \Framework\Http\Middleware\BodyParsingMiddleware::class,
            \Framework\Http\Middleware\ValidatePostSize::class,
            \Framework\Http\Middleware\HandleCors::class
        ];

        return array_values(array_filter(
            array_diff(
                array_unique(array_merge($midleware, $this->global))
            )
        ));
    }

    /**
     * Get the middleware groups.
     *
     * @return array
     */
    public function getMiddlewareGroups()
    {
        $middleware = [
            'web' => [

            ],

            'api' => [

            ]
        ];

        $middleware = array_merge($middleware, $this->groups);

        return $middleware;
    }

    /**
     * Get the middleware aliases.
     *
     * @return array
     */
    public function getMiddlewareAliases()
    {
        return array_merge($this->defaultAliases(), $this->customAliases);
    }

    /**
     * Get the default middleware aliases.
     *
     * @return array
     */
    protected function defaultAliases()
    {
        return [
            'auth' => \Framework\Auth\Middleware\Authenticate::class,
            'can' => \Framework\Auth\Middleware\Authorize::class,
        ];
    }

    /**
     * Get the middleware priority for the application.
     *
     * @return array
     */
    public function getMiddlewarePriority()
    {
        return $this->priority;
    }
}
