<?php
/**
 * Construction des sections « jeux liés » (extension, remake, jaquettes) pour les fiches.
 */

declare(strict_types=1);

namespace Moncine;

final class GameRelatedSections
{
    /**
     * Bandeau jaquettes : jeu de base / origine, ou listes extensions et remakes.
     *
     * @param array<string, mixed> $game Fiche courante (flags is_extension / is_remake)
     * @param array<string, mixed>|null $baseGame Jeu de base (extension)
     * @param array<string, mixed>|null $originalGame Jeu d’origine (remake)
     * @param list<array<string, mixed>> $extensions Extensions ou remakes liés (liste secondaire)
     * @param list<array<string, mixed>> $remakes
     * @param callable(array<string, mixed>): string $urlForRelated URL pour chaque ligne extensions/remakes
     * @return list<array{title: string, items: list<array{url: string, poster_url: mixed, annee: int, titre: string}>}>
     */
    public static function build(
        array $game,
        ?array $baseGame,
        ?array $originalGame,
        array $extensions,
        array $remakes,
        callable $urlForRelated,
    ): array {
        if (!empty($game['is_extension'])) {
            $item = self::parentItem($baseGame);

            return $item !== null
                ? [['title' => 'Jeu de base', 'items' => [$item]]]
                : [];
        }

        if (!empty($game['is_remake'])) {
            $item = self::parentItem($originalGame);

            return $item !== null
                ? [['title' => 'Jeu d\'origine', 'items' => [$item]]]
                : [];
        }

        $sections = [];

        $extensionItems = self::mapRelatedItems($extensions, $urlForRelated);
        if ($extensionItems !== []) {
            $sections[] = [
                'title' => 'Extensions',
                'items' => $extensionItems,
            ];
        }

        $remakeItems = self::mapRelatedItems($remakes, $urlForRelated);
        if ($remakeItems !== []) {
            $sections[] = [
                'title' => 'Remakes',
                'items' => $remakeItems,
            ];
        }

        return $sections;
    }

    /**
     * @param array<string, mixed>|null $parent
     * @return array{url: string, poster_url: mixed, annee: int, titre: string}|null
     */
    private static function parentItem(?array $parent): ?array
    {
        if (!is_array($parent) || (int) ($parent['oeuvre_id'] ?? 0) <= 0) {
            return null;
        }

        return [
            'url' => trim((string) ($parent['library_url'] ?? '')),
            'poster_url' => $parent['poster_url'] ?? null,
            'annee' => (int) ($parent['annee'] ?? 0),
            'titre' => (string) ($parent['titre'] ?? ''),
        ];
    }

    /**
     * @param list<array<string, mixed>> $rows
     * @param callable(array<string, mixed>): string $urlForRelated
     * @return list<array{url: string, poster_url: mixed, annee: int, titre: string}>
     */
    private static function mapRelatedItems(array $rows, callable $urlForRelated): array
    {
        $items = [];
        foreach ($rows as $row) {
            if (!is_array($row)) {
                continue;
            }
            $items[] = [
                'url' => $urlForRelated($row),
                'poster_url' => $row['poster_url'] ?? null,
                'annee' => (int) ($row['annee'] ?? 0),
                'titre' => (string) ($row['titre'] ?? ''),
            ];
        }

        return $items;
    }
}
