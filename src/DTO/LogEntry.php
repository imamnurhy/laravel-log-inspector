<?php

declare(strict_types=1);

namespace Explorin\LogInspector\DTO;

use DateTimeImmutable;
use Explorin\LogInspector\Enums\LogLevel;
use JsonSerializable;

final class LogEntry implements JsonSerializable
{
    /**
     * @param array<string, mixed> $context
     * @param array<string, mixed> $extra
     * @param array<int, string> $stackTrace
     */
    public function __construct(
        public readonly string $id,
        public readonly DateTimeImmutable $timestamp,
        public readonly string $environment,
        public readonly LogLevel $level,
        public readonly string $channel,
        public readonly string $message,
        public readonly array $context = [],
        public readonly array $extra = [],
        public readonly ?string $exceptionClass = null,
        public readonly ?string $exceptionMessage = null,
        public readonly ?string $exceptionFile = null,
        public readonly ?int $exceptionLine = null,
        public readonly array $stackTrace = [],
        public readonly int $byteOffset = 0,
        public readonly int $byteLength = 0,
        public readonly ?string $raw = null,
    ) {
    }

    /**
     * Compute a stable hash of the exception signature (class + file + line)
     * for grouping similar exceptions across files.
     */
    public function exceptionHash(): ?string
    {
        if ($this->exceptionClass === null) {
            return null;
        }

        $signature = sprintf(
            '%s|%s|%d',
            $this->exceptionClass,
            (string) $this->exceptionFile,
            (int) $this->exceptionLine
        );

        return substr(hash('sha256', $signature), 0, 16);
    }

    /**
     * Determine if this entry represents an exception.
     */
    public function hasException(): bool
    {
        return $this->exceptionClass !== null;
    }

    /**
     * Return formatted file and line string.
     */
    public function exceptionLocation(): ?string
    {
        if ($this->exceptionFile === null) {
            return null;
        }

        return sprintf('%s:%d', $this->exceptionFile, (int) $this->exceptionLine);
    }

    /**
     * Format timestamp according to given format.
     */
    public function formattedTimestamp(string $format = 'Y-m-d H:i:s'): string
    {
        return $this->timestamp->format($format);
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'id' => $this->id,
            'timestamp' => $this->timestamp->format('Y-m-d H:i:s'),
            'environment' => $this->environment,
            'level' => $this->level->value,
            'channel' => $this->channel,
            'message' => $this->message,
            'context' => $this->context,
            'extra' => $this->extra,
            'exception_class' => $this->exceptionClass,
            'exception_message' => $this->exceptionMessage,
            'exception_file' => $this->exceptionFile,
            'exception_line' => $this->exceptionLine,
            'exception_location' => $this->exceptionLocation(),
            'exception_hash' => $this->exceptionHash(),
            'stack_trace' => $this->stackTrace,
            'byte_offset' => $this->byteOffset,
            'byte_length' => $this->byteLength,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public function jsonSerialize(): array
    {
        return $this->toArray();
    }
}
