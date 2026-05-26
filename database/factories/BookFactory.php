<?php

namespace Database\Factories;

use App\Enums\BookStatus;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\Book>
 */
class BookFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'title' => $this->faker->sentence,
            'description' => $this->faker->paragraph,
            'author_id' => \App\Models\User::factory(),
            'views_num' => 0,
            'status' => BookStatus::PENDING_UPLOAD,
        ];
    }
}
