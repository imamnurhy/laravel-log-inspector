<?php

declare(strict_types=1);

namespace Explorin\LogInspector\Contracts;

interface MaskerInterface
{
    /**
     * Mask sensitive secrets and PII from a raw log string.
     */
    public function mask(string $content): string;

    /**
     * Check if masking is globally enabled.
     */
    public function isEnabled(): bool;
}
