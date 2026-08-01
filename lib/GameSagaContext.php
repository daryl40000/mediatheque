<?php
/**
 * Contexte de saga jeux : titres voisins autour du jeu courant (encadré).
 */

declare(strict_types=1);

namespace Moncine;

final class GameSagaContext
{
    /**
     * Bandeau : jusqu’à 4 jeux avant, le jeu courant (encadré), jusqu’à 4 jeux après.
     *
     * @return list<array{
     *     oeuvre_id: int,
     *     url: string,
     *     library_url: string,
     *     poster_url: mixed,
     *     annee: int,
     *     titre: string,
     *     in_library: bool,
     *     is_current: bool
     * }>
     */
    public static function neighborStrip(
        GameFranchiseRepository $franchiseRepo,
        GameRepository $gameRepo,
        string $franchise,
        int $currentOeuvreId,
        int $userId,
        int $foyerId,
        int $before = 4,
        int $after = 4,
    ): array {
        $franchise = trim($franchise);
        if ($franchise === '' || $currentOeuvreId <= 0 || !GameFranchiseRepository::isAvailable()) {
            return [];
        }

        // Inclure tous les titres de la saga (y compris le jeu affiché).
        $games = $franchiseRepo->listCatalogByFranchise($franchise, 0);
        if ($games === []) {
            return [];
        }

        $currentIndex = null;
        foreach ($games as $index => $row) {
            if ((int) ($row['oeuvre_id'] ?? 0) === $currentOeuvreId) {
                $currentIndex = $index;
                break;
            }
        }

        // Si le jeu courant n’est pas dans la liste catalogue (cas rare), on affiche quand même les autres.
        if ($currentIndex === null) {
            $items = [];
            foreach ($games as $row) {
                $items[] = self::mapRow($gameRepo, $row, $userId, $foyerId, false);
            }

            return array_values(array_filter($items));
        }

        $start = max(0, $currentIndex - $before);
        $end = min(count($games) - 1, $currentIndex + $after);
        $slice = array_slice($games, $start, $end - $start + 1);

        $items = [];
        foreach ($slice as $row) {
            $oeuvreId = (int) ($row['oeuvre_id'] ?? 0);
            $isCurrent = $oeuvreId === $currentOeuvreId;
            $mapped = self::mapRow($gameRepo, $row, $userId, $foyerId, $isCurrent);
            if ($mapped !== null) {
                $items[] = $mapped;
            }
        }

        return $items;
    }

    /**
     * @param array<string, mixed> $row
     * @return array{
     *     oeuvre_id: int,
     *     url: string,
     *     library_url: string,
     *     poster_url: mixed,
     *     annee: int,
     *     titre: string,
     *     in_library: bool,
     *     is_current: bool
     * }|null
     */
    private static function mapRow(
        GameRepository $gameRepo,
        array $row,
        int $userId,
        int $foyerId,
        bool $isCurrent,
    ): ?array {
        $oeuvreId = (int) ($row['oeuvre_id'] ?? 0);
        if ($oeuvreId <= 0) {
            return null;
        }

        $libraryState = GameRelatedSections::libraryStateForRelatedOeuvre(
            $gameRepo,
            $oeuvreId,
            $userId,
            $foyerId,
            View::oeuvreJeuUrl($oeuvreId),
        );

        return [
            'oeuvre_id' => $oeuvreId,
            'url' => $isCurrent ? '' : (string) ($libraryState['library_url'] ?? ''),
            'library_url' => $isCurrent ? '' : (string) ($libraryState['library_url'] ?? ''),
            'poster_url' => $row['poster_url'] ?? null,
            'annee' => (int) ($row['annee'] ?? 0),
            'titre' => (string) ($row['display_titre'] ?? $row['titre'] ?? ''),
            'in_library' => !empty($libraryState['in_library']),
            'is_current' => $isCurrent,
        ];
    }
}
