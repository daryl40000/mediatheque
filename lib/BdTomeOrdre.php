<?php
/**
 * Numérotation et ordre de tri des tomes BD.
 */

declare(strict_types=1);

namespace Moncine;

final class BdTomeOrdre
{
    /**
     * Calcule l’ordre de tri (décimal si hors-série, ex. 38.5 entre 38 et 39).
     *
     * @param array<string, mixed> $data
     */
    public static function resolve(array $data, int $seriesId, float $fallbackOrdre = 0): float
    {
        $tomeOrdre = (float) ($data['tome_ordre'] ?? 0);
        $tomeNum = max(0, (int) ($data['tome_numero'] ?? 0));
        $tomeLabel = trim((string) ($data['tome_label'] ?? ''));
        $horsSerie = !empty($data['est_hors_serie']);
        $isExplicitTomeZero = $tomeNum === 0 && $tomeLabel === '';

        if ($isExplicitTomeZero) {
            if ($tomeOrdre <= 0) {
                $tomeOrdre = 0.0;
            }
            if ($horsSerie && $tomeOrdre === (float) (int) $tomeOrdre) {
                $tomeOrdre += 0.5;
            }

            return $tomeOrdre;
        }

        if ($tomeOrdre <= 0) {
            $tomeOrdre = $tomeNum > 0 ? (float) $tomeNum : $fallbackOrdre;
        }
        if ($tomeOrdre <= 0 && $seriesId > 0) {
            $tomeOrdre = (new BdLibraryQuery(Database::getInstance()))->maxTomeOrdreForSeries($seriesId) + 1.0;
        }
        if ($horsSerie && $tomeOrdre === (float) (int) $tomeOrdre) {
            $tomeOrdre += 0.5;
        }

        return $tomeOrdre;
    }

    public static function suggestNextTomeNumero(int $lastTome): int
    {
        return $lastTome > 0 ? $lastTome + 1 : 1;
    }

    public static function suggestNextTomeOrdre(float $lastOrdre): float
    {
        return $lastOrdre > 0 ? $lastOrdre + 1.0 : 1.0;
    }
}
