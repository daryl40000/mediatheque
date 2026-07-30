<?php
/**
 * Statistiques de la bibliothèque de livres (collection, lectures, ressentis).
 */

declare(strict_types=1);

namespace Moncine;

use PDO;

final class LivreCollectionStats
{
    private PDO $db;

    public function __construct()
    {
        $this->db = Database::getInstance();
    }

    /**
     * Données pour la page Statistiques livres.
     *
     * @return array<string, mixed>
     */
    public function getDashboard(int $userId, int $foyerId): array
    {
        if (!LivreRepository::isAvailable() || $userId <= 0 || $foyerId <= 0) {
            return $this->emptyDashboard();
        }

        $currentYear = (int) date('Y');
        $repo = new LivreRepository();
        $totalBooks = $repo->countInLibrary($userId, $foyerId, LibraryStatut::COLLECTION);
        $wishlistCount = $repo->countInLibrary($userId, $foyerId, LibraryStatut::WISHLIST);
        $booksReadTotal = $this->countDistinctBooksRead($userId);
        $booksReadYear = $this->countDistinctBooksReadInYear($userId, $currentYear);
        $readingsTotal = $this->countReadings($userId);
        $readingsYear = $this->countReadingsInYear($userId, $currentYear);
        $noteStats = $this->noteStatistics($userId);
        $pagesRead = $this->totalPagesRead($userId);
        $sagaCount = $this->countSagasInCollection($foyerId);

        return [
            'current_year' => $currentYear,
            'total_books' => $totalBooks,
            'wishlist_count' => $wishlistCount,
            'saga_count' => $sagaCount,
            'books_read_total' => $booksReadTotal,
            'books_read_year' => $booksReadYear,
            'books_never_read' => max(0, $totalBooks - $booksReadTotal),
            'readings_total' => $readingsTotal,
            'readings_year' => $readingsYear,
            'pages_read_total' => $pagesRead,
            'percent_read' => $totalBooks > 0
                ? round(($booksReadTotal / $totalBooks) * 100, 1)
                : 0.0,
            'ressenti_count' => $noteStats['count'],
            'readings_sans_ressenti' => $noteStats['readings_without_note'],
            'ressenti_distribution' => $noteStats['distribution'],
            'ressenti_distribution_max' => $noteStats['distribution_max'],
            'coups_de_coeur_count' => $noteStats['adore_count'],
            'reads_by_year' => $this->readsByYear($userId),
            'support_breakdown' => $this->supportBreakdown($foyerId, $totalBooks),
            'category_breakdown' => $this->categoryBreakdown($foyerId),
            'coups_de_coeur' => $this->topAdoredBooks($userId, $foyerId, 8),
            'moins_aimes' => $this->leastLikedBooks($userId, $foyerId, 8),
            'most_reread' => $this->mostRereadBooks($userId, $foyerId, 6),
        ];
    }

    /** @return array<string, mixed> */
    private function emptyDashboard(): array
    {
        $currentYear = (int) date('Y');

        return [
            'current_year' => $currentYear,
            'total_books' => 0,
            'wishlist_count' => 0,
            'saga_count' => 0,
            'books_read_total' => 0,
            'books_read_year' => 0,
            'books_never_read' => 0,
            'readings_total' => 0,
            'readings_year' => 0,
            'pages_read_total' => 0,
            'percent_read' => 0.0,
            'ressenti_count' => 0,
            'readings_sans_ressenti' => 0,
            'ressenti_distribution' => array_fill(1, RessentiNote::MAX_SCORE, 0),
            'ressenti_distribution_max' => 1,
            'coups_de_coeur_count' => 0,
            'reads_by_year' => [],
            'support_breakdown' => ['items' => [], 'max' => 1, 'unknown_count' => 0],
            'category_breakdown' => ['items' => [], 'max' => 1],
            'coups_de_coeur' => [],
            'moins_aimes' => [],
            'most_reread' => [],
        ];
    }

    private function historyJoinSql(): string
    {
        return ' INNER JOIN bibliotheque b ON b.id = h.film_id
                 INNER JOIN oeuvres o ON o.id = b.oeuvre_id
                 INNER JOIN oeuvre_livre ol ON ol.oeuvre_id = o.id';
    }

    private function livreDomainSql(): string
    {
        return ' AND o.media_domain = ?';
    }

