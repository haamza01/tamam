<?php

namespace Tests\Unit\Storage;

use App\Infrastructure\Storage\PublicAssetsBucketProvisioner;
use Tests\TestCase;

class PublicAssetsBucketProvisionerTest extends TestCase
{
    public function test_provisioning_is_disabled_in_production_by_default(): void
    {
        config(['filesystems.disks.public_assets.driver' => 's3']);

        $this->app->detectEnvironment(fn () => 'production');

        $provisioner = new PublicAssetsBucketProvisioner;

        $this->assertFalse($provisioner->isEnabled());
    }

    public function test_provisioning_is_enabled_in_local_environment(): void
    {
        config(['filesystems.disks.public_assets.driver' => 's3']);

        $this->app->detectEnvironment(fn () => 'local');

        $provisioner = new PublicAssetsBucketProvisioner;

        $this->assertTrue($provisioner->isEnabled());
    }

    public function test_provisioning_skips_non_s3_drivers(): void
    {
        config(['filesystems.disks.public_assets.driver' => 'local']);

        $this->app->detectEnvironment(fn () => 'local');

        $provisioner = new PublicAssetsBucketProvisioner;

        $this->assertFalse($provisioner->isEnabled());
    }
}
