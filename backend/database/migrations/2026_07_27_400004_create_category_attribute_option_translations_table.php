<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('category_attribute_option_translations', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->foreignUuid('category_attribute_option_id')->constrained('category_attribute_options')->cascadeOnDelete();
            $table->string('locale', 5);
            $table->string('label');
            $table->timestamps();

            $table->unique(['category_attribute_option_id', 'locale'], 'cat_attr_opt_trans_locale_unique');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('category_attribute_option_translations');
    }
};
