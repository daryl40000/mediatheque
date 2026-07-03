<?php
/**
 * Statistiques de la dvdthèque (visions, notes, collection).
 */

declare(strict_types=1);

namespace Moncine;

use PDO;

final class CollectionStats
{
    private PDO $db;

    public function __construct(
        private readonly FilmRepository $films = new FilmRepository()
    ) {
        $this->db = Database::getInstance();
    }

    /**
     * Données pour la page Statistiques.
     *
     * @return array<string, mixed>
     */
    public function getDashboard(): array
    {
        $currentYear = (int) date('Y');
        $totalFilms = $this->films->count();
        $wishlistCount = $this->films->usesCatalogModel() ? $this->films->countWishlist() : 0;
        $filmsVusTotal = $this->countDistinctFilmsSeen();
        $filmsVusYear = $this->countDistinctFilmsSeenInYear($currentYear);
        $visionsTotal = $this->countViewings();
        $visionsYear = $this->countViewingsInYear($currentYear);
        $viewingMinutesTotal = $this->totalViewingMinutes();
        $noteStats = $this->noteStatistics();

        return [
            'current_year' => $currentYear,
            'total_films' => $totalFilms,
            'wishlist_count' => $wishlistCount,
            'has_wishlist' => $this->films->usesCatalogModel(),
            'films_vus_total' => $filmsVusTotal,
            'films_vus_year' => $filmsVusYear,
            'films_jamais_vus' => max(0, $totalFilms - $filmsVusTotal),
            'visions_total' => $visionsTotal,
            'visions_year' => $visionsYear,
            'viewing_minutes_total' => $viewingMinutesTotal,
            'viewing_duration_label' => self::formatViewingDuration($viewingMinutesTotal),
            'percent_seen' => $totalFilms > 0
                ? round(($filmsVusTotal / $totalFilms) * 100, 1)
                : 0.0,
            'ressenti_count' => $noteStats['count'],
            'visions_sans_ressenti' => $noteStats['viewings_without_note'],
            'ressenti_distribution' => $noteStats['distribution'],
            'ressenti_distribution_max' => $noteStats['distribution_max'],
            'coups_de_coeur_count' => $noteStats['adore_count'],
            'views_by_year' => $this->viewsByYear(),
            'support_breakdown' => $this->supportBreakdown($totalFilms),
            'coups_de_coeur' => $this->topAdoredFilms(8),
            'moins_aimes' => $this->leastLikedFilms(8),
            'most_rewatched' => $this->mostRewatchedFilms(6),
        ];
    }

    /** @deprecated Plus de moyenne sur 10 */
    public static function formatAverage(?float $value): string
    {
        return '—';
    }

    public static function formatPercent(float $value): string
    {
        if ($value === (float) (int) $value) {
            return (string) (int) $value . ' %';
        }

        return number_format($value, 1, ',', ' ') . ' %';
    }

    /**
     * Durée cumulée des visions.
     * Moins d’un jour : « 2h 30min » — un jour ou plus : « 3j 5h 30min ».
     */
    public static function formatViewingDuration(int $totalMinutes): string
    {
        if ($totalMinutes <= 0) {
            return '0h 00min';
        }

        $days = intdiv($totalMinutes, 1440);
        $remainder = $totalMinutes % 1440;
        $hours = intdiv($remainder, 60);
        $minutes = $remainder % 60;
        $minutesLabel = str_pad((string) $minutes, 2, '0', STR_PAD_LEFT) . 'min';

        if ($days < 1) {
            return $hours . 'h ' . $minutesLabel;
        }

        return $days . 'j ' . $hours . 'h ' . $minutesLabel;
    }

