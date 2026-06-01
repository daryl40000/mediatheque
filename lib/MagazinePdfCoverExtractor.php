<?php
/**
 * Rendu de la première page d’un PDF magazine en image (pdftoppm / Poppler).
 */

declare(strict_types=1);

namespace Moncine;

final class MagazinePdfCoverExtractor
{
    public const COVER_PAGE = 1;

    /** Résolution suffisante pour les vignettes, compatible avec la limite affiche (2 Mo). */
    public const RENDER_DPI = 150;

    public static function isAvailable(): bool
    {
        return self::resolvePdftoppmPath() !== null;
    }

    /**
     * Retourne le binaire JPEG de la page 1, ou chaîne vide en cas d’échec.
     */
    public static function renderFirstPageJpeg(string $absolutePdfPath): string
    {
        if (!is_readable($absolutePdfPath)) {
            return '';
        }

        $pdftoppm = self::resolvePdftoppmPath();
        if ($pdftoppm === null) {
            return '';
        }

        $prefix = tempnam(sys_get_temp_dir(), 'moncine_cover_');
        if ($prefix === false) {
            return '';
        }

        @unlink($prefix);

        $command = sprintf(
            '%s -f %d -l %d -jpeg -r %d -singlefile %s %s 2>/dev/null',
            escapeshellarg($pdftoppm),
            self::COVER_PAGE,
            self::COVER_PAGE,
            self::RENDER_DPI,
            escapeshellarg($absolutePdfPath),
            escapeshellarg($prefix)
        );

        shell_exec($command);

        $jpegPath = $prefix . '.jpg';
        if (!is_file($jpegPath) || !is_readable($jpegPath)) {
            return '';
        }

        $binary = file_get_contents($jpegPath);
        @unlink($jpegPath);

        return is_string($binary) && $binary !== '' ? $binary : '';
    }

    private static function resolvePdftoppmPath(): ?string
    {
        $fromEnv = getenv('MONCINE_PDFTOPPM');
        if (is_string($fromEnv) && $fromEnv !== '' && is_executable($fromEnv)) {
            return $fromEnv;
        }

        foreach (['/usr/bin/pdftoppm', '/usr/local/bin/pdftoppm'] as $candidate) {
            if (is_executable($candidate)) {
                return $candidate;
            }
        }

        $which = shell_exec('command -v pdftoppm 2>/dev/null');
        if (is_string($which)) {
            $which = trim($which);
            if ($which !== '' && is_executable($which)) {
                return $which;
            }
        }

        return null;
    }
}
