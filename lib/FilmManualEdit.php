<?php
/**
 * Validation et préparation des modifications manuelles d’une fiche film.
 */

declare(strict_types=1);

namespace Moncine;

final class FilmManualEdit
{
    /**
     * @param array<string, string> $post Champs du formulaire ($_POST)
     * @return array{ok: true, data: array<string, mixed>}|array{ok: false, error: string}
     */
    public static function parseFromPost(array $post): array
    {
        $titre = trim((string) ($post['titre'] ?? ''));
        if ($titre === '') {
            return ['ok' => false, 'error' => 'Le titre est obligatoire.'];
        }

        $realisateur = trim((string) ($post['realisateur'] ?? ''));
        $dureeMin = ImportCsv::parseDurationMinutes((string) ($post['duree'] ?? ''));

        $anneeRaw = trim((string) ($post['annee'] ?? ''));
        $annee = 0;
        if ($anneeRaw !== '') {
            if (!preg_match('/^\d{4}$/', $anneeRaw)) {
                return ['ok' => false, 'error' => 'L’année doit être sur 4 chiffres (ex. 1982).'];
            }
            $annee = (int) $anneeRaw;
        }

        $posterUrl = trim((string) ($post['poster_url'] ?? ''));
        if ($posterUrl !== '' && !SecureUrl::isValidPosterReference($posterUrl)) {
            return [
                'ok' => false,
                'error' => 'L’affiche doit être une URL HTTPS (ex. TMDB) ou un chemin local /posters/… déjà enregistré.',
            ];
        }

        $tmdbRaw = trim((string) ($post['tmdb_id'] ?? ''));
        $tmdbId = 0;
        $tmdbMediaType = '';
        $tmdbTvKind = '';
        $typesLocked = false;
        if ($tmdbRaw !== '') {
            $ref = TmdbClient::normalizeTmdbReference($tmdbRaw);
            if ($ref === null) {
                return ['ok' => false, 'error' => 'Identifiant TMDB invalide (ex. 78 ou URL /movie/78 ou /tv/1396).'];
            }
            $tmdbId = $ref['id'];
            $tmdbMediaType = TmdbMediaType::normalize($ref['type']);
        }

        $moncineKind = MoncineContentKind::FILM;
        if (array_key_exists('content_kind', $post)) {
            $parsedKind = MoncineContentKind::parseFormValue((string) ($post['content_kind'] ?? ''));
            $moncineKind = $parsedKind['moncine_kind'];
            $mapped = TmdbContentProfile::toTmdbFields($parsedKind['tmdb_profile']);
            $tmdbMediaType = $mapped['media_type'];
            $tmdbTvKind = $mapped['tv_kind'];
            $typesLocked = true;
        } elseif (array_key_exists('tmdb_content_profile', $post)) {
            $profile = TmdbContentProfile::normalize((string) ($post['tmdb_content_profile'] ?? ''));
            if ($profile === '') {
                $profile = TmdbContentProfile::FILM;
            }
            $mapped = TmdbContentProfile::toTmdbFields($profile);
            $tmdbMediaType = $mapped['media_type'];
            $tmdbTvKind = $mapped['tv_kind'];
            $typesLocked = true;
        }

        $saisonNumero = max(0, (int) ($post['saison_numero'] ?? 0));
        $saisonLabel = trim((string) ($post['saison_label'] ?? ''));
        if ($moncineKind !== MoncineContentKind::SERIE) {
            $saisonNumero = 0;
            $saisonLabel = '';
        }

        $saga = trim((string) ($post['saga'] ?? ''));
        $sagaOrdreRaw = trim((string) ($post['saga_ordre'] ?? ''));
        $sagaOrdre = 0;
        if ($saga !== '' && $sagaOrdreRaw !== '') {
            if (!preg_match('/^\d+$/', $sagaOrdreRaw)) {
                return ['ok' => false, 'error' => 'Le numéro dans la saga doit être un entier (ex. 1, 2, 3).'];
            }
            $sagaOrdre = max(1, (int) $sagaOrdreRaw);
        }

        $exemplaire = self::exemplaireDataFromParsed($post, $moncineKind, $saga, $sagaOrdre);

        return [
            'ok' => true,
            'data' => array_merge($exemplaire, [
                'oeuvre_id' => max(0, (int) ($post['oeuvre_id'] ?? 0)),
                'titre' => $titre,
                'titre_original' => trim((string) ($post['titre_original'] ?? '')),
                'realisateur' => $realisateur,
                'duree_min' => $dureeMin,
                'annee' => $annee,
                'nationalite' => TmdbCountries::formatNationaliteList((string) ($post['nationalite'] ?? '')),
                'styles' => trim((string) ($post['styles'] ?? '')),
                'poster_url' => $posterUrl,
                'synopsis' => trim((string) ($post['synopsis'] ?? '')),
                'tmdb_id' => $tmdbId,
                'tmdb_media_type' => $tmdbMediaType,
                'tmdb_tv_kind' => $tmdbTvKind,
                'tmdb_types_locked' => $typesLocked,
                'moncine_kind' => $moncineKind,
                'acteur_1' => trim((string) ($post['acteur_1'] ?? '')),
                'acteur_2' => trim((string) ($post['acteur_2'] ?? '')),
                'acteur_3' => trim((string) ($post['acteur_3'] ?? '')),
            ]),
        ];
    }

