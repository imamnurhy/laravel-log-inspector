<?php

declare(strict_types=1);

namespace Explorin\LogInspector\Tests\Unit;

use Explorin\LogInspector\Exceptions\SecurityException;
use Explorin\LogInspector\Indexing\FileIndexStore;
use Explorin\LogInspector\Indexing\LogIndexer;
use Explorin\LogInspector\Parsers\LaravelLogParser;
use Explorin\LogInspector\Security\Masker;
use Explorin\LogInspector\Security\PathGuard;
use Explorin\LogInspector\Services\AuditLogger;
use Explorin\LogInspector\Services\LogFileManager;
use PHPUnit\Framework\TestCase;

final class LogFileManagerTest extends TestCase
{
    private string $tempDir;
    private LogFileManager $manager;

    protected function setUp(): void
    {
        parent::setUp();
        $this->tempDir = sys_get_temp_dir() . '/lfm_test_' . uniqid();
        mkdir($this->tempDir, 0755, true);

        $pathGuard = new PathGuard(['laravel.log'], 300);
        $masker = new Masker(enabled: true);
        $audit = new AuditLogger(enabled: false);
        $indexStore = new FileIndexStore($this->tempDir . '/indices');
        $indexer = new LogIndexer(new LaravelLogParser(), $indexStore);

        $this->manager = new LogFileManager(
            pathGuard: $pathGuard,
            masker: $masker,
            auditLogger: $audit,
            indexer: $indexer,
            allowedDirectories: [$this->tempDir]
        );
    }

    protected function tearDown(): void
    {
        $files = glob($this->tempDir . '/*');
        foreach ($files as $f) {
            if (is_file($f)) {
                @unlink($f);
            }
        }
        @rmdir($this->tempDir);
        parent::tearDown();
    }

    public function test_forbids_deleting_active_log_file(): void
    {
        $activeFile = $this->tempDir . '/laravel.log';
        file_put_contents($activeFile, 'log entry');

        $this->expectException(SecurityException::class);
        $this->expectExceptionMessage('Cannot delete active log file');

        $this->manager->delete($activeFile);
    }

    public function test_allows_deleting_inactive_log_file(): void
    {
        $inactiveFile = $this->tempDir . '/laravel-2020-01-01.log';
        file_put_contents($inactiveFile, 'old log entry');
        // Backdate mtime
        touch($inactiveFile, time() - 100000);

        $result = $this->manager->delete($inactiveFile);

        $this->assertTrue($result);
        $this->assertFileDoesNotExist($inactiveFile);
    }

    public function test_archives_inactive_log_file_to_gzip(): void
    {
        $oldFile = $this->tempDir . '/laravel-2021-05-01.log';
        file_put_contents($oldFile, 'data to compress in log');
        touch($oldFile, time() - 100000);

        $gzPath = $this->manager->archive($oldFile);

        $this->assertFileExists($gzPath);
        $this->assertFileDoesNotExist($oldFile);
        $this->assertStringEndsWith('.gz', $gzPath);
    }
}
