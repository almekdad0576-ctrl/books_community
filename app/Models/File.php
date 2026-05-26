<?php

namespace App\Models;

use App\Enums\EntityType;
use Illuminate\Database\Eloquent\Model;

class File extends Model
{
    protected $fillable = [
        'entity_id',
        'entity_type',
        'path',
    ];

    protected $casts = [
        'entity_type' => EntityType::class,
    ];
}
