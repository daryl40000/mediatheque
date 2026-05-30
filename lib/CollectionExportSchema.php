<?php
/**
 * Schéma unique export / import films (CSV et ODS).
 * Toute nouvelle colonne Moncine doit être ajoutée ici.
 */

declare(strict_types=1);

namespace Moncine;

final class CollectionExportSchema
{
    /** Colonnes historique (feuille ODS). */
    public const HISTORIQUE_HEADERS = [
        'Titre',
        'Réalisateur',
        'Date vue',
        'Note',
    ];

    /**
     * Colonnes feuille Films : clé interne => libellé exporté.
     *
     * @var array<string, string>
     */
    public const FILM_COLUMNS = [
        'oeuvre_id' => 'ID catalogue',
        'titre' => 'Titre',
        'titre_original' => 'Titre original',
        'realisateur' => 'Réalisateur',
        'duree_min' => 'Durée',
        'format_image' => 'format image',
        'format_son' => 'Bande sonore FR',
        'support_physique' => 'Support',
        'styles' => 'Style',
        'saga' => 'Saga',
        'saga_ordre' => 'N° saga',
        'vu' => 'Vu',
        'note' => 'Note',
        'annee' => 'Année',
        'nationalite' => 'Nationalité',
        'acteur_1' => 'Acteur 1',
        'acteur_2' => 'Acteur 2',
        'acteur_3' => 'Acteur 3',
        'synopsis' => 'Synopsis',
        'poster_url' => 'Affiche (URL ou /posters/)',
        'tmdb_id' => 'TMDB ID',
        'tmdb_media_type' => 'Type TMDB',
        'statut' => 'Statut',
    ];

    /**
     * Alias d’en-têtes reconnus à l’import (minuscules, sans accents — voir normalizeHeader).
     *
     * @var array<string, list<string>>
     */
    public const FILM_COLUMN_ALIASES = [
        'oeuvre_id' => [
            'id catalogue',
            'oeuvre_id',
            'oeuvre id',
            'id oeuvre',
            'id film',
            'film_id',
            'id',
        ],
        'titre' => ['titre', 'title', 'nom', 'film'],
        'titre_original' => ['titre original', 'titre_original', 'original title', 'original_title'],
        'realisateur' => ['realisateur', 'director', 'auteur'],
        'duree_min' => ['duree', 'duration'],
        'format_image' => ['format image', 'format_image', 'image', 'video'],
        'format_son' => [
            'format son',
            'format_son',
            'bande sonore fr',
            'bande sonore',
            'son',
            'audio',
            'sound',
        ],
        'support_physique' => [
            'support',
            'support physique',
            'support_physique',
            'type support',
            'media',
        ],
        'styles' => ['style', 'styles', 'genre', 'genres', 'categorie', 'category'],
        'saga' => ['saga', 'suite', 'franchise', 'serie films', 'serie de films'],
        'saga_ordre' => [
            'n saga',
            'n° saga',
            'no saga',
            'numero saga',
            'numero dans la saga',
            'ordre saga',
            'saga ordre',
            'saga_ordre',
            'episode',
            'partie',
        ],
        'vu' => ['vu', 'vu le', 'date vu', 'visionne'],
        'note' => ['note', 'notes', 'notation'],
        'annee' => ['annee', 'year'],
        'nationalite' => [
            'nationalite',
            'nationalité',
            'pays',
            'pays de production',
            'pays principal',
            'country',
            'countries',
            'origin country',
        ],
        'acteur_1' => ['acteur 1', 'acteur_1', 'acteur1'],
        'acteur_2' => ['acteur 2', 'acteur_2', 'acteur2'],
        'acteur_3' => ['acteur 3', 'acteur_3', 'acteur3'],
        'synopsis' => ['synopsis', 'resume', 'résumé'],
        'poster_url' => ['affiche url', 'affiche_url', 'poster url', 'poster_url', 'affiche'],
        'tmdb_id' => ['tmdb id', 'tmdb_id', 'tmdb'],
        'tmdb_media_type' => [
            'type tmdb',
            'tmdb type',
            'tmdb_media_type',
            'type media tmdb',
            'categorie tmdb',
            'catégorie tmdb',
        ],
        'statut' => [
            'statut',
            'status',
            'wishlist',
            'liste',
            'a acheter',
            'à acheter',
            'mes envies',
            'souhait',
            'souhaits',
            'collection',
        ],
    ];

