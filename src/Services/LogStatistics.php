<?php

declare(strict_types=1);

namespace Explorin\LogInspector\Services;

use Explorin\LogInspector\Contracts\LogIndexInterface;
use Explorin\LogInspector\Indexing\FileIndexStore;
use Illuminate\Contracts\Cache\Repository as CacheRepository;

final class LogStatistics
{
    /**
     * @param LogIndexInterface $indexer
     * @param FileIndexStore $indexStore
     * @param CacheRepository|null $cache
     * @param int $ttlSeconds
     */
    public function __construct(
        private readonly LogIndexInterface $indexer,
        private readonly FileIndexStore $indexStore,
        private readonly ?CacheRepository $cache = null,
        private readonly int $ttlSeconds = 300,
    ) {
    }

    /**
     * Calculate summary statistics for a given log file.
     * Uses cache tagged or keyed by file mtime to avoid unnecessary recalculations.
     *
     * @return array{
     *     total: int,
     *     levels: array<string, int>,
     *     daily_distribution: array<string, int>,
     *     top_exceptions: array<string, array{class: string, count: int, hash: string}>,
     *     file_size: int,
     *     last_modified: int
     * }
     */
    public function forFile(string $filePath): array
    {
        if (! file_exists($filePath)) {
            return $this->emptyStats();
        }

        $mtime = (int) filemtime($filePath);
        $cacheKey = sprintf('log_inspector_stats_%s_%d', hash('xxh128', $filePath), $mtime);

        if ($this->cache !== null) {
            $cached = $this->cache->get($cacheKey);
            if (is_array($cached)) {
                return $cached;
            }
        }

        if (! $this->indexer->hasIndex($filePath)) {
            $this->indexer->indexFile($filePath);
        }

        $stats = $this->computeFromIndex($filePath);

        if ($this->cache !== null) {
            $this->cache->put($cacheKey, $stats, $this->ttlSeconds);
        }

        return $stats;
    }

    /**
     * @return array<string, mixed>
     */
    private function computeFromIndex(string $filePath): array
    {
        $indexPath = $this->indexStore->getIndexPath($filePath);
        if (! file_exists($indexPath)) {
            return $this->emptyStats();
        }

        $handle = fopen($indexPath, 'rb');
        if ($handle === false) {
            return $this->emptyStats();
        }

        $total = 0;
        $levelCounts = [
            'EMERGENCY' => 0,
            'ALERT' => 0,
            'CRITICAL' => 0,
            'ERROR' => 0,
            'WARNING' => 0,
            'NOTICE' => 0,
            'INFO' => 0,
            'DEBUG' => 0,
        ];
        $dailyDistribution = [];
        $exceptionCounts = [];

        try {
            while (! feof($handle)) {
                $line = fgets($handle);
                if ($line === false || trim($line) === '' || str_starts_with($line, '#IDX_HEADER:')) {
                    continue;
                }

                $record = json_decode(trim($line), true);
                if (! is_array($record)) {
                    continue;
                }

                $total++;
                $lvl = (string) ($record['v'] ?? 'INFO');
                if (isset($levelCounts[$lvl])) {
                    $levelCounts[$lvl]++;
                }

                $timestamp = (int) ($record['t'] ?? 0);
                if ($timestamp > 0) {
                    $dateKey = date('Y-m-d', $timestamp);
                    $dailyDistribution[$dateKey] = ($dailyDistribution[$dateKey] ?? 0) + 1;
                }

                $exHash = $record['h'] ?? null;
                if ($exHash !== null) {
                    $exceptionCounts[$exHash] = ($exceptionCounts[$exHash] ?? 0) + 1;
                }
            }
        } finally {
            fclose($handle);
        }

        // Sort top exceptions
        arsort($exceptionCounts);
        $topExceptions = [];
        $rank = 0;
        foreach ($exceptionCounts as $hash => $count) {
            $topExceptions[] = [
                'hash' => $hash,
                'count' => $count,
            ];
            $rank++;
            if ($rank >= 5) {
                break;
            }
        }

        return [
            'total' => $total,
            'levels' => $levelCounts,
            'daily_distribution' => $dailyDistribution,
            'top_exceptions' => $topExceptions,
            'file_size' => (int) filesize($filePath),
            'last_modified' => (int) filemtime($filePath),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function emptyStats(): array
    {
        return [
            'total' => 0,
            'levels' => [
                'EMERGENCY' => 0,
                'ALERT' => 0,
                'CRITICAL' => 0,
                'ERROR' => 0,
                'WARNING' => 0,
                'NOTICE' => 0,
                'INFO' => 0,
                'DEBUG' => 0,
            ],
            'daily_distribution' => [],
            'top_exceptions' => [],
            'file_size' => 0,
            'last_modified' => 0,
        ];
    }
}
