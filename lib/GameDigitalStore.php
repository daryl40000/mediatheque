<?php
/**
 * Éditions dématérialisées : magasins PC (Steam, GOG, Epic, Battle.net) ou store console.
 */

declare(strict_types=1);

namespace Moncine;

final class GameDigitalStore
{
    public const STEAM = 'steam';
    public const GOG = 'gog';
    public const EPIC = 'epic';
    public const BATTLENET = 'battlenet';
    public const PSN = 'psn';
    public const XBOX = 'xbox';
    public const ESHOP = 'eshop';

    /** @return array<string, string> magasins PC (lien optionnel) */
    public static function pcStoreChoices(): array
    {
        return [
            self::STEAM => 'Steam',
            self::GOG => 'GOG',
            self::EPIC => 'Epic Games Store',
            self::BATTLENET => 'Battle.net',
        ];
    }

    public static function label(string $store): string
    {
        $store = self::normalizeStoreKey($store);

        return self::pcStoreChoices()[$store]
            ?? match ($store) {
                self::PSN => 'PlayStation Store',
                self::XBOX => 'Microsoft Store / Xbox',
                self::ESHOP => 'Nintendo eShop',
                default => $store,
            };
    }

    /** Store console imposé selon la plateforme (sans lien personnalisé). */
    public static function consoleStoreForPlatform(string $platform): ?string
    {
        $fromRegistry = GamePlatformRegistry::consoleStoreForPlatform($platform);
        if ($fromRegistry !== null) {
            return $fromRegistry;
        }

        return match (GamePlatform::normalize($platform)) {
            GamePlatform::PS5, GamePlatform::PS4 => self::PSN,
            GamePlatform::XBOX_SERIES, GamePlatform::XBOX_ONE => self::XBOX,
            GamePlatform::SWITCH, GamePlatform::SWITCH2 => self::ESHOP,
            default => null,
        };
    }

    /**
     * @return list<array{store: string, url: string, label: string}>
     */
    public static function parseStoredList(string $json): array
    {
        $json = trim($json);
        if ($json === '') {
            return [];
        }

        $decoded = json_decode($json, true);
        if (!is_array($decoded)) {
            return [];
        }

        $items = [];
        foreach ($decoded as $entry) {
            if (!is_array($entry)) {
                continue;
            }
            $store = self::normalizeStoreKey((string) ($entry['store'] ?? ''));
            if ($store === '') {
                continue;
            }
            $url = SecureUrl::sanitizePosterUrl(trim((string) ($entry['url'] ?? '')));
            $items[] = [
                'store' => $store,
                'url' => $url,
                'label' => self::label($store),
            ];
        }

        return $items;
    }

    /**
     * Ajoute ou met à jour un magasin dans le JSON existant (fusion cross-store).
     */
    public static function mergeStore(string $existingJson, string $store, string $url = ''): string
    {
        $store = self::normalizeStoreKey($store);
        if ($store === '') {
            return trim($existingJson);
        }

        $url = SecureUrl::sanitizePosterUrl(trim($url));
        $items = self::parseStoredList($existingJson);
        foreach ($items as $index => $entry) {
            if (($entry['store'] ?? '') !== $store) {
                continue;
            }
            if ($url !== '' && ($entry['url'] ?? '') === '') {
                $items[$index]['url'] = $url;
            }

            return self::serializeList(array_map(
                static fn (array $item): array => [
                    'store' => (string) ($item['store'] ?? ''),
                    'url' => (string) ($item['url'] ?? ''),
                ],
                $items
            ));
        }

        $items[] = [
            'store' => $store,
            'url' => $url,
            'label' => self::label($store),
        ];

        return self::serializeList(array_map(
            static fn (array $item): array => [
                'store' => (string) ($item['store'] ?? ''),
                'url' => (string) ($item['url'] ?? ''),
            ],
            $items
        ));
    }

    /** @param list<array{store: string, url?: string}> $entries */
    public static function serializeList(array $entries): string
    {
        $out = [];
        foreach ($entries as $entry) {
            $store = self::normalizeStoreKey((string) ($entry['store'] ?? ''));
            if ($store === '') {
                continue;
            }
            $url = SecureUrl::sanitizePosterUrl(trim((string) ($entry['url'] ?? '')));
            $out[] = [
                'store' => $store,
                'url' => $url,
            ];
        }

        if ($out === []) {
            return '';
        }

        return json_encode($out, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) ?: '';
    }

    /**
     * Construit le JSON magasins depuis un formulaire POST.
     *
     * @param array<string, mixed> $post
     */
    public static function buildFromPost(array $post, string $platform): string
    {
        if (empty($post['is_digital'])) {
            return '';
        }

        $platform = GamePlatform::normalize($platform);

        if (GamePlatform::usesPcDigitalStores($platform)) {
            $entries = [];
            foreach ((array) ($post['digital_pc_stores'] ?? []) as $storeRaw) {
                $store = self::normalizeStoreKey((string) $storeRaw);
                if (!isset(self::pcStoreChoices()[$store])) {
                    continue;
                }
                $urlRaw = '';
                if (isset($post['digital_store_url']) && is_array($post['digital_store_url'])) {
                    $urlRaw = (string) ($post['digital_store_url'][$store] ?? '');
                }
                $entries[] = [
                    'store' => $store,
                    'url' => SecureUrl::sanitizePosterUrl(trim($urlRaw)),
                ];
            }

            return self::serializeList($entries);
        }

        if (GamePlatform::isConsole($platform)) {
            $consoleStore = self::consoleStoreForPlatform($platform);
            if ($consoleStore !== null) {
                return self::serializeList([['store' => $consoleStore, 'url' => '']]);
            }
        }

        return '';
    }

