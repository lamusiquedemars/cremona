<?php

use App\Exceptions\IdempotencyConflictException;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Request;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        api: __DIR__.'/../routes/api.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withCommands([
        __DIR__.'/../app/Console/Commands',
    ])
    ->withMiddleware(function (Middleware $middleware): void {
        $middleware->redirectGuestsTo(fn () => route('filament.admin.auth.login'));
        // This POST originates from a separate, configured site. Its exact
        // Origin and a short state bound to that site session are validated by
        // SitePortalLogoutController; Laravel's same-site CSRF token cannot
        // cross application domains.
        $middleware->validateCsrfTokens(except: ['site-portal/logout']);
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        $exceptions->render(function (IdempotencyConflictException $exception, Request $request) {
            if (! $request->is('api/*')) {
                return null;
            }

            return response()->json([
                'message' => 'The idempotency key has already been used with a different payload.',
            ], 409);
        });

        $exceptions->shouldRenderJsonWhen(
            fn (Request $request) => $request->is('api/*') || $request->expectsJson(),
        );
    })->create();
