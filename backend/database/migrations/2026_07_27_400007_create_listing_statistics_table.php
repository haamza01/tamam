<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('listing_statistics', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->foreignUuid('listing_id')->unique()->constrained('listings')->cascadeOnDelete();
            $table->unsignedBigInteger('views_count')->default(0);
            $table->unsignedBigInteger('favorites_count')->default(0);
            $table->unsignedBigInteger('messages_count')->default(0);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('listing_statistics');
    }
};
