<?php
/**
 * Fragments SQL réutilisables pour le catalogue et les listes BD.
 */

declare(strict_types=1);

namespace Moncine;

final class BdCatalogSql
{
    /** @var array<string, string> */
    private const SORT_COLUMNS = [
        'titre' => 'o.titre COLLATE FRENCH_NOCASE',
        'annee' => 'o.annee',
        'series' => 's.titre COLLATE FRENCH_NOCASE',
        'tome' => 'ob.tome_ordre',
        'scenariste' => 'ob.scenariste COLLATE FRENCH_NOCASE',
        'dessinateur' => 'ob.dessinateur COLLATE FRENCH_NOCASE',
        'editeur' => 'ob.editeur COLLATE FRENCH_NOCASE',
        'genre' => 'ob.genre COLLATE FRENCH_NOCASE',
        'kind' => 'ob.kind COLLATE NOCASE',
        'note' => 'note_max',
        'read_at' => 'derniere_lecture',
        'support' => 'b.support_physique COLLATE NOCASE',
        'added_at' => 'b.created_at',
    ];

    /** @return list<string> */
    public static function sortableColumns(): array
    {
        return [
            'titre', 'annee', 'series', 'tome', 'scenariste', 'dessinateur',
            'editeur', 'genre', 'kind', 'note', 'read_at', 'support', 'added_at',
        ];
    }

    public static function isValidSortColumn(string $sortBy): bool
    {
        return in_array($sortBy, self::sortableColumns(), true);
    }

    public static function sortOrderExpression(string $sortBy): string
    {
        return self::SORT_COLUMNS[$sortBy] ?? self::SORT_COLUMNS['titre'];
    }

    public static function selectBdRow(): string
    {
        return 'b.id, b.user_id, b.foyer_id, b.oeuvre_id, b.statut, b.support_physique, b.created_at,'
            . ' o.titre, o.titre_original, o.annee, o.poster_url, o.synopsis,'
            . ' ob.series_id, ob.kind, ob.tome_numero, ob.tome_ordre, ob.tome_label, ob.est_hors_serie,'
            . ' ob.scenariste, ob.dessinateur, ob.editeur, ob.genre,'
            . ' s.titre AS series_titre';
    }

    public static function selectBdHistoryExtras(): string
    {
        $noteWhere = RessentiNote::sqlValidNote('h');

        return ','
            . ' (SELECT MAX(h.date_vue) FROM historique h'
            . '  WHERE h.film_id = b.id AND h.user_id = :history_user_id) AS derniere_lecture,'
            . ' (SELECT MAX(h.note) FROM historique h'
            . '  WHERE h.film_id = b.id AND h.user_id = :history_user_id'
            . '    AND ' . $noteWhere . ') AS note_max';
    }

    public static function selectCatalogRow(): string
    {
        return 'o.id AS oeuvre_id, o.titre, o.titre_original, o.annee, o.poster_url, o.synopsis,'
            . ' ob.series_id, ob.kind, ob.tome_numero, ob.tome_ordre, ob.tome_label, ob.est_hors_serie,'
            . ' ob.scenariste, ob.dessinateur, ob.editeur, ob.genre,'
            . ' s.titre AS series_titre';
    }

    /**
     * @return array{0: string, 1: array<string, string>}
     */
    public static function bdSearchSqlConditions(string $query, string $paramPrefix): array
    {
        $pattern = LikePattern::containsFragment($query);
        $params = [
            $paramPrefix => $pattern,
            $paramPrefix . '_series' => $pattern,
            $paramPrefix . '_scen' => $pattern,
            $paramPrefix . '_dess' => $pattern,
            $paramPrefix . '_edit' => $pattern,
            $paramPrefix . '_genre' => $pattern,
        ];

        $sql = '('
            . 'LOWER(o.titre) LIKE LOWER(:' . $paramPrefix . ') ESCAPE \'\\\''
            . ' OR LOWER(s.titre) LIKE LOWER(:' . $paramPrefix . '_series) ESCAPE \'\\\''
            . ' OR LOWER(ob.scenariste) LIKE LOWER(:' . $paramPrefix . '_scen) ESCAPE \'\\\''
            . ' OR LOWER(ob.dessinateur) LIKE LOWER(:' . $paramPrefix . '_dess) ESCAPE \'\\\''
            . ' OR LOWER(ob.editeur) LIKE LOWER(:' . $paramPrefix . '_edit) ESCAPE \'\\\''
            . ' OR LOWER(ob.genre) LIKE LOWER(:' . $paramPrefix . '_genre) ESCAPE \'\\\''
            . ')';

        return [$sql, $params];
    }

    /** Tome possédé : support physique BD valide (album, relié, etc.). */
    public static function sqlTomePossessedCondition(string $bAlias): string
    {
        $keys = array_map(
            static fn (string $key): string => "'" . str_replace("'", "''", $key) . "'",
            array_keys(BdPhysicalSupport::choices())
        );

        return 'LOWER(TRIM(' . $bAlias . '.support_physique)) IN (' . implode(', ', $keys) . ')';
    }

    public static function seriesOrderClause(string $sortBy, string $sortDir): string
    {
        $dir = strtolower($sortDir) === 'desc' ? 'DESC' : 'ASC';

        return match ($sortBy) {
            'tomes' => 'possessed_tome_count ' . $dir . ', s.titre COLLATE FRENCH_NOCASE ASC',
            'kind' => 's.tags COLLATE NOCASE ' . $dir . ', s.titre COLLATE FRENCH_NOCASE ASC',
            'editeur' => 's.editeur COLLATE FRENCH_NOCASE ' . $dir,
            default => 's.titre COLLATE FRENCH_NOCASE ' . $dir,
        };
    }

    /**
     * @param array<string, int|string> $params
     * @return array<string, int|string>
     */
    public static function filterParamsForSql(string $sql, array $params): array
    {
        if (!preg_match_all('/:([a-zA-Z_][a-zA-Z0-9_]*)/', $sql, $matches)) {
            return [];
        }

        $filtered = [];
        foreach (array_unique($matches[1]) as $name) {
            if (array_key_exists($name, $params)) {
                $filtered[$name] = $params[$name];
            }
        }

        return $filtered;
    }
}
