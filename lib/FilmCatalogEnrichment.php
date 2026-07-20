<?php
/**
 * Enrichissement des fiches catalogue films (métadonnées TMDB/OMDb).
 */

declare(strict_types=1);

namespace Moncine;

use PDO;

final class FilmCatalogEnrichment
{
    public function __construct(
        private readonly PDO $db,
        private readonly OeuvreRepository $oeuvres,
        private readonly FilmLibraryQuery $libraryQuery,
        private readonly FilmPosterService $posterService
    ) {
    }

    public function countNeedingEnrichment(bool $includeAttempted = false): int
    {
        if ($includeAttempted) {
            $params = [
                'catalog_foyer_id' => $this->foyerId(),
                'catalog_statut' => LibraryStatut::COLLECTION,
            ];
            $stmt = $this->db->prepare(
                'SELECT COUNT(*) FROM ' . CatalogSchema::JOIN . '
                 WHERE b.foyer_id = :catalog_foyer_id AND b.statut = :catalog_statut'
                . CatalogSchema::sqlMediaDomainAnd('o', $params)
            );
            $stmt->execute($params);

            return (int) $stmt->fetchColumn();
        }

        $params = [
            'catalog_foyer_id' => $this->foyerId(),
            'catalog_statut' => LibraryStatut::COLLECTION,
        ];
        $stmt = $this->db->prepare(
            'SELECT COUNT(*) FROM ' . CatalogSchema::JOIN . '
             WHERE b.foyer_id = :catalog_foyer_id AND b.statut = :catalog_statut AND '
                . FilmCatalogSql::enrichmentPendingSql('o')
            . CatalogSchema::sqlMediaDomainAnd('o', $params)
        );
        $stmt->execute($params);

        return (int) $stmt->fetchColumn();
    }

    /**
     * @return list<array<string, mixed>>
     */
    public function findNeedingEnrichment(int $limit = 10, bool $force = false): array
    {
        $limit = max(1, $limit);
        $params = [
            'catalog_foyer_id' => $this->foyerId(),
            'catalog_statut' => LibraryStatut::COLLECTION,
            'lim' => $limit,
        ];
        $domainSql = CatalogSchema::sqlMediaDomainAnd('o', $params);
        if ($force) {
            $stmt = $this->db->prepare(
                'SELECT ' . CatalogSchema::selectFilmRow() . '
                 FROM ' . CatalogSchema::JOIN . '
                 WHERE b.foyer_id = :catalog_foyer_id AND b.statut = :catalog_statut'
                . $domainSql . '
                 ORDER BY o.titre COLLATE FRENCH_NOCASE
                 LIMIT :lim'
            );
        } else {
            $stmt = $this->db->prepare(
                'SELECT ' . CatalogSchema::selectFilmRow() . '
                 FROM ' . CatalogSchema::JOIN . '
                 WHERE b.foyer_id = :catalog_foyer_id AND b.statut = :catalog_statut
                   AND ' . FilmCatalogSql::enrichmentPendingSql('o')
                . $domainSql . '
                 ORDER BY o.titre COLLATE FRENCH_NOCASE
                 LIMIT :lim'
            );
        }
        $stmt->bindValue('catalog_foyer_id', $params['catalog_foyer_id'], PDO::PARAM_INT);
        $stmt->bindValue('catalog_statut', $params['catalog_statut']);
        if (isset($params['catalog_media_domain'])) {
            $stmt->bindValue('catalog_media_domain', $params['catalog_media_domain']);
        }
        $stmt->bindValue('lim', $limit, PDO::PARAM_INT);
        $stmt->execute();

        return $stmt->fetchAll();
    }

    /**
     * @param array<string, mixed> $meta
     */
    public function updateEnrichmentMetadata(int $filmId, array $meta, bool $forceReplace = false): void
    {
        $film = $this->libraryQuery->findById($filmId);
        if ($film === null) {
            return;
        }

        $this->updateOeuvreEnrichmentMetadata((int) $film['oeuvre_id'], $meta, $forceReplace, $film);
    }

