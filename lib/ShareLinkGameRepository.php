<?php
/**
 * Lecture des jeux exposés via un lien de partage (sans session).
 */

declare(strict_types=1);

namespace Moncine;

use PDO;

final class ShareLinkGameRepository
{
    private PDO $db;

    /** @var array<string, string> */
    private const SORT_COLUMNS = [
        'titre' => 'o.titre COLLATE FRENCH_NOCASE',
        'annee' => 'o.annee',
        'platform' => 'oj.platform COLLATE NOCASE',
        'studio' => 'oj.studio COLLATE FRENCH_NOCASE',
        'genre' => 'oj.genre COLLATE FRENCH_NOCASE',
        'note' => 'note_max',
        'finished_at' => 'derniere_completion',
    ];

    public function __construct()
    {
        $this->db = Database::getInstance();
    }

    /**
     * @param array<string, mixed> $link
     * @return list<array<string, mixed>>
     */
    public function findAllForLink(
        array $link,
        string $sortBy = 'titre',
        string $sortDir = 'asc',
        string $searchQuery = '',
        ?GameListFilter $filter = null
    ): array {
        if (!GameRepository::isAvailable()) {
            return [];
        }
        if (!GameRepository::isValidSortColumn($sortBy)) {
            $sortBy = 'titre';
        }
        $direction = strtolower($sortDir) === 'desc' ? 'DESC' : 'ASC';
        $orderExpr = self::sortOrderExpression($sortBy);
        $finishedAtSort = $sortBy === 'finished_at' && GameCompletionRepository::isAvailable();

        [$userWhere, $params] = $this->libraryFilterForLink($link);
        $params['share_game_domain'] = MediaDomain::JEU;
        $params['history_user_id'] = (int) ($link['user_id'] ?? 0);
        $params['foyer_id_rating'] = (int) ($link['foyer_id'] ?? 0);

        $whereParts = [
            'o.media_domain = :share_game_domain',
            $userWhere,
        ];

        $searchWhere = $this->searchWhereSql($searchQuery, $params);
        if ($searchWhere !== '') {
            $whereParts[] = $searchWhere;
        }

        ($filter ?? GameListFilter::empty())->applyToSql($whereParts, $params);

        $sql = 'SELECT ' . self::selectGameRow() . self::selectGameHistoryExtras()
            . ' FROM bibliotheque b'
            . ' INNER JOIN oeuvres o ON o.id = b.oeuvre_id'
            . ' INNER JOIN oeuvre_jeu oj ON oj.oeuvre_id = o.id'
            . ' WHERE ' . implode(' AND ', $whereParts);
        if ($finishedAtSort) {
            $sql .= ' ORDER BY derniere_completion IS NULL ASC, derniere_completion ' . $direction;
        } else {
            $sql .= ' ORDER BY ' . $orderExpr . ' ' . $direction;
        }
        if ($sortBy !== 'titre') {
            $sql .= ', o.titre COLLATE FRENCH_NOCASE ASC';
        }

        $stmt = $this->db->prepare($sql);
        $stmt->execute($params);
        $rows = $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];

