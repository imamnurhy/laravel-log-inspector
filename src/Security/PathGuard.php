<?php

declare(strict_types=1);

namespace Explorin\LogInspector\Security;

use Explorin\LogInspector\Contracts\PathGuardInterface;
use Explorin\LogInspector\Exceptions\SecurityException;

final class PathGuard implements PathGuardInterface
{
    /**
     * @param array<int, string> $activeLogNames Explicit names considered active, e.g. ['laravel.log']
     * @param int $activeMtimeThresholdSeconds Files modified within this time window are considered active
     */
    public function __construct(
        private readonly array $activeLogNames = ['laravel.log'],
        private readonly int $activeMtimeThresholdSeconds = 300,
    ) {
    }

    /**
     * {@inheritdoc}
     */
    public function validate(string $path, array $allowedDirectories): string
    {
        // 1. Guard against null byte injection
        if (str_contains($path, "\0")) {
            throw new SecurityException('Null byte injection detected.');
        }

        // 2. Guard against directory traversal in base input
        if (str_contains($path, '../') || str_contains($path, '..\\')) {
            throw new SecurityException('Directory traversal (..) detected.');
        }

        // Whitelist safe file extensions
        $extension = strtolower(pathinfo($path, PATHINFO_EXTENSION));
        if (! in_array($extension, ['log', 'gz', 'txt'], true)) {
            throw new SecurityException(sprintf('Disallowed file extension: %s', $extension));
        }

        // Check if path is absolute or relative
        $resolvedCandidate = null;
        if (file_exists($path)) {
            $resolvedCandidate = realpath($path);
        } else {
            // Check within allowed directories
            foreach ($allowedDirectories as $dir) {
                $joined = rtrim($dir, DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR . ltrim($path, DIRECTORY_SEPARATOR);
                if (file_exists($joined)) {
                    $resolvedCandidate = realpath($joined);
                    break;
                }
            }
        }

        if ($resolvedCandidate === false || $resolvedCandidate === null) {
            throw new SecurityException(sprintf('File does not exist or cannot be accessed: %s', $path));
        }

        // 3. Ensure canonical path is strictly inside an allowed whitelist directory
        $insideAllowedDirectory = false;
        foreach ($allowedDirectories as $dir) {
            $realDir = realpath($dir);
            if ($realDir !== false) {
                $dirPrefix = rtrim($realDir, DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR;
                if (str_starts_with($resolvedCandidate, $dirPrefix) || $resolvedCandidate === $realDir) {
                    $insideAllowedDirectory = true;
                    break;
                }
            }
        }

        if (! $insideAllowedDirectory) {
            throw new SecurityException(sprintf('Access outside allowed log directories is forbidden: %s', $path));
        }

        return $resolvedCandidate;
    }

    /**
     * {@inheritdoc}
     */
    public function isActiveLogFile(string $filePath): bool
    {
        if (! file_exists($filePath)) {
            return false;
        }

        $baseName = basename($filePath);

        // 1. Check against explicit static active filenames (e.g. laravel.log)
        if (in_array($baseName, $this->activeLogNames, true)) {
            return true;
        }

        // 2. Check against daily log convention for today (e.g. laravel-2026-11-17.log)
        $todayDate = date('Y-m-d');
        if (str_contains($baseName, $todayDate)) {
            return true;
        }

        // 3. Check recent modification time threshold (file actively being appended to)
        $mtime = filemtime($filePath);
        if ($mtime !== false && (time() - $mtime) < $this->activeMtimeThresholdSeconds) {
            return true;
        }

        return false;
    }
}
