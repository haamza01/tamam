<?php

namespace App\Application\Shared;

use Illuminate\Http\Request;

class LocaleResolver
{
    public function resolve(Request $request): string
    {
        /** @var list<string> $supported */
        $supported = config('locales.supported', ['ar', 'en']);

        $queryLocale = $request->query('locale');

        if (is_string($queryLocale) && in_array($queryLocale, $supported, true)) {
            return $queryLocale;
        }

        $header = $request->header('Accept-Language');

        if (is_string($header) && $header !== '') {
            $primary = strtolower(substr($header, 0, 2));

            if (in_array($primary, $supported, true)) {
                return $primary;
            }
        }

        return (string) config('locales.default', 'ar');
    }

    /**
     * @return list<string>
     */
    public function supported(): array
    {
        /** @var list<string> $supported */
        $supported = config('locales.supported', ['ar', 'en']);

        return $supported;
    }
}
