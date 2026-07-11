<?php

declare(strict_types=1);

namespace Moncine\Tests\Unit;

use Moncine\LoginIdentifier;
use PHPUnit\Framework\TestCase;

final class LoginIdentifierTest extends TestCase
{
    public function testIsEmailLoginWhenAtSignPresent(): void
    {
        $this->assertTrue(LoginIdentifier::isEmailLogin('user@example.com'));
        $this->assertFalse(LoginIdentifier::isEmailLogin('CineFan'));
    }

    public function testNormalizePseudoLookupIsCaseInsensitive(): void
    {
        $this->assertSame('cinefan', LoginIdentifier::normalizePseudoLookup('  CineFan  '));
    }

    public function testNormalizeForThrottleUsesEmailPathWhenAtSign(): void
    {
        $this->assertSame('user@example.com', LoginIdentifier::normalizeForThrottle(' User@Example.com '));
        $this->assertSame('cinefan', LoginIdentifier::normalizeForThrottle('CineFan'));
    }
}
