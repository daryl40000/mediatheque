<?php
declare(strict_types=1);
namespace Moncine;
use PDO;
final class MagazineLibraryAttach { public function __construct(private readonly PDO $db, private readonly MagazineLibraryQuery $libraryQuery) {}
    public function registerSeriesInLibrary(
        int $seriesId,
        string $statut,
        int $userId,
        int $foyerId
    ): bool|string {
        if (!MagazineRepository::seriesLibraryTableExists() || $seriesId <= 0) {
            return 'Module magazines non disponible.';
        }

        $series = (new SeriesRepository())->findById($seriesId, MediaDomain::MAGAZINE);
        if ($series === null) {
            return 'Série introuvable.';
        }

        $statut = LibraryStatut::normalize($statut);
        if ($statut === LibraryStatut::COLLECTION) {
            $this->db->prepare(
                'INSERT OR IGNORE INTO series_bibliotheque (series_id, user_id, foyer_id, statut)
                 VALUES (?, ?, ?, ?)'
            )->execute([$seriesId, $userId, $foyerId, LibraryStatut::COLLECTION]);

            return true;
        }

        $this->db->prepare(
            'INSERT OR IGNORE INTO series_bibliotheque (series_id, user_id, foyer_id, statut)
             VALUES (?, ?, ?, ?)'
        )->execute([$seriesId, $userId, $foyerId, LibraryStatut::WISHLIST]);

        return true;
    }
    public function isSeriesInLibrary(int $seriesId, string $statut, int $userId, int $foyerId): bool
    {
        if (!MagazineRepository::seriesLibraryTableExists() || $seriesId <= 0) {
            return false;
        }

        $statut = LibraryStatut::normalize($statut);
        if ($statut === LibraryStatut::COLLECTION) {
            $stmt = $this->db->prepare(
                'SELECT 1 FROM series_bibliotheque
                 WHERE series_id = ? AND statut = ? AND foyer_id = ?
                 LIMIT 1'
            );
            $stmt->execute([$seriesId, LibraryStatut::COLLECTION, $foyerId]);
        } else {
            $stmt = $this->db->prepare(
                'SELECT 1 FROM series_bibliotheque
                 WHERE series_id = ? AND statut = ? AND user_id = ?
                 LIMIT 1'
            );
            $stmt->execute([$seriesId, LibraryStatut::WISHLIST, $userId]);
        }

        return (bool) $stmt->fetchColumn();
    }
    public function addFromCatalogOeuvre(int $oeuvreId, string $statut, int $userId, int $foyerId): int|string
    {
        if (!MagazineRepository::isAvailable()) {
            return 'Module magazines non disponible.';
        }

        $issue = $this->libraryQuery->findCatalogIssueByOeuvreId($oeuvreId);
        if ($issue === null) {
            return 'Ce numéro n’existe pas dans le catalogue.';
        }

        $statut = LibraryStatut::normalize($statut);
        $bibRepo = new BibliothequeRepository();
        $library = $bibRepo->findByOeuvreId($oeuvreId, $userId, $foyerId);
        if ($library !== null) {
            $bibId = (int) ($library['id'] ?? 0);
            $currentStatut = (string) ($library['statut'] ?? LibraryStatut::COLLECTION);
            if ($currentStatut === $statut) {
                return 'Ce numéro existe déjà dans « ' . LibraryStatut::label($statut) . ' ».';
            }

            $update = ['statut' => $statut];
            if ($statut === LibraryStatut::COLLECTION) {
                $update['foyer_id'] = $foyerId;
            } else {
                $update['foyer_id'] = null;
            }
            $bibRepo->update($bibId, $update);

            return $bibId;
        }

        $seriesId = (int) ($issue['series_id'] ?? 0);
        $hasPdf = (int) ($issue['stored_object_id'] ?? 0) > 0;
        $support = MagazineSupport::formatTagsForStorage(false, $hasPdf);

        $this->db->beginTransaction();
        try {
            $bibId = $bibRepo->insert($userId, $foyerId, $oeuvreId, [
                'statut' => $statut,
                'support_physique' => $support,
            ]);
            $seriesResult = $this->registerSeriesInLibrary($seriesId, $statut, $userId, $foyerId);
            if ($seriesResult !== true) {
                throw new \RuntimeException((string) $seriesResult);
            }

            $this->db->commit();

            return $bibId;
        } catch (\Throwable $e) {
            if ($this->db->inTransaction()) {
                $this->db->rollBack();
            }

            return 'Impossible d’ajouter le numéro à votre bibliothèque.';
        }
    }
    public function attachCatalogIssuesToCollection(int $seriesId, int $userId, int $foyerId): int
    {
        if (!MagazineRepository::isAvailable() || $seriesId <= 0) {
            return 0;
        }

        $stmt = $this->db->prepare(
            'SELECT oeuvre_id FROM oeuvre_magazine WHERE series_id = ? ORDER BY numero_ordre ASC'
        );
        $stmt->execute([$seriesId]);

        $attached = 0;
        while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
            $oeuvreId = (int) ($row['oeuvre_id'] ?? 0);
            if ($oeuvreId <= 0) {
                continue;
            }

            $result = $this->addFromCatalogOeuvre(
                $oeuvreId,
                LibraryStatut::COLLECTION,
                $userId,
                $foyerId
            );
            if (is_int($result)) {
                $attached++;
            }
        }

        return $attached;
    }
    public function addIssueToWishlist(int $bibId, int $userId, int $foyerId): bool|string
    {
        $issue = $this->libraryQuery->findIssueByBibId($bibId, $userId, $foyerId);
        if ($issue === null) {
            return 'Numéro introuvable.';
        }

        if (($issue['statut'] ?? '') !== LibraryStatut::COLLECTION) {
            return 'Action réservée aux numéros de votre collection.';
        }

        if (MagazineSupport::isPossessed($issue)) {
            return 'Ce numéro est déjà possédé (papier ou PDF).';
        }

        $oeuvreId = (int) ($issue['oeuvre_id'] ?? 0);
        $seriesId = (int) ($issue['series_id'] ?? 0);
        if ($oeuvreId <= 0 || $seriesId <= 0) {
            return 'Numéro invalide.';
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
            error_log('MagazineLibraryAttach::addIssueToWishlist: ' . $e->getMessage());

            return 'Impossible d’ajouter aux envies.';
        }

        $this->registerSeriesInLibrary($seriesId, LibraryStatut::WISHLIST, $userId, $foyerId);

        return true;
    }
    public function moveIssueToWishlist(int $bibId, int $userId, int $foyerId): bool|string
    {
        return $this->addIssueToWishlist($bibId, $userId, $foyerId);
    }

}
