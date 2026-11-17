<?php

declare(strict_types=1);

namespace Explorin\LogInspector\Http\Controllers;

use Explorin\LogInspector\Contracts\AccessStoreInterface;
use Explorin\LogInspector\Services\AuditLogger;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\Cookie;

final class LinkExchangeController
{
    public function __construct(
        private readonly AccessStoreInterface $accessStore,
        private readonly AuditLogger $auditLogger,
    ) {
    }

    /**
     * Show neutral confirmation form on HTTP GET.
     * Does NOT consume the token (safe against link preview bots and prefetchers).
     */
    public function show(Request $request, string $token): View
    {
        $this->guardPreAuth($request, $token);

        return view('log-inspector::confirm-access', [
            'token' => $token,
        ]);
    }

    /**
     * Exchange token for an active session on HTTP POST with CSRF validation.
     * Consumes (deletes) the token, creates session hash, binds IP, and redirects.
     */
    public function exchange(Request $request, string $token): RedirectResponse
    {
        $linkData = $this->guardPreAuth($request, $token);
        $tokenHash = hash('sha256', $token);

        // Atomic one-time consumption: delete token from store
        $this->accessStore->forgetLink($tokenHash);

        // Generate cryptographically secure session secret
        $sessionSecret = bin2hex(random_bytes(32));
        $sessionHash = hash('sha256', $sessionSecret);
        $clientIp = (string) $request->ip();

        $sessionTtlMinutes = (int) config('log-inspector.access.session_minutes', 30);
        $sessionTtlSeconds = $sessionTtlMinutes * 60;

        $sessionData = [
            'id' => substr($sessionHash, 0, 8),
            'link_id' => $linkData['id'] ?? 'unknown',
            'created_at' => time(),
            'expires_at' => time() + $sessionTtlSeconds,
            'bound_ip' => $clientIp,
            'os_user' => $linkData['os_user'] ?? 'cli',
        ];

        $this->accessStore->storeSession($sessionHash, $sessionData, $sessionTtlSeconds);
        $this->accessStore->resetLockout($clientIp);

        $this->auditLogger->log(
            'exchange_success',
            'N/A',
            $linkData['os_user'] ?? 'cli',
            $clientIp,
            true,
            ['session_id' => $sessionData['id']]
        );

        $cookieName = (string) config('log-inspector.access.cookie.name', 'li_session');
        $dashboardPath = '/' . trim((string) config('log-inspector.path', 'log-inspector'), '/');

        $isSecure = app()->environment('local') ? false : true;

        $sessionCookie = new Cookie(
            name: $cookieName,
            value: $sessionSecret,
            expire: time() + $sessionTtlSeconds,
            path: $dashboardPath,
            domain: config('log-inspector.domain'),
            secure: $isSecure,
            httpOnly: true,
            raw: false,
            sameSite: Cookie::SAMESITE_STRICT
        );

        return redirect()
            ->route('log-inspector.index')
            ->withCookie($sessionCookie);
    }

    /**
     * Terminate the current link session (revocation by user from dashboard).
     */
    public function logout(Request $request): RedirectResponse
    {
        $cookieName = (string) config('log-inspector.access.cookie.name', 'li_session');
        $sessionSecret = $request->cookie($cookieName);

        if (is_string($sessionSecret)) {
            $sessionHash = hash('sha256', $sessionSecret);
            $this->accessStore->forgetSession($sessionHash);
            $this->auditLogger->log('logout', 'N/A', 'web', $request->ip(), true);
        }

        $dashboardPath = '/' . trim((string) config('log-inspector.path', 'log-inspector'), '/');

        // Expire cookie
        $expiredCookie = new Cookie(
            name: $cookieName,
            value: '',
            expire: time() - 3600,
            path: $dashboardPath,
            httpOnly: true
        );

        return redirect('/')->withCookie($expiredCookie);
    }

    /**
     * Hardened pre-auth validation check:
     * - Checks IP lockout
     * - Validates token format
     * - Evaluates hash in store
     * - Checks IP binding restriction
     * - Aborts with uniform 404 on any failure
     *
     * @return array<string, mixed>
     */
    private function guardPreAuth(Request $request, string $token): array
    {
        $clientIp = (string) $request->ip();

        if ($this->accessStore->isLockedOut($clientIp)) {
            abort(404);
        }

        // Token must be alphanumeric/url-safe and at least 32 bytes (64 hex or ~43 base64)
        if (strlen($token) < 32 || strlen($token) > 128 || ! ctype_alnum(str_replace(['-', '_'], '', $token))) {
            $this->accessStore->recordFailedAttempt($clientIp);
            abort(404);
        }

        $tokenHash = hash('sha256', $token);
        $linkData = $this->accessStore->getLink($tokenHash);

        if ($linkData === null) {
            $this->accessStore->recordFailedAttempt($clientIp);
            $this->auditLogger->log('exchange_failed', 'N/A', null, $clientIp, false, ['reason' => 'invalid_or_expired_token']);
            abort(404);
        }

        // IP Binding check on exchange
        $ipMode = $linkData['ip_mode'] ?? 'first-use';
        $allowedIp = $linkData['allowed_ip'] ?? null;

        if ($ipMode === 'fixed' || $ipMode === 'auto') {
            if ($allowedIp === null || ! hash_equals((string) $allowedIp, $clientIp)) {
                $this->accessStore->recordFailedAttempt($clientIp);
                $this->auditLogger->log('exchange_failed', 'N/A', null, $clientIp, false, [
                    'reason' => 'ip_binding_mismatch',
                    'expected' => $allowedIp,
                ]);
                abort(404);
            }
        }

        return $linkData;
    }
}
