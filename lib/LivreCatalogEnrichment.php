<?php
/**
 * Écriture des métadonnées Open Library sur une fiche livre catalogue.
 */

declare(strict_types=1);

namespace Moncine;

use PDO;

final class LivreCatalogEnrichment
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
        if (!LivreRepository::hasOpenLibraryColumns() || $oeuvreId <= 0) {
            return;
        }

        $repo = new LivreRepository();
        $book = $repo->findCatalogByOeuvreId($oeuvreId);
        if ($book === null) {
            return;
        }

        $existingPoster = trim((string) ($book['poster_url'] ?? ''));
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

        $titre = $this->resolveText($book, $meta, 'titre', $forceReplace, true);
        $auteur = $this->resolveText($book, $meta, 'auteur', $forceReplace);
        $isbn = $this->resolveText($book, $meta, 'isbn', $forceReplace);
        $editeur = $this->resolveText($book, $meta, 'editeur', $forceReplace);
        $synopsis = $this->resolveText($book, $meta, 'synopsis', $forceReplace);
        $langue = $this->resolveText($book, $meta, 'langue', $forceReplace);
        $sousTitre = $this->resolveText($book, $meta, 'sous_titre', $forceReplace);
        $saga = $this->resolveText($book, $meta, 'saga', $forceReplace);

        $newAnnee = (int) ($meta['annee'] ?? 0);
        $annee = (int) ($book['annee'] ?? 0);
        if ($forceReplace && $newAnnee > 0) {
            $annee = $newAnnee;
        } elseif ($annee <= 0) {
            $annee = $newAnnee;
        }

        $newPages = (int) ($meta['pages'] ?? 0);
        $pages = (int) ($book['pages'] ?? 0);
        if ($forceReplace && $newPages > 0) {
            $pages = $newPages;
        } elseif ($pages <= 0) {
            $pages = $newPages;
        }

        $newSagaOrdre = (int) ($meta['saga_ordre'] ?? 0);
        $sagaOrdre = (int) ($book['saga_ordre'] ?? 0);
        if ($saga === '') {
            $sagaOrdre = 0;
        } elseif ($forceReplace && $newSagaOrdre > 0) {
            $sagaOrdre = $newSagaOrdre;
        } elseif ($sagaOrdre <= 0) {
            $sagaOrdre = $newSagaOrdre;
        }

        $openlibraryId = trim((string) ($meta['openlibrary_id'] ?? ''));
        if ($openlibraryId === '') {
            $openlibraryId = trim((string) ($book['openlibrary_id'] ?? ''));
        }

        $this->db->beginTransaction();
        try {
            (new OeuvreRepository())->update($oeuvreId, [
                'titre' => $titre !== '' ? $titre : (string) ($book['titre'] ?? ''),
                'realisateur' => $auteur,
                'annee' => $annee,
                'synopsis' => $synopsis,
                'poster_url' => $poster,
                'saga' => $saga,
                'saga_ordre' => $sagaOrdre,
            ], ['titre', 'realisateur', 'annee', 'synopsis', 'poster_url', 'saga', 'saga_ordre']);

            $langueSql = $langue !== '' ? 'langue = :langue,' : '';
            $params = [
                'auteur' => $auteur,
                'isbn' => $isbn,
                'pages' => $pages,
                'editeur' => $editeur,
                'sous_titre' => $sousTitre,
                'openlibrary_id' => $openlibraryId,
                'oeuvre_id' => $oeuvreId,
            ];
            if ($langue !== '') {
                $params['langue'] = $langue;
            }

            $this->db->prepare(
                'UPDATE oeuvre_livre SET
                    auteur = :auteur,
                    isbn = :isbn,
                    pages = :pages,
                    editeur = :editeur,
                    sous_titre = :sous_titre,
                    ' . $langueSql . '
                    openlibrary_id = :openlibrary_id,
                    ol_enriched_at = datetime(\'now\')
                 WHERE oeuvre_id = :oeuvre_id'
            )->execute($params);

            $this->db->commit();
        } catch (\Throwable) {
            if ($this->db->inTransaction()) {
                $this->db->rollBack();
            }
        }
    }

    public function markEnrichmentAttempt(int $oeuvreId): void
    {
        if (!LivreRepository::hasOpenLibraryColumns() || $oeuvreId <= 0) {
            return;
        }

        $this->db->prepare(
            'UPDATE oeuvre_livre SET ol_enriched_at = datetime(\'now\') WHERE oeuvre_id = ?'
        )->execute([$oeuvreId]);
    }

    /**
     * @param array<string, mixed> $book
     * @param array<string, mixed> $meta
     */
    private function resolveText(
        array $book,
        array $meta,
        string $key,
        bool $forceReplace,
        bool $preferExistingNonEmpty = false
    ): string {
        $existing = trim((string) ($book[$key] ?? ''));
        $incoming = trim((string) ($meta[$key] ?? ''));
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
