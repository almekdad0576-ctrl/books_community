<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\Api\AuthController;
use App\Http\Controllers\Api\BookController;

Route::post('/register', [AuthController::class, 'register']);
Route::post('/login', [AuthController::class, 'login']);

Route::middleware('auth:sanctum')->group(function () {
    Route::get('/user', [AuthController::class, 'getProfile']);
    Route::post('/logout', [AuthController::class, 'logout']);
    Route::get('/authors/{id}', [AuthController::class, 'show']);
    Route::put('/user', [AuthController::class, 'update']);
    Route::delete('/user', [AuthController::class, 'destroy']);

    Route::get('/books/popular', [BookController::class, 'popular']);
    Route::get('/books/recent', [BookController::class, 'recent']);
    Route::get('/books/search', [BookController::class, 'search']);
    Route::apiResource('books', BookController::class);
    Route::post('/books/{id}/upload', [BookController::class, 'upload']);
});
