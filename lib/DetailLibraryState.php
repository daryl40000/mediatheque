<?php
/**
 * État bibliothèque d’une œuvre catalogue (possédée, envie, absente) pour les bandeaux de fiches.
 */

declare(strict_types=1);

namespace Moncine;

final class DetailLibraryState
{
    /**
     * Seule la collection compte comme « possédé » (affichage normal). Les envies restent grisées.
     *
     * @return array{in_library: bool, library_bib_id: int, library_url: string}
     */
    public static function forOeuvre(
        int $oeuvreId,
        int $userId,
        int $foyerId,
        callable $urlForBibId,
        ?string $catalogFallbackUrl = null,
    ): array {
        if ($oeuvreId <= 0) {
            return [
                'in_library' => false,
                'library_bib_id' => 0,
                'library_url' => $catalogFallbackUrl ?? '',
            ];
        }

        $libraryRepo = new BibliothequeRepository();
        $collection = $libraryRepo->findByOeuvreId($oeuvreId, $userId, $foyerId, LibraryStatut::COLLECTION);
        $collectionBibId = $collection !== null ? (int) ($collection['id'] ?? 0) : 0;
        $inLibrary = $collectionBibId > 0;
        $url = '';

        if ($inLibrary) {
            $url = (string) $urlForBibId($collectionBibId);
        } else {
            $wishlist = $libraryRepo->findByOeuvreId($oeuvreId, $userId, $foyerId, LibraryStatut::WISHLIST);
            $wishlistBibId = $wishlist !== null ? (int) ($wishlist['id'] ?? 0) : 0;
            if ($wishlistBibId > 0) {
                $url = (string) $urlForBibId($wishlistBibId);
            } elseif ($catalogFallbackUrl !== null && $catalogFallbackUrl !== '') {
                $url = $catalogFallbackUrl;
            }
        }

        return [
            'in_library' => $inLibrary,
            'library_bib_id' => $collectionBibId,
            'library_url' => $url,
        ];
    }
}
