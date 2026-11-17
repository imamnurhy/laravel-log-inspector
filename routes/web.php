<?php

use Explorin\LogInspector\Http\Controllers\DashboardController;
use Explorin\LogInspector\Http\Controllers\FileActionController;
use Explorin\LogInspector\Http\Middleware\AuthorizeLogInspector;
use Illuminate\Support\Facades\Route;

Route::middleware(config('log-inspector.middleware', ['web', 'auth']))
    ->prefix(config('log-inspector.path', 'log-inspector'))
    ->domain(config('log-inspector.domain'))
    ->name('log-inspector.')
    ->group(function () {
        Route::get('/', [DashboardController::class, 'index'])
            ->middleware(AuthorizeLogInspector::class . ':view')
            ->name('index');

        Route::get('/show', [DashboardController::class, 'show'])
            ->middleware(AuthorizeLogInspector::class . ':view')
            ->name('show');

        // File download & delete actions
        Route::get('/download', [FileActionController::class, 'download'])
            ->name('download');

        Route::post('/delete', [FileActionController::class, 'destroy'])
            ->name('destroy');
    });
