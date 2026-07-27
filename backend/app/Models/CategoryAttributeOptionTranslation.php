<?php

namespace App\Models;

use App\Domain\Shared\Concerns\HasUuid;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CategoryAttributeOptionTranslation extends Model
{
    use HasUuid;

    protected $fillable = [
        'category_attribute_option_id',
        'locale',
        'label',
    ];

    /** @return BelongsTo<CategoryAttributeOption, $this> */
    public function option(): BelongsTo
    {
        return $this->belongsTo(CategoryAttributeOption::class, 'category_attribute_option_id');
    }
}
