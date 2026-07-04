<?php
/**
 * Ordre de tri et segments de chemin pour les numéros de magazines.
 */

declare(strict_types=1);

namespace Moncine;

final class MagazineNumeroOrdre
{
    public static function adjustForHorsSerie(float $numeroOrdre, bool $horsSerie): float
    {
        if ($horsSerie && $numeroOrdre > 0 && $numeroOrdre === (float) (int) $numeroOrdre) {
            return $numeroOrdre + 0.5;
        }

        if (
            !$horsSerie
            && $numeroOrdre > 0
            && $numeroOrdre === (float) (int) $numeroOrdre + 0.5
        ) {
            return (float) (int) $numeroOrdre;
        }

        return $numeroOrdre;
    }

    public static function slugifyForPath(string $text, string $fallback): string
    {
        $text = mb_strtolower(trim($text), 'UTF-8');
        if ($text === '') {
            return $fallback;
        }

        if (function_exists('iconv')) {
            $ascii = iconv('UTF-8', 'ASCII//TRANSLIT//IGNORE', $text);
            if ($ascii !== false) {
                $text = strtolower($ascii);
            }
        }

        $slug = preg_replace('/[^a-z0-9]+/', '-', $text) ?? '';
        $slug = trim($slug, '-');

        return $slug !== '' ? $slug : $fallback;
    }

    public static function extractParutionYear(string $dateParution): string
    {
        $dateParution = trim($dateParution);
        if ($dateParution === '') {
            return 'inconnu';
        }

        if (preg_match('/^(19|20)\d{2}/', $dateParution, $matches) === 1) {
            return $matches[0];
        }

        $timestamp = strtotime($dateParution);

        return $timestamp !== false ? date('Y', $timestamp) : 'inconnu';
    }
}
