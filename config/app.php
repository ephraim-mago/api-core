<?php

return [
    /*
   |--------------------------------------------------------------------------
   | Application Name
   |
   | The name of the application. This value is used when the application
   |--------------------------------------------------------------------------
   |
   */

    'name' => env('APP_NAME', 'Laravel'),

    /*
   |--------------------------------------------------------------------------
   | Application Environment
   |
   | The environment the application is currently running in.
   |--------------------------------------------------------------------------
   |
   */

    'env' => env('APP_ENV', 'production'),

    /*
    |--------------------------------------------------------------------------
    | Application Debug Mode
    |
    | When DEBUG is true, errors will be displayed. When DEBUG is false,
    | errors will be logged. This option is useful 
    | when in development or during.
    |--------------------------------------------------------------------------
    |
    */

    'debug' => (bool) env('APP_DEBUG', false),

    /*
    |--------------------------------------------------------------------------
    | Application URL
    |
    | The URL to this application.
    |--------------------------------------------------------------------------
    |
    */

    'url' => env('APP_URL', 'http://localhost'),

    /*
   |--------------------------------------------------------------------------
   | Application Timezone
   |
   | The default timezone used by the PHP date and date-time functions.
   |--------------------------------------------------------------------------
   |
   */

    'timezone' => env('APP_TIMEZONE', 'UTC'),

    /*
   |--------------------------------------------------------------------------
   | Autoloaded Service Providers
   |
   | The service providers provided by your application 
   | for expanded the functionality.
   |--------------------------------------------------------------------------
   |
   */
    'providers' => [
        /*
         * Framework Service Providers...
         */
        Framework\Filesystem\FilesystemServiceProvider::class,

        /*
         * Package Service Providers...
         */

        /*
         * Application Service Providers...
         */
        App\Providers\AppServiceProvider::class,
        App\Providers\AuthServiceProvider::class,
        App\Providers\EventServiceProvider::class,
        App\Providers\RouteServiceProvider::class,
    ]
];