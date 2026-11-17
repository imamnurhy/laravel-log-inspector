<?php

declare(strict_types=1);

namespace Explorin\LogInspector\Services;

use Explorin\LogInspector\Contracts\LogIndexInterface;
use Explorin\LogInspector\Contracts\LogParserInterface;
use Explorin\LogInspector\Contracts\PathGuardInterface;
use Explorin\LogInspector\Indexing\FileIndexStore;
use Illuminate\Contracts\Cache\Repository as CacheRepository;

final class LogInspector
{
    /**
     * @param LogIndexInterface $indexer
     * @param LogParserInterface $parser
     * @param PathGuardInterface $pathGuard
     * @param FileIndexStore $indexStore
     * @param array<int, string> $allowedDirectories
     * @param CacheRepository|null $cache
     * @param int $cacheTtl
     */
    public function __construct(
        private readonly LogIndexInterface $indexer,
        private readonly LogParserInterface $parser,
        private readonly PathGuardInterface $pathGuard,
        private readonly FileIndexStore $indexStore,
        private readonly array $allowedDirectories,
        private readonly ?CacheRepository $cache = null,
        private readonly int $cacheTtl = 300,
    ) {
    }

    /**
     * Create a new fluent query builder instance.
     */
    public function query(): QueryBuilder
    {
        return new QueryBuilder(
            indexer: $this->indexer,
            parser: $this->parser,
            pathGuard: $this->pathGuard,
            allowedDirectories: $this->allowedDirectories
        );
    }

    /**
     * Get statistics service or calculate stats for a specific file.
     *
     * @return array<string, mixed>|LogStatistics
     */
    public function statistics(?string $file = null): array|LogStatistics
    {
        $statsService = new LogStatistics(
            indexer: $this->indexer,
            indexStore: $this->indexStore,
            cache: $this->cache,
            ttlSeconds: $this->cacheTtl
        );

        if ($file === null) {
            return $statsService;
        }

        $validated = $this->pathGuard->validate($file, $this->allowedDirectories);

        return $statsService->forFile($validated);
    }

    /**
     * Encode filename to URL-safe opaque identifier without .log extension.
     */
    public static function encodeFile(string $fileName): string
    {
        return rtrim(strtr(base64_encode($fileName), '+/', '-_'), '=');
    }

    /**
     * Decode URL-safe opaque identifier back to original filename.
     */
    public static function decodeFile(string $fileIdentifier): string
    {
        // If it already ends with .log / .gz / .txt, return directly (backward compatibility)
        if (str_ends_with($fileIdentifier, '.log') || str_ends_with($fileIdentifier, '.gz') || str_ends_with($fileIdentifier, '.txt')) {
            return $fileIdentifier;
        }

        $decoded = base64_decode(strtr($fileIdentifier, '-_', '+/'), true);
        if ($decoded === false || $decoded === '') {
            return $fileIdentifier;
        }

        return $decoded;
    }

    /**
     * List all log files detected in allowed directories.
     *
     * @return array<int, array{name: string, identifier: string, path: string, size: int, mtime: int, is_active: bool}>
     */
    public function files(): array
    {
        $files = [];

        foreach ($this->allowedDirectories as $dir) {
            $realDir = realpath($dir);
            if ($realDir === false || ! is_dir($realDir)) {
                continue;
            }

            $scan = scandir($realDir);
            if ($scan === false) {
                continue;
            }

            foreach ($scan as $item) {
                if ($item === '.' || $item === '..') {
                    continue;
                }

                $fullPath = $realDir . DIRECTORY_SEPARATOR . $item;
                if (! is_file($fullPath)) {
                    continue;
                }

                $ext = strtolower(pathinfo($item, PATHINFO_EXTENSION));
                if (! in_array($ext, ['log', 'gz', 'txt'], true)) {
                    continue;
                }

                $files[] = [
                    'name' => $item,
                    'identifier' => self::encodeFile($item),
                    'path' => $fullPath,
                    'size' => (int) filesize($fullPath),
                    'mtime' => (int) filemtime($fullPath),
                    'is_active' => $this->pathGuard->isActiveLogFile($fullPath),
                ];
            }
        }

        // Sort by mtime descending (most recently modified first)
        usort($files, static fn ($a, $b) => $b['mtime'] <=> $a['mtime']);

        return $files;
    }
}
