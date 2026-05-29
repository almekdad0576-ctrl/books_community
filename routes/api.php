<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\Api\AuthController;
use App\Http\Controllers\Api\BookController;
use App\Http\Controllers\Api\CategoryController;
use App\Http\Controllers\Api\CommentController;

Route::post('/register', [AuthController::class, 'register']);
Route::post('/login', [AuthController::class, 'login']);
Route::get('/authors/{id}', [AuthController::class, 'show']);
Route::get('/categories', [CategoryController::class, 'index']);
Route::get('/books/popular', [BookController::class, 'popular']);
Route::get('/books/recent', [BookController::class, 'recent']);
Route::get('/books/search', [BookController::class, 'search']);
Route::get('/books', [BookController::class, 'index']);
Route::get('/books/{id}', [BookController::class, 'show']);

Route::get('/comments', [CommentController::class, 'index']);
Route::get('/comments/{id}', [CommentController::class, 'show']);

Route::middleware('auth:sanctum')->group(function () {
    Route::get('/user', [AuthController::class, 'getProfile']);
    Route::get('/user/books', [BookController::class, 'userBooks']);
    Route::get('/user/saved-books', [BookController::class, 'savedBooks']);
    Route::post('/logout', [AuthController::class, 'logout']);
    Route::put('/user', [AuthController::class, 'update']);
    Route::delete('/user', [AuthController::class, 'destroy']);

    Route::apiResource('books', BookController::class)->except(['index', 'show']);
    Route::post('/books/{book}/upload', [BookController::class, 'upload']);
    Route::post('/books/{book}/save', [BookController::class, 'saveBook']);
    Route::post('/books/{book}/unsave', [BookController::class, 'unsaveBook']);

    Route::apiResource('comments', CommentController::class)->only(['store', 'update', 'destroy']);
});
