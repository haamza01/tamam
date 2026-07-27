<?php

namespace App\Application\Listing;

use App\Models\Listing;
use App\Models\ListingImage;
use Illuminate\Support\Facades\Storage;

class ListingImageStorageService
{
    public function sourceObjectKey(Listing $listing, ListingImage $image): string
    {
        return $this->basePath($listing, $image).'/source';
    }

    public function processedObjectKey(Listing $listing, ListingImage $image): string
    {
        return $this->basePath($listing, $image).'/original.webp';
    }

    public function thumbnailObjectKey(Listing $listing, ListingImage $image): string
    {
        return $this->basePath($listing, $image).'/thumb.webp';
    }

    public function storeSource(Listing $listing, ListingImage $image, string $contents, string $mimeType): string
    {
        $objectKey = $this->sourceObjectKey($listing, $image);

        $stored = Storage::disk($this->sourceDisk())->put($objectKey, $contents, [
            'ContentType' => $mimeType,
        ]);

        if ($stored !== true) {
            throw new \RuntimeException('Unable to store listing image source object.');
        }

        return $objectKey;
    }

    public function storeProcessed(string $objectKey, string $contents): void
    {
        $stored = Storage::disk($this->publicDisk())->put($objectKey, $contents, [
            'visibility' => 'public',
            'ContentType' => 'image/webp',
        ]);

        if ($stored !== true) {
            throw new \RuntimeException('Unable to store processed listing image.');
        }
    }

    /**
     * @param  list<string|null>  $objectKeys
     */
    public function deleteObjects(array $objectKeys): void
    {
        $keys = array_values(array_filter(array_unique(array_map(
            fn (?string $key): ?string => $this->normalizeKey($key),
            $objectKeys,
        ))));

        if ($keys === []) {
            return;
        }

        $publicKeys = array_values(array_filter(
            $keys,
            fn (string $key): bool => ! str_ends_with($key, '/source'),
        ));

        if ($publicKeys !== []) {
            Storage::disk($this->publicDisk())->delete($publicKeys);
        }

        $sourceKeys = array_values(array_filter($keys, fn (string $key): bool => str_ends_with($key, '/source')));

        if ($sourceKeys !== []) {
            Storage::disk($this->sourceDisk())->delete($sourceKeys);
        }
    }

    public function readSource(?string $objectKey): ?string
    {
        $normalized = $this->normalizeKey($objectKey);

        if ($normalized === null || ! Storage::disk($this->sourceDisk())->exists($normalized)) {
            return null;
        }

        return Storage::disk($this->sourceDisk())->get($normalized);
    }

    public function isOwnedObjectKey(Listing $listing, ?string $objectKey): bool
    {
        $normalized = $this->normalizeKey($objectKey);

        if ($normalized === null || str_contains($normalized, '..')) {
            return false;
        }

        $prefix = trim((string) config('media.listing.path_prefix'), '/').'/'.$listing->id.'/';

        return str_starts_with($normalized, $prefix);
    }

    private function basePath(Listing $listing, ListingImage $image): string
    {
        $prefix = trim((string) config('media.listing.path_prefix'), '/');

        return $prefix.'/'.$listing->id.'/'.$image->id;
    }

    private function normalizeKey(?string $objectKey): ?string
    {
        if ($objectKey === null || $objectKey === '') {
            return null;
        }

        if (str_starts_with($objectKey, 'http://') || str_starts_with($objectKey, 'https://')) {
            return null;
        }

        return str_replace('\\', '/', ltrim($objectKey, '/'));
    }

    private function sourceDisk(): string
    {
        return (string) config('media.listing.source_disk', 'local');
    }

    private function publicDisk(): string
    {
        return (string) config('media.listing.disk');
    }
}
