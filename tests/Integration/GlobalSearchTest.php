<?php

declare(strict_types=1);

namespace Moncine\Tests\Integration;

use Moncine\GamePlatform;
use Moncine\GameRepository;
use Moncine\GlobalSearch;
use Moncine\LibraryStatut;
use Moncine\MediaContext;
use Moncine\MediaDomain;
use Moncine\SchemaMigrator;
use Moncine\Tests\Support\MoncineTestCase;
use Moncine\UserContext;

final class GlobalSearchTest extends MoncineTestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        (new SchemaMigrator(\Moncine\Database::getInstance()))->runPendingMigrations();
        MediaContext::set(MediaDomain::JEU);
        $this->loginAsAdmin();
    }

    public function testSearchRequiresAtLeastTwoCharacters(): void
    {
        $results = (new GlobalSearch())->search('a', 1, 1, 10);
        $this->assertSame([], $results['library']);
        $this->assertSame([], $results['catalog']);
    }

    public function testSearchFindsLibraryGame(): void
    {
        if (!GameRepository::isAvailable()) {
            $this->markTestSkipped('Module jeux indisponible.');
        }

        $userId = UserContext::currentUserId();
        $foyerId = UserContext::currentFoyerId();
        $repo = new GameRepository();
        $search = new GlobalSearch();

        $unique = 'GlobalSearch' . substr(sha1((string) microtime(true)), 0, 8);
        $titre = $unique . ' In Library';

        $bibId = $repo->createWithLibrary([
            'titre' => $titre,
            'annee' => 2024,
            'platform' => GamePlatform::PC,
        ], LibraryStatut::COLLECTION, $userId, $foyerId);
        $this->assertIsInt($bibId);

        $results = $search->search($unique, $userId, $foyerId, 10);

        $this->assertNotEmpty($results['library']);
        $this->assertSame($titre, $results['library'][0]['titre']);
        $this->assertSame('library', $results['library'][0]['source']);
        $this->assertSame(MediaDomain::JEU, $results['library'][0]['media_domain']);
        $this->assertNotSame('', $results['library'][0]['url']);

        $catalogOnly = $repo->searchCatalog($unique, 10);
        $this->assertNotEmpty($catalogOnly);
    }
}
