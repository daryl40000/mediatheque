<?php

declare(strict_types=1);

namespace Moncine\Tests\Unit;

use Moncine\FilmEnricher;
use Moncine\TmdbClient;
use Moncine\TmdbMediaType;
use PHPUnit\Framework\TestCase;

final class FilmEnricherTmdbTitleTest extends TestCase
{
    public function testExtractLocalizedTitleMovieAndTv(): void
    {
        $this->assertSame(
            'Le Parrain',
            TmdbClient::extractLocalizedTitle(['title' => 'Le Parrain'], TmdbMediaType::MOVIE)
        );
        $this->assertSame(
            'Breaking Bad',
            TmdbClient::extractLocalizedTitle(['name' => 'Breaking Bad'], TmdbMediaType::TV)
        );
    }

    public function testMetaFromTmdbRecordAppliesFrenchTitleOnlyWhenRequested(): void
    {
        $enricher = new FilmEnricher();
        $method = new \ReflectionMethod(FilmEnricher::class, 'metaFromTmdbRecord');
        $method->setAccessible(true);

        $tmdb = [
            'poster_url' => '',
            'overview' => 'Synopsis',
            'runtime' => 120,
            'annee' => 1972,
            'tmdb_id' => 238,
            'media_type' => TmdbMediaType::MOVIE,
            'tv_kind' => '',
            'localized_title' => 'Le Parrain',
            'original_title' => 'The Godfather',
            'nationalite' => '',
            'director' => '',
            'director_tmdb_id' => 0,
            'acteur_1' => '',
            'acteur_2' => '',
            'acteur_3' => '',
            'acteur_1_tmdb_id' => 0,
            'acteur_2_tmdb_id' => 0,
            'acteur_3_tmdb_id' => 0,
            'styles' => '',
        ];

        $withoutTitle = $method->invoke($enricher, $tmdb, false);
        $this->assertArrayNotHasKey('titre', $withoutTitle);

        $withTitle = $method->invoke($enricher, $tmdb, true);
        $this->assertSame('Le Parrain', $withTitle['titre']);
        $this->assertSame('The Godfather', $withTitle['titre_original']);
    }
}
