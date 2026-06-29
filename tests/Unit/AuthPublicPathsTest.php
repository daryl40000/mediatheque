<?php

declare(strict_types=1);

namespace Moncine\Tests\Unit;

use Moncine\Auth;
use PHPUnit\Framework\TestCase;

final class AuthPublicPathsTest extends TestCase
{
    public function testShareVisitorPathsArePublic(): void
    {
        $this->assertTrue(Auth::isPublicWebPath('/partage.php'));
        $this->assertTrue(Auth::isPublicWebPath('/partage-film.php'));
        $this->assertTrue(Auth::isPublicWebPath('/partage-jeux.php'));
        $this->assertTrue(Auth::isPublicWebPath('/partage-jeu.php'));
    }

    public function testPosterDeliveryPathsArePublic(): void
    {
        $this->assertTrue(Auth::isPublicWebPath('/poster.php'));
        $this->assertTrue(Auth::isPublicWebPath('/posters/42.jpg'));
        $this->assertTrue(Auth::isPublicWebPath('/posters/s7.webp'));
    }

    public function testProtectedPathsRequireLogin(): void
    {
        $this->assertFalse(Auth::isPublicWebPath('/films.php'));
        $this->assertFalse(Auth::isPublicWebPath('/gerer-partages.php'));
    }
}
