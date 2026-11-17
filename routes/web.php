<?php

use Explorin\LogInspector\Http\Controllers\AssetController;
use Explorin\LogInspector\Http\Controllers\DashboardController;
use Explorin\LogInspector\Http\Controllers\FileActionController;
use Explorin\LogInspector\Http\Controllers\LinkExchangeController;
use Explorin\LogInspector\Http\Middleware\AuthorizeLogInspector;
use Explorin\LogInspector\Http\Middleware\EnforceLinkSession;
use Illuminate\Support\Facades\Route;

// Register routes only if the package is explicitly enabled
if (config('log-inspector.enabled', false)) {
    $mode = (string) config('log-inspector.access.mode', 'link');
    $path = (string) config('log-inspector.path', 'log-inspector');
    $domain = config('log-inspector.domain');

    // 0. Internal Asset Route (Zero-Publish: Directly served from package)
    Route::prefix($path)
        ->domain($domain)
        ->group(function () {
            Route::get('/assets/{path}', [AssetController::class, 'serve'])
                ->where('path', '.*')
                ->name('log-inspector.asset');
        });

    // 1. Link Exchange Routes (Available in 'link' and 'both' modes)
    if (in_array($mode, ['link', 'both'], true)) {
        Route::prefix($path)
            ->domain($domain)
            ->middleware(['web'])
            ->name('log-inspector.access.')
            ->group(function () {
                // Two-stage exchange: GET displays confirmation, POST consumes token
                Route::get('/access/{token}', [LinkExchangeController::class, 'show'])->name('show');
                Route::post('/access/{token}', [LinkExchangeController::class, 'exchange'])->name('exchange');
            });
    }

    // 2. Dashboard Routes (Scoped to selected access mode)
    $dashboardMiddleware = ['web'];

    if ($mode === 'link') {
        $dashboardMiddleware[] = EnforceLinkSession::class;
    } elseif ($mode === 'gate') {
        $dashboardMiddleware = array_merge(
            config('log-inspector.middleware', ['web', 'auth']),
            [AuthorizeLogInspector::class . ':view']
        );
    } elseif ($mode === 'both') {
        // In 'both' mode, we check if link session cookie is present or fallback to gate
        $dashboardMiddleware = array_merge(
            config('log-inspector.middleware', ['web']),
            [AuthorizeLogInspector::class . ':view']
        );
    }

    Route::prefix($path)
        ->domain($domain)
        ->middleware($dashboardMiddleware)
        ->name('log-inspector.')
        ->group(function () use ($mode) {
            // Dashboard views (Read-only and masked for link mode)
            Route::get('/', [DashboardController::class, 'index'])->name('index');
            Route::get('/show', [DashboardController::class, 'show'])->name('show');
            Route::get('/tail', [DashboardController::class, 'tail'])->name('tail');

            // End session action (Link mode)
            Route::post('/logout', [LinkExchangeController::class, 'logout'])->name('logout');

            // Gate-only actions: Raw download and Web deletion
            if ($mode === 'gate') {
                Route::get('/download', [FileActionController::class, 'download'])->name('download');

                if (config('log-inspector.web_delete_enabled', false)) {
                    Route::post('/delete', [FileActionController::class, 'destroy'])->name('destroy');
                }
            }
        });
}
