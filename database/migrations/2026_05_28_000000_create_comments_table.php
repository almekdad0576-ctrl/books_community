<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Step 1: Create the table structure cleanly
        Schema::create('comments', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('book_id')->constrained('books')->onDelete('cascade');
            $table->foreignUuid('user_id')->constrained('users')->onDelete('cascade');
            
            // Just define the column structure here
            $table->uuid('parent_comment_id')->nullable();
            
            $table->text('content');
            $table->unsignedBigInteger('reply_count')->default(0);
            $table->timestamps();
        });

        // Step 2: Now that the table completely exists, bind the self-referencing key
        Schema::table('comments', function (Blueprint $table) {
            $table->foreign('parent_comment_id')
                  ->references('id')
                  ->on('comments')
                  ->onDelete('cascade');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('comments');
    }
};