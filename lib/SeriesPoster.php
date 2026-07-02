<?php
/**
 * Affiches de séries (BD, magazines) : repli catalogue et chemins référencés.
 */

declare(strict_types=1);

namespace Moncine;

use PDO;

final class SeriesPoster
{
    /**
     * Chemin web affichable : logo série, sinon tome/numéro 1, sinon première couverture catalogue.
     *
     * @param array<string, mixed> $series
     */
    public static function resolveWebPath(array $series): string
    {
        foreach (self::candidateWebPaths($series) as $url) {
            if (self::isDisplayableWebPath($url)) {
                return $url;
            }
        }

        return '';
    }

    /**
     * Chemins à essayer dans l’ordre (sans vérifier l’existence du fichier).
     *
     * @param array<string, mixed> $series
     * @return list<string>
     */
    public static function candidateWebPaths(array $series): array
    {
        $candidates = [];

        foreach ([
            trim((string) ($series['poster_url'] ?? '')),
            trim((string) ($series['first_volume_poster_url'] ?? '')),
            trim((string) ($series['latest_poster_url'] ?? '')),
        ] as $url) {
            if ($url !== '' && !in_array($url, $candidates, true)) {
                $candidates[] = $url;
            }
        }

        return $candidates;
    }

    public static function isDisplayableWebPath(string $url): bool
    {
        $url = trim($url);
        if ($url === '') {
            return false;
        }

        if (PosterStorage::isLocalWebPath($url)) {
            $path = PosterStorage::filesystemPathFromWeb($url);

            return $path !== null && is_file($path);
        }

        return SecureUrl::sanitizePosterUrl($url) !== '';
    }

    /**
     * Complète une fiche série avec la couverture du tome/numéro 1 si besoin.
     *
     * @param array<string, mixed> $series
     * @return array<string, mixed>
     */
    public static function enrichSeries(array $series): array
    {
        $seriesId = (int) ($series['id'] ?? 0);
        $domain = MediaDomain::normalize((string) ($series['media_domain'] ?? ''));

        $needsFirstVolume = trim((string) ($series['first_volume_poster_url'] ?? '')) === ''
            || !self::isDisplayableWebPath((string) ($series['first_volume_poster_url'] ?? ''));

        if ($seriesId > 0 && $needsFirstVolume) {
            $first = self::findCatalogCoverPosterUrl($seriesId, $domain);
            if ($first !== '') {
                $series['first_volume_poster_url'] = $first;
            }
        }

        $series['effective_poster_url'] = self::resolveWebPath($series);

        return $series;
    }

    /** Couverture du tome 1 (BD) ou numéro 1 (magazine), puis repli sur le premier volume catalogue. */
    public static function findCatalogCoverPosterUrl(int $seriesId, string $mediaDomain): string
    {
        if ($seriesId <= 0 || !SeriesRepository::tableExists()) {
            return '';
        }

        $domain = MediaDomain::normalize($mediaDomain);
        $db = Database::getInstance();

        if ($domain === MediaDomain::BD) {
            $preferred = self::fetchBdCoverPosterUrl(
                $db,
                'ob.series_id = ? AND ob.tome_numero = 1 AND IFNULL(ob.est_hors_serie, 0) = 0',
                [$seriesId]
            );
            if ($preferred !== '') {
                return $preferred;
            }

            return self::fetchBdCoverPosterUrl(
                $db,
                'ob.series_id = ? AND IFNULL(ob.est_hors_serie, 0) = 0',
                [$seriesId],
                'ob.tome_ordre ASC, ob.tome_numero ASC'
            );
        }

        if ($domain === MediaDomain::MAGAZINE) {
            $preferred = self::fetchMagazineCoverPosterUrl(
                $db,
                'om.series_id = ? AND IFNULL(om.est_hors_serie, 0) = 0
                 AND (om.numero_ordre = 1 OR TRIM(om.numero) = \'1\')',
                [$seriesId]
            );
            if ($preferred !== '') {
                return $preferred;
            }

            return self::fetchMagazineCoverPosterUrl(
                $db,
                'om.series_id = ? AND IFNULL(om.est_hors_serie, 0) = 0',
                [$seriesId],
                'om.numero_ordre ASC, om.numero ASC'
            );
        }

        return '';
    }

    /**
     * Sous-requête SQL : couverture du tome/numéro 1, sinon premier volume catalogue.
     */
    public static function sqlFirstVolumePosterSubquery(string $mediaDomain): string
    {
        $domain = MediaDomain::normalize($mediaDomain);

        if ($domain === MediaDomain::BD) {
            return 'COALESCE(
                    (SELECT o_pref.poster_url
                        FROM oeuvre_bd ob_pref
                        INNER JOIN oeuvres o_pref ON o_pref.id = ob_pref.oeuvre_id
                            AND o_pref.media_domain = :domain_oeuvre
                        WHERE ob_pref.series_id = s.id
                          AND ob_pref.tome_numero = 1
                          AND IFNULL(ob_pref.est_hors_serie, 0) = 0
                          AND TRIM(o_pref.poster_url) != \'\'
                        LIMIT 1),
                    (SELECT o_first.poster_url
                        FROM oeuvre_bd ob_first
                        INNER JOIN oeuvres o_first ON o_first.id = ob_first.oeuvre_id
                            AND o_first.media_domain = :domain_oeuvre
                        WHERE ob_first.series_id = s.id
                          AND IFNULL(ob_first.est_hors_serie, 0) = 0
                          AND TRIM(o_first.poster_url) != \'\'
                        ORDER BY ob_first.tome_ordre ASC, ob_first.tome_numero ASC
                        LIMIT 1)
                )';
        }

        if ($domain === MediaDomain::MAGAZINE) {
            return 'COALESCE(
                    (SELECT o_pref.poster_url
                        FROM oeuvre_magazine om_pref
                        INNER JOIN oeuvres o_pref ON o_pref.id = om_pref.oeuvre_id
                            AND o_pref.media_domain = :domain_oeuvre
                        WHERE om_pref.series_id = s.id
                          AND IFNULL(om_pref.est_hors_serie, 0) = 0
                          AND (om_pref.numero_ordre = 1 OR TRIM(om_pref.numero) = \'1\')
                          AND TRIM(o_pref.poster_url) != \'\'
                        LIMIT 1),
                    (SELECT o_first.poster_url
                        FROM oeuvre_magazine om_first
                        INNER JOIN oeuvres o_first ON o_first.id = om_first.oeuvre_id
                            AND o_first.media_domain = :domain_oeuvre
                        WHERE om_first.series_id = s.id
                          AND IFNULL(om_first.est_hors_serie, 0) = 0
                          AND TRIM(o_first.poster_url) != \'\'
                        ORDER BY om_first.numero_ordre ASC, om_first.numero ASC
                        LIMIT 1)
                )';
        }

        return 'NULL';
    }

