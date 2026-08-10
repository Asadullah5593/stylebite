<?php

use App\Models\User;

return [

    /*
    |--------------------------------------------------------------------------
    | Authentication Defaults
    |--------------------------------------------------------------------------
    |
    | This option defines the default authentication "guard" and password
    | reset "broker" for your application. You may change these values
    | as required, but they're a perfect start for most applications.
    |
    */

    'defaults' => [
        'guard' => env('AUTH_GUARD', 'web'),
        'passwords' => env('AUTH_PASSWORD_BROKER', 'users'),
    ],

    /*
    |--------------------------------------------------------------------------
    | Authentication Guards
    |--------------------------------------------------------------------------
    |
    | Next, you may define every authentication guard for your application.
    | Of course, a great default configuration has been defined for you
    | which utilizes session storage plus the Eloquent user provider.
    |
    | All authentication guards have a user provider, which defines how the
    | users are actually retrieved out of your database or other storage
    | system used by the application. Typically, Eloquent is utilized.
    |
    | Supported: "session"
    |
    */

    'guards' => [
        'web' => [
            'driver' => 'session',
            'provider' => 'users',
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | User Providers
    |--------------------------------------------------------------------------
    |
    | All authentication guards have a user provider, which defines how the
    | users are actually retrieved out of your database or other storage
    | system used by the application. Typically, Eloquent is utilized.
    |
    | If you have multiple user tables or models you may configure multiple
    | providers to represent the model / table. These providers may then
    | be assigned to any extra authentication guards you have defined.
    |
    | Supported: "database", "eloquent"
    |
    */

    'providers' => [
        'users' => [
            'driver' => 'eloquent',
            'model' => env('AUTH_MODEL', User::class),
        ],

        // 'users' => [
        //     'driver' => 'database',
        //     'table' => 'users',
        // ],
    ],

    /*
    |--------------------------------------------------------------------------
    | Resetting Passwords
    |--------------------------------------------------------------------------
    |
    | These configuration options specify the behavior of Laravel's password
    | reset functionality, including the table utilized for token storage
    | and the user provider that is invoked to actually retrieve users.
    |
    | The expiry time is the number of minutes that each reset token will be
    | considered valid. This security feature keeps tokens short-lived so
    | they have less time to be guessed. You may change this as needed.
    |
    | The throttle setting is the number of seconds a user must wait before
    | generating more password reset tokens. This prevents the user from
    | quickly generating a very large amount of password reset tokens.
    |
    */

    'passwords' => [
        'users' => [
            'provider' => 'users',
            'table' => env('AUTH_PASSWORD_RESET_TOKEN_TABLE', 'password_reset_tokens'),
            'expire' => 60,
            'throttle' => 60,
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | Password Confirmation Timeout
    |--------------------------------------------------------------------------
    |
    | Here you may define the number of seconds before a password confirmation
    | window expires and users are asked to re-enter their password via the
    | confirmation screen. By default, the timeout lasts for three hours.
    |
    */

    'password_timeout' => env('AUTH_PASSWORD_TIMEOUT', 10800),

    /*
    |--------------------------------------------------------------------------
    | API Session Lifetime
    |--------------------------------------------------------------------------
    |
    | Hard lifetime, in hours, of a mobile-API bearer session counted from
    | login. Sessions are not renewed by activity — when this window closes
    | the user must log in (and pass the email 2FA) again.
    |
    */

    'api_session_hours' => (int) env('API_SESSION_HOURS', 24),

    /*
    |--------------------------------------------------------------------------
    | Login Two-Factor
    |--------------------------------------------------------------------------
    |
    | When true, a password login only issues a token after the emailed 6-digit
    | code is verified. This is the intended production behaviour.
    |
    | It is a switch because it is a BREAKING change for mobile builds that
    | predate the OTP screen: with it on, an old build can never complete a
    | login. Set LOGIN_TWO_FACTOR=false to keep the old single-step login while
    | the updated app rolls out, then remove the override to enable it.
    |
    */

    'login_two_factor' => filter_var(env('LOGIN_TWO_FACTOR', true), FILTER_VALIDATE_BOOLEAN),

    /*
    |--------------------------------------------------------------------------
    | Admin Panel Two-Factor
    |--------------------------------------------------------------------------
    |
    | When true, an admin-panel sign-in requires a 6-digit code emailed to the
    | staff member. Separate from the mobile API's login_two_factor: the panel
    | is the higher-value target and has no app release to coordinate with.
    |
    */

    'admin_two_factor' => filter_var(env('ADMIN_TWO_FACTOR', true), FILTER_VALIDATE_BOOLEAN),

    /*
    |--------------------------------------------------------------------------
    | Admin Session Lifetime
    |--------------------------------------------------------------------------
    |
    | Hard lifetime, in hours, of an admin-panel session counted from sign-in.
    | This is absolute, not idle-based: staying active does not extend it, so a
    | forgotten open dashboard cannot stay authenticated indefinitely.
    |
    */

    'admin_session_hours' => (int) env('ADMIN_SESSION_HOURS', 24),

    /*
    |--------------------------------------------------------------------------
    | Super Admins
    |--------------------------------------------------------------------------
    |
    | Comma-separated emails that bypass every permission check (Gate::before)
    | and always retain admin-panel access. Break-glass safety net so a bad
    | role edit cannot lock every administrator out.
    |
    */

    'super_admins' => env('SUPER_ADMIN_EMAILS', ''),

];
