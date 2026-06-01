<?php
/**
 * Extraction de texte des premières pages d’un PDF magazine (pdftotext / Poppler).
 */

declare(strict_types=1);

namespace Moncine;

final class MagazinePdfTextExtractor
{
    public const FIRST_PAGE = 1;
    public const LAST_PAGE = 6;

    /** Limite de caractères stockés en base (6 pages OCR typiques ≪ cette taille). */
    public const MAX_STORED_CHARS = 200_000;

    public static function isAvailable(): bool
    {
        return self::resolvePdftotextPath() !== null;
    }

    /**
     * Extrait le texte des pages 1 à 6 d’un PDF sur disque.
     */
    public static function extractFirstPages(string $absolutePdfPath): string
    {
        if (!is_readable($absolutePdfPath)) {
            return '';
        }

        $binary = self::resolvePdftotextPath();
        if ($binary === null) {
            return '';
        }

        $command = sprintf(
            '%s -f %d -l %d -layout %s - 2>/dev/null',
            escapeshellarg($binary),
            self::FIRST_PAGE,
            self::LAST_PAGE,
            escapeshellarg($absolutePdfPath)
        );

        $output = shell_exec($command);

        return self::normalizeForStorage(is_string($output) ? $output : '');
    }

    public static function normalizeForStorage(string $text): string
    {
        $text = str_replace("\0", '', $text);
        $text = preg_replace('/[ \t]+/u', ' ', $text) ?? $text;
        $text = preg_replace('/\R{3,}/u', "\n\n", $text) ?? $text;
        $text = trim($text);
        if ($text === '') {
            return '';
        }

        if (mb_strlen($text) > self::MAX_STORED_CHARS) {
            return mb_substr($text, 0, self::MAX_STORED_CHARS);
        }

        return $text;
    }

    private static function resolvePdftotextPath(): ?string
    {
        $fromEnv = getenv('MONCINE_PDFTOTEXT');
        if (is_string($fromEnv) && $fromEnv !== '' && is_executable($fromEnv)) {
            return $fromEnv;
        }

        foreach (['/usr/bin/pdftotext', '/usr/local/bin/pdftotext'] as $candidate) {
            if (is_executable($candidate)) {
                return $candidate;
            }
        }

        $which = shell_exec('command -v pdftotext 2>/dev/null');
        if (is_string($which)) {
            $which = trim($which);
            if ($which !== '' && is_executable($which)) {
                return $which;
            }
        }

        return null;
    }
}
