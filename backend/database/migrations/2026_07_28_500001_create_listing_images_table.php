<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('listing_images', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->foreignUuid('listing_id')->constrained('listings')->cascadeOnDelete();
            $table->string('original_object_key')->nullable();
            $table->string('processed_object_key')->nullable();
            $table->string('thumbnail_object_key')->nullable();
            $table->string('mime_type', 64)->nullable();
            $table->unsignedInteger('original_width')->nullable();
            $table->unsignedInteger('original_height')->nullable();
            $table->unsignedInteger('processed_width')->nullable();
            $table->unsignedInteger('processed_height')->nullable();
            $table->unsignedBigInteger('file_size')->nullable();
            $table->unsignedInteger('sort_order')->default(0);
            $table->string('status', 32);
            $table->string('processing_error_code', 64)->nullable();
            $table->timestamps();

            $table->index(['listing_id', 'status']);
            $table->index(['status', 'updated_at']);
            $table->unique(['listing_id', 'sort_order']);
        });

        DB::statement('ALTER TABLE listing_images ADD CONSTRAINT listing_images_sort_order_non_negative CHECK (sort_order >= 0)');
        DB::statement('ALTER TABLE listing_images ADD CONSTRAINT listing_images_file_size_non_negative CHECK (file_size IS NULL OR file_size >= 0)');
        DB::statement('ALTER TABLE listing_images ADD CONSTRAINT listing_images_original_width_non_negative CHECK (original_width IS NULL OR original_width >= 0)');
        DB::statement('ALTER TABLE listing_images ADD CONSTRAINT listing_images_original_height_non_negative CHECK (original_height IS NULL OR original_height >= 0)');
        DB::statement('ALTER TABLE listing_images ADD CONSTRAINT listing_images_processed_width_non_negative CHECK (processed_width IS NULL OR processed_width >= 0)');
        DB::statement('ALTER TABLE listing_images ADD CONSTRAINT listing_images_processed_height_non_negative CHECK (processed_height IS NULL OR processed_height >= 0)');
    }

    public function down(): void
    {
        Schema::dropIfExists('listing_images');
    }
};
