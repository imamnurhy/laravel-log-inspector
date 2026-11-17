<?php

declare(strict_types=1);

namespace Explorin\LogInspector\Console;

use Explorin\LogInspector\Contracts\PathGuardInterface;
use Explorin\LogInspector\Services\AuditLogger;
use Explorin\LogInspector\Services\LogFileManager;
use Illuminate\Console\Command;

final class RetentionCommand extends Command
{
    protected $signature = 'log-inspector:retention
                            {--days=30 : Delete log files older than N days}
                            {--force : Force deletion without prompt (disable dry-run)}';

    protected $description = 'Clean up old log files based on retention days (Dry-run by default)';

    public function handle(
        PathGuardInterface $pathGuard,
        LogFileManager $fileManager,
        AuditLogger $auditLogger
    ): int {
        $days = (int) $this->option('days');
        $force = (bool) $this->option('force');
        $cutoffTime = time() - ($days * 86400);

        $allowedDirs = (array) config('log-inspector.paths', []);
        $filesToDelete = [];

        foreach ($allowedDirs as $dir) {
            $realDir = realpath($dir);
            if ($realDir === false || ! is_dir($realDir)) {
                continue;
            }

            $items = scandir($realDir) ?: [];
            foreach ($items as $item) {
                if ($item === '.' || $item === '..') {
                    continue;
                }

                $filePath = $realDir . DIRECTORY_SEPARATOR . $item;
                if (! is_file($filePath)) {
                    continue;
                }

                // Never target active log files
                if ($pathGuard->isActiveLogFile($filePath)) {
                    continue;
                }

                $mtime = filemtime($filePath);
                if ($mtime !== false && $mtime < $cutoffTime) {
                    $filesToDelete[] = $filePath;
                }
            }
        }

        if (empty($filesToDelete)) {
            $this->info(sprintf('No log files older than %d days found.', $days));
            return self::SUCCESS;
        }

        $this->table(
            ['File Path', 'Size', 'Last Modified'],
            array_map(static fn ($f) => [
                basename($f),
                round(filesize($f) / 1024, 1) . ' KB',
                date('Y-m-d H:i:s', (int) filemtime($f)),
            ], $filesToDelete)
        );

        if (! $force) {
            $this->warn(sprintf(
                '[DRY RUN] %d log file(s) would be deleted. Use --force to execute actual deletion.',
                count($filesToDelete)
            ));
            return self::SUCCESS;
        }

        if (! $this->confirm(sprintf('Are you sure you want to delete %d file(s)?', count($filesToDelete)))) {
            $this->info('Operation cancelled.');
            return self::SUCCESS;
        }

        $deletedCount = 0;
        foreach ($filesToDelete as $file) {
            try {
                $fileManager->delete($file, 'cli');
                $deletedCount++;
                $this->line(sprintf('Deleted: %s', basename($file)));
            } catch (\Throwable $e) {
                $this->error(sprintf('Failed to delete %s: %s', basename($file), $e->getMessage()));
            }
        }

        $this->info(sprintf('Successfully cleaned up %d file(s).', $deletedCount));

        return self::SUCCESS;
    }
}