    private function countDistinctBooksRead(int $userId): int
    {
        $stmt = $this->db->prepare(
            'SELECT COUNT(DISTINCT h.film_id) FROM historique h'
            . $this->historyJoinSql()
            . ' WHERE h.user_id = ?' . $this->livreDomainSql()
        );
        $stmt->execute([$userId, MediaDomain::LIVRE]);

        return (int) $stmt->fetchColumn();
    }

    private function countDistinctBooksReadInYear(int $userId, int $year): int
    {
        $stmt = $this->db->prepare(
            "SELECT COUNT(DISTINCT h.film_id) FROM historique h"
            . $this->historyJoinSql()
            . " WHERE h.user_id = ? AND strftime('%Y', h.date_vue) = ?"
            . $this->livreDomainSql()
        );
        $stmt->execute([$userId, (string) $year, MediaDomain::LIVRE]);

        return (int) $stmt->fetchColumn();
    }

    private function countReadings(int $userId): int
    {
        $stmt = $this->db->prepare(
            'SELECT COUNT(*) FROM historique h'
            . $this->historyJoinSql()
            . ' WHERE h.user_id = ?' . $this->livreDomainSql()
        );
        $stmt->execute([$userId, MediaDomain::LIVRE]);

        return (int) $stmt->fetchColumn();
    }

    private function countReadingsInYear(int $userId, int $year): int
    {
        $stmt = $this->db->prepare(
            "SELECT COUNT(*) FROM historique h"
            . $this->historyJoinSql()
            . " WHERE h.user_id = ? AND strftime('%Y', h.date_vue) = ?"
            . $this->livreDomainSql()
        );
        $stmt->execute([$userId, (string) $year, MediaDomain::LIVRE]);

        return (int) $stmt->fetchColumn();
    }

    /**
     * Pages cumulées : chaque lecture compte la pagination du livre (0 si inconnue).
     */
    private function totalPagesRead(int $userId): int
    {
        $stmt = $this->db->prepare(
            'SELECT COALESCE(SUM(ol.pages), 0) FROM historique h'
            . $this->historyJoinSql()
            . ' WHERE h.user_id = ?' . $this->livreDomainSql()
        );
        $stmt->execute([$userId, MediaDomain::LIVRE]);

        return (int) $stmt->fetchColumn();
    }

    private function countSagasInCollection(int $foyerId): int
    {
        if (!CatalogSchema::hasOeuvreSagaColumns()) {
            return 0;
        }

        $stmt = $this->db->prepare(
            'SELECT COUNT(DISTINCT o.saga)
             FROM bibliotheque b
             INNER JOIN oeuvres o ON o.id = b.oeuvre_id
             WHERE b.foyer_id = ? AND b.statut = ? AND o.media_domain = ?
               AND TRIM(o.saga) != \'\''
        );
        $stmt->execute([$foyerId, LibraryStatut::COLLECTION, MediaDomain::LIVRE]);

        return (int) $stmt->fetchColumn();
    }