        return array_map([GameRowMapper::class, 'hydrateGameRow'], $rows);
    }

    /**
     * @param array<string, mixed> $link
     * @return array<string, mixed>|null
     */
    public function findByIdForLink(array $link, int $bibliothequeId): ?array
    {
        if (!GameRepository::isAvailable() || $bibliothequeId <= 0) {
            return null;
        }

        [$userWhere, $params] = $this->libraryFilterForLink($link);
        $params['share_game_id'] = $bibliothequeId;
        $params['share_game_domain'] = MediaDomain::JEU;

        $sql = 'SELECT ' . self::selectGameRow()
            . ' FROM bibliotheque b'
            . ' INNER JOIN oeuvres o ON o.id = b.oeuvre_id'
            . ' INNER JOIN oeuvre_jeu oj ON oj.oeuvre_id = o.id'
            . ' WHERE ' . $userWhere
            . ' AND b.id = :share_game_id'
            . ' AND o.media_domain = :share_game_domain'
            . ' LIMIT 1';

        $stmt = $this->db->prepare($sql);
        $stmt->execute($params);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);

        return $row !== false ? GameRowMapper::hydrateGameRow($row) : null;
    }

    /**
     * Jeu partagé identifié par l’id catalogue (œuvre), dans le périmètre du lien.
     *
     * @param array<string, mixed> $link
     * @return array<string, mixed>|null
     */
    public function findByOeuvreIdForLink(array $link, int $oeuvreId): ?array
    {
        if (!GameRepository::isAvailable() || $oeuvreId <= 0) {
            return null;
        }

        [$userWhere, $params] = $this->libraryFilterForLink($link);
        $params['share_oeuvre_id'] = $oeuvreId;
        $params['share_game_domain'] = MediaDomain::JEU;

        $sql = 'SELECT ' . self::selectGameRow()
            . ' FROM bibliotheque b'
            . ' INNER JOIN oeuvres o ON o.id = b.oeuvre_id'
            . ' INNER JOIN oeuvre_jeu oj ON oj.oeuvre_id = o.id'
            . ' WHERE ' . $userWhere
            . ' AND o.id = :share_oeuvre_id'
            . ' AND o.media_domain = :share_game_domain'
            . ' LIMIT 1';

        $stmt = $this->db->prepare($sql);
        $stmt->execute($params);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);

        return $row !== false ? GameRowMapper::hydrateGameRow($row) : null;
    }

    /**
     * Extensions d’un jeu de base visibles via le lien de partage.
     *
     * @param array<string, mixed> $link
     * @return list<array<string, mixed>>
     */
    public function listExtensionsForBaseGameForLink(array $link, int $baseOeuvreId): array
    {
        if (!GameRepository::isAvailable() || !GameSchema::hasExtensionColumns() || $baseOeuvreId <= 0) {
            return [];
        }

        return $this->listRelatedForLink($link, 'is_extension', 'base_game_oeuvre_id', $baseOeuvreId);
    }

    /**
     * Remakes visibles via le lien de partage.
     *
     * @param array<string, mixed> $link
     * @return list<array<string, mixed>>
     */
    public function listRemakesForOriginalGameForLink(array $link, int $originalOeuvreId): array
    {
        if (!GameRepository::isAvailable() || !GameSchema::hasRemakeColumns() || $originalOeuvreId <= 0) {
            return [];
        }

        return $this->listRelatedForLink($link, 'is_remake', 'original_game_oeuvre_id', $originalOeuvreId);
    }

    /**
     * Jeu catalogue pour affichage visiteur (jaquette sans lien si absent du partage).
     *
     * @param array<string, mixed> $link
     * @return array<string, mixed>|null
     */
    public function resolveCatalogParentForLink(
        array $link,
        int $parentOeuvreId,
        string $rawToken,
        array $listContext = []
    ): ?array {
        if ($parentOeuvreId <= 0) {
            return null;
        }

        $inShare = $this->findByOeuvreIdForLink($link, $parentOeuvreId);
        if ($inShare !== null) {
            $bibId = (int) ($inShare['id'] ?? 0);

            return [
                'oeuvre_id' => $parentOeuvreId,
                'titre' => (string) ($inShare['display_titre'] ?? $inShare['titre'] ?? ''),
                'poster_url' => $inShare['poster_url'] ?? null,
                'annee' => (int) ($inShare['annee'] ?? 0),
                'library_url' => $bibId > 0
                    ? ShareLinkService::gameUrl($rawToken, $bibId, $listContext)
                    : '',
            ];
        }

        $catalog = (new GameRepository())->findCatalogByOeuvreId($parentOeuvreId);
        if ($catalog === null) {
            return null;
        }

        return [
            'oeuvre_id' => $parentOeuvreId,
            'titre' => (string) ($catalog['display_titre'] ?? $catalog['titre'] ?? ''),
            'poster_url' => $catalog['poster_url'] ?? null,
            'annee' => (int) ($catalog['annee'] ?? 0),
            'library_url' => '',
        ];
    }

    /**
     * @param array<string, mixed> $link
     * @return list<array<string, mixed>>
     */
    private function listRelatedForLink(
        array $link,
        string $flagColumn,
        string $fkColumn,
        int $parentOeuvreId
    ): array {
        [$userWhere, $params] = $this->libraryFilterForLink($link);
        $params['share_parent_oeuvre_id'] = $parentOeuvreId;
        $params['share_game_domain'] = MediaDomain::JEU;

        $sql = 'SELECT b.id AS bib_id, o.id AS oeuvre_id, o.titre, o.titre_original, o.annee, o.poster_url, oj.platform'
            . ' FROM bibliotheque b'
            . ' INNER JOIN oeuvres o ON o.id = b.oeuvre_id'
            . ' INNER JOIN oeuvre_jeu oj ON oj.oeuvre_id = o.id'
            . ' WHERE o.media_domain = :share_game_domain'
            . ' AND oj.' . $flagColumn . ' = 1'
            . ' AND oj.' . $fkColumn . ' = :share_parent_oeuvre_id'
            . ' AND ' . $userWhere
            . ' ORDER BY o.annee ASC, o.titre COLLATE FRENCH_NOCASE ASC';

        $stmt = $this->db->prepare($sql);
        $stmt->execute($params);

        $out = [];
        foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $row) {
            $out[] = GameRowMapper::hydrateLinkedLibraryGameRow($row);
        }

        return $out;
    }

    /**
     * @param array<string, mixed> $link
     * @return array{0: string, 1: array<string, int|string>}
     */
    private function libraryFilterForLink(array $link): array
    {
        $scope = ShareLinkScope::normalize((string) ($link['scope'] ?? ''));
        if ($scope === ShareLinkScope::WISHLIST) {
            return [
                'b.user_id = :share_user_id AND b.statut = :share_statut',
                [
                    'share_user_id' => (int) ($link['user_id'] ?? 0),
                    'share_statut' => LibraryStatut::WISHLIST,
                ],
            ];
        }

        return [
            'b.foyer_id = :share_foyer_id AND b.statut = :share_statut',
            [
                'share_foyer_id' => (int) ($link['foyer_id'] ?? 0),
                'share_statut' => LibraryStatut::COLLECTION,
            ],
        ];
    }

    /** @param array<string, int|string> $params */
    private function searchWhereSql(string $searchQuery, array &$params): string
    {
        $pattern = SearchMatch::foldedContainsPattern($searchQuery);
        if ($pattern === '') {
            return '';
        }

        $params['share_q_titre'] = $pattern;
        $params['share_q_studio'] = $pattern;
        $params['share_q_genre'] = $pattern;

        $parts = [
            'fold_search(o.titre) LIKE :share_q_titre ESCAPE \'\\\'',
            'fold_search(COALESCE(oj.studio, \'\')) LIKE :share_q_studio ESCAPE \'\\\'',
            'fold_search(COALESCE(oj.genre, \'\')) LIKE :share_q_genre ESCAPE \'\\\'',
        ];

        if (GameSchema::hasIgdbMetadataColumns()) {
            $params['share_q_acronym'] = $pattern;
            $parts[] = 'fold_search(COALESCE(oj.alternative_names, \'\')) LIKE :share_q_acronym ESCAPE \'\\\'';
        }

        return '(' . implode(' OR ', $parts) . ')';
    }

    private static function sortOrderExpression(string $sortBy): string
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

        return self::SORT_COLUMNS[$sortBy] ?? self::SORT_COLUMNS['titre'];
    }

    private static function selectGameRow(): string
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

        return 'b.id, b.user_id, b.foyer_id, b.oeuvre_id, b.statut, b.support_physique, b.created_at, b.saga_ordre,'
            . ' o.titre, o.titre_original, o.annee, o.poster_url, o.synopsis,'
            . ' oj.studio, oj.editeur, oj.genre, oj.platform, oj.is_digital' . $edition . $extension . $igdb . $igdbMeta . $linux;
    }

    private static function selectGameHistoryExtras(): string
    {
        return ','
            . ' (SELECT MAX(h.date_vue) FROM historique h'
            . '  WHERE h.film_id = b.id AND h.user_id = :history_user_id) AS derniere_session,'
            . ' (SELECT MAX(h.note) FROM historique h'
            . '  WHERE h.film_id = b.id AND h.user_id = :history_user_id'
            . '    AND h.note IS NOT NULL AND h.note >= 1) AS note_max,'
            . CatalogSchema::foyerAverageNoteSubquery('b.id', ':foyer_id_rating')
            . GameCompletionRepository::selectListExtrasSql();
    }
}
