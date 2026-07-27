<?php

namespace App\Application\Storage;

use Illuminate\Support\Facades\Storage;

class PublicAssetUrlResolver
{
    public function resolve(?string $objectKey): ?string
    {
        if ($objectKey === null || $objectKey === '') {
            return null;
        }

        if (str_starts_with($objectKey, 'http://') || str_starts_with($objectKey, 'https://')) {
            return $objectKey;
        }

        $normalizedPath = str_replace('\\', '/', ltrim($objectKey, '/'));
        $baseUrl = rtrim((string) config('filesystems.disks.public_assets.url', ''), '/');

        if ($baseUrl !== '') {
            return $baseUrl.'/'.$normalizedPath;
        }

        return str_replace('\\', '/', Storage::disk($this->disk())->url($normalizedPath));
    }

    private function disk(): string
    {
        return (string) config('media.listing.disk', config('media.avatar.disk'));
    }
}
