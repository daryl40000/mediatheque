<?php
/**
 * Métadonnées série BD stockées dans la table series (champ tags = type bd/manga/comic).
 */

declare(strict_types=1);

namespace Moncine;

final class BdSeriesMetadata
{
    /** @param array<string, mixed> $series */
    public static function kindFromSeries(array $series): string
    {
        $tags = trim((string) ($series['tags'] ?? ''));

        return BdKind::normalize($tags !== '' ? $tags : BdKind::BD);
    }

    public static function kindLabelFromSeries(array $series): string
    {
        return BdKind::label(self::kindFromSeries($series));
    }

    public static function kindForStorage(string $rawKind): string
    {
        return BdKind::normalize($rawKind);
    }
}
