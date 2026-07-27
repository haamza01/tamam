<?php

namespace App\Application\Search;

use App\Domain\Search\Exceptions\SearchException;
use Illuminate\Support\Facades\DB;
use Symfony\Component\HttpFoundation\Response;

class SearchQueryParser
{
    /**
     * @return array{keyword: string|null, tsquery: string|null}
     */
    public function parse(?string $rawKeyword): array
    {
        if ($rawKeyword === null || trim($rawKeyword) === '') {
            return ['keyword' => null, 'tsquery' => null];
        }

        $keyword = $this->normalize($rawKeyword);

        if ($keyword === '') {
            return ['keyword' => null, 'tsquery' => null];
        }

        $minLength = (int) config('search.keyword.min_length');
        $maxLength = (int) config('search.keyword.max_length');
        $maxTokens = (int) config('search.keyword.max_tokens');

        if (mb_strlen($keyword) < $minLength) {
            throw new SearchException(
                errorCode: 'search.keyword_too_short',
                message: 'Validation failed.',
                status: Response::HTTP_UNPROCESSABLE_ENTITY,
                errors: ['keyword' => ['search.keyword_too_short']],
            );
        }

        if (mb_strlen($keyword) > $maxLength) {
            throw new SearchException(
                errorCode: 'search.keyword_too_long',
                message: 'Validation failed.',
                status: Response::HTTP_UNPROCESSABLE_ENTITY,
                errors: ['keyword' => ['search.keyword_too_long']],
            );
        }

        $tokens = preg_split('/\s+/u', $keyword, -1, PREG_SPLIT_NO_EMPTY) ?: [];

        if (count($tokens) > $maxTokens) {
            throw new SearchException(
                errorCode: 'search.keyword_too_many_tokens',
                message: 'Validation failed.',
                status: Response::HTTP_UNPROCESSABLE_ENTITY,
                errors: ['keyword' => ['search.keyword_too_many_tokens']],
            );
        }

        if (DB::connection()->getDriverName() !== 'pgsql') {
            return ['keyword' => $keyword, 'tsquery' => null];
        }

        $tsquery = DB::selectOne(
            'SELECT plainto_tsquery(?, ?) AS tsquery',
            [(string) config('search.fts_config', 'simple'), $keyword],
        );

        $tsqueryString = $tsquery?->tsquery ?? '';

        if ($tsqueryString === '') {
            return ['keyword' => $keyword, 'tsquery' => null];
        }

        return ['keyword' => $keyword, 'tsquery' => (string) $tsqueryString];
    }

    public function normalize(?string $value): string
    {
        if ($value === null) {
            return '';
        }

        $value = preg_replace('/[\x00-\x1F\x7F]/u', '', $value) ?? '';
        $value = preg_replace('/\s+/u', ' ', trim($value)) ?? '';

        return $value;
    }
}
