<?php

namespace App\Services;

use App\Models\Comment;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Support\Facades\DB;

class CommentService
{
    public function createComment(array $data, string $userId): Comment
    {
        return DB::transaction(function () use ($data, $userId) {
            $comment = Comment::create([
                'book_id' => $data['book_id'],
                'user_id' => $userId,
                'parent_comment_id' => $data['parent_comment_id'] ?? null,
                'content' => $data['content'],
            ]);

            return $comment;
        });
    }

    public function getComments(string $bookId, $pageSize = null, $pageNumber = null)
    {
        $pageSize = (int) ($pageSize ?? 10);
        $pageNumber = (int) ($pageNumber ?? 1);
        $offset = ($pageNumber - 1) * $pageSize;

        return Comment::where('book_id', $bookId)
            ->whereNull('parent_comment_id')
            ->with('user', 'replies.user')
            ->orderBy('created_at', 'desc')
            ->offset($offset)
            ->limit($pageSize)
            ->get();
    }

    public function getComment(string $id): Comment
    {
        return Comment::with('user', 'replies.user')->findOrFail($id);
    }

    public function updateComment(Comment $comment, array $data): Comment
    {
        $comment->update($data);
        return $comment;
    }

    public function deleteComment(Comment $comment): bool
    {
        return $comment->delete();
    }
}
