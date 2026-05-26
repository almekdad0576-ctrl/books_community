<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\StoreBookRequest;
use App\Http\Requests\Api\UpdateBookRequest;
use App\Http\Requests\Api\UploadBookFileRequest;
use App\Http\Resources\BookResource;
use App\Services\BookService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class BookController extends Controller
{
    protected BookService $bookService;

    public function __construct(BookService $bookService)
    {
        $this->bookService = $bookService;
    }

    /**
     * Upload book file in chunks.
     */
    public function upload(UploadBookFileRequest $request, string $id)
    {
        $book = $this->bookService->uploadBookFile(
            $id,
            Auth::id(),
            $request->file('file_chunk'),
            $request->input('chunk_index'),
            $request->input('total_chunks')
        );

        return $this->json_response(true, 200, 'Chunk uploaded successfully', new BookResource($book));
    }

    /**
     * Store a newly created book in storage.
     */
    public function store(StoreBookRequest $request)
    {
        $book = $this->bookService->createBook($request->validated(), Auth::id());

        return $this->json_response(true, 201, 'Book created successfully', new BookResource($book));
    }

    /**
     * Display the specified book.
     */
    public function show(string $id)
    {
        $book = $this->bookService->getBook($id, Auth::id());

        return $this->json_response(true, 200, 'Book retrieved successfully', new BookResource($book));
    }

    /**
     * Update the specified book in storage.
     */
    public function update(UpdateBookRequest $request, string $id)
    {
        $book = $this->bookService->updateBook($id, $request->validated(), Auth::id());

        return $this->json_response(true, 200, 'Book updated successfully', new BookResource($book));
    }

    /**
     * Remove the specified book from storage.
     */
    public function destroy(string $id): JsonResponse
    {
        $this->bookService->deleteBook($id, Auth::id());

        return $this->json_response(true, 200, 'Book deleted successfully');
    }

    /**
     * Get popular books.
     */
    public function popular(Request $request)
    {
        $books = $this->bookService->getPopularBooks(
            $request->query('pageSize'),
            $request->query('pageNumber')
        );
        return $this->json_response(true, 200, 'Popular books retrieved successfully', BookResource::collection($books));
    }

    /**
     * Get recent books.
     */
    public function recent(Request $request)
    {
        $books = $this->bookService->getRecentBooks(
            $request->query('pageSize'),
            $request->query('pageNumber')
        );
        return $this->json_response(true, 200, 'Recent books retrieved successfully', BookResource::collection($books));
    }

    /**
     * Display a listing of books.
     */
    public function index(Request $request)
    {
        $books = $this->bookService->getRecentBooks(
            $request->query('pageSize'),
            $request->query('pageNumber')
        );
        return $this->json_response(true, 200, 'Books retrieved successfully', BookResource::collection($books));
    }

    /**
     * Search books.
     */
    public function search(Request $request)
    {
        $books = $this->bookService->searchBooks(
            $request->query('query'),
            $request->query('pageSize'),
            $request->query('pageNumber')
        );
        return $this->json_response(true, 200, 'Books retrieved successfully', BookResource::collection($books));
    }

    /**
     * Get books for the authenticated user.
     */
    public function userBooks(Request $request)
    {
        $books = $this->bookService->getAuthorBooks(
            Auth::id(),
            $request->query('pageSize'),
            $request->query('pageNumber')
        );
        return $this->json_response(true, 200, 'User books retrieved successfully', BookResource::collection($books));
    }
}
