<?php

namespace App\Services;

use App\Models\Book;
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
    public function uploadBookFile(string $id, string $authorId, $file, int $chunkIndex, int $totalChunks): Book
    {
        $book = Book::where('author_id', $authorId)->findOrFail($id);

        $tempPath = "temp/books/{$id}";
        $chunkName = "chunk_{$chunkIndex}";
        
        // Store chunk
        Storage::disk('local')->putFileAs($tempPath, $file, $chunkName);

        // Check if all chunks are uploaded
        $chunks = Storage::disk('local')->files($tempPath);
        
        if (count($chunks) === $totalChunks) {
            // Merge chunks
            $finalPath = "books/{$id}/book_" . time() . "." . $file->getClientOriginalExtension();
            
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
