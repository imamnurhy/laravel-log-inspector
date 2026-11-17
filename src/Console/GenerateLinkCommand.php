<?php

declare(strict_types=1);

namespace Explorin\LogInspector\Console;

use Explorin\LogInspector\Contracts\AccessStoreInterface;
use Explorin\LogInspector\Services\AuditLogger;
use Illuminate\Console\Command;

final class GenerateLinkCommand extends Command
{
    protected $signature = 'log-inspector:link
                            {--minutes= : Validity TTL in minutes (default 10)}
                            {--ip= : Restrict to specific IP (or "auto" for fixed_ip)}';

    protected $description = 'Generate a temporary single-use access link for the dashboard';

    public function handle(AccessStoreInterface $accessStore, AuditLogger $auditLogger): int
    {
        $enabled = (bool) config('log-inspector.enabled', false);
        if (! $enabled) {
            $this->error('Log Inspector is disabled in configuration (LOG_INSPECTOR_ENABLED=false).');
            return self::FAILURE;
        }

        $accessMode = (string) config('log-inspector.access.mode', 'link');
        if (! in_array($accessMode, ['link', 'both'], true)) {
            $this->error(sprintf('Current access mode [%s] does not support temporary links.', $accessMode));
            return self::FAILURE;
        }

        // Active link count threshold check
        $maxActive = (int) config('log-inspector.access.max_active', 3);
        $activeEntries = $accessStore->listActive();
        $activeLinksCount = count(array_filter($activeEntries, static fn ($e) => ($e['type'] ?? '') === 'link'));

        if ($activeLinksCount >= $maxActive) {
            $this->error(sprintf('Maximum active links limit reached (%d). Revoke old links first.', $maxActive));
            return self::FAILURE;
        }

        // Minutes TTL validation
        $defaultTtl = (int) config('log-inspector.access.default_ttl_minutes', 10);
        $maxTtl = (int) config('log-inspector.access.max_ttl_minutes', 60);
        $requestedMinutes = $this->option('minutes') !== null ? (int) $this->option('minutes') : $defaultTtl;

        if ($requestedMinutes <= 0 || $requestedMinutes > $maxTtl) {
            $this->error(sprintf('Minutes must be between 1 and %d.', $maxTtl));
            return self::FAILURE;
        }

        // IP Binding mode handling
        $ipOption = $this->option('ip');
        $ipMode = 'first-use';
        $allowedIp = null;

        if ($ipOption === 'auto') {
            $ipMode = 'auto';
            $allowedIp = (string) config('log-inspector.access.fixed_ip');
            if ($allowedIp === '') {
                $this->error('access.fixed_ip is not configured in config/log-inspector.php or .env.');
                return self::FAILURE;
            }
        } elseif ($ipOption !== null) {
            $ipMode = 'fixed';
            if (filter_var($ipOption, FILTER_VALIDATE_IP) === false) {
                $this->error(sprintf('Invalid IP address format: %s', $ipOption));
                return self::FAILURE;
            }
            $allowedIp = $ipOption;
        }

        // Generate 256-bit cryptographically secure token
        $rawToken = bin2hex(random_bytes(32)); // 64 hex characters (256 bits)
        $tokenHash = hash('sha256', $rawToken);
        $shortId = substr($tokenHash, 0, 8);

        $ttlSeconds = $requestedMinutes * 60;
        $expiresAt = time() + $ttlSeconds;
        $osUser = get_current_user() ?: 'cli';

        $accessStore->storeLink($tokenHash, [
            'id' => $shortId,
            'created_at' => time(),
            'expires_at' => $expiresAt,
            'ip_mode' => $ipMode,
            'allowed_ip' => $allowedIp,
            'os_user' => $osUser,
        ], $ttlSeconds);

        $auditLogger->log('link_created', 'N/A', $osUser, null, true, [
            'short_id' => $shortId,
            'ip_mode' => $ipMode,
            'allowed_ip' => $allowedIp,
            'expires_at' => date('Y-m-d H:i:s', $expiresAt),
        ]);

        // Base domain resolution: strictly from config('app.url')
        $baseUrl = rtrim((string) config('app.url', 'http://localhost'), '/');
        $path = trim((string) config('log-inspector.path', 'log-inspector'), '/');
        $url = sprintf('%s/%s/access/%s', $baseUrl, $path, $rawToken);

        $this->info('====================================================');
        $this->info('     🪵 Log Inspector Temporary Access Link');
        $this->info('====================================================');
        $this->line(sprintf('URL:         <comment>%s</comment>', $url));
        $this->line(sprintf('ID:          %s', $shortId));
        $this->line(sprintf('Expires In:  %d minutes (%s)', $requestedMinutes, date('Y-m-d H:i:s', $expiresAt)));
        $this->line(sprintf('IP Mode:     %s %s', $ipMode, $allowedIp ? "({$allowedIp})" : '(locks to first IP that clicks)'));
        $this->warn('Notice: Token is single-use and will be consumed immediately upon session creation.');
        $this->info('====================================================');

        return self::SUCCESS;
    }
}
