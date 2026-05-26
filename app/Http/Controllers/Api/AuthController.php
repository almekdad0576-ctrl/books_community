<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Services\AuthService;
use Illuminate\Http\Request;
use App\Http\Requests\Api\RegisterRequest;
use App\Http\Requests\Api\LoginRequest;
use App\Http\Requests\Api\UpdateUserRequest;
use App\Http\Resources\UserResource;
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

        return $this->json_response(true, 201, 'User registered successfully', [
            'token' => $token,
        ]);
    }

    public function login(LoginRequest $request)
    {
        try {
            $token = $this->authService->login($request->validated());

            return $this->json_response(true, 200, 'Login successful', [
                'token' => $token,
            ]);
        } catch (UserNotFoundException | PasswordMismatchException $e) {
            return $this->json_response(false, 401, $e->getMessage());
        }
    }

    public function getProfile(Request $request)
    {
        return $this->json_response(true, 200, 'Profile retrieved successfully', new UserResource($request->user()));
    }

    public function logout(Request $request)
    {
        $this->authService->logout($request->user());

        return $this->json_response(true, 200, 'Successfully logged out');
    }

    public function update(UpdateUserRequest $request)
    {
        $user = $this->authService->updateUser($request->user(), $request->validated());

        return $this->json_response(true, 200, 'User updated successfully', new UserResource($user));
    }

    public function destroy(Request $request)
    {
        $this->authService->deleteUser($request->user());

        return $this->json_response(true, 200, 'User deleted successfully');
    }
}
