<?php

namespace Tests\Feature\Api;

use App\Models\Book;
use App\Models\User;
use App\Models\Category;
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
        $category = Category::factory()->create();
        Sanctum::actingAs($user);

        $image = UploadedFile::fake()->create('cover.jpg', 100);

        $response = $this->postJson('/api/books', [
            'title' => 'Test Book',
            'description' => 'Test Description',
            'image' => $image,
            'category_id' => $category->id,
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
        $category = Category::factory()->create();
        Sanctum::actingAs($user);

        $response = $this->postJson('/api/books', [
            'title' => 'Test Book',
            'description' => 'Test Description',
            'category_id' => $category->id,
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

        $response->assertStatus(403)
            ->assertJson([
                'success' => false,
                'code' => 403,
                'message' => 'This action is unauthorized.',
            ]);
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

        $response->assertStatus(403)
            ->assertJson([
                'success' => false,
                'code' => 403,
                'message' => 'This action is unauthorized.',
            ]);
        $this->assertDatabaseHas('books', ['id' => $book->id]);
    }

    public function test_user_can_get_all_categories(): void
    {
        Category::factory()->count(3)->create();

        $response = $this->getJson('/api/categories');

        $response->assertStatus(200)
            ->assertJson([
                'success' => true,
                'code' => 200,
                'message' => 'Categories retrieved successfully',
            ])
            ->assertJsonCount(3, 'data');
    }

    public function test_guest_can_view_book(): void
    {
        $book = Book::factory()->create();

        $response = $this->getJson("/api/books/{$book->id}");

        $response->assertStatus(200)
            ->assertJson([
                'success' => true,
                'code' => 200,
                'message' => 'Book retrieved successfully',
            ]);
    }

    public function test_user_can_search_books_by_category_name(): void
    {
        $category = Category::factory()->create(['name' => 'Adventure']);
        $book = Book::factory()->create(['category_id' => $category->id, 'title' => 'The Quest']);
        Book::factory()->create(['title' => 'Other Book']);

        $response = $this->getJson('/api/books/search?query=Adventure');

        $response->assertStatus(200)
            ->assertJson([
                'success' => true,
                'code' => 200,
                'message' => 'Books retrieved successfully',
            ])
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.title', 'The Quest')
            ->assertJsonPath('data.0.category_name', 'Adventure');
    }

    public function test_user_can_get_popular_books_publicly(): void
    {
        Book::factory()->count(5)->create();

        $response = $this->getJson('/api/books/popular');

        $response->assertStatus(200)
            ->assertJson([
                'success' => true,
                'code' => 200,
                'message' => 'Popular books retrieved successfully',
            ]);
    }

    public function test_user_can_get_recent_books_publicly(): void
    {
        Book::factory()->count(5)->create();

        $response = $this->getJson('/api/books/recent');

        $response->assertStatus(200)
            ->assertJson([
                'success' => true,
                'code' => 200,
                'message' => 'Recent books retrieved successfully',
            ]);
    }

    public function test_user_can_get_own_books(): void
    {
        $user = User::factory()->create();
        Sanctum::actingAs($user);
        
        Book::factory()->count(3)->create(['author_id' => $user->id]);
        Book::factory()->count(2)->create(); // Books by other users

        $response = $this->getJson('/api/user/books');

        $response->assertStatus(200)
            ->assertJson([
                'success' => true,
                'code' => 200,
                'message' => 'User books retrieved successfully',
            ])
            ->assertJsonCount(3, 'data');
    }

    public function test_user_can_get_all_books_publicly(): void
    {
        Book::factory()->count(5)->create();

        $response = $this->getJson('/api/books');

        $response->assertStatus(200)
            ->assertJson([
                'success' => true,
                'code' => 200,
                'message' => 'Books retrieved successfully',
            ])
            ->assertJsonCount(5, 'data');
    }

    public function test_create_book_validation_errors(): void
    {
        $user = User::factory()->create();
        Sanctum::actingAs($user);

        $response = $this->postJson('/api/books', []);

        $response->assertStatus(422)
            ->assertJson([
                'success' => false,
                'code' => 422,
                'data' => [
                    'title' => ['The title field is required.'],
                    'category_id' => ['The category id field is required.'],
                ]
            ]);
    }

    public function test_update_book_validation_errors(): void
    {
        $user = User::factory()->create();
        $book = Book::factory()->create(['author_id' => $user->id]);
        Sanctum::actingAs($user);

        $response = $this->putJson("/api/books/{$book->id}", [
            'title' => '',
            'category_id' => 'not-a-uuid',
        ]);

        $response->assertStatus(422)
            ->assertJson([
                'success' => false,
                'code' => 422,
                'data' => [
                    'title' => ['The title field is required.'],
                    'category_id' => ['The category id field must be a valid UUID.'],
                ]
            ]);
    }

    public function test_upload_book_file_validation_errors(): void
    {
        $user = User::factory()->create();
        $book = Book::factory()->create(['author_id' => $user->id]);
        Sanctum::actingAs($user);

        $response = $this->postJson("/api/books/{$book->id}/upload", []);

        $response->assertStatus(422)
            ->assertJson([
                'success' => false,
                'code' => 422,
                'data' => [
                    'chunk_index' => ['The chunk index field is required.'],
                    'total_chunks' => ['The total chunks field is required.'],
                    'file_chunk' => ['The file chunk field is required.'],
                ]
            ]);
    }

    public function test_user_can_save_a_book(): void
    {
        $user = User::factory()->create();
        $book = Book::factory()->create();
        Sanctum::actingAs($user);

        $response = $this->postJson("/api/books/{$book->id}/save");

        $response->assertStatus(200)
            ->assertJson([
                'success' => true,
                'code' => 200,
                'message' => 'Book saved successfully',
            ]);

        $this->assertDatabaseHas('book_saves', [
            'user_id' => $user->id,
            'book_id' => $book->id,
        ]);
    }

    public function test_user_can_unsave_a_book(): void
    {
        $user = User::factory()->create();
        $book = Book::factory()->create();
        Sanctum::actingAs($user);

        // First save the book
        $user->savedBooks()->attach($book->id);

        $response = $this->postJson("/api/books/{$book->id}/unsave");

        $response->assertStatus(200)
            ->assertJson([
                'success' => true,
                'code' => 200,
                'message' => 'Book unsaved successfully',
            ]);

        $this->assertDatabaseMissing('book_saves', [
            'user_id' => $user->id,
            'book_id' => $book->id,
        ]);
    }

    public function test_user_can_get_saved_books(): void
    {
        $user = User::factory()->create();
        Sanctum::actingAs($user);

        $books = Book::factory()->count(3)->create();
        foreach ($books as $book) {
            $user->savedBooks()->attach($book->id);
        }

        $response = $this->getJson('/api/user/saved-books');

        $response->assertStatus(200)
            ->assertJson([
                'success' => true,
                'code' => 200,
                'message' => 'Saved books retrieved successfully',
            ])
            ->assertJsonCount(3, 'data');
    }

    public function test_guest_cannot_save_book(): void
    {
        $book = Book::factory()->create();

        $response = $this->postJson("/api/books/{$book->id}/save");

        $response->assertStatus(401);
    }

    public function test_guest_cannot_unsave_book(): void
    {
        $book = Book::factory()->create();

        $response = $this->postJson("/api/books/{$book->id}/unsave");

        $response->assertStatus(401);
    }

    public function test_guest_cannot_get_saved_books(): void
    {
        $response = $this->getJson('/api/user/saved-books');

        $response->assertStatus(401);
    }
}
