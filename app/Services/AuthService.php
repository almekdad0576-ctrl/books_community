<?php

namespace App\Services;

use App\Models\User;
use App\Models\File;
use App\Enums\EntityType;
use App\Enums\FileType;
use App\Exceptions\PasswordMismatchException;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;

class AuthService
{
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
                $path = $image->store('users', 'public');
                
                File::create([
                    'entity_id' => $author->id,
                    'entity_type' => EntityType::USER,
                    'type' => FileType::IMAGE,
                    'path' => $path,
                ]);
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
                // Delete old image if exists
                $oldFile = File::where('entity_id', $user->id)
                    ->where('entity_type', EntityType::USER)
                    ->where('type', FileType::IMAGE)
                    ->first();

                if ($oldFile) {
                    Storage::disk('public')->delete($oldFile->path);
                    $oldFile->delete();
                }

                $path = $image->store('users', 'public');
                
                File::create([
                    'entity_id' => $user->id,
                    'entity_type' => EntityType::USER,
                    'type' => FileType::IMAGE,
                    'path' => $path,
                ]);
            }

            return $user->load('tokens'); // Return user with tokens if needed
        });
    }

    public function deleteUser(User $user)
    {
        return DB::transaction(function () use ($user) {
            // Delete associated file/image
            $file = File::where('entity_id', $user->id)
                ->where('entity_type', EntityType::USER)
                ->where('type', FileType::IMAGE)
                ->first();

            if ($file) {
                Storage::disk('public')->delete($file->path);
                $file->delete();
            }

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
