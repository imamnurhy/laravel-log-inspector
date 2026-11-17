<?php

declare(strict_types=1);

namespace Explorin\LogInspector\Tests\Unit;

use Explorin\LogInspector\Exceptions\SecurityException;
use Explorin\LogInspector\Security\PathGuard;
use PHPUnit\Framework\TestCase;

final class PathGuardTest extends TestCase
{
    private string $fixtureDir;
    private PathGuard $guard;

    protected function setUp(): void
    {
        parent::setUp();
        $this->fixtureDir = realpath(__DIR__ . '/../Fixtures') ?: __DIR__ . '/../Fixtures';
        $this->guard = new PathGuard(['laravel.log'], 300);
    }

    public function test_validates_path_inside_allowed_directory(): void
    {
        $filePath = $this->fixtureDir . '/single.log';
        $validated = $this->guard->validate($filePath, [$this->fixtureDir]);

        $this->assertSame(realpath($filePath), $validated);
    }

    public function test_rejects_directory_traversal(): void
    {
        $this->expectException(SecurityException::class);
        $this->expectExceptionMessage('Directory traversal (..) detected.');

        $this->guard->validate('../outside.log', [$this->fixtureDir]);
    }

    public function test_rejects_null_byte(): void
    {
        $this->expectException(SecurityException::class);
        $this->expectExceptionMessage('Null byte injection detected.');

        $this->guard->validate("single.log\0.php", [$this->fixtureDir]);
    }

    public function test_rejects_disallowed_extension(): void
    {
        $this->expectException(SecurityException::class);
        $this->expectExceptionMessage('Disallowed file extension');

        $this->guard->validate($this->fixtureDir . '/script.php', [$this->fixtureDir]);
    }

    public function test_rejects_path_outside_allowed_directories(): void
    {
        $this->expectException(SecurityException::class);
        $this->expectExceptionMessage('File does not exist or cannot be accessed');

        $this->guard->validate('/etc/hosts', [$this->fixtureDir]);
    }

    public function test_detects_active_log_file(): void
    {
        $this->assertTrue($this->guard->isActiveLogFile('/var/log/laravel.log'));
        $this->assertTrue($this->guard->isActiveLogFile('/var/log/laravel-' . date('Y-m-d') . '.log'));
        $this->assertFalse($this->guard->isActiveLogFile('/var/log/laravel-2020-01-01.log'));
    }
}
