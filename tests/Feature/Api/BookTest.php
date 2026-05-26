<?php

namespace Tests\Feature\Api;

use App\Models\Book;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;
use Laravel\Sanctum\Sanctum;

class BookTest extends TestCase
{
    use RefreshDatabase;

    public function test_user_can_create_book(): void
    {
        $user = User::factory()->create();
        Sanctum::actingAs($user);

        $response = $this->postJson('/api/books', [
            'title' => 'Test Book',
            'description' => 'Test Description',
        ]);

        $response->assertStatus(201)
            ->assertJsonPath('data.title', 'Test Book')
            ->assertJsonPath('data.author_id', $user->id);

        $this->assertDatabaseHas('books', [
            'title' => 'Test Book',
            'author_id' => $user->id,
        ]);
    }

    public function test_author_viewing_own_book_does_not_increment_views(): void
    {
        $user = User::factory()->create();
        $book = Book::factory()->create(['author_id' => $user->id]);
        Sanctum::actingAs($user);

        $response = $this->getJson("/api/books/{$book->id}");

        $response->assertStatus(200)
            ->assertJsonPath('data.views_num', 0);
        
        $this->assertEquals(0, $book->fresh()->views_num);
    }

    public function test_other_user_viewing_book_increments_views(): void
    {
        $author = User::factory()->create();
        $viewer = User::factory()->create();
        $book = Book::factory()->create(['author_id' => $author->id]);
        Sanctum::actingAs($viewer);

        $response = $this->getJson("/api/books/{$book->id}");

        $response->assertStatus(200)
            ->assertJsonPath('data.views_num', 1);
        
        $this->assertEquals(1, $book->fresh()->views_num);
        $this->assertDatabaseHas('book_views', [
            'book_id' => $book->id,
            'user_id' => $viewer->id,
        ]);
    }

    public function test_author_can_update_own_book(): void
    {
        $user = User::factory()->create();
        $book = Book::factory()->create(['author_id' => $user->id]);
        Sanctum::actingAs($user);

        $response = $this->putJson("/api/books/{$book->id}", [
            'title' => 'Updated Title',
        ]);

        $response->assertStatus(200)
            ->assertJsonPath('data.title', 'Updated Title');
        
        $this->assertDatabaseHas('books', [
            'id' => $book->id,
            'title' => 'Updated Title',
        ]);
    }

    public function test_user_cannot_update_others_book(): void
    {
        $author = User::factory()->create();
        $otherUser = User::factory()->create();
        $book = Book::factory()->create(['author_id' => $author->id]);
        Sanctum::actingAs($otherUser);

        $response = $this->putJson("/api/books/{$book->id}", [
            'title' => 'Updated Title',
        ]);

        $response->assertStatus(404);
    }

    public function test_author_can_delete_own_book(): void
    {
        $user = User::factory()->create();
        $book = Book::factory()->create(['author_id' => $user->id]);
        Sanctum::actingAs($user);

        $response = $this->deleteJson("/api/books/{$book->id}");

        $response->assertStatus(200);
        $this->assertDatabaseMissing('books', ['id' => $book->id]);
    }

    public function test_user_cannot_delete_others_book(): void
    {
        $author = User::factory()->create();
        $otherUser = User::factory()->create();
        $book = Book::factory()->create(['author_id' => $author->id]);
        Sanctum::actingAs($otherUser);

        $response = $this->deleteJson("/api/books/{$book->id}");

        $response->assertStatus(404);
        $this->assertDatabaseHas('books', ['id' => $book->id]);
    }
}