    /** @var array<string, list<string>> */
    public const HISTORIQUE_COLUMN_ALIASES = [
        'titre' => ['titre', 'title', 'nom', 'film'],
        'realisateur' => ['realisateur', 'director', 'auteur'],
        'date_vue' => ['date vue', 'date_vue', 'vu', 'date'],
        'note' => ['note', 'notes', 'notation'],
    ];

    /** @return list<string> */
    public static function filmHeaders(): array
    {
        return array_values(self::FILM_COLUMNS);
    }

    /** @return list<string> Clés stockées en base (hors vu / note). */
    public static function filmDatabaseFields(): array
    {
        return array_values(array_filter(
            array_keys(self::FILM_COLUMNS),
            static fn (string $key): bool => !in_array($key, ['vu', 'note', 'statut', 'oeuvre_id'], true)
        ));
    }

    /**
     * Champs mis à jour lors d’un ré-import si la colonne est présente dans le fichier.
     * titre + realisateur forment la clé unique (toujours lus, jamais écrasés par excluded vide).
     *
     * @return list<string>
     */
    public static function filmMergeOnConflictFields(): array
    {
        return array_values(array_filter(
            self::filmDatabaseFields(),
            static fn (string $key): bool => !in_array($key, ['titre', 'realisateur'], true)
        ));
    }

    /**
     * Construit une ligne export à partir d’une fiche film (+ dernière vue / note).
     *
     * @param array<string, mixed> $film
     * @return list<string>
     */
    public static function filmToExportRow(array $film): array
    {
        $note = $film['derniere_note'] ?? null;
        $row = [];

        foreach (self::FILM_COLUMNS as $key => $_label) {
            $row[] = match ($key) {
                'oeuvre_id' => (int) ($film['oeuvre_id'] ?? $film['id'] ?? 0) > 0
                    ? (string) (int) ($film['oeuvre_id'] ?? $film['id'])
                    : '',
                'duree_min' => self::formatDureeForExport((int) ($film['duree_min'] ?? 0)),
                'support_physique' => SupportPhysique::label((string) ($film['support_physique'] ?? '')),
                'vu' => self::formatVueDateForExport((string) ($film['derniere_vue'] ?? '')),
                'note' => $note !== null && $note !== '' ? (string) $note : '',
                'annee' => (int) ($film['annee'] ?? 0) > 0 ? (string) (int) $film['annee'] : '',
                'saga' => trim((string) ($film['saga'] ?? '')),
                'saga_ordre' => (int) ($film['saga_ordre'] ?? 0) > 0
                    && trim((string) ($film['saga'] ?? '')) !== ''
                    ? (string) (int) $film['saga_ordre']
                    : '',
                'nationalite' => TmdbCountries::formatNationaliteList((string) ($film['nationalite'] ?? '')),
                'tmdb_id' => (int) ($film['tmdb_id'] ?? 0) > 0 ? (string) (int) $film['tmdb_id'] : '',
                'tmdb_media_type' => self::formatTmdbTypeForExport($film),
                'statut' => LibraryStatut::label((string) ($film['statut'] ?? LibraryStatut::COLLECTION)),
                default => (string) ($film[$key] ?? ''),
            };
        }

        return $row;
    }

    /** @param array<string, mixed> $film */
    public static function formatTmdbTypeForExport(array $film): string
    {
        if ((int) ($film['tmdb_id'] ?? 0) <= 0) {
            return '';
        }

        return TmdbMediaType::label(
            (string) ($film['tmdb_media_type'] ?? ''),
            (string) ($film['tmdb_tv_kind'] ?? '')
        );
    }

    public static function formatDureeForExport(int $minutes): string
    {
        if ($minutes <= 0) {
            return '';
        }
        $h = intdiv($minutes, 60);
        $m = $minutes % 60;
        if ($h > 0 && $m > 0) {
            return $h . 'h' . $m;
        }
        if ($h > 0) {
            return $h . 'h';
        }

        return (string) $minutes;
    }

    /** Libellés des colonnes exportées (pour aide à l’import). */
    public static function filmColumnLabelsText(): string
    {
        return implode(', ', self::filmHeaders());
    }

    public static function formatVueDateForExport(string $isoDate): string
    {
        $isoDate = trim($isoDate);
        if ($isoDate === '') {
            return '';
        }
        if (preg_match('/^(\d{4})-(\d{2})-(\d{2})/', $isoDate, $m)) {
            return $m[3] . '/' . $m[2] . '/' . $m[1];
        }

        return $isoDate;
    }
}
