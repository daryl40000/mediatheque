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
}
