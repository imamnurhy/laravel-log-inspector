<?php

declare(strict_types=1);

namespace Explorin\LogInspector\DTO;

use Countable;
use IteratorAggregate;
use Traversable;
use ArrayIterator;

/**
 * @template T
 * @implements IteratorAggregate<int, T>
 */
final class Page implements Countable, IteratorAggregate
{
    /**
     * @param array<int, T> $items
     */
    public function __construct(
        public readonly array $items,
        public readonly int $total,
        public readonly int $perPage,
        public readonly int $currentPage,
        public readonly bool $isPartial = false,
        public readonly ?string $partialNotice = null,
    ) {
    }

    public function lastPage(): int
    {
        return (int) max(1, (int) ceil($this->total / max(1, $this->perPage)));
    }

    public function hasPages(): bool
    {
        return $this->lastPage() > 1;
    }

    public function hasMorePages(): bool
    {
        return $this->currentPage < $this->lastPage();
    }

    public function onFirstPage(): bool
    {
        return $this->currentPage <= 1;
    }

    public function count(): int
    {
        return count($this->items);
    }

    /**
     * @return Traversable<int, T>
     */
    public function getIterator(): Traversable
    {
        return new ArrayIterator($this->items);
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'data' => $this->items,
            'total' => $this->total,
            'per_page' => $this->perPage,
            'current_page' => $this->currentPage,
            'last_page' => $this->lastPage(),
            'has_more' => $this->hasMorePages(),
            'is_partial' => $this->isPartial,
            'partial_notice' => $this->partialNotice,
        ];
    }
}
