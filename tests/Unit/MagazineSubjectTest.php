<?php

declare(strict_types=1);

namespace Moncine\Tests\Unit;

use Moncine\MagazineSeriesTag;
use Moncine\MagazineSubject;
use PHPUnit\Framework\TestCase;

final class MagazineSubjectTest extends TestCase
{
    public function testNormalizeCategoryAndDisplayLabel(): void
    {
        $this->assertSame(MagazineSubject::TEST, MagazineSubject::normalizeCategory('jeu'));
        $this->assertSame(MagazineSubject::TEST, MagazineSubject::normalizeCategory('auto'));
        $this->assertSame(MagazineSubject::TEST, MagazineSubject::normalizeCategory('test_voiture'));
        $this->assertSame(
            ['test', 'test_jeu', 'test_voiture', 'test_materiel'],
            MagazineSubject::categoryFilterValues(MagazineSubject::TEST)
        );
        $this->assertSame(['preview'], MagazineSubject::categoryFilterValues(MagazineSubject::PREVIEW));
        $this->assertSame(
            'Peugeot 308 III (2024)',
            MagazineSubject::displayLabel('Peugeot 308 III', '', 2024)
        );
        $this->assertSame(
            'Gran Turismo 7 (PS5 · 2024)',
            MagazineSubject::displayLabel('Gran Turismo 7', 'PS5', 2024)
        );
        $this->assertSame(
            'RTX 4080',
            MagazineSubject::displayLabel('RTX 4080', '')
        );
    }

    public function testParutionYearFromIssue(): void
    {
        $this->assertSame(2024, MagazineSubject::parutionYearFromIssue(['date_parution' => '2024-06-15']));
        $this->assertSame(0, MagazineSubject::parutionYearFromIssue(['date_parution' => '']));
    }

    public function testSeriesTagResolution(): void
    {
        $seriesPc = ['tags' => 'PC'];
        $this->assertSame('PC', MagazineSeriesTag::resolveDetailForSubject($seriesPc, ''));
        $this->assertSame('PC', MagazineSeriesTag::singleTag($seriesPc));

        $seriesMulti = ['tags' => 'PC, PS5'];
        $this->assertSame('PS5', MagazineSeriesTag::resolveDetailForSubject($seriesMulti, 'PS5'));
        $this->assertSame('', MagazineSeriesTag::resolveDetailForSubject($seriesMulti, ''));
        $this->assertTrue(MagazineSeriesTag::requiresTagChoice($seriesMulti));

        $this->assertSame(['PC', 'PS5'], MagazineSeriesTag::parseList('PC, PS5, pc'));
        $this->assertSame('PC, PS5', MagazineSeriesTag::normalizeInput('PC, PS5, pc'));
        $this->assertSame('PC, PS5', MagazineSeriesTag::normalizeFromPost(['PC', 'PS5', 'pc']));
    }

    public function testNormalizeLabelKeyGroupsSimilarSpellings(): void
    {
        $this->assertSame(
            MagazineSubject::normalizeLabelKey('After Life'),
            MagazineSubject::normalizeLabelKey('Afterlife')
        );
        $this->assertSame(
            MagazineSubject::normalizeLabelKey('Gran Turismo 7'),
            MagazineSubject::normalizeLabelKey('GranTurismo7')
        );
    }
}
