<?php
/**
 * Affiches films : URL distante → stockage local pour une œuvre catalogue.
 */

declare(strict_types=1);

namespace Moncine;

final class FilmPosterService
{
    public function __construct(private readonly OeuvreRepository $oeuvres = new OeuvreRepository())
    {
    }

    public function resolvePosterForOeuvre(int $oeuvreId, string $posterUrl): string
    {
        $posterUrl = trim($posterUrl);
        if ($posterUrl === '') {
            return '';
        }

        if ($oeuvreId > 0) {
            $local = (new PosterStorage())->ensureLocalForOeuvre($oeuvreId, $posterUrl);
            if ($local !== '') {
                return $local;
            }
        }

        return SecureUrl::sanitizePosterUrl($posterUrl);
    }

    public function cacheOeuvrePosterIfRemote(int $oeuvreId, string $posterUrl): void
    {
        if ($oeuvreId <= 0 || !PosterStorage::isRemoteUrl(trim($posterUrl))) {
            return;
        }

        $local = (new PosterStorage())->cacheRemoteForOeuvre($oeuvreId, trim($posterUrl));
        if ($local !== '') {
            $this->oeuvres->update($oeuvreId, ['poster_url' => $local], ['poster_url']);
        }
    }
}
