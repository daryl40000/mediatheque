<?php
/**
 * Couvertures BD (upload, URL distante, stockage local).
 */

declare(strict_types=1);

namespace Moncine;

final class BdPosterService
{
    public function __construct(private readonly BdLibraryQuery $libraryQuery)
    {
    }

    public function updatePosterUrl(int $oeuvreId, string $posterUrl): bool
    {
        if (!BdRepository::isAvailable() || $oeuvreId <= 0) {
            return false;
        }

        $posterUrl = trim($posterUrl);
        if ($posterUrl === '') {
            return false;
        }

        if ($this->libraryQuery->findCatalogByOeuvreId($oeuvreId) === null) {
            return false;
        }

        (new OeuvreRepository())->update($oeuvreId, ['poster_url' => $posterUrl], ['poster_url']);

        return true;
    }

    public function savePoster(int $oeuvreId, string $posterUrlInput, ?string $uploadedBinary = null): void
    {
        if ($oeuvreId <= 0 || !BdRepository::isAvailable()) {
            return;
        }

        $storage = new PosterStorage();

        if ($uploadedBinary !== null && $uploadedBinary !== '') {
            $local = $storage->importBinaryForOeuvre($oeuvreId, $uploadedBinary);
            if ($local !== '') {
                $this->updatePosterUrl($oeuvreId, $local);
            }

            return;
        }

        $posterUrlInput = trim($posterUrlInput);
        if ($posterUrlInput === '') {
            return;
        }

        $local = $storage->ensureLocalForOeuvre($oeuvreId, $posterUrlInput);
        if ($local !== '') {
            $this->updatePosterUrl($oeuvreId, $local);

            return;
        }

        $sanitized = SecureUrl::sanitizePosterUrl($posterUrlInput);
        if ($sanitized !== '') {
            $this->updatePosterUrl($oeuvreId, $sanitized);
        }
    }
}
