<?php

declare(strict_types=1);

namespace Moncine\Tests\Unit;

use Moncine\Exception\ValidationException;
use Moncine\GameFranchiseRepository;
use Moncine\Service\GameBulkActionService;
use PHPUnit\Framework\TestCase;

/**
 * Tests unitaires sans mock d’assignation réelle :
 * GameFranchiseRepository est final — on teste surtout la validation.
 */
final class GameBulkActionServiceTest extends TestCase
{
    public function testEmptySelectionThrows(): void
    {
        $service = new GameBulkActionService();

        $this->expectException(ValidationException::class);
        $this->expectExceptionMessage('Sélectionnez au moins un jeu.');

        $service->handleBulkAction('assign_franchise', [], [], 1);
    }

    public function testUnknownActionThrows(): void
    {
        $service = new GameBulkActionService();

        $this->expectException(ValidationException::class);
        $this->expectExceptionMessage('Action inconnue.');

        $service->handleBulkAction('invalid_action', [1], [], 1);
    }

    public function testAssignFranchiseRequiresName(): void
    {
        $service = new GameBulkActionService(
            new GameFranchiseRepository(),
            static fn (): bool => true,
        );

        $this->expectException(ValidationException::class);
        $this->expectExceptionMessage('Choisissez une saga existante ou saisissez un nouveau nom.');

        $service->handleBulkAction('assign_franchise', [1, 2], [], 1);
    }

    public function testAssignFranchiseRequiresModule(): void
    {
        $service = new GameBulkActionService(
            new GameFranchiseRepository(),
            static fn (): bool => false,
        );

        $this->expectException(ValidationException::class);
        $this->expectExceptionMessage('Module sagas indisponible.');

        $service->handleBulkAction(
            'assign_franchise',
            [1],
            ['franchise_new' => 'Zelda'],
            1
        );
    }
}
