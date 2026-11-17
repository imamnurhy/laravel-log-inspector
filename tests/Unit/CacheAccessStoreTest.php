<?php

declare(strict_types=1);

namespace Explorin\LogInspector\Tests\Unit;

use Explorin\LogInspector\Contracts\AccessStoreInterface;
use Explorin\LogInspector\Security\CacheAccessStore;
use Illuminate\Cache\ArrayStore;
use Illuminate\Cache\Repository;
use PHPUnit\Framework\TestCase;

final class CacheAccessStoreTest extends TestCase
{
    private AccessStoreInterface $store;

    protected function setUp(): void
    {
        parent::setUp();
        $cache = new Repository(new ArrayStore());
        $this->store = new CacheAccessStore($cache, maxAttempts: 3, lockoutMinutes: 5);
    }

    public function test_stores_and_retrieves_link_by_hash(): void
    {
        $token = 'test_token_secret_string_1234567890';
        $tokenHash = hash('sha256', $token);

        $this->store->storeLink($tokenHash, [
            'id' => 'abc12345',
            'created_at' => time(),
            'expires_at' => time() + 600,
            'ip_mode' => 'first-use',
        ], 600);

        $retrieved = $this->store->getLink($tokenHash);
        $this->assertNotNull($retrieved);
        $this->assertSame('abc12345', $retrieved['id']);

        // Forget link
        $this->store->forgetLink($tokenHash);
        $this->assertNull($this->store->getLink($tokenHash));
    }

    public function test_lockout_trigger_after_max_failed_attempts(): void
    {
        $ip = '192.168.1.100';
        $this->assertFalse($this->store->isLockedOut($ip));

        $this->store->recordFailedAttempt($ip);
        $this->store->recordFailedAttempt($ip);
        $this->assertFalse($this->store->isLockedOut($ip));

        // 3rd attempt triggers lockout (maxAttempts: 3)
        $this->store->recordFailedAttempt($ip);
        $this->assertTrue($this->store->isLockedOut($ip));

        // Reset lockout
        $this->store->resetLockout($ip);
        $this->assertFalse($this->store->isLockedOut($ip));
    }

    public function test_registry_tracking_and_revocation(): void
    {
        $tokenHash1 = hash('sha256', 'token1_secret_key_1234567890123456');
        $tokenHash2 = hash('sha256', 'token2_secret_key_1234567890123456');

        $this->store->storeLink($tokenHash1, ['id' => 'link0001', 'created_at' => time(), 'expires_at' => time() + 600], 600);
        $this->store->storeLink($tokenHash2, ['id' => 'link0002', 'created_at' => time(), 'expires_at' => time() + 600], 600);

        $active = $this->store->listActive();
        $this->assertCount(2, $active);

        // Revoke one by ID
        $this->assertTrue($this->store->revokeById('link0001'));
        $this->assertNull($this->store->getLink($tokenHash1));
        $this->assertNotNull($this->store->getLink($tokenHash2));

        // Revoke all
        $this->store->revokeAll();
        $this->assertEmpty($this->store->listActive());
    }
}
