<?php

declare(strict_types=1);

namespace Moncine\Tests\Unit;

use Moncine\UploadLimits;
use PHPUnit\Framework\TestCase;

final class UploadLimitsTest extends TestCase
{
    protected function tearDown(): void
    {
        unset($_SERVER['REQUEST_METHOD'], $_SERVER['CONTENT_LENGTH']);
        $_POST = [];
        $_FILES = [];
        parent::tearDown();
    }

    public function testPostBodyWasDiscardedDetectsEmptyPostWithContentLength(): void
    {
        $_SERVER['REQUEST_METHOD'] = 'POST';
        $_SERVER['CONTENT_LENGTH'] = '50000000';
        $_POST = [];
        $_FILES = [];

        $this->assertTrue(UploadLimits::postBodyWasDiscarded());
    }

    public function testPostBodyWasDiscardedFalseWhenPostPresent(): void
    {
        $_SERVER['REQUEST_METHOD'] = 'POST';
        $_SERVER['CONTENT_LENGTH'] = '500';
        $_POST = ['csrf_token' => 'x'];

        $this->assertFalse(UploadLimits::postBodyWasDiscarded());
    }

    public function testMaxPosterBytesUsesApplicationConstant(): void
    {
        $this->assertSame(10 * 1024 * 1024, UploadLimits::maxPosterBytes());
        $this->assertSame('10 Mo', UploadLimits::maxPosterBytesLabel());
    }

    public function testMaxPostersZipBytesUsesApplicationConstant(): void
    {
        $this->assertSame(200 * 1024 * 1024, UploadLimits::maxPostersZipBytes());
        $this->assertSame('200 Mo', UploadLimits::maxPostersZipBytesLabel());
    }
}
