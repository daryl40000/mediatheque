<?php
/**
 * Éditions dématérialisées : magasins PC (Steam, GOG, Epic) ou store console.
 */

declare(strict_types=1);

namespace Moncine;

final class GameDigitalStore
{
    public const STEAM = 'steam';
    public const GOG = 'gog';
    public const EPIC = 'epic';
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

    /** Condition SQL : le JSON `digital_stores` contient le magasin (paramètre LIKE). */
    public static function sqlStoredJsonContains(string $columnExpr, string $paramName): string
    {
        return '(' . $columnExpr . ' LIKE ' . $paramName . ')';
    }

    private static function normalizeStoreKey(string $raw): string
    {
        $raw = mb_strtolower(trim($raw));

        return match ($raw) {
            'steam', 'steam store' => self::STEAM,
            'gog', 'gog.com', 'good old games' => self::GOG,
            'epic', 'epic games', 'epic games store' => self::EPIC,
            'psn', 'playstation store', 'playstation' => self::PSN,
            'xbox', 'microsoft store', 'xbox store' => self::XBOX,
            'eshop', 'nintendo eshop', 'eshop nintendo' => self::ESHOP,
            default => $raw,
        };
    }
}
