<?php
/**
 * Champs bibliothèque spécifiques aux jeux (Linux, prêt, plateformes possédées).
 */

declare(strict_types=1);

namespace Moncine;

use PDO;

final class GameLibraryFields
{
    public static function saveLinuxFlags(
        PDO $db,
        int $bibId,
        string $platform,
        bool $testedOnLinux,
        bool $linuxNotSupported
    ): void {
        if (!GameSchema::hasTestedOnLinuxColumn() || $bibId <= 0) {
            return;
        }

        if ($platform !== GamePlatform::PC) {
            $testedOnLinux = false;
            $linuxNotSupported = false;
        } elseif ($testedOnLinux && $linuxNotSupported) {
            $linuxNotSupported = false;
        }

        $sql = 'UPDATE bibliotheque SET tested_on_linux = ?';
        $params = [$testedOnLinux ? 1 : 0];

        if (GameSchema::hasLinuxNotSupportedColumn()) {
            $sql .= ', linux_not_supported = ?';
            $params[] = $linuxNotSupported ? 1 : 0;
        }

        $sql .= ' WHERE id = ?';
        $params[] = $bibId;

        $db->prepare($sql)->execute($params);
    }

    public static function saveNonPretable(PDO $db, int $bibId, bool $nonPretable): void
    {
        if (!GameSchema::hasNonPretableColumn() || $bibId <= 0) {
            return;
        }

        $db->prepare('UPDATE bibliotheque SET non_pretable = ? WHERE id = ?')
            ->execute([$nonPretable ? 1 : 0, $bibId]);
    }

    public static function saveOwnedPlatforms(PDO $db, int $bibId, string $ownedPlatformsCsv): void
    {
        if (!GameSchema::hasOwnedPlatformsColumn() || $bibId <= 0) {
            return;
        }

        $db->prepare('UPDATE bibliotheque SET owned_platforms = ? WHERE id = ?')
            ->execute([$ownedPlatformsCsv, $bibId]);
    }
}
