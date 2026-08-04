<?php
/**
 * Périodes d’échelle de notation d’une série magazine (par plage de numéros).
 *
 * Exemple : du n°1 au n°92 → notes sur 5 ; du n°93 au n°110 → notes sur 100.
 * series.rating_scale reste l’échelle « par défaut » si aucune période ne correspond.
 */

declare(strict_types=1);

namespace Moncine;

use PDO;

final class MagazineRatingPeriod
{
    private static ?bool $tableExistsCache = null;

    public static function tableExists(): bool
    {
        if (self::$tableExistsCache !== null) {
            return self::$tableExistsCache;
        }

        try {
            $stmt = Database::getInstance()->query(
                "SELECT 1 FROM sqlite_master WHERE type = 'table' AND name = 'magazine_series_rating_period' LIMIT 1"
            );
            self::$tableExistsCache = $stmt !== false && $stmt->fetchColumn() !== false;
        } catch (\Throwable) {
            self::$tableExistsCache = false;
        }

        return self::$tableExistsCache;
    }

    /** Réinitialise le cache (tests). */
    public static function resetTableExistsCache(): void
    {
        self::$tableExistsCache = null;
    }

    /**
     * @return list<array{id: int, series_id: int, from_numero_ordre: float, to_numero_ordre: float|null, rating_scale: string, sort_order: int}>
     */
    public static function listForSeries(int $seriesId): array
    {
        if (!self::tableExists() || $seriesId <= 0) {
            return [];
        }

        $stmt = Database::getInstance()->prepare(
            'SELECT id, series_id, from_numero_ordre, to_numero_ordre, rating_scale, sort_order
             FROM magazine_series_rating_period
             WHERE series_id = ?
             ORDER BY sort_order ASC, from_numero_ordre ASC, id ASC'
        );
        $stmt->execute([$seriesId]);

        $rows = [];
        foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) ?: [] as $row) {
            $normalized = self::normalizeRow($row);
            if ($normalized !== null) {
                $rows[] = $normalized;
            }
        }

        return $rows;
    }

    /**
     * Charge les périodes pour plusieurs séries d’un coup (évite N requêtes).
     *
     * @param list<int> $seriesIds
     * @return array<int, list<array{id: int, series_id: int, from_numero_ordre: float, to_numero_ordre: float|null, rating_scale: string, sort_order: int}>>
     */
    public static function mapForSeriesIds(array $seriesIds): array
    {
        $unique = [];
        foreach ($seriesIds as $id) {
            $id = (int) $id;
            if ($id > 0) {
                $unique[$id] = true;
            }
        }
        $ids = array_keys($unique);
        $out = [];
        foreach ($ids as $id) {
            $out[$id] = [];
        }

        if (!self::tableExists() || $ids === []) {
            return $out;
        }

        $placeholders = implode(',', array_fill(0, count($ids), '?'));
        $stmt = Database::getInstance()->prepare(
            'SELECT id, series_id, from_numero_ordre, to_numero_ordre, rating_scale, sort_order
             FROM magazine_series_rating_period
             WHERE series_id IN (' . $placeholders . ')
             ORDER BY series_id ASC, sort_order ASC, from_numero_ordre ASC, id ASC'
        );
        $stmt->execute($ids);

        foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) ?: [] as $row) {
            $normalized = self::normalizeRow($row);
            if ($normalized === null) {
                continue;
            }
            $seriesId = (int) $normalized['series_id'];
            $out[$seriesId][] = $normalized;
        }

        return $out;
    }

    /**
     * Échelle applicable à un numéro : période correspondante, sinon défaut série.
     *
     * @param list<array<string, mixed>> $periods
     */
    public static function resolve(?string $defaultScale, array $periods, float $numeroOrdre): ?string
    {
        $defaultScale = MagazineRatingScale::normalize($defaultScale);
        foreach ($periods as $period) {
            if (!is_array($period)) {
                continue;
            }
            $from = (float) ($period['from_numero_ordre'] ?? 0);
            $toRaw = $period['to_numero_ordre'] ?? null;
            $to = ($toRaw === null || $toRaw === '') ? null : (float) $toRaw;
            if ($numeroOrdre + 0.00001 < $from) {
                continue;
            }
            if ($to !== null && $numeroOrdre - 0.00001 > $to) {
                continue;
            }
            $scale = MagazineRatingScale::normalize($period['rating_scale'] ?? null);
            if ($scale !== null) {
                return $scale;
            }
        }

        return $defaultScale;
    }

    /**
     * Lit le formulaire (tableaux paralleles from / to / scale).
     *
     * @param array<string, mixed> $post
     * @return list<array{from_numero_ordre: float, to_numero_ordre: float|null, rating_scale: string}>|string
     */
    public static function parseFromPost(array $post): array|string
    {
        $fromList = $post['rating_period_from'] ?? [];
        $toList = $post['rating_period_to'] ?? [];
        $scaleList = $post['rating_period_scale'] ?? [];

        if (!is_array($fromList)) {
            $fromList = [];
        }
        if (!is_array($toList)) {
            $toList = [];
        }
        if (!is_array($scaleList)) {
            $scaleList = [];
        }

        $count = max(count($fromList), count($toList), count($scaleList));
        $periods = [];

        for ($i = 0; $i < $count; $i++) {
            $fromRaw = trim((string) ($fromList[$i] ?? ''));
            $toRaw = trim((string) ($toList[$i] ?? ''));
            $scaleRaw = trim((string) ($scaleList[$i] ?? ''));

            // Ligne entièrement vide → ignorée.
            if ($fromRaw === '' && $toRaw === '' && $scaleRaw === '') {
                continue;
            }

            if ($fromRaw === '' || !is_numeric(str_replace(',', '.', $fromRaw))) {
                return 'Chaque période doit indiquer un numéro de début (ex. 1 ou 93).';
            }
            $from = (float) str_replace(',', '.', $fromRaw);
            if ($from < 0) {
                return 'Le numéro de début d’une période ne peut pas être négatif.';
            }

            $to = null;
            if ($toRaw !== '') {
                if (!is_numeric(str_replace(',', '.', $toRaw))) {
                    return 'Le numéro de fin d’une période est invalide.';
                }
                $to = (float) str_replace(',', '.', $toRaw);
                if ($to < $from) {
                    return 'Dans une période, le numéro de fin doit être ≥ au numéro de début.';
                }
            }

            $scale = MagazineRatingScale::normalize($scaleRaw);
            if ($scale === null) {
                return 'Chaque période doit indiquer une échelle valide (ex. 5, 10, 20, 100).';
            }

            $periods[] = [
                'from_numero_ordre' => $from,
                'to_numero_ordre' => $to,
                'rating_scale' => $scale,
            ];
        }

        $overlapError = self::validateNoOverlap($periods);
        if ($overlapError !== null) {
            return $overlapError;
        }

        // Tri naturel pour l’affichage / le stockage.
        usort(
            $periods,
            static function (array $a, array $b): int {
                return $a['from_numero_ordre'] <=> $b['from_numero_ordre'];
            }
        );

        return $periods;
    }

    /**
     * Remplace toutes les périodes d’une série.
     *
     * @param list<array{from_numero_ordre: float, to_numero_ordre: float|null, rating_scale: string}> $periods
     */
    public static function replaceForSeries(int $seriesId, array $periods): true|string
    {
        if ($seriesId <= 0) {
            return 'Série invalide.';
        }
        if (!self::tableExists()) {
            return 'Table des périodes d’échelle indisponible (migration 075).';
        }

        $overlapError = self::validateNoOverlap($periods);
        if ($overlapError !== null) {
            return $overlapError;
        }

        $db = Database::getInstance();
        $db->beginTransaction();
        try {
            $del = $db->prepare('DELETE FROM magazine_series_rating_period WHERE series_id = ?');
            $del->execute([$seriesId]);

            $ins = $db->prepare(
                'INSERT INTO magazine_series_rating_period
                    (series_id, from_numero_ordre, to_numero_ordre, rating_scale, sort_order)
                 VALUES (?, ?, ?, ?, ?)'
            );
            $sort = 0;
            foreach ($periods as $period) {
                $scale = MagazineRatingScale::normalize($period['rating_scale'] ?? null);
                if ($scale === null) {
                    throw new \RuntimeException('Échelle de période invalide.');
                }
                $from = (float) ($period['from_numero_ordre'] ?? 0);
                $toRaw = $period['to_numero_ordre'] ?? null;
                $to = ($toRaw === null || $toRaw === '') ? null : (float) $toRaw;
                $ins->execute([$seriesId, $from, $to, $scale, $sort]);
                $sort++;
            }
            $db->commit();
        } catch (\Throwable $e) {
            if ($db->inTransaction()) {
                $db->rollBack();
            }

            return 'Impossible d’enregistrer les périodes d’échelle : ' . $e->getMessage();
        }

        return true;
    }

    /**
     * Libellé court d’une période (ex. « n°1 → 92 : Sur 5 »).
     *
     * @param array<string, mixed> $period
     */
    public static function formatLabel(array $period): string
    {
        $from = MagazineRatingScale::formatNumber((float) ($period['from_numero_ordre'] ?? 0));
        $toRaw = $period['to_numero_ordre'] ?? null;
        $scale = MagazineRatingScale::label(
            MagazineRatingScale::normalize($period['rating_scale'] ?? null)
        );
        if ($toRaw === null || $toRaw === '') {
            return 'n°' . $from . ' → … : ' . $scale;
        }

        $to = MagazineRatingScale::formatNumber((float) $toRaw);

        return 'n°' . $from . ' → ' . $to . ' : ' . $scale;
    }

    /**
     * @param list<array{from_numero_ordre: float, to_numero_ordre: float|null, rating_scale: string}> $periods
     */
    private static function validateNoOverlap(array $periods): ?string
    {
        $sorted = $periods;
        usort(
            $sorted,
            static function (array $a, array $b): int {
                return $a['from_numero_ordre'] <=> $b['from_numero_ordre'];
            }
        );

        $previousEnd = null;
        foreach ($sorted as $period) {
            $from = (float) $period['from_numero_ordre'];
            $to = $period['to_numero_ordre'];
            if ($previousEnd !== null && $from <= $previousEnd + 0.00001) {
                return 'Les plages de numéros se chevauchent. Chaque période doit être distincte.';
            }
            if ($to === null) {
                // Ouverte jusqu’à la fin : aucune période suivante possible.
                $previousEnd = PHP_FLOAT_MAX;
            } else {
                $previousEnd = (float) $to;
            }
        }

        return null;
    }

    /**
     * @param array<string, mixed> $row
     * @return array{id: int, series_id: int, from_numero_ordre: float, to_numero_ordre: float|null, rating_scale: string, sort_order: int}|null
     */
    private static function normalizeRow(array $row): ?array
    {
        $scale = MagazineRatingScale::normalize($row['rating_scale'] ?? null);
        if ($scale === null) {
            return null;
        }
        $toRaw = $row['to_numero_ordre'] ?? null;
        $to = ($toRaw === null || $toRaw === '') ? null : (float) $toRaw;

        return [
            'id' => (int) ($row['id'] ?? 0),
            'series_id' => (int) ($row['series_id'] ?? 0),
            'from_numero_ordre' => (float) ($row['from_numero_ordre'] ?? 0),
            'to_numero_ordre' => $to,
            'rating_scale' => $scale,
            'sort_order' => (int) ($row['sort_order'] ?? 0),
        ];
    }
}
