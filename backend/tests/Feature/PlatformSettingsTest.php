<?php

namespace Tests\Feature;

use App\Application\Platform\PlatformSettingsService;
use Database\Seeders\PlatformSettingsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PlatformSettingsTest extends TestCase
{
    use RefreshDatabase;

    public function test_platform_settings_seeder_populates_defaults(): void
    {
        $this->seed(PlatformSettingsSeeder::class);

        /** @var PlatformSettingsService $settings */
        $settings = app(PlatformSettingsService::class);

        $this->assertSame(30, $settings->getInt('default_listing_duration_days'));
        $this->assertSame(3, $settings->getInt('category_max_depth'));
        $this->assertTrue($settings->getBool('remoderate_on_significant_edit'));
        $this->assertSame(
            ['title', 'description', 'category_id', 'price', 'price_type', 'images'],
            $settings->get('listing_significant_edit_fields'),
        );
    }

    public function test_platform_settings_can_be_updated(): void
    {
        /** @var PlatformSettingsService $settings */
        $settings = app(PlatformSettingsService::class);

        $settings->set('max_active_listings_user', 25, 'listings', 'Test override');

        $this->assertSame(25, $settings->getInt('max_active_listings_user'));
    }
}
