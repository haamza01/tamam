<?php

namespace Tests\Feature\Search;

use App\Models\Category;
use App\Models\City;
use App\Models\District;
use App\Models\Listing;
use App\Models\User;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;

class SearchApiTest extends SearchTestCase
{
    public function test_search_returns_published_listing_by_keyword(): void
    {
        $listing = $this->createPublishedListing(
            owner: $this->verifiedSeller(),
            moderator: $this->moderator(),
        );

        $this->asGuest()
            ->getJson('/api/v1/search?keyword='.urlencode('Reliable sedan'))
            ->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.pagination.total', 1)
            ->assertJsonPath('data.listings.0.id', $listing->id);
    }

    public function test_search_excludes_draft_listings(): void
    {
        $this->createPublishedListing();

        $owner = $this->verifiedSeller();
        $this->withApiToken($this->authenticate($owner))
            ->postJson('/api/v1/listings', $this->validListingPayload([
                'title' => 'Draft sedan listing hidden from search',
            ]))
            ->assertCreated();

        $this->asGuest()
            ->getJson('/api/v1/search?keyword=sedan')
            ->assertOk()
            ->assertJsonPath('data.pagination.total', 1);
    }

    public function test_search_excludes_expired_listings(): void
    {
        $listing = $this->createPublishedListing();
        $listing->update(['expires_at' => now()->subMinute()]);

        $this->asGuest()
            ->getJson('/api/v1/search?keyword='.urlencode('Reliable sedan'))
            ->assertOk()
            ->assertJsonPath('data.pagination.total', 0);
    }

    public function test_search_rejects_keyword_that_is_too_short(): void
    {
        $this->asGuest()
            ->getJson('/api/v1/search?keyword=a')
            ->assertUnprocessable()
            ->assertJsonPath('errors.keyword.0', 'search.keyword_too_short');
    }

    public function test_search_handles_arabic_keyword(): void
    {
        $owner = $this->verifiedSeller();
        $moderator = $this->moderator();
        $listingId = $this->withApiToken($this->authenticate($owner))
            ->postJson('/api/v1/listings', $this->validListingPayload([
                'title' => 'سيارة تويota للبيع في الدوحة',
                'description' => str_repeat('سيارة نظيفة جداً وبحالة ممتازة مع صيانة دورية كاملة. ', 2),
            ]))
            ->json('data.listing.id');

        $this->publishListing($listingId, $owner, $moderator);

        $this->asGuest()
            ->getJson('/api/v1/search?keyword='.urlencode('سيارة'))
            ->assertOk()
            ->assertJsonPath('data.pagination.total', 1);
    }

    public function test_search_handles_punctuation_without_sql_errors(): void
    {
        $this->createPublishedListing();

        $this->asGuest()
            ->getJson('/api/v1/search?keyword='.urlencode('sedan & car: "test"'))
            ->assertOk()
            ->assertJsonPath('success', true);
    }

    public function test_title_match_ranks_above_description_only_match(): void
    {
        if (DB::connection()->getDriverName() !== 'pgsql') {
            $this->markTestSkipped('PostgreSQL required for ranking tests.');
        }

        $owner = $this->verifiedSeller();
        $moderator = $this->moderator();
        $token = $this->authenticate($owner);

        $descriptionOnlyId = $this->withApiToken($token)
            ->postJson('/api/v1/listings', $this->validListingPayload([
                'title' => 'Generic vehicle listing number one',
                'description' => str_repeat('Contains unique platinum keyword in description only. ', 2),
            ]))
            ->json('data.listing.id');
        $this->publishListing($descriptionOnlyId, $owner, $moderator);

        $titleMatchId = $this->withApiToken($token)
            ->postJson('/api/v1/listings', $this->validListingPayload([
                'title' => 'Platinum sedan special edition',
                'description' => str_repeat('Standard vehicle description without the special keyword. ', 2),
            ]))
            ->json('data.listing.id');
        $this->publishListing($titleMatchId, $owner, $moderator);

        $response = $this->asGuest()
            ->getJson('/api/v1/search?keyword=platinum&sort=relevance')
            ->assertOk();

        $ids = collect($response->json('data.listings'))->pluck('id');
        $this->assertSame($titleMatchId, $ids->first());
    }