    /**
     * Magasins démat pour plusieurs plateformes cochées (PC + console sur un même exemplaire).
     *
     * @param array<string, mixed> $post
     * @param list<string> $platformKeys
     */
    public static function buildFromPostForPlatforms(array $post, array $platformKeys): string
    {
        if (empty($post['is_digital']) || $platformKeys === []) {
            return '';
        }

        $entries = [];
        $seen = [];
        foreach (GamePlatformList::orderedKeys($platformKeys) as $platform) {
            $chunk = self::buildFromPost($post, $platform);
            foreach (self::parseStoredList($chunk) as $entry) {
                $store = (string) ($entry['store'] ?? '');
                if ($store === '' || isset($seen[$store])) {
                    continue;
                }
                $seen[$store] = true;
                $entries[] = [
                    'store' => $store,
                    'url' => (string) ($entry['url'] ?? ''),
                ];
            }
        }

        return self::serializeList($entries);
    }

    public static function hasDigitalEdition(string $storedJson, bool $legacyIsDigital = false): bool
    {
        if (self::parseStoredList($storedJson) !== []) {
            return true;
        }

        return $legacyIsDigital;
    }

    /** @return list<string> résumé court pour listes */
    public static function summaryLabels(string $storedJson): array
    {
        $labels = [];
        foreach (self::parseStoredList($storedJson) as $entry) {
            $labels[] = (string) ($entry['label'] ?? '');
        }

        return array_values(array_filter($labels, static fn (string $label): bool => $label !== ''));
    }

    /** @return array<string, string> choix pour le filtre liste « Mes jeux » */
    public static function filterChoices(): array
    {
        return [
            self::STEAM => self::label(self::STEAM),
            self::GOG => self::label(self::GOG),
            self::EPIC => self::label(self::EPIC),
            self::BATTLENET => self::label(self::BATTLENET),
            self::PSN => self::label(self::PSN),
            self::XBOX => self::label(self::XBOX),
            self::ESHOP => self::label(self::ESHOP),
        ];
    }

    public static function isValidFilterKey(string $raw): bool
    {
        return self::normalizeFilterKey($raw) !== '';
    }

    public static function normalizeFilterKey(string $raw): string
    {
        $key = self::normalizeStoreKey($raw);

        return isset(self::filterChoices()[$key]) ? $key : '';
    }

    /** Condition SQL : le JSON `digital_stores` contient le magasin (clé exacte). */
    public static function sqlStoredJsonContains(string $columnExpr, string $storeParamName): string
    {
        $jsonArray = 'CASE
            WHEN json_valid(' . $columnExpr . ") AND json_type(" . $columnExpr . ") = 'array'
            THEN " . $columnExpr . "
            ELSE '[]'
        END";

        return '(
            EXISTS (
                SELECT 1
                FROM json_each(' . $jsonArray . ') AS store_row
                WHERE json_extract(store_row.value, \'$.store\') = ' . $storeParamName . '
            )
            OR ' . self::sqlImplicitConsoleStoreMatch($storeParamName) . '
        )';
    }

    /** Jeux démat console sans JSON explicite (store implicite selon la plateforme). */
    public static function sqlImplicitConsoleStoreMatch(string $storeParamName): string
    {
        if (!GamePlatformRegistry::isAvailable()) {
            return '0';
        }

        return '(
            oj.is_digital = 1
            AND TRIM(COALESCE(oj.digital_stores, \'\')) IN (\'\', \'[]\')
            AND EXISTS (
                SELECT 1 FROM game_platform gp
                WHERE gp.console_store = ' . $storeParamName . '
                  AND gp.active = 1
                  AND (
                    ' . GamePlatformList::sqlCsvContains('b.owned_platforms', 'gp.platform_key') . '
                    OR ' . GamePlatformList::sqlCsvContains('oj.platforms', 'gp.platform_key') . '
                    OR oj.platform = gp.platform_key
                  )
            )
        )';
    }

    private static function normalizeStoreKey(string $raw): string
    {
        $raw = mb_strtolower(trim($raw));

        return match ($raw) {
            'steam', 'steam store' => self::STEAM,
            'gog', 'gog.com', 'good old games' => self::GOG,
            'epic', 'epic games', 'epic games store' => self::EPIC,
            'battlenet', 'battle.net', 'battle net' => self::BATTLENET,
            'psn', 'playstation store', 'playstation' => self::PSN,
            'xbox', 'microsoft store', 'xbox store' => self::XBOX,
            'eshop', 'nintendo eshop', 'eshop nintendo' => self::ESHOP,
            default => $raw,
        };
    }
}
