<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class CommentResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'book_id' => $this->book_id,
            'user_id' => $this->user_id,
            'user_name' => $this->user?->name,
            'parent_comment_id' => $this->parent_comment_id,
            'content' => $this->content,
            'reply_count' => $this->reply_count,
            'replies' => CommentResource::collection($this->whenLoaded('replies')),
            'created_at' => $this->created_at,
            'updated_at' => $this->updated_at,
        ];
    }
}
