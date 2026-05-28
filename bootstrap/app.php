<?php

use App\Helpers\Utilities;
use App\Exceptions\PasswordMismatchException;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
use Illuminate\Auth\AuthenticationException;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
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

        // 1. Tell Laravel to treat all /api/* routes as JSON requests, 
        // which forces native JSON errors for 404s, Auth, and Validation.
        $exceptions->shouldRenderJsonWhen(function (Request $request, Throwable $e) {
            return $request->is('api/*') || $request->wantsJson();
        });

        // 2. Handle your specific Custom Exception
        $exceptions->render(function (PasswordMismatchException $e, Request $request) {
            return Utilities::apiResponse(false, 401, $e->getMessage());
        });

        // 3. Handle Model Not Found Exception
        $exceptions->render(function (ModelNotFoundException $e, Request $request) {
            return Utilities::apiResponse(false, 404, 'Resource not found');
        });

        // 4. Handle Not Found Http Exception (often converted from ModelNotFoundException)
        $exceptions->render(function (NotFoundHttpException $e, Request $request) {
            return Utilities::apiResponse(false, 404, 'Resource not found');
        });

        // 5. Handle Authentication Exception
        $exceptions->render(function (AuthenticationException $e, Request $request) {
            return Utilities::apiResponse(false, 401, 'Unauthenticated');
        });

        // 6. Handle Authorization Exception
        $exceptions->render(function (AuthorizationException $e, Request $request) {
            return Utilities::apiResponse(false, 403, 'This action is unauthorized.');
        });

        // 7. Catch all other unhandled API errors to normalize the output format
        $exceptions->render(function (\Throwable $e, Request $request) {
            if ($request->is('api/*')) {
                
                // Fallback details
                $status = 500;
                $message = 'An unexpected error occurred.';
                $data = [];

                // If it's a standard HTTP exception (like a 403 or 405), pull its code
                if ($e instanceof HttpExceptionInterface) {
                    $status = $e->getStatusCode();
                    $message = $e->getMessage() ?: $message;
                } 
                // Catch any stray Validation errors if they bypassed native handling
                elseif ($e instanceof ValidationException) {
                    $status = 422;
                    $message = 'The given data was invalid.';
                    $data = $e->errors();
                }

                return Utilities::apiResponse(false, $status, $message, $data);
            }
        });

    })->create();