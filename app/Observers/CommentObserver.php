<?php

namespace App\Observers;

use App\Models\Comment;

class CommentObserver
{
    public function created(Comment $comment): void
    {
        $this->updateCounts($comment);
    }

    public function deleted(Comment $comment): void
    {
        $this->updateCounts($comment);
    }

    protected function updateCounts(Comment $comment): void
    {
        // Calculate and set comment_count on the book
        $commentCount = Comment::where('book_id', $comment->book_id)->count();
        $comment->book()->update(['comment_count' => $commentCount]);

        // If comment has a parent, calculate and set reply_count on parent
        if ($comment->parent_comment_id) {
            $replyCount = Comment::where('parent_comment_id', $comment->parent_comment_id)->count();
            $comment->parent()->update(['reply_count' => $replyCount]);
        }
    }
}