    /**
     * Minutes de vision cumulées : chaque entrée d’historique compte (re-visions incluses),
     * durée prise sur la fiche film / œuvre (0 si durée inconnue).
     */
    private function totalViewingMinutes(): int
    {
        if (CatalogSchema::usesCatalogTables($this->db)) {
            $params = [$this->currentUserId()];
            $stmt = $this->db->prepare(
                'SELECT COALESCE(SUM(o.duree_min), 0)
                 FROM historique h
                 INNER JOIN bibliotheque b ON b.id = h.film_id
                 INNER JOIN oeuvres o ON o.id = b.oeuvre_id
                 WHERE h.user_id = ?' . $this->filmDomainSql($params)
            );
            $stmt->execute($params);

            return (int) $stmt->fetchColumn();
        }

        return (int) $this->db->query(
            'SELECT COALESCE(SUM(f.duree_min), 0)
             FROM historique h
             INNER JOIN films f ON f.id = h.film_id'
        )->fetchColumn();
    }

    private function countDistinctFilmsSeen(): int
    {
        if ($this->usesPerUserHistory()) {
            $params = [$this->currentUserId()];
            $stmt = $this->db->prepare(
                'SELECT COUNT(DISTINCT h.film_id) FROM historique h'
                . $this->catalogFilmHistoryJoinSql()
                . ' WHERE h.user_id = ?' . $this->filmDomainSql($params)
            );
            $stmt->execute($params);

            return (int) $stmt->fetchColumn();
        }

        return (int) $this->db->query(
            'SELECT COUNT(DISTINCT film_id) FROM historique'
        )->fetchColumn();
    }

    private function countDistinctFilmsSeenInYear(int $year): int
    {
        if ($this->usesPerUserHistory()) {
            $params = [$this->currentUserId(), (string) $year];
            $stmt = $this->db->prepare(
                "SELECT COUNT(DISTINCT h.film_id) FROM historique h"
                . $this->catalogFilmHistoryJoinSql()
                . " WHERE h.user_id = ? AND strftime('%Y', h.date_vue) = ?"
                . $this->filmDomainSql($params)
            );
            $stmt->execute($params);

            return (int) $stmt->fetchColumn();
        }

        $stmt = $this->db->prepare(
            "SELECT COUNT(DISTINCT film_id) FROM historique
             WHERE strftime('%Y', date_vue) = ?"
        );
        $stmt->execute([(string) $year]);

        return (int) $stmt->fetchColumn();
    }

    private function countViewings(): int
    {
        if ($this->usesPerUserHistory()) {
            $params = [$this->currentUserId()];
            $stmt = $this->db->prepare(
                'SELECT COUNT(*) FROM historique h'
                . $this->catalogFilmHistoryJoinSql()
                . ' WHERE h.user_id = ?' . $this->filmDomainSql($params)
            );
            $stmt->execute($params);

            return (int) $stmt->fetchColumn();
        }

        return (int) $this->db->query('SELECT COUNT(*) FROM historique')->fetchColumn();
    }

    private function countViewingsInYear(int $year): int
    {
        if ($this->usesPerUserHistory()) {
            $params = [$this->currentUserId(), (string) $year];
            $stmt = $this->db->prepare(
                "SELECT COUNT(*) FROM historique h"
                . $this->catalogFilmHistoryJoinSql()
                . " WHERE h.user_id = ? AND strftime('%Y', h.date_vue) = ?"
                . $this->filmDomainSql($params)
            );
            $stmt->execute($params);

            return (int) $stmt->fetchColumn();
        }

        $stmt = $this->db->prepare(
            "SELECT COUNT(*) FROM historique WHERE strftime('%Y', date_vue) = ?"
        );
        $stmt->execute([(string) $year]);

        return (int) $stmt->fetchColumn();
    }

