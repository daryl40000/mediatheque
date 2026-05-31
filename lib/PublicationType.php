<?php
/**
 * Périodicité d’une série (magazine, revue…) et formatage des dates de parution.
 */

declare(strict_types=1);

namespace Moncine;

final class PublicationType
{
    public const HEBDOMADAIRE = 'hebdomadaire';
    public const MENSUEL = 'mensuel';
    public const BIMENSUEL = 'bimensuel';
    public const TRIMESTRIEL = 'trimestriel';
    public const ANNUEL = 'annuel';
    public const IRREGULIER = 'irregulier';

    /** @return array<string, string> clé => libellé affiché */
    public static function choices(): array
    {
        return [
            self::HEBDOMADAIRE => 'Hebdomadaire',
            self::MENSUEL => 'Mensuel',
            self::BIMENSUEL => 'Bimensuel',
            self::TRIMESTRIEL => 'Trimestriel',
            self::ANNUEL => 'Annuel',
            self::IRREGULIER => 'Irrégulier',
        ];
    }

    public static function normalize(string $raw): string
    {
        $raw = mb_strtolower(trim($raw));
        if ($raw === '') {
            return self::MENSUEL;
        }

        $aliases = [
            'hebdo' => self::HEBDOMADAIRE,
            'weekly' => self::HEBDOMADAIRE,
            'monthly' => self::MENSUEL,
            'mensuelle' => self::MENSUEL,
            'bimestriel' => self::BIMENSUEL,
            'quarterly' => self::TRIMESTRIEL,
            'yearly' => self::ANNUEL,
            'annuelle' => self::ANNUEL,
        ];

        if (isset(self::choices()[$raw])) {
            return $raw;
        }

        return $aliases[$raw] ?? self::MENSUEL;
    }

    public static function label(string $type): string
    {
        $type = self::normalize($type);

        return self::choices()[$type] ?? self::choices()[self::MENSUEL];
    }

    /**
     * Affiche une date ISO (YYYY-MM-DD) selon la périodicité de la série.
     */
    public static function formatParutionDate(?string $isoDate, string $publicationType): string
    {
        $isoDate = trim((string) $isoDate);
        if ($isoDate === '') {
            return '—';
        }

        $ts = strtotime($isoDate);
        if ($ts === false) {
            return $isoDate;
        }

        return match (self::normalize($publicationType)) {
            self::HEBDOMADAIRE => self::formatWeek($ts),
            self::MENSUEL, self::BIMENSUEL => self::formatMonthYear($ts),
            self::TRIMESTRIEL => self::formatQuarter($ts),
            self::ANNUEL => date('Y', $ts),
            default => date('d/m/Y', $ts),
        };
    }

    /** Prochain numéro suggéré (dernier + 1). */
    public static function suggestNextNumeroOrdre(float $lastOrdre): float
    {
        if ($lastOrdre <= 0) {
            return 1.0;
        }

        return $lastOrdre + 1.0;
    }

    private static function formatMonthYear(int $ts): string
    {
        $months = [
            1 => 'janvier', 2 => 'février', 3 => 'mars', 4 => 'avril',
            5 => 'mai', 6 => 'juin', 7 => 'juillet', 8 => 'août',
            9 => 'septembre', 10 => 'octobre', 11 => 'novembre', 12 => 'décembre',
        ];
        $month = (int) date('n', $ts);
        $year = date('Y', $ts);

        return ($months[$month] ?? date('m', $ts)) . ' ' . $year;
    }

    private static function formatWeek(int $ts): string
    {
        $week = (int) date('W', $ts);
        $year = date('o', $ts);

        return 'Semaine ' . $week . ' — ' . $year;
    }

    private static function formatQuarter(int $ts): string
    {
        $month = (int) date('n', $ts);
        $quarter = (int) ceil($month / 3);
        $year = date('Y', $ts);

        return 'T' . $quarter . ' ' . $year;
    }
}
