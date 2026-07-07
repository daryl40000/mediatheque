<?php
/**
 * Formatage et enrichissement des lignes jeux (catalogue / bibliothèque).
 */

declare(strict_types=1);

namespace Moncine;

final class GameRowMapper
{
    /** Titre affiché (français prioritaire, sinon anglais IGDB). */
    public static function displayTitle(array $row): string
    {
        return GameTitle::displayTitle($row);
    }

    /** Résumé supports pour listes (ex. « CD/DVD · Steam »). */
    public static function editionSummary(array $row): string
    {
        $parts = GamePhysicalSupport::displayLabels((string) ($row['physical_supports'] ?? ''));
        $parts = array_merge($parts, GameDigitalStore::summaryLabels((string) ($row['digital_stores'] ?? '')));

        if ($parts === [] && !empty($row['is_digital'])) {
            $parts[] = 'Démat';
        }

        return implode(' · ', $parts);
    }

    /** Libellé affiché pour autocomplétion (ex. « Elden Ring (PS5 · 2022) »). */
    public static function displayLabel(array $row): string
    {
        $titre = self::displayTitle($row);
        if ($titre === '') {
            return '';
        }

        $parts = [];
        $platformKeys = GamePlatformList::ownedKeysFromRow($row);
        if ($platformKeys === []) {
            $platformKeys = GamePlatformList::catalogKeysFromRow($row);
        }
        $platformDisplay = GamePlatformList::shortLabelsDisplay($platformKeys);
        if ($platformDisplay !== '') {
            $parts[] = $platformDisplay;
        }
        $annee = (int) ($row['annee'] ?? 0);
        if ($annee > 0) {
            $parts[] = (string) $annee;
        }
        if ($parts === []) {
            return $titre;
        }

        return $titre . ' (' . implode(' · ', $parts) . ')';
    }

    public static function formatAddedAt(string $createdAt): string
    {
        $createdAt = trim($createdAt);
        if ($createdAt === '') {
            return '';
        }

        return HistoriqueRepository::formatDateVue(substr($createdAt, 0, 10));
    }

    public static function formatFinishedAt(string $completedAt): string
    {
        $completedAt = trim($completedAt);
        if ($completedAt === '') {
            return '';
        }

        return HistoriqueRepository::formatDateVue(substr($completedAt, 0, 10));
    }

    public static function formatSteamPlaytime(int $minutes): string
    {
        return GamePlaytime::format($minutes);
    }

    /**
     * @param array<string, mixed> $row
     * @return array{bib_id:int, oeuvre_id:int, titre:string, annee:int, poster_url:string, platform_short:string, display_label:string}
     */
    public static function hydrateLinkedLibraryGameRow(array $row): array
    {
        $titre = GameTitle::displayTitle($row);
        $annee = (int) ($row['annee'] ?? 0);
        $platformKeys = GamePlatformList::ownedKeysFromRow($row);
        if ($platformKeys === []) {
            $platformKeys = GamePlatformList::catalogKeysFromRow($row);
        }
        $platformShort = GamePlatformList::shortLabelsDisplay($platformKeys);

        return [
            'bib_id' => (int) ($row['bib_id'] ?? 0),
            'oeuvre_id' => (int) ($row['oeuvre_id'] ?? 0),
            'titre' => $titre,
            'annee' => $annee,
            'poster_url' => (string) ($row['poster_url'] ?? ''),
            'platform_short' => $platformShort,
            'display_label' => self::linkedGameDisplayLabel($titre, $annee, $platformShort),
        ];
    }

    /**
     * @param array<string, mixed> $row
     * @return array<string, mixed>
     */
    public static function hydrateGameRow(array $row): array
    {
        $row = self::hydrateCatalogFields($row);
        $row['id'] = (int) ($row['id'] ?? 0);
        $row['edition_icon_keys'] = GameEditionIcons::iconKeys($row);
        $row['added_at_label'] = self::formatAddedAt((string) ($row['created_at'] ?? ''));
        $row['finished_at_label'] = self::formatFinishedAt((string) ($row['derniere_completion'] ?? ''));
        $row['completion_count'] = (int) ($row['completion_count'] ?? 0);
        $row = GamePlaytime::hydrateRow($row);
        $row['is_pc'] = in_array(GamePlatform::PC, GamePlatformList::ownedKeysFromRow($row), true)
            || in_array(GamePlatform::PC, GamePlatformList::catalogKeysFromRow($row), true);
        $row['tested_on_linux'] = !empty($row['tested_on_linux']);
        $row['linux_not_supported'] = !empty($row['linux_not_supported']);
        $row['non_pretable'] = !empty($row['non_pretable']);
        $row['linux_badge'] = $row['tested_on_linux']
            ? 'supported'
            : ($row['linux_not_supported'] ? 'unsupported' : '');

        return $row;
    }

