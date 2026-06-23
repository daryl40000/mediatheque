<?php

declare(strict_types=1);

namespace Moncine\Tests\Integration;

use Moncine\LibraryStatut;
use Moncine\OeuvreEanRepository;
use Moncine\SchemaMigrator;
use Moncine\ShareLinkFilmRepository;
use Moncine\ShareLinkGameRepository;
use Moncine\ShareLinkRateLimit;
use Moncine\ShareLinkRepository;
use Moncine\ShareLinkScope;
use Moncine\ShareLinkService;
use Moncine\SupportPhysique;
use Moncine\GameRepository;
use Moncine\Tests\Support\MoncineTestCase;
use Moncine\UserContext;
use Moncine\WishlistTargetRepository;

final class ShareFeaturesTest extends MoncineTestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        (new SchemaMigrator(\Moncine\Database::getInstance()))->runPendingMigrations();
        ShareLinkRateLimit::resetForTests();
    }

    public function testOeuvreEanUniquePerSupport(): void
    {
        $this->loginAsAdmin();
        $db = \Moncine\Database::getInstance();
        $db->exec(
            "INSERT INTO oeuvres (titre, moncine_kind) VALUES ('Test EAN Film', 'film')"
        );
        $oeuvreId = (int) $db->lastInsertId();

        $repo = new OeuvreEanRepository();
        $this->assertIsInt($repo->add($oeuvreId, '3760123456789', SupportPhysique::DVD));

        $dupSupport = $repo->add($oeuvreId, '3760987654321', SupportPhysique::DVD);
        $this->assertIsString($dupSupport);

        $this->assertIsInt($repo->add($oeuvreId, '3760987654321', SupportPhysique::BLURAY));
        $row = $repo->findByEan('3760987654321');
        $this->assertNotNull($row);
        $this->assertSame($oeuvreId, (int) ($row['oeuvre_id'] ?? 0));
    }

    public function testShareLinkVisitorReadOnly(): void
    {
        $userId = $this->loginAsAdmin();
        $foyerId = UserContext::currentFoyerId();
        $db = \Moncine\Database::getInstance();

        $db->exec("INSERT INTO oeuvres (titre, moncine_kind) VALUES ('Partage Film', 'film')");
        $oeuvreId = (int) $db->lastInsertId();
        $db->prepare(
            'INSERT INTO bibliotheque (oeuvre_id, foyer_id, statut, support_physique)
             VALUES (?, ?, ?, ?)'
        )->execute([$oeuvreId, $foyerId, LibraryStatut::COLLECTION, SupportPhysique::DVD]);
        $filmId = (int) $db->lastInsertId();

        $db->prepare(
            'INSERT INTO bibliotheque (oeuvre_id, user_id, statut)
             VALUES (?, ?, ?)'
        )->execute([$oeuvreId, $userId, LibraryStatut::WISHLIST]);
        $wishId = (int) $db->lastInsertId();

        $service = new ShareLinkService();
        $collection = $service->create($userId, $foyerId, ShareLinkScope::COLLECTION, 'test');
        $this->assertIsArray($collection);
        $token = (string) $collection['token'];

        $wish = $service->create($userId, $foyerId, ShareLinkScope::WISHLIST, 'envies');
        $this->assertIsArray($wish);
        $wishToken = (string) $wish['token'];

        $filmRepo = new ShareLinkFilmRepository();
        $colLink = $service->resolve($token);
        $this->assertNotNull($colLink);
        $colFilms = $filmRepo->findAllForLink($colLink);
        $this->assertCount(1, $colFilms);
        $this->assertSame($filmId, (int) ($colFilms[0]['id'] ?? 0));

        $wishLink = $service->resolve($wishToken);
        $this->assertNotNull($wishLink);
        $wishFilms = $filmRepo->findAllForLink($wishLink);
        $this->assertCount(1, $wishFilms);
        $this->assertSame($wishId, (int) ($wishFilms[0]['id'] ?? 0));

        $this->assertNull($filmRepo->findByIdForLink($colLink, $wishId));
        $this->assertNotNull($filmRepo->findByIdForLink($colLink, $filmId));

        $this->assertTrue($service->revoke((int) ($colLink['id'] ?? 0), $userId));
        $this->assertNull($service->resolve($token));
    }

    public function testWishlistShareLinkExposesTargets(): void
    {
        $userId = $this->loginAsAdmin();
        $foyerId = UserContext::currentFoyerId();
        if (!WishlistTargetRepository::tableExists()) {
            $this->markTestSkipped('Table wishlist_targets absente.');
        }

        $oeuvreId = $this->seedCatalogOeuvre('Film Partage Cibles', 'Réal Partage');
        $filmId = (new \Moncine\FilmRepository())->addFromCatalogOeuvre($oeuvreId, LibraryStatut::WISHLIST);
        $this->assertIsInt($filmId);

        (new WishlistTargetRepository())->add($filmId, SupportPhysique::DVD, '3760061234567');
        (new WishlistTargetRepository())->add($filmId, SupportPhysique::BLURAY, '');

        $created = (new ShareLinkService())->create($userId, $foyerId, ShareLinkScope::WISHLIST, 'cibles');
        $this->assertIsArray($created);
        $link = (new ShareLinkService())->resolve((string) $created['token']);
        $this->assertNotNull($link);

        $films = (new ShareLinkFilmRepository())->findAllForLink($link);
        $this->assertCount(1, $films);

        $map = (new WishlistTargetRepository())->mapByBibliothequeIds([$filmId]);
        $this->assertCount(2, $map[$filmId] ?? []);
    }

    public function testInvalidTokenIsRejected(): void
    {
        $this->loginAsAdmin();
        $service = new ShareLinkService();
        $this->assertNull($service->resolve('token-invalide-trop-court'));
    }

    public function testGameShareLinkVisitorReadOnly(): void
    {
        if (!GameRepository::isAvailable()) {
            $this->markTestSkipped('Module jeux indisponible.');
        }

        $userId = $this->loginAsAdmin();
        $foyerId = UserContext::currentFoyerId();
        $db = \Moncine\Database::getInstance();

        $db->exec(
            "INSERT INTO oeuvres (titre, media_domain) VALUES ('Partage Jeu', 'jeu')"
        );
        $oeuvreId = (int) $db->lastInsertId();
        $db->prepare(
            'INSERT INTO oeuvre_jeu (oeuvre_id, studio, editeur, genre, platform, is_digital)
             VALUES (?, ?, ?, ?, ?, ?)'
        )->execute([$oeuvreId, 'Studio Test', '', 'Action', 'pc', 0]);
        $db->prepare(
            'INSERT INTO bibliotheque (oeuvre_id, foyer_id, statut)
             VALUES (?, ?, ?)'
        )->execute([$oeuvreId, $foyerId, LibraryStatut::COLLECTION]);
        $gameId = (int) $db->lastInsertId();

        $db->prepare(
            'INSERT INTO bibliotheque (oeuvre_id, user_id, statut)
             VALUES (?, ?, ?)'
        )->execute([$oeuvreId, $userId, LibraryStatut::WISHLIST]);
        $wishId = (int) $db->lastInsertId();

        $service = new ShareLinkService();
        $collection = $service->create(
            $userId,
            $foyerId,
            ShareLinkScope::COLLECTION,
            'jeux coll',
            null,
            \Moncine\MediaDomain::JEU
        );
        $this->assertIsArray($collection);
        $token = (string) $collection['token'];

        $wish = $service->create(
            $userId,
            $foyerId,
            ShareLinkScope::WISHLIST,
            'jeux envies',
            null,
            \Moncine\MediaDomain::JEU
        );
        $this->assertIsArray($wish);
        $wishToken = (string) $wish['token'];

        $gameRepo = new \Moncine\ShareLinkGameRepository();
        $colLink = $service->resolve($token);
        $this->assertNotNull($colLink);
        $this->assertSame(\Moncine\MediaDomain::JEU, ShareLinkRepository::mediaDomainFromRow($colLink));
        $colGames = $gameRepo->findAllForLink($colLink);
        $this->assertCount(1, $colGames);
        $this->assertSame($gameId, (int) ($colGames[0]['id'] ?? 0));

        $wishLink = $service->resolve($wishToken);
        $this->assertNotNull($wishLink);
        $wishGames = $gameRepo->findAllForLink($wishLink);
        $this->assertCount(1, $wishGames);
        $this->assertSame($wishId, (int) ($wishGames[0]['id'] ?? 0));

        $this->assertNull($gameRepo->findByIdForLink($colLink, $wishId));
        $this->assertNotNull($gameRepo->findByIdForLink($colLink, $gameId));
    }
}
