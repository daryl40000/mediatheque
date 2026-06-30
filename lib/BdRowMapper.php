<?php
/**
 * Formatage des lignes BD / manga (catalogue et bibliothèque).
 */

declare(strict_types=1);

namespace Moncine;

final class BdRowMapper
{
    /** Titre affiché (titre œuvre ou série + tome). */
    public static function displayTitle(array $row): string
    {
        $titre = trim((string) ($row['titre'] ?? ''));
        if ($titre !== '') {
            return $titre;
        }

        return self::seriesDisplayTitle($row);
    }

    /** Titre basé sur la série et le numéro de tome. */
    public static function seriesDisplayTitle(array $row): string
    {
        $series = trim((string) ($row['series_titre'] ?? ''));
        $tomeNum = (int) ($row['tome_numero'] ?? 0);
        $tomeLabel = trim((string) ($row['tome_label'] ?? ''));
        if ($series === '') {
            return 'Sans titre';
        }
        if ($tomeNum > 0) {
            return $series . ' — Tome ' . $tomeNum;
        }
        if ($tomeLabel !== '') {
            return $series . ' — ' . $tomeLabel;
        }

        return $series;
    }

    /** Libellé pour autocomplétion (ex. « Astérix — Tome 1 (1961) »). */
    public static function displayLabel(array $row): string
    {
        $titre = self::displayTitle($row);
        if ($titre === '') {
            return '';
        }

        $parts = [];
        $kind = BdKind::label((string) ($row['kind'] ?? BdKind::BD));
        if ($kind !== '') {
            $parts[] = $kind;
        }
        $annee = (int) ($row['annee'] ?? 0);
        if ($annee > 0) {
            $parts[] = (string) $annee;
        }
        if ($parts === []) {
            return $titre;
        }

        return $titre . ' (' . implode(' · ', $parts) . ')';
    }

    public static function formatAddedAt(string $createdAt): string
    {
        $createdAt = trim($createdAt);
        if ($createdAt === '') {
            return '';
        }

        return HistoriqueRepository::formatDateVue(substr($createdAt, 0, 10));
    }

    public static function formatReadAt(string $readAt): string
    {
        $readAt = trim($readAt);
        if ($readAt === '') {
            return '';
        }

        return HistoriqueRepository::formatDateVue(substr($readAt, 0, 10));
    }

    /** Résumé tome (numéro ou libellé libre). */
    public static function tomeSummary(array $row): string
    {
        $tomeNum = (int) ($row['tome_numero'] ?? 0);
        $tomeLabel = trim((string) ($row['tome_label'] ?? ''));
        if ($tomeNum > 0) {
            return 'Tome ' . $tomeNum;
        }
        if ($tomeLabel !== '') {
            return $tomeLabel;
        }

        return '';
    }

    /**
     * @param array<string, mixed> $row
     * @return array<string, mixed>
     */
    public static function hydrateBdRow(array $row): array
    {
        $row = self::hydrateCatalogFields($row);
        $row['id'] = (int) ($row['id'] ?? 0);
        $row['added_at_label'] = self::formatAddedAt((string) ($row['created_at'] ?? ''));
        $row['read_at_label'] = self::formatReadAt((string) ($row['derniere_lecture'] ?? ''));
        $row['support_label'] = BdPhysicalSupport::label((string) ($row['support_physique'] ?? ''));
        $row['is_possessed'] = BdPossession::isPossessed($row);
        $row['possession_label'] = BdPossession::possessionStatusLabel($row);

        return $row;
    }

    /**
     * @param array<string, mixed> $row
     * @return array<string, mixed>
     */
    public static function hydrateCatalogRow(array $row): array
    {
        return self::hydrateCatalogFields($row);
    }

    /**
     * @param array<string, mixed> $row
     * @return array<string, mixed>
     */
    private static function hydrateCatalogFields(array $row): array
    {
        if (isset($row['oeuvre_id'])) {
            $row['oeuvre_id'] = (int) ($row['oeuvre_id'] ?? 0);
        }
        $row['annee'] = (int) ($row['annee'] ?? 0);
        $row['series_id'] = (int) ($row['series_id'] ?? 0);
        $row['tome_numero'] = (int) ($row['tome_numero'] ?? 0);
        $row['kind'] = BdKind::normalize((string) ($row['kind'] ?? BdKind::BD));
        $row['kind_label'] = BdKind::label($row['kind']);
        $row['display_titre'] = self::displayTitle($row);
        $row['display_label'] = self::displayLabel($row);
        $row['tome_summary'] = self::tomeSummary($row);
        $row['series_titre'] = trim((string) ($row['series_titre'] ?? ''));

        return $row;
    }
}
