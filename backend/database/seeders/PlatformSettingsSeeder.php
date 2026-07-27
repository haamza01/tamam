<?php

namespace Database\Seeders;

use App\Application\Platform\PlatformSettingsService;
use Illuminate\Database\Seeder;

class PlatformSettingsSeeder extends Seeder
{
    public function run(): void
    {
        /** @var PlatformSettingsService $settings */
        $settings = app(PlatformSettingsService::class);

        $defaults = [
            'default_listing_duration_days' => ['value' => 30, 'group' => 'listings', 'description' => 'Default published listing duration in days.'],
            'require_manual_moderation_for_new_users' => ['value' => true, 'group' => 'moderation', 'description' => 'Require manual review for listings from non-trusted users.'],
            'auto_publish_for_trusted_users' => ['value' => false, 'group' => 'moderation', 'description' => 'Allow trusted sellers to auto-publish when moderation rules allow.'],
            'max_active_listings_user' => ['value' => 10, 'group' => 'listings', 'description' => 'Maximum active listings per individual user.'],
            'category_max_depth' => ['value' => 3, 'group' => 'categories', 'description' => 'Maximum category tree depth.'],
            'remoderate_on_significant_edit' => ['value' => true, 'group' => 'moderation', 'description' => 'Return published listings to moderation after significant edits.'],
            'listing_significant_edit_fields' => [
                'value' => ['title', 'description', 'category_id', 'price', 'price_type', 'images'],
                'group' => 'moderation',
                'description' => 'Listing fields that trigger re-moderation when changed.',
            ],
            'message_email_inactivity_minutes' => ['value' => 15, 'group' => 'notifications', 'description' => 'Minutes of inactivity before message email notifications are sent.'],
            'message_email_throttle_minutes' => ['value' => 30, 'group' => 'notifications', 'description' => 'Minimum minutes between message email notifications per conversation.'],
        ];

        foreach ($defaults as $key => $definition) {
            $settings->set(
                $key,
                $definition['value'],
                $definition['group'],
                $definition['description'],
            );
        }
    }
}
