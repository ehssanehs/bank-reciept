<?php

use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        api: __DIR__.'/../routes/api.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware) {
        $middleware->alias([
            'device.auth' => \App\Http\Middleware\DeviceAuthenticated::class,
            'role' => \App\Http\Middleware\EnsureRole::class,
            'telegram.secret' => \App\Http\Middleware\TelegramWebhookSecret::class,
        ]);

        $middleware->statefulApi();
        $middleware->redirectGuestsTo('/login');

        $middleware->web(prepend: [
            \App\Http\Middleware\SetLocale::class,
        ]);

        $middleware->trustProxies(
            at: '*',
            headers: \Illuminate\Http\Middleware\TrustProxies::HEADER_X_FORWARDED_FOR
                | \Illuminate\Http\Middleware\TrustProxies::HEADER_X_FORWARDED_PROTO
        );

        $middleware->throttleApi();
    })
    ->withExceptions(function (Exceptions $exceptions) {
        $exceptions->render(function (\Illuminate\Validation\ValidationException $e, $request) {
            if ($request->is('api/*') || $request->expectsJson()) {
                return response()->json([
                    'success' => false,
                    'message' => __('validation.invalid_input'),
                    'errors' => $e->errors(),
                ], 422);
            }
            return null;
        });

        $exceptions->render(function (\Illuminate\Auth\AuthenticationException $e, $request) {
            if ($request->is('api/*') || $request->expectsJson()) {
                return response()->json(['success' => false, 'message' => 'Unauthenticated.'], 401);
            }
            return null;
        });
    })->create();
