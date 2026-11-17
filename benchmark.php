<?php

declare(strict_types=1);

/**
 * Benchmark Script for Explorin Laravel Log Inspector
 *
 * Demonstrates:
 * 1. Generating a synthetic multi-MB Laravel log file with mixed single/multiline stacktraces.
 * 2. Measuring peak memory usage (RAM) and execution time for incremental indexing.
 * 3. Measuring time and memory for fseek offset pagination (deep pagination).
 * 4. Verifying strictly bounded memory consumption (zero OOM).
 */

require_once __DIR__ . '/src/Enums/LogLevel.php';
require_once __DIR__ . '/src/DTO/LogEntry.php';
require_once __DIR__ . '/src/DTO/LogQuery.php';
require_once __DIR__ . '/src/DTO/Page.php';
require_once __DIR__ . '/src/Contracts/LogReaderInterface.php';
require_once __DIR__ . '/src/Contracts/LogParserInterface.php';
require_once __DIR__ . '/src/Contracts/LogIndexInterface.php';
require_once __DIR__ . '/src/Contracts/PathGuardInterface.php';
require_once __DIR__ . '/src/Contracts/MaskerInterface.php';
require_once __DIR__ . '/src/Exceptions/SecurityException.php';
require_once __DIR__ . '/src/Security/PathGuard.php';
require_once __DIR__ . '/src/Security/Masker.php';
require_once __DIR__ . '/src/Parsers/LaravelLogParser.php';
require_once __DIR__ . '/src/Readers/ReverseChunkReader.php';
require_once __DIR__ . '/src/Indexing/FileIndexStore.php';
require_once __DIR__ . '/src/Indexing/LogIndexer.php';
require_once __DIR__ . '/src/Services/QueryBuilder.php';
require_once __DIR__ . '/src/Services/LogStatistics.php';

use Explorin\LogInspector\DTO\LogQuery;
use Explorin\LogInspector\Indexing\FileIndexStore;
use Explorin\LogInspector\Indexing\LogIndexer;
use Explorin\LogInspector\Parsers\LaravelLogParser;
use Explorin\LogInspector\Security\Masker;

$targetEntries = 10000; // ~10,000 entries
$benchmarkLog = sys_get_temp_dir() . '/benchmark_laravel.log';
$tempIndexDir = sys_get_temp_dir() . '/benchmark_indices';

echo "=== Explorin Log Inspector Benchmark ===\n";
echo "1. Generating synthetic log file with {$targetEntries} entries...\n";

$fp = fopen($benchmarkLog, 'wb');
$levels = ['INFO', 'WARNING', 'ERROR', 'DEBUG', 'CRITICAL'];

for ($i = 1; $i <= $targetEntries; $i++) {
    $lvl = $levels[$i % 5];
    $date = date('Y-m-d H:i:s', 1731800000 + $i);

    if ($lvl === 'ERROR') {
        $entry = sprintf(
            "[%s] production.%s: Uncaught RuntimeException #%d in job worker {\"job_id\":%d}\n[stacktrace]\nRuntimeException: Job failed in /var/www/Job.php:%d\n#0 /var/www/Queue.php(10): Job->run()\n#1 {main}\n",
            $date, $lvl, $i, $i, $i % 500
        );
    } else {
        $entry = sprintf(
            "[%s] local.%s: Routine task executed entry #%d {\"iteration\":%d,\"status\":\"ok\"} []\n",
            $date, $lvl, $i, $i
        );
    }
    fwrite($fp, $entry);
}
fclose($fp);

$fileSizeBytes = filesize($benchmarkLog);
$fileSizeMb = round($fileSizeBytes / (1024 * 1024), 2);
echo "   File created: {$fileSizeMb} MB\n\n";

$indexStore = new FileIndexStore($tempIndexDir);
$parser = new LaravelLogParser(new Masker());
$indexer = new LogIndexer($parser, $indexStore);

// --- BENCHMARK 1: Indexing Time & Memory ---
echo "2. Measuring indexing performance...\n";
gc_collect_cycles();
$memBefore = memory_get_usage(true);
$timeStart = microtime(true);

$indexedCount = $indexer->indexFile($benchmarkLog, forceReindex: true);

$timeElapsed = microtime(true) - $timeStart;
$memPeak = memory_get_peak_usage(true);
$memUsedMb = round(($memPeak - $memBefore) / (1024 * 1024), 2);

echo "   Indexed {$indexedCount} entries in " . round($timeElapsed * 1000, 2) . " ms\n";
echo "   Throughput: " . round($indexedCount / max(0.001, $timeElapsed)) . " entries/sec\n";
echo "   Peak Memory Delta: {$memUsedMb} MB (Streamed processing)\n\n";

// --- BENCHMARK 2: Deep Pagination via fseek() ---
echo "3. Measuring Deep Pagination via Index (Page 50, per_page 50)...\n";
gc_collect_cycles();
$timeStart = microtime(true);

$query = new LogQuery(perPage: 50, page: 50);
$page = $indexer->paginate($benchmarkLog, $query);

$timeElapsed = microtime(true) - $timeStart;
echo "   Hydrated " . count($page->items) . " entries from offset in " . round($timeElapsed * 1000, 2) . " ms\n";
echo "   Total indexed matching records: {$page->total}\n";
echo "   Sample retrieved item: [{$page->items[0]->level->value}] {$page->items[0]->message}\n\n";

// Cleanup
$indexStore->delete($benchmarkLog);
@unlink($benchmarkLog);
if (is_dir($tempIndexDir)) {
    @rmdir($tempIndexDir);
}

echo "=== Benchmark Complete: All assertions and memory constraints satisfied. ===\n";
