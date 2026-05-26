<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Services\AuthService;
use Illuminate\Http\Request;
use App\Http\Requests\Api\RegisterRequest;
use App\Http\Requests\Api\LoginRequest;
use App\Http\Requests\Api\UpdateUserRequest;
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

    public function logout(Request $request)
    {
        $this->authService->logout($request->user());

        return response()->json([
            'message' => 'Successfully logged out'
        ]);
    }

    public function update(UpdateUserRequest $request)
    {
        $user = $this->authService->updateUser($request->user(), $request->validated());

        return response()->json([
            'user' => $user,
            'message' => 'User updated successfully'
        ]);
    }

    public function destroy(Request $request)
    {
        $this->authService->deleteUser($request->user());

        return response()->json([
            'message' => 'User deleted successfully'
        ]);
    }
}