    /**
     * @return array{
     *   average_all: ?float,
     *   average_per_film: ?float,
     *   count: int,
     *   viewings_without_note: int,
     *   distribution: array<int, int>,
     *   distribution_max: int
     * }
     */
    private function noteStatistics(): array
    {
        $distribution = array_fill(1, RessentiNote::MAX_SCORE, 0);
        $max = 0;
        $noteWhere = RessentiNote::sqlValidNote('h');
        $historyJoin = $this->catalogFilmHistoryJoinSql();
        $userWhere = $this->historyUserWhereSql();
        $params = $this->historyUserParams();
        $filmWhere = $this->filmDomainSql($params);

        $stmt = $this->db->prepare(
            "SELECT h.note, COUNT(*) AS cnt FROM historique h
             {$historyJoin}
             WHERE {$userWhere}{$filmWhere} AND {$noteWhere}
             GROUP BY h.note
             ORDER BY h.note"
        );
        $stmt->execute($params);
        foreach ($stmt->fetchAll() as $row) {
            $n = (int) $row['note'];
            $c = (int) $row['cnt'];
            if ($n >= RessentiNote::MIN_SCORE && $n <= RessentiNote::MAX_SCORE) {
                $distribution[$n] = $c;
                $max = max($max, $c);
            }
        }

        $notesCount = array_sum($distribution);
        $visionsTotal = $this->countViewings();

        return [
            'count' => $notesCount,
            'viewings_without_note' => max(0, $visionsTotal - $notesCount),
            'distribution' => $distribution,
            'distribution_max' => $max > 0 ? $max : 1,
            'adore_count' => $distribution[5] ?? 0,
        ];
    }

    /**
     * Nombre de visions par année (pour le graphique).
     *
     * @return list<array{year: int, films: int, viewings: int}>
     */
    private function viewsByYear(): array
    {
        $historyJoin = $this->catalogFilmHistoryJoinSql();
        $userWhere = $this->historyUserWhereSql();
        $params = $this->historyUserParams();
        $filmWhere = $this->filmDomainSql($params);

        $stmt = $this->db->prepare(
            "SELECT CAST(strftime('%Y', h.date_vue) AS INTEGER) AS y,
                    COUNT(*) AS viewings,
                    COUNT(DISTINCT h.film_id) AS films
             FROM historique h
             {$historyJoin}
             WHERE {$userWhere}{$filmWhere}
             GROUP BY y
             ORDER BY y ASC"
        );
        $stmt->execute($params);
        $rows = [];
        foreach ($stmt->fetchAll() as $row) {
            $year = (int) ($row['y'] ?? 0);
            if ($year <= 0) {
                continue;
            }
            $rows[] = [
                'year' => $year,
                'films' => (int) ($row['films'] ?? 0),
                'viewings' => (int) ($row['viewings'] ?? 0),
            ];
        }

        return $rows;
    }

    /**
     * Répartition DVD / Blu-ray / Blu-ray 4K dans la collection.
     *
     * @return array{
     *   items: list<array{key: string, label: string, count: int, percent: float, url: string}>,
     *   max: int,
     *   unknown_count: int
     * }
     */
    private function supportBreakdown(int $totalFilms): array
    {
        $counts = [];
        foreach (SupportPhysique::choices() as $key => $label) {
            $counts[$key] = 0;
        }
        $unknown = 0;

        if (CatalogSchema::usesCatalogTables($this->db)) {
            $params = [UserContext::currentFoyerId(), LibraryStatut::COLLECTION];
            $stmt = $this->db->prepare(
                'SELECT b.support_physique, COUNT(*) AS cnt FROM bibliotheque b
                 INNER JOIN oeuvres o ON o.id = b.oeuvre_id
                 WHERE b.foyer_id = ? AND b.statut = ?' . $this->filmDomainSql($params) . '
                 GROUP BY b.support_physique'
            );
            $stmt->execute($params);
        } else {
            $stmt = $this->db->query(
                'SELECT support_physique, COUNT(*) AS cnt FROM films GROUP BY support_physique'
            );
        }
        foreach ($stmt->fetchAll() as $row) {
            $key = SupportPhysique::normalize((string) ($row['support_physique'] ?? ''));
            $cnt = (int) ($row['cnt'] ?? 0);
            if ($key === '' || !isset($counts[$key])) {
                $unknown += $cnt;
            } else {
                $counts[$key] += $cnt;
            }
        }

        $items = [];
        $max = 0;

        foreach (SupportPhysique::choices() as $key => $label) {
            $count = $counts[$key];
            $max = max($max, $count);
            $items[] = [
                'key' => $key,
                'label' => $label,
                'count' => $count,
                'percent' => $totalFilms > 0 ? round(($count / $totalFilms) * 100, 1) : 0.0,
                'url' => View::supportFilterUrl($key),
            ];
        }

        if ($unknown > 0) {
            $max = max($max, $unknown);
            $items[] = [
                'key' => '',
                'label' => 'Non renseigné',
                'count' => $unknown,
                'percent' => $totalFilms > 0 ? round(($unknown / $totalFilms) * 100, 1) : 0.0,
                'url' => '',
            ];
        }

        return [
            'items' => $items,
            'max' => $max > 0 ? $max : 1,
            'unknown_count' => $unknown,
        ];
    }

