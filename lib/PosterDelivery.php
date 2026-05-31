<?php
/**
 * En-têtes HTTP pour servir une affiche locale (lecture publique).
 */

declare(strict_types=1);

namespace Moncine;

final class PosterDelivery
{
    private const MIME_BY_EXT = [
        'jpg' => 'image/jpeg',
        'jpeg' => 'image/jpeg',
        'png' => 'image/png',
        'webp' => 'image/webp',
    ];

    public static function sendFile(string $absolutePath): void
    {
        $ext = strtolower(pathinfo($absolutePath, PATHINFO_EXTENSION));
        $mime = self::MIME_BY_EXT[$ext] ?? 'application/octet-stream';

        header('Content-Type: ' . $mime);
        header('Content-Length: ' . (string) filesize($absolutePath));
        header('X-Content-Type-Options: nosniff');
        header('Cache-Control: public, max-age=604800');
        HttpContentDisposition::sendInline(basename($absolutePath));
    }
}
