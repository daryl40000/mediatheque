<?php

declare(strict_types=1);

namespace Moncine\Tests\Unit;

use Moncine\LoanEligibility;
use Moncine\MediaDomain;
use PHPUnit\Framework\TestCase;

final class LoanEligibilityTest extends TestCase
{
    public function testFilmIsLoanableByDefault(): void
    {
        $this->assertTrue(LoanEligibility::isRowLoanable([
            'media_domain' => MediaDomain::FILM,
        ]));
    }

    public function testMagazineIsNotLoanable(): void
    {
        $this->assertFalse(LoanEligibility::isRowLoanable([
            'media_domain' => MediaDomain::MAGAZINE,
        ]));
    }

    public function testDigitalOnlyGameIsNotLoanable(): void
    {
        $this->assertFalse(LoanEligibility::isRowLoanable([
            'media_domain' => MediaDomain::JEU,
            'physical_supports' => '',
            'digital_stores' => 'steam',
            'is_digital' => 1,
        ]));
    }

    public function testPhysicalGameIsLoanable(): void
    {
        $this->assertTrue(LoanEligibility::isRowLoanable([
            'media_domain' => MediaDomain::JEU,
            'physical_supports' => 'cd_dvd',
            'digital_stores' => 'steam',
            'is_digital' => 1,
        ]));
    }

    public function testNonPretableBlocksLoan(): void
    {
        if (!LoanEligibility::hasNonPretableColumn()) {
            $this->markTestSkipped('Colonne non_pretable absente.');
        }

        $this->assertFalse(LoanEligibility::isRowLoanable([
            'media_domain' => MediaDomain::JEU,
            'physical_supports' => 'cd_dvd',
            'non_pretable' => 1,
        ]));
    }
}
