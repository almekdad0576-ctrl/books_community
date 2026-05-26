<?php

namespace App\Helpers;

use Illuminate\Http\JsonResponse;

class Utilities
{
    /**
     * Standard API response format.
     */
    public static function apiResponse(bool $success, int $code, string $message, $data = []): JsonResponse
    {
        return response()->json([
            'success' => $success,
            'code' => $code,
            'message' => $message,
            'data' => $data,
        ], $code);
    }
}
