<?php

namespace Tests\Feature\Search;

use App\Application\Listing\ListingStateMachine;
use App\Models\City;
use App\Models\District;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;

class SearchHardeningTest extends SearchTestCase
{
    /**
     * @return array<string, string>
     */
    public static function tsquerySpecialCharacterProvider(): array
    {
        return [
            'colon' => ['sedan: luxury'],
            'ampersand' => ['sedan & car'],
            'pipe' => ['sedan | car'],
            'exclamation' => ['sedan! fast'],
            'parentheses' => ['sedan (2020)'],
            'quotes' => ['sedan "fast"'],
            'apostrophe' => ["sedan's best"],
            'backslash' => ['sedan\\car'],
            'tsquery operators' => ['sedan & | ! ( ) : *'],
            'arabic and punctuation' => ['سيارة: & (ممتازة)'],
        ];
    }

    /**
     * @dataProvider tsquerySpecialCharacterProvider
     */
    public function test_search_never_errors_on_special_characters(string $keyword): void
    {
        $this->createPublishedListing();

        $this->asGuest()
            ->getJson('/api/v1/search?keyword='.urlencode($keyword))
            ->assertOk()
            ->assertJsonPath('success', true);
    }

    public function test_search_vector_handles_empty_description(): void
    {
        if (DB::connection()->getDriverName() !== 'pgsql') {
            $this->markTestSkipped('PostgreSQL required.');
        }

        $listing = $this->createPublishedListing();
        $listing->update(['description' => '']);

        $vector = DB::selectOne('SELECT search_vector::text AS vector FROM listings WHERE id = ?', [$listing->id]);
        $this->assertNotNull($vector?->vector);

        $this->asGuest()
            ->getJson('/api/v1/search?keyword='.urlencode('Reliable sedan'))
            ->assertOk()
            ->assertJsonPath('data.pagination.total', 1);
    }

    public function test_search_vector_updates_when_description_changes(): void
    {
        if (DB::connection()->getDriverName() !== 'pgsql') {
            $this->markTestSkipped('PostgreSQL required.');
        }

        $listing = $this->createPublishedListing();
        $before = DB::selectOne('SELECT search_vector::text AS vector FROM listings WHERE id = ?', [$listing->id]);

        $listing->update(['description' => str_repeat('Updated unique description token xyz. ', 3)]);

        $after = DB::selectOne('SELECT search_vector::text AS vector FROM listings WHERE id = ?', [$listing->id]);
        $this->assertNotSame($before->vector, $after->vector);

        $this->asGuest()
            ->getJson('/api/v1/search?keyword=xyz')
            ->assertOk()
            ->assertJsonPath('data.pagination.total', 1);
    }

    public function test_description_only_keyword_match_works(): void
    {
        $owner = $this->verifiedSeller();
        $moderator = $this->moderator();
        $token = $this->authenticate($owner);

        $listingId = $this->withApiToken($token)
            ->postJson('/api/v1/listings', $this->validListingPayload([
                'title' => 'Generic vehicle listing alpha',
                'description' => str_repeat('Contains uniquedesc token omegazap only here. ', 2),
            ]))
            ->assertCreated()
            ->json('data.listing.id');

        $this->publishListing($listingId, $owner, $moderator);

        $this->asGuest()
            ->getJson('/api/v1/search?keyword=omegazap')
            ->assertOk()
            ->assertJsonPath('data.pagination.total', 1);
    }

    public function test_inactive_district_listing_is_excluded_from_search(): void
    {
        $city = City::query()->where('slug', 'doha')->firstOrFail();
        $district = District::query()->where('city_id', $city->id)->where('is_active', true)->firstOrFail();

        $owner = $this->verifiedSeller();
        $moderator = $this->moderator();
        $token = $this->authenticate($owner);

        $listingId = $this->withApiToken($token)
            ->postJson('/api/v1/listings', $this->validListingPayload([
                'title' => 'District scoped sedan listing search',
                'district_id' => $district->id,
            ]))
            ->assertCreated()
            ->json('data.listing.id');

        $this->publishListing($listingId, $owner, $moderator);

        $district->update(['is_active' => false]);

        $this->asGuest()
            ->getJson('/api/v1/search?keyword=district')
            ->assertOk()
            ->assertJsonPath('data.pagination.total', 0);
    }

    public function test_multiple_attribute_filters_use_and_semantics(): void
    {
        $owner = $this->verifiedSeller();
        $moderator = $this->moderator();
        $token = $this->authenticate($owner);

        $matchId = $this->withApiToken($token)
            ->postJson('/api/v1/listings', $this->validListingPayload([
                'title' => 'Matching brand and year sedan listing',
                'attributes' => [
                    ['slug' => 'brand', 'value' => 'Toyota'],
                    ['slug' => 'year', 'value' => 2020],
                    ['slug' => 'mileage', 'value' => 55000],
                ],
            ]))
            ->assertCreated()
            ->json('data.listing.id');
        $this->publishListing($matchId, $owner, $moderator);

        $nonMatchId = $this->withApiToken($token)
            ->postJson('/api/v1/listings', $this->validListingPayload([
                'title' => 'Matching brand wrong year sedan listing',
                'attributes' => [
                    ['slug' => 'brand', 'value' => 'Toyota'],
                    ['slug' => 'year', 'value' => 2018],
                    ['slug' => 'mileage', 'value' => 60000],
                ],
            ]))
            ->assertCreated()
            ->json('data.listing.id');
        $this->publishListing($nonMatchId, $owner, $moderator);

        $this->asGuest()
            ->getJson('/api/v1/search?'.http_build_query(['attr' => ['brand' => 'Toyota', 'year' => 2020]]))
            ->assertOk()
            ->assertJsonPath('data.pagination.total', 1)
            ->assertJsonPath('data.listings.0.id', $matchId);
    }

