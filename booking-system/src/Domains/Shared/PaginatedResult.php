<?php

namespace Domains\Shared;

/**
 * @template T
 */
class PaginatedResult
{
    /** @var T[] */
    protected iterable $items;
    protected int $page;
    protected int $perPage;
    protected int $total;

    /**
     * @param T[] $items
     */
    public function __construct(
        iterable $items,
        int $page,
        int $perPage,
        int $total
    ) {
        $this->items = $items;
        $this->page = $page;
        $this->perPage = $perPage;
        $this->total = $total;
    }

    /**
     * @return T[]
     */
    public function getItems(): iterable
    {
        return $this->items;
    }

    public function getPage(): int
    {
        return $this->page;
    }

    public function getPerPage(): int
    {
        return $this->perPage;
    }

    public function getLastPage(): int
    {
        return max(1, (int) ceil($this->getTotal() / $this->getPerPage()));
    }

    public function getTotal(): int
    {
        return $this->total;
    }

    public function getFrom(): int
    {
        return max(($this->getPage() - 1) * $this->getPerPage() + 1, 1);
    }

    public function getTo(): int
    {
        return min($this->getFrom() + $this->getPerPage() - 1, $this->getTotal());
    }
}
