<?php

declare(strict_types=1);

namespace Moncine\Tests\Unit;

use Moncine\Exception\ValidationException;
use Moncine\FilmEnricher;
use Moncine\FilmRepository;
use Moncine\Service\FilmBulkActionService;
use PHPUnit\Framework\TestCase;

/**
 * Tests unitaires sans mock : les classes FilmRepository et FilmEnricher sont final.
 */
final class FilmBulkActionServiceTest extends TestCase
{
    public function testEmptySelectionThrows(): void
    {
        $service = new FilmBulkActionService();

        $this->expectException(ValidationException::class);
        $this->expectExceptionMessage('Sélectionnez au moins un film.');

        $service->handleBulkAction('assign_saga', [], []);
    }

    public function testAssignSagaRequiresSagaName(): void
    {
        $service = new FilmBulkActionService();

        $this->expectException(ValidationException::class);
        $this->expectExceptionMessage('Choisissez une saga existante ou saisissez un nouveau nom.');

        $service->handleBulkAction('assign_saga', [1, 2], []);
    }

    public function testUnknownActionThrows(): void
    {
        $service = new FilmBulkActionService();

        $this->expectException(ValidationException::class);
        $this->expectExceptionMessage('Action inconnue.');

        $service->handleBulkAction('invalid_action', [1], []);
    }

    public function testSetSupportRejectsInvalidSupport(): void
    {
        $service = new FilmBulkActionService();

        $this->expectException(ValidationException::class);
        $this->expectExceptionMessage('Support invalide');

        $service->handleBulkAction('set_support', [5], ['bulk_support_physique' => 'vhs']);
    }

    public function testEnrichTmdbRequiresCatalogAdmin(): void
    {
        $service = new FilmBulkActionService(
            new FilmRepository(),
            new FilmEnricher(),
            static fn (): bool => false,
        );

        $this->expectException(ValidationException::class);
        $this->expectExceptionMessage('administrateur du catalogue');

        $service->handleBulkAction('enrich_tmdb', [1], []);
    }

    public function testBulkTmdbSummaryMessageFormatsPartialResult(): void
    {
        $message = FilmEnricher::bulkTmdbSummaryMessage([
            'selected' => 2,
            'updated' => 1,
            'skipped_no_tmdb' => 0,
            'failed' => 1,
            'errors' => ['Film X : erreur API'],
        ]);

        $this->assertStringContainsString('mise à jour', $message);
        $this->assertStringContainsString('échec', $message);
    }
}
