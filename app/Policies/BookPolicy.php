<?php

namespace App\Policies;

use App\Models\Book;
use App\Models\User;

class BookPolicy
{
    public function update(User $user, Book $book): bool
    {
        return $user->id === $book->author_id;
    }

    public function delete(User $user, Book $book): bool
    {
        return $user->id === $book->author_id;
    }
}
