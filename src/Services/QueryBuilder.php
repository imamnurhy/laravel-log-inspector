<?php

declare(strict_types=1);

namespace Explorin\LogInspector\Services;

use DateTimeImmutable;
use DateTimeInterface;
use Explorin\LogInspector\Contracts\LogIndexInterface;
use Explorin\LogInspector\Contracts\LogParserInterface;
use Explorin\LogInspector\Contracts\LogReaderInterface;
use Explorin\LogInspector\Contracts\PathGuardInterface;
use Explorin\LogInspector\DTO\LogEntry;
use Explorin\LogInspector\DTO\LogQuery;
use Explorin\LogInspector\DTO\Page;
use Explorin\LogInspector\Enums\LogLevel;
use Explorin\LogInspector\Exceptions\SecurityException;
use Explorin\LogInspector\Readers\ReverseChunkReader;
use Generator;
use InvalidArgumentException;
use LazyCollection;

final class QueryBuilder
{
    private ?string $filePath = null;
    /** @var array<int, LogLevel> */
    private array $levels = [];
    /** @var array<int, string> */
    private array $channels = [];
    /** @var array<int, string> */
    private array $environments = [];
    private ?DateTimeImmutable $fromDate = null;
    private ?DateTimeImmutable $toDate = null;
    private ?string $search = null;
    private ?string $exceptionClass = null;
    private ?string $exceptionHash = null;

    /**
     * @param LogIndexInterface $indexer
     * @param LogParserInterface $parser
     * @param PathGuardInterface $pathGuard
     * @param array<int, string> $allowedDirectories
     * @param int $maxScanBytes
     * @param int $maxScanSeconds
     * @param int $maxSearchLength
     */
    public function __construct(
        private readonly LogIndexInterface $indexer,
        private readonly LogParserInterface $parser,
        private readonly PathGuardInterface $pathGuard,
        private readonly array $allowedDirectories,
        private readonly int $maxScanBytes = 52428800, // 50 MB
        private readonly int $maxScanSeconds = 5,
        private readonly int $maxSearchLength = 200,
    ) {
    }

    public function file(string $filePath): self
    {
        $this->filePath = $this->pathGuard->validate($filePath, $this->allowedDirectories);

        return $this;
    }

    public function level(string|LogLevel $level): self
    {
        $enum = is_string($level) ? LogLevel::tryFromString($level) : $level;
        if ($enum !== null && ! in_array($enum, $this->levels, true)) {
            $this->levels[] = $enum;
        }

        return $this;
    }

    /**
     * @param array<int, string|LogLevel> $levels
     */
    public function levels(array $levels): self
    {
        foreach ($levels as $lvl) {
            $this->level($lvl);
        }

        return $this;
    }

    public function channel(string $channel): self
    {
        if (! in_array($channel, $this->channels, true)) {
            $this->channels[] = $channel;
        }

        return $this;
    }

    public function environment(string $env): self
    {
        if (! in_array($env, $this->environments, true)) {
            $this->environments[] = $env;
        }

        return $this;
    }

    public function dateRange(string|DateTimeInterface $from, string|DateTimeInterface $to): self
    {
        $this->fromDate = $this->normalizeDate($from);
        $this->toDate = $this->normalizeDate($to);

        return $this;
    }

    public function search(?string $search): self
    {
        if ($search !== null && strlen($search) > $this->maxSearchLength) {
            throw new InvalidArgumentException(sprintf(
                'Search query exceeds maximum allowed length of %d characters.',
                $this->maxSearchLength
            ));
        }

        $this->search = $search !== null ? trim($search) : null;

        return $this;
    }

    public function exception(string $exceptionClass): self
    {
        $this->exceptionClass = $exceptionClass;

        return $this;
    }

    public function exceptionHash(string $hash): self
    {
        $this->exceptionHash = $hash;

        return $this;
    }

    /**
     * Build the query DTO.
     */
    public function toQuery(int $perPage = 10, int $page = 1): LogQuery
    {
        return new LogQuery(
            file: $this->filePath,
            levels: $this->levels,
            channels: $this->channels,
            environments: $this->environments,
            fromDate: $this->fromDate,
            toDate: $this->toDate,
            search: $this->search,
            exceptionClass: $this->exceptionClass,
            exceptionHash: $this->exceptionHash,
            perPage: $perPage,
            page: $page
        );
    }

