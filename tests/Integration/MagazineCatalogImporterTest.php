<?php

declare(strict_types=1);

namespace Moncine\Tests\Integration;

use Moncine\LibraryStatut;
use Moncine\MagazineCatalogImporter;
use Moncine\MagazineRepository;
use Moncine\MediaContext;
use Moncine\MediaDomain;
use Moncine\PublicationType;
use Moncine\SeriesRepository;
use Moncine\Tests\Support\MoncineTestCase;
use Moncine\UserContext;

final class MagazineCatalogImporterTest extends MoncineTestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        MediaContext::set(MediaDomain::MAGAZINE);
    }

    public function testImportCreatesCatalogWithoutLibrary(): void
    {
        if (!MagazineRepository::isAvailable()) {
            $this->markTestSkipped('Module magazines indisponible.');
        }

        $export = [
            'format_version' => 1,
            'series' => [
                [
                    'abm_magazine_id' => 99001,
                    'titre' => 'Revue Test Import ' . uniqid(),
                    'logo_url' => '',
                    'issues' => [
                        [
                            'numero' => '001',
                            'numero_ordre' => 1,
                            'hors_serie' => false,
                            'date_label' => 'janvier 1990',
                            'annee' => 1990,
                            'cover_url' => '',
                        ],
                    ],
                ],
            ],
        ];

        $result = (new MagazineCatalogImporter())->importFromExportArray($export);
        $this->assertSame([], $result['errors']);
        $this->assertSame(1, $result['series_created']);
        $this->assertSame(1, $result['issues_created']);

        $series = (new SeriesRepository())->findByTitre($export['series'][0]['titre'], MediaDomain::MAGAZINE);
        $this->assertNotNull($series);

        $issue = (new MagazineRepository())->findCatalogIssueBySeriesNumero((int) $series['id'], '001');
        $this->assertNotNull($issue);
        $this->assertSame('1990-01-01', (string) ($issue['date_parution'] ?? ''));

        $bibCount = (int) \Moncine\Database::getInstance()
            ->query('SELECT COUNT(*) FROM bibliotheque WHERE oeuvre_id = ' . (int) $issue['oeuvre_id'])
            ->fetchColumn();
        $this->assertSame(0, $bibCount);

        $stmt = \Moncine\Database::getInstance()->prepare(
            'SELECT COUNT(*) FROM series_bibliotheque WHERE series_id = ?'
        );
        $stmt->execute([(int) $series['id']]);
        $this->assertSame(0, (int) $stmt->fetchColumn());
    }

    public function testSkipExistingIssue(): void
    {
        if (!MagazineRepository::isAvailable()) {
            $this->markTestSkipped('Module magazines indisponible.');
        }

        $titre = 'Revue Skip Test ' . uniqid();
        $export = [
            'series' => [
                [
                    'abm_magazine_id' => 99002,
                    'titre' => $titre,
                    'issues' => [
                        ['numero' => '7', 'numero_ordre' => 7, 'date_label' => '1991'],
                    ],
                ],
            ],
        ];

        $importer = new MagazineCatalogImporter();
        $first = $importer->importFromExportArray($export);
        $this->assertSame(1, $first['issues_created']);

        $second = $importer->importFromExportArray($export);
        $this->assertSame(0, $second['issues_created']);
        $this->assertSame(1, $second['issues_skipped']);
        $this->assertSame(1, $second['series_reused']);
    }

    public function testSearchCatalogIssuesAndAddFromCatalog(): void
    {
        if (!MagazineRepository::isAvailable()) {
            $this->markTestSkipped('Module magazines indisponible.');
        }

        $this->loginAsAdmin();
        $userId = UserContext::currentUserId();
        $foyerId = UserContext::currentFoyerId();
        $repo = new MagazineRepository();

        $seriesId = (new SeriesRepository())->create([
            'titre' => 'Revue Search Test ' . uniqid(),
            'publication_type' => PublicationType::MENSUEL,
        ], MediaDomain::MAGAZINE);
        $this->assertIsInt($seriesId);

        $oeuvreId = $repo->createCatalogIssue($seriesId, [
            'numero' => '042',
            'numero_ordre' => 42,
            'date_parution' => '2020-04-01',
            'series_titre' => 'Revue Search Test',
        ]);
        $this->assertIsInt($oeuvreId);

        $found = $repo->searchCatalogIssues($seriesId, '042', $userId, $foyerId);
        $this->assertCount(1, $found);
        $this->assertSame($oeuvreId, (int) ($found[0]['oeuvre_id'] ?? 0));

        $bibId = $repo->addFromCatalogOeuvre($oeuvreId, LibraryStatut::COLLECTION, $userId, $foyerId);
        $this->assertIsInt($bibId);

        $foundAfter = $repo->searchCatalogIssues($seriesId, '042', $userId, $foyerId);
        $this->assertTrue(!empty($foundAfter[0]['in_library']));
    }

    public function testDryRunCountsPendingCovers(): void
    {
        if (!MagazineRepository::isAvailable()) {
            $this->markTestSkipped('Module magazines indisponible.');
        }

        $export = [
            'series' => [
                [
                    'titre' => 'Revue Covers DryRun ' . uniqid(),
                    'issues' => [
                        ['numero' => '1', 'cover_url' => 'https://example.org/1.jpg'],
                        ['numero' => '2', 'cover_url' => 'https://example.org/2.jpg'],
                        ['numero' => '3'],
                    ],
                ],
            ],
        ];

        $result = (new MagazineCatalogImporter())->importFromExportArray($export, [
            'dry_run' => true,
            'download_covers' => true,
        ]);

        $this->assertSame(3, $result['issues_created']);
        $this->assertSame(2, $result['issue_covers_remaining']);
        $this->assertSame(20, $result['cover_batch_size']);
    }
}
