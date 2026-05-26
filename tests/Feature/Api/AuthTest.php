<?php

namespace Tests\Feature\Api;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AuthTest extends TestCase
{
    use RefreshDatabase;

    public function test_user_can_register()
    {
        $response = $this->postJson('/api/register', [
            'name' => 'Test User',
            'email' => 'test@example.com',
            'password' => 'password123',
        ]);

        $response->assertStatus(201)
            ->assertJsonStructure([
                'success',
                'code',
                'message',
                'data' => ['token']
            ]);

        $this->assertDatabaseHas('users', [
            'email' => 'test@example.com',
        ]);
    }

    public function test_user_cannot_register_with_existing_email()
    {
        User::factory()->create(['email' => 'test@example.com']);

        $response = $this->postJson('/api/register', [
            'name' => 'Another User',
            'email' => 'test@example.com',
            'password' => 'password123',
        ]);

        $response->assertStatus(422)
            ->assertJson([
                'success' => false,
                'code' => 422,
                'message' => 'The given data was invalid.',
                'data' => [
                    'email' => ['The email has already been taken.']
                ]
            ]);
    }

    public function test_user_can_login()
    {
        $user = User::factory()->create([
            'email' => 'test@example.com',
            'password' => \Illuminate\Support\Facades\Hash::make('password123'),
        ]);

        $response = $this->postJson('/api/login', [
            'email' => 'test@example.com',
            'password' => 'password123',
        ]);

        $response->assertStatus(200)
            ->assertJsonStructure([
                'success',
                'code',
                'message',
                'data' => ['token']
            ]);
    }

    public function test_user_cannot_login_with_wrong_password()
    {
        User::factory()->create([
            'email' => 'test@example.com',
            'password' => \Illuminate\Support\Facades\Hash::make('password123'),
        ]);

        $response = $this->postJson('/api/login', [
            'email' => 'test@example.com',
            'password' => 'wrong_password',
        ]);

        $response->assertStatus(401)
            ->assertJson([
                'success' => false,
                'code' => 401,
                'message' => 'Password is incorrect'
            ]);
    }

    public function test_user_cannot_login_if_not_found()
    {
        $response = $this->postJson('/api/login', [
            'email' => 'nonexistent@example.com',
            'password' => 'password123',
        ]);

        $response->assertStatus(404)
            ->assertJson([
                'success' => false,
                'code' => 404,
                'message' => 'Resource not found'
            ]);
    }

    public function test_authenticated_user_can_get_profile()
    {
        $user = User::factory()->create();
        $token = $user->createToken('test_token')->plainTextToken;

        $response = $this->getJson('/api/user', [
            'Authorization' => 'Bearer ' . $token,
        ]);

        $response->assertStatus(200)
            ->assertJson([
                'success' => true,
                'code' => 200,
                'data' => [
                    'id' => $user->id,
                    'email' => $user->email,
                ]
            ]);
    }

    public function test_user_can_logout()
    {
        $user = User::factory()->create();
        $token = $user->createToken('test_token')->plainTextToken;

        $response = $this->postJson('/api/logout', [], [
            'Authorization' => 'Bearer ' . $token,
        ]);

        $response->assertStatus(200)
            ->assertJson([
                'success' => true,
                'code' => 200,
                'message' => 'Successfully logged out'
            ]);

        $this->assertCount(0, $user->tokens);
    }

    public function test_unauthenticated_user_cannot_get_profile()
    {
        $response = $this->getJson('/api/user');

        $response->assertStatus(401);
    }

    public function test_user_can_update_profile()
    {
        $user = User::factory()->create([
            'name' => 'Old Name',
            'email' => 'old@example.com',
        ]);
        $token = $user->createToken('test_token')->plainTextToken;

        $response = $this->putJson('/api/user', [
            'name' => 'New Name',
            'email' => 'new@example.com',
        ], [
            'Authorization' => 'Bearer ' . $token,
        ]);

        $response->assertStatus(200)
            ->assertJson([
                'success' => true,
                'code' => 200,
                'message' => 'User updated successfully',
                'data' => [
                    'name' => 'New Name',
                    'email' => 'new@example.com',
                ]
            ]);

        $this->assertDatabaseHas('users', [
            'id' => $user->id,
            'name' => 'New Name',
            'email' => 'new@example.com',
        ]);
    }

    public function test_user_can_delete_profile()
    {
        $user = User::factory()->create();
        $token = $user->createToken('test_token')->plainTextToken;

        $response = $this->deleteJson('/api/user', [], [
            'Authorization' => 'Bearer ' . $token,
        ]);

        $response->assertStatus(200)
            ->assertJson([
                'success' => true,
                'code' => 200,
                'message' => 'User deleted successfully'
            ]);

        $this->assertDatabaseMissing('users', [
            'id' => $user->id,
        ]);
    }

    public function test_user_can_get_author_profile_publicly()
    {
        $user = User::factory()->create();

        $response = $this->getJson("/api/authors/{$user->id}");

        $response->assertStatus(200)
            ->assertJson([
                'success' => true,
                'code' => 200,
                'message' => 'Author retrieved successfully',
                'data' => [
                    'id' => $user->id,
                    'name' => $user->name,
                ]
            ]);
    }

    public function test_register_validation_errors()
    {
        $response = $this->postJson('/api/register', []);

        $response->assertStatus(422)
            ->assertJson([
                'success' => false,
                'code' => 422,
                'data' => [
                    'name' => ['The name field is required.'],
                    'email' => ['The email field is required.'],
                    'password' => ['The password field is required.'],
                ]
            ]);
    }

    public function test_login_validation_errors()
    {
        $response = $this->postJson('/api/login', []);

        $response->assertStatus(422)
            ->assertJson([
                'success' => false,
                'code' => 422,
                'data' => [
                    'email' => ['The email field is required.'],
                    'password' => ['The password field is required.'],
                ]
            ]);
    }

    public function test_update_profile_validation_errors()
    {
        $user = User::factory()->create();
        $token = $user->createToken('test_token')->plainTextToken;

        $response = $this->putJson('/api/user', [
            'email' => 'not-an-email',
            'password' => 'short',
        ], [
            'Authorization' => 'Bearer ' . $token,
        ]);

        $response->assertStatus(422)
            ->assertJson([
                'success' => false,
                'code' => 422,
                'data' => [
                    'email' => ['The email field must be a valid email address.'],
                    'password' => ['The password field must be at least 6 characters.'],
                ]
            ]);
    }
}
