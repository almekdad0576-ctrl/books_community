<?php

use App\Helpers\Utilities;
use App\Exceptions\PasswordMismatchException;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\ValidationException;
use Illuminate\Auth\AuthenticationException;
use Illuminate\Auth\Access\AuthorizationException;
use Symfony\Component\HttpKernel\Exception\HttpExceptionInterface;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__ . '/../routes/web.php',
        api: __DIR__ . '/../routes/api.php',
        commands: __DIR__ . '/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware) {
        // This tells Laravel to trust the headers sent by Render's load balancer
        $middleware->trustProxies(at: '*');
    })
    ->withExceptions(function (Exceptions $exceptions) {

        $exceptions->shouldRenderJsonWhen(
            fn (Request $request) => $request->is('api/*') || $request->wantsJson()
        );

        $exceptions->render(function (PasswordMismatchException $e) {
            Log::warning('Password mismatch', ['exception' => $e]);
            return Utilities::apiResponse(false, 401, $e->getMessage());
        });

        $exceptions->render(function (AuthenticationException $e) {
            Log::warning('Authentication failed', ['exception' => $e]);
            return Utilities::apiResponse(false, 401, 'Unauthenticated');
        });

        $exceptions->render(function (AuthorizationException $e) {
            Log::warning('Authorization failed', ['exception' => $e]);
            return Utilities::apiResponse(false, 403, 'This action is unauthorized.');
        });

        $exceptions->render(function (ValidationException $e) {
            Log::warning('Validation failed', ['exception' => $e, 'errors' => $e->errors()]);
            return Utilities::apiResponse(false, 422, 'The given data was invalid.', $e->errors());
        });

        $exceptions->render(function (HttpExceptionInterface $e) {
            Log::error('HTTP exception occurred', ['exception' => $e, 'status_code' => $e->getStatusCode()]);
            return Utilities::apiResponse(
                false,
                $e->getStatusCode(),
                $e->getMessage() ?: 'HTTP error occurred'
            );
        });

        $exceptions->render(function (\Throwable $e, Request $request) {
            Log::error('Unexpected exception occurred', ['exception' => $e, 'request' => $request->all()]);
            if (!($request->is('api/*') || $request->wantsJson())) {
                return null;
            }

            return Utilities::apiResponse(
                false,
                500,
                'An unexpected error occurred.'
            );
        });
    })
    ->create();