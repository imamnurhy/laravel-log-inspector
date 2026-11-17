<?php

declare(strict_types=1);

namespace Explorin\LogInspector\Indexing;

use Explorin\LogInspector\Contracts\LogIndexInterface;
use Explorin\LogInspector\Contracts\LogParserInterface;
use Explorin\LogInspector\Contracts\MaskerInterface;
use Explorin\LogInspector\DTO\LogEntry;
use Explorin\LogInspector\DTO\LogQuery;
use Explorin\LogInspector\DTO\Page;
use Explorin\LogInspector\Enums\LogLevel;
use Generator;
use RuntimeException;

final class LogIndexer implements LogIndexInterface
{
    /**
     * @param LogParserInterface $parser
     * @param FileIndexStore $indexStore
     * @param MaskerInterface|null $masker
     */
    public function __construct(
        private readonly LogParserInterface $parser,
        private readonly FileIndexStore $indexStore,
        private readonly ?MaskerInterface $masker = null,
    ) {
    }

    /**
     * {@inheritdoc}
     */
    public function hasIndex(string $filePath): bool
    {
        return $this->indexStore->exists($filePath);
    }

    /**
     * {@inheritdoc}
     */
    public function forgetIndex(string $filePath): void
    {
        $this->indexStore->delete($filePath);
    }

    /**
     * Build or incrementally update the index file.
     *
     * {@inheritdoc}
     */
    public function indexFile(string $filePath, bool $forceReindex = false): int
    {
        if (! file_exists($filePath) || ! is_readable($filePath)) {
            throw new RuntimeException(sprintf('File does not exist or is not readable: %s', $filePath));
        }

        $this->indexStore->ensureDirectoryExists();
        $indexPath = $this->indexStore->getIndexPath($filePath);

        $currentSize = (int) filesize($filePath);
        $currentInode = (int) fileinode($filePath);
        $currentMtime = (int) filemtime($filePath);

        $lastIndexedOffset = 0;
        $indexHandle = null;

        if ($forceReindex || ! file_exists($indexPath)) {
            // Fresh index
            $indexHandle = fopen($indexPath, 'w+b');
            if ($indexHandle === false) {
                throw new RuntimeException(sprintf('Cannot write index file: %s', $indexPath));
            }
            $this->writeHeader($indexHandle, $currentSize, $currentInode, $currentMtime);
        } else {
            // Read header to check rotation or truncation
            $indexHandle = fopen($indexPath, 'r+b');
            if ($indexHandle === false) {
                throw new RuntimeException(sprintf('Cannot open index file: %s', $indexPath));
            }

            $header = $this->readHeader($indexHandle);
            if ($header === null || $currentSize < $header['last_offset'] || $currentInode !== $header['inode']) {
                // File was rotated or truncated! Rebuild from scratch.
                fclose($indexHandle);
                $indexHandle = fopen($indexPath, 'w+b');
                if ($indexHandle === false) {
                    throw new RuntimeException(sprintf('Cannot re-create index file: %s', $indexPath));
                }
                $this->writeHeader($indexHandle, $currentSize, $currentInode, $currentMtime);
                $lastIndexedOffset = 0;
            } else {
                $lastIndexedOffset = $header['last_offset'];
                // Move pointer to end of index file to append new records
                fseek($indexHandle, 0, SEEK_END);
            }
        }

        // If file hasn't grown since last index, return 0 new entries
        if ($currentSize <= $lastIndexedOffset) {
            fclose($indexHandle);
            return 0;
        }

        $logHandle = fopen($filePath, 'rb');
        if ($logHandle === false) {
            fclose($indexHandle);
            throw new RuntimeException(sprintf('Cannot read log file: %s', $filePath));
        }

        fseek($logHandle, $lastIndexedOffset, SEEK_SET);

        $newEntriesCount = 0;
        $currentEntryOffset = $lastIndexedOffset;
        $currentEntryText = '';
        $streamOffset = $lastIndexedOffset;

        while (! feof($logHandle)) {
            $line = fgets($logHandle, 65536);
            if ($line === false) {
                break;
            }

            $lineLength = strlen($line);

            if ($this->parser->isEntryStart($line)) {
                // If we already had an entry accumulating, index it
                if ($currentEntryText !== '') {
                    $entryLength = $streamOffset - $currentEntryOffset;
                    $this->appendIndexRecord($indexHandle, $currentEntryOffset, $entryLength, $currentEntryText);
                    $newEntriesCount++;
                    $currentEntryOffset = $streamOffset;
                    $currentEntryText = '';
                }
            }

            $currentEntryText .= $line;
            $streamOffset += $lineLength;
        }

        // Final entry at EOF
        if ($currentEntryText !== '') {
            $entryLength = $streamOffset - $currentEntryOffset;
            $this->appendIndexRecord($indexHandle, $currentEntryOffset, $entryLength, $currentEntryText);
            $newEntriesCount++;
        }

        // Update header with new last_offset and mtime
        $this->updateHeaderOffset($indexHandle, $streamOffset, $currentMtime);

        fclose($logHandle);
        fclose($indexHandle);

        return $newEntriesCount;
    }

