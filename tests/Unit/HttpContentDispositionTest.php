<?php

declare(strict_types=1);

namespace Moncine\Tests\Unit;

use Moncine\HttpContentDisposition;
use PHPUnit\Framework\TestCase;

final class HttpContentDispositionTest extends TestCase
{
    public function testBuildInlineWithUtf8Filename(): void
    {
        $header = HttpContentDisposition::build('inline', 'café.pdf');
        $this->assertStringStartsWith('inline; filename="', $header);
        $this->assertStringContainsString('filename*=UTF-8\'\'', $header);
        $this->assertStringContainsString('caf', $header);
    }

    public function testBuildSanitizesPathSegments(): void
    {
        $header = HttpContentDisposition::build('attachment', '../../secret.pdf');
        $this->assertStringContainsString('filename="secret.pdf"', $header);
        $this->assertStringNotContainsString('..', $header);
    }
}
