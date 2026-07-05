<?php
/**
 * Sérialisation des champs catalogue dans une proposition utilisateur (films et jeux).
 */

declare(strict_types=1);

namespace Moncine;

final class CatalogSubmissionPayload
{
    /** Champs films stockés dans payload_json. */
    private const FILM_KEYS = [
        'titre',
        'titre_original',
        'realisateur',
        'duree_min',
        'styles',
        'annee',
        'nationalite',
        'tmdb_id',
        'tmdb_media_type',
        'tmdb_tv_kind',
        'tmdb_types_locked',
        'acteur_1',
        'acteur_2',
        'acteur_3',
        'poster_url',
        'synopsis',
        'moncine_kind',
        'submission_domain',
    ];

    /** Champs jeux stockés dans payload_json. */
    private const GAME_KEYS = [
        'titre',
        'titre_original',
        'annee',
        'studio',
        'editeur',
        'genre',
        'platform',
        'platforms',
        'platform_list',
        'synopsis',
        'poster_url',
        'is_extension',
        'base_game_oeuvre_id',
        'is_remake',
        'original_game_oeuvre_id',
        'igdb_id',
        'submission_domain',
    ];

    public static function domain(array $stored): string
    {
        $raw = (string) ($stored['submission_domain'] ?? '');

        return $raw !== '' ? MediaDomain::normalize($raw) : MediaDomain::FILM;
    }

    public static function isGame(array $stored): bool
    {
        return MediaDomain::isGame(self::domain($stored));
    }

    /**
     * @param array<string, mixed> $manualEditData Sortie de FilmManualEdit ou GameManualEdit
     * @return array<string, mixed>
     */
    public static function fromManualEditData(array $manualEditData): array
    {
        $keys = self::isGame($manualEditData) ? self::GAME_KEYS : self::FILM_KEYS;
        $out = [];
        foreach ($keys as $key) {
            if (array_key_exists($key, $manualEditData)) {
                $out[$key] = $manualEditData[$key];
            }
        }

        if (!isset($out['submission_domain'])) {
            $out['submission_domain'] = self::isGame($manualEditData)
                ? MediaDomain::JEU
                : MediaDomain::FILM;
        }

        return $out;
    }

    /**
     * @param array<string, mixed>|null $stored
     * @return array<string, mixed>
     */
    public static function decode(?string $json): array
    {
        if ($json === null || trim($json) === '') {
            return [];
        }

        $data = json_decode($json, true, 512, JSON_THROW_ON_ERROR);
        if (!is_array($data)) {
            return [];
        }

        $out = self::fromManualEditData($data);
        $steam = self::steamImportMeta($data);
        if ($steam !== null) {
            $out['steam_import'] = $steam;
        }

        return $out;
    }

    public static function encode(array $payload): string
    {
        $clean = self::fromManualEditData($payload);
        $steam = self::steamImportMeta($payload);
        if ($steam !== null) {
            $clean['steam_import'] = $steam;
        }

        return json_encode($clean, JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR);
    }

    /**
     * Données pour CatalogAdmin::createOeuvre() (films).
     *
     * @param array<string, mixed> $stored
     * @return array<string, mixed>
     */
    public static function toCreateOeuvreData(array $stored): array
    {
        $types = FilmManualEdit::resolveTmdbTypesForSave($stored, []);

        return array_merge(self::fromManualEditData($stored), [
            'oeuvre_id' => 0,
            'tmdb_media_type' => $types['media_type'],
            'tmdb_tv_kind' => $types['tv_kind'],
            'realisateur_tmdb_id' => 0,
            'acteur_1_tmdb_id' => 0,
            'acteur_2_tmdb_id' => 0,
            'acteur_3_tmdb_id' => 0,
            'omdb_imdb_id' => '',
            'omdb_enriched_at' => null,
        ]);
    }

    /**
     * Données pour CatalogAdmin::createGameOeuvre() (jeux).
     *
     * @param array<string, mixed> $stored
     * @return array<string, mixed>
     */
    public static function toCreateGameData(array $stored): array
    {
        $clean = self::fromManualEditData($stored);

        return array_merge($clean, [
            'oeuvre_id' => 0,
        ]);
    }

    /** @return array<string, mixed> Pour préremplir le formulaire (clés formulaire). */
    public static function toFormPrefill(array $stored): array
    {
        if (self::isGame($stored)) {
            $game = self::fromManualEditData($stored);
            $game['platform_list'] = $game['platform_list']
                ?? GamePlatformList::parseList((string) ($game['platforms'] ?? ''));
            $game['genre_list'] = GameGenre::parseList((string) ($game['genre'] ?? ''));

            return $game;
        }

        $film = self::fromManualEditData($stored);
        $film['duree'] = FilmManualEdit::dureeForInput((int) ($film['duree_min'] ?? 0));
        unset($film['duree_min'], $film['tmdb_types_locked']);

        return $film;
    }

    /**
     * Métadonnées import Steam conservées dans une proposition (ajout bibliothèque différé).
     *
     * @param array<string, mixed> $stored
     * @return array{appid: int, playtime_forever: int, rtime_last_played: int, img_icon_url: string}|null
     */
    public static function steamImportMeta(array $stored): ?array
    {
        $raw = $stored['steam_import'] ?? null;
        if (!is_array($raw)) {
            return null;
        }

        $appid = (int) ($raw['appid'] ?? 0);
        if ($appid <= 0) {
            return null;
        }

        return [
            'appid' => $appid,
            'playtime_forever' => max(0, (int) ($raw['playtime_forever'] ?? 0)),
            'rtime_last_played' => max(0, (int) ($raw['rtime_last_played'] ?? 0)),
            'img_icon_url' => trim((string) ($raw['img_icon_url'] ?? '')),
        ];
    }

    /**
     * @param array<string, mixed> $manualEditData
     * @return array<string, mixed>
     */
    public static function withSteamImportMeta(array $manualEditData, array $steamImport): array
    {
        $appid = (int) ($steamImport['appid'] ?? 0);
        if ($appid <= 0) {
            return $manualEditData;
        }

        $manualEditData['steam_import'] = [
            'appid' => $appid,
            'playtime_forever' => max(0, (int) ($steamImport['playtime_forever'] ?? 0)),
            'rtime_last_played' => max(0, (int) ($steamImport['rtime_last_played'] ?? 0)),
            'img_icon_url' => trim((string) ($steamImport['img_icon_url'] ?? '')),
        ];

        return $manualEditData;
    }

    public static function domainLabel(array $stored): string
    {
        return MediaDomain::label(self::domain($stored));
    }
}
