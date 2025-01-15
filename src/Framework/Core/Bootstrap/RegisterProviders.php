<?php

namespace Framework\Core\Bootstrap;

use Framework\Core\Application;

class RegisterProviders
{
    /**
     * Bootstrap the given application.
     *
     * @param  \Framework\Core\Application  $app
     * @return void
     */
    public function bootstrap(Application $app)
    {
        $app->registerConfiguredProviders();
    }
}