    /**
     * Champs de l’exemplaire personnel uniquement (fiche film / bibliothèque).
     *
     * @param array<string, string> $post
     * @return array{ok: true, data: array<string, mixed>}|array{ok: false, error: string}
     */
    public static function parseExemplaireFromPost(array $post): array
    {
        $saga = trim((string) ($post['saga'] ?? ''));
        $sagaOrdreRaw = trim((string) ($post['saga_ordre'] ?? ''));
        $sagaOrdre = 0;
        if ($saga !== '' && $sagaOrdreRaw !== '') {
            if (!preg_match('/^\d+$/', $sagaOrdreRaw)) {
                return ['ok' => false, 'error' => 'Le numéro dans la saga doit être un entier (ex. 1, 2, 3).'];
            }
            $sagaOrdre = max(1, (int) $sagaOrdreRaw);
        }

        $moncineKind = MoncineContentKind::FILM;
        if (array_key_exists('content_kind', $post)) {
            $parsedKind = MoncineContentKind::parseFormValue((string) ($post['content_kind'] ?? ''));
            $moncineKind = $parsedKind['moncine_kind'];
        }

        return [
            'ok' => true,
            'data' => self::exemplaireDataFromParsed($post, $moncineKind, $saga, $sagaOrdre),
        ];
    }

    /**
     * @param array<string, string> $post
     * @return array<string, mixed>
     */
    private static function exemplaireDataFromParsed(
        array $post,
        string $moncineKind,
        string $saga = '',
        int $sagaOrdre = 0
    ): array {
        if ($saga === '' && array_key_exists('saga', $post)) {
            $saga = trim((string) $post['saga']);
        }
        if ($sagaOrdre === 0 && $saga !== '') {
            $sagaOrdreRaw = trim((string) ($post['saga_ordre'] ?? ''));
            if ($sagaOrdreRaw !== '' && preg_match('/^\d+$/', $sagaOrdreRaw)) {
                $sagaOrdre = max(1, (int) $sagaOrdreRaw);
            }
        }
        if ($saga === '') {
            $sagaOrdre = 0;
        }

        $saisonNumero = max(0, (int) ($post['saison_numero'] ?? 0));
        $saisonLabel = trim((string) ($post['saison_label'] ?? ''));
        if ($moncineKind !== MoncineContentKind::SERIE) {
            $saisonNumero = 0;
            $saisonLabel = '';
        }

        return [
            'saga' => $saga,
            'saga_ordre' => $sagaOrdre,
            'format_image' => trim((string) ($post['format_image'] ?? '')),
            'format_son' => trim((string) ($post['format_son'] ?? '')),
            'support_physique' => SupportPhysique::normalize((string) ($post['support_physique'] ?? '')),
            'saison_numero' => $saisonNumero,
            'saison_label' => $saisonLabel,
            'ean' => OeuvreEanRepository::normalizeEan((string) ($post['ean'] ?? '')),
        ];
    }

    /**
     * Calcule le type TMDB à enregistrer (formulaire manuel ou conservation de l’existant).
     *
     * @param array<string, mixed> $data
     * @param array<string, mixed> $film
     * @return array{media_type: string, tv_kind: string}
     */
    public static function resolveTmdbTypesForSave(array $data, array $film): array
    {
        if (!empty($data['tmdb_types_locked'])) {
            $media = TmdbMediaType::normalize((string) ($data['tmdb_media_type'] ?? ''));
            $kind = TmdbTvKind::normalize((string) ($data['tmdb_tv_kind'] ?? ''));
            if ($media !== TmdbMediaType::TV && !TmdbTvKind::isMovieMetadata($kind)) {
                $kind = '';
            }

            return ['media_type' => $media, 'tv_kind' => $kind];
        }

        $tmdbId = (int) ($data['tmdb_id'] ?? 0);
        if ($tmdbId <= 0) {
            return ['media_type' => '', 'tv_kind' => ''];
        }

        $tmdbMediaType = TmdbMediaType::normalize((string) ($data['tmdb_media_type'] ?? ''));
        if ($tmdbMediaType === '' && trim((string) ($film['tmdb_media_type'] ?? '')) !== '') {
            $tmdbMediaType = TmdbMediaType::normalize((string) $film['tmdb_media_type']);
        }

        $tmdbTvKind = TmdbTvKind::normalize((string) ($data['tmdb_tv_kind'] ?? ''));
        if ($tmdbTvKind === '' && trim((string) ($film['tmdb_tv_kind'] ?? '')) !== '') {
            $tmdbTvKind = TmdbTvKind::normalize((string) $film['tmdb_tv_kind']);
        }
        if ($tmdbMediaType !== TmdbMediaType::TV && !TmdbTvKind::isMovieMetadata($tmdbTvKind)) {
            $tmdbTvKind = '';
        }

        return ['media_type' => $tmdbMediaType, 'tv_kind' => $tmdbTvKind];
    }

    /** @return list<string> Noms d’acteurs non vides pour affichage. */
    public static function acteursList(array $film): array
    {
        $out = [];
        foreach (['acteur_1', 'acteur_2', 'acteur_3'] as $key) {
            $name = trim((string) ($film[$key] ?? ''));
            if ($name !== '') {
                $out[] = $name;
            }
        }
        return $out;
    }

    /** Valeur affichée dans le champ « durée » du formulaire. */
    public static function dureeForInput(int $minutes): string
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
}