    /**
     * Coups de cœur (meilleur ressenti « J'adore » par film).
     *
     * @return list<array<string, mixed>>
     */
    private function topAdoredFilms(int $limit): array
    {
        $noteWhere = RessentiNote::sqlValidNote('h');
        $adoreScore = RessentiNote::MAX_SCORE;

        if (CatalogSchema::usesCatalogTables($this->db)) {
            $params = $this->catalogFilmListParams();
            $params['limit'] = max(1, $limit);
            $stmt = $this->db->prepare(
                'SELECT b.id, o.titre, o.realisateur, MAX(h.note) AS best_note
                 FROM bibliotheque b
                 INNER JOIN oeuvres o ON o.id = b.oeuvre_id
                 INNER JOIN historique h ON h.film_id = b.id
                 WHERE b.foyer_id = :foyer_id AND b.statut = :collection AND h.user_id = :user_id'
                . $this->catalogFilmDomainWhereSql()
                . ' AND ' . $noteWhere . '
                 GROUP BY b.id
                 HAVING best_note = ' . $adoreScore . '
                 ORDER BY o.titre COLLATE FRENCH_NOCASE ASC
                 LIMIT :limit'
            );
            $stmt->bindValue(':limit', $params['limit'], PDO::PARAM_INT);
            $stmt->execute($params);

            return $stmt->fetchAll();
        }

        $stmt = $this->db->prepare(
            'SELECT f.id, f.titre, f.realisateur, MAX(h.note) AS best_note
             FROM films f
             INNER JOIN historique h ON h.film_id = f.id
             WHERE ' . $noteWhere . '
             GROUP BY f.id
             HAVING best_note = ?
             ORDER BY f.titre COLLATE FRENCH_NOCASE ASC
             LIMIT ?'
        );
        $stmt->bindValue(1, $adoreScore, PDO::PARAM_INT);
        $stmt->bindValue(2, max(1, $limit), PDO::PARAM_INT);
        $stmt->execute();

        return $stmt->fetchAll();
    }

    /**
     * Films les moins aimés (meilleur ressenti le plus bas par film).
     *
     * @return list<array<string, mixed>>
     */
    private function leastLikedFilms(int $limit): array
    {
        $noteWhere = RessentiNote::sqlValidNote('h');

        if (CatalogSchema::usesCatalogTables($this->db)) {
            $params = $this->catalogFilmListParams();
            $params['limit'] = max(1, $limit);
            $stmt = $this->db->prepare(
                'SELECT b.id, o.titre, o.realisateur, MAX(h.note) AS best_note
                 FROM bibliotheque b
                 INNER JOIN oeuvres o ON o.id = b.oeuvre_id
                 INNER JOIN historique h ON h.film_id = b.id
                 WHERE b.foyer_id = :foyer_id AND b.statut = :collection AND h.user_id = :user_id'
                . $this->catalogFilmDomainWhereSql()
                . ' AND ' . $noteWhere . '
                 GROUP BY b.id
                 ORDER BY best_note ASC, o.titre COLLATE FRENCH_NOCASE ASC
                 LIMIT :limit'
            );
            $stmt->bindValue(':limit', $params['limit'], PDO::PARAM_INT);
            $stmt->execute($params);

            return $stmt->fetchAll();
        }

        $stmt = $this->db->prepare(
            'SELECT f.id, f.titre, f.realisateur, MAX(h.note) AS best_note
             FROM films f
             INNER JOIN historique h ON h.film_id = f.id
             WHERE ' . $noteWhere . '
             GROUP BY f.id
             ORDER BY best_note ASC, f.titre COLLATE FRENCH_NOCASE ASC
             LIMIT ?'
        );
        $stmt->bindValue(1, max(1, $limit), PDO::PARAM_INT);
        $stmt->execute();

        return $stmt->fetchAll();
    }

