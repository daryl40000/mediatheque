<?php
declare(strict_types=1);
namespace Moncine;
use PDO;
final class MagazineLibraryMutations { public function __construct(private readonly PDO $db, private readonly MagazineLibraryQuery $libraryQuery, private readonly MagazineLibraryAttach $libraryAttach) {}
    public function deleteFromLibrary(int $bibId, int $userId, int $foyerId): bool|string
    {
        $issue = $this->libraryQuery->findIssueByBibId($bibId, $userId, $foyerId);
        if ($issue === null) {
            return 'Numéro introuvable.';
        }

        $stmt = $this->db->prepare('DELETE FROM bibliotheque WHERE id = ?');
        $stmt->execute([$bibId]);

        return $stmt->rowCount() > 0 ? true : 'Suppression impossible.';
    }
    public function removeSeriesFromLibrary(int $seriesId, string $statut, int $userId, int $foyerId): array|string
    {
        if (!MagazineRepository::isAvailable() || $seriesId <= 0) {
            return 'Module magazines non disponible.';
        }

        $series = (new SeriesRepository())->findById($seriesId, MediaDomain::MAGAZINE);
        if ($series === null) {
            return 'Série introuvable.';
        }

        $statut = LibraryStatut::normalize($statut);
        if (!$this->libraryAttach->isSeriesInLibrary($seriesId, $statut, $userId, $foyerId)) {
            return $statut === LibraryStatut::WISHLIST
                ? 'Cette série n’est pas dans vos envies.'
                : 'Cette série n’est pas dans vos magazines.';
        }

        $this->db->beginTransaction();
        try {
            if ($statut === LibraryStatut::COLLECTION) {
                $deleteIssues = $this->db->prepare(
                    'DELETE FROM bibliotheque
                     WHERE statut = :statut
                       AND foyer_id = :foyer_id
                       AND oeuvre_id IN (
                           SELECT oeuvre_id FROM oeuvre_magazine WHERE series_id = :series_id
                       )'
                );
                $deleteIssues->execute([
                    'statut' => LibraryStatut::COLLECTION,
                    'foyer_id' => $foyerId,
                    'series_id' => $seriesId,
                ]);

                $this->db->prepare(
                    'DELETE FROM series_bibliotheque
                     WHERE series_id = ? AND statut = ? AND foyer_id = ?'
                )->execute([$seriesId, LibraryStatut::COLLECTION, $foyerId]);
            } else {
                $deleteIssues = $this->db->prepare(
                    'DELETE FROM bibliotheque
                     WHERE statut = :statut
                       AND user_id = :user_id
                       AND oeuvre_id IN (
                           SELECT oeuvre_id FROM oeuvre_magazine WHERE series_id = :series_id
                       )'
                );
                $deleteIssues->execute([
                    'statut' => LibraryStatut::WISHLIST,
                    'user_id' => $userId,
                    'series_id' => $seriesId,
                ]);

                $this->db->prepare(
                    'DELETE FROM series_bibliotheque
                     WHERE series_id = ? AND statut = ? AND user_id = ?'
                )->execute([$seriesId, LibraryStatut::WISHLIST, $userId]);
            }

            $removedIssues = $deleteIssues->rowCount();
            $this->db->commit();

            return ['removed_issues' => $removedIssues];
        } catch (\Throwable $e) {
            if ($this->db->inTransaction()) {
                $this->db->rollBack();
            }
            error_log('MagazineLibraryMutations::removeSeriesFromLibrary: ' . $e->getMessage());

            return 'Impossible de retirer la série de votre bibliothèque.';
        }
    }
    public function userCanAccessStoredObject(int $storedObjectId, int $userId, int $foyerId): bool
    {
        if (!MagazineRepository::isAvailable() || $storedObjectId <= 0 || $userId <= 0) {
            return false;
        }

        $params = [
            'stored_object_id' => $storedObjectId,
            'user_id' => $userId,
            'foyer_id' => $foyerId,
            'collection' => LibraryStatut::COLLECTION,
            'wishlist' => LibraryStatut::WISHLIST,
        ];

        $sql = 'SELECT 1
             FROM oeuvre_magazine om
             INNER JOIN bibliotheque b ON b.oeuvre_id = om.oeuvre_id
             WHERE om.stored_object_id = :stored_object_id
               AND (
                    (b.statut = :collection AND b.foyer_id = :foyer_id)
                    OR (b.statut = :wishlist AND b.user_id = :user_id)
               )
             LIMIT 1';
        $stmt = $this->db->prepare($sql);
        $stmt->execute(MagazineCatalogSql::filterParamsForSql($sql, $params));

        return (bool) $stmt->fetchColumn();
    }
    public function clearWishlistEntriesWhenPossessed(int $oeuvreId): void
    {
        if ($oeuvreId <= 0) {
            return;
        }

        $stmt = $this->db->prepare(
            'SELECT b.support_physique, om.stored_object_id
             FROM bibliotheque b
             INNER JOIN oeuvre_magazine om ON om.oeuvre_id = b.oeuvre_id
             WHERE b.oeuvre_id = ? AND b.statut = ?
             LIMIT 1'
        );
        $stmt->execute([$oeuvreId, LibraryStatut::COLLECTION]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        if ($row === false || !MagazineSupport::isPossessed($row)) {
            return;
        }

        $this->db->prepare(
            'DELETE FROM bibliotheque WHERE oeuvre_id = ? AND statut = ?'
        )->execute([$oeuvreId, LibraryStatut::WISHLIST]);
    }
}
