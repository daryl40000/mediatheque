<?php

declare(strict_types=1);

namespace Moncine\Tests\Unit;

use Moncine\PublicationType;
use PHPUnit\Framework\TestCase;

final class PublicationTypeTest extends TestCase
{
    public function testFormatMensuelAsMonthYear(): void
    {
        $label = PublicationType::formatParutionDate('2024-03-15', PublicationType::MENSUEL);
        $this->assertStringContainsString('2024', $label);
        $this->assertStringContainsString('mars', mb_strtolower($label));
    }

    public function testFormatHebdomadaireAsWeek(): void
    {
        $label = PublicationType::formatParutionDate('2024-03-15', PublicationType::HEBDOMADAIRE);
        $this->assertStringContainsString('Semaine', $label);
    }

    public function testSuggestNextNumero(): void
    {
        $this->assertSame(1.0, PublicationType::suggestNextNumeroOrdre(0));
        $this->assertSame(124.0, PublicationType::suggestNextNumeroOrdre(123));
    }

    public function testParseParutionDateFilter(): void
    {
        $this->assertSame(['year' => 2024, 'month' => null], PublicationType::parseParutionDateFilter('2024'));
        $this->assertSame(['year' => 2024, 'month' => 6], PublicationType::parseParutionDateFilter('06/2024'));
        $this->assertSame(['year' => 2024, 'month' => 6], PublicationType::parseParutionDateFilter('2024-06'));
        $this->assertSame(['year' => 2024, 'month' => 6], PublicationType::parseParutionDateFilter('juin 2024'));
        $this->assertNull(PublicationType::parseParutionDateFilter(''));
    }

    public function testParseParutionDateLabel(): void
    {
        $this->assertSame('2002-01-01', PublicationType::parseParutionDateLabel('Janvier 2002'));
        $this->assertSame('2018-03-01', PublicationType::parseParutionDateLabel('Mars 2018'));
        $this->assertSame('2020-07-01', PublicationType::parseParutionDateLabel('Juillet / aout 2020'));
        $this->assertSame('2020-07-01', PublicationType::parseParutionDateLabel('Juillet/aout 2020'));
        $this->assertSame('2020-07-01', PublicationType::parseParutionDateLabel('juillet / août 2020'));
        $this->assertSame('1986-03-01', PublicationType::parseParutionDateLabel('mars 1986'));
        $this->assertSame('1991-01-01', PublicationType::parseParutionDateLabel('1991'));
        $this->assertSame('2024-06-15', PublicationType::parseParutionDateLabel('15/06/2024'));
        $this->assertSame('2024-03-15', PublicationType::parseParutionDateLabel('2024-03-15'));
        $this->assertNull(PublicationType::parseParutionDateLabel(''));
        $this->assertNull(PublicationType::parseParutionDateLabel('sans date'));
    }

    public function testFormatParutionDateAcceptsFrenchLabel(): void
    {
        $label = PublicationType::formatParutionDate('mars 2018', PublicationType::MENSUEL);
        $this->assertStringContainsString('2018', $label);
        $this->assertStringContainsString('mars', mb_strtolower($label));
    }
}
