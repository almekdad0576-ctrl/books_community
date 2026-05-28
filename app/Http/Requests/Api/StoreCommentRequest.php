<?php

namespace App\Http\Requests\Api;

use Illuminate\Foundation\Http\FormRequest;

class StoreCommentRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'book_id' => 'required|uuid|exists:books,id',
            'parent_comment_id' => 'nullable|uuid|exists:comments,id',
            'content' => 'required|string',
        ];
    }
}
