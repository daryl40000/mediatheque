<?php
/**
 * Fragments SQL réutilisables pour listes et enrichissement films (catalogue).
 */

declare(strict_types=1);

namespace Moncine;

final class FilmCatalogSql
{
    /** @var array<string, string> */
    private const COLLECTION_SORT_COLUMNS = [
        'titre' => 'o.titre COLLATE FRENCH_NOCASE',
        'annee' => 'o.annee',
        'realisateur' => 'o.realisateur COLLATE FRENCH_NOCASE',
        'duree_min' => 'o.duree_min',
        'styles' => 'o.styles COLLATE FRENCH_NOCASE',
        'support_physique' => 'b.support_physique COLLATE FRENCH_NOCASE',
        'note' => 'note_max',
        'derniere_vue' => 'derniere_vue',
    ];

    /** @return list<string> */
    public static function sortableColumns(): array
    {
        return array_keys(self::COLLECTION_SORT_COLUMNS);
    }

    public static function isValidSortColumn(string $sortBy): bool
    {
        return isset(self::COLLECTION_SORT_COLUMNS[$sortBy]);
    }

    public static function sortOrderExpression(string $sortBy): string
    {
        $key = \Moncine\Repository\SortColumnHelper::resolve($sortBy, self::COLLECTION_SORT_COLUMNS, 'titre');

        return self::COLLECTION_SORT_COLUMNS[$key];
    }

    /**
     * Clause WHERE recherche collection (titre, acteurs, styles, saga…).
     *
     * @param array<string, mixed> $params
     */
    public static function collectionSearchWhereSql(string $searchQuery, array &$params): string
    {
        $searchQuery = trim($searchQuery);
        if ($searchQuery === '') {
            return '';
        }

        $pattern = SearchMatch::foldedContainsPattern($searchQuery);
        $params['collection_q'] = $pattern;

        $fields = [
            'o.titre',
            'o.titre_original',
            'o.realisateur',
            'o.acteur_1',
            'o.acteur_2',
            'o.acteur_3',
            'o.styles',
            'b.saga',
        ];
        if (CatalogSchema::hasOeuvreSagaColumns()) {
            $fields[] = 'o.saga';
        }

        $parts = [];
        foreach ($fields as $field) {
            $parts[] = 'fold_search(' . $field . ') LIKE :collection_q ESCAPE \'\\\'';
        }

        return '(' . implode(' OR ', $parts) . ')';
    }

    /** Sous-select note max / dernière vue pour les listes collection. */
    public static function collectionRatingSelectSql(): string
    {
        $noteWhere = RessentiNote::sqlValidNote('h');

        return ',
                (SELECT MAX(h.date_vue) FROM historique h WHERE h.film_id = b.id AND h.user_id = :history_user_id) AS derniere_vue,
                (SELECT MAX(h.note) FROM historique h
                 WHERE h.film_id = b.id AND h.user_id = :history_user_id AND ' . $noteWhere . ') AS note_max';
    }

    /** @param array<string, int|string|float|null> $params */
    public static function appendCollectionRatingParams(array &$params, int $userId): void
    {
        $params['history_user_id'] = $userId;
    }

    /** Films encore à enrichir (pas d’essai OMDB, ou affiche/synopsis manquants). */
    public static function enrichmentPendingSql(string $alias): string
    {
        $p = $alias . '.';

        return $p . 'omdb_enriched_at IS NULL
            OR (
                ' . $p . 'omdb_enriched_at IS NOT NULL
                AND (' . $p . 'poster_url IS NULL OR ' . $p . 'poster_url = "")
                AND (' . $p . 'synopsis IS NULL OR ' . $p . 'synopsis = "")
            )';
    }
}
