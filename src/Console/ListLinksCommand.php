<?php

declare(strict_types=1);

namespace Explorin\LogInspector\Console;

use Explorin\LogInspector\Contracts\AccessStoreInterface;
use Illuminate\Console\Command;

final class ListLinksCommand extends Command
{
    protected $signature = 'log-inspector:links';

    protected $description = 'List all active temporary access links and active sessions';

    public function handle(AccessStoreInterface $accessStore): int
    {
        $active = $accessStore->listActive();

        if (empty($active)) {
            $this->info('No active temporary links or sessions found.');
            return self::SUCCESS;
        }

        $now = time();
        $rows = [];

        foreach ($active as $entry) {
            $expiresAt = (int) ($entry['expires_at'] ?? 0);
            $remainingSeconds = max(0, $expiresAt - $now);
            $remainingMinutes = (int) ceil($remainingSeconds / 60);

            $rows[] = [
                'id' => $entry['id'] ?? 'unknown',
                'type' => strtoupper((string) ($entry['type'] ?? 'link')),
                'created' => date('Y-m-d H:i:s', (int) ($entry['created_at'] ?? 0)),
                'expires_at' => date('Y-m-d H:i:s', $expiresAt),
                'status' => $remainingSeconds > 0 ? "Active ({$remainingMinutes}m left)" : 'Expired',
                'ip' => (string) ($entry['ip'] ?? '-'),
                'os_user' => (string) ($entry['os_user'] ?? '-'),
            ];
        }

        $this->table(
            ['ID', 'Type', 'Created', 'Expires At', 'Status', 'IP', 'Created By'],
            $rows
        );

        return self::SUCCESS;
    }
}
