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

final class LaravelLogParser implements LogParserInterface
{
    /**
     * Regex matching the start of a standard Laravel / Monolog LineFormatter entry.
     * e.g. [2026-11-17 10:30:00] local.ERROR: Call to undefined method ...
     */
    public const ENTRY_REGEX = '/^\[(?<datetime>\d{4}-\d{2}-\d{2}[ T]\d{2}:\d{2}:\d{2}(?:\.\d+)?(?:[+-]\d{2}:?\d{2}|Z)?)\]\s+(?:(?<env>[a-zA-Z0-9_\-]+)\.)?(?<level>[a-zA-Z]+)(?::\s+|\s+-\s+)(?<message>.*)$/s';

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
        // Must start with bracket followed by date
        if (! str_starts_with($line, '[')) {
            return false;
        }

        return (bool) preg_match('/^\[\d{4}-\d{2}-\d{2}[ T]\d{2}:\d{2}:\d{2}/', $line);
    }

    /**
     * {@inheritdoc}
     */
    public function parse(string $rawText, int $byteOffset = 0, int $byteLength = 0): LogEntry
    {
        $textToParse = $this->masker !== null ? $this->masker->mask($rawText) : $rawText;
        $lines = explode("\n", $textToParse);
        $firstLine = rtrim($lines[0] ?? '', "\r");

        if (! preg_match(self::ENTRY_REGEX, $firstLine, $matches)) {
            // Unparsed / raw fallback entry
            return new LogEntry(
                id: $this->generateId($byteOffset, $byteLength),
                timestamp: new DateTimeImmutable('now', $this->timeZone),
                environment: 'unknown',
                level: LogLevel::INFO,
                channel: 'default',
                message: $firstLine,
                byteOffset: $byteOffset,
                byteLength: $byteLength,
                raw: $rawText
            );
        }

        $datetimeStr = $matches['datetime'];
        $env = ! empty($matches['env']) ? $matches['env'] : 'local';
        $levelStr = $matches['level'];
        $level = LogLevel::tryFromString($levelStr) ?? LogLevel::INFO;
        $messageAndPayload = $matches['message'];

        $timestamp = $this->parseDateTime($datetimeStr);

        // Separate message and remaining lines (which may contain context and stack trace)
        $subsequentLines = array_slice($lines, 1);
        $remainingText = implode("\n", $subsequentLines);

        $parsedPayload = $this->extractContextAndException($messageAndPayload, $remainingText);

        return new LogEntry(
            id: $this->generateId($byteOffset, $byteLength),
            timestamp: $timestamp,
            environment: $env,
            level: $level,
            channel: $parsedPayload['channel'] ?? 'default',
            message: $parsedPayload['message'],
            context: $parsedPayload['context'],
            extra: $parsedPayload['extra'],
            exceptionClass: $parsedPayload['exception_class'],
            exceptionMessage: $parsedPayload['exception_message'],
            exceptionFile: $parsedPayload['exception_file'],
            exceptionLine: $parsedPayload['exception_line'],
            stackTrace: $parsedPayload['stack_trace'],
            byteOffset: $byteOffset,
            byteLength: $byteLength,
            raw: $rawText
        );
    }

    /**
     * Extract context JSON, exception details, and stack trace lines.
     *
     * @return array<string, mixed>
     */
    private function extractContextAndException(string $firstLineMessage, string $remainingText): array
    {
        $channel = 'default';
        $message = trim($firstLineMessage);
        $context = [];
        $extra = [];
        $exceptionClass = null;
        $exceptionMessage = null;
        $exceptionFile = null;
        $exceptionLine = null;
        $stackTrace = [];

        // Check if channel is prefixed in message e.g. [channel_name] or channel.LEVEL
        if (preg_match('/^\[(?<ch>[a-zA-Z0-9_\-]+)\]\s+(.*)$/', $message, $chMatch)) {
            $channel = $chMatch['ch'];
            $message = $chMatch[2];
        }

        $fullBody = $message . ($remainingText !== '' ? "\n" . $remainingText : '');

        // Extract Exception pattern: [stacktrace] or Exception: message in /path/to/file:123
        if (preg_match('/(?:\[stacktrace\]|\b(?<exClass>[A-Za-z0-9_\\\\]+(?:Exception|Error))\s*:\s*(?<exMsg>[^\n]+)\s+in\s+(?<exFile>\/[^\s:]+):(?<exLine>\d+))/m', $fullBody, $exMatches)) {
            if (isset($exMatches['exClass'])) {
                $exceptionClass = $exMatches['exClass'];
                $exceptionMessage = trim($exMatches['exMsg'] ?? '');
                $exceptionFile = $exMatches['exFile'] ?? null;
                $exceptionLine = isset($exMatches['exLine']) ? (int) $exMatches['exLine'] : null;
            }
        }

        // Extract stack trace frames (#0 /path/to/file(123): ...)
        if (preg_match_all('/^#\d+\s+.*$/m', $fullBody, $traceMatches)) {
            $stackTrace = array_map('trim', $traceMatches[0]);
        }

        // Try extracting JSON context object at the end of first line or body
        // Standard Monolog format ends with: message {context_json} {extra_json}
        if (preg_match('/(?<msg>.*?)(?<json>\{.*\})\s*$/s', $firstLineMessage, $jsonMatch)) {
            $decoded = json_decode($jsonMatch['json'], true);
            if (is_array($decoded)) {
                $context = $decoded;
                $message = trim($jsonMatch['msg']);
            }
        }

        return [
            'channel' => $channel,
            'message' => $message,
            'context' => $context,
            'extra' => $extra,
            'exception_class' => $exceptionClass,
            'exception_message' => $exceptionMessage,
            'exception_file' => $exceptionFile,
            'exception_line' => $exceptionLine,
            'stack_trace' => $stackTrace,
        ];
    }

    private function parseDateTime(string $datetimeStr): DateTimeImmutable
    {
        try {
            return new DateTimeImmutable($datetimeStr, $this->timeZone);
        } catch (Throwable) {
            return new DateTimeImmutable('now', $this->timeZone);
        }
    }

    private function generateId(int $byteOffset, int $byteLength): string
    {
        return sprintf('%x-%x', $byteOffset, $byteLength);
    }
}
