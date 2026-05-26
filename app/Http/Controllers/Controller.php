<?php

namespace App\Http\Controllers;

use App\Helpers\Utilities;
use Illuminate\Http\JsonResponse;

abstract class Controller
{
    /**
     * Standard API response format.
     */
    protected function json_response(bool $success, int $code, string $message, $data = []): JsonResponse
    {
        return Utilities::apiResponse($success, $code, $message, $data);
    }
}
