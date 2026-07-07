<?php
/**
 * Icônes de support / magasin pour les listes jeux.
 */

declare(strict_types=1);

namespace Moncine;

final class GameEditionIcons
{
    public const CD_DVD = 'cd_dvd';
    public const DISKETTE = 'disquette';
    public const STEAM = 'steam';
    public const GOG = 'gog';
    public const EPIC = 'epic';
    public const BATTLENET = 'battlenet';

    /** @return list<string> clés d’icônes à afficher (ordre stable) */
    public static function iconKeys(array $gameRow): array
    {
        $keys = [];

        foreach (GamePhysicalSupport::parseList((string) ($gameRow['physical_supports'] ?? '')) as $support) {
            if ($support === GamePhysicalSupport::CD_DVD) {
                $keys[self::CD_DVD] = self::CD_DVD;
            } elseif ($support === GamePhysicalSupport::DISKETTE) {
                $keys[self::DISKETTE] = self::DISKETTE;
            }
        }

        foreach (GameDigitalStore::parseStoredList((string) ($gameRow['digital_stores'] ?? '')) as $entry) {
            $store = (string) ($entry['store'] ?? '');
            if (isset(self::pcStoreIconMap()[$store])) {
                $keys[$store] = $store;
            }
        }

        return array_values($keys);
    }

    /** Texte pour supports sans icône dédiée (stores console sans logo…). */
    public static function supplementalText(array $gameRow): string
    {
        $parts = [];

        foreach (GamePhysicalSupport::parseList((string) ($gameRow['physical_supports'] ?? '')) as $support) {
            if ($support === GamePhysicalSupport::CD_DVD || $support === GamePhysicalSupport::DISKETTE) {
                continue;
            }
            $parts[] = GamePhysicalSupport::label($support);
        }

        foreach (GameDigitalStore::parseStoredList((string) ($gameRow['digital_stores'] ?? '')) as $entry) {
            $store = (string) ($entry['store'] ?? '');
            if (!isset(self::pcStoreIconMap()[$store])) {
                $parts[] = (string) ($entry['label'] ?? GameDigitalStore::label($store));
            }
        }

        if ($parts === [] && !empty($gameRow['is_digital']) && self::iconKeys($gameRow) === []) {
            $parts[] = 'Démat';
        }

        return implode(' · ', $parts);
    }

    /** @return array<string, string> */
    private static function pcStoreIconMap(): array
    {
        return [
            GameDigitalStore::STEAM => self::STEAM,
            GameDigitalStore::GOG => self::GOG,
            GameDigitalStore::EPIC => self::EPIC,
            GameDigitalStore::BATTLENET => self::BATTLENET,
        ];
    }

    public static function label(string $iconKey): string
    {
        return match ($iconKey) {
            self::CD_DVD => 'CD / DVD',
            self::DISKETTE => 'Disquette/cartouche',
            self::STEAM => 'Steam',
            self::GOG => 'GOG',
            self::EPIC => 'Epic Games Store',
            self::BATTLENET => 'Battle.net',
            default => $iconKey,
        };
    }

    /** Lien magasin pour une icône (Steam, GOG, Epic, Battle.net) si connu. */
    public static function linkUrlForKey(string $iconKey, array $gameRow): string
    {
        $storeKey = match ($iconKey) {
            self::STEAM => GameDigitalStore::STEAM,
            self::GOG => GameDigitalStore::GOG,
            self::EPIC => GameDigitalStore::EPIC,
            self::BATTLENET => GameDigitalStore::BATTLENET,
            default => '',
        };
        if ($storeKey === '') {
            return '';
        }

        $catalogUrls = is_array($gameRow['catalog_store_urls'] ?? null) ? $gameRow['catalog_store_urls'] : [];
        if (isset($catalogUrls[$storeKey]) && trim((string) $catalogUrls[$storeKey]) !== '') {
            return trim((string) $catalogUrls[$storeKey]);
        }

        $catalogDirect = trim((string) ($gameRow['catalog_store_url_' . $storeKey] ?? ''));
        if ($catalogDirect !== '') {
            return $catalogDirect;
        }

        foreach (GameDigitalStore::parseStoredList((string) ($gameRow['digital_stores'] ?? '')) as $entry) {
            if (($entry['store'] ?? '') !== $storeKey) {
                continue;
            }
            $url = trim((string) ($entry['url'] ?? ''));
            if ($url !== '') {
                return $url;
            }
        }

        if ($storeKey !== GameDigitalStore::STEAM) {
            $slug = trim((string) ($gameRow['store_link_slug_' . $storeKey] ?? ''));
            if ($slug !== '') {
                return match ($storeKey) {
                    GameDigitalStore::GOG => GogCatalogClient::storeUrl($slug),
                    GameDigitalStore::EPIC => EpicCatalogClient::storeUrl($slug),
                    default => '',
                };
            }

            return '';
        }

        $appid = (int) ($gameRow['library_steam_appid'] ?? $gameRow['steam_appid'] ?? 0);
        if ($appid <= 0) {
            return '';
        }

        return SteamWebApiClient::storeUrl($appid, GameTitle::displayTitle($gameRow));
    }

    /** Nom du fichier image dans www/assets/img/game-editions/ (PNG ou WebP). */
    public static function iconImageFilename(string $iconKey): string
    {
        return match ($iconKey) {
            self::CD_DVD => 'cd_dvd',
            self::DISKETTE => 'disquette',
            self::STEAM => 'steam',
            self::GOG => 'gog',
            self::EPIC => 'epic',
            self::BATTLENET => 'battlenet',
            default => '',
        };
    }

    /**
     * URL publique de l’icône si un fichier existe (sinon le template utilise le SVG de secours).
     * Extensions testées : .png, .webp, .svg
     */
    public static function iconImageUrl(string $iconKey): string
    {
        $base = self::iconImageFilename($iconKey);
        if ($base === '') {
            return '';
        }

        $dir = defined('MONCINE_ROOT')
            ? MONCINE_ROOT . '/www/assets/img/game-editions'
            : dirname(__DIR__) . '/www/assets/img/game-editions';

        foreach (['png', 'webp', 'svg'] as $ext) {
            $filename = $base . '.' . $ext;
            if (is_file($dir . '/' . $filename)) {
                return '/assets/img/game-editions/' . $filename;
            }
        }

        return '';
    }
}
