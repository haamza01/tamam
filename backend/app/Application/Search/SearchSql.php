<?php

namespace App\Application\Search;

/**
 * Approved PostgreSQL FTS fragments. User input is always bound as the second
 * parameter to plainto_tsquery — never passed to to_tsquery.
 */
final class SearchSql
{
    public const FTS_MATCH = 'search_vector @@ plainto_tsquery(?, ?)';

    public const FTS_RANK = 'ts_rank_cd(search_vector, plainto_tsquery(?, ?), 32) DESC';
}
