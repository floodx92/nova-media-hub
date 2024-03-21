<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Outl1ne\NovaMediaHub\MediaHub;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create(MediaHub::getTableName(), function (Blueprint $table) {
            // Primary keys
            $table->bigIncrements('id');
            $table->uuid('uuid')->nullable()->unique();

            // Core data
            $table->string('collection_name');

            // File info
            $table->string('disk');
            $table->string('file_name');
            $table->unsignedBigInteger('size');
            $table->string('mime_type')->nullable();

            // Save original file hash to check for duplicate uploads
            $table->string('original_file_hash');

            // Data
            $table->json('data');

            // Conversions
            $table->json('conversions');
            $table->string('conversions_disk')->nullable();

            $table->timestamps();
            $table->timestamp('optimized_at')->nullable();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists(MediaHub::getTableName());
    }
};
