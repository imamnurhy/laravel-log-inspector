<?php

declare(strict_types=1);

namespace Explorin\LogInspector\DTO;

use DateTimeImmutable;
use DateTimeInterface;
use Explorin\LogInspector\Enums\LogLevel;

final class LogQuery
{
    /**
     * @param array<int, LogLevel> $levels
     * @param array<int, string> $channels
     * @param array<int, string> $environments
     */
    public function __construct(
        public readonly ?string $file = null,
        public readonly array $levels = [],
        public readonly array $channels = [],
        public readonly array $environments = [],
        public readonly ?DateTimeImmutable $fromDate = null,
        public readonly ?DateTimeImmutable $toDate = null,
        public readonly ?string $search = null,
        public readonly ?string $exceptionClass = null,
        public readonly ?string $exceptionHash = null,
        public readonly int $perPage = 10,
        public readonly int $page = 1,
    ) {
    }

    /**
     * Helper to clone with a specific file.
     */
    public function withFile(string $file): self
    {
        return new self(
            file: $file,
            levels: $this->levels,
            channels: $this->channels,
            environments: $this->environments,
            fromDate: $this->fromDate,
            toDate: $this->toDate,
            search: $this->search,
            exceptionClass: $this->exceptionClass,
            exceptionHash: $this->exceptionHash,
            perPage: $this->perPage,
            page: $this->page,
        );
    }
}
