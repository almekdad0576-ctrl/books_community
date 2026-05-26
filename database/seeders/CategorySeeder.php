<?php

namespace Database\Seeders;

use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class CategorySeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $categories = [
            'Fiction',
            'Non-Fiction',
            'Science Fiction',
            'Fantasy',
            'Mystery',
            'Thriller',
            'Romance',
            'Horror',
            'Biography',
            'History',
            'Self-Help',
            'Cooking',
            'Travel',
            'Art',
            'Science',
            'Technology',
        ];

        foreach ($categories as $category) {
            \App\Models\Category::firstOrCreate(['name' => $category]);
        }
    }
}
