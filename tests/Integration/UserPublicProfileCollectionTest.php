<?php

declare(strict_types=1);

namespace Moncine\Tests\Integration;

use Moncine\BibliothequeRepository;
use Moncine\FoyerRepository;
use Moncine\LibraryStatut;
use Moncine\Tests\Support\MoncineTestCase;
use Moncine\UserPublicProfileService;

final class UserPublicProfileCollectionTest extends MoncineTestCase
{
    public function testLastCollectionFilmsOrdersByCreatedAt(): void
    {
        $adminId = $this->loginAsAdmin();
        $foyerId = (new FoyerRepository())->currentFoyerIdForUser($adminId);
        $this->assertGreaterThan(0, $foyerId);

        $oeuvreOld = $this->seedCatalogOeuvre('Collection Old', 'Réalisateur C');
        $oeuvreNew = $this->seedCatalogOeuvre('Collection New', 'Réalisateur C');

        $bib = new BibliothequeRepository();
        $bib->insert($adminId, $foyerId, $oeuvreOld, ['statut' => LibraryStatut::COLLECTION]);
        usleep(1100);
        $bib->insert($adminId, $foyerId, $oeuvreNew, ['statut' => LibraryStatut::COLLECTION]);

        $recent = (new UserPublicProfileService())->lastCollectionFilms($adminId, 5);
        $this->assertNotEmpty($recent);
        $this->assertSame('Collection New', (string) ($recent[0]['titre'] ?? ''));

        $titles = array_map(static fn (array $row): string => (string) ($row['titre'] ?? ''), $recent);
        $this->assertContains('Collection Old', $titles);
    }
}
