<?php

declare(strict_types=1);

namespace Explorin\LogInspector\Tests\Unit;

use Explorin\LogInspector\Enums\LogLevel;
use Explorin\LogInspector\Parsers\JsonLogParser;
use PHPUnit\Framework\TestCase;

final class JsonLogParserTest extends TestCase
{
    public function test_parses_json_formatted_entry(): void
    {
        $parser = new JsonLogParser();
        $raw = '{"datetime":"2026-11-17 10:20:00","level_name":"ERROR","channel":"billing","message":"Stripe charge failed","context":{"amount":5000,"exception":{"class":"Stripe\\CardException","message":"Declined","file":"/var/www/Api.php","line":100,"trace":["#0 main()"]}},"extra":{"environment":"production"}}';

        $entry = $parser->parse($raw);

        $this->assertSame('2026-11-17 10:20:00', $entry->formattedTimestamp());
        $this->assertSame('production', $entry->environment);
        $this->assertSame(LogLevel::ERROR, $entry->level);
        $this->assertSame('billing', $entry->channel);
        $this->assertSame('Stripe charge failed', $entry->message);
        $this->assertTrue($entry->hasException());
        $this->assertSame('Stripe\\CardException', $entry->exceptionClass);
        $this->assertSame(100, $entry->exceptionLine);
        $this->assertCount(1, $entry->stackTrace);
    }
}
