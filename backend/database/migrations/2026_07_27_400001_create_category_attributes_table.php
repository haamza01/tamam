<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('category_attributes', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->foreignUuid('category_id')->constrained('categories')->cascadeOnDelete();
            $table->string('slug');
            $table->string('type');
            $table->boolean('required')->default(false);
            $table->boolean('searchable')->default(false);
            $table->boolean('filterable')->default(false);
            $table->unsignedInteger('sort_order')->default(0);
            $table->string('unit')->nullable();
            $table->decimal('min_value', 15, 4)->nullable();
            $table->decimal('max_value', 15, 4)->nullable();
            $table->json('validation_rules')->nullable();
            $table->timestamps();

            $table->unique(['category_id', 'slug']);
            $table->index(['category_id', 'sort_order']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('category_attributes');
    }
};
