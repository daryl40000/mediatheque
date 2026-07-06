<?php
/**
 * Fragments SQL réutilisables pour le catalogue et les listes jeux.
 */

declare(strict_types=1);

namespace Moncine;

final class GameCatalogSql
{
    /** @var array<string, string> */
    private const SORT_COLUMNS = [
        'titre' => 'o.titre COLLATE FRENCH_NOCASE',
        'annee' => 'o.annee',
        'platform' => 'oj.platform COLLATE NOCASE',
        'franchise' => 'oj.franchise COLLATE FRENCH_NOCASE',
        'studio' => 'oj.studio COLLATE FRENCH_NOCASE',
        'genre' => 'oj.genre COLLATE FRENCH_NOCASE',
        'note' => 'note_max',
        'finished_at' => 'derniere_completion',
        'steam_playtime' => 'playtime_total',
    ];

    /** @return list<string> */
    public static function sortableColumns(): array
    {
        $columns = ['titre', 'annee', 'platform', 'genre', 'studio', 'support', 'note', 'finished_at'];
        if (GameSchema::hasIgdbMetadataColumns()) {
            array_splice($columns, 3, 0, ['franchise']);
        }
        if (GameSteamStatsRepository::isAvailable() || GameSchema::hasManualPlaytimeColumn()) {
            $columns[] = 'steam_playtime';
        }

        return $columns;
    }

    public static function isValidSortColumn(string $sortBy): bool
    {
        return in_array($sortBy, self::sortableColumns(), true);
    }

    public static function sortOrderExpression(string $sortBy): string
    {
        if ($sortBy === 'support') {
            if (GameSchema::hasEditionColumns()) {
                return 'oj.physical_supports COLLATE NOCASE, oj.digital_stores COLLATE NOCASE, oj.is_digital';
            }

            return 'oj.is_digital';
        }

        if ($sortBy === 'finished_at' && !GameCompletionRepository::isAvailable()) {
            return self::SORT_COLUMNS['titre'];
        }

        if ($sortBy === 'franchise' && !GameSchema::hasIgdbMetadataColumns()) {
            return self::SORT_COLUMNS['titre'];
        }

        if ($sortBy === 'steam_playtime' && !GamePlaytime::isAvailable()) {
            return self::SORT_COLUMNS['titre'];
        }

        if ($sortBy === 'steam_playtime') {
            return GamePlaytime::totalMinutesSql();
        }

        return self::SORT_COLUMNS[$sortBy] ?? self::SORT_COLUMNS['titre'];
    }

    public static function selectGameRow(): string
    {
        $edition = GameSchema::hasEditionColumns()
            ? ', oj.physical_supports, oj.digital_stores'
            : '';
        $extension = GameRelations::selectColumns();
        $igdb = GameSchema::hasIgdbColumns() ? ', oj.igdb_id, oj.igdb_enriched_at' : '';
        $igdbMeta = GameSchema::hasIgdbMetadataColumns()
            ? ', oj.franchise, oj.game_mode, oj.theme, oj.alternative_names'
            : '';
        $linux = GameSchema::hasTestedOnLinuxColumn()
            ? ', b.tested_on_linux' . (GameSchema::hasLinuxNotSupportedColumn() ? ', b.linux_not_supported' : '')
            : '';
        $nonPretable = GameSchema::hasNonPretableColumn() ? ', b.non_pretable' : '';
        $ownedPlatforms = GameSchema::hasOwnedPlatformsColumn() ? ', b.owned_platforms' : '';
        $manualPlaytime = GameSchema::hasManualPlaytimeColumn() ? ', b.manual_playtime_minutes' : '';
        $platformsCol = GameSchema::hasPlatformsColumn() ? ', oj.platforms' : '';

        return 'b.id, b.user_id, b.foyer_id, b.oeuvre_id, b.statut, b.support_physique, b.created_at, b.saga_ordre,'
            . ' o.titre, o.titre_original, o.annee, o.poster_url, o.synopsis,'
            . ' oj.studio, oj.editeur, oj.genre, oj.platform, oj.is_digital' . $platformsCol . $edition . $extension . $igdb . $igdbMeta . $linux . $nonPretable . $ownedPlatforms . $manualPlaytime;
    }

