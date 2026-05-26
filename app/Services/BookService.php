<?php

namespace App\Services;

use App\Models\Book;
use Illuminate\Support\Facades\DB;

class BookService
{
    /**
     * Create a new book.
     */
    public function createBook(array $data, string $authorId): Book
    {
        return Book::create([
            'title' => $data['title'],
            'description' => $data['description'] ?? null,
            'author_id' => $authorId,
        ]);
    }

    /**
     * Get a book by its ID and increment views if viewed by someone other than the author.
     */
    public function getBook(string $id, ?string $viewerId = null): Book
    {
        $book = Book::findOrFail($id);

        if ($viewerId && $viewerId !== $book->author_id) {
            DB::transaction(function () use ($book, $viewerId) {
                $book->increment('views_num');
                $book->viewers()->attach($viewerId);
            });
        }

        return $book;
    }

    /**
     * Update a book.
     */
    public function updateBook(string $id, array $data, string $authorId): Book
    {
        $book = Book::where('author_id', $authorId)->findOrFail($id);
        $book->update($data);
        return $book;
    }

    /**
     * Delete a book.
     */
    public function deleteBook(string $id, string $authorId): bool
    {
        $book = Book::where('author_id', $authorId)->findOrFail($id);
        return $book->delete();
    }
}