    /**
     * Chemins absolus des affiches référencées (œuvres catalogue + logos séries).
     *
     * @return array<string, true>
     */
    public static function referencedFilesystemPaths(PDO $db): array
    {
        $referenced = [];

        $oeuvreStmt = $db->query(
            "SELECT poster_url FROM oeuvres WHERE TRIM(poster_url) LIKE '/posters/%'"
        );
        foreach ($oeuvreStmt->fetchAll(PDO::FETCH_ASSOC) ?: [] as $row) {
            self::markReferencedPath($referenced, (string) ($row['poster_url'] ?? ''));
        }

        if (SeriesRepository::tableExists()) {
            $seriesStmt = $db->query(
                "SELECT poster_url FROM series WHERE TRIM(poster_url) LIKE '/posters/%'"
            );
            foreach ($seriesStmt->fetchAll(PDO::FETCH_ASSOC) ?: [] as $row) {
                self::markReferencedPath($referenced, (string) ($row['poster_url'] ?? ''));
            }
        }

        return $referenced;
    }

    /**
     * @param array<int, scalar> $params
     */
    private static function fetchBdCoverPosterUrl(
        PDO $db,
        string $whereSql,
        array $params,
        ?string $orderBy = null
    ): string {
        $sql = 'SELECT o.poster_url
                FROM oeuvre_bd ob
                INNER JOIN oeuvres o ON o.id = ob.oeuvre_id AND o.media_domain = ?
                WHERE ' . $whereSql . '
                  AND TRIM(o.poster_url) != \'\'';
        if ($orderBy !== null) {
            $sql .= ' ORDER BY ' . $orderBy;
        }
        $sql .= ' LIMIT 1';

        $stmt = $db->prepare($sql);
        $stmt->execute(array_merge([MediaDomain::BD], $params));
        $row = $stmt->fetch(PDO::FETCH_ASSOC);

        return $row !== false ? trim((string) ($row['poster_url'] ?? '')) : '';
    }

    /**
     * @param array<int, scalar> $params
     */
    private static function fetchMagazineCoverPosterUrl(
        PDO $db,
        string $whereSql,
        array $params,
        ?string $orderBy = null
    ): string {
        $sql = 'SELECT o.poster_url
                FROM oeuvre_magazine om
                INNER JOIN oeuvres o ON o.id = om.oeuvre_id AND o.media_domain = ?
                WHERE ' . $whereSql . '
                  AND TRIM(o.poster_url) != \'\'';
        if ($orderBy !== null) {
            $sql .= ' ORDER BY ' . $orderBy;
        }
        $sql .= ' LIMIT 1';

        $stmt = $db->prepare($sql);
        $stmt->execute(array_merge([MediaDomain::MAGAZINE], $params));
        $row = $stmt->fetch(PDO::FETCH_ASSOC);

        return $row !== false ? trim((string) ($row['poster_url'] ?? '')) : '';
    }

    /**
     * @param array<string, true> $referenced
     */
    private static function markReferencedPath(array &$referenced, string $posterUrl): void
    {
        $path = PosterStorage::filesystemPathFromWeb(trim($posterUrl));
        if ($path !== null && is_file($path)) {
            $referenced[realpath($path) ?: $path] = true;
        }
    }
}
