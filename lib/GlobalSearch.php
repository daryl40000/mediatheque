<?php
/**
 * Recherche globale : bibliothèque personnelle + catalogue partagé (tous médias).
 */

declare(strict_types=1);

namespace Moncine;

use PDO;

final class GlobalSearch
{
    private PDO $db;

    public function __construct(?PDO $db = null)
    {
        $this->db = $db ?? Database::getInstance();
        SearchMatch::registerSqlFunction($this->db);
    }

    /**
     * @return array{library: list<array<string, mixed>>, catalog: list<array<string, mixed>>}
     */
    public function search(string $query, int $userId, int $foyerId, int $limitPerGroup = 10): array
    {
        $query = trim($query);
        if (mb_strlen($query) < 2 || $userId <= 0) {
            return ['library' => [], 'catalog' => []];
        }

        $limitPerGroup = max(1, min(30, $limitPerGroup));
        $library = $this->searchLibrary($query, $userId, $foyerId, $limitPerGroup);
        $libraryOeuvreIds = [];
        foreach ($library as $row) {
            $oeuvreId = (int) ($row['oeuvre_id'] ?? 0);
            if ($oeuvreId > 0) {
                $libraryOeuvreIds[$oeuvreId] = true;
            }
        }

        $catalog = $this->searchCatalog($query, $limitPerGroup, $libraryOeuvreIds);

        return [
            'library' => $library,
            'catalog' => $catalog,
        ];
    }

    /**
     * @param array<int, true> $excludeOeuvreIds
     * @return list<array<string, mixed>>
     */
    private function searchCatalog(string $query, int $limit, array $excludeOeuvreIds = []): array
    {
        $prefetchLimit = min(max($limit * 10, 80), 250);
        $params = $this->searchBindParams($query);
        $searchSql = $this->searchWhereSql();

        $sql = 'SELECT ' . $this->selectColumns()
            . ' FROM oeuvres o'
            . $this->joinSql()
            . ' WHERE ' . $searchSql
            . ' ORDER BY o.titre COLLATE FRENCH_NOCASE ASC'
            . ' LIMIT ' . $prefetchLimit;

        $stmt = $this->db->prepare($sql);
        $stmt->execute($params);
        $rows = $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];

        $rows = SearchMatch::filterRankLimit(
            $rows,
            $query,
            static fn (array $row): string => self::searchTextForRow($row),
            $limit + count($excludeOeuvreIds)
        );

        $out = [];
        foreach ($rows as $row) {
            $oeuvreId = (int) ($row['oeuvre_id'] ?? 0);
            if ($oeuvreId <= 0 || isset($excludeOeuvreIds[$oeuvreId])) {
                continue;
            }
            $out[] = $this->formatCatalogResult($row);
            if (count($out) >= $limit) {
                break;
            }
        }

        return $out;
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function searchLibrary(string $query, int $userId, int $foyerId, int $limit): array
    {
        $prefetchLimit = min(max($limit * 10, 80), 250);
        $params = array_merge($this->searchBindParams($query), [
            'collection' => LibraryStatut::COLLECTION,
            'wishlist' => LibraryStatut::WISHLIST,
            'foyer_id' => $foyerId,
            'user_id' => $userId,
        ]);
        $searchSql = $this->searchWhereSql();

        $sql = 'SELECT b.id AS bib_id, b.statut, ' . $this->selectColumns()
            . ' FROM bibliotheque b'
            . ' INNER JOIN oeuvres o ON o.id = b.oeuvre_id'
            . $this->joinSql()
            . ' WHERE ('
            . '   (b.statut = :collection AND b.foyer_id = :foyer_id)'
            . '   OR (b.statut = :wishlist AND b.user_id = :user_id)'
            . ' ) AND ' . $searchSql
            . ' ORDER BY o.titre COLLATE FRENCH_NOCASE ASC'
            . ' LIMIT ' . $prefetchLimit;

        $stmt = $this->db->prepare($sql);
        $stmt->execute($params);
        $rows = $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];

        $rows = SearchMatch::filterRankLimit(
            $rows,
            $query,
            static fn (array $row): string => self::searchTextForRow($row),
            $limit
        );

