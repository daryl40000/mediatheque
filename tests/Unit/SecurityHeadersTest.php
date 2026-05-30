<?php

declare(strict_types=1);

namespace Moncine\Tests\Unit;

use Moncine\SecurityHeaders;
use PHPUnit\Framework\TestCase;

final class SecurityHeadersTest extends TestCase
{
    protected function tearDown(): void
    {
        unset($_SERVER['HTTPS'], $_SERVER['HTTP_X_FORWARDED_PROTO'], $_SERVER['SERVER_PORT']);
        parent::tearDown();
    }

    public function testIsHttpsRequestDetectsHttpsServerVar(): void
    {
        $_SERVER['HTTPS'] = 'on';
        $this->assertTrue(SecurityHeaders::isHttpsRequest());
    }

    public function testIsHttpsRequestDetectsForwardedProto(): void
    {
        unset($_SERVER['HTTPS']);
        $_SERVER['HTTP_X_FORWARDED_PROTO'] = 'https';
        $this->assertTrue(SecurityHeaders::isHttpsRequest());
    }

    public function testIsHttpsRequestFalseOnPlainHttp(): void
    {
        unset($_SERVER['HTTPS'], $_SERVER['HTTP_X_FORWARDED_PROTO']);
        $_SERVER['SERVER_PORT'] = '8080';
        $this->assertFalse(SecurityHeaders::isHttpsRequest());
    }
}
