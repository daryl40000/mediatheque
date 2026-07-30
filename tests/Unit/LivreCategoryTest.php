<?php
/**
 * Tests unitaires — catégories de livres.
 */

declare(strict_types=1);

namespace Moncine\Tests\Unit;

use Moncine\LivreCategory;
use PHPUnit\Framework\TestCase;

final class LivreCategoryTest extends TestCase
{
    public function testNormalizeAliasesJeuxVideo(): void
    {
        self::assertSame(LivreCategory::JEUX_VIDEO, LivreCategory::normalizeLabel('jeux video'));
        self::assertSame(LivreCategory::JEUX_VIDEO, LivreCategory::normalizeLabel('Jeux vidéo'));
        self::assertSame(LivreCategory::CINEMA, LivreCategory::normalizeLabel('cinema'));
    }

    public function testIncludesJeuxVideoFromCsv(): void
    {
        self::assertTrue(LivreCategory::includesJeuxVideo('Cinéma, Jeux vidéo'));
        self::assertFalse(LivreCategory::includesJeuxVideo('Cinéma, Figurines'));
    }

    public function testNormalizeFromPostArray(): void
    {
        $serialized = LivreCategory::normalizeFromPost(['Jeux vidéo', 'Cinéma', 'jeux video']);
        self::assertSame('Jeux vidéo, Cinéma', $serialized);
    }
}
