<?php

declare(strict_types=1);

namespace Explorin\LogInspector;

use Explorin\LogInspector\Console\ArchiveCommand;
use Explorin\LogInspector\Console\ReindexCommand;
use Explorin\LogInspector\Console\RetentionCommand;
use Explorin\LogInspector\Contracts\LogIndexInterface;
use Explorin\LogInspector\Contracts\LogParserInterface;
use Explorin\LogInspector\Contracts\MaskerInterface;
use Explorin\LogInspector\Contracts\PathGuardInterface;
use Explorin\LogInspector\Indexing\FileIndexStore;
use Explorin\LogInspector\Indexing\LogIndexer;
use Explorin\LogInspector\Parsers\LaravelLogParser;
use Explorin\LogInspector\Security\Masker;
use Explorin\LogInspector\Security\PathGuard;
use Explorin\LogInspector\Services\AuditLogger;
use Explorin\LogInspector\Services\LogFileManager;
use Explorin\LogInspector\Services\LogInspector;
use Illuminate\Contracts\Cache\Factory as CacheFactory;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\ServiceProvider;

final class LogInspectorServiceProvider extends ServiceProvider
{
    /**
     * Register any package services.
     */
    public function register(): void
    {
        $this->mergeConfigFrom(
            __DIR__ . '/../config/log-inspector.php',
            'log-inspector'
        );

        // Bind Masker
        $this->app->singleton(MaskerInterface::class, function ($app) {
            $config = $app['config']->get('log-inspector.masking', []);
            $enabled = (bool) ($config['enabled'] ?? true);
            $patterns = (array) ($config['patterns'] ?? []);
            $extraPatterns = (array) ($config['extra_patterns'] ?? []);
            $replacement = (string) ($config['replacement'] ?? '******');

            $allPatterns = array_merge($patterns, $extraPatterns);

            return new Masker(
                enabled: $enabled,
                patterns: $allPatterns,
                maskReplacement: $replacement
            );
        });

        // Bind PathGuard
        $this->app->singleton(PathGuardInterface::class, function ($app) {
            $activeNames = (array) $app['config']->get('log-inspector.active_logs.names', ['laravel.log']);
            $threshold = (int) $app['config']->get('log-inspector.active_logs.mtime_threshold', 300);

            return new PathGuard(
                activeLogNames: $activeNames,
                activeMtimeThresholdSeconds: $threshold
            );
        });

        // Bind Default Parser
        $this->app->singleton(LogParserInterface::class, function ($app) {
            return new LaravelLogParser(
                masker: $app->make(MaskerInterface::class)
            );
        });

        // Bind FileIndexStore
        $this->app->singleton(FileIndexStore::class, function ($app) {
            $indexPath = (string) $app['config']->get(
                'log-inspector.cache.index_path',
                storage_path('framework/cache/log-inspector/indices')
            );

            return new FileIndexStore($indexPath);
        });

        // Bind LogIndexer
        $this->app->singleton(LogIndexInterface::class, function ($app) {
            return new LogIndexer(
                parser: $app->make(LogParserInterface::class),
                indexStore: $app->make(FileIndexStore::class),
                masker: $app->make(MaskerInterface::class)
            );
        });

        // Bind AuditLogger
        $this->app->singleton(AuditLogger::class, function ($app) {
            $config = $app['config']->get('log-inspector.audit', []);
            return new AuditLogger(
                enabled: (bool) ($config['enabled'] ?? true),
                channel: $config['channel'] ?? null
            );
        });

        // Bind LogFileManager
        $this->app->singleton(LogFileManager::class, function ($app) {
            $paths = (array) $app['config']->get('log-inspector.paths', [storage_path('logs')]);

            return new LogFileManager(
                pathGuard: $app->make(PathGuardInterface::class),
                masker: $app->make(MaskerInterface::class),
                auditLogger: $app->make(AuditLogger::class),
                indexer: $app->make(LogIndexInterface::class),
                allowedDirectories: $paths
            );
        });

        // Bind Main Service
        $this->app->singleton(LogInspector::class, function ($app) {
            $paths = (array) $app['config']->get('log-inspector.paths', [storage_path('logs')]);
            $cacheStoreName = $app['config']->get('log-inspector.cache.store');
            $cacheTtl = (int) $app['config']->get('log-inspector.cache.ttl', 300);

            $cacheRepo = null;
            if ($app->bound(CacheFactory::class)) {
                $cacheRepo = $app->make(CacheFactory::class)->store($cacheStoreName);
            }

            return new LogInspector(
                indexer: $app->make(LogIndexInterface::class),
                parser: $app->make(LogParserInterface::class),
                pathGuard: $app->make(PathGuardInterface::class),
                indexStore: $app->make(FileIndexStore::class),
                allowedDirectories: $paths,
                cache: $cacheRepo,
                cacheTtl: $cacheTtl
            );
        });
    }

    /**
     * Bootstrap any package services.
     */
    public function boot(): void
    {
        if ($this->app->runningInConsole()) {
            $this->publishes([
                __DIR__ . '/../config/log-inspector.php' => config_path('log-inspector.php'),
            ], 'log-inspector-config');

            $this->publishes([
                __DIR__ . '/../resources/views' => resource_path('views/vendor/log-inspector'),
            ], 'log-inspector-views');

            $this->publishes([
                __DIR__ . '/../public' => public_path('vendor/log-inspector'),
            ], 'log-inspector-assets');

            $this->commands([
                RetentionCommand::class,
                ArchiveCommand::class,
                ReindexCommand::class,
            ]);
        }

        $this->loadViewsFrom(__DIR__ . '/../resources/views', 'log-inspector');

        $this->registerRoutes();
    }

    private function registerRoutes(): void
    {
        Route::group([], function () {
            $this->loadRoutesFrom(__DIR__ . '/../routes/web.php');
        });
    }
}
