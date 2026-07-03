<?php
/**
 * Jaquettes jeux (upload, URL distante, stockage local).
 */

declare(strict_types=1);

namespace Moncine;

final class GamePosterService
{
    public function __construct(private readonly GameLibraryQuery $libraryQuery)
    {
    }

    public function updatePosterUrl(int $oeuvreId, string $posterUrl): bool
    {
        if ($oeuvreId <= 0 || !GameRepository::isAvailable()) {
            return false;
        }

        if ($this->libraryQuery->findCatalogByOeuvreId($oeuvreId) === null) {
            return false;
        }

        (new OeuvreRepository())->update($oeuvreId, [
            'poster_url' => trim($posterUrl),
        ], ['poster_url']);

        return true;
    }

    public function savePoster(int $oeuvreId, string $posterUrlInput, ?string $uploadedBinary = null): void
    {
        if ($oeuvreId <= 0 || !GameRepository::isAvailable()) {
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
