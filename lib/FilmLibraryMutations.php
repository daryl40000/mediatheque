<?php
/**
 * Suppressions et mutations en masse sur la bibliothèque films (support, promotion en collection).
 */

declare(strict_types=1);

namespace Moncine;

use PDO;

final class FilmLibraryMutations
{
    public function __construct(
        private readonly PDO $db,
        private readonly BibliothequeRepository $bibliotheque
    ) {
    }

    public function deleteAll(): void
    {
        $userId = $this->userId();
        $foyerId = $this->foyerId();

        $stmt = $this->db->prepare(
            'DELETE FROM historique WHERE user_id = ?'
        );
        $stmt->execute([$userId]);

        if ($foyerId > 0) {
            $stmt = $this->db->prepare(
                'DELETE FROM historique WHERE film_id IN (
                    SELECT id FROM bibliotheque WHERE foyer_id = ? AND statut = ?
                 )'
            );
            $stmt->execute([$foyerId, LibraryStatut::COLLECTION]);

            $stmt = $this->db->prepare(
                'DELETE FROM bibliotheque WHERE foyer_id = ? AND statut = ?'
            );
            $stmt->execute([$foyerId, LibraryStatut::COLLECTION]);
        }

        $stmt = $this->db->prepare(
            'DELETE FROM bibliotheque WHERE user_id = ? AND statut = ?'
        );
        $stmt->execute([$userId, LibraryStatut::WISHLIST]);
    }

    public function deleteById(int $filmId): bool
    {
        if ($filmId <= 0) {
            return false;
        }

        $userId = $this->userId();
        $foyerId = $this->foyerId();
        $item = $this->bibliotheque->findById($filmId, $userId, $foyerId);
        if ($item !== null && ($item['statut'] ?? '') === LibraryStatut::COLLECTION) {
            $this->db->prepare('DELETE FROM historique WHERE film_id = ?')->execute([$filmId]);
        } else {
            $this->db->prepare('DELETE FROM historique WHERE film_id = ? AND user_id = ?')
                ->execute([$filmId, $userId]);
        }

        return $this->bibliotheque->deleteById($filmId, $userId, $foyerId);
    }

    /**
     * @param list<int> $filmIds
     */
    public function deleteFilms(array $filmIds): int
    {
        $deleted = 0;
        foreach ($filmIds as $filmId) {
            if ($this->deleteById((int) $filmId)) {
                $deleted++;
            }
        }

        return $deleted;
    }

    /**
     * @param list<int> $filmIds
     */
    public function updateFilmsSupportPhysique(array $filmIds, string $supportKey): int
    {
        if ($filmIds === []) {
            return 0;
        }

        $supportKey = SupportPhysique::normalize($supportKey);
        $stmt = $this->db->prepare(
            'UPDATE bibliotheque SET support_physique = :support_physique
             WHERE id = :id AND foyer_id = :foyer_id'
        );

        $updated = 0;
        foreach ($filmIds as $filmId) {
            $filmId = (int) $filmId;
            if ($filmId <= 0) {
                continue;
            }
            $stmt->execute([
                'support_physique' => $supportKey,
                'id' => $filmId,
                'foyer_id' => $this->foyerId(),
            ]);
            if ($stmt->rowCount() > 0) {
                $updated++;
            }
        }

        return $updated;
    }

    public function promoteToCollection(
        int $libraryId,
        string $supportKey = '',
        string $ean = '',
        ?int $wishlistTargetId = null
    ): bool {
        return $this->bibliotheque->promoteToCollection(
            $libraryId,
            $this->userId(),
            $this->foyerId(),
            $supportKey,
            $ean,
            $wishlistTargetId
        );
    }

    private function userId(): int
    {
        return UserContext::currentUserId();
    }

    private function foyerId(): int
    {
        return UserContext::currentFoyerId();
    }
}