    public static function selectGameHistoryExtras(): string
    {
        $noteWhere = RessentiNote::sqlValidNote('h');

        return ','
            . ' (SELECT MAX(h.date_vue) FROM historique h'
            . '  WHERE h.film_id = b.id AND h.user_id = :history_user_id) AS derniere_session,'
            . ' (SELECT MAX(h.note) FROM historique h'
            . '  WHERE h.film_id = b.id AND h.user_id = :history_user_id'
            . '    AND ' . $noteWhere . ') AS note_max'
            . GameCompletionRepository::selectListExtrasSql();
    }

    public static function selectCatalogRow(): string
    {
        $edition = GameSchema::hasEditionColumns()
            ? ', oj.physical_supports, oj.digital_stores'
            : '';
        $extension = GameRelations::selectColumns();
        $igdb = GameSchema::hasIgdbColumns() ? ', oj.igdb_id, oj.igdb_enriched_at' : '';
        $igdbMeta = GameSchema::hasIgdbMetadataColumns()
            ? ', oj.franchise, oj.game_mode, oj.theme, oj.alternative_names'
            : '';

        $platformsCol = GameSchema::hasPlatformsColumn() ? ', oj.platforms' : '';

        return 'o.id AS oeuvre_id, o.titre, o.titre_original, o.annee, o.poster_url, o.synopsis,'
            . ' oj.studio, oj.editeur, oj.genre, oj.platform, oj.is_digital' . $platformsCol . $edition . $extension . $igdb . $igdbMeta;
    }

    /**
     * @return array{0: string, 1: array<string, string>}
     */
    public static function gameSearchSqlConditions(
        string $query,
        bool $includeGenre,
        bool $includePrefix,
        string $titleParam = 'q_titre',
    ): array {
        $pattern = SearchMatch::foldedContainsPattern($query);
        $conditions = [
            'fold_search(o.titre) LIKE :' . $titleParam . ' ESCAPE \'\\\'',
            'fold_search(COALESCE(oj.studio, \'\')) LIKE :q_studio ESCAPE \'\\\'',
        ];
        $params = [
            $titleParam => $pattern,
            'q_studio' => $pattern,
        ];

        if ($includeGenre) {
            $conditions[] = 'fold_search(COALESCE(oj.genre, \'\')) LIKE :q_genre ESCAPE \'\\\'';
            $params['q_genre'] = $pattern;
        }

        if (GameSchema::hasIgdbMetadataColumns()) {
            $conditions[] = 'fold_search(COALESCE(oj.alternative_names, \'\')) LIKE :q_acronym ESCAPE \'\\\'';
            $params['q_acronym'] = $pattern;
        }

        if ($includePrefix) {
            $prefixPattern = SearchMatch::foldedPrefixPattern($query, 2);
            if ($prefixPattern !== '') {
                $conditions[] = 'fold_search(o.titre) LIKE :q_prefix ESCAPE \'\\\'';
                $conditions[] = 'fold_search(COALESCE(oj.studio, \'\')) LIKE :q_prefix_studio ESCAPE \'\\\'';
                $params['q_prefix'] = $prefixPattern;
                $params['q_prefix_studio'] = $prefixPattern;

                if (GameSchema::hasIgdbMetadataColumns()) {
                    $conditions[] = 'fold_search(COALESCE(oj.alternative_names, \'\')) LIKE :q_prefix_acronym ESCAPE \'\\\'';
                    $params['q_prefix_acronym'] = $prefixPattern;
                }
            }
        }

        return ['(' . implode(' OR ', $conditions) . ')', $params];
    }

    public static function igdbMetadataUpdateSet(): string
    {
        return GameSchema::hasIgdbMetadataColumns()
            ? ', franchise = ?, game_mode = ?, theme = ?, alternative_names = ?'
            : '';
    }

    /**
     * @param array<string, mixed> $data
     * @return list<string>
     */
    public static function igdbMetadataWriteParams(array $data): array
    {
        if (!GameSchema::hasIgdbMetadataColumns()) {
            return [];
        }

        return [
            trim((string) ($data['franchise'] ?? '')),
            GameGenre::normalizeInput((string) ($data['game_mode'] ?? '')),
            GameGenre::normalizeInput((string) ($data['theme'] ?? '')),
            GameGenre::normalizeInput((string) ($data['alternative_names'] ?? '')),
        ];
    }
}
