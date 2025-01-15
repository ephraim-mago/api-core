<?php

return [
    /*
    |--------------------------------------------------------------------------
    | Authentication Defaults
    |--------------------------------------------------------------------------
    |
    */

    'defaults' => [
        'guard' => env('AUTH_GUARD', 'api'),
    ],

    /*
    |--------------------------------------------------------------------------
    | Authentication Guards
    |
    | Here you may define authentication guards for your application.
    | Supported: "token"
    |--------------------------------------------------------------------------
    |
    */

    'guards' => [
        'api' => ['driver' => 'api'],
    ],

    /*
    |--------------------------------------------------------------------------
    | User Providers
    |
    | Retrieved or storage mechanisms used to persist your user's data.
    | Supported: "database", "custom"
    |--------------------------------------------------------------------------
    |
    */
    'providers' => [
        //
    ],

    /*
    |--------------------------------------------------------------------------
    | Resetting Passwords
    |
    | The options for resetting passwords.
    |--------------------------------------------------------------------------
    |
    */
    'passwords' => [
        //
    ],
];