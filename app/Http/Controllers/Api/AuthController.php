<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Services\AuthService;
use Illuminate\Http\Request;
use App\Http\Requests\Api\RegisterRequest;
use App\Http\Requests\Api\LoginRequest;
use App\Exceptions\UserNotFoundException;
use App\Exceptions\PasswordMismatchException;

class AuthController extends Controller
{
    protected $authService;

    public function __construct(AuthService $authService)
    {
        $this->authService = $authService;
    }

    public function register(RegisterRequest $request)
    {
        $token = $this->authService->registerAuthor($request->validated());

        return response()->json([
            'token' => $token,
            'message' => 'User registered successfully'
        ], 201);
    }

    public function login(LoginRequest $request)
    {
        try {
            $token = $this->authService->login($request->validated());

            return response()->json([
                'token' => $token,
                'message' => 'Login successful'
            ]);
        } catch (UserNotFoundException | PasswordMismatchException $e) {
            return response()->json([
                'message' => $e->getMessage()
            ], 401);
        }
    }

    public function getProfile(Request $request)
    {
        return response()->json($request->user());
    }
}
