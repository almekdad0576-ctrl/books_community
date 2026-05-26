<?php

namespace App\Http\Resources;

use App\Enums\EntityType;
use App\Enums\FileType;
use App\Models\File;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Support\Facades\Storage;

class BookResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        $coverFile = File::where('entity_id', $this->id)
            ->where('entity_type', EntityType::BOOK)
            ->where('type', FileType::IMAGE)
            ->first();

        $bookFile = File::where('entity_id', $this->id)
            ->where('entity_type', EntityType::BOOK)
            ->where('type', FileType::DOCUMENT)
            ->first();

        return [
            'id' => $this->id,
            'title' => $this->title,
            'description' => $this->description,
            'author_id' => $this->author_id,
            'status' => $this->status,
            'views_num' => $this->views_num,
            'cover_url' => $coverFile ? Storage::disk('public')->url($coverFile->path) : null,
            'book_file_url' => $bookFile ? Storage::disk('public')->url($bookFile->path) : null,
            'created_at' => $this->created_at,
            'updated_at' => $this->updated_at,
        ];
    }
}
