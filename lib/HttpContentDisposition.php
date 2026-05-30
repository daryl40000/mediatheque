<?php
/**
 * En-têtes Content-Disposition conformes (nom ASCII + UTF-8 RFC 5987).
 */

declare(strict_types=1);

namespace Moncine;

final class HttpContentDisposition
{
    public static function sendInline(string $filename): void
    {
        header('Content-Disposition: ' . self::build('inline', $filename));
    }

    public static function sendAttachment(string $filename): void
    {
        header('Content-Disposition: ' . self::build('attachment', $filename));
    }

    public static function build(string $disposition, string $filename): string
    {
        $filename = basename(str_replace('\\', '/', $filename));
        if ($filename === '' || $filename === '.' || $filename === '..') {
            $filename = 'fichier';
        }

        $ascii = self::asciiFallback($filename);
        $utf8 = rawurlencode($filename);

        return $disposition . '; filename="' . $ascii . '"; filename*=UTF-8\'\'' . $utf8;
    }

    private static function asciiFallback(string $filename): string
    {
        $safe = preg_replace('/[^\x20-\x7E]/', '_', $filename);
        if ($safe === null || $safe === '') {
            return 'fichier';
        }
        $safe = str_replace(['"', '\\', "\r", "\n"], '_', $safe);

        return $safe !== '' ? $safe : 'fichier';
    }
}
