<?php

declare(strict_types=1);

namespace Explorin\LogInspector\Tests\Unit;

use Explorin\LogInspector\Indexing\FileIndexStore;
use Explorin\LogInspector\Indexing\LogIndexer;
use Explorin\LogInspector\Parsers\LaravelLogParser;
use Explorin\LogInspector\Services\LogStatistics;
use PHPUnit\Framework\TestCase;

final class LogStatisticsTest extends TestCase
{
    public function test_computes_statistics_from_indexed_file(): void
    {
        $fixture = realpath(__DIR__ . '/../Fixtures/multiline.log') ?: '';
        $tempIndexDir = sys_get_temp_dir() . '/log_stats_test_' . uniqid();

        $parser = new LaravelLogParser();
        $indexStore = new FileIndexStore($tempIndexDir);
        $indexer = new LogIndexer($parser, $indexStore);

        $statsService = new LogStatistics($indexer, $indexStore, cache: null);
        $stats = $statsService->forFile($fixture);

        $this->assertSame(2, $stats['total']);
        $this->assertSame(1, $stats['levels']['ERROR']);
        $this->assertSame(1, $stats['levels']['CRITICAL']);
        $this->assertSame(0, $stats['levels']['DEBUG']);
        $this->assertNotEmpty($stats['daily_distribution']);
        $this->assertNotEmpty($stats['top_exceptions']);
    }
}
