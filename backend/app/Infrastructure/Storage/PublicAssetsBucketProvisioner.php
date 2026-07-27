<?php

namespace App\Infrastructure\Storage;

use Aws\S3\S3Client;

class PublicAssetsBucketProvisioner
{
    public function isEnabled(): bool
    {
        if ((string) config('filesystems.disks.public_assets.driver') !== 's3') {
            return false;
        }

        if (app()->environment('production') && ! $this->explicitlyEnabled()) {
            return false;
        }

        return app()->environment('local', 'testing') || $this->explicitlyEnabled();
    }

    public function ensure(): void
    {
        if (! $this->isEnabled()) {
            return;
        }

        /** @var array<string, mixed> $diskConfig */
        $diskConfig = config('filesystems.disks.public_assets');
        $bucket = (string) ($diskConfig['bucket'] ?? '');

        if ($bucket === '') {
            return;
        }

        $prefixes = array_values(array_filter([
            trim((string) config('media.avatar.path_prefix'), '/'),
            trim((string) config('media.listing.path_prefix'), '/'),
        ]));

        if ($prefixes === []) {
            return;
        }

        $client = new S3Client([
            'version' => 'latest',
            'region' => (string) ($diskConfig['region'] ?? 'us-east-1'),
            'endpoint' => (string) ($diskConfig['endpoint'] ?? ''),
            'use_path_style_endpoint' => (bool) ($diskConfig['use_path_style_endpoint'] ?? true),
            'credentials' => [
                'key' => (string) ($diskConfig['key'] ?? ''),
                'secret' => (string) ($diskConfig['secret'] ?? ''),
            ],
        ]);

        if (! $client->doesBucketExistV2($bucket, true)) {
            $client->createBucket(['Bucket' => $bucket]);
        }

        $resources = array_map(
            fn (string $prefix): string => "arn:aws:s3:::{$bucket}/{$prefix}/*",
            $prefixes,
        );

        $policy = [
            'Version' => '2012-10-17',
            'Statement' => [
                [
                    'Effect' => 'Allow',
                    'Principal' => ['AWS' => ['*']],
                    'Action' => ['s3:GetObject'],
                    'Resource' => $resources,
                ],
            ],
        ];

        $client->putBucketPolicy([
            'Bucket' => $bucket,
            'Policy' => json_encode($policy, JSON_THROW_ON_ERROR),
        ]);
    }

    private function explicitlyEnabled(): bool
    {
        return filter_var(env('STORAGE_PROVISION_BUCKETS', false), FILTER_VALIDATE_BOOL);
    }
}
