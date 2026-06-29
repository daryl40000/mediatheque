<?php

declare(strict_types=1);

namespace Moncine\Tests\Unit;

use Moncine\AppUrl;
use Moncine\Auth;
use PHPUnit\Framework\TestCase;

final class AppUrlWebPathTest extends TestCase
{
    protected function tearDown(): void
    {
        putenv('MONCINE_WEB_BASE_PATH');
        unset($_SERVER['SCRIPT_NAME'], $_SERVER['REQUEST_URI']);
        parent::tearDown();
    }

    public function testWebPathUsesConfiguredBasePrefix(): void
    {
        putenv('MONCINE_WEB_BASE_PATH=/mediatheque');
        $this->assertSame('/mediatheque/poster.php', AppUrl::webPath('/poster.php'));
    }

    public function testAuthRecognizesPosterUnderSubdirectory(): void
    {
        putenv('MONCINE_WEB_BASE_PATH=/mediatheque');
        $_SERVER['SCRIPT_NAME'] = '/mediatheque/poster.php';
        $_SERVER['REQUEST_URI'] = '/mediatheque/poster.php?id=12&ext=jpg';

        $this->assertTrue(Auth::isPublicWebPath('/poster.php'));
    }
}
