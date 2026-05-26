<?php

namespace App\Http\Controllers;

abstract class Controller
{
    /**
     * Standard API response format.
     */
    protected function json_response(bool $success, int $code, string $message, $data = [])
    {
        return response()->json([
            'success' => $success,
            'code' => $code,
            'message' => $message,
            'data' => $data,
        ], $code);
    }
}
