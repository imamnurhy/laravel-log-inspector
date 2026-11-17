<?php

declare(strict_types=1);

namespace Explorin\LogInspector\Tests\Feature;

use Explorin\LogInspector\Contracts\AccessStoreInterface;
use Explorin\LogInspector\Tests\TestCase;

final class LinkExchangeTest extends TestCase
{
    private AccessStoreInterface $accessStore;

    protected function getEnvironmentSetUp($app): void
    {
        parent::getEnvironmentSetUp($app);

        $app['config']->set('log-inspector.enabled', true);
        $app['config']->set('log-inspector.access.mode', 'link');
        $app['config']->set('log-inspector.path', 'log-inspector');
    }

    protected function setUp(): void
    {
        parent::setUp();
        $this->accessStore = $this->app->make(AccessStoreInterface::class);
    }

    public function test_get_on_exchange_shows_confirmation_without_consuming_token(): void
    {
        $token = bin2hex(random_bytes(32));
        $tokenHash = hash('sha256', $token);

        $this->accessStore->storeLink($tokenHash, [
            'id' => 'test0001',
            'created_at' => time(),
            'expires_at' => time() + 600,
            'ip_mode' => 'first-use',
            'allowed_ip' => null,
            'os_user' => 'tester',
        ], 600);

        // GET request (e.g. from chat link unfurling bot)
        $response = $this->get('/log-inspector/access/' . $token);

        $response->assertStatus(200);
        $response->assertSee('Lanjutkan ke Dashboard');

        // Verify token is NOT consumed
        $this->assertNotNull($this->accessStore->getLink($tokenHash));
    }

    public function test_post_on_exchange_consumes_token_and_sets_secure_session_cookie(): void
    {
        $token = bin2hex(random_bytes(32));
        $tokenHash = hash('sha256', $token);

        $this->accessStore->storeLink($tokenHash, [
            'id' => 'test0002',
            'created_at' => time(),
            'expires_at' => time() + 600,
            'ip_mode' => 'first-use',
            'allowed_ip' => null,
            'os_user' => 'tester',
        ], 600);

        // POST request confirms exchange
        $response = $this->post('/log-inspector/access/' . $token);

        $response->assertRedirect('/log-inspector');
        $response->assertCookie('li_session');

        // Token must be destroyed (one-time use)
        $this->assertNull($this->accessStore->getLink($tokenHash));

        // Replay attack with same token must yield uniform 404
        $replayResponse = $this->post('/log-inspector/access/' . $token);
        $replayResponse->assertStatus(404);
    }

    public function test_fixed_ip_mode_rejects_unauthorized_ip(): void
    {
        $token = bin2hex(random_bytes(32));
        $tokenHash = hash('sha256', $token);

        $this->accessStore->storeLink($tokenHash, [
            'id' => 'test0003',
            'created_at' => time(),
            'expires_at' => time() + 600,
            'ip_mode' => 'fixed',
            'allowed_ip' => '203.0.113.50',
            'os_user' => 'tester',
        ], 600);

        // Incoming client IP is default 127.0.0.1 (not 203.0.113.50)
        $response = $this->post('/log-inspector/access/' . $token);

        $response->assertStatus(404);
    }

    public function test_dashboard_routes_return_404_without_session(): void
    {
        $response = $this->get('/log-inspector');

        // Without a valid temporary session cookie, returns uniform 404
        $response->assertStatus(404);
    }

    public function test_link_session_dashboard_has_no_raw_download_or_delete_routes(): void
    {
        // Assert download raw route does not exist in link mode
        $response = $this->get('/log-inspector/download?file=laravel.log&raw=1');
        $response->assertStatus(404);

        // Assert delete route does not exist in link mode
        $deleteResponse = $this->post('/log-inspector/delete', ['file' => 'laravel.log']);
        $deleteResponse->assertStatus(404);
    }
}
