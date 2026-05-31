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

    public function testCollectionImplementedForFilmAndMagazine(): void
    {
        $this->assertTrue(MediaDomain::isCollectionImplemented(MediaDomain::FILM));
        $this->assertTrue(MediaDomain::isCollectionImplemented(MediaDomain::MAGAZINE));
        $this->assertFalse(MediaDomain::isCollectionImplemented(MediaDomain::BD));
    }

    public function testTabSwitchFromQuizRedirectsToCollectionPage(): void
    {
        $this->assertTrue(MediaDomainGuards::isFilmOnlyPath('/quiz.php'));
        $this->assertSame(
            '/magazines.php',
            MediaDomainGuards::redirectTargetForTabSwitch(MediaDomain::MAGAZINE, '/quiz.php', 'reset=1')
        );
        $this->assertSame(
            '/films.php',
            MediaDomainGuards::redirectTargetForTabSwitch(MediaDomain::BD, '/quiz.php', 'reset=1')
        );
        $this->assertSame(
            '/films.php?q=test',
            MediaDomainGuards::redirectTargetForTabSwitch(MediaDomain::FILM, '/films.php', 'q=test')
        );
    }
}
