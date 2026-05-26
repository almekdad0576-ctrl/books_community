<?php

use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Symfony\Component\HttpKernel\Exception\HttpExceptionInterface;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        api: __DIR__.'/../routes/api.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware) {
        //
    })
    ->withExceptions(function (Exceptions $exceptions) {
        $exceptions->render(function (\Throwable $e, Request $request) {
            
            // Only modify behavior for API routes
            if ($request->is('api/*')) {

                // 1. Automatically ignore ALL HTTP client-side errors (400 up to 499)
                if ($e instanceof HttpExceptionInterface && $e->getStatusCode() < 500) {
                    return null;
                }

                // 2. Ignore specific core Laravel client-side exceptions
                $ignoredExceptions = [
                    \Illuminate\Validation\ValidationException::class,
                    \Illuminate\Auth\AuthenticationException::class,
                    \Illuminate\Auth\Access\AuthorizationException::class,
                    \Illuminate\Database\Eloquent\ModelNotFoundException::class,
                ];

                foreach ($ignoredExceptions as $exceptionClass) {
                    if ($e instanceof $exceptionClass) {
                        return null; // Fallback to Laravel's default clean JSON response
                    }
                }

                // 3. If it gets here, it's a true, unexpected 500 Server Error (e.g. DB crash, Code Typos)
                Log::error('API Error: ' . $e->getMessage(), [
                    'url'       => $request->fullUrl(),
                    'method'    => $request->method(),
                    'input'     => $request->except(['password', 'password_confirmation']),
                    'exception' => $e,
                ]);

                return response()->json([
                    'message' => 'An unexpected error occurred.',
                    'error'   => config('app.debug') ? $e->getMessage() : 'Internal Server Error'
                ], 500);
            }
        });
    })->create();