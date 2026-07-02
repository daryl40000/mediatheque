<?php
/**
 * Lecture des champs formulaire jeux (POST → tableaux pour le catalogue / la bibliothèque).
 */

declare(strict_types=1);

namespace Moncine;

final class GameFormPayload
{
    /** @param array<string, mixed> $post */
    public static function nonPretableFromPost(array $post): bool
    {
        return !empty($post['non_pretable']);
    }

    /**
     * @param array<string, mixed> $post
     * @return array{platform: string, platforms: string, platform_list: list<string>}
     */
    public static function catalogPlatformsFromPost(array $post): array
    {
        return self::resolveCatalogPlatformFields($post);
    }

    /**
     * @param array<string, mixed> $post
     * @return array{owned_platforms: string, owned_platform_list: list<string>}
     */
    public static function ownedPlatformsFromPost(array $post, string $catalogPlatformsCsv): array
    {
        $ownedCsv = GamePlatformList::normalizeOwnedFromPost(
            $post['owned_platforms'] ?? [],
            GamePlatformList::parseList($catalogPlatformsCsv)
        );
        if ($ownedCsv === '' && isset($post['platform'])) {
            $legacy = GamePlatform::normalize((string) $post['platform']);
            $catalogKeys = GamePlatformList::parseList($catalogPlatformsCsv);
            if ($legacy !== '' && in_array($legacy, $catalogKeys, true)) {
                $ownedCsv = $legacy;
            }
        }

        return [
            'owned_platforms' => $ownedCsv,
            'owned_platform_list' => GamePlatformList::parseList($ownedCsv),
        ];
    }

    /**
     * @param array<string, mixed> $data
     * @return array{platform: string, platforms: string, platform_list: list<string>}
     */
    public static function resolveCatalogPlatformFields(array $data): array
    {
        $keys = [];
        if (isset($data['platform_list']) && is_array($data['platform_list'])) {
            $keys = GamePlatformList::parseList(GamePlatformList::serializeList($data['platform_list']));
        } elseif (isset($data['platforms'])) {
            if (is_array($data['platforms'])) {
                $keys = GamePlatformList::parseList(GamePlatformList::serializeList($data['platforms']));
            } else {
                $keys = GamePlatformList::parseList((string) $data['platforms']);
            }
        } elseif (isset($data['platform'])) {
            $single = GamePlatform::normalize((string) $data['platform']);
            if ($single !== '') {
                $keys = [$single];
            }
        }

        $platformsCsv = GamePlatformList::serializeList($keys);
        $primary = GamePlatformList::primaryKey($keys);

        return [
            'platform' => $primary,
            'platforms' => $platformsCsv,
            'platform_list' => $keys,
        ];
    }

    /**
     * @param array<string, mixed> $post
     * @return array{physical_supports: string, digital_stores: string, is_digital: bool}
     */
    public static function editionPayloadFromPost(array $post): array
    {
        $keys = GamePlatform::selectedKeysFromPost($post, 'owned_platforms');
        if ($keys === []) {
            $keys = GamePlatform::selectedKeysFromPost($post, 'platforms', 'platform');
        }
        $physicalSupports = GameSchema::hasEditionColumns()
            ? GamePhysicalSupport::normalizeFromPost($post['physical_supports'] ?? [])
            : '';
        $digitalStores = GameSchema::hasEditionColumns()
            ? GameDigitalStore::buildFromPostForPlatforms($post, $keys)
            : '';
        $isDigital = !empty($post['is_digital'])
            || GameDigitalStore::hasDigitalEdition($digitalStores, false);

        return [
            'physical_supports' => $physicalSupports,
            'digital_stores' => $digitalStores,
            'is_digital' => $isDigital,
        ];
    }

    /** @param array<string, mixed> $post */
    public static function linuxFlagsFromPost(array $post): array
    {
        $keys = GamePlatform::selectedKeysFromPost($post, 'owned_platforms');
        if ($keys === []) {
            $keys = GamePlatform::selectedKeysFromPost($post, 'platforms', 'platform');
        }
        if (!in_array(GamePlatform::PC, $keys, true)) {
            return [
                'tested_on_linux' => false,
                'linux_not_supported' => false,
            ];
        }

        $notSupported = !empty($post['linux_not_supported']);
        $tested = !$notSupported && !empty($post['tested_on_linux']);

        return [
            'tested_on_linux' => $tested,
            'linux_not_supported' => $notSupported,
        ];
    }

    /** @deprecated Utiliser linuxFlagsFromPost() */
    public static function testedOnLinuxFromPost(array $post): bool
    {
        return self::linuxFlagsFromPost($post)['tested_on_linux'];
    }

    /**
     * @param array<string, mixed> $post
     * @return array<string, mixed>
     */
    public static function catalogPayloadFromPost(array $post): array
    {
        return [
            'oeuvre_id' => max(0, (int) ($post['oeuvre_id'] ?? 0)),
            'titre' => trim((string) ($post['titre'] ?? '')),
            'titre_original' => trim((string) ($post['titre_original'] ?? '')),
            'annee' => max(0, (int) ($post['annee'] ?? 0)),
            'studio' => trim((string) ($post['studio'] ?? '')),
            'editeur' => trim((string) ($post['editeur'] ?? '')),
            'genre' => GameGenre::normalizeFromPost($post['genres'] ?? []),
            'franchise' => trim((string) ($post['franchise'] ?? '')),
            'game_mode' => GameGenre::normalizeFromPost($post['game_modes'] ?? []),
            'theme' => GameGenre::normalizeFromPost($post['themes'] ?? []),
            'alternative_names' => GameGenre::normalizeFromPost($post['alternative_names'] ?? []),
            'platform' => GamePlatform::normalize((string) ($post['platform'] ?? '')),
            'synopsis' => trim((string) ($post['synopsis'] ?? '')),
            'poster_url' => SecureUrl::sanitizePosterUrl((string) ($post['poster_url'] ?? '')),
            'is_extension' => !empty($post['is_extension']),
            'base_game_oeuvre_id' => max(0, (int) ($post['base_game_oeuvre_id'] ?? 0)),
            'is_remake' => !empty($post['is_remake']),
            'original_game_oeuvre_id' => max(0, (int) ($post['original_game_oeuvre_id'] ?? 0)),
        ];
    }
}
