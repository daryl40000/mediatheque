<?php

declare(strict_types=1);

namespace Moncine\Tests\Unit;

use Moncine\MagazineSupport;
use PHPUnit\Framework\TestCase;

final class MagazineSupportTest extends TestCase
{
    public function testFormatAndParseTags(): void
    {
        $this->assertSame('papier,pdf', MagazineSupport::formatTagsForStorage(true, true));
        $this->assertSame(['papier', 'pdf'], MagazineSupport::parseTags('papier,pdf'));
    }

    public function testParseLegacyFreeText(): void
    {
        $tags = MagazineSupport::parseTags('Papier + PDF');
        $this->assertContains(MagazineSupport::TAG_PAPIER, $tags);
        $this->assertContains(MagazineSupport::TAG_PDF, $tags);
    }

    public function testTagsForIssueAddsPdfWhenStoredObjectPresent(): void
    {
        $tags = MagazineSupport::tagsForIssue([
            'support_physique' => 'papier',
            'stored_object_id' => 12,
        ]);
        $this->assertContains(MagazineSupport::TAG_PAPIER, $tags);
        $this->assertContains(MagazineSupport::TAG_PDF, $tags);
        $this->assertTrue(MagazineSupport::isPossessed([
            'support_physique' => 'papier',
            'stored_object_id' => 12,
        ]));
    }

    public function testIsPossessedFalseWhenNoTags(): void
    {
        $this->assertFalse(MagazineSupport::isPossessed([
            'support_physique' => '',
            'stored_object_id' => 0,
        ]));
    }
}
