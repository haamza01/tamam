<?php

namespace App\Application\Shared;

use Illuminate\Http\Request;

class LocaleResolver
{
    private const SUPPORTED = ['ar', 'en'];

    public function resolve(Request $request): string
    {
        $queryLocale = $request->query('locale');

        if (is_string($queryLocale) && in_array($queryLocale, self::SUPPORTED, true)) {
            return $queryLocale;
        }

        $header = $request->header('Accept-Language');

        if (is_string($header) && $header !== '') {
            $primary = strtolower(substr($header, 0, 2));

            if (in_array($primary, self::SUPPORTED, true)) {
                return $primary;
            }
        }

        return 'ar';
    }
}
