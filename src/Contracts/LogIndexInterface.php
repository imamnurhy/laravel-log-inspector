<?php

declare(strict_types=1);

namespace Explorin\LogInspector\Contracts;

use Explorin\LogInspector\DTO\LogEntry;
use Explorin\LogInspector\DTO\LogQuery;
use Explorin\LogInspector\DTO\Page;

interface LogIndexInterface
{
    /**
     * Build or update incremental sidecar index for the given log file.
     *
     * @param string $filePath
     * @param bool $forceReindex
     * @return int Number of entries indexed
     */
    public function indexFile(string $filePath, bool $forceReindex = false): int;

    /**
     * Retrieve a paginated list of LogEntries using the sidecar offset index.
     *
     * @param string $filePath
     * @param LogQuery $query
     * @return Page<LogEntry>
     */
    public function paginate(string $filePath, LogQuery $query): Page;

    /**
     * Check if a valid sidecar index exists for the file.
     */
    public function hasIndex(string $filePath): bool;

    /**
     * Invalidate and delete sidecar index for the file.
     */
    public function forgetIndex(string $filePath): void;
}
