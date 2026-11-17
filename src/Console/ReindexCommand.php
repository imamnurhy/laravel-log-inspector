<?php

declare(strict_types=1);

namespace Explorin\LogInspector\Console;

use Explorin\LogInspector\Contracts\LogIndexInterface;
use Explorin\LogInspector\Contracts\PathGuardInterface;
use Illuminate\Console\Command;

final class ReindexCommand extends Command
{
    protected $signature = 'log-inspector:reindex
                            {file? : Specific log filename to rebuild index (optional)}';

    protected $description = 'Rebuild or update incremental sidecar index for log files';

    public function handle(
        LogIndexInterface $indexer,
        PathGuardInterface $pathGuard
    ): int {
        $file = $this->argument('file');
        $allowedDirs = (array) config('log-inspector.paths', []);

        if ($file !== null) {
            $validated = $pathGuard->validate((string) $file, $allowedDirs);
            $this->info(sprintf('Reindexing [%s]...', basename($validated)));
            $count = $indexer->indexFile($validated, forceReindex: true);
            $this->info(sprintf('Completed. %d entries indexed.', $count));
            return self::SUCCESS;
        }

        // Reindex all files in allowed directories
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
                $ext = strtolower(pathinfo($filePath, PATHINFO_EXTENSION));
                if (! in_array($ext, ['log', 'txt'], true)) {
                    continue;
                }

                $this->line(sprintf('Indexing %s...', $item));
                $count = $indexer->indexFile($filePath);
                $this->info(sprintf(' -> %d entries indexed.', $count));
            }
        }

        $this->info('Reindex finished for all files.');

        return self::SUCCESS;
    }
}
