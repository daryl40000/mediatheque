<?php
/**
 * Création et validation des liens de partage visiteur.
 */

declare(strict_types=1);

namespace Moncine;

final class ShareLinkService
{
    private const TOKEN_BYTES = 32;

    private const DEFAULT_EXPIRE_DAYS = 90;

    /** Nombre maximal de liens actifs (non révoqués, non expirés) par utilisateur. */
    public const MAX_ACTIVE_LINKS_PER_USER = 10;

    private ShareLinkRepository $links;

    public function __construct(?ShareLinkRepository $links = null)
    {
        $this->links = $links ?? new ShareLinkRepository();
    }

    /**
     * @return array{link: array<string, mixed>, token: string}|string
     */
    public function create(
        int $userId,
        int $foyerId,
        string $scope,
        string $label = '',
        ?int $expireDays = self::DEFAULT_EXPIRE_DAYS,
        string $mediaDomain = MediaDomain::FILM
    ): array|string {
        if (!ShareLinkRepository::tableExists()) {
            return 'Le partage visiteur n’est pas disponible (migration en attente).';
        }
        if ($userId <= 0) {
            return 'Utilisateur invalide.';
        }

        $scope = ShareLinkScope::normalize($scope);
        $mediaDomain = MediaDomain::normalize($mediaDomain);
        if ($scope === ShareLinkScope::COLLECTION && $foyerId <= 0) {
            return 'Aucun foyer associé pour partager la collection.';
        }

        $activeCount = $this->links->countActiveForUser($userId);
        if ($activeCount >= self::MAX_ACTIVE_LINKS_PER_USER) {
            return 'Limite atteinte : maximum ' . self::MAX_ACTIVE_LINKS_PER_USER
                . ' liens actifs. Révoquez un lien existant avant d’en créer un nouveau.';
        }

        $rawToken = self::generateToken();
        $hash = self::hashToken($rawToken);
        $expiresAt = null;
        if ($expireDays !== null && $expireDays > 0) {
            $expiresAt = date('Y-m-d H:i:s', time() + $expireDays * 86400);
        }

        $linkId = $this->links->insert(
            $hash,
            $scope,
            $userId,
            $scope === ShareLinkScope::COLLECTION ? $foyerId : null,
            $label,
            $expiresAt,
            $mediaDomain
        );
        $link = $this->links->findByIdForUser($linkId, $userId);
        if ($link === null) {
            return 'Impossible de créer le lien de partage.';
        }

        return ['link' => $link, 'token' => $rawToken];
    }

    /** @return array<string, mixed>|null */
    public function resolve(string $rawToken): ?array
    {
        $rawToken = trim($rawToken);
        if ($rawToken === '') {
            return null;
        }
        if (!ShareLinkRateLimit::allowAttempt()) {
            return null;
        }
        if (!ShareLinkRepository::tableExists()) {
            ShareLinkRateLimit::recordFailure();

            return null;
        }

        $hash = self::hashToken($rawToken);
        $link = $this->links->findByTokenHash($hash);
        if ($link === null || !$this->links->isActive($link)) {
            ShareLinkRateLimit::recordFailure();

            return null;
        }

        $this->links->recordAccess((int) $link['id']);

        return $link;
    }

    public function revoke(int $linkId, int $userId): bool
    {
        return $this->links->revoke($linkId, $userId);
    }

    public static function generateToken(): string
    {
        return rtrim(strtr(base64_encode(random_bytes(self::TOKEN_BYTES)), '+/', '-_'), '=');
    }

    public static function hashToken(string $rawToken): string
    {
        return hash('sha256', $rawToken);
    }

    public static function listUrl(string $rawToken, string $scope, string $mediaDomain = MediaDomain::FILM): string
    {
        unset($scope);

        return self::collectionUrl($rawToken, [], $mediaDomain);
    }

    public static function collectionPath(string $mediaDomain = MediaDomain::FILM): string
    {
        return match (MediaDomain::normalize($mediaDomain)) {
            MediaDomain::JEU => '/partage-jeux.php',
            MediaDomain::BD => '/partage-bd.php',
            default => '/partage.php',
        };
    }

