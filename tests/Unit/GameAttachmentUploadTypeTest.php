<?php

declare(strict_types=1);

namespace Moncine\Tests\Unit;

use Moncine\GameAttachmentRepository;
use Moncine\StoredObjectDelivery;
use PHPUnit\Framework\TestCase;

final class GameAttachmentUploadTypeTest extends TestCase
{
    public function testAcceptsPdfAndZip(): void
    {
        $this->assertNull(GameAttachmentRepository::validateUploadType('manuel.pdf', 'application/pdf'));
        $this->assertNull(GameAttachmentRepository::validateUploadType('pack.zip', 'application/zip'));
    }

    public function testRejectsSvgAndHtml(): void
    {
        $this->assertNotNull(GameAttachmentRepository::validateUploadType('x.svg', 'image/svg+xml'));
        $this->assertNotNull(GameAttachmentRepository::validateUploadType('x.html', 'text/html'));
    }

    public function testRejectsUnknownExtension(): void
    {
        $this->assertNotNull(GameAttachmentRepository::validateUploadType('script.php', 'application/octet-stream'));
        $this->assertNotNull(GameAttachmentRepository::validateUploadType('photo.jpg', 'image/jpeg'));
    }

    public function testInlineDeliveryBlocksSvg(): void
    {
        $this->assertFalse(StoredObjectDelivery::isInlineSafe('image/svg+xml'));
        $this->assertTrue(StoredObjectDelivery::isInlineSafe('image/jpeg'));
        $this->assertTrue(StoredObjectDelivery::isInlineSafe('application/pdf'));
    }
}
