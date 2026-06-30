<?php
/**
 * Possession d’un tome BD (support physique renseigné).
 */

declare(strict_types=1);

namespace Moncine;

final class BdPossession
{
    /** @param array<string, mixed> $row */
    public static function isPossessed(array $row): bool
    {
        return BdPhysicalSupport::isValid((string) ($row['support_physique'] ?? ''));
    }

    /** @param array<string, mixed> $row */
    public static function possessionStatusLabel(array $row): string
    {
        if (!self::isPossessed($row)) {
            return 'Non possédé';
        }

        return BdPhysicalSupport::label((string) ($row['support_physique'] ?? ''));
    }
}
