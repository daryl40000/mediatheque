<?php
/**
 * Schéma catalogue Moncine : œuvres (catalogue) + bibliothèque (relation utilisateur / foyer).
 */

declare(strict_types=1);

namespace Moncine;

final class CatalogSchema
{
    /** Champs stockés dans la table bibliotheque (exemplaire personnel). */
    public const LIBRARY_FIELDS = [
        'support_physique',
        'format_image',
        'format_son',
        'saga',
        'saga_ordre',
        'saison_numero',
        'saison_label',
        'ean',
    ];

    /** Champs stockés dans la table oeuvres (catalogue partagé). */
    public const OEUVRE_FIELDS = [
        'titre',
        'titre_original',
        'realisateur',
        'duree_min',
        'styles',
        'annee',
        'nationalite',
        'tmdb_id',
        'tmdb_media_type',
        'tmdb_tv_kind',
        'realisateur_tmdb_id',
        'acteur_1',
        'acteur_1_tmdb_id',
        'acteur_2',
        'acteur_2_tmdb_id',
        'acteur_3',
        'acteur_3_tmdb_id',
        'poster_url',
        'synopsis',
        'moncine_kind',
        'omdb_imdb_id',
        'omdb_enriched_at',
    ];

    public const JOIN = 'bibliotheque b INNER JOIN oeuvres o ON o.id = b.oeuvre_id';

    public static function selectFilmRow(): string
    {
        $oeuvre = [];
        foreach (self::OEUVRE_FIELDS as $field) {
            $oeuvre[] = 'o.' . $field;
        }

        return 'b.id, b.user_id, b.foyer_id, b.oeuvre_id, b.statut, b.support_physique, b.format_image, b.format_son, '
            . 'b.saga, b.saga_ordre, b.saison_numero, b.saison_label, b.ean, b.created_at, '
            . implode(', ', $oeuvre);
    }

    /**
     * Filtre bibliothèque : collection partagée du foyer, envies personnelles.
     *
     * @return array{0: string, 1: array<string, int|string>}
     */
    public static function libraryFilter(int $foyerId, int $userId, ?string $statut): array
    {
        if ($statut === LibraryStatut::WISHLIST) {
            return [
                'b.user_id = :catalog_user_id AND b.statut = :catalog_statut',
                [
                    'catalog_user_id' => $userId,
                    'catalog_statut' => LibraryStatut::WISHLIST,
                ],
            ];
        }

        if ($statut === LibraryStatut::COLLECTION) {
            return [
                'b.foyer_id = :catalog_foyer_id AND b.statut = :catalog_statut',
                [
                    'catalog_foyer_id' => $foyerId,
                    'catalog_statut' => LibraryStatut::COLLECTION,
                ],
            ];
        }

        return [
            '((b.foyer_id = :catalog_foyer_id AND b.statut = :catalog_collection)
              OR (b.user_id = :catalog_user_id AND b.statut = :catalog_wishlist))',
            [
                'catalog_foyer_id' => $foyerId,
                'catalog_user_id' => $userId,
                'catalog_collection' => LibraryStatut::COLLECTION,
                'catalog_wishlist' => LibraryStatut::WISHLIST,
            ],
        ];
    }

    /**
     * @deprecated Utiliser libraryFilter().
     * @return array{0: string, 1: array<string, int|string>}
     */
    public static function userFilter(int $userId, ?string $statut): array
    {
        return self::libraryFilter(UserContext::currentFoyerId(), $userId, $statut);
    }

    public static function usesCatalogTables(\PDO $db): bool
    {
        $stmt = $db->query(
            "SELECT 1 FROM sqlite_master WHERE type = 'table' AND name = 'bibliotheque' LIMIT 1"
        );

        return (bool) $stmt->fetchColumn();
    }

    public static function hasMediaDomainColumn(?PDO $db = null): bool
    {
        $db ??= Database::getInstance();
        if (!self::usesCatalogTables($db)) {
            return false;
        }
        $stmt = $db->query(
            "SELECT 1 FROM pragma_table_info('oeuvres') WHERE name = 'media_domain' LIMIT 1"
        );

        return (bool) $stmt->fetchColumn();
    }

    /**
     * Filtre SQL sur le domaine média actif (alias œuvres « o » par défaut).
     *
     * @param list<string> $whereParts
     * @param array<string, mixed> $params
     */
    public static function applyMediaDomainFilter(array &$whereParts, array &$params, string $oeuvreAlias = 'o'): void
    {
        if (!self::hasMediaDomainColumn()) {
            return;
        }

        $param = 'catalog_media_domain';
        $params[$param] = MediaContext::current();
        $col = rtrim($oeuvreAlias, '.') . '.media_domain';

        $whereParts[] = $col . ' = :' . $param;
    }

    /**
     * Fragment « AND o.media_domain = :catalog_media_domain » pour requêtes déjà construites.
     *
     * @param array<string, mixed> $params
     */
    public static function sqlMediaDomainAnd(string $oeuvreAlias, array &$params): string
    {
        if (!self::hasMediaDomainColumn()) {
            return '';
        }

        $parts = [];
        self::applyMediaDomainFilter($parts, $params, $oeuvreAlias);

        return $parts === [] ? '' : ' AND ' . $parts[0];
    }

    public static function usesFoyerModel(\PDO $db): bool
    {
        if (!self::usesCatalogTables($db)) {
            return false;
        }
        $stmt = $db->query(
            "SELECT 1 FROM pragma_table_info('bibliotheque') WHERE name = 'foyer_id' LIMIT 1"
        );

        return (bool) $stmt->fetchColumn();
    }

    /**
     * Moyenne des meilleures notes de chaque membre du foyer pour un film.
     */
    public static function foyerAverageNoteSubquery(
        string $filmIdColumn = 'b.id',
        string $foyerParam = ':foyer_rating_id'
    ): string {
        return '(SELECT ROUND(AVG(member_note.best_note), 2)
            FROM (
                SELECT MAX(h.note) AS best_note
                FROM historique h
                INNER JOIN utilisateurs u ON u.id = h.user_id
                WHERE h.film_id = ' . $filmIdColumn . '
                  AND u.foyer_id = ' . $foyerParam . '
                  AND h.note IS NOT NULL AND h.note >= 1 AND h.note <= 10
                GROUP BY h.user_id
            ) member_note) AS note_foyer_moy';
    }

    /**
     * @param array<string, mixed> $row
     * @return array<string, mixed>
     */
    public static function normalizeFilmRow(array $row): array
    {
        if (array_key_exists('ean', $row)) {
            $row['ean'] = OeuvreEanRepository::normalizeEan((string) ($row['ean'] ?? ''));
        }

        return $row;
    }
}
