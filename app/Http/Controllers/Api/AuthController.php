<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Services\AuthService;
use Illuminate\Http\Request;
use App\Http\Requests\Api\RegisterRequest;
use App\Http\Requests\Api\LoginRequest;
use App\Http\Requests\Api\UpdateUserRequest;
use App\Http\Resources\UserResource;

class AuthController extends Controller
{
    protected $authService;

    public function __construct(AuthService $authService)
    {
        $this->authService = $authService;
    }

    /**
     * Register a new user.
     * @requestMediaType multipart/form-data
     * @unauthenticated
     */
    public function register(RegisterRequest $request)
    {
        $token = $this->authService->registerAuthor($request->validated());

        return $this->json_response(true, 201, 'User registered successfully', [
            'token' => $token,
        ]);
    }

    /**
     * Login user.
     * @unauthenticated
     */
    public function login(LoginRequest $request)
    {
        $token = $this->authService->login($request->validated());

        return $this->json_response(true, 200, 'Login successful', [
            'token' => $token,
        ]);
    }

    /**
     * Get authenticated user profile.
     */
    public function getProfile(Request $request)
    {
        return $this->json_response(true, 200, 'Profile retrieved successfully', new UserResource($request->user()));
    }

    /**
     * Logout user.
     */
    public function logout(Request $request)
    {
        $this->authService->logout($request->user());

        return $this->json_response(true, 200, 'Successfully logged out');
    }

    /**
     * Update user profile.
     * @requestMediaType multipart/form-data
     */
    public function update(UpdateUserRequest $request)
    {
        $user = $this->authService->updateUser($request->user(), $request->validated());

        return $this->json_response(true, 200, 'User updated successfully', new UserResource($user));
    }

    /**
     * Delete user account.
     */
    public function destroy(Request $request)
    {
        $this->authService->deleteUser($request->user());

        return $this->json_response(true, 200, 'User deleted successfully');
    }

    /**
     * Get author by ID.
     * @unauthenticated
     */
    public function show(string $id)
    {
        $user = $this->authService->getUserById($id);
        return $this->json_response(true, 200, 'Author retrieved successfully', new UserResource($user));
    }
}
