<?php

namespace Tests\Feature\Api;

use App\Models\Book;
use App\Models\Comment;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;
use Laravel\Sanctum\Sanctum;

class CommentTest extends TestCase
{
    use RefreshDatabase;

    public function test_guest_can_view_comments_for_a_book(): void
    {
        $book = Book::factory()->create();
        Comment::factory()->count(3)->create(['book_id' => $book->id, 'parent_comment_id' => null]);
        Comment::factory()->count(2)->create(); // Comments for other books

        $response = $this->getJson("/api/comments?book_id={$book->id}");

        $response->assertStatus(200)
            ->assertJson([
                'success' => true,
                'code' => 200,
                'message' => 'Comments retrieved successfully',
            ])
            ->assertJsonCount(3, 'data');
    }

    public function test_guest_can_view_single_comment(): void
    {
        $comment = Comment::factory()->create();

        $response = $this->getJson("/api/comments/{$comment->id}");

        $response->assertStatus(200)
            ->assertJson([
                'success' => true,
                'code' => 200,
                'message' => 'Comment retrieved successfully',
            ])
            ->assertJsonPath('data.id', $comment->id);
    }

    public function test_user_can_create_comment(): void
    {
        $user = User::factory()->create();
        $book = Book::factory()->create();
        Sanctum::actingAs($user);

        $response = $this->postJson('/api/comments', [
            'book_id' => $book->id,
            'content' => 'Test comment',
        ]);

        $response->assertStatus(201)
            ->assertJson([
                'success' => true,
                'code' => 201,
                'message' => 'Comment created successfully',
            ])
            ->assertJsonPath('data.content', 'Test comment');

        $this->assertDatabaseHas('comments', [
            'book_id' => $book->id,
            'user_id' => $user->id,
            'content' => 'Test comment',
        ]);
        $this->assertEquals(1, $book->fresh()->comment_count);
    }

    public function test_user_can_create_reply_to_comment(): void
    {
        $user = User::factory()->create();
        $book = Book::factory()->create();
        $parentComment = Comment::factory()->create(['book_id' => $book->id]);
        Sanctum::actingAs($user);

        $response = $this->postJson('/api/comments', [
            'book_id' => $book->id,
            'parent_comment_id' => $parentComment->id,
            'content' => 'Test reply',
        ]);

        $response->assertStatus(201);
        $this->assertDatabaseHas('comments', [
            'parent_comment_id' => $parentComment->id,
            'content' => 'Test reply',
        ]);
        $this->assertEquals(1, $parentComment->fresh()->reply_count);
        $this->assertEquals(2, $book->fresh()->comment_count); // Parent + reply
    }

    public function test_user_can_update_own_comment_without_replies(): void
    {
        $user = User::factory()->create();
        $comment = Comment::factory()->create(['user_id' => $user->id]);
        Sanctum::actingAs($user);

        $response = $this->putJson("/api/comments/{$comment->id}", [
            'content' => 'Updated comment',
        ]);

        $response->assertStatus(200)
            ->assertJson([
                'success' => true,
                'code' => 200,
                'message' => 'Comment updated successfully',
            ])
            ->assertJsonPath('data.content', 'Updated comment');

        $this->assertDatabaseHas('comments', [
            'id' => $comment->id,
            'content' => 'Updated comment',
        ]);
    }

    public function test_user_cannot_update_comment_with_replies(): void
    {
        $user = User::factory()->create();
        $parentComment = Comment::factory()->create(['user_id' => $user->id]);
        Comment::factory()->create(['parent_comment_id' => $parentComment->id]); // Create a reply
        Sanctum::actingAs($user);

        $response = $this->putJson("/api/comments/{$parentComment->id}", [
            'content' => 'Updated comment',
        ]);

        $response->assertStatus(403);
        $this->assertDatabaseHas('comments', [
            'id' => $parentComment->id,
            'content' => $parentComment->content, // Original content should remain
        ]);
    }

    public function test_user_cannot_update_others_comment(): void
    {
        $author = User::factory()->create();
        $otherUser = User::factory()->create();
        $comment = Comment::factory()->create(['user_id' => $author->id]);
        Sanctum::actingAs($otherUser);

        $response = $this->putJson("/api/comments/{$comment->id}", [
            'content' => 'Updated comment',
        ]);

        $response->assertStatus(403);
    }

    public function test_user_can_delete_own_comment_without_replies(): void
    {
        $user = User::factory()->create();
        $book = Book::factory()->create();
        $comment = Comment::factory()->create(['user_id' => $user->id, 'book_id' => $book->id]);
        Sanctum::actingAs($user);

        $response = $this->deleteJson("/api/comments/{$comment->id}");

        $response->assertStatus(200)
            ->assertJson([
                'success' => true,
                'code' => 200,
                'message' => 'Comment deleted successfully',
            ]);

        $this->assertDatabaseMissing('comments', ['id' => $comment->id]);
        $this->assertEquals(0, $book->fresh()->comment_count);
    }

    public function test_book_owner_can_delete_comment_with_replies(): void
    {
        $bookOwner = User::factory()->create();
        $book = Book::factory()->create(['author_id' => $bookOwner->id]);
        $comment = Comment::factory()->create(['book_id' => $book->id]);
        Comment::factory()->count(2)->create(['parent_comment_id' => $comment->id, 'book_id' => $book->id]); // Create replies
        Sanctum::actingAs($bookOwner);

        $response = $this->deleteJson("/api/comments/{$comment->id}");

        $response->assertStatus(200);
        $this->assertDatabaseMissing('comments', ['id' => $comment->id]);
        $this->assertEquals(0, $book->fresh()->comment_count); // All comments should be deleted
    }

    public function test_user_cannot_delete_others_comment_with_replies(): void
    {
        $user = User::factory()->create();
        $otherUser = User::factory()->create();
        $book = Book::factory()->create(['author_id' => $otherUser->id]);
        $comment = Comment::factory()->create(['user_id' => $otherUser->id, 'book_id' => $book->id]);
        Comment::factory()->create(['parent_comment_id' => $comment->id]);
        Sanctum::actingAs($user);

        $response = $this->deleteJson("/api/comments/{$comment->id}");

        $response->assertStatus(403);
        $this->assertDatabaseHas('comments', ['id' => $comment->id]);
    }

    public function test_create_comment_validation_errors(): void
    {
        $user = User::factory()->create();
        Sanctum::actingAs($user);

        $response = $this->postJson('/api/comments', []);

        $response->assertStatus(422)
            ->assertJson([
                'success' => false,
                'code' => 422,
                'data' => [
                    'book_id' => ['The book id field is required.'],
                    'content' => ['The content field is required.'],
                ]
            ]);
    }

    public function test_update_comment_validation_errors(): void
    {
        $user = User::factory()->create();
        $comment = Comment::factory()->create(['user_id' => $user->id]);
        Sanctum::actingAs($user);

        $response = $this->putJson("/api/comments/{$comment->id}", [
            'content' => '',
        ]);

        $response->assertStatus(422)
            ->assertJson([
                'success' => false,
                'code' => 422,
                'data' => [
                    'content' => ['The content field is required.'],
                ]
            ]);
    }
}
