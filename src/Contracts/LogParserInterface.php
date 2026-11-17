<?php

declare(strict_types=1);

namespace Explorin\LogInspector\Contracts;

use Explorin\LogInspector\DTO\LogEntry;

interface LogParserInterface
{
    /**
     * Parse raw log text into a strongly-typed LogEntry DTO.
     *
     * @param string $rawText Contiguous raw block of one complete log entry (single or multiline).
     * @param int $byteOffset Byte position in the source file where this entry starts.
     * @param int $byteLength Total length in bytes of this entry in the source file.
     * @return LogEntry
     */
    public function parse(string $rawText, int $byteOffset = 0, int $byteLength = 0): LogEntry;

    /**
     * Quick check if the given line starts a new log entry according to this parser's grammar.
     */
    public function isEntryStart(string $line): bool;
}
