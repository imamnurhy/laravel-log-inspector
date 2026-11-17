<?php

declare(strict_types=1);

namespace Explorin\LogInspector\Indexing;

use RuntimeException;

final class FileIndexStore
{
    public function __construct(
        private readonly string $storagePath
    ) {
    }

    /**
     * Get path to the sidecar index file for a given log file.
     */
    public function getIndexPath(string $logFilePath): string
    {
        $hash = hash('xxh128', $logFilePath);
        $baseName = basename($logFilePath);
        $fileName = sprintf('%s_%s.idx', $baseName, substr($hash, 0, 8));

        return rtrim($this->storagePath, DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR . $fileName;
    }

    /**
     * Determine if an index file exists for the given log file.
     */
    public function exists(string $logFilePath): bool
    {
        return file_exists($this->getIndexPath($logFilePath));
    }

    /**
     * Ensure storage directory exists.
     */
    public function ensureDirectoryExists(): void
    {
        if (! is_dir($this->storagePath)) {
            if (! mkdir($this->storagePath, 0755, true) && ! is_dir($this->storagePath)) {
                throw new RuntimeException(sprintf('Directory "%s" was not created', $this->storagePath));
            }
        }
    }

    /**
     * Delete the index file if it exists.
     */
    public function delete(string $logFilePath): bool
    {
        $indexPath = $this->getIndexPath($logFilePath);
        if (file_exists($indexPath)) {
            return unlink($indexPath);
        }

        return false;
    }
}