    /**
     * Films revus le plus souvent.
     *
     * @return list<array<string, mixed>>
     */
    private function mostRewatchedFilms(int $limit): array
    {
        if (CatalogSchema::usesCatalogTables($this->db)) {
            $params = $this->catalogFilmListParams();
            $params['limit'] = max(1, $limit);
            $stmt = $this->db->prepare(
                'SELECT b.id, o.titre, o.realisateur, COUNT(*) AS view_count
                 FROM historique h
                 INNER JOIN bibliotheque b ON b.id = h.film_id
                 INNER JOIN oeuvres o ON o.id = b.oeuvre_id
                 WHERE b.foyer_id = :foyer_id AND b.statut = :collection AND h.user_id = :user_id'
                . $this->catalogFilmDomainWhereSql() . '
                 GROUP BY h.film_id
                 HAVING view_count > 1
                 ORDER BY view_count DESC, o.titre COLLATE FRENCH_NOCASE ASC
                 LIMIT :limit'
            );
            $stmt->bindValue(':limit', $params['limit'], PDO::PARAM_INT);
            $stmt->execute($params);

            return $stmt->fetchAll();
        }

        $stmt = $this->db->prepare(
            'SELECT f.id, f.titre, f.realisateur, COUNT(*) AS view_count
             FROM historique h
             INNER JOIN films f ON f.id = h.film_id
             GROUP BY h.film_id
             HAVING view_count > 1
             ORDER BY view_count DESC, f.titre COLLATE FRENCH_NOCASE ASC
             LIMIT ?'
        );
        $stmt->bindValue(1, max(1, $limit), PDO::PARAM_INT);
        $stmt->execute();

        return $stmt->fetchAll();
    }

    private function usesPerUserHistory(): bool
    {
        return CatalogSchema::usesCatalogTables($this->db);
    }

    private function currentUserId(): int
    {
        return UserContext::currentUserId();
    }

    /** Jointure historique → bibliothèque → œuvre (catalogue uniquement). */
    private function catalogFilmHistoryJoinSql(): string
    {
        if (!$this->usesPerUserHistory()) {
            return '';
        }

        return ' INNER JOIN bibliotheque b ON b.id = h.film_id
                 INNER JOIN oeuvres o ON o.id = b.oeuvre_id';
    }

    /**
     * Limite aux films (exclut jeux, BD, etc.).
     *
     * @param array<int|string, mixed> $params
     */
    private function filmDomainSql(array &$params): string
    {
        if (!CatalogSchema::hasMediaDomainColumn()) {
            return '';
        }

        $params['stats_film_domain'] = MediaDomain::FILM;

        return ' AND o.media_domain = :stats_film_domain';
    }

    /** @return array<string, mixed> */
    private function catalogFilmListParams(): array
    {
        $params = [
            'foyer_id' => UserContext::currentFoyerId(),
            'collection' => LibraryStatut::COLLECTION,
            'user_id' => UserContext::currentUserId(),
        ];
        if (CatalogSchema::hasMediaDomainColumn()) {
            $params['film_domain'] = MediaDomain::FILM;
        }

        return $params;
    }

    private function catalogFilmDomainWhereSql(): string
    {
        return CatalogSchema::hasMediaDomainColumn()
            ? ' AND o.media_domain = :film_domain'
            : '';
    }

    /** Jointure historique → bibliothèque (multi-comptes) ou chaîne vide (legacy). */
    private function historyJoinSql(): string
    {
        return '';
    }

    private function historyUserWhereSql(): string
    {
        if ($this->usesPerUserHistory()) {
            return 'h.user_id = ?';
        }

        return '1=1';
    }

    /** @return list<int> */
    private function historyUserParams(): array
    {
        if ($this->usesPerUserHistory()) {
            return [$this->currentUserId()];
        }

        return [];
    }
}
