<?php

namespace Tests\Feature\Listing;

use App\Models\Category;

class ListingAttributeTest extends ListingTestCase
{
    public function test_submit_requires_category_attributes(): void
    {
        $user = $this->verifiedSeller();
        $token = $this->authenticate($user);

        $listingId = $this->withApiToken($token)
            ->postJson('/api/v1/listings', $this->validListingPayload([
                'attributes' => [
                    ['slug' => 'brand', 'value' => 'Toyota'],
                ],
            ]))
            ->json('data.listing.id');

        $response = $this->withApiToken($token)
            ->postJson("/api/v1/listings/{$listingId}/submit")
            ->assertUnprocessable();

        $this->assertSame('listing.attribute_required', $response->json('errors')['attributes.year'][0]);
    }

    public function test_create_rejects_invalid_dropdown_option(): void
    {
        $user = $this->verifiedSeller();
        $token = $this->authenticate($user);

        $response = $this->withApiToken($token)
            ->postJson('/api/v1/listings', $this->validListingPayload([
                'attributes' => [
                    ['slug' => 'brand', 'value' => 'UnknownBrand'],
                    ['slug' => 'year', 'value' => 2020],
                    ['slug' => 'mileage', 'value' => 55000],
                ],
            ]))
            ->assertUnprocessable();

        $this->assertSame('listing.attribute_invalid', $response->json('errors')['attributes.brand'][0]);
    }

    public function test_create_rejects_attribute_from_other_category(): void
    {
        $phones = Category::query()->where('slug', 'phones')->firstOrFail();
        $user = $this->verifiedSeller();
        $token = $this->authenticate($user);

        $response = $this->withApiToken($token)
            ->postJson('/api/v1/listings', $this->validListingPayload([
                'category_id' => $phones->id,
                'attributes' => [
                    ['slug' => 'brand', 'value' => 'Apple'],
                    ['slug' => 'storage', 'value' => '128GB'],
                    ['slug' => 'mileage', 'value' => 55000],
                ],
            ]))
            ->assertUnprocessable();

        $this->assertSame('listing.attribute_not_allowed', $response->json('errors')['attributes.mileage'][0]);
    }

    public function test_create_rejects_duplicate_attribute_slug(): void
    {
        $user = $this->verifiedSeller();
        $token = $this->authenticate($user);

        $response = $this->withApiToken($token)
            ->postJson('/api/v1/listings', $this->validListingPayload([
                'attributes' => [
                    ['slug' => 'brand', 'value' => 'Toyota'],
                    ['slug' => 'brand', 'value' => 'Nissan'],
                    ['slug' => 'year', 'value' => 2020],
                    ['slug' => 'mileage', 'value' => 55000],
                ],
            ]))
            ->assertUnprocessable();

        $this->assertSame('listing.attribute_duplicate', $response->json('errors')['attributes.brand'][0]);
    }
}
