<?php

declare(strict_types=1);

namespace Explorin\LogInspector\Readers;

use Explorin\LogInspector\Contracts\LogParserInterface;
use Explorin\LogInspector\Contracts\LogReaderInterface;
use Explorin\LogInspector\DTO\LogEntry;
use Generator;
use RuntimeException;

final class GzipReader implements LogReaderInterface
{
    public function __construct(
        private readonly LogParserInterface $parser,
        private readonly int $bufferSize = 65536,
    ) {
    }

    /**
     * {@inheritdoc}
     */
    public function supports(string $filePath): bool
    {
        return str_ends_with(strtolower($filePath), '.gz');
    }

    /**
     * Read streaming forward from gz file (gzipped log archives cannot seek backwards).
     *
     * {@inheritdoc}
     */
    public function read(string $filePath, int $maxEntries = 0, int $byteOffset = 0): Generator
    {
        if (! extension_loaded('zlib')) {
            throw new RuntimeException('PHP zlib extension is required to read .gz log files.');
        }

        $gz = gzopen($filePath, 'rb');
        if ($gz === false) {
            throw new RuntimeException(sprintf('Failed to open gz stream: %s', $filePath));
        }

        try {
            $buffer = '';
            $currentEntry = '';
            $currentOffset = 0;
            $yielded = 0;

            while (! gzeof($gz)) {
                $line = gzgets($gz, 8192);
                if ($line === false) {
                    break;
                }

                if ($this->parser->isEntryStart($line)) {
                    if ($currentEntry !== '') {
                        $parsed = $this->parser->parse(trim($currentEntry), $currentOffset, strlen($currentEntry));
                        yield $parsed;
                        $yielded++;

                        if ($maxEntries > 0 && $yielded >= $maxEntries) {
                            return;
                        }

                        $currentOffset += strlen($currentEntry);
                    }
                    $currentEntry = $line;
                } else {
                    $currentEntry .= $line;
                }
            }

            if ($currentEntry !== '') {
                $parsed = $this->parser->parse(trim($currentEntry), $currentOffset, strlen($currentEntry));
                yield $parsed;
            }
        } finally {
            gzclose($gz);
        }
    }
}
