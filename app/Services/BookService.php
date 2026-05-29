<?php

namespace App\Services;

use App\Models\Book;
use App\Models\User;
use App\Models\File;
use App\Enums\EntityType;
use App\Enums\FileType;
use App\Enums\BookStatus;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;

class BookService
{
    /**
     * Create a new book.
     */
    public function createBook(array $data, string $authorId): Book
    {
        return DB::transaction(function () use ($data, $authorId) {
            $image = $data['image'] ?? null;
            unset($data['image']);

            $book = Book::create([
                'title' => $data['title'],
                'description' => $data['description'] ?? null,
                'author_id' => $authorId,
                'category_id' => $data['category_id'],
                'status' => BookStatus::PENDING_UPLOAD,
            ]);

            if ($image) {
                $path = $image->store('books/covers', 'public');
                
                File::create([
                    'entity_id' => $book->id,
                    'entity_type' => EntityType::BOOK,
                    'type' => FileType::IMAGE,
                    'path' => $path,
                ]);
            }

            return $book;
        });
    }

    /**
     * Handle chunked upload for book file.
     */
    public function uploadBookFile(Book $book, $file, int $chunkIndex, int $totalChunks): Book
    {
        $tempPath = "temp/books/{$book->id}";
        $chunkName = "chunk_{$chunkIndex}";
        
        // Store chunk
        Storage::disk('local')->putFileAs($tempPath, $file, $chunkName);

        // Check if all chunks are uploaded
        $chunks = Storage::disk('local')->files($tempPath);
        
        if (count($chunks) === $totalChunks) {
            // Merge chunks
            $finalPath = "books/{$book->id}/book_" . time() . "." . $file->getClientOriginalExtension();
            
            $content = '';
            for ($i = 0; $i < $totalChunks; $i++) {
                $chunkPath = "{$tempPath}/chunk_{$i}";
                $content .= Storage::disk('local')->get($chunkPath);
                Storage::disk('local')->delete($chunkPath);
            }
            
            Storage::disk('public')->put($finalPath, $content);
            Storage::disk('local')->deleteDirectory($tempPath);

            // Create file record and update book status
            DB::transaction(function () use ($book, $finalPath) {
                File::create([
                    'entity_id' => $book->id,
                    'entity_type' => EntityType::BOOK,
                    'type' => FileType::DOCUMENT,
                    'path' => $finalPath,
                ]);

                $book->update(['status' => BookStatus::ACTIVE]);
            });
        }

        return $book;
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
     * Get popular books ordered by views_num.
     */
    public function getPopularBooks($pageSize = null, $pageNumber = null)
    {
        $pageSize = (int) ($pageSize ?? 10);
        $pageNumber = (int) ($pageNumber ?? 1);

        $offset = ($pageNumber - 1) * $pageSize;
        return Book::orderBy('views_num', 'desc')
            ->offset($offset)
            ->limit($pageSize)
            ->get();
    }

    /**
     * Get recent books ordered by created_at.
     */
    public function getRecentBooks($pageSize = null, $pageNumber = null)
    {
        $pageSize = (int) ($pageSize ?? 10);
        $pageNumber = (int) ($pageNumber ?? 1);

        $offset = ($pageNumber - 1) * $pageSize;
        return Book::orderBy('created_at', 'desc')
            ->offset($offset)
            ->limit($pageSize)
            ->get();
    }

    /**
     * Search books by title, author name, or category name.
     */
    public function searchBooks(?string $query, $pageSize = null, $pageNumber = null)
    {
        $pageSize = (int) ($pageSize ?? 10);
        $pageNumber = (int) ($pageNumber ?? 1);

        $offset = ($pageNumber - 1) * $pageSize;
        return Book::query()
            ->when($query, function ($q) use ($query) {
                $q->where('title', 'like', "%{$query}%")
                  ->orWhereHas('author', function ($q) use ($query) {
                      $q->where('name', 'like', "%{$query}%");
                  })
                  ->orWhereHas('category', function ($q) use ($query) {
                      $q->where('name', 'like', "%{$query}%");
                  });
            })
            ->offset($offset)
            ->limit($pageSize)
            ->get();
    }

    /**
     * Update a book.
     */
    public function updateBook(Book $book, array $data): Book
    {
        $book->update($data);
        return $book;
    }

    /**
     * Delete a book.
     */
    public function deleteBook(Book $book): bool
    {
        return $book->delete();
    }

    /**
     * Get books for a specific author.
     */
    public function getAuthorBooks(string $authorId, $pageSize = null, $pageNumber = null)
    {
        $pageSize = (int) ($pageSize ?? 10);
        $pageNumber = (int) ($pageNumber ?? 1);

        $offset = ($pageNumber - 1) * $pageSize;
        return Book::where('author_id', $authorId)
            ->orderBy('created_at', 'desc')
            ->offset($offset)
            ->limit($pageSize)
            ->get();
    }

    /**
     * Save a book for a user.
     */
    public function saveBook(string $userId, string $bookId): void
    {
        $book = Book::findOrFail($bookId);
        $book->savers()->syncWithoutDetaching($userId);
    }

    /**
     * Unsave a book for a user.
     */
    public function unsaveBook(string $userId, string $bookId): void
    {
        $book = Book::findOrFail($bookId);
        $book->savers()->detach($userId);
    }

    /**
     * Get saved books for a user with pagination.
     */
    public function getSavedBooks(string $userId, $pageSize = null, $pageNumber = null)
    {
        $pageSize = (int) ($pageSize ?? 10);
        $pageNumber = (int) ($pageNumber ?? 1);

        $offset = ($pageNumber - 1) * $pageSize;
        return User::findOrFail($userId)
            ->savedBooks()
            ->orderBy('book_saves.created_at', 'desc')
            ->offset($offset)
            ->limit($pageSize)
            ->get();
    }

    /**
     * Get the book file for a given book.
     */
    public function getBookFile(Book $book): ?File
    {
        return File::where('entity_id', $book->id)
            ->where('entity_type', EntityType::BOOK)
            ->where('type', FileType::DOCUMENT)
            ->first();
    }
}
