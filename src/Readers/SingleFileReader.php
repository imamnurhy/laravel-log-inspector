<?php

declare(strict_types=1);

namespace Explorin\LogInspector\Readers;

use Explorin\LogInspector\Contracts\LogParserInterface;
use Explorin\LogInspector\Contracts\LogReaderInterface;
use Explorin\LogInspector\DTO\LogEntry;
use Generator;

final class SingleFileReader implements LogReaderInterface
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
        return $this->reverseReader->supports($filePath);
    }

    /**
     * {@inheritdoc}
     */
    public function read(string $filePath, int $maxEntries = 0, int $byteOffset = 0): Generator
    {
        return $this->reverseReader->read($filePath, $maxEntries, $byteOffset);
    }
}
