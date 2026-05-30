<?php

namespace App\Services;

use App\Models\File;
use App\Models\User;
use App\Models\Book;
use App\Enums\EntityType;
use App\Enums\FileType;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Storage;
use Exception;

class FileService
{
    public function attachToUser(User $user, $file, FileType $type): File
    {
        $directory = $this->getDefaultDirectory(EntityType::USER, $type);
        return $this->attach($user, $file, $type, $directory);
    }

    public function attachToBook(Book $book, $file, FileType $type): File
    {
        $directory = $this->getDefaultDirectory(EntityType::BOOK, $type);
        return $this->attach($book, $file, $type, $directory);
    }

    /**
     * Attach a file to a model (internal use).
     */
    protected function attach(Model $model, $file, FileType $type, string $directory): File
    {
        // Determine entity type based on model
        $entityType = $this->getEntityType($model);
        
        // Detach existing file of the same type
        $this->detach($model, $type);

        // Determine path
        if ($file instanceof \Illuminate\Http\UploadedFile) {
            $path = $file->store($directory);
        } else {
            // If $file is a string, assume it's already a path
            $path = $file;
        }

        // Create file record
        return File::create([
            'entity_id' => $model->id,
            'entity_type' => $entityType,
            'type' => $type,
            'path' => $path,
        ]);
    }

    /**
     * Detach a file from a model.
     */
    public function detach(Model $model, FileType $type): void
    {
        $entityType = $this->getEntityType($model);
        
        $file = File::where('entity_id', $model->id)
            ->where('entity_type', $entityType)
            ->where('type', $type)
            ->first();

        if ($file) {
            Storage::delete($file->path);
            $file->delete();
        }
    }

    /**
     * Get entity type from model.
     */
    protected function getEntityType(Model $model): EntityType
    {
        if ($model instanceof \App\Models\User) {
            return EntityType::USER;
        } elseif ($model instanceof \App\Models\Book) {
            return EntityType::BOOK;
        }

        throw new Exception('Unsupported model type');
    }

    /**
     * Get default directory for file storage.
     */
    protected function getDefaultDirectory(EntityType $entityType, FileType $fileType): string
    {
        return match ([$entityType, $fileType]) {
            [EntityType::USER, FileType::IMAGE] => 'users',
            [EntityType::BOOK, FileType::IMAGE] => 'books/covers',
            [EntityType::BOOK, FileType::DOCUMENT] => 'books/' . uniqid(),
            default => 'others',
        };
    }

    /**
     * Handle chunked file upload.
     * Returns true if all chunks are uploaded and file is processed, false otherwise.
     */
    public function uploadChunk(Model $model, $fileChunk, FileType $type, int $chunkIndex, int $totalChunks): bool
    {
        $entityType = $this->getEntityType($model);
        $tempPath = "{$entityType->value}_temp/{$model->id}";
        $chunkName = "chunk_{$chunkIndex}";
        
        // Store chunk on local disk
        Storage::disk('local')->putFileAs($tempPath, $fileChunk, $chunkName);
        
        // Check if all chunks are uploaded
        $chunks = Storage::disk('local')->files($tempPath);
        
        if (count($chunks) === $totalChunks) {
            // Get directory for final file
            $directory = $this->getDefaultDirectory($entityType, $type);
            
            // Merge all chunks
            $content = '';
            for ($i = 0; $i < $totalChunks; $i++) {
                $chunkPath = "{$tempPath}/chunk_{$i}";
                $content .= Storage::disk('local')->get($chunkPath);
                Storage::disk('local')->delete($chunkPath);
            }
            
            // Generate final file path
            $extension = $fileChunk->getClientOriginalExtension();
            $filename = uniqid() . '.' . $extension;
            $finalPath = $directory . '/' . $filename;
            
            // Store final file on default disk
            Storage::put($finalPath, $content);
            
            // Clean up temp directory
            Storage::disk('local')->deleteDirectory($tempPath);
            
            // Attach the file to the model
            $this->attach($model, $finalPath, $type, $directory);
            
            return true;
        }
        
        return false;
    }
}
