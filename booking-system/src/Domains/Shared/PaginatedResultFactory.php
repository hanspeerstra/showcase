<?php

namespace Domains\Shared;

use Illuminate\Contracts\Database\Eloquent\Builder;

class PaginatedResultFactory
{
    public function make(
        Builder|\Illuminate\Database\Query\Builder $query,
        int $page,
        int $perPage
    ): PaginatedResult {
        $query->forPage($page, $perPage);

        $results = $query->paginate($perPage, ['*'], 'page', $page);

        $items = $results->items();
        $count = $results->total();

        return new PaginatedResult(
            $items,
            $page,
            $perPage,
            $count
        );
    }
}
