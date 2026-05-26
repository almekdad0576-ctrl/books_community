<?php

namespace App\Services;

use App\Models\User;
use App\Models\File;
use App\Enums\EntityType;
use App\Exceptions\UserNotFoundException;
use App\Exceptions\PasswordMismatchException;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\DB;

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
        $author = User::where('email', $data['email'])->first();
        if (!$author) 
            throw new UserNotFoundException('Email not found');

        // Check if the password is correct
        if (!Hash::check($data['password'], $author->password)) 
            throw new PasswordMismatchException('Password is incorrect');
        // Generate and return token
        return $author->createToken('auth_token')->plainTextToken;
    }
}
