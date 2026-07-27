<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('listings', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->foreignUuid('user_id')->constrained('users')->restrictOnDelete();
            $table->foreignUuid('category_id')->constrained('categories')->restrictOnDelete();
            $table->foreignUuid('city_id')->constrained('cities')->restrictOnDelete();
            $table->foreignUuid('district_id')->nullable()->constrained('districts')->nullOnDelete();
            $table->string('title', 120);
            $table->string('slug')->unique();
            $table->text('description');
            $table->decimal('price', 12, 2)->nullable();
            $table->string('price_type');
            $table->char('currency', 3)->default('QAR');
            $table->string('condition')->nullable();
            $table->string('status')->default('draft');
            $table->string('rejection_reason')->nullable();
            $table->text('moderation_notes')->nullable();
            $table->decimal('latitude', 10, 7)->nullable();
            $table->decimal('longitude', 10, 7)->nullable();
            $table->json('contact_preferences')->nullable();
            $table->boolean('featured')->default(false);
            $table->timestamp('expires_at')->nullable();
            $table->timestamp('published_at')->nullable();
            $table->timestamp('sold_at')->nullable();
            $table->unsignedInteger('version')->default(1);
            $table->timestamps();
            $table->softDeletes();

            $table->index(['user_id', 'status']);
            $table->index(['category_id', 'status']);
            $table->index(['city_id', 'status']);
            $table->index(['status', 'published_at']);
            $table->index(['status', 'expires_at']);
            $table->index('published_at');
        });

        DB::statement('ALTER TABLE listings ADD CONSTRAINT listings_price_non_negative CHECK (price IS NULL OR price >= 0)');
    }

    public function down(): void
    {
        Schema::dropIfExists('listings');
    }
};
