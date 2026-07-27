<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('category_attribute_options', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->foreignUuid('category_attribute_id')->constrained('category_attributes')->cascadeOnDelete();
            $table->string('value');
            $table->unsignedInteger('sort_order')->default(0);
            $table->timestamps();

            $table->unique(['category_attribute_id', 'value']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('category_attribute_options');
    }
};
