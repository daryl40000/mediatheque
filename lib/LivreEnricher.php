<?php
/**
 * Enrichit les livres via Open Library (ISBN prioritaire, sinon titre).
 */

declare(strict_types=1);

namespace Moncine;

final class LivreEnricher
{
    public function __construct(
        private readonly LivreRepository $livres = new LivreRepository(),
        private readonly LivreCatalogEnrichment $enrichment = new LivreCatalogEnrichment(),
        private readonly OpenLibraryClient $openLibrary = new OpenLibraryClient()
    ) {
    }

    public static function canEnrich(): bool
    {
        return LivreRepository::isAvailable() && LivreRepository::hasOpenLibraryColumns();
    }

    /**
     * @return array{ok: bool, not_found: bool, message: string}
     */
    public function enrichOne(int $bibId, int $userId, int $foyerId, bool $keepPoster = false): array
    {
        if (!LivreRepository::isAvailable()) {
            return [
                'ok' => false,
                'not_found' => false,
                'message' => 'Module livres non disponible.',
            ];
        }

        $book = $this->livres->findByBibId($bibId, $userId, $foyerId);
        if ($book === null) {
            return [
                'ok' => false,
                'not_found' => false,
                'message' => 'Livre introuvable.',
            ];
        }

        $oeuvreId = (int) ($book['oeuvre_id'] ?? 0);
        if ($oeuvreId <= 0) {
            return [
                'ok' => false,
                'not_found' => false,
                'message' => 'Fiche catalogue introuvable pour ce livre.',
            ];
        }

        return $this->enrichOeuvre($oeuvreId, $keepPoster);
    }

    /**
     * Enrichit par ISBN (si présent) puis par titre / auteur.
     *
     * @return array{ok: bool, not_found: bool, message: string}
     */
    public function enrichOeuvre(int $oeuvreId, bool $keepPoster = false): array
    {
        if (!self::canEnrich()) {
            return [
                'ok' => false,
                'not_found' => false,
                'message' => 'Enrichissement Open Library indisponible (migration requise).',
            ];
        }

        $book = $this->livres->findCatalogByOeuvreId($oeuvreId);
        if ($book === null) {
            return [
                'ok' => false,
                'not_found' => false,
                'message' => 'Livre catalogue introuvable.',
            ];
        }

        try {
            $meta = $this->lookupForBook($book);
        } catch (\Throwable $e) {
            $this->enrichment->markEnrichmentAttempt($oeuvreId);

            return [
                'ok' => false,
                'not_found' => false,
                'message' => $e->getMessage(),
            ];
        }

        if ($meta === null) {
            $this->enrichment->markEnrichmentAttempt($oeuvreId);
            $err = $this->openLibrary->getLastError() ?? 'Livre introuvable sur Open Library.';

            return [
                'ok' => false,
                'not_found' => true,
                'message' => $err,
            ];
        }

        $this->enrichment->updateEnrichmentMetadata($oeuvreId, $meta, false, $keepPoster);

        return [
            'ok' => true,
            'not_found' => false,
            'message' => 'Fiche enrichie via Open Library.',
        ];
    }

    /**
     * Force l’enrichissement avec un ID édition Open Library (OLxxxxM).
     *
     * @return array{ok: bool, not_found: bool, message: string}
     */
    public function correctOeuvreWithOpenLibraryId(
        int $oeuvreId,
        string $openLibraryId,
        bool $keepPoster = false
    ): array {
        if (!self::canEnrich()) {
            return [
                'ok' => false,
                'not_found' => false,
                'message' => 'Enrichissement Open Library indisponible (migration requise).',
            ];
        }

        $book = $this->livres->findCatalogByOeuvreId($oeuvreId);
        if ($book === null) {
            return [
                'ok' => false,
                'not_found' => false,
                'message' => 'Livre catalogue introuvable.',
            ];
        }

        $meta = $this->openLibrary->lookupByEditionKey($openLibraryId);
        if ($meta === null) {
            $this->enrichment->markEnrichmentAttempt($oeuvreId);

            return [
                'ok' => false,
                'not_found' => true,
                'message' => $this->openLibrary->getLastError() ?? 'Édition Open Library introuvable.',
            ];
        }

        $this->enrichment->updateEnrichmentMetadata($oeuvreId, $meta, true, $keepPoster);

        return [
            'ok' => true,
            'not_found' => false,
            'message' => 'Fiche mise à jour avec l’édition Open Library.',
        ];
    }

    /**
     * Enrichit uniquement à partir de l’ISBN (saisie manuelle ou champ existant).
     *
     * @return array{ok: bool, not_found: bool, message: string}
     */
    public function enrichOeuvreByIsbn(int $oeuvreId, string $isbn, bool $keepPoster = false): array
    {
        if (!self::canEnrich()) {
            return [
                'ok' => false,
                'not_found' => false,
                'message' => 'Enrichissement Open Library indisponible (migration requise).',
            ];
        }

        $book = $this->livres->findCatalogByOeuvreId($oeuvreId);
        if ($book === null) {
            return [
                'ok' => false,
                'not_found' => false,
                'message' => 'Livre catalogue introuvable.',
            ];
        }

        $isbn = OpenLibraryClient::normalizeIsbn($isbn);
        if ($isbn === '') {
            $isbn = OpenLibraryClient::normalizeIsbn((string) ($book['isbn'] ?? ''));
        }
        if ($isbn === '') {
            return [
                'ok' => false,
                'not_found' => false,
                'message' => 'Indiquez un ISBN valide.',
            ];
        }

        $meta = $this->openLibrary->lookupByIsbn($isbn);
        if ($meta === null) {
            $this->enrichment->markEnrichmentAttempt($oeuvreId);

            return [
                'ok' => false,
                'not_found' => true,
                'message' => $this->openLibrary->getLastError() ?? 'ISBN introuvable sur Open Library.',
            ];
        }

        $this->enrichment->updateEnrichmentMetadata($oeuvreId, $meta, true, $keepPoster);

        return [
            'ok' => true,
            'not_found' => false,
            'message' => 'Fiche enrichie via l’ISBN Open Library.',
        ];
    }

    /**
     * @param array<string, mixed> $book
     * @return array<string, mixed>|null
     */
    private function lookupForBook(array $book): ?array
    {
        $isbn = OpenLibraryClient::normalizeIsbn((string) ($book['isbn'] ?? ''));
        if ($isbn !== '') {
            $byIsbn = $this->openLibrary->lookupByIsbn($isbn);
            if ($byIsbn !== null) {
                return $byIsbn;
            }
        }

        $title = trim((string) ($book['titre'] ?? ''));
        $author = trim((string) ($book['auteur'] ?? ''));
        $year = (int) ($book['annee'] ?? 0);

        return $this->openLibrary->searchByTitle($title, $author, $year > 0 ? $year : null);
    }
}
