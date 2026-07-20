<?php

declare(strict_types=1);

namespace Moncine\Tests\Unit;

use Moncine\FilmPresentation;
use Moncine\FilmRepository;
use Moncine\SupportPhysique;
use Moncine\Tests\Support\MoncineTestCase;

/**
 * Dette qualité : helpers films hors de FilmRepositoryLegacy.
 */
final class FilmPresentationTest extends MoncineTestCase
{
    public function testParseBulkFilmIdsIgnoresInvalid(): void
    {
        $ids = FilmPresentation::parseBulkFilmIds([
            'film_ids' => ['1', '0', '-3', '42', 'x'],
        ]);
        $this->assertSame([1, 42], $ids);
    }

    public function testFormatSagaOrdre(): void
    {
        $this->assertSame('3', FilmPresentation::formatSagaOrdre(3));
        $this->assertSame('—', FilmPresentation::formatSagaOrdre(0));
    }

    public function testFormatSupportAndAnnee(): void
    {
        $this->assertSame('—', FilmPresentation::formatSupport(null));
        $this->assertSame('—', FilmPresentation::formatSupport(''));
        $dvdLabel = SupportPhysique::label(SupportPhysique::DVD);
        if ($dvdLabel !== '') {
            $this->assertSame($dvdLabel, FilmPresentation::formatSupport(SupportPhysique::DVD));
        }

        $this->assertSame('1999', FilmPresentation::formatAnnee(1999));
        $this->assertSame('—', FilmPresentation::formatAnnee(0));
    }

    public function testFormatDuree(): void
    {
        $this->assertSame('—', FilmPresentation::formatDuree(0));
        $this->assertSame('45 min', FilmPresentation::formatDuree(45));
        $this->assertSame('2 h', FilmPresentation::formatDuree(120));
        $this->assertSame('1 h 56 min', FilmPresentation::formatDuree(116));
    }

    public function testSplitStyles(): void
    {
        $this->assertSame(
            ['Action', 'Comédie', 'Drame'],
            FilmPresentation::splitStyles('Action, Comédie; Drame')
        );
        $this->assertSame([], FilmPresentation::splitStyles('  ,  ; '));
    }

    public function testRolesForPersonByNameAndId(): void
    {
        $film = [
            'realisateur' => 'Christopher Nolan',
            'realisateur_tmdb_id' => 525,
            'acteur_1' => 'Christian Bale',
            'acteur_1_tmdb_id' => 3894,
            'acteur_2' => '',
            'acteur_2_tmdb_id' => 0,
            'acteur_3' => '',
            'acteur_3_tmdb_id' => 0,
        ];

        $this->assertSame(['Réalisateur'], FilmPresentation::rolesForPerson($film, 'nolan'));
        $this->assertSame(['Acteur'], FilmPresentation::rolesForPerson($film, 'Bale'));
        $this->assertSame(['Réalisateur'], FilmPresentation::rolesForPerson($film, '525'));
        $this->assertSame([], FilmPresentation::rolesForPerson($film, ''));
    }

    public function testFilmRepositoryFacadeDelegatesToPresentation(): void
    {
        $this->assertSame(
            FilmPresentation::formatDuree(90),
            FilmRepository::formatDuree(90)
        );
        $this->assertSame(
            FilmPresentation::splitStyles('A|B'),
            FilmRepository::splitStyles('A|B')
        );
    }
}
