<?php

declare(strict_types=1);

namespace Explorin\LogInspector\Parsers;

use DateTimeImmutable;
use DateTimeZone;
use Explorin\LogInspector\Contracts\LogParserInterface;
use Explorin\LogInspector\Contracts\MaskerInterface;
use Explorin\LogInspector\DTO\LogEntry;
use Explorin\LogInspector\Enums\LogLevel;
use Throwable;

final class JsonLogParser implements LogParserInterface
{
    public function __construct(
        private readonly ?MaskerInterface $masker = null,
        private readonly ?DateTimeZone $timeZone = null,
    ) {
    }

    /**
     * {@inheritdoc}
     */
    public function isEntryStart(string $line): bool
    {
        $trimmed = ltrim($line);

        return str_starts_with($trimmed, '{');
    }

    /**
     * {@inheritdoc}
     */
    public function parse(string $rawText, int $byteOffset = 0, int $byteLength = 0): LogEntry
    {
        $textToParse = $this->masker !== null ? $this->masker->mask($rawText) : $rawText;
        $data = json_decode(trim($textToParse), true);

        if (! is_array($data)) {
            return new LogEntry(
                id: sprintf('%x-%x', $byteOffset, $byteLength),
                timestamp: new DateTimeImmutable('now', $this->timeZone),
                environment: 'unknown',
                level: LogLevel::INFO,
                channel: 'default',
                message: $rawText,
                byteOffset: $byteOffset,
                byteLength: $byteLength,
                raw: $rawText
            );
        }

        // Monolog JsonFormatter field conventions
        $datetimeStr = $data['datetime'] ?? $data['timestamp'] ?? 'now';
        $timestamp = $this->parseDateTime((string) $datetimeStr);

        $levelStr = $data['level_name'] ?? $data['level'] ?? 'INFO';
        $level = LogLevel::tryFromString((string) $levelStr) ?? LogLevel::INFO;

        $channel = (string) ($data['channel'] ?? 'default');
        $environment = (string) ($data['extra']['environment'] ?? $data['environment'] ?? 'local');
        $message = (string) ($data['message'] ?? '');
        $context = is_array($data['context'] ?? null) ? $data['context'] : [];
        $extra = is_array($data['extra'] ?? null) ? $data['extra'] : [];

        // Check for exception in context
        $exceptionClass = null;
        $exceptionMessage = null;
        $exceptionFile = null;
        $exceptionLine = null;
        $stackTrace = [];

        if (isset($context['exception']) && is_array($context['exception'])) {
            $ex = $context['exception'];
            $exceptionClass = $ex['class'] ?? null;
            $exceptionMessage = $ex['message'] ?? null;
            $exceptionFile = $ex['file'] ?? null;
            $exceptionLine = isset($ex['line']) ? (int) $ex['line'] : null;
            $stackTrace = is_array($ex['trace'] ?? null) ? $ex['trace'] : [];
        }

        return new LogEntry(
            id: sprintf('%x-%x', $byteOffset, $byteLength),
            timestamp: $timestamp,
            environment: $environment,
            level: $level,
            channel: $channel,
            message: $message,
            context: $context,
            extra: $extra,
            exceptionClass: $exceptionClass,
            exceptionMessage: $exceptionMessage,
            exceptionFile: $exceptionFile,
            exceptionLine: $exceptionLine,
            stackTrace: $stackTrace,
            byteOffset: $byteOffset,
            byteLength: $byteLength,
            raw: $rawText
        );
    }

    private function parseDateTime(string $datetimeStr): DateTimeImmutable
    {
        try {
            return new DateTimeImmutable($datetimeStr, $this->timeZone);
        } catch (Throwable) {
            return new DateTimeImmutable('now', $this->timeZone);
        }
    }
}
