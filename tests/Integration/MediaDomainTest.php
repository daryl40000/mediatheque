<?php

declare(strict_types=1);

namespace Moncine\Tests\Integration;

use Moncine\FilmRepository;
use Moncine\MediaContext;
use Moncine\MediaDomain;
use Moncine\OeuvreRepository;
use Moncine\SchemaMigrator;
use Moncine\Tests\Support\MoncineTestCase;
use Moncine\UserContext;

final class MediaDomainTest extends MoncineTestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        (new SchemaMigrator(\Moncine\Database::getInstance()))->runPendingMigrations();
        $this->loginAsAdmin();
    }

    public function testNewOeuvreGetsFilmDomain(): void
    {
        $oeuvreId = (new OeuvreRepository())->insert([
            'titre' => 'Film Domaine Test',
            'realisateur' => 'Test Réalisateur',
        ]);
        $row = (new OeuvreRepository())->findById($oeuvreId);
        $this->assertNotNull($row);
        $this->assertSame(MediaDomain::FILM, $row['media_domain'] ?? '');
    }

    public function testCollectionListFiltersByActiveDomain(): void
    {
        $oeuvres = new OeuvreRepository();
        $films = new FilmRepository();

        $filmOeuvreId = $oeuvres->insert([
            'titre' => 'Visible En Film',
            'realisateur' => 'Dom A',
        ]);
        $bdOeuvreId = $oeuvres->insert([
            'titre' => 'Caché En BD',
            'realisateur' => 'Dom B',
            'media_domain' => MediaDomain::BD,
        ]);

        $userId = UserContext::currentUserId();
        $foyerId = UserContext::currentFoyerId();
        $bibliotheque = new \Moncine\BibliothequeRepository();
        $bibliotheque->insert($userId, $foyerId, $filmOeuvreId, ['statut' => 'collection']);
        $bibliotheque->insert($userId, $foyerId, $bdOeuvreId, ['statut' => 'collection']);

        MediaContext::set(MediaDomain::FILM);
        $titles = array_column($films->findAll(), 'titre');
        $this->assertContains('Visible En Film', $titles);
        $this->assertNotContains('Caché En BD', $titles);

        MediaContext::set(MediaDomain::BD);
        $this->assertSame([], $films->findAll());
    }
}
