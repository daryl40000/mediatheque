<?php

declare(strict_types=1);

namespace Moncine\Tests\Unit;

use Moncine\MagazineExternalUrl;
use PHPUnit\Framework\TestCase;

/**
 * Vérifie le nettoyage des URLs et la construction des liens ABM.
 */
final class MagazineExternalUrlTest extends TestCase
{
    public function testSanitizeAcceptsHttpsAndUpgradesHttp(): void
    {
        $this->assertSame(
            'https://www.abandonware-magazines.org/affiche_mag.php?mag=29',
            MagazineExternalUrl::sanitize(
                'https://www.abandonware-magazines.org/affiche_mag.php?mag=29'
            )
        );
        $this->assertSame(
            'https://example.com/mag',
            MagazineExternalUrl::sanitize('http://example.com/mag')
        );
        $this->assertSame('', MagazineExternalUrl::sanitize('ftp://example.com/x'));
        $this->assertSame('', MagazineExternalUrl::sanitize('javascript:alert(1)'));
        $this->assertSame('', MagazineExternalUrl::sanitize(''));
    }

    public function testAbmUrls(): void
    {
        $this->assertSame(
            'https://www.abandonware-magazines.org/affiche_mag.php?mag=29',
            MagazineExternalUrl::abmSeriesUrl(29)
        );
        $this->assertSame(
            'https://www.abandonware-magazines.org/affiche_mag.php?mag=29&num=42',
            MagazineExternalUrl::abmIssueUrl(29, 42)
        );
        $this->assertSame('', MagazineExternalUrl::abmSeriesUrl(0));
        $this->assertSame('', MagazineExternalUrl::abmIssueUrl(29, 0));
    }

    public function testResolveSeriesUrlPrefersExplicitThenNotes(): void
    {
        $this->assertSame(
            'https://example.com/revue',
            MagazineExternalUrl::resolveSeriesUrl([
                'external_url' => 'https://example.com/revue',
                'notes' => 'abm_magazine_id=29',
            ])
        );
        $this->assertSame(
            'https://www.abandonware-magazines.org/affiche_mag.php?mag=29',
            MagazineExternalUrl::resolveSeriesUrl([
                'external_url' => '',
                'notes' => 'abm_magazine_id=29',
            ])
        );
    }
}
