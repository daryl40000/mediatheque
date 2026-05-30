<?php
/**
 * Contexte de liste (collection ou envies) pour les liens et la navigation entre fiches.
 */

declare(strict_types=1);

namespace Moncine;

final class FilmListContext
{
    public const COLLECTION = 'collection';

    public const WISHLIST = 'wishlist';

    public function __construct(
        private string $list,
        private string $sortBy = 'titre',
        private string $sortDir = 'asc',
        private string $searchQuery = '',
        private string $kindFilter = ''
    ) {
        if (!in_array($this->list, [self::COLLECTION, self::WISHLIST], true)) {
            $this->list = self::COLLECTION;
        }
        $this->sortBy = trim($this->sortBy) !== '' ? trim($this->sortBy) : 'titre';
        $this->sortDir = strtolower(trim($this->sortDir)) === 'desc' ? 'desc' : 'asc';
        $this->searchQuery = trim($this->searchQuery);
        $this->kindFilter = ContentKindFilter::normalize($this->kindFilter);
    }

    public static function forCollection(
        string $sortBy = 'titre',
        string $sortDir = 'asc',
        string $searchQuery = '',
        string $kindFilter = ''
    ): self {
        return new self(self::COLLECTION, $sortBy, $sortDir, $searchQuery, $kindFilter);
    }

    public static function forWishlist(
        string $sortBy = 'titre',
        string $sortDir = 'asc',
        string $searchQuery = ''
    ): self {
        return new self(self::WISHLIST, $sortBy, $sortDir, $searchQuery);
    }

    /** @param array<string, mixed> $query */
    public static function fromQuery(array $query, string $defaultList): self
    {
        $list = (string) ($query['list'] ?? '');
        if (!in_array($list, [self::COLLECTION, self::WISHLIST], true)) {
            $list = $defaultList;
        }

        return new self(
            $list,
            (string) ($query['sort'] ?? 'titre'),
            (string) ($query['dir'] ?? 'asc'),
            (string) ($query['q'] ?? ''),
            (string) ($query['kind'] ?? '')
        );
    }

    /** @param array<string, mixed> $post */
    public static function fromPost(array $post, string $defaultList): self
    {
        return self::fromQuery($post, $defaultList);
    }

    public function isWishlist(): bool
    {
        return $this->list === self::WISHLIST;
    }

    public function list(): string
    {
        return $this->list;
    }

    public function sortBy(): string
    {
        return $this->sortBy;
    }

    public function sortDir(): string
    {
        return $this->sortDir;
    }

    public function searchQuery(): string
    {
        return $this->searchQuery;
    }

    public function kindFilter(): string
    {
        return $this->kindFilter;
    }

    /** @return array<string, string> */
    public function queryParams(): array
    {
        $params = ['list' => $this->list];
        if ($this->searchQuery !== '') {
            $params['q'] = $this->searchQuery;
        }
        if ($this->sortBy !== 'titre') {
            $params['sort'] = $this->sortBy;
        }
        if ($this->sortDir === 'desc') {
            $params['dir'] = 'desc';
        }
        if ($this->list === self::COLLECTION && $this->kindFilter !== ContentKindFilter::ALL) {
            $params['kind'] = $this->kindFilter;
        }

        return $params;
    }

    public function filmUrl(int $filmId): string
    {
        return $this->filmUrlWithQuery($filmId);
    }

    /**
     * @param array<string, scalar> $extraQuery
     */
    public function filmUrlWithQuery(int $filmId, array $extraQuery = []): string
    {
        if ($filmId <= 0) {
            return $this->backUrl();
        }

        $query = array_merge(['id' => (string) $filmId], $this->queryParams(), $extraQuery);

        return '/film.php?' . http_build_query($query, '', '&', PHP_QUERY_RFC3986) . '#film-list-nav';
    }

    public function backUrl(): string
    {
        if ($this->isWishlist()) {
            return View::wishlistUrl($this->searchQuery, $this->sortBy, $this->sortDir);
        }

        return View::filmsCollectionUrl(
            $this->searchQuery,
            $this->sortBy,
            $this->sortDir,
            $this->kindFilter
        );
    }

    public function appendToUrl(string $url): string
    {
        $hash = '';
        if (($hashPos = strpos($url, '#')) !== false) {
            $hash = substr($url, $hashPos);
            $url = substr($url, 0, $hashPos);
        }

        $params = $this->queryParams();
        if ($params === []) {
            return $url . $hash;
        }

        $sep = str_contains($url, '?') ? '&' : '?';

        return $url . $sep . http_build_query($params, '', '&', PHP_QUERY_RFC3986) . $hash;
    }
}
