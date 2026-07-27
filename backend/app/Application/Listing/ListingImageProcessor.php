<?php

namespace App\Application\Listing;

use Intervention\Image\Drivers\Gd\Driver;
use Intervention\Image\ImageManager;

class ListingImageProcessor
{
    private ImageManager $manager;

    public function __construct()
    {
        $this->manager = new ImageManager(new Driver);
    }

    /**
     * @return array{
     *     processed: string,
     *     thumbnail: string,
     *     processed_width: int,
     *     processed_height: int,
     *     thumbnail_width: int,
     *     thumbnail_height: int
     * }
     */
    public function process(string $binary): array
    {
        $image = $this->manager->read($binary)->orient();
        $maxWidth = (int) config('media.listing.max_width');
        $thumbWidth = (int) config('media.listing.thumbnail_width');
        $quality = (int) config('media.listing.webp_quality');

        $processed = clone $image;
        if ($processed->width() > $maxWidth) {
            $processed->scaleDown(width: $maxWidth);
        }

        $thumbnail = clone $image;
        if ($thumbnail->width() > $thumbWidth) {
            $thumbnail->scaleDown(width: $thumbWidth);
        }

        return [
            'processed' => $processed->toWebp(quality: $quality)->toString(),
            'thumbnail' => $thumbnail->toWebp(quality: $quality)->toString(),
            'processed_width' => $processed->width(),
            'processed_height' => $processed->height(),
            'thumbnail_width' => $thumbnail->width(),
            'thumbnail_height' => $thumbnail->height(),
        ];
    }
}
