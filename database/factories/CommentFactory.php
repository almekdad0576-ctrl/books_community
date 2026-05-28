<?php

namespace Database\Factories;

use App\Models\Book;
use App\Models\Comment;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

class CommentFactory extends Factory
{
    protected $model = Comment::class;

    public function definition(): array
    {
        return [
            'book_id' => Book::factory(),
            'user_id' => User::factory(),
            'content' => fake()->paragraph(),
        ];
    }

    public function withParent(Comment $parent): static
    {
        return $this->state(fn (array $attributes) => [
            'parent_comment_id' => $parent->id,
        ]);
    }
}
