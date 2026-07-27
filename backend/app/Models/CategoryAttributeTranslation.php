<?php

namespace App\Models;

use App\Domain\Shared\Concerns\HasUuid;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CategoryAttributeTranslation extends Model
{
    use HasUuid;

    protected $fillable = [
        'category_attribute_id',
        'locale',
        'name',
    ];

    /** @return BelongsTo<CategoryAttribute, $this> */
    public function attribute(): BelongsTo
    {
        return $this->belongsTo(CategoryAttribute::class, 'category_attribute_id');
    }
}
