<?php

declare(strict_types=1);

namespace Explorin\LogInspector\Console;

use Explorin\LogInspector\Contracts\AccessStoreInterface;
use Explorin\LogInspector\Services\AuditLogger;
use Illuminate\Console\Command;

final class RevokeLinkCommand extends Command
{
    protected $signature = 'log-inspector:revoke
                            {id? : Short ID of the link or session to revoke}
                            {--all : Revoke ALL active links and sessions (Emergency)}
                            {--expired : Clean up expired entries from the registry}';

    protected $description = 'Revoke a specific link/session or purge all active access';

    public function handle(AccessStoreInterface $accessStore, AuditLogger $auditLogger): int
    {
        $id = $this->argument('id');
        $all = (bool) $this->option('all');
        $expired = (bool) $this->option('expired');

        if ($all) {
            $count = $accessStore->revokeAll();
            $auditLogger->log('revoke_all', 'N/A', get_current_user() ?: 'cli', null, true, ['count' => $count]);
            $this->info(sprintf('Successfully revoked %d active link(s) and session(s).', $count));
            return self::SUCCESS;
        }

        if ($expired) {
            $count = $accessStore->pruneExpired();
            $this->info(sprintf('Pruned %d expired entries from registry.', $count));
            return self::SUCCESS;
        }

        if ($id === null) {
            $this->error('Please specify an ID to revoke or use --all / --expired.');
            return self::FAILURE;
        }

        $revoked = $accessStore->revokeById((string) $id);

        if ($revoked) {
            $auditLogger->log('revoke_single', 'N/A', get_current_user() ?: 'cli', null, true, ['id' => $id]);
            $this->info(sprintf('Successfully revoked entry [%s]. Access terminated immediately.', $id));
            return self::SUCCESS;
        }

        $this->error(sprintf('Entry with ID [%s] not found or already expired.', $id));

        return self::FAILURE;
    }
}
