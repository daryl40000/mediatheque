<?php
/**
 * Mutations bibliothèque BD (retrait série, etc.).
 */

declare(strict_types=1);

namespace Moncine;

use PDO;

final class BdLibraryMutations
{
    public function __construct(
        private readonly PDO $db,
        private readonly BdLibraryAttach $libraryAttach
    ) {
    }

    /**
     * Retire une série BD de la collection ou des envies (pas le catalogue).
     *
     * @return array{removed_tomes: int}|string
     */
    public function removeSeriesFromLibrary(int $seriesId, string $statut, int $userId, int $foyerId): array|string
    {
        if (!BdRepository::isAvailable() || $seriesId <= 0) {
            return 'Module BD non disponible.';
        }

        $series = (new SeriesRepository())->findById($seriesId, MediaDomain::BD);
        if ($series === null) {
            return 'Série introuvable.';
        }

        $statut = LibraryStatut::normalize($statut);
        if (!$this->libraryAttach->isSeriesInLibrary($seriesId, $statut, $userId, $foyerId)) {
            return $statut === LibraryStatut::WISHLIST
                ? 'Cette série n’est pas dans vos envies.'
                : 'Cette série n’est pas dans vos BD.';
        }

        $this->db->beginTransaction();
        try {
            if ($statut === LibraryStatut::COLLECTION) {
                $deleteTomes = $this->db->prepare(
                    'DELETE FROM bibliotheque
                     WHERE statut = :statut
                       AND foyer_id = :foyer_id
                       AND oeuvre_id IN (
                           SELECT oeuvre_id FROM oeuvre_bd WHERE series_id = :series_id
                       )'
                );
                $deleteTomes->execute([
                    'statut' => LibraryStatut::COLLECTION,
                    'foyer_id' => $foyerId,
                    'series_id' => $seriesId,
                ]);

                $this->db->prepare(
                    'DELETE FROM series_bibliotheque
                     WHERE series_id = ? AND statut = ? AND foyer_id = ?'
                )->execute([$seriesId, LibraryStatut::COLLECTION, $foyerId]);
            } else {
                $deleteTomes = $this->db->prepare(
                    'DELETE FROM bibliotheque
                     WHERE statut = :statut
                       AND user_id = :user_id
                       AND oeuvre_id IN (
                           SELECT oeuvre_id FROM oeuvre_bd WHERE series_id = :series_id
                       )'
                );
                $deleteTomes->execute([
                    'statut' => LibraryStatut::WISHLIST,
                    'user_id' => $userId,
                    'series_id' => $seriesId,
                ]);

                $this->db->prepare(
                    'DELETE FROM series_bibliotheque
                     WHERE series_id = ? AND statut = ? AND user_id = ?'
                )->execute([$seriesId, LibraryStatut::WISHLIST, $userId]);
            }

            $removedTomes = $deleteTomes->rowCount();
            $this->db->commit();

            return ['removed_tomes' => $removedTomes];
        } catch (\Throwable $e) {
            if ($this->db->inTransaction()) {
                $this->db->rollBack();
            }
            error_log('BdLibraryMutations::removeSeriesFromLibrary: ' . $e->getMessage());

            return 'Impossible de retirer la série de votre bibliothèque.';
        }
    }
}