    public function test_invalid_dropdown_attribute_value_is_rejected(): void
    {
        $this->createPublishedListing();

        $this->asGuest()
            ->getJson('/api/v1/search?'.http_build_query(['attr' => ['brand' => 'NotARealBrand']]))
            ->assertUnprocessable()
            ->assertJsonFragment(['search.invalid_attribute_filter']);
    }

    public function test_suggestions_do_not_return_unpublished_listing_titles(): void
    {
        $owner = $this->verifiedSeller();
        $token = $this->authenticate($owner);

        $this->withApiToken($token)
            ->postJson('/api/v1/listings', $this->validListingPayload([
                'title' => 'Secret draft suggestion title unique',
            ]))
            ->assertCreated();

        $values = collect($this->asGuest()
            ->getJson('/api/v1/search/suggestions?q=Secret')
            ->json('data.suggestions'))
            ->pluck('value');

        $this->assertFalse($values->contains('Secret draft suggestion title unique'));
    }

    public function test_suggestions_do_not_return_title_after_listing_is_archived(): void
    {
        $owner = $this->verifiedSeller();
        $moderator = $this->moderator();
        $token = $this->authenticate($owner);

        $listingId = $this->withApiToken($token)
            ->postJson('/api/v1/listings', $this->validListingPayload([
                'title' => 'Archived suggestion title unique marker',
            ]))
            ->assertCreated()
            ->json('data.listing.id');

        $listing = $this->publishListing($listingId, $owner, $moderator);

        $this->asGuest()
            ->getJson('/api/v1/search/suggestions?q=Archived')
            ->assertOk()
            ->assertJsonFragment(['value' => 'Archived suggestion title unique marker']);

        app(ListingStateMachine::class)->archive($listing->fresh(), $owner);

        $values = collect($this->asGuest()
            ->getJson('/api/v1/search/suggestions?q=Archived')
            ->json('data.suggestions'))
            ->pluck('value');

        $this->assertFalse($values->contains('Archived suggestion title unique marker'));
    }

    public function test_suggestions_do_not_return_title_after_title_change(): void
    {
        $listing = $this->createPublishedListing(
            owner: $this->verifiedSeller(),
            moderator: $this->moderator(),
        );

        $this->asGuest()
            ->getJson('/api/v1/search/suggestions?q=Reliable')
            ->assertOk()
            ->assertJsonFragment(['value' => 'Reliable sedan for daily commute']);

        $listing->update(['title' => 'Renamed sedan listing title completely']);

        $values = collect($this->asGuest()
            ->getJson('/api/v1/search/suggestions?q=Reliable')
            ->json('data.suggestions'))
            ->pluck('value');

        $this->assertFalse($values->contains('Reliable sedan for daily commute'));
    }

    public function test_documented_parameter_aliases_are_accepted(): void
    {
        $listing = $this->createPublishedListing();
        $city = City::query()->where('slug', 'doha')->firstOrFail();
        $category = $listing->category;

        $this->asGuest()
            ->getJson('/api/v1/search?'.http_build_query([
                'category_id' => $category->id,
                'city_id' => $city->id,
                'per_page' => 10,
            ]))
            ->assertOk()
            ->assertJsonPath('data.pagination.per_page', 10)
            ->assertJsonPath('data.pagination.total', 1);
    }

    public function test_undocumented_attributes_alias_is_not_accepted(): void
    {
        $owner = $this->verifiedSeller();
        $moderator = $this->moderator();
        $token = $this->authenticate($owner);

        $toyotaId = $this->withApiToken($token)
            ->postJson('/api/v1/listings', $this->validListingPayload([
                'title' => 'Toyota attribute alias filter test listing',
                'attributes' => [
                    ['slug' => 'brand', 'value' => 'Toyota'],
                    ['slug' => 'year', 'value' => 2020],
                    ['slug' => 'mileage', 'value' => 55000],
                ],
            ]))
            ->assertCreated()
            ->json('data.listing.id');
        $this->publishListing($toyotaId, $owner, $moderator);

        $nissanId = $this->withApiToken($token)
            ->postJson('/api/v1/listings', $this->validListingPayload([
                'title' => 'Nissan attribute alias filter test listing',
                'attributes' => [
                    ['slug' => 'brand', 'value' => 'Nissan'],
                    ['slug' => 'year', 'value' => 2020],
                    ['slug' => 'mileage', 'value' => 56000],
                ],
            ]))
            ->assertCreated()
            ->json('data.listing.id');
        $this->publishListing($nissanId, $owner, $moderator);

        $this->asGuest()
            ->getJson('/api/v1/search?'.http_build_query(['attr' => ['brand' => 'Toyota']]))
            ->assertOk()
            ->assertJsonPath('data.pagination.total', 1);

        $this->asGuest()
            ->getJson('/api/v1/search?'.http_build_query(['attributes' => ['brand' => 'Toyota']]))
            ->assertOk()
            ->assertJsonPath('data.pagination.total', 2);
    }

    private function moderator(): User
    {
        $moderator = User::factory()->create(['password' => Hash::make('Password123!'), 'phone_verified_at' => now()]);
        $moderator->assignRole('moderator');

        return $moderator;
    }
}
