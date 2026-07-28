<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        if (DB::connection()->getDriverName() !== 'pgsql') {
            return;
        }

        DB::statement("
            CREATE INDEX listings_published_title_prefix_idx
            ON listings (lower(title) varchar_pattern_ops)
            WHERE status = 'published' AND deleted_at IS NULL
        ");

        DB::statement('
            CREATE INDEX category_translations_name_prefix_idx
            ON category_translations (lower(name) varchar_pattern_ops)
        ');
    }

    public function down(): void
    {
        if (DB::connection()->getDriverName() !== 'pgsql') {
            return;
        }

        DB::statement('DROP INDEX IF EXISTS listings_published_title_prefix_idx');
        DB::statement('DROP INDEX IF EXISTS category_translations_name_prefix_idx');
    }
};
