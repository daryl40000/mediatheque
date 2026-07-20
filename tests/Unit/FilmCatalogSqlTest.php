<?php

declare(strict_types=1);

namespace Moncine\Tests\Unit;

use Moncine\FilmCatalogSql;
use Moncine\FilmPosterService;
use Moncine\Tests\Support\MoncineTestCase;

/**
 * Verrouille les extractions Phase B films (SQL + affiches) sans dépendre de TMDB.
 */
final class FilmCatalogSqlTest extends MoncineTestCase
{
    public function testSortableColumnsIncludeCoreKeys(): void
    {
        $columns = FilmCatalogSql::sortableColumns();
        $this->assertContains('titre', $columns);
        $this->assertContains('annee', $columns);
        $this->assertContains('note', $columns);
        $this->assertTrue(FilmCatalogSql::isValidSortColumn('titre'));
        $this->assertFalse(FilmCatalogSql::isValidSortColumn('inconnu'));
    }

    public function testSortOrderExpressionFallsBackToTitre(): void
    {
        $this->assertStringContainsString('o.titre', FilmCatalogSql::sortOrderExpression('titre'));
        $this->assertStringContainsString('o.titre', FilmCatalogSql::sortOrderExpression('pas_une_colonne'));
        $this->assertSame('o.annee', FilmCatalogSql::sortOrderExpression('annee'));
    }

    public function testCollectionSearchWhereSqlEmptyQuery(): void
    {
        $params = [];
        $this->assertSame('', FilmCatalogSql::collectionSearchWhereSql('', $params));
        $this->assertSame([], $params);
    }

    public function testCollectionSearchWhereSqlAddsFoldedPattern(): void
    {
        $params = [];
        $sql = FilmCatalogSql::collectionSearchWhereSql('Matrix', $params);
        $this->assertNotSame('', $sql);
        $this->assertArrayHasKey('collection_q', $params);
        $this->assertStringContainsString('fold_search', $sql);
        $this->assertStringContainsString(':collection_q', $sql);
    }

    public function testCollectionRatingSelectSqlMentionsHistoryUser(): void
    {
        $sql = FilmCatalogSql::collectionRatingSelectSql();
        $this->assertStringContainsString('derniere_vue', $sql);
        $this->assertStringContainsString('note_max', $sql);
        $this->assertStringContainsString(':history_user_id', $sql);

        $params = [];
        FilmCatalogSql::appendCollectionRatingParams($params, 42);
        $this->assertSame(42, $params['history_user_id']);
    }

    public function testEnrichmentPendingSqlMentionsPosterAndSynopsis(): void
    {
        $sql = FilmCatalogSql::enrichmentPendingSql('o');
        $this->assertStringContainsString('omdb_enriched_at', $sql);
        $this->assertStringContainsString('poster_url', $sql);
        $this->assertStringContainsString('synopsis', $sql);
    }

    public function testPosterServiceEmptyUrlReturnsEmpty(): void
    {
        $service = new FilmPosterService();
        $this->assertSame('', $service->resolvePosterForOeuvre(0, ''));
        $this->assertSame('', $service->resolvePosterForOeuvre(1, '   '));
    }
}
