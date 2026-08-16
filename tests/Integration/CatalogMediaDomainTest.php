<?php

declare(strict_types=1);

namespace Moncine\Tests\Integration;

use Moncine\CatalogAdmin;
use Moncine\CatalogExportSchema;
use Moncine\GameRepository;
use Moncine\ImportRunner;
use Moncine\MediaContext;
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
            'Jeu — IGDB ID' => '1942',
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
        if (GameRepository::hasIgdbColumns()) {
            $this->assertSame(1942, (int) ($game['igdb_id'] ?? 0));
        }
    }

    public function testCatalogExportIncludesMediaDomainColumn(): void
    {
        $this->assertContains('Domaine média', CatalogExportSchema::headers());
        $this->assertContains('Jeu — IGDB ID', CatalogExportSchema::headers());
    }

    public function testCatalogExportIncludesGameIgdbId(): void
    {
        if (!GameRepository::isAvailable() || !GameRepository::hasIgdbColumns()) {
            $this->markTestSkipped('Module jeux / colonnes IGDB non disponibles.');
        }

        $oeuvreId = (new OeuvreRepository())->insert([
            'titre' => 'Jeu Export IGDB',
            'realisateur' => '',
            'media_domain' => MediaDomain::JEU,
        ]);
        $db = \Moncine\Database::getInstance();
        $db->prepare(
            'INSERT INTO oeuvre_jeu (oeuvre_id, studio, editeur, genre, platform, is_digital, igdb_id)
             VALUES (?, ?, ?, ?, ?, ?, ?)'
        )->execute([$oeuvreId, 'Studio', '', 'Action', 'pc', 0, 119171]);

        $exported = null;
        foreach ((new OeuvreRepository())->findAllForExport() as $oeuvre) {
            if ((int) ($oeuvre['id'] ?? 0) === $oeuvreId) {
                $exported = $oeuvre;
                break;
            }
        }
        $this->assertNotNull($exported);
        $this->assertSame(119171, (int) ($exported['jeu_igdb_id'] ?? 0));

        $row = CatalogExportSchema::rowToExport($exported);
        $headers = CatalogExportSchema::headers();
        $igdbIndex = array_search('Jeu — IGDB ID', $headers, true);
        $this->assertNotFalse($igdbIndex);
        $this->assertSame('119171', $row[$igdbIndex]);
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

    public function testCatalogAdminDeletesGameWhileFilmTabActive(): void
    {
        if (!GameRepository::isAvailable()) {
            $this->markTestSkipped('Table oeuvre_jeu absente.');
        }

        $gameId = (new OeuvreRepository())->insert([
            'titre' => 'Jeu à supprimer',
            'realisateur' => '',
            'media_domain' => MediaDomain::JEU,
        ]);
        \Moncine\Database::getInstance()->prepare(
            'INSERT INTO oeuvre_jeu (oeuvre_id, studio, editeur, genre, platform, is_digital)
             VALUES (?, ?, ?, ?, ?, ?)'
        )->execute([$gameId, 'Studio', '', 'Action', 'pc', 0]);

        MediaContext::set(MediaDomain::FILM);

        $result = (new CatalogAdmin())->deleteOeuvre($gameId);
        $this->assertTrue($result === true);
        $this->assertNull((new OeuvreRepository())->findByIdForAdmin($gameId));
    }

    public function testCatalogAdminSortsById(): void
    {
        $oeuvres = new OeuvreRepository();
        $firstId = $oeuvres->insert([
            'titre' => 'Zzz Sort Id A',
            'realisateur' => '',
            'media_domain' => MediaDomain::FILM,
        ]);
        $secondId = $oeuvres->insert([
            'titre' => 'Aaa Sort Id B',
            'realisateur' => '',
            'media_domain' => MediaDomain::FILM,
        ]);
        $this->assertGreaterThan($firstId, $secondId);

        $admin = new CatalogAdmin();
        $asc = $admin->listOeuvres('Sort Id', 'id', 'asc', 1);
        $desc = $admin->listOeuvres('Sort Id', 'id', 'desc', 1);

        $this->assertSame([$firstId, $secondId], array_map('intval', array_column($asc, 'id')));
        $this->assertSame([$secondId, $firstId], array_map('intval', array_column($desc, 'id')));
        $this->assertStringContainsString('sort=id', $admin->sortUrl('id', 'titre', 'asc', '', 1));
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