    /**
     * Met à jour les métadonnées TMDB d’une œuvre du catalogue (toutes bibliothèques liées).
     *
     * @param array<string, mixed> $meta
     * @param array<string, mixed>|null $filmRow Ligne œuvre (ou jointe) pour fusion ; sinon chargée par ID.
     */
    public function updateOeuvreEnrichmentMetadata(
        int $oeuvreId,
        array $meta,
        bool $forceReplace = false,
        ?array $filmRow = null
    ): void {
        if ($oeuvreId <= 0) {
            return;
        }

        $film = $filmRow ?? $this->oeuvres->findById($oeuvreId);
        if ($film === null) {
            return;
        }

        $newPoster = trim((string) ($meta['poster_url'] ?? ''));
        $newSynopsis = trim((string) ($meta['synopsis'] ?? ''));
        $newDuree = (int) ($meta['duree_min'] ?? 0);
        $newAnnee = (int) ($meta['annee'] ?? 0);

        if ($forceReplace && $newPoster !== '') {
            $poster = $newPoster;
        } else {
            $poster = $newPoster !== '' ? $newPoster : (string) ($film['poster_url'] ?? '');
        }
        $poster = $this->posterService->resolvePosterForOeuvre($oeuvreId, $poster);

        if ($forceReplace && $newSynopsis !== '') {
            $synopsis = $newSynopsis;
        } else {
            $synopsis = $newSynopsis !== '' ? $newSynopsis : (string) ($film['synopsis'] ?? '');
        }

        $newRealisateur = trim((string) ($meta['realisateur'] ?? ''));
        if ($forceReplace && $newRealisateur !== '') {
            $realisateur = $newRealisateur;
        } elseif ($newRealisateur !== '') {
            $realisateur = $newRealisateur;
        } else {
            $realisateur = trim((string) ($film['realisateur'] ?? ''));
        }

        $acteur1 = $this->resolveActeurField($film, $meta, 'acteur_1', $forceReplace);
        $acteur2 = $this->resolveActeurField($film, $meta, 'acteur_2', $forceReplace);
        $acteur3 = $this->resolveActeurField($film, $meta, 'acteur_3', $forceReplace);

        if ($forceReplace && $newDuree > 0) {
            $duree = $newDuree;
        } else {
            $duree = (int) ($film['duree_min'] ?? 0);
            if ($duree <= 0) {
                $duree = $newDuree;
            }
        }

        if ($forceReplace && $newAnnee > 0) {
            $annee = $newAnnee;
        } else {
            $annee = (int) ($film['annee'] ?? 0);
            if ($annee <= 0) {
                $annee = $newAnnee;
            }
        }

        $incomingTmdbId = (int) ($meta['tmdb_id'] ?? 0);
        if ($incomingTmdbId > 0) {
            $tmdbId = $incomingTmdbId;
        } else {
            $tmdbId = (int) ($film['tmdb_id'] ?? 0);
        }

        $incomingMediaType = TmdbMediaType::normalize((string) ($meta['tmdb_media_type'] ?? ''));
        if ($incomingMediaType !== '') {
            $tmdbMediaType = $incomingMediaType;
        } elseif ($tmdbId > 0 && trim((string) ($film['tmdb_media_type'] ?? '')) !== '') {
            $tmdbMediaType = TmdbMediaType::normalize((string) $film['tmdb_media_type']);
        } else {
            $tmdbMediaType = '';
        }

        $incomingTvKind = TmdbTvKind::normalize((string) ($meta['tmdb_tv_kind'] ?? ''));
        if ($incomingTvKind !== '') {
            $tmdbTvKind = $incomingTvKind;
        } elseif ($tmdbId > 0 && trim((string) ($film['tmdb_tv_kind'] ?? '')) !== '') {
            $tmdbTvKind = TmdbTvKind::normalize((string) $film['tmdb_tv_kind']);
        } else {
            $tmdbTvKind = '';
        }
        if ($tmdbMediaType !== TmdbMediaType::TV && !TmdbTvKind::isMovieMetadata($tmdbTvKind)) {
            $tmdbTvKind = '';
        }

        $titre = trim((string) ($film['titre'] ?? ''));
        if (array_key_exists('titre', $meta)) {
            $newTitre = trim((string) ($meta['titre'] ?? ''));
            if ($forceReplace && $newTitre !== '') {
                $titre = $newTitre;
            }
        }

        $newTitreOriginal = trim((string) ($meta['titre_original'] ?? ''));
        if (array_key_exists('titre_original', $meta)) {
            if ($forceReplace || $newTitreOriginal !== '') {
                $titreOriginal = $newTitreOriginal;
            } else {
                $titreOriginal = trim((string) ($film['titre_original'] ?? ''));
            }
        } else {
            $titreOriginal = trim((string) ($film['titre_original'] ?? ''));
        }

        $newNationalite = trim((string) ($meta['nationalite'] ?? ''));
        if (array_key_exists('nationalite', $meta)) {
            if ($forceReplace) {
                $nationalite = $newNationalite;
            } elseif ($newNationalite !== '') {
                $nationalite = $newNationalite;
            } else {
                $nationalite = trim((string) ($film['nationalite'] ?? ''));
            }
        } else {
            $nationalite = trim((string) ($film['nationalite'] ?? ''));
        }
        $nationalite = TmdbCountries::formatNationaliteList($nationalite);

        $realisateurTmdbId = $this->resolvePersonTmdbIdField($film, $meta, 'realisateur_tmdb_id', $forceReplace);
        $acteur1TmdbId = $this->resolvePersonTmdbIdField($film, $meta, 'acteur_1_tmdb_id', $forceReplace);
        $acteur2TmdbId = $this->resolvePersonTmdbIdField($film, $meta, 'acteur_2_tmdb_id', $forceReplace);
        $acteur3TmdbId = $this->resolvePersonTmdbIdField($film, $meta, 'acteur_3_tmdb_id', $forceReplace);
        $styles = TmdbGenres::mergeStylesForEnrichment($film, $meta);

        $incomingMoncineKind = MoncineContentKind::normalize((string) ($meta['moncine_kind'] ?? ''));
        if ($incomingMoncineKind !== MoncineContentKind::FILM || $forceReplace) {
            $moncineKind = $incomingMoncineKind !== ''
                ? $incomingMoncineKind
                : MoncineContentKind::fromTmdbFields($tmdbMediaType, $tmdbTvKind);
        } else {
            $moncineKind = MoncineContentKind::normalize((string) ($film['moncine_kind'] ?? MoncineContentKind::FILM));
        }

        $stmt = $this->db->prepare(
            'UPDATE oeuvres SET
                titre = :titre,
                poster_url = :poster_url,
                synopsis = :synopsis,
                realisateur = :realisateur,
                styles = :styles,
                duree_min = :duree_min,
                annee = :annee,
                tmdb_id = :tmdb_id,
                tmdb_media_type = :tmdb_media_type,
                tmdb_tv_kind = :tmdb_tv_kind,
                moncine_kind = :moncine_kind,
                titre_original = :titre_original,
                nationalite = :nationalite,
                realisateur_tmdb_id = :realisateur_tmdb_id,
                acteur_1 = :acteur_1,
                acteur_2 = :acteur_2,
                acteur_3 = :acteur_3,
                acteur_1_tmdb_id = :acteur_1_tmdb_id,
                acteur_2_tmdb_id = :acteur_2_tmdb_id,
                acteur_3_tmdb_id = :acteur_3_tmdb_id,
                omdb_enriched_at = datetime(\'now\'),
                updated_at = datetime(\'now\')
             WHERE id = :oeuvre_id'
        );
        $stmt->execute([
            'oeuvre_id' => $oeuvreId,
            'titre' => $titre,
            'poster_url' => $poster,
            'synopsis' => $synopsis,
            'realisateur' => $realisateur,
            'styles' => $styles,
            'duree_min' => $duree,
            'annee' => $annee,
            'tmdb_id' => $tmdbId,
            'tmdb_media_type' => $tmdbMediaType,
            'tmdb_tv_kind' => $tmdbTvKind,
            'moncine_kind' => $moncineKind,
            'titre_original' => $titreOriginal,
            'nationalite' => $nationalite,
            'realisateur_tmdb_id' => $realisateurTmdbId,
            'acteur_1' => $acteur1,
            'acteur_2' => $acteur2,
            'acteur_3' => $acteur3,
            'acteur_1_tmdb_id' => $acteur1TmdbId,
            'acteur_2_tmdb_id' => $acteur2TmdbId,
            'acteur_3_tmdb_id' => $acteur3TmdbId,
        ]);
    }

    public function markEnrichmentAttempt(int $filmId): void
    {
        $film = $this->libraryQuery->findById($filmId);
        if ($film === null) {
            return;
        }

        $this->markOeuvreEnrichmentAttempt((int) $film['oeuvre_id']);
    }

    public function markOeuvreEnrichmentAttempt(int $oeuvreId): void
    {
        if ($oeuvreId <= 0) {
            return;
        }

        $this->db->prepare(
            'UPDATE oeuvres SET omdb_enriched_at = datetime(\'now\'), updated_at = datetime(\'now\') WHERE id = ?'
        )->execute([$oeuvreId]);
    }

    /**
     * @param array<string, mixed> $film
     * @param array<string, mixed> $meta
     */
    private function resolveActeurField(array $film, array $meta, string $key, bool $forceReplace): string
    {
        $incoming = trim((string) ($meta[$key] ?? ''));
        $current = trim((string) ($film[$key] ?? ''));
        if ($forceReplace) {
            return $incoming;
        }

        return $incoming !== '' ? $incoming : $current;
    }

    /**
     * @param array<string, mixed> $film
     * @param array<string, mixed> $meta
     */
    private function resolvePersonTmdbIdField(array $film, array $meta, string $key, bool $forceReplace): int
    {
        $incoming = (int) ($meta[$key] ?? 0);
        $current = (int) ($film[$key] ?? 0);
        if ($forceReplace) {
            return $incoming;
        }

        return $incoming > 0 ? $incoming : $current;
    }

    private function foyerId(): int
    {
        return UserContext::currentFoyerId();
    }
}