    /**
     * Execute query and get results up to limit.
     *
     * @return array<int, LogEntry>
     */
    public function get(int $limit = 50): array
    {
        $page = $this->paginate($limit, 1);

        return $page->items;
    }

    /**
     * Execute paginated query with fast sidecar index or bounded stream scan.
     */
    public function paginate(int $perPage = 10, int $page = 1): Page
    {
        if ($this->filePath === null) {
            throw new InvalidArgumentException('File path must be specified to paginate.');
        }

        $query = $this->toQuery($perPage, $page);

        // If no keyword search is requested, query directly via sidecar index!
        if (empty($this->search) && empty($this->exceptionClass)) {
            return $this->indexer->paginate($this->filePath, $query);
        }

        // When keyword search or unindexed filters are present, perform bounded stream scan
        return $this->scanWithLimits($query);
    }

    /**
     * Return generator / cursor for processing logs one by one.
     *
     * @return Generator<int, LogEntry>
     */
    public function cursor(): Generator
    {
        if ($this->filePath === null) {
            throw new InvalidArgumentException('File path must be specified for cursor.');
        }

        $reader = new ReverseChunkReader($this->parser);

        foreach ($reader->read($this->filePath) as $entry) {
            if ($this->matchesEntry($entry)) {
                yield $entry;
            }
        }
    }

    /**
     * Bounded streaming scan over file (capped at maxScanBytes and maxScanSeconds).
     */
    private function scanWithLimits(LogQuery $query): Page
    {
        $reader = new ReverseChunkReader($this->parser);
        $startTime = microtime(true);
        $scannedBytes = 0;
        $isPartial = false;
        $partialNotice = null;

        $matchingItems = [];

        foreach ($reader->read((string) $query->file) as $entry) {
            $scannedBytes += $entry->byteLength;

            // Check execution time budget
            if ((microtime(true) - $startTime) >= $this->maxScanSeconds) {
                $isPartial = true;
                $partialNotice = sprintf('Search time limit reached (%d seconds). Showing partial results.', $this->maxScanSeconds);
                break;
            }

            // Check byte budget
            if ($scannedBytes >= $this->maxScanBytes) {
                $isPartial = true;
                $partialNotice = sprintf('Search scan size limit reached (%d MB). Showing partial results.', (int) ($this->maxScanBytes / 1048576));
                break;
            }

            if ($this->matchesEntry($entry)) {
                $matchingItems[] = $entry;
            }
        }

        $total = count($matchingItems);
        $offset = ($query->page - 1) * $query->perPage;
        $pageItems = array_slice($matchingItems, $offset, $query->perPage);

        return new Page(
            items: $pageItems,
            total: $total,
            perPage: $query->perPage,
            currentPage: $query->page,
            isPartial: $isPartial,
            partialNotice: $partialNotice
        );
    }

    /**
     * Determine if a given entry satisfies current filter criteria.
     */
    private function matchesEntry(LogEntry $entry): bool
    {
        if (! empty($this->levels) && ! in_array($entry->level, $this->levels, true)) {
            return false;
        }

        if (! empty($this->channels) && ! in_array($entry->channel, $this->channels, true)) {
            return false;
        }

        if (! empty($this->environments) && ! in_array($entry->environment, $this->environments, true)) {
            return false;
        }

        if ($this->fromDate !== null && $entry->timestamp < $this->fromDate) {
            return false;
        }

        if ($this->toDate !== null && $entry->timestamp > $this->toDate) {
            return false;
        }

        if ($this->exceptionClass !== null && $entry->exceptionClass !== $this->exceptionClass) {
            return false;
        }

        if ($this->exceptionHash !== null && $entry->exceptionHash() !== $this->exceptionHash) {
            return false;
        }

        if (! empty($this->search)) {
            // Literal case-insensitive search
            $searchNeedle = $this->search;
            $inMessage = stripos($entry->message, $searchNeedle) !== false;
            $inRaw = $entry->raw !== null && stripos($entry->raw, $searchNeedle) !== false;

            if (! $inMessage && ! $inRaw) {
                return false;
            }
        }

        return true;
    }

    private function normalizeDate(string|DateTimeInterface $date): DateTimeImmutable
    {
        if ($date instanceof DateTimeImmutable) {
            return $date;
        }

        if ($date instanceof DateTimeInterface) {
            return DateTimeImmutable::createFromInterface($date);
        }

        return new DateTimeImmutable($date);
    }
}
