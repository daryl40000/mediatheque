<?php

declare(strict_types=1);

namespace Moncine\Tests\Integration;

use Moncine\BibliothequeRepository;
use Moncine\CatalogMaintenance;
use Moncine\FoyerRepository;
use Moncine\HistoriqueRepository;
use Moncine\OeuvreRepository;
use Moncine\PosterStorage;
use Moncine\Tests\Support\MoncineTestCase;

final class CatalogMaintenanceTest extends MoncineTestCase
{
    public function testMergeOeuvresReassignsBibliothequeAndDeletesDuplicate(): void
    {
        $adminId = $this->loginAsAdmin();
        $keepId = $this->seedCatalogOeuvre('Film Alpha', 'Réalisateur A');
        $removeId = $this->seedCatalogOeuvre('Film Alpha ', 'Réalisateur A');

        $foyerId = (new FoyerRepository())->currentFoyerIdForUser($adminId);
        $bib = new BibliothequeRepository();
        $removeEntryId = $bib->insert($adminId, $foyerId, $removeId, [
            'statut' => 'collection',
            'support_physique' => 'bluray',
            'format_image' => '1080p',
        ]);

        (new HistoriqueRepository())->recordViewing($removeEntryId, '2024-01-15', 8);

        $result = (new CatalogMaintenance())->mergeOeuvres($keepId, $removeId, $adminId);
        $this->assertTrue($result === true);

        $this->assertNull((new OeuvreRepository())->findById($removeId));
        $entry = $bib->findByOeuvreId($keepId, $adminId, $foyerId);
        $this->assertNotNull($entry);
        $this->assertSame('bluray', $entry['support_physique']);
        $this->assertSame('1080p', $entry['format_image']);

        $history = (new HistoriqueRepository())->findViewingsByFilm((int) $entry['id']);
        $this->assertCount(1, $history);
        $this->assertSame(8, (int) ($history[0]['note'] ?? 0));
    }

    public function testFindOrphanPosterFilesIgnoresReferencedPosters(): void
    {
        $this->loginAsAdmin();
        $oeuvreId = $this->seedCatalogOeuvre('Poster Test', 'Réalisateur P');

        $dir = PosterStorage::postersFilesystemDir();
        if (!is_dir($dir)) {
            mkdir($dir, 0755, true);
        }

        $referenced = $dir . '/' . $oeuvreId . '.jpg';
        $orphan = $dir . '/99999.jpg';
        file_put_contents($referenced, "\xFF\xD8\xFF\xE0" . str_repeat('0', 100));
        file_put_contents($orphan, "\xFF\xD8\xFF\xE0" . str_repeat('1', 100));

        (new OeuvreRepository())->update($oeuvreId, ['poster_url' => '/posters/' . $oeuvreId . '.jpg'], ['poster_url']);

        $orphans = (new CatalogMaintenance())->findOrphanPosterFiles();
        $basenames = array_map('basename', $orphans);

        $this->assertContains('99999.jpg', $basenames);
        $this->assertNotContains($oeuvreId . '.jpg', $basenames);

        @unlink($referenced);
        @unlink($orphan);
    }

    public function testFindDuplicateGroupsByTmdb(): void
    {
        $this->loginAsAdmin();
        $this->seedCatalogOeuvre('Film TMDB 1', 'Real A', ['tmdb_id' => 550]);
        $this->seedCatalogOeuvre('Film TMDB 2', 'Real B', ['tmdb_id' => 550]);

        $groups = (new CatalogMaintenance())->findDuplicateGroupsByTmdb();
        $this->assertNotEmpty($groups);
        $this->assertSame(550, $groups[0]['tmdb_id']);
        $this->assertGreaterThanOrEqual(2, $groups[0]['count']);
    }
}
