<?php

namespace App\Services;

use App\Models\User;
use App\Enums\FileType;
use App\Exceptions\PasswordMismatchException;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\DB;

class AuthService
{
    protected FileService $fileService;

    public function __construct(FileService $fileService)
    {
        $this->fileService = $fileService;
    }
    public function registerAuthor(array $data)
    {
        return DB::transaction(function () use ($data) {
            // Extract image if exists
            $image = $data['image'] ?? null;
            unset($data['image']);

            // Hash the password
            $data['password'] = Hash::make($data['password']);

            // create author user in database
            $author = User::create($data);

            // Handle image upload
            if ($image) {
                $this->fileService->attachToUser($author, $image, FileType::IMAGE);
            }

            // Generate and return token
            return $author->createToken('auth_token')->plainTextToken;
        });
    }

    public function login(array $data)
    {
        // Check if the user exists
        $author = User::where('email', $data['email'])->firstOrFail();

        // Check if the password is correct
        if (!Hash::check($data['password'], $author->password)) 
            throw new PasswordMismatchException('Password is incorrect');
        // Generate and return token
        return $author->createToken('auth_token')->plainTextToken;
    }

    public function logout(User $user)
    {
        // Revoke the current token
        $user->currentAccessToken()->delete();
    }

    public function updateUser(User $user, array $data)
    {
        return DB::transaction(function () use ($user, $data) {
            // Extract image if exists
            $image = $data['image'] ?? null;
            unset($data['image']);

            // Hash the password if provided
            if (isset($data['password'])) {
                $data['password'] = Hash::make($data['password']);
            }

            // Update user in database
            $user->update($data);

            // Handle image upload
            if ($image) {
                $this->fileService->attachToUser($user, $image, FileType::IMAGE);
            }

            return $user->load('tokens'); // Return user with tokens if needed
        });
    }

    public function deleteUser(User $user)
    {
        return DB::transaction(function () use ($user) {
            // Delete associated file/image
            $this->fileService->detach($user, FileType::IMAGE);

            // Revoke all tokens
            $user->tokens()->delete();

            // Delete user
            $user->delete();
        });
    }

    /**
     * Get a user by their ID.
     */
    public function getUserById(string $id): User
    {
        return User::findOrFail($id);
    }
}
