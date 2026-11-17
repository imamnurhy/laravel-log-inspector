<?php

declare(strict_types=1);

namespace Explorin\LogInspector\Services;

use Explorin\LogInspector\Contracts\LogIndexInterface;
use Explorin\LogInspector\Contracts\MaskerInterface;
use Explorin\LogInspector\Contracts\PathGuardInterface;
use Explorin\LogInspector\Exceptions\SecurityException;
use Generator;
use RuntimeException;
use Symfony\Component\HttpFoundation\StreamedResponse;

final class LogFileManager
{
    /**
     * @param PathGuardInterface $pathGuard
     * @param MaskerInterface $masker
     * @param AuditLogger $auditLogger
     * @param LogIndexInterface $indexer
     * @param array<int, string> $allowedDirectories
     */
    public function __construct(
        private readonly PathGuardInterface $pathGuard,
        private readonly MaskerInterface $masker,
        private readonly AuditLogger $auditLogger,
        private readonly LogIndexInterface $indexer,
        private readonly array $allowedDirectories,
    ) {
    }

    /**
     * Delete an inactive log file and its corresponding sidecar index.
     * Active files (e.g. today's daily log or laravel.log) CANNOT be deleted.
     */
    public function delete(string $filePath, string|int|null $userId = null, ?string $ipAddress = null): bool
    {
        $validated = $this->pathGuard->validate($filePath, $this->allowedDirectories);

        if ($this->pathGuard->isActiveLogFile($validated)) {
            $this->auditLogger->log('delete', basename($validated), $userId, $ipAddress, false, [
                'error' => 'Cannot delete active log file.',
            ]);
            throw new SecurityException(sprintf(
                'Cannot delete active log file [%s]. It is currently being written to.',
                basename($validated)
            ));
        }

        // Clean up sidecar index
        $this->indexer->forgetIndex($validated);

        $deleted = @unlink($validated);

        $this->auditLogger->log('delete', basename($validated), $userId, $ipAddress, $deleted);

        return $deleted;
    }

    /**
     * Create a secure streamed download response for a log file.
     * Automatically applies data masking unless $raw is explicitly requested.
     */
    public function download(
        string $filePath,
        bool $raw = false,
        string|int|null $userId = null,
        ?string $ipAddress = null
    ): StreamedResponse {
        $validated = $this->pathGuard->validate($filePath, $this->allowedDirectories);
        $fileName = basename($validated);
        $downloadName = $raw ? $fileName : 'masked_' . $fileName;

        $this->auditLogger->log('download', $fileName, $userId, $ipAddress, true, ['raw' => $raw]);

        $headers = [
            'Content-Type' => 'text/plain; charset=UTF-8',
            'Content-Disposition' => sprintf('attachment; filename="%s"', $downloadName),
            'X-Content-Type-Options' => 'nosniff',
            'Pragma' => 'no-cache',
            'Expires' => '0',
        ];

        return new StreamedResponse(function () use ($validated, $raw) {
            $handle = fopen($validated, 'rb');
            if ($handle === false) {
                return;
            }

            try {
                while (! feof($handle)) {
                    $buffer = fread($handle, 65536);
                    if ($buffer === false) {
                        break;
                    }

                    if (! $raw && $this->masker->isEnabled()) {
                        echo $this->masker->mask($buffer);
                    } else {
                        echo $buffer;
                    }

                    if (ob_get_level() > 0) {
                        ob_flush();
                    }
                    flush();
                }
            } finally {
                fclose($handle);
            }
        }, 200, $headers);
    }

    /**
     * Archive an inactive log file by compressing it into a .gz file.
     */
    public function archive(string $filePath): string
    {
        $validated = $this->pathGuard->validate($filePath, $this->allowedDirectories);

        if (str_ends_with(strtolower($validated), '.gz')) {
            throw new RuntimeException('File is already archived (.gz).');
        }

        if ($this->pathGuard->isActiveLogFile($validated)) {
            throw new SecurityException('Cannot archive an active log file.');
        }

        $gzPath = $validated . '.gz';
        $gz = gzopen($gzPath, 'wb9');
        if ($gz === false) {
            throw new RuntimeException(sprintf('Failed to create archive file: %s', $gzPath));
        }

        $src = fopen($validated, 'rb');
        if ($src === false) {
            gzclose($gz);
            throw new RuntimeException(sprintf('Failed to read source file: %s', $validated));
        }

        while (! feof($src)) {
            $chunk = fread($src, 65536);
            if ($chunk === false) {
                break;
            }
            gzwrite($gz, $chunk);
        }

        fclose($src);
        gzclose($gz);

        // Remove original and its sidecar index
        $this->indexer->forgetIndex($validated);
        @unlink($validated);

        return $gzPath;
    }
}
