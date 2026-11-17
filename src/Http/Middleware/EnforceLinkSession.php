<?php

declare(strict_types=1);

namespace Explorin\LogInspector\Http\Middleware;

use Closure;
use Explorin\LogInspector\Contracts\AccessStoreInterface;
use Explorin\LogInspector\Services\AuditLogger;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

final class EnforceLinkSession
{
    public function __construct(
        private readonly AccessStoreInterface $accessStore,
        private readonly AuditLogger $auditLogger,
    ) {
    }

    /**
     * Handle incoming request for Link-Session mode.
     * Enforce:
     * 1. Secret cookie presence and validation.
     * 2. Instant revocation check against store.
     * 3. IP binding check (matches bound IP).
     * 4. Uniform 404 on any failure (no credential disclosure).
     * 5. Strict security headers on response.
     */
    public function handle(Request $request, Closure $next): Response
    {
        $cookieName = (string) config('log-inspector.access.cookie.name', 'li_session');
        $sessionSecret = $request->cookie($cookieName);

        if (! is_string($sessionSecret) || strlen($sessionSecret) < 32) {
            abort(404);
        }

        $sessionHash = hash('sha256', $sessionSecret);
        $sessionData = $this->accessStore->getSession($sessionHash);

        if ($sessionData === null) {
            abort(404);
        }

        // IP Binding Check
        $boundIp = $sessionData['bound_ip'] ?? null;
        $clientIp = $request->ip();

        if ($boundIp !== null && ! hash_equals((string) $boundIp, (string) $clientIp)) {
            $this->auditLogger->log(
                'session_ip_mismatch',
                'N/A',
                $sessionData['os_user'] ?? 'web',
                $clientIp,
                false,
                ['expected_ip' => $boundIp, 'session_id' => $sessionData['id'] ?? 'unknown']
            );
            abort(404);
        }

        // Attach session info to request attributes for downstream controllers
        $request->attributes->set('log_inspector_session', $sessionData);
        $request->attributes->set('log_inspector_mode', 'link');

        $response = $next($request);

        // Enforce Strict Security Headers (Addendum D3)
        $response->headers->set('Referrer-Policy', 'no-referrer');
        $response->headers->set('Cache-Control', 'no-store, no-cache, must-revalidate');
        $response->headers->set('Pragma', 'no-cache');
        $response->headers->set('X-Frame-Options', 'DENY');
        $response->headers->set('X-Content-Type-Options', 'nosniff');

        return $response;
    }
}
