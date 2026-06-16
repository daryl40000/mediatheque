<?php

declare(strict_types=1);

namespace Moncine\Tests\Integration;

use Moncine\CatalogAdmin;
use Moncine\CatalogExportSchema;
use Moncine\GameRepository;
use Moncine\ImportRunner;
use Moncine\MediaDomain;
use Moncine\OeuvreRepository;
use Moncine\SchemaMigrator;
use Moncine\Tests\Support\MoncineTestCase;
use Moncine\View;

final class CatalogMediaDomainTest extends MoncineTestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        (new SchemaMigrator(\Moncine\Database::getInstance()))->runPendingMigrations();
        $this->loginAsAdmin();
    }

    public function testContentKindLabelUsesMediaDomain(): void
    {
        $label = View::contentKindLabel([
            'media_domain' => MediaDomain::JEU,
            'moncine_kind' => 'film',
            'tmdb_media_type' => 'movie',
        ]);

        $this->assertSame('Jeux', $label);
    }

    public function testCatalogImportPreservesGameDomainAndExtension(): void
    {
        if (!GameRepository::isAvailable()) {
            $this->markTestSkipped('Table oeuvre_jeu absente.');
        }

        $header = CatalogExportSchema::headers();
        $row = $this->catalogRowFromHeader($header, [
            'ID catalogue' => '9100',
            'Titre' => 'Jeu Import Test',
            'Réalisateur' => '',
            'Domaine média' => 'jeu',
            'Jeu — studio' => 'Studio X',
            'Jeu — plateforme' => 'pc',
            'Jeu — genre' => 'RPG',
        ]);

        $result = (new ImportRunner())->importCatalogSheet([$row], $header);
        $this->assertSame([], $result['errors'], implode('; ', $result['errors']));
        $this->assertSame(1, $result['imported']);

        $oeuvre = (new OeuvreRepository())->findByIdForAdmin(9100);
        $this->assertNotNull($oeuvre);
        $this->assertSame(MediaDomain::JEU, $oeuvre['media_domain'] ?? '');

        $game = (new GameRepository())->findCatalogByOeuvreId(9100);
        $this->assertNotNull($game);
        $this->assertSame('Studio X', $game['studio'] ?? '');
        $this->assertSame('pc', $game['platform'] ?? '');
    }

    public function testCatalogExportIncludesMediaDomainColumn(): void
    {
        $this->assertContains('Domaine média', CatalogExportSchema::headers());
    }

    public function testCatalogAdminListsAllMediaDomains(): void
    {
        $oeuvres = new OeuvreRepository();
        $filmId = $oeuvres->insert([
            'titre' => 'Film Catalogue Admin',
            'realisateur' => 'A',
            'media_domain' => MediaDomain::FILM,
        ]);
        $gameId = $oeuvres->insert([
            'titre' => 'Jeu Catalogue Admin',
            'realisateur' => '',
            'media_domain' => MediaDomain::JEU,
        ]);
        if (GameRepository::isAvailable()) {
            \Moncine\Database::getInstance()->prepare(
                'INSERT INTO oeuvre_jeu (oeuvre_id, studio, editeur, genre, platform, is_digital)
                 VALUES (?, ?, ?, ?, ?, ?)'
            )->execute([$gameId, 'S', '', 'Action', 'pc', 0]);
        }

        $admin = new CatalogAdmin();
        $listed = $admin->listOeuvres('', 'titre', 'asc', 1);
        $titles = array_column($listed, 'titre');

        $this->assertContains('Film Catalogue Admin', $titles);
        $this->assertContains('Jeu Catalogue Admin', $titles);
    }

    /**
     * @param array<string, string> $values
     * @return list<string>
     */
    private function catalogRowFromHeader(array $header, array $values): array
    {
        $row = [];
        foreach ($header as $label) {
            $row[] = $values[$label] ?? '';
        }

        return $row;
    }
}
