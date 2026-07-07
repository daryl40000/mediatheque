<?php
/**
 * Liens magasins PC sur les fiches jeu catalogue (saisie manuelle admin).
 *
 * Ces liens sont stockés dans oeuvre_store_links (catalogue partagé).
 * Ils ne doivent pas être confondus avec digital_stores (possession utilisateur).
 */

declare(strict_types=1);

namespace Moncine;

final class CatalogGameStoreLinks
{
    /** @var list<string> */
    public const MANUAL_STORES = [
        GameDigitalStore::STEAM,
        GameDigitalStore::GOG,
        GameDigitalStore::EPIC,
    ];

    /**
     * @param array<string, mixed> $gameRow
     * @return array<string, string> store => URL affichée dans le formulaire
     */
    public static function urlsForCatalogRow(array $gameRow): array
    {
        $urls = [];
        if (is_array($gameRow['catalog_store_urls'] ?? null)) {
            foreach ($gameRow['catalog_store_urls'] as $store => $url) {
                $store = GameDigitalStore::normalizeStoreKey((string) $store);
                $url = trim((string) $url);
                if ($store !== '' && $url !== '') {
                    $urls[$store] = $url;
                }
            }
        } elseif (OeuvreStoreLinkRepository::isAvailable()) {
            $oeuvreId = (int) ($gameRow['oeuvre_id'] ?? 0);
            if ($oeuvreId > 0) {
                $urls = (new OeuvreStoreLinkRepository())->listVerifiedUrlsForOeuvre($oeuvreId);
            }
        }

        // Ancien stockage : afficher dans le formulaire admin pour faciliter la migration.
        foreach (GameDigitalStore::parseStoredList((string) ($gameRow['digital_stores'] ?? '')) as $entry) {
            $store = (string) ($entry['store'] ?? '');
            $url = trim((string) ($entry['url'] ?? ''));
            if ($store !== '' && $url !== '' && !isset($urls[$store])) {
                $urls[$store] = $url;
            }
        }

        if (!isset($urls[GameDigitalStore::STEAM])) {
            $appid = (int) ($gameRow['steam_appid'] ?? 0);
            if ($appid > 0) {
                $urls[GameDigitalStore::STEAM] = SteamWebApiClient::storeUrl(
                    $appid,
                    GameTitle::displayTitle($gameRow)
                );
            }
        }

        return $urls;
    }

    /**
     * @param array<string, mixed> $post
     */
    public function saveFromPost(int $oeuvreId, array $post): ?string
    {
        if ($oeuvreId <= 0 || !GameRepository::isAvailable()) {
            return 'Fiche jeu invalide.';
        }

        if (!OeuvreStoreLinkRepository::isAvailable()) {
            return 'La table des liens magasins catalogue n’est pas disponible (migration manquante).';
        }

        $repo = new GameRepository();
        $game = $repo->findCatalogByOeuvreId($oeuvreId);
        if ($game === null) {
            return 'Fiche jeu introuvable.';
        }

        $this->migrateUrlsFromDigitalStores($repo, $oeuvreId, $game);

        $links = new OeuvreStoreLinkRepository();

        foreach (self::MANUAL_STORES as $store) {
            $field = 'store_url_' . $store;
            $clearField = 'clear_store_' . $store;
            $clear = !empty($post[$clearField]);
            $url = SecureUrl::sanitizePosterUrl(trim((string) ($post[$field] ?? '')));

            if ($clear) {
                $this->clearStore($links, $repo, $oeuvreId, $store);

                continue;
            }

            if ($url === '') {
                continue;
            }

            $links->upsert($oeuvreId, $store, [
                'store_slug' => self::slugFromUrl($store, $url),
                'store_url' => $url,
                'store_title' => '',
                'match_confidence' => null,
                'manually_verified' => true,
            ]);

            self::stripCatalogUrlFromOwnership($repo, $oeuvreId, $game, $store);

            if ($store === GameDigitalStore::STEAM) {
                $appid = SteamGameResolver::extractAppIdFromStoreUrl($url);
                if ($appid > 0) {
                    $repo->setSteamAppIdIfEmpty($oeuvreId, $appid);
                }
            }
        }

        return null;
    }

    /**
     * Retire une URL catalogue erronément enregistrée dans digital_stores (possession).
     *
     * @param array<string, mixed> $game
     */
    public static function stripCatalogUrlFromOwnership(
        GameRepository $repo,
        int $oeuvreId,
        array &$game,
        string $store,
    ): void {
        $store = GameDigitalStore::normalizeStoreKey($store);
        if ($store === '') {
            return;
        }

        $digitalJson = (string) ($game['digital_stores'] ?? '');
        foreach (GameDigitalStore::parseStoredList($digitalJson) as $entry) {
            if (($entry['store'] ?? '') !== $store) {
                continue;
            }

            $entryUrl = trim((string) ($entry['url'] ?? ''));
            if ($entryUrl === '') {
                return;
            }

            $merged = GameDigitalStore::removeStore($digitalJson, $store);
            $isDigital = GameDigitalStore::hasDigitalEdition($merged, !empty($game['is_digital']));
            $repo->updateCatalogDigitalStores($oeuvreId, $merged, $isDigital);
            $game['digital_stores'] = $merged;

            return;
        }
    }

    /**
     * Copie les anciens liens catalogue (digital_stores) vers oeuvre_store_links.
     *
     * @param array<string, mixed> $game
     */
    private function migrateUrlsFromDigitalStores(GameRepository $repo, int $oeuvreId, array &$game): void
    {
        $links = new OeuvreStoreLinkRepository();

        foreach (self::MANUAL_STORES as $store) {
            $existing = $links->find($oeuvreId, $store);
            if ($existing !== null && !empty($existing['manually_verified'])) {
                continue;
            }

            foreach (GameDigitalStore::parseStoredList((string) ($game['digital_stores'] ?? '')) as $entry) {
                if (($entry['store'] ?? '') !== $store) {
                    continue;
                }

                $url = SecureUrl::sanitizePosterUrl(trim((string) ($entry['url'] ?? '')));
                if ($url === '') {
                    continue;
                }

                $links->upsert($oeuvreId, $store, [
                    'store_slug' => self::slugFromUrl($store, $url),
                    'store_url' => $url,
                    'store_title' => '',
                    'match_confidence' => null,
                    'manually_verified' => true,
                ]);
                self::stripCatalogUrlFromOwnership($repo, $oeuvreId, $game, $store);
            }
        }
    }

    private function clearStore(
        OeuvreStoreLinkRepository $links,
        GameRepository $repo,
        int $oeuvreId,
        string $store,
    ): void {
        $links->delete($oeuvreId, $store);

        if ($store === GameDigitalStore::STEAM && GameSchema::hasSteamAppIdColumn()) {
            $repo->clearSteamAppId($oeuvreId);
        }
    }

    private static function slugFromUrl(string $store, string $url): string
    {
        return match (GameDigitalStore::normalizeStoreKey($store)) {
            GameDigitalStore::GOG => GogCatalogClient::slugFromStoreUrl($url),
            GameDigitalStore::EPIC => EpicCatalogClient::slugFromStoreUrl($url),
            default => '',
        };
    }
}
