<?php

namespace App\Models;

use App\Domain\Category\Enums\CategoryStatus;
use App\Domain\Shared\Concerns\HasUuid;
use App\Models\Concerns\HasTranslations;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class Category extends Model
{
    use HasTranslations;
    use HasUuid;
    use SoftDeletes;

    protected $fillable = [
        'parent_id',
        'slug',
        'icon',
        'image',
        'sort_order',
        'status',
        'seo_title',
        'seo_description',
        'listing_count',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'sort_order' => 'integer',
            'listing_count' => 'integer',
            'status' => CategoryStatus::class,
        ];
    }

    /** @return BelongsTo<Category, $this> */
    public function parent(): BelongsTo
    {
        return $this->belongsTo(Category::class, 'parent_id');
    }

    /** @return HasMany<Category, $this> */
    public function children(): HasMany
    {
        return $this->hasMany(Category::class, 'parent_id')->orderBy('sort_order');
    }

    public function isLeaf(): bool
    {
        if ($this->relationLoaded('children')) {
            return $this->children->isEmpty();
        }

        return ! $this->children()->exists();
    }

    /** @return HasMany<CategoryAttribute, $this> */
    public function attributes(): HasMany
    {
        return $this->hasMany(CategoryAttribute::class)->orderBy('sort_order');
    }

    /** @return HasMany<CategoryTranslation, $this> */
    public function translations(): HasMany
    {
        return $this->hasMany(CategoryTranslation::class);
    }

    public function isPubliclyVisible(): bool
    {
        return $this->status->isPubliclyVisible() && ! $this->trashed();
    }
}
