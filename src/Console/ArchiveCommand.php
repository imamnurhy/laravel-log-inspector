<?php

declare(strict_types=1);

namespace Explorin\LogInspector\Console;

use Explorin\LogInspector\Services\LogFileManager;
use Illuminate\Console\Command;

final class ArchiveCommand extends Command
{
    protected $signature = 'log-inspector:archive
                            {file : Log filename or relative path to compress into .gz}';

    protected $description = 'Compress an inactive log file into .gz format and remove original';

    public function handle(LogFileManager $fileManager): int
    {
        $file = (string) $this->argument('file');

        $this->info(sprintf('Archiving log file [%s]...', $file));

        try {
            $gzPath = $fileManager->archive($file);
            $this->info(sprintf('Archived successfully to: %s', $gzPath));
            return self::SUCCESS;
        } catch (\Throwable $e) {
            $this->error(sprintf('Archive failed: %s', $e->getMessage()));
            return self::FAILURE;
        }
    }
}
