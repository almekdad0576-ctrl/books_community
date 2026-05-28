<?php

namespace App\Models;

use App\Enums\BookStatus;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Book extends Model
{
    use HasFactory, HasUuids;

    protected $fillable = [
        'title',
        'description',
        'author_id',
        'category_id',
        'views_num',
        'status',
        'comment_count',
    ];

    protected $casts = [
        'status' => BookStatus::class,
    ];

    /**
     * Get the category that owns the book.
     */
    public function category(): BelongsTo
    {
        return $this->belongsTo(Category::class);
    }

    /**
     * Get the author that owns the book.
     */
    public function author(): BelongsTo
    {
        return $this->belongsTo(User::class, 'author_id');
    }

    /**
     * The users that have viewed the book.
     */
    public function viewers(): BelongsToMany
    {
        return $this->belongsToMany(User::class, 'book_views', 'book_id', 'user_id')
                    ->withTimestamps();
    }

    /**
     * Get the comments for the book.
     */
    public function comments(): HasMany
    {
        return $this->hasMany(Comment::class);
    }
}
