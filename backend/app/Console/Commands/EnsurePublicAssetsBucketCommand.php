<?php

namespace App\Console\Commands;

use App\Infrastructure\Storage\PublicAssetsBucketProvisioner;
use Illuminate\Console\Command;

class EnsurePublicAssetsBucketCommand extends Command
{
    protected $signature = 'storage:ensure-public-bucket';

    protected $description = 'Ensure the local public assets bucket exists with scoped public-read policy';

    public function handle(PublicAssetsBucketProvisioner $provisioner): int
    {
        if (! $provisioner->isEnabled()) {
            $this->components->info('Public assets bucket provisioning is disabled for this environment.');

            return self::SUCCESS;
        }

        $provisioner->ensure();

        $this->components->info('Public assets bucket is ready.');

        return self::SUCCESS;
    }
}
