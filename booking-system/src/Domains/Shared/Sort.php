<?php

namespace Domains\Shared;

class Sort
{
    public const SORT_ASC = 'asc';
    public const SORT_DESC = 'desc';

    public function __construct(
        public string $column,
        public string $direction
    ) {}

    public static function from(string $sort): self
    {
        $column = substr($sort, 0, 1) === '-'
            ? substr($sort, 1)
            : $sort;

        $direction = substr($sort, 0, 1) === '-'
            ? 'asc'
            : 'desc';

        return new Sort($column, $direction);
    }

    public function getColumn(): string
    {
        return $this->column;
    }

    public function getDirection(): string
    {
        return $this->direction;
    }
}
