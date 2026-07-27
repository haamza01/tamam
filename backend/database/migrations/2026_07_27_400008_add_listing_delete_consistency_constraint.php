<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        DB::statement(<<<'SQL'
            ALTER TABLE listings ADD CONSTRAINT listings_deleted_consistency CHECK (
                (status = 'deleted' AND deleted_at IS NOT NULL)
                OR (status <> 'deleted' AND deleted_at IS NULL)
            )
        SQL);
    }

    public function down(): void
    {
        DB::statement('ALTER TABLE listings DROP CONSTRAINT IF EXISTS listings_deleted_consistency');
    }
};
