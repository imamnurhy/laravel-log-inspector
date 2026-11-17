<?php

declare(strict_types=1);

namespace Explorin\LogInspector\Tests\Unit;

use Explorin\LogInspector\Enums\LogLevel;
use Explorin\LogInspector\Indexing\FileIndexStore;
use Explorin\LogInspector\Indexing\LogIndexer;
use Explorin\LogInspector\Parsers\LaravelLogParser;
use Explorin\LogInspector\Security\PathGuard;
use Explorin\LogInspector\Services\QueryBuilder;
use PHPUnit\Framework\TestCase;

final class QueryBuilderTest extends TestCase
{
    private string $fixtureDir;
    private string $tempIndexDir;
    private QueryBuilder $query;

    protected function setUp(): void
    {
        parent::setUp();
        $this->fixtureDir = realpath(__DIR__ . '/../Fixtures') ?: __DIR__ . '/../Fixtures';
        $this->tempIndexDir = sys_get_temp_dir() . '/log_inspector_qb_test_' . uniqid();

        $parser = new LaravelLogParser();
        $indexStore = new FileIndexStore($this->tempIndexDir);
        $indexer = new LogIndexer($parser, $indexStore);
        $pathGuard = new PathGuard(['laravel.log'], 300);

        $this->query = new QueryBuilder(
            indexer: $indexer,
            parser: $parser,
            pathGuard: $pathGuard,
            allowedDirectories: [$this->fixtureDir],
            maxScanBytes: 1024 * 1024,
            maxScanSeconds: 2
        );
    }

    public function test_query_filter_by_level_via_index(): void
    {
        $results = $this->query
            ->file($this->fixtureDir . '/single.log')
            ->level(LogLevel::WARNING)
            ->get();

        $this->assertCount(1, $results);
        $this->assertSame(LogLevel::WARNING, $results[0]->level);
    }

    public function test_literal_keyword_search_with_streaming(): void
    {
        $results = $this->query
            ->file($this->fixtureDir . '/single.log')
            ->search('High memory')
            ->get();

        $this->assertCount(1, $results);
        $this->assertStringContainsString('High memory', $results[0]->message);
    }

    public function test_search_partial_indicator_when_bytes_exceeded(): void
    {
        // Set very tiny byte budget (50 bytes) to force partial termination
        $parser = new LaravelLogParser();
        $indexStore = new FileIndexStore($this->tempIndexDir);
        $indexer = new LogIndexer($parser, $indexStore);
        $pathGuard = new PathGuard();

        $boundedQuery = new QueryBuilder(
            indexer: $indexer,
            parser: $parser,
            pathGuard: $pathGuard,
            allowedDirectories: [$this->fixtureDir],
            maxScanBytes: 50,
            maxScanSeconds: 1
        );

        $page = $boundedQuery
            ->file($this->fixtureDir . '/multiline.log')
            ->search('runtime')
            ->paginate();

        $this->assertTrue($page->isPartial);
        $this->assertNotNull($page->partialNotice);
        $this->assertStringContainsString('Search scan size limit reached', $page->partialNotice);
    }
}
