<?php
/**
 * Validation et préparation des champs jeu pour proposition / validation catalogue.
 */

declare(strict_types=1);

namespace Moncine;

final class GameManualEdit
{
    /**
     * @param array<string, mixed> $post Champs du formulaire ($_POST)
     * @return array{ok: true, data: array<string, mixed>}|array{ok: false, error: string}
     */
    public static function parseFromPost(array $post, bool $forCatalogApproval = false): array
    {
        if (!GameRepository::isAvailable()) {
            return ['ok' => false, 'error' => 'Module jeux non disponible.'];
        }

        $titre = trim((string) ($post['titre'] ?? ''));
        if ($titre === '') {
            return ['ok' => false, 'error' => 'Le titre est obligatoire.'];
        }

        if (!$forCatalogApproval && max(0, (int) ($post['oeuvre_id'] ?? 0)) > 0) {
            return [
                'ok' => false,
                'error' => 'Ce jeu est déjà au catalogue. Ajoutez-le depuis Mes jeux ou Mes envies.',
            ];
        }

        $anneeRaw = trim((string) ($post['annee'] ?? ''));
        $annee = 0;
        if ($anneeRaw !== '') {
            if (!preg_match('/^\d{4}$/', $anneeRaw)) {
                return ['ok' => false, 'error' => 'L’année doit être sur 4 chiffres (ex. 1995).'];
            }
            $annee = (int) $anneeRaw;
        }

        $posterUrl = trim((string) ($post['poster_url'] ?? ''));
        if ($posterUrl !== '' && !SecureUrl::isValidPosterReference($posterUrl)) {
            return [
                'ok' => false,
                'error' => 'La jaquette doit être une URL HTTPS ou un chemin local /posters/… déjà enregistré.',
            ];
        }

        $platformFields = GameRepository::catalogPlatformsFromPost($post);
        if ($platformFields['platforms'] === '') {
            return ['ok' => false, 'error' => 'Indiquez au moins une plateforme pour ce jeu.'];
        }

        $payload = array_merge(
            GameRepository::catalogPayloadFromPost($post),
            $platformFields,
            [
                'annee' => $annee,
                'poster_url' => $posterUrl,
                'submission_domain' => MediaDomain::JEU,
            ]
        );

        $relationError = GameRepository::validateGameRelationFlags($payload);
        if ($relationError !== null) {
            return ['ok' => false, 'error' => $relationError];
        }

        return ['ok' => true, 'data' => $payload];
    }
}
