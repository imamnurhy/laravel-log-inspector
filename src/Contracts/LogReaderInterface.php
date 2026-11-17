<?php

declare(strict_types=1);

namespace Explorin\LogInspector\Contracts;

use Explorin\LogInspector\DTO\LogEntry;
use Generator;

interface LogReaderInterface
{
    /**
     * Read log entries from a file in reverse chronological order (newest first).
     *
     * @param string $filePath Absolute path to the log file.
     * @param int $maxEntries Maximum number of entries to yield (0 for unlimited).
     * @param int $byteOffset Optional starting byte offset (from end of file if reverse).
     * @return Generator<int, LogEntry>
     */
    public function read(string $filePath, int $maxEntries = 0, int $byteOffset = 0): Generator;

    /**
     * Check if this reader supports the given file extension/format.
     */
    public function supports(string $filePath): bool;
}
