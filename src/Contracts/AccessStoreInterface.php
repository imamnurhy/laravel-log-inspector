<?php

declare(strict_types=1);

namespace Explorin\LogInspector\Contracts;

interface AccessStoreInterface
{
    /**
     * Store a temporary access link token (stored as SHA-256 hash).
     *
     * @param string $tokenHash
     * @param array<string, mixed> $data
     * @param int $ttlSeconds
     */
    public function storeLink(string $tokenHash, array $data, int $ttlSeconds): void;

    /**
     * Retrieve link data by token hash.
     *
     * @param string $tokenHash
     * @return array<string, mixed>|null
     */
    public function getLink(string $tokenHash): ?array;

    /**
     * Delete link token (one-time consumption).
     */
    public function forgetLink(string $tokenHash): bool;

    /**
     * Store an active session (stored as SHA-256 hash of session secret).
     *
     * @param string $sessionHash
     * @param array<string, mixed> $data
     * @param int $ttlSeconds
     */
    public function storeSession(string $sessionHash, array $data, int $ttlSeconds): void;

    /**
     * Retrieve active session data by session hash.
     *
     * @param string $sessionHash
     * @return array<string, mixed>|null
     */
    public function getSession(string $sessionHash): ?array;

    /**
     * Revoke / delete active session.
     */
    public function forgetSession(string $sessionHash): bool;

    /**
     * Get list of all currently tracked active links and sessions.
     *
     * @return array<int, array<string, mixed>>
     */
    public function listActive(): array;

    /**
     * Revoke by short ID (8 chars).
     */
    public function revokeById(string $shortId): bool;

    /**
     * Revoke all active links and sessions (emergency purge).
     */
    public function revokeAll(): int;

    /**
     * Clean up expired entries from registry.
     */
    public function pruneExpired(): int;

    /**
     * Track failed pre-auth attempt for IP lockout.
     */
    public function recordFailedAttempt(string $ip): int;

    /**
     * Check if IP is currently locked out.
     */
    public function isLockedOut(string $ip): bool;

    /**
     * Reset lockout counter for IP.
     */
    public function resetLockout(string $ip): void;
}
