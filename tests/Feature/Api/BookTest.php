<?php

namespace Tests\Feature\Api;

use App\Models\Book;
use App\Models\User;
use App\Enums\BookStatus;
use App\Enums\EntityType;
use App\Enums\FileType;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;
use Laravel\Sanctum\Sanctum;

class BookTest extends TestCase
{
    use RefreshDatabase;

    public function test_user_can_create_book_with_cover_photo(): void
    {
        Storage::fake('public');
        $user = User::factory()->create();
        Sanctum::actingAs($user);

        $image = UploadedFile::fake()->create('cover.jpg', 100);

        $response = $this->postJson('/api/books', [
            'title' => 'Test Book',
            'description' => 'Test Description',
            'image' => $image,
        ]);

        $response->assertStatus(201)
            ->assertJson([
                'success' => true,
                'code' => 201,
                'message' => 'Book created successfully',
            ])
            ->assertJsonPath('data.title', 'Test Book')
            ->assertJsonPath('data.status', BookStatus::PENDING_UPLOAD->value);

        $this->assertDatabaseHas('books', [
            'title' => 'Test Book',
            'status' => BookStatus::PENDING_UPLOAD->value,
        ]);

        $bookId = $response->json('data.id');
        $this->assertDatabaseHas('files', [
            'entity_id' => $bookId,
            'entity_type' => EntityType::BOOK->value,
            'type' => FileType::IMAGE->value,
        ]);
    }

    public function test_user_can_upload_book_file_in_chunks(): void
    {
        Storage::fake('local');
        Storage::fake('public');
        
        $user = User::factory()->create();
        $book = Book::factory()->create(['author_id' => $user->id, 'status' => BookStatus::PENDING_UPLOAD]);
        Sanctum::actingAs($user);

        $chunk1 = UploadedFile::fake()->create('chunk1.pdf', 100);
        $chunk2 = UploadedFile::fake()->create('chunk2.pdf', 100);

        // Upload first chunk
        $response = $this->postJson("/api/books/{$book->id}/upload", [
            'chunk_index' => 0,
            'total_chunks' => 2,
            'file_chunk' => $chunk1,
        ]);

        $response->assertStatus(200)
            ->assertJson([
                'success' => true,
                'code' => 200,
                'message' => 'Chunk uploaded successfully'
            ]);
        $this->assertEquals(BookStatus::PENDING_UPLOAD, $book->fresh()->status);

        // Upload second chunk
        $response = $this->postJson("/api/books/{$book->id}/upload", [
            'chunk_index' => 1,
            'total_chunks' => 2,
            'file_chunk' => $chunk2,
        ]);

        $response->assertStatus(200)
            ->assertJson([
                'success' => true,
                'code' => 200,
                'message' => 'Chunk uploaded successfully'
            ]);
        $this->assertEquals(BookStatus::ACTIVE, $book->fresh()->status);

        $this->assertDatabaseHas('files', [
            'entity_id' => $book->id,
            'entity_type' => EntityType::BOOK->value,
            'type' => FileType::DOCUMENT->value,
        ]);
    }

    public function test_user_can_create_book(): void
    {
        $user = User::factory()->create();
        Sanctum::actingAs($user);

        $response = $this->postJson('/api/books', [
            'title' => 'Test Book',
            'description' => 'Test Description',
        ]);

        $response->assertStatus(201)
            ->assertJson([
                'success' => true,
                'code' => 201,
                'message' => 'Book created successfully',
            ])
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
            ->assertJson([
                'success' => true,
                'code' => 200,
                'message' => 'Book retrieved successfully',
            ])
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
            ->assertJson([
                'success' => true,
                'code' => 200,
                'message' => 'Book retrieved successfully',
            ])
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
            ->assertJson([
                'success' => true,
                'code' => 200,
                'message' => 'Book updated successfully',
            ])
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

        $response->assertStatus(200)
            ->assertJson([
                'success' => true,
                'code' => 200,
                'message' => 'Book deleted successfully',
            ]);
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
