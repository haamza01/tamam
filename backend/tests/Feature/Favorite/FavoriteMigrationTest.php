<?php

namespace Tests\Feature\Favorite;

use Illuminate\Support\Facades\DB;

class FavoriteMigrationTest extends FavoriteTestCase
{
    public function test_favorites_table_has_expected_schema(): void
    {
        if (DB::connection()->getDriverName() !== 'pgsql') {
            $this->markTestSkipped('PostgreSQL required for schema introspection.');
        }

        $this->assertTrue(
            collect(DB::select("SELECT 1 FROM information_schema.tables WHERE table_name = 'favorites'"))->isNotEmpty()
        );

        $columns = collect(DB::select("
            SELECT column_name, data_type
            FROM information_schema.columns
            WHERE table_name = 'favorites'
        "))->keyBy('column_name');

        foreach (['id', 'user_id', 'listing_id', 'created_at', 'updated_at'] as $column) {
            $this->assertTrue($columns->has($column), "Missing column {$column}");
        }

        $this->assertSame('uuid', $columns['id']->data_type);

        $this->assertTrue(
            collect(DB::select("
                SELECT 1 FROM pg_indexes
                WHERE tablename = 'favorites' AND indexname LIKE '%user_id_listing_id%'
            "))->isNotEmpty()
        );

        $foreignKeys = collect(DB::select("
            SELECT
                kcu.column_name,
                ccu.table_name AS foreign_table_name,
                rc.delete_rule
            FROM information_schema.table_constraints tc
            JOIN information_schema.key_column_usage kcu
                ON tc.constraint_name = kcu.constraint_name
            JOIN information_schema.referential_constraints rc
                ON rc.constraint_name = tc.constraint_name
            JOIN information_schema.constraint_column_usage ccu
                ON ccu.constraint_name = tc.constraint_name
            WHERE tc.table_name = 'favorites' AND tc.constraint_type = 'FOREIGN KEY'
        "))->keyBy('column_name');

        $this->assertSame('users', $foreignKeys['user_id']->foreign_table_name);
        $this->assertSame('CASCADE', $foreignKeys['user_id']->delete_rule);
        $this->assertSame('listings', $foreignKeys['listing_id']->foreign_table_name);
        $this->assertSame('CASCADE', $foreignKeys['listing_id']->delete_rule);
    }
}
