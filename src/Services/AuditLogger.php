<?php

declare(strict_types=1);

namespace Explorin\LogInspector\Services;

use Illuminate\Support\Facades\Log;

final class AuditLogger
{
    public function __construct(
        private readonly bool $enabled = true,
        private readonly ?string $channel = null,
    ) {
    }

    /**
     * Log an administrative / security action to the audit trail.
     *
     * @param string $action 'download', 'delete', 'retention', 'archive'
     * @param string $file Filename affected
     * @param string|int|null $userId User ID or 'system' / 'cli'
     * @param string|null $ipAddress Client IP address
     * @param bool $success Whether the operation succeeded
     * @param array<string, mixed> $extra
     */
    public function log(
        string $action,
        string $file,
        string|int|null $userId,
        ?string $ipAddress = null,
        bool $success = true,
        array $extra = []
    ): void {
        if (! $this->enabled) {
            return;
        }

        $message = sprintf(
            '[LogInspector Audit] Action: %s | File: %s | User: %s | IP: %s | Status: %s',
            strtoupper($action),
            $file,
            (string) ($userId ?? 'guest/cli'),
            (string) ($ipAddress ?? 'N/A'),
            $success ? 'SUCCESS' : 'FAILED'
        );

        $payload = array_merge([
            'action' => $action,
            'file' => $file,
            'user_id' => $userId,
            'ip' => $ipAddress,
            'success' => $success,
            'timestamp' => date('Y-m-d H:i:s'),
        ], $extra);

        if ($this->channel !== null && $this->channel !== '') {
            Log::channel($this->channel)->info($message, $payload);
        } else {
            Log::info($message, $payload);
        }
    }
}
