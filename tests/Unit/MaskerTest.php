<?php

declare(strict_types=1);

namespace Explorin\LogInspector\Tests\Unit;

use Explorin\LogInspector\Security\Masker;
use PHPUnit\Framework\TestCase;

final class MaskerTest extends TestCase
{
    public function test_masks_passwords_and_tokens_in_json(): void
    {
        $masker = new Masker(enabled: true);
        $input = '{"user":"john","password":"super_secret_password","token":"xyz_12345"}';

        $masked = $masker->mask($input);

        $this->assertStringNotContainsString('super_secret_password', $masked);
        $this->assertStringNotContainsString('xyz_12345', $masked);
        $this->assertStringContainsString('******', $masked);
        $this->assertStringContainsString('"user":"john"', $masked);
    }

    public function test_masks_authorization_bearer_headers(): void
    {
        $masker = new Masker(enabled: true);
        $input = 'Request Header: Authorization: Bearer eyJhbGciOiJIUzI1NiIsInR5cCI6IkpXVCJ9.payload.sig to endpoint';

        $masked = $masker->mask($input);

        $this->assertStringNotContainsString('eyJhbGciOiJIUzI1NiIsInR5cCI6IkpXVCJ9', $masked);
        $this->assertStringContainsString('Bearer ******', $masked);
    }

    public function test_passes_through_when_disabled(): void
    {
        $masker = new Masker(enabled: false);
        $input = 'password: my_password';

        $this->assertSame($input, $masker->mask($input));
    }
}
