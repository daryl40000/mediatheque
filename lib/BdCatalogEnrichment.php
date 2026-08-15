<?php
/**
 * Écriture des métadonnées Open Library sur un tome BD / manga (catalogue).
 */

declare(strict_types=1);

namespace Moncine;

use PDO;

final class BdCatalogEnrichment
{
    private readonly PDO $db;

    public function __construct(?PDO $db = null)
    {
        $this->db = $db ?? Database::getInstance();
    }

    /**
     * @param array<string, mixed> $meta
     */
    public function updateEnrichmentMetadata(
        int $oeuvreId,
        array $meta,
        bool $forceReplace = false,
        bool $keepPoster = false
    ): void {
        if (!BdRepository::hasOpenLibraryColumns() || $oeuvreId <= 0) {
            return;
        }

        $repo = new BdRepository();
        $album = $repo->findCatalogByOeuvreId($oeuvreId);
        if ($album === null) {
            return;
        }

        $existingPoster = trim((string) ($album['poster_url'] ?? ''));
        $newPoster = trim((string) ($meta['poster_url'] ?? ''));
        if ($keepPoster && $existingPoster !== '') {
            $poster = $existingPoster;
        } elseif ($forceReplace && $newPoster !== '') {
            $poster = $newPoster;
        } else {
            $poster = $newPoster !== '' ? $newPoster : $existingPoster;
        }
        if (!$keepPoster || $existingPoster === '') {
            $poster = $this->resolvePosterForOeuvre($oeuvreId, $poster);
        }

        // Titre spécifique : on ne remplace pas un titre déjà saisi (sauf force).
        $titre = $this->resolveText($album, $meta, 'titre', $forceReplace, true);
        $scenariste = $this->resolveMappedText(
            (string) ($album['scenariste'] ?? ''),
            (string) ($meta['auteur'] ?? ''),
            $forceReplace
        );
        $editeur = $this->resolveText($album, $meta, 'editeur', $forceReplace);
        $synopsis = $this->resolveText($album, $meta, 'synopsis', $forceReplace);
        $isbn = $this->resolveText($album, $meta, 'isbn', $forceReplace);

        $newAnnee = (int) ($meta['annee'] ?? 0);
        $annee = (int) ($album['annee'] ?? 0);
        if ($forceReplace && $newAnnee > 0) {
            $annee = $newAnnee;
        } elseif ($annee <= 0) {
            $annee = $newAnnee;
        }

        $newPages = (int) ($meta['pages'] ?? 0);
        $pages = (int) ($album['pages'] ?? 0);
        if ($forceReplace && $newPages > 0) {
            $pages = $newPages;
        } elseif ($pages <= 0) {
            $pages = $newPages;
        }

        // N° de tome : seulement si force (ISBN / ID OL), pour ne pas casser la numérotation série.
        $tomeNumero = (int) ($album['tome_numero'] ?? 0);
        $newTome = (int) ($meta['saga_ordre'] ?? 0);
        if ($forceReplace && $newTome > 0) {
            $tomeNumero = $newTome;
        }

        $openlibraryId = trim((string) ($meta['openlibrary_id'] ?? ''));
        if ($openlibraryId === '') {
            $openlibraryId = trim((string) ($album['openlibrary_id'] ?? ''));
        }

        $this->db->beginTransaction();
        try {
            (new OeuvreRepository())->update($oeuvreId, [
                'titre' => $titre,
                'annee' => $annee,
                'synopsis' => $synopsis,
                'poster_url' => $poster,
            ], ['titre', 'annee', 'synopsis', 'poster_url']);

            $this->db->prepare(
                'UPDATE oeuvre_bd SET
                    scenariste = :scenariste,
                    editeur = :editeur,
                    isbn = :isbn,
                    pages = :pages,
                    tome_numero = :tome_numero,
                    openlibrary_id = :openlibrary_id,
                    ol_enriched_at = datetime(\'now\')
                 WHERE oeuvre_id = :oeuvre_id'
            )->execute([
                'scenariste' => $scenariste,
                'editeur' => $editeur,
                'isbn' => $isbn,
                'pages' => $pages,
                'tome_numero' => $tomeNumero,
                'openlibrary_id' => $openlibraryId,
                'oeuvre_id' => $oeuvreId,
            ]);

            $this->db->commit();
        } catch (\Throwable) {
            if ($this->db->inTransaction()) {
                $this->db->rollBack();
            }
        }
    }

    public function markEnrichmentAttempt(int $oeuvreId): void
    {
        if (!BdRepository::hasOpenLibraryColumns() || $oeuvreId <= 0) {
            return;
        }

        $this->db->prepare(
            'UPDATE oeuvre_bd SET ol_enriched_at = datetime(\'now\') WHERE oeuvre_id = ?'
        )->execute([$oeuvreId]);
    }

    /**
     * @param array<string, mixed> $album
     * @param array<string, mixed> $meta
     */
    private function resolveText(
        array $album,
        array $meta,
        string $key,
        bool $forceReplace,
        bool $preferExistingNonEmpty = false
    ): string {
        $existing = trim((string) ($album[$key] ?? ''));
        $incoming = trim((string) ($meta[$key] ?? ''));

        return $this->resolveMappedText($existing, $incoming, $forceReplace, $preferExistingNonEmpty);
    }

    private function resolveMappedText(
        string $existing,
        string $incoming,
        bool $forceReplace,
        bool $preferExistingNonEmpty = false
    ): string {
        $existing = trim($existing);
        $incoming = trim($incoming);
        if ($forceReplace && $incoming !== '') {
            return $incoming;
        }
        if ($preferExistingNonEmpty && $existing !== '') {
            return $existing;
        }
        if ($existing !== '') {
            return $existing;
        }

        return $incoming;
    }

    private function resolvePosterForOeuvre(int $oeuvreId, string $posterUrl): string
    {
        $posterUrl = trim($posterUrl);
        if ($posterUrl === '') {
            return '';
        }

        try {
            $local = (new PosterStorage())->ensureLocalForOeuvre($oeuvreId, $posterUrl);
            if ($local !== '') {
                return $local;
            }
        } catch (\Throwable) {
            // Repli URL distante sécurisée.
        }

        return SecureUrl::sanitizePosterUrl($posterUrl);
    }
}