    /**
     * @param array<string, mixed> $row
     * @return array<string, mixed>
     */
    public static function hydrateCatalogRow(array $row): array
    {
        return self::hydrateCatalogFields($row);
    }

    /**
     * @param array<string, mixed> $row
     * @return array<string, mixed>
     */
    private static function hydrateCatalogFields(array $row): array
    {
        if (isset($row['oeuvre_id'])) {
            $row['oeuvre_id'] = (int) ($row['oeuvre_id'] ?? 0);
        }
        $row['annee'] = (int) ($row['annee'] ?? 0);
        $row['is_digital'] = !empty($row['is_digital']);
        $row['is_extension'] = !empty($row['is_extension']);
        $row['base_game_oeuvre_id'] = (int) ($row['base_game_oeuvre_id'] ?? 0);
        $row['is_remake'] = !empty($row['is_remake']);
        $row['original_game_oeuvre_id'] = (int) ($row['original_game_oeuvre_id'] ?? 0);
        $row['igdb_id'] = (int) ($row['igdb_id'] ?? 0);
        $row['display_titre'] = self::displayTitle($row);
        $row['platform_list'] = GamePlatformList::catalogKeysFromRow($row);
        $row['owned_platform_list'] = GamePlatformList::ownedKeysFromRow($row);
        $row['platform_label'] = GamePlatformList::shortLabelsDisplay(
            $row['owned_platform_list'] !== [] ? $row['owned_platform_list'] : $row['platform_list']
        );
        if ($row['platform_label'] === '') {
            $row['platform_label'] = GamePlatform::label((string) ($row['platform'] ?? ''));
        }
        $row['platform_short'] = $row['platform_label'];
        $row['display_label'] = self::displayLabel($row);
        $row['genre_list'] = GameGenre::parseList((string) ($row['genre'] ?? ''));
        $row['genre_label'] = GameGenre::displayLabel((string) ($row['genre'] ?? ''));
        $row['game_mode_list'] = GameGenre::parseList((string) ($row['game_mode'] ?? ''));
        $row['theme_list'] = GameGenre::parseList((string) ($row['theme'] ?? ''));
        $row['alternative_name_list'] = GameGenre::parseList((string) ($row['alternative_names'] ?? ''));
        $row['physical_support_list'] = GamePhysicalSupport::parseList((string) ($row['physical_supports'] ?? ''));
        $row['physical_support_labels'] = GamePhysicalSupport::displayLabels((string) ($row['physical_supports'] ?? ''));
        $row['digital_store_list'] = GameDigitalStore::parseStoredList((string) ($row['digital_stores'] ?? ''));
        $row['has_digital_edition'] = GameDigitalStore::hasDigitalEdition(
            (string) ($row['digital_stores'] ?? ''),
            !empty($row['is_digital'])
        );
        $row['edition_summary'] = self::editionSummary($row);
        $row = self::hydrateCatalogStoreLinks($row);

        return $row;
    }

    /**
     * @param array<string, mixed> $row
     * @return array<string, mixed>
     */
    private static function hydrateCatalogStoreLinks(array $row): array
    {
        $oeuvreId = (int) ($row['oeuvre_id'] ?? 0);
        $row['catalog_store_urls'] = [];

        if ($oeuvreId > 0 && OeuvreStoreLinkRepository::isAvailable()) {
            $row['catalog_store_urls'] = (new OeuvreStoreLinkRepository())->listVerifiedUrlsForOeuvre($oeuvreId);
        }

        foreach ($row['catalog_store_urls'] as $store => $url) {
            $row['catalog_store_url_' . $store] = $url;
        }

        return $row;
    }

    private static function linkedGameDisplayLabel(string $titre, int $annee, string $platformShort = ''): string
    {
        $parts = [];
        if ($platformShort !== '') {
            $parts[] = $platformShort;
        }
        if ($annee > 0) {
            $parts[] = (string) $annee;
        }
        if ($parts === []) {
            return $titre;
        }

        return $titre . ' (' . implode(' · ', $parts) . ')';
    }
}
