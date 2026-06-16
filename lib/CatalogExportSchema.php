<?php
/**
 * Export / import du catalogue partagé (œuvres) — réservé à l’administrateur.
 */

declare(strict_types=1);

namespace Moncine;

final class CatalogExportSchema
{
    public const SHEET_CATALOGUE = 'Catalogue';

    /**
     * Colonnes catalogue : clé => libellé exporté.
     *
     * @var array<string, string>
     */
    public const COLUMNS = [
        'oeuvre_id' => 'ID catalogue',
        'titre' => 'Titre',
        'titre_original' => 'Titre original',
        'realisateur' => 'Réalisateur',
        'duree_min' => 'Durée',
        'styles' => 'Style',
        'annee' => 'Année',
        'nationalite' => 'Nationalité',
        'acteur_1' => 'Acteur 1',
        'acteur_2' => 'Acteur 2',
        'acteur_3' => 'Acteur 3',
        'synopsis' => 'Synopsis',
        'poster_url' => 'Affiche (URL ou /posters/)',
        'tmdb_id' => 'TMDB ID',
        'tmdb_media_type' => 'Type TMDB',
        'moncine_kind' => 'Catégorie Moncine',
    ] + CatalogDomainExtensions::EXTENSION_COLUMNS;

    /** @var array<string, list<string>> */
    public const COLUMN_ALIASES = [
        'oeuvre_id' => [
            'id catalogue',
            'oeuvre_id',
            'oeuvre id',
            'id oeuvre',
            'id film',
            'film_id',
            'id',
            'id moncine',
        ],
        'titre' => ['titre', 'title', 'nom', 'film'],
        'titre_original' => ['titre original', 'titre_original', 'original title'],
        'realisateur' => ['realisateur', 'director', 'auteur'],
        'duree_min' => ['duree', 'duration'],
        'styles' => ['style', 'styles', 'genre', 'genres'],
        'annee' => ['annee', 'year'],
        'nationalite' => [
            'nationalite',
            'nationalité',
            'pays',
            'country',
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
        ],
        'moncine_kind' => [
            'categorie moncine',
            'moncine_kind',
            'type contenu',
            'categorie',
        ],
    ] + CatalogDomainExtensions::COLUMN_ALIASES;

    /** @return list<string> */
    public static function headers(): array
    {
        return array_values(self::COLUMNS);
    }

    /** @return list<string> */
    public static function oeuvreDatabaseFields(): array
    {
        $extensionKeys = array_keys(CatalogDomainExtensions::EXTENSION_COLUMNS);

        return array_values(array_filter(
            array_keys(self::COLUMNS),
            static fn (string $key): bool => $key !== 'oeuvre_id'
                && !in_array($key, $extensionKeys, true)
        ));
    }

    /**
     * @param array<string, mixed> $oeuvre
     * @return list<string>
     */
    public static function rowToExport(array $oeuvre): array
    {
        $row = [];
        $kind = MoncineContentKind::normalize((string) ($oeuvre['moncine_kind'] ?? ''));

        foreach (self::COLUMNS as $key => $_label) {
            if (array_key_exists($key, CatalogDomainExtensions::EXTENSION_COLUMNS)) {
                continue;
            }
            $row[] = match ($key) {
                'oeuvre_id' => (string) (int) ($oeuvre['id'] ?? $oeuvre['oeuvre_id'] ?? 0),
                'duree_min' => CollectionExportSchema::formatDureeForExport((int) ($oeuvre['duree_min'] ?? 0)),
                'annee' => (int) ($oeuvre['annee'] ?? 0) > 0 ? (string) (int) $oeuvre['annee'] : '',
                'nationalite' => TmdbCountries::formatNationaliteList((string) ($oeuvre['nationalite'] ?? '')),
                'tmdb_id' => (int) ($oeuvre['tmdb_id'] ?? 0) > 0 ? (string) (int) $oeuvre['tmdb_id'] : '',
                'tmdb_media_type' => CollectionExportSchema::formatTmdbTypeForExport($oeuvre),
                'moncine_kind' => $kind,
                default => (string) ($oeuvre[$key] ?? ''),
            };
        }

        return array_merge($row, CatalogDomainExtensions::extensionValuesForExport($oeuvre));
    }

    public static function columnLabelsText(): string
    {
        return implode(', ', self::headers());
    }
}
