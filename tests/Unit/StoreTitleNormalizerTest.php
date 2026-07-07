<?php

declare(strict_types=1);

namespace Moncine\Tests\Unit;

use Moncine\StoreTitleNormalizer;
use PHPUnit\Framework\TestCase;

final class StoreTitleNormalizerTest extends TestCase
{
    public function testSearchQueriesStripsSuffixAfterColon(): void
    {
        $queries = StoreTitleNormalizer::searchQueries('The Witcher 3: Wild Hunt');

        $this->assertContains('The Witcher 3: Wild Hunt', $queries);
        $this->assertContains('The Witcher 3', $queries);
    }

    public function testSearchQueriesRemovesEditionWords(): void
    {
        $queries = StoreTitleNormalizer::searchQueries('Hades — Definitive Edition');

        $this->assertNotEmpty($queries);
        $this->assertTrue(
            in_array('Hades', $queries, true) || in_array('Hades —', $queries, true),
            'Une variante sans « Definitive Edition » doit exister.'
        );
    }

    public function testSearchQueriesFromRowUsesDisplayTitle(): void
    {
        $queries = StoreTitleNormalizer::searchQueriesFromRow([
            'titre' => 'Jeu A',
            'titre_original' => 'Game A',
        ]);

        $this->assertContains('Jeu A', $queries);
    }

    public function testStripEditionWordsKeepsBaseTitle(): void
    {
        $this->assertSame('Hades', StoreTitleNormalizer::stripEditionWords('Hades GOTY'));
    }
}
