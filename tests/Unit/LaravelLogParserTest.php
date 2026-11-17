<?php

declare(strict_types=1);

namespace Explorin\LogInspector\Tests\Unit;

use Explorin\LogInspector\Enums\LogLevel;
use Explorin\LogInspector\Parsers\LaravelLogParser;
use Explorin\LogInspector\Security\Masker;
use PHPUnit\Framework\TestCase;

final class LaravelLogParserTest extends TestCase
{
    private LaravelLogParser $parser;

    protected function setUp(): void
    {
        parent::setUp();
        $this->parser = new LaravelLogParser(new Masker());
    }

    public function test_parses_single_line_log_entry(): void
    {
        $raw = '[2026-11-17 10:15:30] local.INFO: Application booted successfully. {"app":"explorin"} []';

        $entry = $this->parser->parse($raw);

        $this->assertSame('2026-11-17 10:15:30', $entry->formattedTimestamp());
        $this->assertSame('local', $entry->environment);
        $this->assertSame(LogLevel::INFO, $entry->level);
        $this->assertSame('Application booted successfully.', $entry->message);
        $this->assertSame(['app' => 'explorin'], $entry->context);
        $this->assertFalse($entry->hasException());
    }

    public function test_parses_multiline_entry_with_stacktrace(): void
    {
        $raw = <<<'LOG'
[2026-11-17 10:16:00] production.ERROR: Call to undefined function foo() {"user_id":42} []
[stacktrace]
RuntimeException: Call to undefined function foo() in /var/www/app/Services/UserService.php:88
#0 /var/www/app/Http/Controllers/UserController.php(45): App\Services\UserService->sendAlert()
#1 {main}
LOG;

        $entry = $this->parser->parse($raw);

        $this->assertSame('2026-11-17 10:16:00', $entry->formattedTimestamp());
        $this->assertSame('production', $entry->environment);
        $this->assertSame(LogLevel::ERROR, $entry->level);
        $this->assertTrue($entry->hasException());
        $this->assertSame('RuntimeException', $entry->exceptionClass);
        $this->assertSame('/var/www/app/Services/UserService.php', $entry->exceptionFile);
        $this->assertSame(88, $entry->exceptionLine);
        $this->assertCount(2, $entry->stackTrace);
        $this->assertNotNull($entry->exceptionHash());
    }

    public function test_masks_sensitive_data_during_parsing(): void
    {
        $raw = '[2026-11-17 10:16:05] production.CRITICAL: DB fail {"password":"secret_123"} []';

        $entry = $this->parser->parse($raw);

        $this->assertStringNotContainsString('secret_123', json_encode($entry->context) ?: '');
        $this->assertSame(LogLevel::CRITICAL, $entry->level);
    }
}
