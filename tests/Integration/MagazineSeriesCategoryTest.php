<?php

declare(strict_types=1);

namespace Moncine\Tests\Integration;

use Moncine\MagazineSeriesCategory;
use Moncine\MediaContext;
use Moncine\MediaDomain;
use Moncine\PublicationType;
use Moncine\SchemaMigrator;
use Moncine\SeriesRepository;
use Moncine\Tests\Support\MoncineTestCase;

final class MagazineSeriesCategoryTest extends MoncineTestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        (new SchemaMigrator(\Moncine\Database::getInstance()))->runPendingMigrations();
        MediaContext::set(MediaDomain::MAGAZINE);
        $this->loginAsAdmin();
    }

    public function testSeriesCategoriesPersistAndApplyToIssues(): void
    {
        $this->assertTrue(SeriesRepository::categoriesColumnExists());

        $seriesRepo = new SeriesRepository();
        $seriesId = $seriesRepo->create([
            'titre' => 'Revue catégories test',
            'publication_type' => PublicationType::MENSUEL,
            'categories' => 'jeux video, cinema',
        ], MediaDomain::MAGAZINE);
        $this->assertIsInt($seriesId);

        $series = $seriesRepo->findById($seriesId, MediaDomain::MAGAZINE);
        $this->assertNotNull($series);
        $this->assertSame(
            'Jeux vidéo, Cinéma',
            (string) ($series['categories'] ?? '')
        );
        $this->assertSame(
            ['Jeux vidéo', 'Cinéma'],
            MagazineSeriesCategory::listForSeries($series)
        );

        $result = $seriesRepo->update($seriesId, [
            'categories' => MagazineSeriesCategory::normalizeFromPost([
                MagazineSeriesCategory::FIGURINES,
                MagazineSeriesCategory::DIVERS,
            ]),
        ]);
        $this->assertTrue($result === true);

        $updated = $seriesRepo->findById($seriesId, MediaDomain::MAGAZINE);
        $this->assertNotNull($updated);
        $this->assertSame(
            ['Figurines', 'Divers'],
            MagazineSeriesCategory::listForSeries($updated)
        );

        $map = $seriesRepo->categoriesBySeriesIds([$seriesId]);
        $this->assertSame('Figurines, Divers', $map[$seriesId] ?? '');
    }
}
