<?php

namespace App\Http\Controllers;

abstract class Controller
{
    protected function json_response(bool $success,int $code, string $message, array $data = [])
    {
        return response()->json([
            'success' => $success,
            'message' => $message,
            'code' => $code,
            'data' => $data,
        ]);
    }
}
