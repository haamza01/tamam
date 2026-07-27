<?php

namespace App\Application\Shared;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class SlugGenerator
{
    public function forListing(string $title): string
    {
        $base = Str::slug(Str::limit($title, 80, ''));
        $base = $base !== '' ? $base : 'listing';

        return $this->ensureUnique('listings', 'slug', $base);
    }

    private function ensureUnique(string $table, string $column, string $base): string
    {
        $candidate = $base;
        $suffix = 1;

        while (DB::table($table)->where($column, $candidate)->exists()) {
            $candidate = $base.'-'.Str::lower(Str::random(6));
            $suffix++;

            if ($suffix > 20) {
                $candidate = $base.'-'.Str::uuid()->toString();
                break;
            }
        }

        return $candidate;
    }
}
