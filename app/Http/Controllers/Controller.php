<?php

namespace App\Http\Controllers;

use App\Helpers\Utilities;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Illuminate\Foundation\Validation\ValidatesRequests;
use Illuminate\Http\JsonResponse;
use Illuminate\Routing\Controller as BaseController;

abstract class Controller extends BaseController
{
    use AuthorizesRequests;

    /**
     * Standard API response format.
     */
    protected function json_response(bool $success, int $code, string $message, $data = []): JsonResponse
    {
        return Utilities::apiResponse($success, $code, $message, $data);
    }
}
