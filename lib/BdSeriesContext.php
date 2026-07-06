<?php
/**
 * Contexte de série BD : tomes voisins autour du tome courant (catalogue).
 */

declare(strict_types=1);

namespace Moncine;

final class BdSeriesContext
{
    /**
     * Bandeau : jusqu’à 4 tomes avant, le tome courant (encadré), jusqu’à 4 tomes après.
     *
     * @return list<array{
     *     url: string,
     *     poster_url: mixed,
     *     annee: int,
     *     titre: string,
     *     is_possessed: bool,
     *     is_current: bool,
     *     tome_label: string
     * }>
     */
    public static function neighborStrip(
        BdRepository $repo,
        int $seriesId,
        int $currentOeuvreId,
        int $userId,
        int $foyerId,
        int $before = 4,
        int $after = 4,
    ): array {
        if ($seriesId <= 0 || $currentOeuvreId <= 0) {
            return [];
        }

        $tomes = $repo->listCatalogTomesForSeries($seriesId);
        if ($tomes === []) {
            return [];
        }

        $currentIndex = null;
        foreach ($tomes as $index => $row) {
            if ((int) ($row['oeuvre_id'] ?? 0) === $currentOeuvreId) {
                $currentIndex = $index;
                break;
            }
        }

        if ($currentIndex === null) {
            return [];
        }

        $start = max(0, $currentIndex - $before);
        $end = min(count($tomes) - 1, $currentIndex + $after);
        $slice = array_slice($tomes, $start, $end - $start + 1);

        $items = [];
        foreach ($slice as $row) {
            $oeuvreId = (int) ($row['oeuvre_id'] ?? 0);
            if ($oeuvreId <= 0) {
                continue;
            }

            $libraryState = DetailLibraryState::forOeuvre(
                $oeuvreId,
                $userId,
                $foyerId,
                static fn (int $bibId): string => View::bdUrl($bibId),
            );

            $isPossessed = false;
            $collectionBibId = (int) ($libraryState['library_bib_id'] ?? 0);
            if ($collectionBibId > 0) {
                $bibRow = $repo->findByBibId($collectionBibId, $userId, $foyerId);
                $isPossessed = $bibRow !== null && BdPossession::isPossessed($bibRow);
            }

            $items[] = [
                'url' => (string) ($libraryState['library_url'] ?? ''),
                'poster_url' => $row['poster_url'] ?? null,
                'annee' => (int) ($row['annee'] ?? 0),
                'titre' => (string) ($row['display_titre'] ?? $row['titre'] ?? ''),
                'is_possessed' => $isPossessed,
                'is_current' => $oeuvreId === $currentOeuvreId,
                'tome_label' => (string) ($row['tome_summary'] ?? ''),
            ];
        }

        return $items;
    }
}
