<?php

declare(strict_types=1);

namespace Explorin\LogInspector\Readers;

use Explorin\LogInspector\Contracts\LogParserInterface;
use Explorin\LogInspector\Contracts\LogReaderInterface;
use Explorin\LogInspector\DTO\LogEntry;
use Generator;
use RuntimeException;

final class ReverseChunkReader implements LogReaderInterface
{
    /**
     * @param LogParserInterface $parser Parser used to process extracted entry blocks
     * @param int $chunkSize Number of bytes to read per block (default 64 KB)
     */
    public function __construct(
        private readonly LogParserInterface $parser,
        private readonly int $chunkSize = 65536,
    ) {
    }

    /**
     * {@inheritdoc}
     */
    public function supports(string $filePath): bool
    {
        $ext = strtolower(pathinfo($filePath, PATHINFO_EXTENSION));

        return in_array($ext, ['log', 'txt'], true);
    }

    /**
     * {@inheritdoc}
     */
    public function read(string $filePath, int $maxEntries = 0, int $byteOffset = 0): Generator
    {
        if (! file_exists($filePath) || ! is_readable($filePath)) {
            throw new RuntimeException(sprintf('Log file cannot be opened for reading: %s', $filePath));
        }

        $fileSize = filesize($filePath);
        if ($fileSize === false || $fileSize === 0) {
            return;
        }

        // Open in binary read mode ('rb') without exclusive locks
        $handle = fopen($filePath, 'rb');
        if ($handle === false) {
            throw new RuntimeException(sprintf('Failed to open stream for file: %s', $filePath));
        }

        try {
            $currentPos = $byteOffset > 0 ? min($byteOffset, $fileSize) : $fileSize;
            $yieldedCount = 0;
            $buffer = '';

            // Read backwards block-by-block
            while ($currentPos > 0) {
                $readLength = min($this->chunkSize, $currentPos);
                $currentPos -= $readLength;

                fseek($handle, $currentPos, SEEK_SET);
                $chunk = fread($handle, $readLength);
                if ($chunk === false) {
                    break;
                }

                $buffer = $chunk . $buffer;

                // Process complete entries backwards from current buffer
                while (($splitPos = $this->findPreviousEntryStart($buffer)) !== false) {
                    // Entry starts at $splitPos within $buffer
                    $rawEntry = substr($buffer, $splitPos);
                    $entryByteLength = strlen($rawEntry);
                    $entryByteOffset = $currentPos + $splitPos;

                    // Remaining buffer to the left
                    $buffer = substr($buffer, 0, $splitPos);

                    $trimmed = trim($rawEntry);
                    if ($trimmed !== '') {
                        $parsed = $this->parser->parse($trimmed, $entryByteOffset, $entryByteLength);
                        yield $parsed;
                        $yieldedCount++;

                        if ($maxEntries > 0 && $yieldedCount >= $maxEntries) {
                            return;
                        }
                    }
                }
            }

            // Leftover at the very top of file (offset 0)
            if (trim($buffer) !== '') {
                $rawEntry = $buffer;
                $entryByteLength = strlen($rawEntry);
                $entryByteOffset = 0;

                $parsed = $this->parser->parse(trim($rawEntry), $entryByteOffset, $entryByteLength);
                yield $parsed;
            }
        } finally {
            fclose($handle);
        }
    }

    /**
     * Find the rightmost position where a new log entry begins in the buffer,
     * skipping the very end of the buffer so we capture the preceding entry.
     */
    private function findPreviousEntryStart(string $buffer): int|false
    {
        $bufferLength = strlen($buffer);
        if ($bufferLength === 0) {
            return false;
        }

        // Search from end towards start for newline followed by entry start
        $offset = $bufferLength - 1;

        while ($offset > 0) {
            $lastNewline = strrpos(substr($buffer, 0, $offset), "\n");
            if ($lastNewline === false) {
                // If we are at the very beginning of the whole file, check if offset 0 starts entry
                return false;
            }

            $lineStartPos = $lastNewline + 1;
            // Peek line content
            $lineEndPos = strpos($buffer, "\n", $lineStartPos);
            $candidateLine = $lineEndPos !== false
                ? substr($buffer, $lineStartPos, $lineEndPos - $lineStartPos)
                : substr($buffer, $lineStartPos);

            if ($this->parser->isEntryStart($candidateLine)) {
                return $lineStartPos;
            }

            $offset = $lastNewline;
        }

        return false;
    }
}
