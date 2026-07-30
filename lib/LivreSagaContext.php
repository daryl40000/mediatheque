<?php
/**
 * Contexte de saga livres : volumes voisins autour du livre courant.
 */

declare(strict_types=1);

namespace Moncine;

final class LivreSagaContext
{
    /**
     * Bandeau : jusqu’à 4 livres avant, le livre courant (encadré), jusqu’à 4 livres après.
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
        LivreRepository $repo,
        string $saga,
        int $currentOeuvreId,
        int $userId,
        int $foyerId,
        int $before = 4,
        int $after = 4,
    ): array {
        $saga = trim($saga);
        if ($saga === '' || $currentOeuvreId <= 0) {
            return [];
        }

        $books = $repo->listFoyerBooksForSaga($saga, $userId, $foyerId);
        if ($books === []) {
            return [];
        }

        $currentIndex = null;
        foreach ($books as $index => $row) {
            if ((int) ($row['oeuvre_id'] ?? 0) === $currentOeuvreId) {
                $currentIndex = $index;
                break;
            }
        }

        if ($currentIndex === null) {
            return [];
        }

        $start = max(0, $currentIndex - $before);
        $end = min(count($books) - 1, $currentIndex + $after);
        $slice = array_slice($books, $start, $end - $start + 1);

        $items = [];
        foreach ($slice as $row) {
            $oeuvreId = (int) ($row['oeuvre_id'] ?? 0);
            $bibId = (int) ($row['bib_id'] ?? 0);
            if ($oeuvreId <= 0 || $bibId <= 0) {
                continue;
            }

            $sagaOrdre = (int) ($row['saga_ordre'] ?? 0);
            $tomeLabel = $sagaOrdre > 0 ? 'Tome ' . $sagaOrdre : '';

            $items[] = [
                'url' => View::livreUrl($bibId),
                'poster_url' => $row['poster_url'] ?? null,
                'annee' => (int) ($row['annee'] ?? 0),
                'titre' => (string) ($row['display_titre'] ?? $row['titre'] ?? ''),
                'is_possessed' => ($row['statut'] ?? '') === LibraryStatut::COLLECTION,
                'is_current' => $oeuvreId === $currentOeuvreId,
                'tome_label' => $tomeLabel,
            ];
        }

        return $items;
    }
}
