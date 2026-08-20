<?php

use App\Exceptions\MailDeliveryException;
use Illuminate\Auth\AuthenticationException;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Request;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        api: __DIR__.'/../routes/api.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware) {
        $middleware->alias([
            'role' => \App\Http\Middleware\RoleMiddleware::class,
            'agent.active' => \App\Http\Middleware\EnsureActiveAgent::class,
        ]);
    })
    ->withExceptions(function (Exceptions $exceptions) {
        // The mobile app expects the documented error shape on every failure:
        // { "message": "...", "errors": { "field": ["..."] } }
        $exceptions->render(function (AuthenticationException $e, Request $request) {
            if ($request->expectsJson() || $request->is('api/*')) {
                return response()->json(['message' => 'Unauthenticated.'], 401);
            }
        });

        // An OTP that could not be sent must never look like a success.
        $exceptions->render(function (MailDeliveryException $e, Request $request) {
            if ($request->expectsJson() || $request->is('api/*')) {
                return response()->json([
                    'code' => 'mail_not_configured',
                    'message' => $e->getMessage(),
                ], 503);
            }

            return back()->withInput()->with('error', $e->getMessage());
        });

        $exceptions->render(function (NotFoundHttpException $e, Request $request) {
            if ($request->is('api/*')) {
                return response()->json([
                    'message' => $e->getMessage() ?: 'Resource not found.',
                ], 404);
            }
        });
    })->create();
