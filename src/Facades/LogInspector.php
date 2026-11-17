<?php

declare(strict_types=1);

namespace Explorin\LogInspector\Facades;

use Explorin\LogInspector\Services\LogInspector as LogInspectorService;
use Illuminate\Support\Facades\Facade;

/**
 * @method static array files()
 * @method static \Explorin\LogInspector\Services\QueryBuilder query()
 * @method static array|\Explorin\LogInspector\Services\LogStatistics statistics(?string $file = null)
 *
 * @see \Explorin\LogInspector\Services\LogInspector
 */
final class LogInspector extends Facade
{
    protected static function getFacadeAccessor(): string
    {
        return LogInspectorService::class;
    }
}
