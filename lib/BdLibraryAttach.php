<?php
/**
 * Rattachement d’albums BD au catalogue dans la bibliothèque utilisateur.
 */

declare(strict_types=1);

namespace Moncine;

use PDO;

final class BdLibraryAttach
{
    public function __construct(
        private readonly PDO $db,
        private readonly BdLibraryQuery $libraryQuery
    ) {
    }

    /** Ajoute une série BD à la collection ou aux envies (sans tome). */
    public function registerSeriesInLibrary(
        int $seriesId,
        string $statut,
        int $userId,
        int $foyerId
    ): bool|string {
        if (!BdRepository::seriesLibraryTableExists() || $seriesId <= 0) {
            return 'Module BD non disponible.';
        }

        $series = (new SeriesRepository())->findById($seriesId, MediaDomain::BD);
        if ($series === null) {
            return 'Série introuvable.';
        }

        $statut = LibraryStatut::normalize($statut);
        $this->db->prepare(
            'INSERT OR IGNORE INTO series_bibliotheque (series_id, user_id, foyer_id, statut)
             VALUES (?, ?, ?, ?)'
        )->execute([$seriesId, $userId, $foyerId, $statut]);

        return true;
    }

    public function isSeriesInLibrary(int $seriesId, string $statut, int $userId, int $foyerId): bool
    {
        if (!BdRepository::seriesLibraryTableExists() || $seriesId <= 0) {
            return false;
        }

        $statut = LibraryStatut::normalize($statut);
        if ($statut === LibraryStatut::COLLECTION) {
            $stmt = $this->db->prepare(
                'SELECT 1 FROM series_bibliotheque
                 WHERE series_id = ? AND statut = ? AND foyer_id = ? LIMIT 1'
            );
            $stmt->execute([$seriesId, LibraryStatut::COLLECTION, $foyerId]);
        } else {
            $stmt = $this->db->prepare(
                'SELECT 1 FROM series_bibliotheque
                 WHERE series_id = ? AND statut = ? AND user_id = ? LIMIT 1'
            );
            $stmt->execute([$seriesId, LibraryStatut::WISHLIST, $userId]);
        }

        return $stmt->fetchColumn() !== false;
    }

    /**
     * @param array<string, mixed> $libraryDetails
     * @return int|string
     */
    public function addFromCatalogOeuvre(
        int $oeuvreId,
        string $statut,
        int $userId,
        int $foyerId,
        array $libraryDetails = []
    ): int|string {
        if (!BdRepository::isAvailable()) {
            return 'Module BD non disponible.';
        }

        $catalog = $this->libraryQuery->findCatalogByOeuvreId($oeuvreId);
        if ($catalog === null) {
            return 'Album introuvable dans le catalogue.';
        }

        $existing = $this->libraryQuery->findLibraryBibIdForCatalogOeuvre($oeuvreId, $userId, $foyerId);
        if ($existing !== null && $existing > 0) {
            return 'Cet album est déjà dans votre bibliothèque.';
        }

        $support = BdPhysicalSupport::normalize((string) ($libraryDetails['support_physique'] ?? ''));
        $seriesId = (int) ($catalog['series_id'] ?? 0);

        try {
            $bibId = (new BibliothequeRepository())->insert($userId, $foyerId, $oeuvreId, [
                'statut' => LibraryStatut::normalize($statut),
                'support_physique' => $support,
            ]);
            if ($seriesId > 0) {
                $register = $this->registerSeriesInLibrary($seriesId, $statut, $userId, $foyerId);
                if ($register !== true) {
                    return (string) $register;
                }
            }

            return $bibId;
        } catch (\Throwable $e) {
            return 'Erreur lors de l’ajout à la bibliothèque.';
        }
    }

    /**
     * @return int nombre de tomes ajoutés
     */
    public function attachCatalogTomesToCollection(int $seriesId, int $userId, int $foyerId): int
    {
        if (!BdRepository::isAvailable() || $seriesId <= 0) {
            return 0;
        }

        $stmt = $this->db->prepare(
            'SELECT oeuvre_id FROM oeuvre_bd WHERE series_id = ? ORDER BY tome_ordre ASC'
        );
        $stmt->execute([$seriesId]);

        $attached = 0;
        while ($row = $stmt->fetch(\PDO::FETCH_ASSOC)) {
            $oeuvreId = (int) ($row['oeuvre_id'] ?? 0);
            if ($oeuvreId <= 0) {
                continue;
            }
            $result = $this->addFromCatalogOeuvre($oeuvreId, LibraryStatut::COLLECTION, $userId, $foyerId);
            if (is_int($result)) {
                $attached++;
            }
        }

        return $attached;
    }

    public function addAlbumToWishlist(int $bibId, int $userId, int $foyerId): bool|string
    {
        $album = $this->libraryQuery->findByBibId($bibId, $userId, $foyerId);
        if ($album === null) {
            return 'Album introuvable.';
        }

        if (($album['statut'] ?? '') !== LibraryStatut::COLLECTION) {
            return 'Action réservée aux tomes de votre collection.';
        }

        if (BdPossession::isPossessed($album)) {
            return 'Ce tome est déjà possédé.';
        }

        $oeuvreId = (int) ($album['oeuvre_id'] ?? 0);
        $seriesId = (int) ($album['series_id'] ?? 0);
        if ($oeuvreId <= 0) {
            return 'Tome invalide.';
        }

        $bibRepo = new BibliothequeRepository();
        $existingWishlist = $bibRepo->findByOeuvreId($oeuvreId, $userId, $foyerId, LibraryStatut::WISHLIST);
        if ($existingWishlist !== null) {
            return true;
        }

        try {
            $bibRepo->insert($userId, $foyerId, $oeuvreId, [
                'statut' => LibraryStatut::WISHLIST,
                'support_physique' => '',
            ]);
        } catch (\Throwable $e) {
            error_log('BdLibraryAttach::addAlbumToWishlist: ' . $e->getMessage());

            return 'Impossible d’ajouter aux envies.';
        }

        if ($seriesId > 0) {
            $register = $this->registerSeriesInLibrary($seriesId, LibraryStatut::WISHLIST, $userId, $foyerId);
            if ($register !== true) {
                return (string) $register;
            }
        }

        return true;
    }
}
