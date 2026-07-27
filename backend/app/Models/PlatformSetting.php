<?php

namespace App\Models;

use App\Domain\Shared\Concerns\HasUuid;
use Illuminate\Database\Eloquent\Model;

class PlatformSetting extends Model
{
    use HasUuid;

    protected $fillable = [
        'key',
        'value',
        'group',
        'description',
    ];

    protected function casts(): array
    {
        return [
            'value' => 'array',
        ];
    }
}
