<?php

declare(strict_types=1);

namespace Explorin\LogInspector\Readers;

use Explorin\LogInspector\Contracts\LogParserInterface;
use Explorin\LogInspector\Contracts\LogReaderInterface;
use Explorin\LogInspector\DTO\LogEntry;
use Generator;

final class DailyFileReader implements LogReaderInterface
{
    private ReverseChunkReader $reverseReader;

    public function __construct(
        LogParserInterface $parser,
        int $chunkSize = 65536,
    ) {
        $this->reverseReader = new ReverseChunkReader($parser, $chunkSize);
    }

    /**
     * {@inheritdoc}
     */
    public function supports(string $filePath): bool
    {
        $baseName = basename($filePath);

        // Matches files like laravel-2026-11-17.log
        return (bool) preg_match('/-\d{4}-\d{2}-\d{2}\.log$/', $baseName);
    }

    /**
     * {@inheritdoc}
     */
    public function read(string $filePath, int $maxEntries = 0, int $byteOffset = 0): Generator
    {
        return $this->reverseReader->read($filePath, $maxEntries, $byteOffset);
    }
}
