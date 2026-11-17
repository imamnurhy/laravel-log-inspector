<?php

declare(strict_types=1);

namespace Explorin\LogInspector\Tests\Unit;

use Explorin\LogInspector\DTO\LogQuery;
use Explorin\LogInspector\Enums\LogLevel;
use Explorin\LogInspector\Indexing\FileIndexStore;
use Explorin\LogInspector\Indexing\LogIndexer;
use Explorin\LogInspector\Parsers\LaravelLogParser;
use PHPUnit\Framework\TestCase;

final class LogIndexerTest extends TestCase
{
    private string $fixtureLog;
    private string $tempIndexDir;
    private FileIndexStore $indexStore;
    private LogIndexer $indexer;

    protected function setUp(): void
    {
        parent::setUp();
        $this->fixtureLog = realpath(__DIR__ . '/../Fixtures/multiline.log') ?: '';
        $this->tempIndexDir = sys_get_temp_dir() . '/log_inspector_test_idx_' . uniqid();
        $this->indexStore = new FileIndexStore($this->tempIndexDir);
        $parser = new LaravelLogParser();
        $this->indexer = new LogIndexer($parser, $this->indexStore);
    }

    protected function tearDown(): void
    {
        // Cleanup index files
        $this->indexStore->delete($this->fixtureLog);
        if (is_dir($this->tempIndexDir)) {
            @rmdir($this->tempIndexDir);
        }
        parent::tearDown();
    }

    public function test_indexes_file_and_paginates_via_fseek(): void
    {
        $indexedCount = $this->indexer->indexFile($this->fixtureLog);
        $this->assertSame(2, $indexedCount);
        $this->assertTrue($this->indexer->hasIndex($this->fixtureLog));

        // Paginate all (newest first)
        $query = new LogQuery(perPage: 10, page: 1);
        $page = $this->indexer->paginate($this->fixtureLog, $query);

        $this->assertSame(2, $page->total);
        $this->assertCount(2, $page->items);
        $this->assertSame('Database connection failed', $page->items[0]->message);
        $this->assertSame('Call to undefined function foo()', $page->items[1]->message);
    }

    public function test_filter_by_level_in_indexer(): void
    {
        $this->indexer->indexFile($this->fixtureLog);

        $query = new LogQuery(levels: [LogLevel::ERROR], perPage: 10, page: 1);
        $page = $this->indexer->paginate($this->fixtureLog, $query);

        $this->assertSame(1, $page->total);
        $this->assertCount(1, $page->items);
        $this->assertSame(LogLevel::ERROR, $page->items[0]->level);
    }

    public function test_incremental_indexing_skips_when_size_unchanged(): void
    {
        $firstRun = $this->indexer->indexFile($this->fixtureLog);
        $this->assertSame(2, $firstRun);

        // Second run without new content should return 0 new entries
        $secondRun = $this->indexer->indexFile($this->fixtureLog);
        $this->assertSame(0, $secondRun);
    }
}
