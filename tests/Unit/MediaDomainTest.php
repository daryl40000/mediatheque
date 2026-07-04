<?php

declare(strict_types=1);

namespace Moncine\Tests\Unit;

use Moncine\MediaDomain;
use Moncine\MediaDomainGuards;
use PHPUnit\Framework\TestCase;

final class MediaDomainTest extends TestCase
{
    public function testNormalizeDefaultsToFilm(): void
    {
        $this->assertSame(MediaDomain::FILM, MediaDomain::normalize(''));
        $this->assertSame(MediaDomain::FILM, MediaDomain::normalize('unknown'));
    }

    public function testCollectionImplementedForFilmMagazineAndGame(): void
    {
        $this->assertTrue(MediaDomain::isCollectionImplemented(MediaDomain::FILM));
        $this->assertTrue(MediaDomain::isCollectionImplemented(MediaDomain::MAGAZINE));
        $this->assertTrue(MediaDomain::isCollectionImplemented(MediaDomain::JEU));
        $this->assertTrue(MediaDomain::isCollectionImplemented(MediaDomain::BD));
        $this->assertFalse(MediaDomain::isCollectionImplemented(MediaDomain::LIVRE));
        $this->assertFalse(MediaDomain::isCollectionImplemented(MediaDomain::MUSIQUE));
    }

    public function testPlaceholderCollectionPaths(): void
    {
        $this->assertSame('/livres.php', MediaDomain::collectionPath(MediaDomain::LIVRE));
        $this->assertSame('/musique.php', MediaDomain::collectionPath(MediaDomain::MUSIQUE));
        $this->assertSame('/livres-envies.php', MediaDomain::wishlistPath(MediaDomain::LIVRE));
        $this->assertSame('/musique-envies.php', MediaDomain::wishlistPath(MediaDomain::MUSIQUE));
    }

    public function testTabSwitchToMusiqueUsesPlaceholderPage(): void
    {
        $this->assertSame(
            '/musique.php',
            MediaDomainGuards::redirectTargetForTabSwitch(MediaDomain::MUSIQUE, '/jeux.php')
        );
    }

    public function testTabSwitchFromPlaceholderToImplementedCollection(): void
    {
        $this->assertTrue(MediaDomainGuards::isPlaceholderCollectionPath('/musique.php'));
        $this->assertTrue(MediaDomainGuards::isPlaceholderCollectionPath('/livres-envies.php'));

        $this->assertSame(
            '/jeux.php',
            MediaDomainGuards::redirectTargetForTabSwitch(MediaDomain::JEU, '/musique.php')
        );
        $this->assertSame(
            '/bd.php',
            MediaDomainGuards::redirectTargetForTabSwitch(MediaDomain::BD, '/livres.php')
        );
        $this->assertSame(
            '/films.php',
            MediaDomainGuards::redirectTargetForTabSwitch(MediaDomain::FILM, '/musique-envies.php')
        );
    }

    public function testGameCollectionPaths(): void
    {
        $this->assertSame('/jeux.php', MediaDomain::collectionPath(MediaDomain::JEU));
        $this->assertSame('/jeux-envies.php', MediaDomain::wishlistPath(MediaDomain::JEU));
        $this->assertTrue(MediaDomainGuards::isGameOnlyPath('/jeux.php'));
        $this->assertTrue(MediaDomainGuards::isGameCollectionPath('/jeu.php'));
    }

    public function testTabSwitchFromQuizRedirectsToCollectionPage(): void
    {
        $this->assertTrue(MediaDomainGuards::isFilmOnlyPath('/quiz.php'));
        $this->assertSame(
            '/magazines.php',
            MediaDomainGuards::redirectTargetForTabSwitch(MediaDomain::MAGAZINE, '/quiz.php', 'reset=1')
        );
        $this->assertSame(
            '/bd.php',
            MediaDomainGuards::redirectTargetForTabSwitch(MediaDomain::BD, '/quiz.php', 'reset=1')
        );
        $this->assertSame(
            '/films.php?q=test',
            MediaDomainGuards::redirectTargetForTabSwitch(MediaDomain::FILM, '/films.php', 'q=test')
        );
    }

    public function testTabSwitchBetweenFilmAndMagazineCollections(): void
    {
        $this->assertTrue(MediaDomainGuards::isMagazineOnlyPath('/magazines.php'));
        $this->assertTrue(MediaDomainGuards::isMagazineOnlyPath('/serie-magazine.php'));
        $this->assertTrue(MediaDomainGuards::isFilmCollectionPath('/films.php'));

        $this->assertSame(
            '/films.php',
            MediaDomainGuards::redirectTargetForTabSwitch(MediaDomain::FILM, '/magazines.php')
        );
        $this->assertSame(
            '/films.php',
            MediaDomainGuards::redirectTargetForTabSwitch(MediaDomain::FILM, '/serie-magazine.php', 'id=3')
        );
        $this->assertSame(
            '/magazines.php',
            MediaDomainGuards::redirectTargetForTabSwitch(MediaDomain::MAGAZINE, '/films.php', 'q=test')
        );
        $this->assertSame(
            '/magazines.php',
            MediaDomainGuards::redirectTargetForTabSwitch(MediaDomain::MAGAZINE, '/jeux.php')
        );
        $this->assertSame(
            '/jeux.php',
            MediaDomainGuards::redirectTargetForTabSwitch(MediaDomain::JEU, '/films.php', 'q=test')
        );
        $this->assertSame(
            '/films.php',
            MediaDomainGuards::redirectTargetForTabSwitch(MediaDomain::FILM, '/jeux.php')
        );
    }

    public function testMediaDomainSwitchUrlPreservesExplicitPath(): void
    {
        $url = MediaDomainGuards::mediaDomainSwitchUrl(
            MediaDomain::MAGAZINE,
            '/magazine-numero.php?id=42'
        );
        $this->assertStringContainsString('domain=magazine', $url);
        $this->assertStringContainsString('redirect=%2Fmagazine-numero.php%3Fid%3D42', $url);
    }
}
