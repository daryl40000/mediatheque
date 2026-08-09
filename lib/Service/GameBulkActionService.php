<?php
/**
 * Actions de masse sur la collection jeux (saga / franchise).
 *
 * Même idée que FilmBulkActionService : la page www/jeux.php ne fait plus
 * que CSRF → appeler ce service → rediriger.
 */

declare(strict_types=1);

namespace Moncine\Service;

use Moncine\Exception\ValidationException;
use Moncine\GameFranchiseRepository;

final class GameBulkActionService
{
    /** @var callable(): bool */
    private $franchiseModuleChecker;

    public function __construct(
        private readonly GameFranchiseRepository $franchiseRepo = new GameFranchiseRepository(),
        ?callable $franchiseModuleChecker = null,
    ) {
        $this->franchiseModuleChecker = $franchiseModuleChecker
            ?? static fn (): bool => GameFranchiseRepository::isAvailable();
    }

    /**
     * @param list<int> $gameIds identifiants bibliothèque (bib_id)
     * @param array<string, mixed> $postData
     *
     * @return array<string, int|string> paramètres de redirection (?bulk_ok=…)
     *
     * @throws ValidationException
     */
    public function handleBulkAction(
        string $action,
        array $gameIds,
        array $postData,
        int $foyerId
    ): array {
        if ($gameIds === []) {
            throw new ValidationException('Sélectionnez au moins un jeu.');
        }

        return match ($action) {
            'assign_franchise' => $this->handleAssignFranchise($gameIds, $postData, $foyerId),
            default => throw new ValidationException('Action inconnue.'),
        };
    }

    /**
     * @param list<int> $gameIds
     * @param array<string, mixed> $postData
     *
     * @return array<string, int|string>
     */
    private function handleAssignFranchise(array $gameIds, array $postData, int $foyerId): array
    {
        if (!($this->franchiseModuleChecker)()) {
            throw new ValidationException('Module sagas indisponible.');
        }

        $franchiseNew = trim((string) ($postData['franchise_new'] ?? ''));
        $franchiseExisting = trim((string) ($postData['franchise_existing'] ?? ''));
        $franchiseName = $franchiseNew !== '' ? $franchiseNew : $franchiseExisting;

        if ($franchiseName === '') {
            throw new ValidationException(
                'Choisissez une saga existante ou saisissez un nouveau nom.'
            );
        }

        $updated = $this->franchiseRepo->assignGamesToFranchise($gameIds, $franchiseName, $foyerId);

        return [
            'bulk_ok' => $updated,
            'bulk_msg' => $updated . ' jeu' . ($updated > 1 ? 'x' : '') . ' ajouté' . ($updated > 1 ? 's' : '')
                . ' à la saga « ' . $franchiseName . ' ».',
            'franchise_name' => $franchiseName,
        ];
    }
}
