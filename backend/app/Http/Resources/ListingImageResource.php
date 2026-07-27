<?php

namespace App\Http\Resources;

use App\Application\Storage\PublicAssetUrlResolver;
use App\Models\ListingImage;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin ListingImage */
class ListingImageResource extends JsonResource
{
    private bool $ownerView = false;

    public function forOwner(bool $ownerView = true): self
    {
        $this->ownerView = $ownerView;

        return $this;
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        /** @var PublicAssetUrlResolver $urlResolver */
        $urlResolver = app(PublicAssetUrlResolver::class);

        if (! $this->ownerView && ! $this->isReady()) {
            return [];
        }

        $data = [
            'id' => $this->id,
            'sort_order' => $this->sort_order,
        ];

        if ($this->isReady()) {
            $data['url'] = $urlResolver->resolve($this->processed_object_key);
            $data['thumbnail_url'] = $urlResolver->resolve($this->thumbnail_object_key);
            $data['width'] = $this->processed_width;
            $data['height'] = $this->processed_height;
        }

        if ($this->ownerView) {
            $data['status'] = $this->status->value;
            $data['created_at'] = $this->created_at?->toIso8601String();

            if ($this->status->value === 'failed' && $this->processing_error_code !== null) {
                $data['processing_error_code'] = $this->processing_error_code;
            }
        }

        return $data;
    }
}
