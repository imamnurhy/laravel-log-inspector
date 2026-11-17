<?php

declare(strict_types=1);

namespace Explorin\LogInspector\Enums;

enum LogLevel: string
{
    case EMERGENCY = 'EMERGENCY';
    case ALERT = 'ALERT';
    case CRITICAL = 'CRITICAL';
    case ERROR = 'ERROR';
    case WARNING = 'WARNING';
    case NOTICE = 'NOTICE';
    case INFO = 'INFO';
    case DEBUG = 'DEBUG';

    /**
     * Map case-insensitive string level to enum case.
     */
    public static function tryFromString(?string $level): ?self
    {
        if ($level === null) {
            return null;
        }

        $normalized = strtoupper(trim($level));

        return self::tryFrom($normalized);
    }

    /**
     * Returns numerical severity for sorting/comparisons.
     * Lower number means higher severity.
     */
    public function severity(): int
    {
        return match ($this) {
            self::EMERGENCY => 1,
            self::ALERT => 2,
            self::CRITICAL => 3,
            self::ERROR => 4,
            self::WARNING => 5,
            self::NOTICE => 6,
            self::INFO => 7,
            self::DEBUG => 8,
        };
    }

    /**
     * Badge CSS custom class helper for modern aesthetic styling.
     */
    public function badgeClass(): string
    {
        return match ($this) {
            self::EMERGENCY, self::ALERT, self::CRITICAL => 'li-badge-critical',
            self::ERROR => 'li-badge-error',
            self::WARNING => 'li-badge-warning',
            self::NOTICE, self::INFO => 'li-badge-info',
            self::DEBUG => 'li-badge-debug',
        };
    }
}
