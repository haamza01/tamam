<?php

namespace App\Console\Commands;

use App\Application\Listing\ListingImageStorageService;
use App\Domain\Listing\Enums\ListingImageStatus;
use App\Models\ListingImage;
use Illuminate\Console\Command;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class CleanupOrphanListingImagesCommand extends Command
{
    protected $signature = 'listings:cleanup-orphan-images {--dry-run : Report orphaned objects without deleting them}';

    protected $description = 'Remove stale listing image source objects without database rows';

    private const SOURCE_MIN_AGE_HOURS = 24;

    private const STALE_ROW_AGE_HOURS = 24;

    private const STALE_PROCESSING_MINUTES = 10;

    public function handle(ListingImageStorageService $storage): int
    {
        $dryRun = (bool) $this->option('dry-run');
        $sourceDisk = (string) config('media.listing.source_disk', 'local');
        $prefix = trim((string) config('media.listing.path_prefix'), '/');
        $sourceThreshold = Carbon::now()->subHours(self::SOURCE_MIN_AGE_HOURS);
        $rowThreshold = Carbon::now()->subHours(self::STALE_ROW_AGE_HOURS);
        $processingThreshold = Carbon::now()->subMinutes(self::STALE_PROCESSING_MINUTES);
        $removed = 0;

        $files = Storage::disk($sourceDisk)->allFiles($prefix);

        foreach ($files as $file) {
            if (! str_ends_with($file, '/source')) {
                continue;
            }

            if (Storage::disk($sourceDisk)->lastModified($file) > $sourceThreshold->getTimestamp()) {
                continue;
            }

            $parts = explode('/', str_replace('\\', '/', $file));

            if (count($parts) < 4) {
                continue;
            }

            $imageId = $parts[count($parts) - 2];

            if (! Str::isUuid($imageId)) {
                continue;
            }

            $image = ListingImage::query()->whereKey($imageId)->first();

            if ($image !== null) {
                continue;
            }

            if ($dryRun) {
                $this->line("Would delete orphan source object: {$file}");
            } else {
                $storage->deleteObjects([$file]);
            }

            $removed++;
        }

        $this->info(($dryRun ? 'Found' : 'Removed')." {$removed} orphan listing image source object(s).");

        $staleRows = 0;

        ListingImage::query()
            ->where(function ($query) use ($rowThreshold, $processingThreshold): void {
                $query->where(function ($query) use ($rowThreshold): void {
                    $query->whereIn('status', [ListingImageStatus::Pending, ListingImageStatus::Failed])
                        ->where('updated_at', '<', $rowThreshold);
                })->orWhere(function ($query) use ($processingThreshold): void {
                    $query->where('status', ListingImageStatus::Processing)
                        ->where('updated_at', '<', $processingThreshold);
                });
            })
            ->whereNull('processed_object_key')
            ->chunkById(100, function ($images) use ($dryRun, $storage, &$staleRows): void {
                foreach ($images as $image) {
                    if ($dryRun) {
                        $this->line("Would delete stale listing image row: {$image->id}");
                    } else {
                        $storage->deleteObjects([
                            $image->original_object_key,
                            $image->processed_object_key,
                            $image->thumbnail_object_key,
                        ]);
                        $image->delete();
                    }

                    $staleRows++;
                }
            });

        $this->info(($dryRun ? 'Found' : 'Removed')." {$staleRows} stale listing image row(s).");

        return self::SUCCESS;
    }
}
