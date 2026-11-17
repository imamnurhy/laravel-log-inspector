<?php

declare(strict_types=1);

namespace Explorin\LogInspector\Tests\Unit;

use Explorin\LogInspector\Parsers\LaravelLogParser;
use Explorin\LogInspector\Readers\ReverseChunkReader;
use PHPUnit\Framework\TestCase;

final class ReverseChunkReaderTest extends TestCase
{
    private string $fixtureDir;
    private ReverseChunkReader $reader;

    protected function setUp(): void
    {
        parent::setUp();
        $this->fixtureDir = realpath(__DIR__ . '/../Fixtures') ?: __DIR__ . '/../Fixtures';
        $parser = new LaravelLogParser();
        // Use small chunk size (64 bytes) to explicitly stress-test chunk boundaries splitting multiline entries
        $this->reader = new ReverseChunkReader($parser, chunkSize: 64);
    }

    public function test_reads_entries_in_reverse_order(): void
    {
        $filePath = $this->fixtureDir . '/single.log';
        $entries = iterator_to_array($this->reader->read($filePath));

        $this->assertCount(2, $entries);
        // Newest entry first
        $this->assertSame('High memory usage detected', $entries[0]->message);
        $this->assertSame('Application booted successfully.', $entries[1]->message);
    }

    public function test_handles_multiline_and_stacktraces_across_tiny_chunks(): void
    {
        $filePath = $this->fixtureDir . '/multiline.log';
        $entries = iterator_to_array($this->reader->read($filePath));

        $this->assertCount(2, $entries);
        // First entry in reverse is the critical error
        $this->assertSame('Database connection failed', $entries[0]->message);
        // Second entry in reverse is the multiline runtime exception with stacktrace
        $this->assertTrue($entries[1]->hasException());
        $this->assertSame('RuntimeException', $entries[1]->exceptionClass);
        $this->assertCount(3, $entries[1]->stackTrace);
    }

    public function test_respects_max_entries_limit(): void
    {
        $filePath = $this->fixtureDir . '/single.log';
        $entries = iterator_to_array($this->reader->read($filePath, maxEntries: 1));

        $this->assertCount(1, $entries);
        $this->assertSame('High memory usage detected', $entries[0]->message);
    }
}