    /**
     * URL de la liste partagée (recherche, tri, filtre, mode liste/vignettes).
     *
     * @param array<string, int|string> $extra
     */
    public static function collectionUrl(
        string $rawToken,
        array $extra = [],
        string $mediaDomain = MediaDomain::FILM
    ): string {
        $params = ['t' => $rawToken];
        foreach ($extra as $key => $value) {
            if ($value === '' || $value === null) {
                continue;
            }
            $params[(string) $key] = $value;
        }

        return self::collectionPath($mediaDomain) . '?' . http_build_query($params, '', '&', PHP_QUERY_RFC3986);
    }

    /** Lien de tri pour la liste partagée. */
    public static function sortUrl(
        string $rawToken,
        string $column,
        string $currentSort,
        string $currentDir,
        string $searchQuery = '',
        string $kindFilter = '',
        string $viewMode = '',
        string $mediaDomain = MediaDomain::FILM,
        ?GameListFilter $gameFilter = null
    ): string {
        $dir = 'asc';
        if ($currentSort === $column && strtolower($currentDir) === 'asc') {
            $dir = 'desc';
        }

        return self::collectionUrl($rawToken, self::collectionQueryParams(
            $searchQuery,
            $column,
            $dir,
            $kindFilter,
            $viewMode,
            $gameFilter
        ), $mediaDomain);
    }

    /**
     * @return array<string, string>
     */
    public static function collectionQueryParams(
        string $searchQuery = '',
        string $sortBy = 'titre',
        string $sortDir = 'asc',
        string $kindFilter = '',
        string $viewMode = '',
        ?GameListFilter $gameFilter = null
    ): array {
        $params = [];
        $searchQuery = trim($searchQuery);
        if ($searchQuery !== '') {
            $params['q'] = $searchQuery;
        }
        if ($sortBy !== '' && $sortBy !== 'titre') {
            $params['sort'] = $sortBy;
        }
        if (strtolower($sortDir) === 'desc') {
            $params['dir'] = 'desc';
        }
        $kindFilter = ContentKindFilter::normalize($kindFilter);
        if ($kindFilter !== ContentKindFilter::ALL) {
            $params['kind'] = $kindFilter;
        }
        $viewParam = CollectionViewMode::queryValue($viewMode);
        if ($viewParam !== null) {
            $params['view'] = $viewParam;
        }

        if ($gameFilter !== null) {
            foreach ($gameFilter->toQueryParams() as $key => $value) {
                $params[$key] = (string) $value;
            }
        }

        return $params;
    }

    public static function filmUrl(string $rawToken, int $filmId, array $listContext = []): string
    {
        $query = array_merge(['t' => $rawToken, 'id' => $filmId], $listContext);

        return '/partage-film.php?' . http_build_query($query, '', '&', PHP_QUERY_RFC3986);
    }

    public static function gameUrl(string $rawToken, int $gameId, array $listContext = []): string
    {
        $query = array_merge(['t' => $rawToken, 'id' => $gameId], $listContext);

        return '/partage-jeu.php?' . http_build_query($query, '', '&', PHP_QUERY_RFC3986);
    }

    public static function bdSeriesUrl(string $rawToken, int $seriesId, array $listContext = []): string
    {
        $query = array_merge(['t' => $rawToken, 'series_id' => $seriesId], $listContext);

        return '/partage-serie-bd.php?' . http_build_query($query, '', '&', PHP_QUERY_RFC3986);
    }

    public static function bdAlbumUrl(string $rawToken, int $bibId, array $listContext = []): string
    {
        $query = array_merge(['t' => $rawToken, 'id' => $bibId], $listContext);

        return '/partage-album-bd.php?' . http_build_query($query, '', '&', PHP_QUERY_RFC3986);
    }

    /** URL de retour vers la liste partagée (conserve recherche, tri, filtre, vue). */
    public static function listBackUrl(
        string $rawToken,
        array $query = [],
        string $mediaDomain = MediaDomain::FILM
    ): string {
        return self::collectionUrl($rawToken, $query, $mediaDomain);
    }
}
