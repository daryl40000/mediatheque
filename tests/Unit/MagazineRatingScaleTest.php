<?php

declare(strict_types=1);

namespace Moncine\Tests\Unit;

use Moncine\MagazineRatingScale;
use PHPUnit\Framework\TestCase;

final class MagazineRatingScaleTest extends TestCase
{
    public function testNormalizeAcceptsAnyPositiveMax(): void
    {
        $this->assertSame('5', MagazineRatingScale::normalize('5'));
        $this->assertSame('6', MagazineRatingScale::normalize(6));
        $this->assertSame('50', MagazineRatingScale::normalize('sur 50'));
        $this->assertSame('20', MagazineRatingScale::normalize('/20'));
        $this->assertSame('100', MagazineRatingScale::normalize('percent'));
        $this->assertSame('100', MagazineRatingScale::normalize('%'));
        $this->assertNull(MagazineRatingScale::normalize(''));
        $this->assertNull(MagazineRatingScale::normalize('aucune'));
        $this->assertNull(MagazineRatingScale::normalize('0'));
        $this->assertNull(MagazineRatingScale::normalize('2000'));
    }

    public function testUsesStarsOnlyBelowTen(): void
    {
        $this->assertTrue(MagazineRatingScale::usesStars('5'));
        $this->assertTrue(MagazineRatingScale::usesStars('6'));
        $this->assertTrue(MagazineRatingScale::usesStars('7'));
        $this->assertFalse(MagazineRatingScale::usesStars('10'));
        $this->assertFalse(MagazineRatingScale::usesStars('50'));
        $this->assertFalse(MagazineRatingScale::usesStars('100'));
        $this->assertFalse(MagazineRatingScale::usesStars(null));
    }

    public function testParseScoreAcceptsHalfPointsAndBounds(): void
    {
        $this->assertSame(3.5, MagazineRatingScale::parseScore('3,5', '5'));
        $this->assertSame(3.5, MagazineRatingScale::parseScore('3.5', '6'));
        $this->assertSame(42.0, MagazineRatingScale::parseScore('42', '50'));
        $this->assertSame(0.0, MagazineRatingScale::parseScore('0', '10'));
        $this->assertSame(10.0, MagazineRatingScale::parseScore('10', '10'));
        $this->assertNull(MagazineRatingScale::parseScore('', '10'));
        $this->assertIsString(MagazineRatingScale::parseScore('11', '10'));
        $this->assertIsString(MagazineRatingScale::parseScore('-1', '5'));
        $this->assertIsString(MagazineRatingScale::parseScore('3', null));
    }

    public function testToPercentUsesRuleOfThree(): void
    {
        $this->assertSame(80.0, MagazineRatingScale::toPercent(4.0, '5'));
        $this->assertSame(50.0, MagazineRatingScale::toPercent(3.0, '6'));
        $this->assertSame(50.0, MagazineRatingScale::toPercent(3.5, '7'));
        $this->assertSame(84.0, MagazineRatingScale::toPercent(42.0, '50'));
        $this->assertSame(42.0, MagazineRatingScale::toPercent(42.0, '100'));
        $this->assertNull(MagazineRatingScale::toPercent(null, '10'));
        $this->assertNull(MagazineRatingScale::toPercent(8.0, null));
    }

    public function testFormatDisplayAndStars(): void
    {
        $this->assertSame('Sur 20', MagazineRatingScale::label('20'));
        $this->assertSame('4/5', MagazineRatingScale::formatDisplay(4.0, '5'));
        $this->assertSame('3,5/6', MagazineRatingScale::formatDisplay(3.5, '6'));
        $this->assertSame('42/50', MagazineRatingScale::formatDisplay(42.0, '50'));
        $this->assertSame('75 %', MagazineRatingScale::formatDisplay(75.0, '100'));

        $this->assertSame(
            ['full', 'full', 'full', 'half', 'empty'],
            MagazineRatingScale::starParts(3.5, '5')
        );
        $this->assertSame(
            ['full', 'full', 'full', 'full', 'full', 'empty'],
            MagazineRatingScale::starParts(5.0, '6')
        );
        $this->assertSame([], MagazineRatingScale::starParts(8.0, '10'));
    }
}
