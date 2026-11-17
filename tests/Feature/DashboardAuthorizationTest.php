<?php

declare(strict_types=1);

namespace Explorin\LogInspector\Tests\Feature;

use Explorin\LogInspector\Tests\TestCase;
use Illuminate\Support\Facades\Gate;

final class DashboardAuthorizationTest extends TestCase
{
    protected function getEnvironmentSetUp($app): void
    {
        parent::getEnvironmentSetUp($app);

        $app['config']->set('log-inspector.enabled', true);
        $app['config']->set('log-inspector.enabled_environments', ['testing']);
        $app['config']->set('log-inspector.middleware', []);
    }

    public function test_deny_by_default_when_gate_is_not_defined(): void
    {
        // No Gate defined: should return 403 Forbidden
        $response = $this->get('/log-inspector');

        $response->assertStatus(403);
    }

    public function test_allows_access_when_gate_is_explicitly_granted(): void
    {
        Gate::define('view-log-inspector', static fn () => true);

        $response = $this->get('/log-inspector');

        $response->assertStatus(200);
        $response->assertSee('Explorin Log Inspector');
    }

    public function test_aborts_404_when_disabled(): void
    {
        config(['log-inspector.enabled' => false]);
        Gate::define('view-log-inspector', static fn () => true);

        $response = $this->get('/log-inspector');

        $response->assertStatus(404);
    }

    public function test_xss_prevention_on_rendered_logs(): void
    {
        Gate::define('view-log-inspector', static fn () => true);

        // Access dashboard with multiline log containing potential script tags
        $response = $this->get('/log-inspector?file=single.log');

        $response->assertStatus(200);
        // Verify Blade escapes HTML characters
        $response->assertDontSee('<script>', false);
    }
}
