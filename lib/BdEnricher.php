<?php
/**
 * Enrichit les tomes BD / manga via Open Library (ISBN prioritaire, sinon titre).
 */

declare(strict_types=1);

namespace Moncine;

final class BdEnricher
{
    public function __construct(
        private readonly BdRepository $bd = new BdRepository(),
        private readonly BdCatalogEnrichment $enrichment = new BdCatalogEnrichment(),
        private readonly OpenLibraryClient $openLibrary = new OpenLibraryClient()
    ) {
    }

    public static function canEnrich(): bool
    {
        return BdRepository::isAvailable() && BdRepository::hasOpenLibraryColumns();
    }

    /**
     * @return array{ok: bool, not_found: bool, message: string}
     */
    public function enrichOne(int $bibId, int $userId, int $foyerId, bool $keepPoster = false): array
    {
        if (!BdRepository::isAvailable()) {
            return [
                'ok' => false,
                'not_found' => false,
                'message' => 'Module BD non disponible.',
            ];
        }

        $album = $this->bd->findByBibId($bibId, $userId, $foyerId);
        if ($album === null) {
            return [
                'ok' => false,
                'not_found' => false,
                'message' => 'Album introuvable.',
            ];
        }

        $oeuvreId = (int) ($album['oeuvre_id'] ?? 0);
        if ($oeuvreId <= 0) {
            return [
                'ok' => false,
                'not_found' => false,
                'message' => 'Fiche catalogue introuvable pour cet album.',
            ];
        }

        return $this->enrichOeuvre($oeuvreId, $keepPoster);
    }

    /**
     * Enrichit par ISBN (si présent) puis par titre / scénariste.
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

        $album = $this->bd->findCatalogByOeuvreId($oeuvreId);
        if ($album === null) {
            return [
                'ok' => false,
                'not_found' => false,
                'message' => 'Album catalogue introuvable.',
            ];
        }

        try {
            $meta = $this->lookupForAlbum($album);
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
            $err = $this->openLibrary->getLastError() ?? 'Album introuvable sur Open Library.';

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

        $album = $this->bd->findCatalogByOeuvreId($oeuvreId);
        if ($album === null) {
            return [
                'ok' => false,
                'not_found' => false,
                'message' => 'Album catalogue introuvable.',
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
     * Enrichit uniquement à partir de l’ISBN.
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

        $album = $this->bd->findCatalogByOeuvreId($oeuvreId);
        if ($album === null) {
            return [
                'ok' => false,
                'not_found' => false,
                'message' => 'Album catalogue introuvable.',
            ];
        }

        $isbn = OpenLibraryClient::normalizeIsbn($isbn);
        if ($isbn === '') {
            $isbn = OpenLibraryClient::normalizeIsbn((string) ($album['isbn'] ?? ''));
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
     * @param array<string, mixed> $album
     * @return array<string, mixed>|null
     */
    private function lookupForAlbum(array $album): ?array
    {
        $isbn = OpenLibraryClient::normalizeIsbn((string) ($album['isbn'] ?? ''));
        if ($isbn !== '') {
            $byIsbn = $this->openLibrary->lookupByIsbn($isbn);
            if ($byIsbn !== null) {
                return $byIsbn;
            }
        }

        $title = $this->searchTitleForAlbum($album);
        $author = trim((string) ($album['scenariste'] ?? ''));
        if ($author === '') {
            $author = trim((string) ($album['dessinateur'] ?? ''));
        }
        $year = (int) ($album['annee'] ?? 0);

        return $this->openLibrary->searchByTitle($title, $author, $year > 0 ? $year : null);
    }

    /**
     * Titre de recherche : titre spécifique, sinon « Série Tome N ».
     *
     * @param array<string, mixed> $album
     */
    private function searchTitleForAlbum(array $album): string
    {
        $titre = trim((string) ($album['titre'] ?? ''));
        if ($titre !== '') {
            return $titre;
        }

        $series = trim((string) ($album['series_titre'] ?? ''));
        $tomeNum = (int) ($album['tome_numero'] ?? 0);
        $tomeLabel = trim((string) ($album['tome_label'] ?? ''));
        if ($series === '') {
            return BdRowMapper::displayTitle($album);
        }
        if ($tomeLabel !== '') {
            return $series . ' ' . $tomeLabel;
        }
        if ($tomeNum > 0) {
            return $series . ' ' . $tomeNum;
        }

        return $series;
    }
}
