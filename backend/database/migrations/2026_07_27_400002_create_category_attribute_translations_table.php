<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('category_attribute_translations', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->foreignUuid('category_attribute_id')->constrained('category_attributes')->cascadeOnDelete();
            $table->string('locale', 5);
            $table->string('name');
            $table->timestamps();

            $table->unique(['category_attribute_id', 'locale'], 'cat_attr_trans_locale_unique');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('category_attribute_translations');
    }
};
