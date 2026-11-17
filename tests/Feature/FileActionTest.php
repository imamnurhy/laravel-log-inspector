<?php

declare(strict_types=1);

namespace Explorin\LogInspector\Tests\Feature;

use Explorin\LogInspector\Tests\TestCase;
use Illuminate\Support\Facades\Gate;

final class FileActionTest extends TestCase
{
    private string $tempLogDir;

    protected function getEnvironmentSetUp($app): void
    {
        parent::getEnvironmentSetUp($app);

        $this->tempLogDir = sys_get_temp_dir() . '/file_action_test_' . uniqid();
        @mkdir($this->tempLogDir, 0755, true);

        $app['config']->set('log-inspector.enabled', true);
        $app['config']->set('log-inspector.enabled_environments', ['testing']);
        $app['config']->set('log-inspector.middleware', []);
        $app['config']->set('log-inspector.paths', [$this->tempLogDir]);
    }

    protected function tearDown(): void
    {
        $files = glob($this->tempLogDir . '/*');
        foreach ($files as $f) {
            if (is_file($f)) {
                @unlink($f);
            }
        }
        @rmdir($this->tempLogDir);
        parent::tearDown();
    }

    public function test_masked_download_scrubs_secrets_by_default(): void
    {
        Gate::define('download-log-inspector', static fn () => true);

        $testFile = $this->tempLogDir . '/test-download.log';
        file_put_contents($testFile, '{"user":"alice","password":"super_secret_db_pass"}');

        $response = $this->get('/log-inspector/download?file=test-download.log');

        $response->assertStatus(200);
        $response->assertHeader('Content-Disposition', 'attachment; filename="masked_test-download.log"');

        // Capture streamed response content
        ob_start();
        $response->sendContent();
        $content = ob_get_clean();

        $this->assertStringNotContainsString('super_secret_db_pass', $content ?: '');
        $this->assertStringContainsString('******', $content ?: '');
    }

    public function test_raw_download_requires_download_raw_gate(): void
    {
        Gate::define('download-log-inspector', static fn () => true);
        // download-raw-log-inspector is NOT granted

        $testFile = $this->tempLogDir . '/test-download.log';
        file_put_contents($testFile, 'test');

        $response = $this->get('/log-inspector/download?file=test-download.log&raw=1');

        $response->assertStatus(403);
    }

    public function test_delete_action_denied_without_delete_gate(): void
    {
        $testFile = $this->tempLogDir . '/laravel-2020-01-01.log';
        file_put_contents($testFile, 'old');

        $response = $this->post('/log-inspector/delete', ['file' => 'laravel-2020-01-01.log']);

        $response->assertStatus(403);
    }
}
