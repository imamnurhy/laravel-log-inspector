<?php

declare(strict_types=1);

namespace Explorin\LogInspector\Tests;

use Explorin\LogInspector\LogInspectorServiceProvider;
use Orchestra\Testbench\TestCase as OrchestraTestCase;

abstract class TestCase extends OrchestraTestCase
{
    protected function getPackageProviders($app): array
    {
        return [
            LogInspectorServiceProvider::class,
        ];
    }

    protected function getEnvironmentSetUp($app): void
    {
        $app['config']->set('log-inspector.paths', [
            __DIR__ . '/Fixtures',
        ]);
    }
}
