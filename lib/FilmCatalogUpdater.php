<?php
/**
 * Mise à jour manuelle des fiches films : exemplaire personnel et fiche catalogue partagée.
 */

declare(strict_types=1);

namespace Moncine;

final class FilmCatalogUpdater
{
    public function __construct(
        private readonly OeuvreRepository $oeuvres,
        private readonly BibliothequeRepository $bibliotheque,
        private readonly FilmLibraryQuery $libraryQuery,
        private readonly FilmPosterService $posterService,
        private readonly FilmCatalogSaga $saga
    ) {
    }

    /**
     * Met à jour uniquement l’exemplaire personnel (bibliothèque), pas le catalogue partagé.
     *
     * @param array<string, mixed> $data
     * @return true|string
     */
    public function updateManual(int $filmId, array $data): bool|string
    {
        $film = $this->libraryQuery->findById($filmId);
        if ($film === null) {
            return 'Film introuvable.';
        }

        $saga = trim((string) ($data['saga'] ?? ''));
        $sagaOrdre = max(0, (int) ($data['saga_ordre'] ?? 0));
        if ($saga === '') {
            $oeuvre = $this->oeuvres->findById((int) ($film['oeuvre_id'] ?? 0));
            if ($oeuvre !== null) {
                [$saga, $sagaOrdre] = $this->saga->resolveLibrarySagaFromOeuvre($oeuvre, $data);
            }
        }
        if ($saga === '') {
            $sagaOrdre = 0;
        }

        $moncineKind = MoncineContentKind::normalize((string) ($film['moncine_kind'] ?? ''));
        $saisonNumero = max(0, (int) ($data['saison_numero'] ?? 0));
        $saisonLabel = trim((string) ($data['saison_label'] ?? ''));
        if ($moncineKind !== MoncineContentKind::SERIE) {
            $saisonNumero = 0;
            $saisonLabel = '';
        }

        $this->bibliotheque->update($filmId, [
            'support_physique' => SupportPhysique::normalize($data['support_physique'] ?? ''),
            'format_image' => trim((string) ($data['format_image'] ?? '')),
            'format_son' => trim((string) ($data['format_son'] ?? '')),
            'saga' => $saga,
            'saga_ordre' => $sagaOrdre,
            'saison_numero' => $saisonNumero,
            'saison_label' => $saisonLabel,
            'ean' => OeuvreEanRepository::normalizeEan((string) ($data['ean'] ?? '')),
        ]);

        return true;
    }

    /**
     * @param array<string, mixed> $data
     * @return true|string
     */
    public function updateOeuvreManual(int $oeuvreId, array $data): bool|string
    {
        if ($oeuvreId <= 0) {
            return 'Œuvre invalide.';
        }

        $film = $this->oeuvres->findById($oeuvreId);
        if ($film === null) {
            return 'Œuvre introuvable.';
        }

        $titre = trim($data['titre']);
        $realisateur = trim($data['realisateur']);
        if ($titre === '') {
            return 'Le titre est obligatoire.';
        }

        $existing = $this->oeuvres->findByTitreAndRealisateur($titre, $realisateur);
        if ($existing !== null && (int) ($existing['id'] ?? 0) !== $oeuvreId) {
            return 'Une autre œuvre a déjà ce titre et ce réalisateur.';
        }

        $tmdbId = (int) ($data['tmdb_id'] ?? 0);
        $tmdbTypes = FilmManualEdit::resolveTmdbTypesForSave($data, $film);
        $oeuvrePayload = [
            'titre' => $titre,
            'titre_original' => trim((string) ($data['titre_original'] ?? '')),
            'realisateur' => $realisateur,
            'duree_min' => (int) ($data['duree_min'] ?? 0),
            'annee' => (int) ($data['annee'] ?? 0),
            'styles' => $data['styles'] ?? '',
            'poster_url' => $this->posterService->resolvePosterForOeuvre($oeuvreId, (string) ($data['poster_url'] ?? '')),
            'synopsis' => $data['synopsis'] ?? '',
            'tmdb_id' => $tmdbId,
            'tmdb_media_type' => $tmdbTypes['media_type'],
            'tmdb_tv_kind' => $tmdbTypes['tv_kind'],
            'realisateur_tmdb_id' => (int) ($data['realisateur_tmdb_id'] ?? 0),
            'acteur_1' => trim((string) ($data['acteur_1'] ?? '')),
            'acteur_2' => trim((string) ($data['acteur_2'] ?? '')),
            'acteur_3' => trim((string) ($data['acteur_3'] ?? '')),
            'acteur_1_tmdb_id' => (int) ($data['acteur_1_tmdb_id'] ?? 0),
            'acteur_2_tmdb_id' => (int) ($data['acteur_2_tmdb_id'] ?? 0),
            'acteur_3_tmdb_id' => (int) ($data['acteur_3_tmdb_id'] ?? 0),
            'nationalite' => TmdbCountries::formatNationaliteList((string) ($data['nationalite'] ?? '')),
            'moncine_kind' => MoncineContentKind::normalize((string) ($data['moncine_kind'] ?? '')),
        ];
        $this->oeuvres->update($oeuvreId, $oeuvrePayload, array_keys($oeuvrePayload));

        return true;
    }
}