    public function test_category_filter_includes_descendants(): void
    {
        $vehicles = Category::query()->where('slug', 'vehicles')->firstOrFail();
        $listing = $this->createPublishedListing();

        $this->asGuest()
            ->getJson('/api/v1/search?category='.$vehicles->id)
            ->assertOk()
            ->assertJsonPath('data.pagination.total', 1)
            ->assertJsonPath('data.listings.0.id', $listing->id);
    }

    public function test_city_filter_works(): void
    {
        $city = City::query()->where('slug', 'doha')->firstOrFail();
        $listing = $this->createPublishedListing();

        $this->asGuest()
            ->getJson('/api/v1/search?city='.$city->id)
            ->assertOk()
            ->assertJsonPath('data.listings.0.id', $listing->id);
    }

    public function test_district_requires_city_and_rejects_mismatched_city(): void
    {
        $city = City::query()->where('slug', 'doha')->firstOrFail();
        $district = District::query()->where('city_id', $city->id)->where('is_active', true)->firstOrFail();
        $otherCity = City::query()->where('slug', 'al-wakrah')->where('is_active', true)->firstOrFail();

        $this->asGuest()
            ->getJson('/api/v1/search?district='.$district->id)
            ->assertUnprocessable()
            ->assertJsonPath('errors.district.0', 'search.district_requires_city');

        $this->asGuest()
            ->getJson('/api/v1/search?city='.$otherCity->id.'&district='.$district->id)
            ->assertUnprocessable()
            ->assertJsonPath('errors.district.0', 'search.invalid_location');
    }

    public function test_price_range_filter_and_invalid_range(): void
    {
        $this->createPublishedListing();

        $this->asGuest()
            ->getJson('/api/v1/search?price_min=40000&price_max=50000')
            ->assertOk()
            ->assertJsonPath('data.pagination.total', 1);

        $this->asGuest()
            ->getJson('/api/v1/search?price_min=60000&price_max=1000')
            ->assertUnprocessable()
            ->assertJsonPath('errors.price_max.0', 'search.invalid_price_range');
    }

    public function test_condition_filter_works(): void
    {
        $this->createPublishedListing();

        $this->asGuest()
            ->getJson('/api/v1/search?condition=used')
            ->assertOk()
            ->assertJsonPath('data.pagination.total', 1);

        $this->asGuest()
            ->getJson('/api/v1/search?condition=invalid')
            ->assertUnprocessable();
    }

    public function test_attribute_filter_requires_filterable_attribute(): void
    {
        $this->createPublishedListing();

        $this->asGuest()
            ->getJson('/api/v1/search?'.http_build_query(['attr' => ['brand' => 'Toyota']]))
            ->assertOk()
            ->assertJsonPath('data.pagination.total', 1);

        $this->asGuest()
            ->getJson('/api/v1/search?'.http_build_query(['attr' => ['mileage' => 55000]]))
            ->assertUnprocessable()
            ->assertJsonFragment(['search.invalid_attribute_filter']);
    }

    public function test_sorting_modes_and_null_price_ordering(): void
    {
        $owner = $this->verifiedSeller();
        $moderator = $this->moderator();
        $token = $this->authenticate($owner);
        $city = City::query()->where('slug', 'doha')->firstOrFail();

        $cheapId = $this->publishListingFromPayload($owner, $moderator, $token, [
            'title' => 'Budget sedan listing for sort test',
            'price' => 10000,
        ])->id;
        $expensiveId = $this->publishListingFromPayload($owner, $moderator, $token, [
            'title' => 'Premium sedan listing for sort test',
            'price' => 90000,
        ])->id;
        $nullPriceId = $this->publishListingFromPayload($owner, $moderator, $token, [
            'title' => 'Contact for price sedan listing sort test',
            'price_type' => 'contact_for_price',
            'price' => null,
        ])->id;

        $ascIds = collect($this->asGuest()->getJson('/api/v1/search?sort=price_asc&city='.$city->id)->json('data.listings'))->pluck('id');
        $this->assertSame([$cheapId, $expensiveId, $nullPriceId], $ascIds->take(3)->all());

        $descIds = collect($this->asGuest()->getJson('/api/v1/search?sort=price_desc&city='.$city->id)->json('data.listings'))->pluck('id');
        $this->assertSame([$expensiveId, $cheapId, $nullPriceId], $descIds->take(3)->all());
    }

