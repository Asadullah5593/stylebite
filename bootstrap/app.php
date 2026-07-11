<?php

use Illuminate\Auth\AuthenticationException;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpFoundation\Response;

if (! function_exists('api_database_error_message')) {
    function api_database_error_message(QueryException $exception): string
    {
        $message = Str::lower($exception->getMessage());

        if (str_contains($message, 'device_tokens_platform_push_token_unique')) {
            return 'This device token is already in use. Please sign in again or try another device.';
        }

        if (str_contains($message, 'duplicate entry')) {
            return 'This record already exists. Please use a different value and try again.';
        }

        if (str_contains($message, 'foreign key constraint fails') || str_contains($message, 'a foreign key constraint fails')) {
            return 'The selected related record is invalid or no longer available.';
        }

        if (str_contains($message, 'cannot delete or update a parent row')) {
            return 'This record cannot be changed because it is linked to other data.';
        }

        if (str_contains($message, 'cannot be null') || str_contains($message, 'doesn\'t have a default value')) {
            return 'Some required data is missing. Please review your request and try again.';
        }

        if (str_contains($message, 'data too long for column')) {
            return 'One of the provided values is too long. Please shorten it and try again.';
        }

        return 'We could not process your request right now. Please try again.';
    }
}

if (! function_exists('api_database_error_status')) {
    function api_database_error_status(QueryException $exception): int
    {
        $message = Str::lower($exception->getMessage());

        if (str_contains($message, 'duplicate entry')) {
            return Response::HTTP_CONFLICT;
        }

        if (str_contains($message, 'foreign key constraint fails') || str_contains($message, 'a foreign key constraint fails')) {
            return Response::HTTP_UNPROCESSABLE_ENTITY;
        }

        if (str_contains($message, 'cannot be null') || str_contains($message, 'doesn\'t have a default value') || str_contains($message, 'data too long for column')) {
            return Response::HTTP_UNPROCESSABLE_ENTITY;
        }

        return Response::HTTP_INTERNAL_SERVER_ERROR;
    }
}

if (! function_exists('api_validation_first_message')) {
    function api_validation_first_message(array $errors): ?string
    {
        $field = array_key_first($errors);
        $message = collect($errors)->flatten()->first();

        if (! $field || ! is_string($message)) {
            return $message;
        }

        return match ($field) {
            'target_user_id' => 'The selected user was not found.',
            'parent_reply_id' => 'The selected reply was not found.',
            'device_id' => str_contains(Str::lower($message), 'required')
                ? 'Device ID is required when a push token is provided.'
                : $message,
            'platform' => str_contains(Str::lower($message), 'invalid')
                ? 'Please choose a valid platform.'
                : $message,
            'push_token' => str_contains(Str::lower($message), 'required')
                ? 'Push token is required for this request.'
                : $message,
            'email' => str_contains(Str::lower($message), 'taken')
                ? 'An account with this email already exists.'
                : $message,
            'username' => str_contains(Str::lower($message), 'taken')
                ? 'This username is already in use. Please choose another one.'
                : $message,
            default => $message,
        };
    }
}

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        api: __DIR__.'/../routes/api.php',
        web: __DIR__.'/../routes/web.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        $middleware->alias([
            'session.auth' => \App\Http\Middleware\SessionTokenAuth::class,
            'admin' => \App\Http\Middleware\EnsureAdminUser::class,
        ]);
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        $exceptions->render(function (ValidationException $exception, Request $request) {
            if (! $request->is('api/*')) {
                return null;
            }

            $errors = $exception->errors();
            $firstError = api_validation_first_message($errors);

            return response()->json([
                'status_code' => 0,
                'message' => $firstError ?: 'The given data was invalid.',
                'errors' => $errors,
            ], $exception->status);
        });

        $exceptions->render(function (ModelNotFoundException $exception, Request $request) {
            if (! $request->is('api/*')) {
                return null;
            }

            return response()->json([
                'status_code' => 0,
                'message' => 'Requested resource was not found.',
            ], Response::HTTP_NOT_FOUND);
        });

        $exceptions->render(function (AuthenticationException $exception, Request $request) {
            if (! $request->is('api/*')) {
                return null;
            }

            return response()->json([
                'status_code' => 0,
                'message' => 'Unauthenticated.',
            ], Response::HTTP_UNAUTHORIZED);
        });

        $exceptions->render(function (QueryException $exception, Request $request) {
            if (! $request->is('api/*')) {
                return null;
            }

            return response()->json([
                'status_code' => 0,
                'message' => api_database_error_message($exception),
            ], api_database_error_status($exception));
        });

        $exceptions->render(function (Throwable $exception, Request $request) {
            if (! $request->is('api/*')) {
                return null;
            }

            return response()->json([
                'status_code' => 0,
                'message' => 'Something went wrong. Please try again.',
            ], Response::HTTP_INTERNAL_SERVER_ERROR);
        });
    })->create();
