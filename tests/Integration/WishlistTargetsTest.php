<?php

declare(strict_types=1);

namespace Moncine\Tests\Integration;

use Moncine\Auth;
use Moncine\BibliothequeRepository;
use Moncine\FilmRepository;
use Moncine\FoyerRepository;
use Moncine\UserContext;
use Moncine\LibraryStatut;
use Moncine\OeuvreEanRepository;
use Moncine\SchemaMigrator;
use Moncine\SupportPhysique;
use Moncine\Tests\Support\MoncineTestCase;
use Moncine\WishlistTargetRepository;

final class WishlistTargetsTest extends MoncineTestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        (new SchemaMigrator(\Moncine\Database::getInstance()))->runPendingMigrations();
    }

    public function testAddMultipleSupportsAndPromoteClearsTargets(): void
    {
        $this->loginAsAdmin();
        $userId = \Moncine\UserContext::currentUserId();
        $foyerId = (new FoyerRepository())->currentFoyerIdForUser($userId);

        $oeuvreId = $this->seedCatalogOeuvre('Film Cible Envie', 'Réal Cible');
        $filmId = (new FilmRepository())->addFromCatalogOeuvre($oeuvreId, LibraryStatut::WISHLIST);
        $this->assertIsInt($filmId);

        $targets = new WishlistTargetRepository();
        $this->assertTrue(WishlistTargetRepository::tableExists());

        $dvd = $targets->add($filmId, SupportPhysique::DVD, '3760061234567');
        $this->assertIsInt($dvd);

        $bluray = $targets->add($filmId, SupportPhysique::BLURAY, '');
        $this->assertIsInt($bluray);

        $dup = $targets->add($filmId, SupportPhysique::DVD, '9999999999999');
        $this->assertIsString($dup);

        $list = $targets->listForBibliothequeId($filmId);
        $this->assertCount(2, $list);

        $map = $targets->mapByBibliothequeIds([$filmId]);
        $this->assertCount(2, $map[$filmId] ?? []);

        $this->assertTrue((new FilmRepository())->promoteToCollection(
            $filmId,
            '',
            '',
            $bluray
        ));
        $entry = (new BibliothequeRepository())->findById($filmId, $userId, $foyerId);
        $this->assertNotNull($entry);
        $this->assertSame(LibraryStatut::COLLECTION, $entry['statut'] ?? '');
        $this->assertSame(SupportPhysique::BLURAY, $entry['support_physique'] ?? '');
        $this->assertSame([], $targets->listForBibliothequeId($filmId));
    }

    public function testPromoteWithWishlistTargetPrefillsSupportAndEan(): void
    {
        $this->loginAsAdmin();
        $userId = UserContext::currentUserId();
        $foyerId = (new FoyerRepository())->currentFoyerIdForUser($userId);

        $oeuvreId = $this->seedCatalogOeuvre('Film Promote EAN', 'Réal Promote');
        $filmId = (new FilmRepository())->addFromCatalogOeuvre($oeuvreId, LibraryStatut::WISHLIST);
        $this->assertIsInt($filmId);

        $targetId = (new WishlistTargetRepository())->add(
            $filmId,
            SupportPhysique::BLURAY_4K,
            '4012345678901'
        );
        $this->assertIsInt($targetId);

        $this->assertTrue((new FilmRepository())->promoteToCollection($filmId, '', '', $targetId));
        $entry = (new BibliothequeRepository())->findById($filmId, $userId, $foyerId);
        $this->assertNotNull($entry);
        $this->assertSame(SupportPhysique::BLURAY_4K, $entry['support_physique'] ?? '');
        $this->assertSame('4012345678901', $entry['ean'] ?? '');
    }

    public function testUpdateManualStripsSpacesFromCollectionEan(): void
    {
        $this->loginAsAdmin();
        $oeuvreId = $this->seedCatalogOeuvre('Film EAN Espaces', 'Réal EAN Espaces');
        $filmId = (new FilmRepository())->addFromCatalogOeuvre($oeuvreId, LibraryStatut::COLLECTION);
        $this->assertIsInt($filmId);

        $result = (new FilmRepository())->updateManual($filmId, [
            'support_physique' => SupportPhysique::DVD,
            'format_image' => '',
            'format_son' => '',
            'saga' => '',
            'saga_ordre' => 0,
            'saison_numero' => 0,
            'saison_label' => '',
            'ean' => '376 006 123 4567',
        ]);
        $this->assertTrue($result);

        $film = (new FilmRepository())->findById($filmId);
        $this->assertNotNull($film);
        $this->assertSame('3760061234567', $film['ean'] ?? '');
    }

    public function testAddFromCatalogOeuvreEan(): void
    {
        $this->loginAsAdmin();

        $oeuvreId = $this->seedCatalogOeuvre('Film EAN Envie', 'Réal EAN');
        if (!OeuvreEanRepository::tableExists()) {
            $this->markTestSkipped('Table oeuvre_eans absente.');
        }

        $eanId = (new OeuvreEanRepository())->add(
            $oeuvreId,
            '4012345678901',
            SupportPhysique::BLURAY_4K,
            'Édition test'
        );
        $this->assertIsInt($eanId);

        $filmId = (new FilmRepository())->addFromCatalogOeuvre($oeuvreId, LibraryStatut::WISHLIST);
        $this->assertIsInt($filmId);

        $targets = new WishlistTargetRepository();
        $result = $targets->addFromCatalogEan($filmId, $eanId, $oeuvreId);
        $this->assertIsInt($result);

        $row = $targets->listForBibliothequeId($filmId)[0] ?? [];
        $this->assertSame(SupportPhysique::BLURAY_4K, $row['support_physique'] ?? '');
        $this->assertSame('4012345678901', $row['ean'] ?? '');
        $this->assertSame($eanId, (int) ($row['oeuvre_ean_id'] ?? 0));
    }
}
