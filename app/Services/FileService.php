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
    protected array $directories;

    public function __construct()
    {
        $this->directories = [
            EntityType::USER->value => [
                FileType::IMAGE->value => 'users',
            ],
            EntityType::BOOK->value => [
                FileType::IMAGE->value => 'books/covers',
                FileType::DOCUMENT->value => 'books/',
            ],
        ];
    }

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
     * Ensure a directory exists on the default disk.
     *
     * @throws Exception If directory cannot be created
     */
    protected function ensureDirectoryExists(string $directory): void
    {
        if (!Storage::exists($directory)) {
            if (!Storage::makeDirectory($directory)) {
                throw new Exception("Failed to create directory: {$directory}");
            }
        }
    }

    /**
     * Attach a file to a model (internal use).
     */
    protected function attach(Model $model, $file, FileType $type, string $directory): File
    {
        // Determine entity type based on model
        $entityType = $this->getEntityType($model);
        
        // Ensure directory exists
        $this->ensureDirectoryExists($directory);
        
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
        $entityValue = $entityType->value;
        $fileValue = $fileType->value;
        
        if (isset($this->directories[$entityValue][$fileValue])) {
            $dir = $this->directories[$entityValue][$fileValue];
            if ($entityType === EntityType::BOOK && $fileType === FileType::DOCUMENT) {
                return $dir . uniqid();
            }
            return $dir;
        }
        return 'others';
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
        
        // 1. LOG INCOMING FILE: Verify PHP actually received a valid file chunk
        info("--- CHUNK UPLOAD STARTED ---", [
            'chunk_index' => $chunkIndex,
            'total_expected' => $totalChunks,
            'is_valid_file' => $fileChunk ? $fileChunk->isValid() : false,
            'chunk_size_bytes' => $fileChunk ? $fileChunk->getSize() : 0,
            'upload_error' => $fileChunk ? $fileChunk->getError() : 'No file object received',
        ]);

        if (!$fileChunk || !$fileChunk->isValid()) {
            info("ERROR: Invalid file chunk rejected by PHP.", [
                'error_message' => $fileChunk ? $fileChunk->getErrorMessage() : 'Null chunk'
            ]);
            return false;
        }

        // Store chunk on local disk
        Storage::disk('local')->putFileAs($tempPath, $fileChunk, $chunkName);
        info("Chunk successfully written to disk", ['path' => "$tempPath/$chunkName"]);
        
        // Check if all chunks are uploaded
        $chunks = Storage::disk('local')->files($tempPath);
        $validChunks = array_filter($chunks, fn($c) => !str_contains($c, 'merged.tmp'));
        $currentChunkCount = count($validChunks);
        
        info("Checking merge condition", [
            'current_valid_chunks' => $currentChunkCount,
            'total_expected_chunks' => $totalChunks
        ]);

        if ($currentChunkCount === $totalChunks) {
            info("All chunks received! Starting stream merge process.");
            
            $directory = $this->getDefaultDirectory($entityType, $type);
            $this->ensureDirectoryExists($directory);
            
            $mergedTempPath = "{$tempPath}/merged.tmp";
            $mergedTempAbsolute = Storage::disk('local')->path($mergedTempPath);
            $mergeStream = fopen($mergedTempAbsolute, 'a+');
            
            if (!$mergeStream) {
                info("CRITICAL ERROR: PHP failed to open the merge stream.", ['path' => $mergedTempAbsolute]);
                return false;
            }

            // Merge all chunks
            for ($i = 1; $i <= $totalChunks; $i++) {
                $chunkPath = "{$tempPath}/chunk_{$i}";
                
                if (!Storage::disk('local')->exists($chunkPath)) {
                    info("ERROR: Missing chunk during merge loop.", ['missing' => $chunkPath]);
                    fclose($mergeStream);
                    return false; 
                }

                $chunkAbsolute = Storage::disk('local')->path($chunkPath);
                $chunkStream = fopen($chunkAbsolute, 'r');
                
                if (!$chunkStream) {
                    info("ERROR: Could not read chunk.", ['chunk' => $chunkAbsolute]);
                    fclose($mergeStream);
                    return false;
                }

                stream_copy_to_stream($chunkStream, $mergeStream);
                fclose($chunkStream);
                info("Merged chunk $i.");
            }
            
            fclose($mergeStream);
            info("Merge complete.", ['merged_file_size_bytes' => filesize($mergedTempAbsolute)]);
            
            // Generate final file path
            // Fallback to 'bin' in case chunking strips the original extension
            $extension = $fileChunk->getClientOriginalExtension() ?: 'bin';
            $filename = uniqid() . '.' . $extension;
            $finalPath = $directory . '/' . $filename;
            
            // Store final file on default disk
            $finalUploadStream = fopen($mergedTempAbsolute, 'r');
            Storage::put($finalPath, $finalUploadStream);
            fclose($finalUploadStream);
            
            info("Final file saved to main storage.", [
                'final_path' => $finalPath,
                'exists' => Storage::exists($finalPath),
                'final_size' => Storage::size($finalPath)
            ]);

            // Clean up temp directory
            Storage::disk('local')->deleteDirectory($tempPath);
            info("Temp directory wiped.", ['deleted' => $tempPath]);
            
            // Attach the file to the model
            $this->attach($model, $finalPath, $type, $directory);
            
            info("--- UPLOAD AND ATTACH COMPLETE ---");
            return true;
        }
        
        info("Chunk $chunkIndex processed successfully. Waiting for remaining chunks.");
        return false;
    }
}
