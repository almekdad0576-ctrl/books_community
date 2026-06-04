<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\StoreCommentRequest;
use App\Http\Requests\Api\UpdateCommentRequest;
use App\Http\Resources\CommentResource;
use App\Models\Comment;
use App\Services\CommentService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class CommentController extends Controller
{
    protected CommentService $commentService;

    public function __construct(CommentService $commentService)
    {
        $this->commentService = $commentService;
    }

    /**
     * Display a listing of comments.
     * @unauthenticated
     */
    public function index(Request $request)
    {
        $bookId = $request->query('book_id');
        $comments = $this->commentService->getComments(
            $bookId,
            $request->query('pageSize'),
            $request->query('pageNumber')
        );
        return $this->json_response(true, 200, 'Comments retrieved successfully', CommentResource::collection($comments));
    }

    /**
     * Display the specified comment.
     * @unauthenticated
     */
    public function show(string $id)
    {
        $comment = $this->commentService->getComment($id);
        return $this->json_response(true, 200, 'Comment retrieved successfully', new CommentResource($comment));
    }

    /**
     * Store a newly created comment in storage.
     */
    public function store(StoreCommentRequest $request)
    {
        $comment = $this->commentService->createComment($request->validated(), Auth::id());
        return $this->json_response(true, 201, 'Comment created successfully', new CommentResource($comment));
    }

    /**
     * Update the specified comment in storage.
     */
    public function update(UpdateCommentRequest $request, Comment $comment)
    {
        $this->authorize('update', $comment);
        $comment = $this->commentService->updateComment($comment, $request->validated());
        return $this->json_response(true, 200, 'Comment updated successfully', new CommentResource($comment));
    }

    /**
     * Remove the specified comment from storage.
     */
    public function destroy(Comment $comment): JsonResponse
    {
        $this->authorize('delete', $comment);
        $this->commentService->deleteComment($comment);
        return $this->json_response(true, 200, 'Comment deleted successfully');
    }
}
