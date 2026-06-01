<?php
/**
 * Métadonnées PDF (nombre de pages…) via pdfinfo / Poppler.
 */

declare(strict_types=1);

namespace Moncine;

final class MagazinePdfInfo
{
    public static function isAvailable(): bool
    {
        return self::resolvePdfinfoPath() !== null;
    }

    /**
     * Lit le nombre de pages d’un PDF (0 si inconnu ou erreur).
     */
    public static function readPageCount(string $absolutePdfPath): int
    {
        if (!is_readable($absolutePdfPath)) {
            return 0;
        }

        $pdfinfo = self::resolvePdfinfoPath();
        if ($pdfinfo === null) {
            return 0;
        }

        $command = sprintf('%s %s 2>/dev/null', escapeshellarg($pdfinfo), escapeshellarg($absolutePdfPath));
        $output = shell_exec($command);
        if (!is_string($output) || $output === '') {
            return 0;
        }

        if (preg_match('/^Pages:\s*(\d+)/mi', $output, $matches) !== 1) {
            return 0;
        }

        return max(0, (int) $matches[1]);
    }

    private static function resolvePdfinfoPath(): ?string
    {
        $fromEnv = getenv('MONCINE_PDFINFO');
        if (is_string($fromEnv) && $fromEnv !== '' && is_executable($fromEnv)) {
            return $fromEnv;
        }

        foreach (['/usr/bin/pdfinfo', '/usr/local/bin/pdfinfo'] as $candidate) {
            if (is_executable($candidate)) {
                return $candidate;
            }
        }

        $which = shell_exec('command -v pdfinfo 2>/dev/null');
        if (is_string($which)) {
            $which = trim($which);
            if ($which !== '' && is_executable($which)) {
                return $which;
            }
        }

        return null;
    }
}
