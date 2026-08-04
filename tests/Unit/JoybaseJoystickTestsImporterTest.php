<?php

declare(strict_types=1);

namespace Moncine\Tests\Unit;

use Moncine\JoybaseJoystickTestsImporter;
use Moncine\MagazineRatingPeriod;
use PHPUnit\Framework\TestCase;

final class JoybaseJoystickTestsImporterTest extends TestCase
{
    public function testParseIssueNumber(): void
    {
        $this->assertSame(66, JoybaseJoystickTestsImporter::parseIssueNumber('066'));
        $this->assertSame(84, JoybaseJoystickTestsImporter::parseIssueNumber('084 (JS-01)'));
        $this->assertSame(0, JoybaseJoystickTestsImporter::parseIssueNumber(''));
    }

    public function testParseMagazineYear(): void
    {
        $this->assertSame(1995, JoybaseJoystickTestsImporter::parseMagazineYear('12/95'));
        $this->assertSame(2008, JoybaseJoystickTestsImporter::parseMagazineYear('07/08'));
        $this->assertSame(0, JoybaseJoystickTestsImporter::parseMagazineYear('bad'));
    }

    public function testParseJoybaseScore(): void
    {
        $this->assertNull(JoybaseJoystickTestsImporter::parseJoybaseScore('non noté'));
        $this->assertSame(75.0, JoybaseJoystickTestsImporter::parseJoybaseScore('75,00 %'));
        $this->assertSame(7.0, JoybaseJoystickTestsImporter::parseJoybaseScore('7'));
        $this->assertSame(7.5, JoybaseJoystickTestsImporter::parseJoybaseScore('7,5'));
        $this->assertSame(
            80.0,
            JoybaseJoystickTestsImporter::parseJoybaseScore("1J: 80%\n2J: 86%\n======\n80,00 %")
        );
        $this->assertNull(JoybaseJoystickTestsImporter::parseJoybaseScore('Assurément'));
        $this->assertFalse(JoybaseJoystickTestsImporter::parseJoybaseScore('1664/20'));
    }

    public function testParsePage(): void
    {
        $this->assertSame(106, JoybaseJoystickTestsImporter::parsePage('106'));
        $this->assertSame(14, JoybaseJoystickTestsImporter::parsePage('014 -025'));
        $this->assertSame(0, JoybaseJoystickTestsImporter::parsePage('supplément'));
    }

    public function testJoystickRatingPeriodsResolve(): void
    {
        $periods = JoybaseJoystickTestsImporter::joystickRatingPeriods();
        $this->assertSame('100', MagazineRatingPeriod::resolve('10', $periods, 66.0));
        $this->assertSame('100', MagazineRatingPeriod::resolve('10', $periods, 139.0));
        $this->assertSame('10', MagazineRatingPeriod::resolve('10', $periods, 140.0));
        $this->assertSame('10', MagazineRatingPeriod::resolve('10', $periods, 260.0));
    }

    public function testCanonicalizeJoybaseTitleMovesArticleToFront(): void
    {
        $this->assertSame(
            'The Dig',
            JoybaseJoystickTestsImporter::canonicalizeJoybaseTitle('Dig - The…')
        );
        $this->assertSame(
            "L'Enigme de maitre Lu",
            JoybaseJoystickTestsImporter::canonicalizeJoybaseTitle("Enigme de maitre Lu - L’…")
        );
        $this->assertSame(
            'The Ball',
            JoybaseJoystickTestsImporter::canonicalizeJoybaseTitle('Ball (The...)')
        );
        $this->assertSame(
            "L'Ecole des bébés",
            JoybaseJoystickTestsImporter::canonicalizeJoybaseTitle("Ecole des bébés (L'...)")
        );
        $this->assertSame(
            'Les Aventures de Sherlock Holmes - La boucle d\'argent',
            JoybaseJoystickTestsImporter::canonicalizeJoybaseTitle(
                'Aventures de Sherlock Holmes (Les...) - La boucle d\'argent'
            )
        );
        $this->assertSame(
            'The Binding of Isaac - Wrath of the Lamb',
            JoybaseJoystickTestsImporter::canonicalizeJoybaseTitle(
                'Binding of Isaac - Wrath of the Lamb (The...)'
            )
        );
        $this->assertSame(
            'The Entente - WWI Battlefields',
            JoybaseJoystickTestsImporter::canonicalizeJoybaseTitle('Entente, The - WWI Battlefields')
        );
        // Titre déjà naturel : inchangé.
        $this->assertSame('The Dig', JoybaseJoystickTestsImporter::canonicalizeJoybaseTitle('The Dig'));
    }

    public function testMatchKeyIgnoresAccents(): void
    {
        $this->assertSame(
            JoybaseJoystickTestsImporter::matchKey("L'énigme de maître Lu"),
            JoybaseJoystickTestsImporter::matchKey("L'Enigme de maitre Lu")
        );
        $this->assertSame(
            JoybaseJoystickTestsImporter::matchKey('The Dig'),
            JoybaseJoystickTestsImporter::matchKey('Dig - The…')
        );
    }
}
