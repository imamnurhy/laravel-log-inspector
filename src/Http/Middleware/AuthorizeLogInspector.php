<?php

declare(strict_types=1);

namespace Explorin\LogInspector\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Symfony\Component\HttpFoundation\Response;

final class AuthorizeLogInspector
{
    /**
     * Handle an incoming request.
     * Deny by default: If the configured gate does not exist or fails, abort with 403.
     */
    public function handle(Request $request, Closure $next, string $ability = 'view'): Response
    {
        $enabled = (bool) config('log-inspector.enabled', false);
        if (! $enabled) {
            abort(404, 'Log Inspector is disabled.');
        }

        $environments = (array) config('log-inspector.enabled_environments', ['local', 'staging']);
        $currentEnv = app()->environment();
        if (! in_array($currentEnv, $environments, true)) {
            abort(403, sprintf('Log Inspector is not enabled in [%s] environment.', $currentEnv));
        }

        $gateName = (string) config("log-inspector.authorization.{$ability}", "{$ability}-log-inspector");

        // DENY BY DEFAULT: If the host app did not define the Gate, deny access!
        if (! Gate::has($gateName) || ! Gate::allows($gateName)) {
            abort(403, sprintf('Access denied. Missing or unauthorized gate [%s].', $gateName));
        }

        return $next($request);
    }
}
