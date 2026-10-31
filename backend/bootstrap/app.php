<?php

use App\Http\Middleware\EnforcePlanFeatures;
use App\Http\Middleware\RoleMiddleware;
use App\Http\Middleware\SecurityHeaders;
use App\Support\RequestId;
use App\Support\TrustedProxies;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Auth\AuthenticationException;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Exceptions\ThrottleRequestsException;
use Illuminate\Http\Request;
use Illuminate\Session\TokenMismatchException;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpKernel\Exception\HttpExceptionInterface;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        api: __DIR__.'/../routes/api.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    // Broadcast auth over the Sanctum bearer token (not the web session): POST /api/broadcasting/auth.
    ->withBroadcasting(__DIR__.'/../routes/channels.php', [
        'prefix' => 'api',
        'middleware' => ['auth:sanctum', 'throttle:api'],
    ])
    ->withMiddleware(function (Middleware $middleware): void {
        $middleware->alias([
            'role' => RoleMiddleware::class,
            'plan.features' => EnforcePlanFeatures::class,
        ]);

        // BSEC-02: only proxies listed in TRUSTED_PROXIES may set X-Forwarded-*.
        // Default is none, so a spoofed X-Forwarded-For can never bypass a throttle.
        $middleware->trustProxies(
            at: TrustedProxies::parse(env('TRUSTED_PROXIES')),
            headers: Request::HEADER_X_FORWARDED_FOR | Request::HEADER_X_FORWARDED_PROTO | Request::HEADER_X_FORWARDED_PORT,
        );

        // BSEC-10: default per-user/IP limiter on the whole API; BSEC-28: security headers.
        $middleware->throttleApi('api');
        $middleware->api(append: [SecurityHeaders::class]);
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        $exceptions->render(function (ValidationException $e, Request $request) {
            if ($request->expectsJson()) {
                return response()->json([
                    'message' => 'Validation failed',
                    'errors' => $e->errors(),
                ], 422);
            }
        });

        $exceptions->render(function (NotFoundHttpException $e, Request $request) {
            if ($request->expectsJson()) {
                return response()->json([
                    'message' => 'Resource not found',
                ], 404);
            }
        });

        // BSEC-25: JSON errors never carry exception class, file, SQL or trace
        // (unless APP_DEBUG, which production refuses at boot). Anything
        // unexpected becomes a generic 500 with a request id that is also logged.
        $exceptions->shouldRenderJsonWhen(fn (Request $request, Throwable $e) => $request->is('api/*') || $request->expectsJson());

        $exceptions->context(fn (Throwable $e) => ['request_id' => RequestId::current()]);

        $exceptions->render(function (Throwable $e, Request $request) {
            if (config('app.debug') || ! ($request->is('api/*') || $request->expectsJson())) {
                return null;
            }
            if ($e instanceof ValidationException || $e instanceof AuthenticationException) {
                return null; // already generic, shapes are relied on by the SPA
            }

            $id = RequestId::current();
            $status = 500;
            $message = 'Something went wrong. Quote this reference if you contact support.';
            $headers = ['X-Request-Id' => $id];

            if ($e instanceof ModelNotFoundException) {
                $status = 404;
                $message = 'Resource not found';
            } elseif ($e instanceof AuthorizationException) {
                $status = 403;
                $message = 'Forbidden';
            } elseif ($e instanceof TokenMismatchException) {
                $status = 419;
                $message = 'Session expired';
            } elseif ($e instanceof ThrottleRequestsException) {
                $status = 429;
                $message = 'Too many requests';
                $headers += $e->getHeaders();
            } elseif ($e instanceof HttpExceptionInterface) {
                $status = $e->getStatusCode();
                $headers += $e->getHeaders();
                // 4xx messages are written by our own abort() calls; 5xx and framework 404s are not.
                $message = $status < 500 && $status !== 404 ? ($e->getMessage() ?: 'Request failed') : ($status === 404 ? 'Resource not found' : 'Something went wrong. Quote this reference if you contact support.');
            }

            if ($status >= 500) {
                Log::error('Unhandled API error', ['request_id' => $id, 'exception' => $e::class, 'path' => $request->path()]);
            }

            return response()->json(['message' => $message, 'requestId' => $id], $status, $headers);
        });
    })->create();