    public function test_pagination_defaults_and_limit_cap(): void
    {
        $this->asGuest()
            ->getJson('/api/v1/search')
            ->assertOk()
            ->assertJsonPath('data.pagination.per_page', 20);

        $this->asGuest()
            ->getJson('/api/v1/search?limit=150')
            ->assertUnprocessable();
    }

    public function test_suggestions_require_minimum_prefix(): void
    {
        $this->asGuest()
            ->getJson('/api/v1/search/suggestions?q=a')
            ->assertUnprocessable()
            ->assertJsonPath('errors.q.0', 'search.prefix_too_short');
    }

    public function test_suggestions_return_public_titles_and_categories(): void
    {
        $this->createPublishedListing();

        $this->asGuest()
            ->getJson('/api/v1/search/suggestions?q=Rel')
            ->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonStructure(['data' => ['suggestions']]);

        $this->asGuest()
            ->getJson('/api/v1/search/suggestions?q=Sed')
            ->assertOk();
    }

    public function test_popular_searches_returns_seeded_terms(): void
    {
        Cache::forget((string) config('search.cache_keys.popular'));

        $this->asGuest()
            ->getJson('/api/v1/search/popular')
            ->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonStructure(['data' => ['popular' => [['term', 'rank']]]]);
    }

    public function test_search_rate_limiter_is_enforced(): void
    {
        $this->withoutToken()->withHeaders(['Accept' => 'application/json']);

        for ($i = 0; $i < (int) config('search.rate_limits.search_per_minute'); $i++) {
            $this->getJson('/api/v1/search')->assertOk();
        }

        $this->getJson('/api/v1/search')->assertStatus(429);
    }

    public function test_search_does_not_leak_internal_fields(): void
    {
        $this->createPublishedListing();

        $listing = $this->asGuest()
            ->getJson('/api/v1/search?keyword=sedan')
            ->assertOk()
            ->json('data.listings.0');

        $this->assertArrayNotHasKey('search_vector', $listing);
        $this->assertArrayNotHasKey('moderation_notes', $listing);
        $this->assertArrayNotHasKey('status', $listing);
    }

    public function test_empty_search_without_keyword_returns_newest_listings(): void
    {
        $owner = $this->verifiedSeller();
        $moderator = $this->moderator();
        $token = $this->authenticate($owner);

        $firstId = $this->withApiToken($token)
            ->postJson('/api/v1/listings', $this->validListingPayload([
                'title' => 'First unique listing for newest search sort',
            ]))
            ->assertCreated()
            ->json('data.listing.id');

        $first = $this->publishListing($firstId, $owner, $moderator);
        $first->update(['published_at' => '2026-07-01 10:00:00']);

        $secondId = $this->withApiToken($token)
            ->postJson('/api/v1/listings', $this->validListingPayload([
                'title' => 'Second unique listing for newest search sort',
            ]))
            ->assertCreated()
            ->json('data.listing.id');

        $second = $this->publishListing($secondId, $owner, $moderator);
        $second->update(['published_at' => '2026-07-02 10:00:00']);

        $ids = collect($this->asGuest()->getJson('/api/v1/search')->json('data.listings'))->pluck('id');

        $this->assertSame($second->id, $ids->first());
        $this->assertTrue($ids->contains($first->id));
    }

    /**
     * @param  array<string, mixed>  $overrides
     */
    private function publishListingFromPayload(User $owner, User $moderator, string $token, array $overrides): Listing
    {
        $listingId = $this->withApiToken($token)
            ->postJson('/api/v1/listings', $this->validListingPayload($overrides))
            ->json('data.listing.id');

        return $this->publishListing($listingId, $owner, $moderator);
    }

    private function moderator(): User
    {
        $moderator = User::factory()->create(['password' => Hash::make('Password123!'), 'phone_verified_at' => now()]);
        $moderator->assignRole('moderator');

        return $moderator;
    }
}
