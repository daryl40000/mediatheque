<?php
/**
 * Construction des sections « jeux liés » (extension, remake, jaquettes) pour les fiches.
 */

declare(strict_types=1);

namespace Moncine;

final class GameRelatedSections
{
    /**
     * État bibliothèque d’un jeu lié catalogue : seule la collection compte comme « possédé » (non grisé).
     *
     * @return array{in_library: bool, library_bib_id: int, library_url: string}
     */
    public static function libraryStateForRelatedOeuvre(
        GameRepository $repo,
        int $oeuvreId,
        int $userId,
        int $foyerId,
        ?string $catalogFallbackUrl = null,
    ): array {
        $collectionBibId = $repo->findCollectionBibIdForCatalogOeuvre($oeuvreId, $userId, $foyerId);
        $inLibrary = $collectionBibId !== null && $collectionBibId > 0;
        $url = '';
        if ($inLibrary) {
            $url = View::gameUrl($collectionBibId);
        } else {
            $wishlistBibId = $repo->findWishlistBibIdForCatalogOeuvre($oeuvreId, $userId, $foyerId);
            if ($wishlistBibId !== null && $wishlistBibId > 0) {
                $url = View::gameUrl($wishlistBibId);
            } elseif ($catalogFallbackUrl !== null && $catalogFallbackUrl !== '') {
                $url = $catalogFallbackUrl;
            }
        }

        return [
            'in_library' => $inLibrary,
            'library_bib_id' => (int) ($collectionBibId ?? 0),
            'library_url' => $url,
        ];
    }

    /**
     * Bandeau jaquettes : jeu de base / origine, ou listes extensions et remakes.
     *
     * @param array<string, mixed> $game Fiche courante (flags is_extension / is_remake)
     * @param array<string, mixed>|null $baseGame Jeu de base (extension)
     * @param array<string, mixed>|null $originalGame Jeu d’origine (remake)
     * @param list<array<string, mixed>> $extensions Extensions ou remakes liés (liste secondaire)
     * @param list<array<string, mixed>> $remakes
     * @param list<array<string, mixed>> $franchiseGames Autres jeux de la même saga (catalogue)
     * @param callable(array<string, mixed>): string $urlForRelated URL pour chaque ligne extensions/remakes/saga
     * @return list<array{title: string, items: list<array{url: string, poster_url: mixed, annee: int, titre: string, in_library: bool}>}>
     */
    public static function build(
        array $game,
        ?array $baseGame,
        ?array $originalGame,
        array $extensions,
        array $remakes,
        callable $urlForRelated,
        array $franchiseGames = [],
    ): array {
        if (!empty($game['is_extension'])) {
            $sections = [];
            $item = self::parentItem($baseGame);
            if ($item !== null) {
                $sections[] = [
                    'title' => 'Jeu de base',
                    'layout' => 'compact',
                    'items' => [$item],
                ];
            }

            return self::appendFranchiseSection($sections, $franchiseGames, $urlForRelated);
        }

        if (!empty($game['is_remake'])) {
            $sections = [];
            $item = self::parentItem($originalGame);
            if ($item !== null) {
                $sections[] = [
                    'title' => 'Jeu d\'origine',
                    'layout' => 'compact',
                    'items' => [$item],
                ];
            }

            return self::appendFranchiseSection($sections, $franchiseGames, $urlForRelated);
        }

        $sections = [];

        $extensionItems = self::mapRelatedItems($extensions, $urlForRelated);
        if ($extensionItems !== []) {
            $sections[] = [
                'title' => 'Extensions',
                'layout' => 'compact',
                'items' => $extensionItems,
            ];
        }

        $franchiseItems = self::mapRelatedItems($franchiseGames, $urlForRelated);
        if ($franchiseItems !== []) {
            $sections[] = [
                'title' => 'Saga',
                'layout' => 'wide',
                'items' => $franchiseItems,
            ];
        }

        $remakeItems = self::mapRelatedItems($remakes, $urlForRelated);
        if ($remakeItems !== []) {
            $sections[] = [
                'title' => 'Remakes',
                'layout' => 'compact',
                'items' => $remakeItems,
            ];
        }

        return $sections;
    }

    /** Saga catalogue : jeu courant, jeu de base ou jeu d’origine. */
    public static function resolveFranchiseName(array $game, ?array $baseGame = null, ?array $originalGame = null): string
    {
        $name = trim((string) ($game['franchise'] ?? ''));
        if ($name !== '') {
            return $name;
        }

        if ($baseGame !== null) {
            $name = trim((string) ($baseGame['franchise'] ?? ''));
            if ($name !== '') {
                return $name;
            }
        }

        if ($originalGame !== null) {
            return trim((string) ($originalGame['franchise'] ?? ''));
        }

        return '';
    }

    /**
     * @param list<array{title: string, layout?: string, items: list<array<string, mixed>>}> $sections
     * @param list<array<string, mixed>> $franchiseGames
     * @param callable(array<string, mixed>): string $urlForRelated
     * @return list<array{title: string, layout?: string, items: list<array<string, mixed>>}>
     */
    private static function appendFranchiseSection(array $sections, array $franchiseGames, callable $urlForRelated): array
    {
        $franchiseItems = self::mapRelatedItems($franchiseGames, $urlForRelated);
        if ($franchiseItems === []) {
            return $sections;
        }

        $sections[] = [
            'title' => 'Saga',
            'layout' => 'wide',
            'items' => $franchiseItems,
        ];

        return $sections;
    }

    /**
     * @param array<string, mixed>|null $parent
     * @return array{url: string, poster_url: mixed, annee: int, titre: string, in_library: bool}|null
     */
    private static function parentItem(?array $parent): ?array
    {
        if (!is_array($parent) || (int) ($parent['oeuvre_id'] ?? 0) <= 0) {
            return null;
        }

        $inLibrary = array_key_exists('in_library', $parent)
            ? (bool) $parent['in_library']
            : (int) ($parent['library_bib_id'] ?? 0) > 0;

        return [
            'url' => trim((string) ($parent['library_url'] ?? '')),
            'poster_url' => $parent['poster_url'] ?? null,
            'annee' => (int) ($parent['annee'] ?? 0),
            'titre' => (string) ($parent['titre'] ?? ''),
            'in_library' => $inLibrary,
        ];
    }

    /**
     * @param list<array<string, mixed>> $rows
     * @param callable(array<string, mixed>): string $urlForRelated
     * @return list<array{url: string, poster_url: mixed, annee: int, titre: string, in_library: bool}>
     */
    private static function mapRelatedItems(array $rows, callable $urlForRelated): array
    {
        $items = [];
        foreach ($rows as $row) {
            if (!is_array($row)) {
                continue;
            }
            $inLibrary = array_key_exists('in_library', $row)
                ? (bool) $row['in_library']
                : (int) ($row['bib_id'] ?? $row['library_bib_id'] ?? 0) > 0;
            $items[] = [
                'url' => $urlForRelated($row),
                'poster_url' => $row['poster_url'] ?? null,
                'annee' => (int) ($row['annee'] ?? 0),
                'titre' => (string) ($row['titre'] ?? ''),
                'in_library' => $inLibrary,
            ];
        }

        return $items;
    }
}