    /**
     * {@inheritdoc}
     */
    public function paginate(string $filePath, LogQuery $query): Page
    {
        if (! $this->hasIndex($filePath)) {
            $this->indexFile($filePath);
        }

        $indexPath = $this->indexStore->getIndexPath($filePath);
        $indexHandle = fopen($indexPath, 'rb');
        if ($indexHandle === false) {
            throw new RuntimeException(sprintf('Failed to open index: %s', $indexPath));
        }

        try {
            $matchingOffsets = [];

            // Read header
            $header = $this->readHeader($indexHandle);
            if ($header === null) {
                return new Page([], 0, $query->perPage, $query->page);
            }

            // Stream index records
            while (! feof($indexHandle)) {
                $recordLine = fgets($indexHandle);
                if ($recordLine === false || trim($recordLine) === '') {
                    continue;
                }

                $record = json_decode(trim($recordLine), true);
                if (! is_array($record)) {
                    continue;
                }

                // Apply query filters on indexed fields without reading log file:
                // Record schema: [o: offset, l: len, t: timestamp, v: level, c: channel, h: exception_hash]
                if (! empty($query->levels)) {
                    $matchLevel = false;
                    foreach ($query->levels as $levelEnum) {
                        if ($levelEnum->value === ($record['v'] ?? '')) {
                            $matchLevel = true;
                            break;
                        }
                    }
                    if (! $matchLevel) {
                        continue;
                    }
                }

                if (! empty($query->channels) && ! in_array($record['c'] ?? '', $query->channels, true)) {
                    continue;
                }

                $recordTimestamp = (int) ($record['t'] ?? 0);
                if ($query->fromDate !== null && $recordTimestamp < $query->fromDate->getTimestamp()) {
                    continue;
                }

                if ($query->toDate !== null && $recordTimestamp > $query->toDate->getTimestamp()) {
                    continue;
                }

                if ($query->exceptionHash !== null && ($record['h'] ?? '') !== $query->exceptionHash) {
                    continue;
                }

                $matchingOffsets[] = [
                    'offset' => (int) $record['o'],
                    'length' => (int) $record['l'],
                ];
            }

            // Order reverse chronological (newest first)
            $matchingOffsets = array_reverse($matchingOffsets);
            $total = count($matchingOffsets);

            // Compute slice for requested page
            $page = max(1, $query->page);
            $perPage = max(1, $query->perPage);
            $offsetIndex = ($page - 1) * $perPage;

            $pageSlice = array_slice($matchingOffsets, $offsetIndex, $perPage);

            // Hydrate LogEntries using fseek() to exact byte offsets
            $logHandle = fopen($filePath, 'rb');
            if ($logHandle === false) {
                throw new RuntimeException(sprintf('Failed to open log file for hydration: %s', $filePath));
            }

            $items = [];
            try {
                foreach ($pageSlice as $sliceItem) {
                    fseek($logHandle, $sliceItem['offset'], SEEK_SET);
                    $rawText = fread($logHandle, $sliceItem['length']);
                    if ($rawText !== false) {
                        $items[] = $this->parser->parse($rawText, $sliceItem['offset'], $sliceItem['length']);
                    }
                }
            } finally {
                fclose($logHandle);
            }

            return new Page(
                items: $items,
                total: $total,
                perPage: $perPage,
                currentPage: $page,
                isPartial: false
            );
        } finally {
            fclose($indexHandle);
        }
    }

    /**
     * @return array{inode: int, last_offset: int, mtime: int}|null
     */
    private function readHeader($handle): ?array
    {
        fseek($handle, 0, SEEK_SET);
        $headerLine = fgets($handle);
        if ($headerLine === false || ! str_starts_with($headerLine, '#IDX_HEADER:')) {
            return null;
        }

        $jsonStr = substr(trim($headerLine), strlen('#IDX_HEADER:'));
        $data = json_decode($jsonStr, true);

        if (! is_array($data)) {
            return null;
        }

        return [
            'inode' => (int) ($data['inode'] ?? 0),
            'last_offset' => (int) ($data['last_offset'] ?? 0),
            'mtime' => (int) ($data['mtime'] ?? 0),
        ];
    }

    private function writeHeader($handle, int $offset, int $inode, int $mtime): void
    {
        fseek($handle, 0, SEEK_SET);
        // Fixed width header padded to 256 bytes for in-place rewriting
        $data = json_encode(['last_offset' => $offset, 'inode' => $inode, 'mtime' => $mtime]);
        $line = sprintf("#IDX_HEADER:%s\n", $data);
        $padded = str_pad($line, 256, ' ', STR_PAD_RIGHT);
        fwrite($handle, $padded);
    }

    private function updateHeaderOffset($handle, int $offset, int $mtime): void
    {
        $header = $this->readHeader($handle);
        $inode = $header['inode'] ?? 0;
        $this->writeHeader($handle, $offset, $inode, $mtime);
    }

    private function appendIndexRecord($handle, int $offset, int $length, string $rawText): void
    {
        $parsed = $this->parser->parse($rawText, $offset, $length);

        $record = [
            'o' => $offset,
            'l' => $length,
            't' => $parsed->timestamp->getTimestamp(),
            'v' => $parsed->level->value,
            'c' => $parsed->channel,
            'h' => $parsed->exceptionHash(),
        ];

        fwrite($handle, json_encode($record) . "\n");
    }
}
