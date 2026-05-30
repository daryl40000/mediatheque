<?php
/**
 * Notes « du soir » pendant la session de choix (mémoire session, pas la base).
 */

declare(strict_types=1);

namespace Moncine;

final class ChoixNote
{
    /** @var array<string, array{label: string, score: int}> */
    public const LEVELS = [
        'non' => ['label' => 'Non', 'score' => 1],
        'bof' => ['label' => 'Bof', 'score' => 2],
        'pourquoi_pas' => ['label' => 'Pourquoi pas', 'score' => 3],
        'pas_mal' => ['label' => 'Pas mal', 'score' => 4],
        'genial' => ['label' => 'Génial', 'score' => 5],
    ];

    public static function isValid(string $key): bool
    {
        return isset(self::LEVELS[$key]);
    }

    public static function label(string $key): string
    {
        return self::LEVELS[$key]['label'] ?? $key;
    }

    public static function score(string $key): int
    {
        return self::LEVELS[$key]['score'] ?? 0;
    }
}
