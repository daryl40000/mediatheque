<?php
/**
 * Recherche catalogue GOG (API publique, sans authentification).
 */

declare(strict_types=1);

namespace Moncine;

final class GogCatalogClient
{
    /** API catalogue actuelle (api.gog.com/products renvoie souvent HTTP 500). */
    private const SEARCH_URL = 'https://catalog.gog.com/v1/catalog';

    /** Ancienne API conservée pour les tests de parsing défensif. */
    private const LEGACY_SEARCH_URL = 'https://api.gog.com/products';

    private ?string $lastError = null;

    public function getLastError(): ?string
    {
        return $this->lastError;
    }

    /**
     * @return list<array{product_id: int, title: string, slug: string}>
     */
    public function search(string $query, int $limit = 10): array
    {
        $query = trim($query);
        if ($query === '') {
            return [];
        }

        $limit = max(1, min(25, $limit));
        $url = self::SEARCH_URL . '?' . http_build_query([
            'productSearch' => $query,
            'limit' => $limit,
        ], '', '&', PHP_QUERY_RFC3986);

        $error = null;
        $body = StoreCatalogHttp::get($url, [], $error);
        if ($body === null) {
            $this->lastError = $error ?? 'Réponse GOG vide.';

            return [];
        }

        $parsed = self::parseSearchResponse($body);
        if ($parsed !== []) {
            return $parsed;
        }

        // Repli sur l’ancienne API si le format change à nouveau.
        $legacyUrl = self::LEGACY_SEARCH_URL . '?' . http_build_query([
            'search' => $query,
            'limit' => $limit,
        ], '', '&', PHP_QUERY_RFC3986);
        $legacyBody = StoreCatalogHttp::get($legacyUrl, [], $error);
        if ($legacyBody === null) {
            $this->lastError = $error ?? 'Réponse GOG vide.';

            return [];
        }

        return self::parseSearchResponse($legacyBody);
    }

    /**
     * @return list<array{product_id: int, title: string, slug: string}>
     */
    public static function parseSearchResponse(string $json): array
    {
        try {
            $data = json_decode($json, true, 512, JSON_THROW_ON_ERROR);
        } catch (\JsonException) {
            return [];
        }

        if (!is_array($data)) {
            return [];
        }

        $items = $data['products'] ?? $data['_embedded']['items'] ?? $data['items'] ?? [];
        if (!is_array($items)) {
            return [];
        }

        $out = [];
        foreach ($items as $item) {
            if (!is_array($item)) {
                continue;
            }
            $parsed = self::parseProductRow($item);
            if ($parsed !== null) {
                $out[] = $parsed;
            }
        }

        return $out;
    }

    /**
     * @param array<string, mixed> $item
     * @return array{product_id: int, title: string, slug: string}|null
     */
    public static function parseProductRow(array $item): ?array
    {
        $title = trim((string) ($item['title'] ?? $item['name'] ?? ''));
        $slug = trim((string) ($item['slug'] ?? ''));
        $productId = (int) ($item['product_id'] ?? $item['id'] ?? 0);
        if ($productId === 0 && isset($item['id'])) {
            $productId = (int) preg_replace('/\D+/', '', (string) $item['id']);
        }

        if ($title === '' || $slug === '') {
            return null;
        }

        return [
            'product_id' => $productId,
            'title' => $title,
            'slug' => $slug,
        ];
    }

    public static function storeUrl(string $slug): string
    {
        $slug = trim($slug, '/');
        if ($slug === '') {
            return '';
        }

        return 'https://www.gog.com/game/' . $slug;
    }

    public static function slugFromStoreUrl(string $url): string
    {
        $url = trim($url);
        if ($url === '') {
            return '';
        }

        if (preg_match('~gog\.com/game/([^/?#]+)~i', $url, $matches) === 1) {
            return trim((string) ($matches[1] ?? ''));
        }

        return '';
    }

    public static function normalizeImageUrl(?string $url): string
    {
        $url = trim((string) $url);
        if ($url === '') {
            return '';
        }
        if (str_starts_with($url, '//')) {
            return 'https:' . $url;
        }

        return $url;
    }
}
