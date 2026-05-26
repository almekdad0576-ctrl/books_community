<?php

namespace App\Services;

use App\Models\User;
use App\Exceptions\UserNotFoundException;
use App\Exceptions\PasswordMismatchException;
use Illuminate\Support\Facades\Hash;

class AuthService
{
    public function registerAuthor(array $data)
    {
        // Hash the password
        $data['password'] = Hash::make($data['password']);

        // create author user in database
        $author = User::create($data);

        // Generate and return token
        return $author->createToken('auth_token')->plainTextToken;
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
