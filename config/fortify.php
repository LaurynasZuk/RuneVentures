<?php

use Laravel\Fortify\Features;

return [

    /*
    |--------------------------------------------------------------------------
    | Fortify Guard
    |--------------------------------------------------------------------------
    |
    | Here you may specify which authentication guard Fortify will use while
    | authenticating users. This value should correspond with one of your
    | guards that is already present in your "auth" configuration file.
    |
    */

    'guard' => 'web',

    /*
    |--------------------------------------------------------------------------
    | Fortify Password Broker
    |--------------------------------------------------------------------------
    |
    | Here you may specify which password broker Fortify can use when resetting
    | passwords. This configured value should match one of your password
    | brokers that is already present in your "auth" configuration file.
    |
    */

    'passwords' => 'users',

    /*
    |--------------------------------------------------------------------------
    | Username / Email
    |--------------------------------------------------------------------------
    |
    | Players authenticate with their unique account name. Email remains the
    | recovery address used by forgot-password and reset-password flows.
    |
    */

    'username' => 'name',

    'email' => 'email',

    /*
    |--------------------------------------------------------------------------
    | Lowercase Usernames
    |--------------------------------------------------------------------------
    |
    | Account names keep the exact casing chosen during registration.
    |
    */

    'lowercase_usernames' => false,

    /*
    |--------------------------------------------------------------------------
    | Home Path
    |--------------------------------------------------------------------------
    */

    'home' => '/main',

    /*
    |--------------------------------------------------------------------------
    | Fortify Routes Prefix / Subdomain
    |--------------------------------------------------------------------------
    */

    'prefix' => '',

    'domain' => null,

    /*
    |--------------------------------------------------------------------------
    | Fortify Routes Middleware
    |--------------------------------------------------------------------------
    */

    'middleware' => ['web'],

    /*
    |--------------------------------------------------------------------------
    | Rate Limiting
    |--------------------------------------------------------------------------
    */

    'limiters' => [
        'login' => 'login',
        /* @chisel-2fa */
        'two-factor' => 'two-factor',
        /* @end-chisel-2fa */
        /* @chisel-passkeys */
        'passkeys' => 'passkeys',
        /* @end-chisel-passkeys */
    ],

    /*
    |--------------------------------------------------------------------------
    | Register View Routes
    |--------------------------------------------------------------------------
    */

    'views' => true,

    /* @chisel-passkeys */
    'passkeys' => [
        'relying_party_id' => parse_url(config('app.url'), PHP_URL_HOST),
        'allowed_origins' => [config('app.url')],
        'user_handle_secret' => env('PASSKEYS_USER_HANDLE_SECRET', config('app.key')),
        'timeout' => 60000,
    ],
    /* @end-chisel-passkeys */

    /*
    |--------------------------------------------------------------------------
    | Features
    |--------------------------------------------------------------------------
    */

    'features' => [
        /* @chisel-registration */
        Features::registration(),
        /* @end-chisel-registration */
        Features::resetPasswords(),
        /* @chisel-email-verification */
        Features::emailVerification(),
        /* @end-chisel-email-verification */
        /* @chisel-2fa */
        Features::twoFactorAuthentication([
            'confirm' => true,
            'confirmPassword' => true,
        ]),
        /* @end-chisel-2fa */
        /* @chisel-passkeys */
        Features::passkeys([
            'confirmPassword' => true,
        ]),
        /* @end-chisel-passkeys */
    ],

];
