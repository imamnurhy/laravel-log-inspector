<?php

declare(strict_types=1);

namespace Explorin\LogInspector\Tests\Feature;

use Explorin\LogInspector\Tests\TestCase;

final class AssetControllerTest extends TestCase
{
    protected function getEnvironmentSetUp($app): void
    {
        parent::getEnvironmentSetUp($app);

        $app['config']->set('log-inspector.enabled', true);
        $app['config']->set('log-inspector.path', 'log-inspector');
    }

    public function test_serves_internal_bootstrap_css_with_caching_headers(): void
    {
        $response = $this->get('/log-inspector/assets/css/bootstrap.min.css');

        $response->assertStatus(200);
        $response->assertHeader('Content-Type', 'text/css; charset=UTF-8');
        $response->assertHeader('Cache-Control', 'public, max-age=31536000, immutable');
        $this->assertTrue($response->headers->has('ETag'));
    }

    public function test_serves_internal_javascript_asset(): void
    {
        $response = $this->get('/log-inspector/assets/js/log-inspector.js');

        $response->assertStatus(200);
        $response->assertHeader('Content-Type', 'application/javascript; charset=UTF-8');
    }

    public function test_returns_304_when_etag_matches(): void
    {
        $initial = $this->get('/log-inspector/assets/css/bootstrap.min.css');
        $etag = $initial->headers->get('ETag');

        $cachedResponse = $this->withHeaders(['If-None-Match' => $etag])
            ->get('/log-inspector/assets/css/bootstrap.min.css');

        $cachedResponse->assertStatus(304);
    }

    public function test_prevents_directory_traversal(): void
    {
        $response = $this->get('/log-inspector/assets/../../composer.json');

        $response->assertStatus(404);
    }

    public function test_returns_404_for_non_existent_asset(): void
    {
        $response = $this->get('/log-inspector/assets/css/not-found.css');

        $response->assertStatus(404);
    }
}
