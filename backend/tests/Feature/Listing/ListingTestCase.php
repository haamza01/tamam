<?php

namespace Tests\Feature\Listing;

use App\Application\Listing\ListingStateMachine;
use App\Models\Category;
use App\Models\City;
use App\Models\Listing;
use App\Models\User;
use Database\Seeders\CategoryAttributeSeeder;
use Database\Seeders\CategorySeeder;
use Database\Seeders\CitySeeder;
use Database\Seeders\CountrySeeder;
use Database\Seeders\PlatformSettingsSeeder;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use PHPOpenSourceSaver\JWTAuth\Facades\JWTAuth;
use Tests\TestCase;

abstract class ListingTestCase extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed([
            RolePermissionSeeder::class,
            PlatformSettingsSeeder::class,
            CountrySeeder::class,
            CitySeeder::class,
            CategorySeeder::class,
            CategoryAttributeSeeder::class,
        ]);
    }

    protected function verifiedSeller(array $overrides = []): User
    {
        $user = User::factory()->create(array_merge([
            'password' => Hash::make('Password123!'),
            'phone_verified_at' => now(),
            'status' => 'active',
        ], $overrides));

        $user->assignRole('user');

        return $user;
    }

    protected function authenticate(User $user): string
    {
        return JWTAuth::fromUser($user);
    }

    protected function withApiToken(string $token): static
    {
        $this->resetSharedTestState();

        return $this->withToken($token);
    }

    protected function asGuest(): static
    {
        $this->resetSharedTestState();

        return $this->withoutToken()->withHeaders([
            'Accept' => 'application/json',
        ]);
    }

    protected function actingAsApi(User $user): static
    {
        auth()->forgetGuards();

        return $this->actingAs($user, 'api');
    }

    /**
     * @return array<string, mixed>
     */
    protected function validListingPayload(array $overrides = []): array
    {
        $category = Category::query()->where('slug', 'sedans')->firstOrFail();
        $city = City::query()->where('slug', 'doha')->firstOrFail();

        return array_merge([
            'category_id' => $category->id,
            'city_id' => $city->id,
            'title' => 'Reliable sedan for daily commute',
            'description' => str_repeat('Well maintained vehicle with full service history and clean interior. ', 2),
            'price_type' => 'fixed',
            'price' => 45000,
            'currency' => 'QAR',
            'condition' => 'used',
            'attributes' => [
                ['slug' => 'brand', 'value' => 'Toyota'],
                ['slug' => 'year', 'value' => 2020],
                ['slug' => 'mileage', 'value' => 55000],
            ],
        ], $overrides);
    }

    protected function publishListing(string $listingId, User $owner, User $moderator): Listing
    {
        $this->withApiToken($this->authenticate($owner))
            ->postJson("/api/v1/listings/{$listingId}/submit")
            ->assertOk();

        $listing = Listing::query()->findOrFail($listingId);
        app(ListingStateMachine::class)->approve($listing, $moderator);

        return $listing->fresh();
    }

    protected function createPublishedListing(?User $owner = null, ?User $moderator = null): Listing
    {
        $owner ??= $this->verifiedSeller();
        $moderator ??= User::factory()->create(['password' => Hash::make('Password123!'), 'phone_verified_at' => now()]);
        $moderator->assignRole('moderator');

        $listingId = $this->withApiToken($this->authenticate($owner))
            ->postJson('/api/v1/listings', $this->validListingPayload())
            ->json('data.listing.id');

        return $this->publishListing($listingId, $owner, $moderator);
    }
}
