<?php
/**
 * Sérialisation des champs catalogue dans une proposition utilisateur.
 */

declare(strict_types=1);

namespace Moncine;

final class CatalogSubmissionPayload
{
    /** Champs stockés dans payload_json (hors métadonnées techniques). */
    private const STORED_KEYS = [
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
    ];

    /**
     * @param array<string, mixed> $manualEditData Sortie de FilmManualEdit::parseFromPost
     * @return array<string, mixed>
     */
    public static function fromManualEditData(array $manualEditData): array
    {
        $out = [];
        foreach (self::STORED_KEYS as $key) {
            if (array_key_exists($key, $manualEditData)) {
                $out[$key] = $manualEditData[$key];
            }
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

        return self::fromManualEditData($data);
    }

    public static function encode(array $payload): string
    {
        $clean = self::fromManualEditData($payload);

        return json_encode($clean, JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR);
    }

    /**
     * Données pour CatalogAdmin::createOeuvre().
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

    /** @return array<string, mixed> Pour préremplir le formulaire (clés formulaire). */
    public static function toFormPrefill(array $stored): array
    {
        $film = self::fromManualEditData($stored);
        $film['duree'] = FilmManualEdit::dureeForInput((int) ($film['duree_min'] ?? 0));
        unset($film['duree_min'], $film['tmdb_types_locked']);

        return $film;
    }
}
