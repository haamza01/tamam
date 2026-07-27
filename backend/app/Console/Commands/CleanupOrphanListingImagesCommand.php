<?php

namespace App\Console\Commands;

use App\Application\Listing\ListingImageStorageService;
use App\Domain\Listing\Enums\ListingImageStatus;
use App\Models\ListingImage;
use Illuminate\Console\Command;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Storage;

class CleanupOrphanListingImagesCommand extends Command
{
    protected $signature = 'listings:cleanup-orphan-images {--dry-run : Report orphaned objects without deleting them}';

    protected $description = 'Remove stale listing image source objects without database rows';

    public function handle(ListingImageStorageService $storage): int
    {
        $dryRun = (bool) $this->option('dry-run');
        $sourceDisk = (string) config('media.listing.source_disk', 'local');
        $prefix = trim((string) config('media.listing.path_prefix'), '/');
        $threshold = Carbon::now()->subHours(24);
        $removed = 0;

        $files = Storage::disk($sourceDisk)->allFiles($prefix);

        foreach ($files as $file) {
            if (! str_ends_with($file, '/source')) {
                continue;
            }

            if (Storage::disk($sourceDisk)->lastModified($file) > $threshold->getTimestamp()) {
                continue;
            }

            $parts = explode('/', str_replace('\\', '/', $file));

            if (count($parts) < 4) {
                continue;
            }

            $imageId = $parts[count($parts) - 2];
            $exists = ListingImage::query()->whereKey($imageId)->exists();

            if ($exists) {
                continue;
            }

            if ($dryRun) {
                $this->line("Would delete orphan source object: {$file}");
            } else {
                $storage->deleteObjects([$file]);
            }

            $removed++;
        }

        $this->info(($dryRun ? 'Found' : 'Removed')." {$removed} orphan listing image object(s).");

        ListingImage::query()
            ->whereIn('status', [ListingImageStatus::Pending, ListingImageStatus::Failed])
            ->where('updated_at', '<', $threshold)
            ->whereNull('processed_object_key')
            ->chunkById(100, function ($images) use ($dryRun, $storage, &$removed): void {
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

                    $removed++;
                }
            });

        return self::SUCCESS;
    }
}
