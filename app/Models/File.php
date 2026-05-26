<?php

namespace App\Models;

use App\Enums\EntityType;
use App\Enums\FileType;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Concerns\HasUuids;

class File extends Model
{
    use HasUuids;
    protected $fillable = [
        'entity_id',
        'entity_type',
        'type',
        'path',
    ];

    protected $casts = [
        'entity_type' => EntityType::class,
        'type' => FileType::class,
    ];
}
