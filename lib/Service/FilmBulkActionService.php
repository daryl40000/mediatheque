<?php
/**
 * Actions de masse sur la collection films (saga, support, TMDB, suppression).
 */

declare(strict_types=1);

namespace Moncine\Service;

use Moncine\CatalogAdmin;
use Moncine\Exception\ValidationException;
use Moncine\FilmEnricher;
use Moncine\FilmRepository;
use Moncine\SupportPhysique;

final class FilmBulkActionService
{
    /** @var callable(): bool */
    private $catalogAdminChecker;

    public function __construct(
        private readonly FilmRepository $repo = new FilmRepository(),
        private readonly FilmEnricher $enricher = new FilmEnricher(),
        ?callable $catalogAdminChecker = null,
    ) {
        $this->catalogAdminChecker = $catalogAdminChecker
            ?? static fn (): bool => CatalogAdmin::canAccess();
    }

    /**
     * @param list<int> $filmIds
     * @param array<string, mixed> $postData
     *
     * @return array<string, int|string> paramètres de redirection (?bulk_ok=…)
     *
     * @throws ValidationException
     */
    public function handleBulkAction(string $action, array $filmIds, array $postData): array
    {
        if ($filmIds === []) {
            throw new ValidationException('Sélectionnez au moins un film.');
        }

        return match ($action) {
            'assign_saga' => $this->handleAssignSaga($filmIds, $postData),
            'set_support' => $this->handleSetSupport($filmIds, $postData),
            'enrich_tmdb' => $this->handleEnrichTmdb($filmIds),
            'delete_films' => $this->handleDeleteFilms($filmIds),
            default => throw new ValidationException('Action inconnue.'),
        };
    }

    /**
     * @param list<int> $filmIds
     * @param array<string, mixed> $postData
     *
     * @return array<string, int|string>
     */
    private function handleAssignSaga(array $filmIds, array $postData): array
    {
        $sagaNew = trim((string) ($postData['saga_new'] ?? ''));
        $sagaExisting = trim((string) ($postData['saga_existing'] ?? ''));
        $sagaName = $sagaNew !== '' ? $sagaNew : $sagaExisting;
        $startOrder = max(1, (int) ($postData['saga_ordre_start'] ?? 1));

        if ($sagaName === '') {
            throw new ValidationException('Choisissez une saga existante ou saisissez un nouveau nom.');
        }

        $updated = $this->repo->assignFilmsToSaga($filmIds, $sagaName, $startOrder);

        return [
            'bulk_ok' => $updated,
            'bulk_msg' => $updated . ' film' . ($updated > 1 ? 's' : '') . ' ajouté' . ($updated > 1 ? 's' : '')
                . ' à la saga « ' . $sagaName . ' ».',
            'saga_name' => $sagaName,
        ];
    }

    /**
     * @param list<int> $filmIds
     * @param array<string, mixed> $postData
     *
     * @return array<string, int|string>
     */
    private function handleSetSupport(array $filmIds, array $postData): array
    {
        $supportRaw = (string) ($postData['bulk_support_physique'] ?? '');
        $supportKey = SupportPhysique::normalize($supportRaw);
        if ($supportRaw !== '' && $supportKey === '') {
            throw new ValidationException('Support invalide. Choisissez DVD, Blu-ray ou Blu-ray 4K.');
        }

        $updated = $this->repo->updateFilmsSupportPhysique($filmIds, $supportKey);
        $label = $supportKey !== ''
            ? SupportPhysique::label($supportKey)
            : 'Non renseigné';

        return [
            'bulk_ok' => $updated,
            'bulk_msg' => $updated . ' film' . ($updated > 1 ? 's' : '') . ' : support « ' . $label . ' ».',
        ];
    }

    /**
     * @param list<int> $filmIds
     *
     * @return array<string, int|string>
     */
    private function handleEnrichTmdb(array $filmIds): array
    {
        if (!($this->catalogAdminChecker)()) {
            throw new ValidationException(
                'L’enrichissement TMDB est réservé à l’administrateur du catalogue.'
            );
        }

        $result = $this->enricher->enrichSelectedByTmdbId($filmIds);

        if ($result['errors'] !== [] && $result['updated'] === 0 && $result['skipped_no_tmdb'] === 0) {
            throw new ValidationException((string) $result['errors'][0]);
        }

        $params = [
            'bulk_ok' => $result['updated'],
            'bulk_msg' => FilmEnricher::bulkTmdbSummaryMessage($result),
        ];
        if ($result['errors'] !== [] && $result['failed'] > 0) {
            $params['bulk_detail'] = implode(' | ', array_slice($result['errors'], 0, 5));
        }

        return $params;
    }

    /**
     * @param list<int> $filmIds
     *
     * @return array<string, int|string>
     */
    private function handleDeleteFilms(array $filmIds): array
    {
        $deleted = $this->repo->deleteFilms($filmIds);

        return [
            'bulk_ok' => $deleted,
            'bulk_msg' => $deleted . ' film' . ($deleted > 1 ? 's' : '') . ' supprimé' . ($deleted > 1 ? 's' : '')
                . ' de vos films.',
        ];
    }
}
