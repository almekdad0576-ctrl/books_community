<?php

namespace App\Http\Resources;

use App\Enums\EntityType;
use App\Enums\FileType;
use App\Models\File;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Support\Facades\Storage;

class UserResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        $imageFile = File::where('entity_id', $this->id)
            ->where('entity_type', EntityType::USER)
            ->where('type', FileType::IMAGE)
            ->first();

        return [
            'id' => $this->id,
            'name' => $this->name,
            'email' => $this->email,
            'image_url' => $imageFile ? Storage::disk('public')->url($imageFile->path) : null,
            'created_at' => $this->created_at,
            'updated_at' => $this->updated_at,
        ];
    }
}