    /**
     * @return array{
     *   count: int,
     *   readings_without_note: int,
     *   distribution: array<int, int>,
     *   distribution_max: int,
     *   adore_count: int
     * }
     */
    private function noteStatistics(int $userId): array
    {
        $distribution = array_fill(1, RessentiNote::MAX_SCORE, 0);
        $max = 0;
        $noteWhere = RessentiNote::sqlValidNote('h');

        $stmt = $this->db->prepare(
            'SELECT h.note, COUNT(*) AS cnt FROM historique h'
            . $this->historyJoinSql()
            . ' WHERE h.user_id = ?' . $this->livreDomainSql()
            . ' AND ' . $noteWhere . '
             GROUP BY h.note
             ORDER BY h.note'
        );
        $stmt->execute([$userId, MediaDomain::LIVRE]);
        foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) ?: [] as $row) {
            $n = (int) ($row['note'] ?? 0);
            $c = (int) ($row['cnt'] ?? 0);
            if ($n >= RessentiNote::MIN_SCORE && $n <= RessentiNote::MAX_SCORE) {
                $distribution[$n] = $c;
                $max = max($max, $c);
            }
        }

        $notesCount = array_sum($distribution);
        $readingsTotal = $this->countReadings($userId);

        return [
            'count' => $notesCount,
            'readings_without_note' => max(0, $readingsTotal - $notesCount),
            'distribution' => $distribution,
            'distribution_max' => $max > 0 ? $max : 1,
            'adore_count' => $distribution[5] ?? 0,
        ];
    }

    /**
     * @return list<array{year: int, books: int, readings: int}>
     */
    private function readsByYear(int $userId): array
    {
        $stmt = $this->db->prepare(
            "SELECT CAST(strftime('%Y', h.date_vue) AS INTEGER) AS y,
                    COUNT(*) AS readings,
                    COUNT(DISTINCT h.film_id) AS books
             FROM historique h"
            . $this->historyJoinSql()
            . ' WHERE h.user_id = ?' . $this->livreDomainSql() . '
             GROUP BY y
             ORDER BY y ASC'
        );
        $stmt->execute([$userId, MediaDomain::LIVRE]);

        $rows = [];
        foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) ?: [] as $row) {
            $year = (int) ($row['y'] ?? 0);
            if ($year <= 0) {
                continue;
            }
            $rows[] = [
                'year' => $year,
                'books' => (int) ($row['books'] ?? 0),
                'readings' => (int) ($row['readings'] ?? 0),
            ];
        }

        return $rows;
    }

    /**
     * @return array{
     *   items: list<array{key: string, label: string, count: int, percent: float}>,
     *   max: int,
     *   unknown_count: int
     * }
     */
    private function supportBreakdown(int $foyerId, int $totalBooks): array
    {
        $supports = [
            'papier' => 'Papier',
            'ebook' => 'Numérique (ebook)',
            'autre' => 'Autre',
        ];
        $counts = array_fill_keys(array_keys($supports), 0);
        $unknown = 0;

        $stmt = $this->db->prepare(
            'SELECT b.support_physique, COUNT(*) AS cnt
             FROM bibliotheque b
             INNER JOIN oeuvres o ON o.id = b.oeuvre_id
             WHERE b.foyer_id = ? AND b.statut = ? AND o.media_domain = ?
             GROUP BY b.support_physique'
        );
        $stmt->execute([$foyerId, LibraryStatut::COLLECTION, MediaDomain::LIVRE]);

        foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) ?: [] as $row) {
            $key = strtolower(trim((string) ($row['support_physique'] ?? '')));
            $cnt = (int) ($row['cnt'] ?? 0);
            if ($key !== '' && isset($counts[$key])) {
                $counts[$key] += $cnt;
            } else {
                $unknown += $cnt;
            }
        }

        $items = [];
        $max = 0;
        foreach ($supports as $key => $label) {
            $count = $counts[$key];
            $max = max($max, $count);
            $items[] = [
                'key' => $key,
                'label' => $label,
                'count' => $count,
                'percent' => $totalBooks > 0 ? round(($count / $totalBooks) * 100, 1) : 0.0,
            ];
        }

        if ($unknown > 0) {
            $max = max($max, $unknown);
            $items[] = [
                'key' => '',
                'label' => 'Non renseigné',
                'count' => $unknown,
                'percent' => $totalBooks > 0 ? round(($unknown / $totalBooks) * 100, 1) : 0.0,
            ];
        }

        return [
            'items' => $items,
            'max' => $max > 0 ? $max : 1,
            'unknown_count' => $unknown,
        ];
    }

    /**
     * @return array{
     *   items: list<array{label: string, count: int, percent: float}>,
     *   max: int
     * }
     */
    private function categoryBreakdown(int $foyerId): array
    {
        $stmt = $this->db->prepare(
            'SELECT ol.categories
             FROM bibliotheque b
             INNER JOIN oeuvres o ON o.id = b.oeuvre_id
             INNER JOIN oeuvre_livre ol ON ol.oeuvre_id = o.id
             WHERE b.foyer_id = ? AND b.statut = ? AND o.media_domain = ?'
        );
        $stmt->execute([$foyerId, LibraryStatut::COLLECTION, MediaDomain::LIVRE]);

        $counts = [];
        $totalTagged = 0;
        foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) ?: [] as $row) {
            $labels = LivreCategory::parseList((string) ($row['categories'] ?? ''));
            if ($labels === []) {
                continue;
            }
            $totalTagged++;
            foreach ($labels as $label) {
                $counts[$label] = ($counts[$label] ?? 0) + 1;
            }
        }

        arsort($counts);
        $items = [];
        $max = 0;
        foreach ($counts as $label => $count) {
            $max = max($max, $count);
            $items[] = [
                'label' => (string) $label,
                'count' => $count,
                'percent' => $totalTagged > 0 ? round(($count / $totalTagged) * 100, 1) : 0.0,
            ];
        }

        return [
            'items' => $items,
            'max' => $max > 0 ? $max : 1,
        ];
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function topAdoredBooks(int $userId, int $foyerId, int $limit): array
    {
        $noteWhere = RessentiNote::sqlValidNote('h');
        $adoreScore = RessentiNote::MAX_SCORE;
        $stmt = $this->db->prepare(
            'SELECT b.id, o.titre, ol.auteur, MAX(h.note) AS best_note
             FROM bibliotheque b
             INNER JOIN oeuvres o ON o.id = b.oeuvre_id
             INNER JOIN oeuvre_livre ol ON ol.oeuvre_id = o.id
             INNER JOIN historique h ON h.film_id = b.id
             WHERE b.foyer_id = ? AND b.statut = ? AND h.user_id = ?
               AND o.media_domain = ?
               AND ' . $noteWhere . '
             GROUP BY b.id
             HAVING best_note = ?
             ORDER BY o.titre COLLATE NOCASE ASC
             LIMIT ?'
        );
        $stmt->bindValue(1, $foyerId, PDO::PARAM_INT);
        $stmt->bindValue(2, LibraryStatut::COLLECTION);
        $stmt->bindValue(3, $userId, PDO::PARAM_INT);
        $stmt->bindValue(4, MediaDomain::LIVRE);
        $stmt->bindValue(5, $adoreScore, PDO::PARAM_INT);
        $stmt->bindValue(6, max(1, $limit), PDO::PARAM_INT);
        $stmt->execute();

        return ListOf::assocRows($stmt->fetchAll(PDO::FETCH_ASSOC) ?: []);
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function leastLikedBooks(int $userId, int $foyerId, int $limit): array
    {
        $noteWhere = RessentiNote::sqlValidNote('h');
        $stmt = $this->db->prepare(
            'SELECT b.id, o.titre, ol.auteur, MAX(h.note) AS best_note
             FROM bibliotheque b
             INNER JOIN oeuvres o ON o.id = b.oeuvre_id
             INNER JOIN oeuvre_livre ol ON ol.oeuvre_id = o.id
             INNER JOIN historique h ON h.film_id = b.id
             WHERE b.foyer_id = ? AND b.statut = ? AND h.user_id = ?
               AND o.media_domain = ?
               AND ' . $noteWhere . '
             GROUP BY b.id
             ORDER BY best_note ASC, o.titre COLLATE NOCASE ASC
             LIMIT ?'
        );
        $stmt->bindValue(1, $foyerId, PDO::PARAM_INT);
        $stmt->bindValue(2, LibraryStatut::COLLECTION);
        $stmt->bindValue(3, $userId, PDO::PARAM_INT);
        $stmt->bindValue(4, MediaDomain::LIVRE);
        $stmt->bindValue(5, max(1, $limit), PDO::PARAM_INT);
        $stmt->execute();

        return ListOf::assocRows($stmt->fetchAll(PDO::FETCH_ASSOC) ?: []);
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function mostRereadBooks(int $userId, int $foyerId, int $limit): array
    {
        $stmt = $this->db->prepare(
            'SELECT b.id, o.titre, ol.auteur, COUNT(*) AS read_count
             FROM historique h
             INNER JOIN bibliotheque b ON b.id = h.film_id
             INNER JOIN oeuvres o ON o.id = b.oeuvre_id
             INNER JOIN oeuvre_livre ol ON ol.oeuvre_id = o.id
             WHERE b.foyer_id = ? AND b.statut = ? AND h.user_id = ?
               AND o.media_domain = ?
             GROUP BY h.film_id
             HAVING read_count > 1
             ORDER BY read_count DESC, o.titre COLLATE NOCASE ASC
             LIMIT ?'
        );
        $stmt->bindValue(1, $foyerId, PDO::PARAM_INT);
        $stmt->bindValue(2, LibraryStatut::COLLECTION);
        $stmt->bindValue(3, $userId, PDO::PARAM_INT);
        $stmt->bindValue(4, MediaDomain::LIVRE);
        $stmt->bindValue(5, max(1, $limit), PDO::PARAM_INT);
        $stmt->execute();

        return ListOf::assocRows($stmt->fetchAll(PDO::FETCH_ASSOC) ?: []);
    }
}
