<?php

declare(strict_types=1);

namespace Moncine\Tests\Unit;

use Moncine\ShareLinkShare;
use PHPUnit\Framework\TestCase;

final class ShareLinkShareTest extends TestCase
{
    public function testBlueskyIntentContainsEncodedUrl(): void
    {
        $url = 'https://moncine.example/partage.php?t=abc123';
        $intent = ShareLinkShare::blueskyIntentUrl($url, 'Mes films');

        $this->assertStringStartsWith('https://bsky.app/intent/compose?text=', $intent);
        $this->assertStringContainsString(rawurlencode($url), $intent);
    }

    public function testMailtoContainsSubjectAndBody(): void
    {
        $mailto = ShareLinkShare::mailtoUrl('https://example.test/p', 'Mes envies', 'ami@test.local');

        $this->assertStringStartsWith('mailto:', $mailto);
        $this->assertStringContainsString('ami%40test.local', $mailto);
        $this->assertStringContainsString('subject=', $mailto);
        $this->assertStringContainsString('body=', $mailto);
    }
}
