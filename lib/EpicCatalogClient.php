<?php
/**
 * Recherche catalogue Epic Games Store (GraphQL public).
 */

declare(strict_types=1);

namespace Moncine;

final class EpicCatalogClient
{
    private const GRAPHQL_URL = 'https://store.epicgames.com/graphql';

    private const SEARCH_QUERY = <<<'GQL'
query searchStoreQuery($keywords: String!, $country: String!, $locale: String, $count: Int) {
  Catalog {
    searchStore(keywords: $keywords, country: $country, locale: $locale, count: $count) {
      elements {
        title
        productSlug
        urlSlug
        catalogNs {
          mappings(pageType: "productHome") {
            pageSlug
            pageType
          }
        }
      }
    }
  }
}
GQL;

    private ?string $lastError = null;

    public function getLastError(): ?string
    {
        return $this->lastError;
    }

    /**
     * @return list<array{title: string, slug: string}>
     */
    public function search(string $keyword, int $limit = 10, string $country = 'FR', string $locale = 'fr'): array
    {
        $keyword = trim($keyword);
        if ($keyword === '') {
            return [];
        }

        $limit = max(1, min(25, $limit));
        $payload = json_encode([
            'query' => self::SEARCH_QUERY,
            'variables' => [
                'keywords' => $keyword,
                'country' => strtoupper($country),
                'locale' => $locale,
                'count' => $limit,
            ],
        ], JSON_UNESCAPED_UNICODE);

        if ($payload === false) {
            $this->lastError = 'Requête Epic illisible.';

            return [];
        }

        $error = null;
        $body = StoreCatalogHttp::postJson(self::GRAPHQL_URL, $payload, [
            'Referer: https://store.epicgames.com/fr/',
            'Origin: https://store.epicgames.com',
        ], $error);
        if ($body === null) {
            $this->lastError = $error ?? 'Réponse Epic vide.';
            if (str_contains((string) $error, '403') || str_contains((string) $error, 'HTML')) {
                $this->lastError .= ' Epic bloque souvent les requêtes serveur ; validez les liens Epic à la main si besoin.';
            }

            return [];
        }

        return self::parseSearchResponse($body);
    }

    /**
     * @return list<array{title: string, slug: string}>
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

        $elements = $data['data']['Catalog']['searchStore']['elements'] ?? null;
        if (!is_array($elements)) {
            return [];
        }

        $out = [];
        foreach ($elements as $element) {
            if (!is_array($element)) {
                continue;
            }
            $parsed = self::parseElementRow($element);
            if ($parsed !== null) {
                $out[] = $parsed;
            }
        }

        return $out;
    }

    /**
     * @param array<string, mixed> $element
     * @return array{title: string, slug: string}|null
     */
    public static function parseElementRow(array $element): ?array
    {
        $title = trim((string) ($element['title'] ?? ''));
        $slug = self::resolveSlug($element);
        if ($title === '' || $slug === '') {
            return null;
        }

        return [
            'title' => $title,
            'slug' => $slug,
        ];
    }

    /**
     * @param array<string, mixed> $element
     */
    public static function resolveSlug(array $element): string
    {
        foreach (['productSlug', 'urlSlug'] as $field) {
            $slug = trim((string) ($element[$field] ?? ''));
            if ($slug !== '') {
                return $slug;
            }
        }

        $mappings = $element['catalogNs']['mappings'] ?? null;
        if (is_array($mappings)) {
            foreach ($mappings as $mapping) {
                if (!is_array($mapping)) {
                    continue;
                }
                $slug = trim((string) ($mapping['pageSlug'] ?? ''));
                if ($slug !== '') {
                    return $slug;
                }
            }
        }

        return '';
    }

    public static function storeUrl(string $slug): string
    {
        $slug = trim($slug, '/');
        if ($slug === '') {
            return '';
        }

        return 'https://store.epicgames.com/p/' . $slug;
    }

    public static function slugFromStoreUrl(string $url): string
    {
        $url = trim($url);
        if ($url === '') {
            return '';
        }

        if (preg_match('~store\.epicgames\.com/p/([^/?#]+)~i', $url, $matches) === 1) {
            return trim((string) ($matches[1] ?? ''));
        }

        return '';
    }
}
