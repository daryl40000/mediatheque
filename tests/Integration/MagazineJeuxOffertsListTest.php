<?php

declare(strict_types=1);

namespace Moncine\Tests\Integration;

use Moncine\GameLibraryFields;
use Moncine\GamePlatform;
use Moncine\GameRepository;
use Moncine\LibraryStatut;
use Moncine\MagazineGameLink;
use Moncine\MagazineJeuxOffertsList;
use Moncine\MagazineRepository;
use Moncine\MagazineSubject;
use Moncine\MagazineSubjectRepository;
use Moncine\MediaContext;
use Moncine\MediaDomain;
use Moncine\PublicationType;
use Moncine\SchemaMigrator;
use Moncine\SeriesRepository;
use Moncine\Tests\Support\MoncineTestCase;
use Moncine\UserContext;
use Moncine\View;

final class MagazineJeuxOffertsListTest extends MoncineTestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        (new SchemaMigrator(\Moncine\Database::getInstance()))->runPendingMigrations();
        $this->loginAsAdmin();
    }

    public function testMagazinesJeuxOffertsUrl(): void
    {
        $this->assertSame('/magazines-jeux-offerts.php', View::magazinesJeuxOffertsUrl());
    }

    public function testListGroupedBySeriesOrderedByParution(): void
    {
        if (!MagazineJeuxOffertsList::isAvailable() || !GameRepository::isAvailable()) {
            $this->markTestSkipped('Liste jeux offerts indisponible.');
        }

        $userId = UserContext::currentUserId();
        $foyerId = UserContext::currentFoyerId();

        MediaContext::set(MediaDomain::JEU);
        $gameRepo = new GameRepository();
        $bibGame = $gameRepo->createWithLibrary([
            'titre' => 'Jeu Coverdisc Liste',
            'platform' => GamePlatform::PC,
        ], LibraryStatut::COLLECTION, $userId, $foyerId);
        $this->assertIsInt($bibGame);
        $game = $gameRepo->findByBibId($bibGame, $userId, $foyerId);
        $this->assertNotNull($game);
        $gameOeuvreId = (int) $game['oeuvre_id'];

        // Renseigne « testé sous Linux » pour vérifier le badge sur la liste.
        GameLibraryFields::saveLinuxFlags(
            \Moncine\Database::getInstance(),
            $bibGame,
            GamePlatform::PC,
            true,
            false
        );
        MediaContext::set(MediaDomain::MAGAZINE);
        $seriesId = (new SeriesRepository())->create([
            'titre' => 'Revue Coverdisc Liste',
            'publication_type' => PublicationType::MENSUEL,
            'categories' => 'Jeux vidéo',
        ], MediaDomain::MAGAZINE);
        $this->assertIsInt($seriesId);
        $seriesRow = (new SeriesRepository())->findById($seriesId, MediaDomain::MAGAZINE);
        $this->assertNotNull($seriesRow);

        $magRepo = new MagazineRepository();
        $subjectRepo = new MagazineSubjectRepository();

        // Deux numéros : février puis janvier — l’ordre d’affichage doit suivre la parution.
        $issueLate = $magRepo->createIssueWithLibrary($seriesId, [
            'numero' => '2',
            'numero_ordre' => 2,
            'date_parution' => '1999-02-01',
        ], LibraryStatut::COLLECTION, $userId, $foyerId);
        $issueEarly = $magRepo->createIssueWithLibrary($seriesId, [
            'numero' => '1',
            'numero_ordre' => 1,
            'date_parution' => 'janvier 1999',
        ], LibraryStatut::COLLECTION, $userId, $foyerId);
        $this->assertIsInt($issueLate);
        $this->assertIsInt($issueEarly);

        foreach ([$issueEarly, $issueLate] as $issueBib) {
            $issue = $magRepo->findIssueByBibId((int) $issueBib, $userId, $foyerId);
            $this->assertNotNull($issue);
            $prepared = $subjectRepo->prepareSubjectForIssue(
                MagazineSubject::JEUX_OFFERTS,
                'Jeu Coverdisc Liste',
                'PC',
                $seriesRow,
                $issue,
                1999
            );
            $this->assertIsArray($prepared);
            $subject = $subjectRepo->findOrCreate(
                (string) $prepared['category'],
                (string) $prepared['label'],
                (string) $prepared['detail'],
                (int) $prepared['parution_year']
            );
            $this->assertNotNull($subject);
            $subjectId = (int) ($subject['id'] ?? 0);
            $this->assertTrue($subjectRepo->attachToOeuvre((int) $issue['oeuvre_id'], $subjectId) === true);
            $this->assertSame(true, (new MagazineGameLink())->setSubjectCatalogLink($subjectId, $gameOeuvreId));
        }

        $groups = (new MagazineJeuxOffertsList())->listGroupedBySeries($userId, $foyerId);
        $matched = null;
        foreach ($groups as $group) {
            if ((int) ($group['series_id'] ?? 0) === $seriesId) {
                $matched = $group;
                break;
            }
        }
        $this->assertNotNull($matched);
        $issues = $matched['issues'] ?? [];
        $this->assertCount(2, $issues);
        $this->assertSame('1', (string) ($issues[0]['numero'] ?? ''));
        $this->assertSame('2', (string) ($issues[1]['numero'] ?? ''));
        $this->assertStringContainsString('Jeu Coverdisc Liste', (string) ($issues[0]['game_titre'] ?? ''));
        $this->assertSame('supported', (string) ($issues[0]['linux_badge'] ?? ''));
        // Titre → fiche jeu (avec bascule d’onglet si on est en Magazines) ; numéro → fiche magazine.
        $gameUrl = (string) ($issues[0]['game_url'] ?? '');
        $this->assertTrue(
            str_contains($gameUrl, '/jeu.php?id=') || str_contains($gameUrl, '%2Fjeu.php%3Fid%3D'),
            'Le lien jeu doit mener à la fiche jeu, reçu : ' . $gameUrl
        );
        $this->assertStringNotContainsString('jeu-magazines.php', $gameUrl);
        $this->assertStringContainsString('/magazine-numero.php?id=', (string) ($issues[0]['issue_url'] ?? ''));
    }
}
