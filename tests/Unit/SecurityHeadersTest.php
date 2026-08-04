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

    public function testIsHttpsRequestIgnoresForwardedProtoWithoutTrustProxy(): void
    {
        if (!defined('MONCINE_TRUST_PROXY') || MONCINE_TRUST_PROXY) {
            $this->markTestSkipped('Nécessite MONCINE_TRUST_PROXY=false (défaut des tests).');
        }

        unset($_SERVER['HTTPS']);
        $_SERVER['HTTP_X_FORWARDED_PROTO'] = 'https';
        $_SERVER['SERVER_PORT'] = '8080';
        $this->assertFalse(SecurityHeaders::isHttpsRequest());
    }

    public function testIsHttpsRequestFalseOnPlainHttp(): void
    {
        unset($_SERVER['HTTPS'], $_SERVER['HTTP_X_FORWARDED_PROTO']);
        $_SERVER['SERVER_PORT'] = '8080';
        $this->assertFalse(SecurityHeaders::isHttpsRequest());
    }
}
