<?php
/**
 * Repli IGDB pour les liens magasins (Epic bloque souvent les requêtes serveur).
 */

declare(strict_types=1);

namespace Moncine;

final class IgdbStoreLinkResolver
{
    public function __construct(
        private readonly IgdbClient $igdb = new IgdbClient(),
    ) {
    }

    public static function isAvailable(): bool
    {
        return IgdbConfig::hasCredentials();
    }

    /**
     * @param array<string, mixed> $catalogRow
     * @return array{title: string, slug: string, url: string, from_catalog_igdb: bool}|null
     */
    public function resolve(array $catalogRow, string $store): ?array
    {
        if (!self::isAvailable()) {
            return null;
        }

        $store = GameDigitalStore::normalizeStoreKey($store);
        if ($store === '') {
            return null;
        }

        $catalogIgdbId = (int) ($catalogRow['igdb_id'] ?? 0);
        $igdbId = $catalogIgdbId;
        if ($igdbId <= 0) {
            $title = GameTitle::displayTitle($catalogRow);
            if ($title === '') {
                return null;
            }
            $game = $this->igdb->searchGame($title, (int) ($catalogRow['annee'] ?? 0));
            $igdbId = (int) ($game['igdb_id'] ?? 0);
        }

        if ($igdbId <= 0) {
            return null;
        }

        foreach ($this->igdb->listExternalStoreUrlsForGame($igdbId) as $entry) {
            $parsed = self::parseStoreEntry($entry, $store);
            if ($parsed !== null) {
                $parsed['from_catalog_igdb'] = $catalogIgdbId > 0 && $catalogIgdbId === $igdbId;

                return $parsed;
            }
        }

        return null;
    }

    /**
     * @param array{name: string, url: string} $entry
     * @return array{title: string, slug: string, url: string}|null
     */
    public static function parseStoreEntry(array $entry, string $store): ?array
    {
        $store = GameDigitalStore::normalizeStoreKey($store);
        $url = SecureUrl::sanitizePosterUrl(trim((string) ($entry['url'] ?? '')));
        if ($url === '' || !self::urlMatchesStore($url, $store)) {
            return null;
        }

        $slug = match ($store) {
            GameDigitalStore::EPIC => EpicCatalogClient::slugFromStoreUrl($url),
            GameDigitalStore::GOG => GogCatalogClient::slugFromStoreUrl($url),
            default => '',
        };
        if ($slug === '') {
            return null;
        }

        $title = trim((string) ($entry['name'] ?? ''));
        if ($title === '') {
            $title = $slug;
        }

        return [
            'title' => $title,
            'slug' => $slug,
            'url' => $url,
        ];
    }

    public static function urlMatchesStore(string $url, string $store): bool
    {
        $url = strtolower($url);

        return match (GameDigitalStore::normalizeStoreKey($store)) {
            GameDigitalStore::EPIC => str_contains($url, 'store.epicgames.com/p/'),
            GameDigitalStore::GOG => (bool) preg_match('~gog\.com/(?:[a-z]{2}/)?game/~i', $url),
            default => false,
        };
    }
}
