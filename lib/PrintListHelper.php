<?php
/**
 * Libellés pour les pages imprimables (Mes films / Mes envies).
 */

declare(strict_types=1);

namespace Moncine;

final class PrintListHelper
{
    /** @var array<string, string> */
    private const SORT_LABELS = [
        'titre' => 'Titre',
        'annee' => 'Année',
        'realisateur' => 'Réalisateur',
        'duree_min' => 'Durée',
        'styles' => 'Style',
        'support_physique' => 'Support',
        'note' => 'Notes',
        'derniere_vue' => 'Dernière vue',
        'votes' => 'Demandes',
    ];

    /** @var array<string, string> */
    private const GAME_SORT_LABELS = [
        'titre' => 'Titre',
        'annee' => 'Année',
        'platform' => 'Plateforme',
        'franchise' => 'Saga',
        'studio' => 'Studio',
        'genre' => 'Genres',
        'support' => 'Support',
        'note' => 'Notes',
        'finished_at' => 'Fini le',
        'steam_playtime' => 'Temps Steam',
    ];

    public static function sortLabel(string $sortBy): string
    {
        return self::SORT_LABELS[$sortBy] ?? $sortBy;
    }

    public static function sortDirectionLabel(string $dir): string
    {
        return strtolower($dir) === 'desc' ? 'décroissant' : 'croissant';
    }

    public static function sortSummary(string $sortBy, string $sortDir): string
    {
        return self::sortLabel($sortBy) . ' (' . self::sortDirectionLabel($sortDir) . ')';
    }

    public static function gameSortLabel(string $sortBy): string
    {
        return self::GAME_SORT_LABELS[$sortBy] ?? $sortBy;
    }

    public static function gameSortSummary(string $sortBy, string $sortDir): string
    {
        return self::gameSortLabel($sortBy) . ' (' . self::sortDirectionLabel($sortDir) . ')';
    }

    public static function gameCollectionFilterSummary(
        string $query,
        GameListFilter $listFilter,
        int $resultCount
    ): string {
        $parts = [];
        $filterLabel = $listFilter->activeLabel();
        if ($filterLabel !== '') {
            $parts[] = $filterLabel;
        }
        if (trim($query) !== '') {
            $parts[] = 'recherche « ' . trim($query) . ' »';
        }
        $count = $resultCount . ' jeu' . ($resultCount > 1 ? 'x' : '');
        if ($parts !== []) {
            return $count . ' — ' . implode(', ', $parts);
        }

        return $count;
    }

    public static function gameWishlistFilterSummary(string $query, int $resultCount): string
    {
        $count = $resultCount . ' jeu' . ($resultCount > 1 ? 'x' : '');
        if (trim($query) !== '') {
            return $count . ' — recherche « ' . trim($query) . ' »';
        }

        return $count;
    }

    public static function collectionFilterSummary(
        string $query,
        string $kindFilter,
        int $resultCount,
        int $totalCount
    ): string {
        $parts = [];
        if ($kindFilter !== ContentKindFilter::ALL) {
            $parts[] = ContentKindFilter::label($kindFilter);
        }
        if (trim($query) !== '') {
            $parts[] = 'recherche « ' . trim($query) . ' »';
        }
        $count = $resultCount . ' titre' . ($resultCount > 1 ? 's' : '');
        if ($parts !== []) {
            return $count . ' — ' . implode(', ', $parts);
        }
        if ($totalCount !== $resultCount && $totalCount > 0) {
            return $count . ' (sur ' . $totalCount . ' au total)';
        }

        return $count;
    }

    public static function wishlistFilterSummary(
        string $query,
        int $resultCount,
        bool $isGroupScope,
        string $groupName = ''
    ): string {
        $count = $resultCount . ' titre' . ($resultCount > 1 ? 's' : '');
        if ($isGroupScope) {
            $label = $groupName !== '' ? 'Envies du groupe — ' . $groupName : 'Envies du groupe';

            return $count . ' — ' . $label;
        }
        if (trim($query) !== '') {
            return $count . ' — recherche « ' . trim($query) . ' »';
        }

        return $count;
    }
}
