<?php

declare(strict_types=1);

namespace Explorin\LogInspector\Security;

use Explorin\LogInspector\Contracts\AccessStoreInterface;
use Illuminate\Contracts\Cache\Repository as CacheRepository;

final class CacheAccessStore implements AccessStoreInterface
{
    private const LINK_PREFIX = 'log_inspector_link:';
    private const SESSION_PREFIX = 'log_inspector_session:';
    private const REGISTRY_KEY = 'log_inspector_active_registry';
    private const FAILED_ATTEMPTS_PREFIX = 'log_inspector_failed:';
    private const LOCKOUT_PREFIX = 'log_inspector_lockout:';

    /**
     * @param CacheRepository $cache
     * @param int $maxAttempts Number of failures before lockout
     * @param int $lockoutMinutes Duration of lockout in minutes
     */
    public function __construct(
        private readonly CacheRepository $cache,
        private readonly int $maxAttempts = 10,
        private readonly int $lockoutMinutes = 15,
    ) {
    }

    /**
     * {@inheritdoc}
     */
    public function storeLink(string $tokenHash, array $data, int $ttlSeconds): void
    {
        $key = self::LINK_PREFIX . $tokenHash;
        $this->cache->put($key, $data, $ttlSeconds);

        $shortId = (string) ($data['id'] ?? substr($tokenHash, 0, 8));
        $this->addToRegistry([
            'id' => $shortId,
            'type' => 'link',
            'hash' => $tokenHash,
            'key' => $key,
            'created_at' => $data['created_at'] ?? time(),
            'expires_at' => $data['expires_at'] ?? (time() + $ttlSeconds),
            'ip_mode' => $data['ip_mode'] ?? 'first-use',
            'ip' => $data['allowed_ip'] ?? 'unbound',
            'os_user' => $data['os_user'] ?? 'cli',
        ]);
    }

    /**
     * {@inheritdoc}
     */
    public function getLink(string $tokenHash): ?array
    {
        $key = self::LINK_PREFIX . $tokenHash;
        $data = $this->cache->get($key);

        return is_array($data) ? $data : null;
    }

    /**
     * {@inheritdoc}
     */
    public function forgetLink(string $tokenHash): bool
    {
        $key = self::LINK_PREFIX . $tokenHash;
        $deleted = $this->cache->forget($key);
        $this->removeFromRegistryByHash($tokenHash);

        return $deleted;
    }

    /**
     * {@inheritdoc}
     */
    public function storeSession(string $sessionHash, array $data, int $ttlSeconds): void
    {
        $key = self::SESSION_PREFIX . $sessionHash;
        $this->cache->put($key, $data, $ttlSeconds);

        $shortId = (string) ($data['id'] ?? substr($sessionHash, 0, 8));
        $this->addToRegistry([
            'id' => $shortId,
            'type' => 'session',
            'hash' => $sessionHash,
            'key' => $key,
            'created_at' => $data['created_at'] ?? time(),
            'expires_at' => $data['expires_at'] ?? (time() + $ttlSeconds),
            'ip_mode' => 'bound',
            'ip' => $data['bound_ip'] ?? 'unknown',
            'os_user' => $data['os_user'] ?? 'web',
        ]);
    }

    /**
     * {@inheritdoc}
     */
    public function getSession(string $sessionHash): ?array
    {
        $key = self::SESSION_PREFIX . $sessionHash;
        $data = $this->cache->get($key);

        return is_array($data) ? $data : null;
    }

    /**
     * {@inheritdoc}
     */
    public function forgetSession(string $sessionHash): bool
    {
        $key = self::SESSION_PREFIX . $sessionHash;
        $deleted = $this->cache->forget($key);
        $this->removeFromRegistryByHash($sessionHash);

        return $deleted;
    }

    /**
     * {@inheritdoc}
     */
    public function listActive(): array
    {
        $registry = (array) $this->cache->get(self::REGISTRY_KEY, []);
        $active = [];
        $now = time();

        foreach ($registry as $entry) {
            $expiresAt = (int) ($entry['expires_at'] ?? 0);
            if ($expiresAt > $now && $this->cache->has($entry['key'])) {
                $active[] = $entry;
            }
        }

        return $active;
    }

    /**
     * {@inheritdoc}
     */
    public function revokeById(string $shortId): bool
    {
        $registry = (array) $this->cache->get(self::REGISTRY_KEY, []);
        $found = false;

        foreach ($registry as $idx => $entry) {
            if (($entry['id'] ?? '') === $shortId) {
                $this->cache->forget($entry['key']);
                unset($registry[$idx]);
                $found = true;
                break;
            }
        }

        if ($found) {
            $this->cache->put(self::REGISTRY_KEY, array_values($registry), 86400 * 7);
        }

        return $found;
    }

    /**
     * {@inheritdoc}
     */
    public function revokeAll(): int
    {
        $registry = (array) $this->cache->get(self::REGISTRY_KEY, []);
        $count = 0;

        foreach ($registry as $entry) {
            $this->cache->forget($entry['key']);
            $count++;
        }

        $this->cache->forget(self::REGISTRY_KEY);

        return $count;
    }

    /**
     * {@inheritdoc}
     */
    public function pruneExpired(): int
    {
        $registry = (array) $this->cache->get(self::REGISTRY_KEY, []);
        $now = time();
        $pruned = 0;
        $active = [];

        foreach ($registry as $entry) {
            $expiresAt = (int) ($entry['expires_at'] ?? 0);
            if ($expiresAt <= $now || ! $this->cache->has($entry['key'])) {
                $pruned++;
            } else {
                $active[] = $entry;
            }
        }

        $this->cache->put(self::REGISTRY_KEY, $active, 86400 * 7);

        return $pruned;
    }

    /**
     * {@inheritdoc}
     */
    public function recordFailedAttempt(string $ip): int
    {
        $key = self::FAILED_ATTEMPTS_PREFIX . md5($ip);
        $attempts = (int) $this->cache->get($key, 0) + 1;
        $this->cache->put($key, $attempts, 60); // 1 minute window

        if ($attempts >= $this->maxAttempts) {
            $this->cache->put(self::LOCKOUT_PREFIX . md5($ip), true, $this->lockoutMinutes * 60);
        }

        return $attempts;
    }

    /**
     * {@inheritdoc}
     */
    public function isLockedOut(string $ip): bool
    {
        return (bool) $this->cache->get(self::LOCKOUT_PREFIX . md5($ip), false);
    }

    /**
     * {@inheritdoc}
     */
    public function resetLockout(string $ip): void
    {
        $this->cache->forget(self::FAILED_ATTEMPTS_PREFIX . md5($ip));
        $this->cache->forget(self::LOCKOUT_PREFIX . md5($ip));
    }

    /**
     * @param array<string, mixed> $item
     */
    private function addToRegistry(array $item): void
    {
        $registry = (array) $this->cache->get(self::REGISTRY_KEY, []);
        $registry[] = $item;
        $this->cache->put(self::REGISTRY_KEY, $registry, 86400 * 7);
    }

    private function removeFromRegistryByHash(string $hash): void
    {
        $registry = (array) $this->cache->get(self::REGISTRY_KEY, []);
        $filtered = array_filter($registry, static fn ($entry) => ($entry['hash'] ?? '') !== $hash);
        $this->cache->put(self::REGISTRY_KEY, array_values($filtered), 86400 * 7);
    }
}
