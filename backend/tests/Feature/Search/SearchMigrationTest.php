<?php

namespace Tests\Feature\Search;

use Illuminate\Support\Facades\DB;

class SearchMigrationTest extends SearchTestCase
{
    public function test_search_vector_column_and_gin_index_exist_on_postgresql(): void
    {
        if (DB::connection()->getDriverName() !== 'pgsql') {
            $this->markTestSkipped('PostgreSQL required for FTS migration tests.');
        }

        $this->assertTrue(
            collect(DB::select("SELECT 1 FROM information_schema.columns WHERE table_name = 'listings' AND column_name = 'search_vector'"))->isNotEmpty()
        );

        $this->assertTrue(
            collect(DB::select("SELECT 1 FROM pg_indexes WHERE tablename = 'listings' AND indexname = 'listings_search_vector_idx'"))->isNotEmpty()
        );
    }

    public function test_search_vector_populates_for_existing_rows(): void
    {
        if (DB::connection()->getDriverName() !== 'pgsql') {
            $this->markTestSkipped('PostgreSQL required for FTS migration tests.');
        }

        $listing = $this->createPublishedListing();

        $vector = DB::selectOne('SELECT search_vector::text AS vector FROM listings WHERE id = ?', [$listing->id]);

        $this->assertNotNull($vector?->vector);
        $this->assertNotSame('', $vector->vector);
    }

    public function test_search_vector_updates_when_title_changes(): void
    {
        if (DB::connection()->getDriverName() !== 'pgsql') {
            $this->markTestSkipped('PostgreSQL required for FTS migration tests.');
        }

        $listing = $this->createPublishedListing();
        $before = DB::selectOne('SELECT search_vector::text AS vector FROM listings WHERE id = ?', [$listing->id]);

        $listing->update(['title' => 'Unique searchable title marker xyz']);

        $after = DB::selectOne('SELECT search_vector::text AS vector FROM listings WHERE id = ?', [$listing->id]);

        $this->assertNotSame($before->vector, $after->vector);
    }
}
