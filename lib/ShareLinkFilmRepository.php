<?php
/**
 * Lecture des films exposés via un lien de partage (sans session).
 */

declare(strict_types=1);

namespace Moncine;

use PDO;

final class ShareLinkFilmRepository
{
    private PDO $db;

    private const SORT_COLUMNS = [
        'titre' => 'o.titre COLLATE FRENCH_NOCASE',
        'annee' => 'o.annee',
        'realisateur' => 'o.realisateur COLLATE FRENCH_NOCASE',
        'duree_min' => 'o.duree_min',
        'styles' => 'o.styles COLLATE FRENCH_NOCASE',
        'support_physique' => 'b.support_physique COLLATE FRENCH_NOCASE',
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
        string $kindFilter = ''
    ): array {
        if (!isset(self::SORT_COLUMNS[$sortBy])) {
            $sortBy = 'titre';
        }
        $direction = strtolower($sortDir) === 'desc' ? 'DESC' : 'ASC';
        $orderExpr = self::SORT_COLUMNS[$sortBy];

        $sql = 'SELECT ' . CatalogSchema::selectFilmRow()
            . ' FROM ' . CatalogSchema::JOIN;

        [$userWhere, $params] = $this->libraryFilterForLink($link);
        $whereParts = [$userWhere];

        $searchWhere = $this->searchWhereSql($searchQuery, $params);
        if ($searchWhere !== '') {
            $whereParts[] = $searchWhere;
        }

        $kindWhere = ContentKindFilter::sqlWhere($kindFilter, 'o.', $params);
        if ($kindWhere !== '') {
            $whereParts[] = $kindWhere;
        }

        if (CatalogSchema::hasMediaDomainColumn()) {
            $params['share_media_domain'] = MediaDomain::FILM;
            $whereParts[] = 'o.media_domain = :share_media_domain';
        }

        $sql .= ' WHERE ' . implode(' AND ', $whereParts);
        $sql .= ' ORDER BY ' . $orderExpr . ' ' . $direction;
        if ($sortBy !== 'titre') {
            $sql .= ', o.titre COLLATE FRENCH_NOCASE ASC';
        }

        $stmt = $this->db->prepare($sql);
        $stmt->execute($params);

        return $stmt->fetchAll() ?: [];
    }

    /**
     * @param array<string, mixed> $link
     * @return array<string, mixed>|null
     */
    public function findByIdForLink(array $link, int $bibliothequeId): ?array
    {
        if ($bibliothequeId <= 0) {
            return null;
        }

        $sql = 'SELECT ' . CatalogSchema::selectFilmRow()
            . ' FROM ' . CatalogSchema::JOIN;

        [$userWhere, $params] = $this->libraryFilterForLink($link);
        $sql .= ' WHERE ' . $userWhere . ' AND b.id = :share_film_id';
        $sql .= CatalogSchema::sqlMediaDomainAnd('o', $params);

        $params['share_film_id'] = $bibliothequeId;

        $stmt = $this->db->prepare($sql);
        $stmt->execute($params);
        $row = $stmt->fetch();

        return $row !== false ? $row : null;
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
        $pattern = LikePattern::containsFragment($searchQuery);
        if ($pattern === '') {
            return '';
        }

        $params['share_q'] = $pattern;
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
        $parts = [];
        foreach ($fields as $field) {
            $parts[] = 'LOWER(' . $field . ') LIKE LOWER(:share_q) ESCAPE \'\\\'';
        }

        return '(' . implode(' OR ', $parts) . ')';
    }
}
