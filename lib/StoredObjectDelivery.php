<?php
/**
 * Lecture sécurisée d’un stored_object (MIME, en-têtes HTTP).
 */

declare(strict_types=1);

namespace Moncine;

final class StoredObjectDelivery
{
    /** Types affichables en inline dans le navigateur (admin). */
    private const INLINE_MIME_PREFIXES = [
        'image/',
    ];

    private const INLINE_MIME_EXACT = [
        'application/pdf',
        'text/plain',
    ];

    public static function normalizeMime(string $mime): string
    {
        $mime = strtolower(trim(explode(';', $mime)[0]));

        return $mime !== '' ? $mime : 'application/octet-stream';
    }

    public static function isInlineSafe(string $mime): bool
    {
        $mime = self::normalizeMime($mime);
        if (in_array($mime, self::INLINE_MIME_EXACT, true)) {
            return true;
        }
        foreach (self::INLINE_MIME_PREFIXES as $prefix) {
            if (str_starts_with($mime, $prefix)) {
                return true;
            }
        }

        return false;
    }

    /**
     * @param array<string, mixed> $row ligne stored_objects
     */
    public static function sendFile(array $row, string $absolutePath): void
    {
        $mime = self::normalizeMime((string) ($row['mime'] ?? ''));
        $filename = basename((string) ($row['relative_path'] ?? 'fichier'));

        if (!self::isInlineSafe($mime)) {
            $mime = 'application/octet-stream';
            HttpContentDisposition::sendAttachment($filename);
        } else {
            HttpContentDisposition::sendInline($filename);
        }

        header('Content-Type: ' . $mime);
        header('Content-Length: ' . (string) filesize($absolutePath));
        header('X-Content-Type-Options: nosniff');
        header('Cache-Control: private, no-store');
    }
}
