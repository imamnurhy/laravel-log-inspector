<?php

declare(strict_types=1);

namespace Explorin\LogInspector\Contracts;

interface PathGuardInterface
{
    /**
     * Validate that the requested filename/path resolves within one of the allowed directories
     * and is free from path traversal, symlink breakout, and null bytes.
     *
     * @param string $path
     * @param array<int, string> $allowedDirectories
     * @return string Canonical realpath of the validated file.
     * @throws \Explorin\LogInspector\Exceptions\SecurityException
     */
    public function validate(string $path, array $allowedDirectories): string;

    /**
     * Determine if a given file path is an active log file that must not be deleted.
     */
    public function isActiveLogFile(string $filePath): bool;
}