        return array_map(fn (array $row): array => $this->formatLibraryResult($row), $rows);
    }

    private function joinSql(): string
    {
        return ' LEFT JOIN oeuvre_jeu oj ON oj.oeuvre_id = o.id'
            . ' LEFT JOIN oeuvre_bd ob ON ob.oeuvre_id = o.id'
            . ' LEFT JOIN series s ON s.id = ob.series_id'
            . ' LEFT JOIN oeuvre_magazine om ON om.oeuvre_id = o.id'
            . ' LEFT JOIN series ms ON ms.id = om.series_id';
    }

    private function selectColumns(): string
    {
        return 'o.id AS oeuvre_id, o.titre, o.titre_original, o.realisateur, o.annee, o.poster_url,'
            . ' o.media_domain, oj.studio, oj.alternative_names,'
            . ' s.titre AS series_titre, om.numero AS magazine_numero, ms.titre AS magazine_series_titre';
    }

    /**
     * @return array<string, string>
     */
    private function searchBindParams(string $query): array
    {
        $pattern = SearchMatch::foldedContainsPattern($query);
        $prefix = SearchMatch::foldedPrefixPattern($query, 2);

        $params = [
            'global_q' => $pattern,
            'global_q_real' => $pattern,
            'global_q_orig' => $pattern,
            'global_q_studio' => $pattern,
            'global_q_series' => $pattern,
            'global_q_mag_series' => $pattern,
            'global_q_mag_num' => $pattern,
            'global_q_acronym' => $pattern,
        ];

        if ($prefix !== '') {
            $params['global_q_prefix'] = $prefix;
            $params['global_q_prefix_real'] = $prefix;
        } else {
            $params['global_q_prefix'] = $pattern;
            $params['global_q_prefix_real'] = $pattern;
        }

        return $params;
    }

    private function searchWhereSql(): string
    {
        $parts = [
            'fold_search(o.titre) LIKE :global_q ESCAPE \'\\\'',
            'fold_search(COALESCE(o.realisateur, \'\')) LIKE :global_q_real ESCAPE \'\\\'',
            'fold_search(COALESCE(o.titre_original, \'\')) LIKE :global_q_orig ESCAPE \'\\\'',
            'fold_search(COALESCE(oj.studio, \'\')) LIKE :global_q_studio ESCAPE \'\\\'',
            'fold_search(COALESCE(s.titre, \'\')) LIKE :global_q_series ESCAPE \'\\\'',
            'fold_search(COALESCE(ms.titre, \'\')) LIKE :global_q_mag_series ESCAPE \'\\\'',
            'fold_search(COALESCE(om.numero, \'\')) LIKE :global_q_mag_num ESCAPE \'\\\'',
            'fold_search(COALESCE(oj.alternative_names, \'\')) LIKE :global_q_acronym ESCAPE \'\\\'',
            'fold_search(o.titre) LIKE :global_q_prefix ESCAPE \'\\\'',
            'fold_search(COALESCE(o.realisateur, \'\')) LIKE :global_q_prefix_real ESCAPE \'\\\'',
        ];

        return '(' . implode(' OR ', $parts) . ')';
    }

    /** @param array<string, mixed> $row */
    private static function searchTextForRow(array $row): string
    {
        return trim(
            (string) ($row['titre'] ?? '') . ' '
            . (string) ($row['titre_original'] ?? '') . ' '
            . (string) ($row['realisateur'] ?? '') . ' '
            . (string) ($row['studio'] ?? '') . ' '
            . (string) ($row['series_titre'] ?? '') . ' '
            . (string) ($row['magazine_series_titre'] ?? '') . ' '
            . (string) ($row['magazine_numero'] ?? '') . ' '
            . (string) ($row['alternative_names'] ?? '')
        );
    }

    /** @param array<string, mixed> $row */
    private function formatCatalogResult(array $row): array
    {
        $domain = MediaDomain::normalize((string) ($row['media_domain'] ?? MediaDomain::FILM));
        $oeuvreId = (int) ($row['oeuvre_id'] ?? 0);

        return [
            'source' => 'catalog',
            'oeuvre_id' => $oeuvreId,
            'titre' => (string) ($row['titre'] ?? ''),
            'subtitle' => $this->subtitleForRow($row),
            'annee' => (int) ($row['annee'] ?? 0),
            'media_domain' => $domain,
            'media_label' => MediaDomain::label($domain),
            'display_label' => $this->displayLabel($row),
            'url' => View::catalogOeuvreDetailUrl($oeuvreId, $domain),
        ];
    }

    /** @param array<string, mixed> $row */
    private function formatLibraryResult(array $row): array
    {
        $domain = MediaDomain::normalize((string) ($row['media_domain'] ?? MediaDomain::FILM));
        $bibId = (int) ($row['bib_id'] ?? 0);
        $statut = (string) ($row['statut'] ?? '');

        return [
            'source' => 'library',
            'bib_id' => $bibId,
            'oeuvre_id' => (int) ($row['oeuvre_id'] ?? 0),
            'titre' => (string) ($row['titre'] ?? ''),
            'subtitle' => $this->subtitleForRow($row),
            'annee' => (int) ($row['annee'] ?? 0),
            'media_domain' => $domain,
            'media_label' => MediaDomain::label($domain),
            'display_label' => $this->displayLabel($row),
            'statut_label' => $statut === LibraryStatut::WISHLIST ? 'Envies' : 'Bibliothèque',
            'url' => View::libraryItemNavUrl($bibId, $domain),
        ];
    }

    /** @param array<string, mixed> $row */
    private function subtitleForRow(array $row): string
    {
        $domain = MediaDomain::normalize((string) ($row['media_domain'] ?? MediaDomain::FILM));

        return match ($domain) {
            MediaDomain::JEU => trim((string) ($row['studio'] ?? '')),
            MediaDomain::BD => trim((string) ($row['series_titre'] ?? '')),
            MediaDomain::MAGAZINE => trim(
                trim((string) ($row['magazine_series_titre'] ?? ''))
                . ' '
                . trim((string) ($row['magazine_numero'] ?? ''))
            ),
            default => trim((string) ($row['realisateur'] ?? '')),
        };
    }

    /** @param array<string, mixed> $row */
    private function displayLabel(array $row): string
    {
        $titre = trim((string) ($row['titre'] ?? ''));
        $subtitle = $this->subtitleForRow($row);
        $annee = (int) ($row['annee'] ?? 0);
        $parts = [];
        if ($subtitle !== '' && !str_contains(mb_strtolower($titre), mb_strtolower($subtitle))) {
            $parts[] = $subtitle;
        }
        if ($annee > 0) {
            $parts[] = (string) $annee;
        }

        return $parts === [] ? $titre : $titre . ' (' . implode(' · ', $parts) . ')';
    }
}
